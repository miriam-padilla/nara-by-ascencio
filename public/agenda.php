<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/citas.php";
requerirRol(['paciente']);
requerirNivelRol('mis_citas', 'editar');

$pdo = conectarRol('paciente');
$pid = pacienteIdActual($pdo);
$error = '';
$exito = '';

// ¿Reagendando una cita existente?
$reagendar = (int)($_GET['reagendar'] ?? $_POST['reagendar'] ?? 0);
$citaActual = null;
if ($reagendar > 0) {
    $st = $pdo->prepare("SELECT a.id, a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, s.nombre AS servicio
                         FROM agenda a JOIN servicio s ON s.id = a.servicio_id
                         WHERE a.id = ? AND a.paciente_id = ? AND a.estado = 'programada'");
    $st->execute([$reagendar, $pid]);
    $citaActual = $st->fetch() ?: null;
    if (!$citaActual) { $reagendar = 0; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? '';
    $hora  = $_POST['hora'] ?? '';
    if ($reagendar > 0) {
        [$ok, $texto] = reagendarCita($pdo, $reagendar, $pid, $fecha, $hora);
    } else {
        [$ok, $texto] = agendarCita($pdo, $pid, (int)($_POST['servicio_id'] ?? 0), $fecha, $hora);
    }
    if ($ok) { $exito = $reagendar > 0 ? 'Tu cita se modificó correctamente' : 'Tu cita se agendó correctamente'; }
    else { $error = $texto; }
    $_GET['fecha'] = $fecha;
}

// Mes del calendario
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) $mes = date('Y-m');
$primero = new DateTime("$mes-01");
$diasMes = (int)$primero->format('t');
$offset  = (int)$primero->format('N') - 1; // lunes = 0
$ant = (clone $primero)->modify('-1 month')->format('Y-m');
$sig = (clone $primero)->modify('+1 month')->format('Y-m');
$nombresMes = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Ocupación por día
$st = $pdo->prepare("SELECT fecha, COUNT(*) FROM agenda WHERE fecha BETWEEN ? AND ? AND estado IN ('programada','bloqueada') GROUP BY fecha");
$st->execute(["$mes-01", "$mes-$diasMes"]);
$ocupadas = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$fechaSel = $_GET['fecha'] ?? '';
$libres = []; $todas = [];
if ($fechaSel !== '' && fechaValida($fechaSel) === null) {
    $todas  = estadoHorarios($pdo, $fechaSel);
    $libres = array_keys(array_filter($todas));
} else {
    $fechaSel = '';
}
$servicios = $pdo->query("SELECT id, categoria, nombre, costo FROM servicio WHERE categoria IN ('nutricion','corporal','facial') ORDER BY categoria, id")->fetchAll();
$servicioSel = (int)($_GET['servicio'] ?? $_POST['servicio_id'] ?? 0);
if ($servicioSel === 0 && $servicios) { $servicioSel = (int)$servicios[0]['id']; }
$extra = ($reagendar > 0 ? "&reagendar=$reagendar" : '') . ($servicioSel > 0 ? "&servicio=$servicioSel" : '');

encabezado($reagendar > 0 ? 'Modificar cita' : 'Agendar cita');
if ($exito) { modalExito($exito, 'index.php', 'Salir'); }
if ($citaActual): ?>
  <p class="alerta">Modificando: <?= e($citaActual['servicio']) ?> del <?= e($citaActual['fecha']) ?> a las <?= e($citaActual['hora']) ?>.
     Elige el nuevo día y horario. <a href="mis_citas.php">Cancelar cambio</a></p>
<?php endif;
if ($error) echo '<p class="alerta error">' . e($error) . '</p>'; ?>

<p class="titulo-icono"><?= iconoCalendario(26) ?> <strong>Elige el día y la hora</strong></p>
<div class="calendario">
  <div class="cal-nav">
    <a href="agenda.php?mes=<?= $ant . $extra ?>">&laquo; Anterior</a>
    <strong><?= $nombresMes[(int)$primero->format('n')] . ' ' . $primero->format('Y') ?></strong>
    <a href="agenda.php?mes=<?= $sig . $extra ?>">Siguiente &raquo;</a>
  </div>
  <div class="cal-grid">
    <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?><div class="cal-cab"><?= $d ?></div><?php endforeach; ?>
    <?php for ($i = 0; $i < $offset; $i++): ?><div></div><?php endfor; ?>
    <?php for ($dia = 1; $dia <= $diasMes; $dia++):
        $f = sprintf('%s-%02d', $mes, $dia);
        $habil = fechaValida($f) === null;
        $llenos = ($ocupadas[$f] ?? 0) >= count(horasDelDia($f));
        $clase = 'cal-dia' . ($f === $fechaSel ? ' sel' : '') . (!$habil ? ' off' : '') . ($llenos ? ' lleno' : '');
        if ($habil && !$llenos): ?>
          <a class="<?= $clase ?>" href="agenda.php?mes=<?= $mes ?>&fecha=<?= $f . $extra ?>"><?= $dia ?></a>
        <?php else: ?>
          <div class="<?= $clase ?>"><?= $dia ?></div>
        <?php endif;
    endfor; ?>
  </div>
</div>

<?php if ($fechaSel !== ''): ?>
  <h2>Horarios del <?= e($fechaSel) ?></h2>
  <?php if (!$libres): ?>
    <p class="alerta error">No quedan horarios ese día. Elige otro.</p>
  <?php else: ?>
  <form method="post" class="validar tarjeta formulario" novalidate>
    <input type="hidden" name="fecha" value="<?= e($fechaSel) ?>">
    <input type="hidden" name="reagendar" value="<?= $reagendar ?>">
    <?php if ($reagendar === 0): ?>
      <label>Servicio
        <select name="servicio_id" required>
          <option value="">Selecciona un servicio</option>
          <?php foreach ($servicios as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $servicioSel === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <p><strong>Horario:</strong></p>
    <div class="horas">
      <?php foreach ($todas as $h => $libre): ?>
        <?php if ($libre): ?>
          <label class="hora"><input type="radio" name="hora" value="<?= $h ?>" required> <?= $h ?></label>
        <?php else: ?>
          <span class="hora off" title="No disponible"><?= $h ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="botones">
      <button type="submit">Guardar</button>
      <a class="boton secundario" href="index.php">Salir</a>
    </div>
  </form>
  <?php endif; ?>
<?php endif; ?>
<?php pie(); ?>
