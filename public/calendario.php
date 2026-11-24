<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/citas.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
$nivel       = requerirSeccion($pdo, 'calendario', 'lectura');
$puedeEditar = $nivel === 'editar';

$mes   = $_GET['mes'] ?? $_POST['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) $mes = date('Y-m');
$fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? '';
if ($fecha !== '' && !DateTime::createFromFormat('Y-m-d', $fecha)) $fecha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puedeEditar) {
        $_SESSION['flash_error'] = 'Solo tienes permiso de lectura en esta sección.';
    } else {
        $hora = $_POST['hora'] ?? '';
        switch ($_POST['accion'] ?? '') {
            case 'bloquear_hora':  [$ok, $txt] = bloquearHora($pdo, $fecha, $hora); break;
            case 'liberar_hora':   [$ok, $txt] = desbloquearHora($pdo, $fecha, $hora); break;
            case 'bloquear_dia':   [$ok, $txt] = bloquearDia($pdo, $fecha); break;
            case 'liberar_dia':    [$ok, $txt] = desbloquearDia($pdo, $fecha); break;
            default: [$ok, $txt] = [false, 'Acción no válida.'];
        }
        $_SESSION[$ok ? 'flash' : 'flash_error'] = $txt;
    }
    header("Location: calendario.php?mes=$mes&fecha=$fecha");
    exit;
}

$primero = new DateTime("$mes-01");
$diasMes = (int)$primero->format('t');
$offset  = (int)$primero->format('N') - 1;
$ant = (clone $primero)->modify('-1 month')->format('Y-m');
$sig = (clone $primero)->modify('+1 month')->format('Y-m');
$nombresMes = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$st = $pdo->prepare("SELECT fecha, estado, COUNT(*) FROM agenda WHERE fecha BETWEEN ? AND ? AND estado IN ('programada','bloqueada') GROUP BY fecha, estado");
$st->execute(["$mes-01", "$mes-$diasMes"]);
$conteo = [];
foreach ($st->fetchAll(PDO::FETCH_NUM) as [$f, $e, $n]) $conteo[$f][$e] = (int)$n;

$detalle = [];
if ($fecha !== '') {
    $st = $pdo->prepare("SELECT TIME_FORMAT(a.hora,'%H:%i') AS h, a.estado, u.nombre
                         FROM agenda a LEFT JOIN paciente p ON p.id = a.paciente_id LEFT JOIN usuarios u ON u.id = p.usuario_id
                         WHERE a.fecha = ? AND a.estado IN ('programada','bloqueada')");
    $st->execute([$fecha]);
    foreach ($st->fetchAll() as $r) $detalle[$r['h']] = $r;
}

$st = $pdo->prepare("SELECT a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS h, a.estado, u.nombre AS paciente, sv.nombre AS servicio
                     FROM agenda a LEFT JOIN paciente p ON p.id = a.paciente_id LEFT JOIN usuarios u ON u.id = p.usuario_id
                     LEFT JOIN servicio sv ON sv.id = a.servicio_id
                     WHERE a.fecha BETWEEN ? AND ? AND a.estado IN ('programada','bloqueada') ORDER BY a.fecha, a.hora");
$st->execute(["$mes-01", "$mes-$diasMes"]);
$agendadas = $st->fetchAll();

encabezado('Calendario');
avisoFlash();
?>
<div class="calendario">
  <div class="cal-nav">
    <a href="calendario.php?mes=<?= $ant ?>">&laquo; Anterior</a>
    <strong><?= $nombresMes[(int)$primero->format('n')] . ' ' . $primero->format('Y') ?></strong>
    <a href="calendario.php?mes=<?= $sig ?>">Siguiente &raquo;</a>
  </div>
  <div class="cal-grid">
    <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?><div class="cal-cab"><?= $d ?></div><?php endforeach; ?>
    <?php for ($i = 0; $i < $offset; $i++): ?><div></div><?php endfor; ?>
    <?php for ($dia = 1; $dia <= $diasMes; $dia++):
        $f = sprintf('%s-%02d', $mes, $dia);
        $domingo = (int)(new DateTime($f))->format('N') === 7;
        $usadas = array_sum($conteo[$f] ?? []);
        $lleno = !$domingo && $usadas >= count(horasDelDia($f));
        $clase = 'cal-dia' . ($f === $fecha ? ' sel' : '') . ($domingo ? ' off' : '') . ($lleno ? ' lleno' : '');
        if ($domingo): ?>
          <div class="<?= $clase ?>"><?= $dia ?></div>
        <?php else: ?>
          <a class="<?= $clase ?>" href="calendario.php?mes=<?= $mes ?>&fecha=<?= $f ?>"><?= $dia ?></a>
        <?php endif;
    endfor; ?>
  </div>
</div>

<?php if ($fecha !== '' && horasDelDia($fecha)): ?>
  <h2>Horarios del <?= e($fecha) ?></h2>
  <?php if ($puedeEditar): ?>
  <div class="botones">
    <form method="post" onsubmit="return confirm('¿Bloquear todos los horarios libres de este día?')">
      <input type="hidden" name="mes" value="<?= e($mes) ?>"><input type="hidden" name="fecha" value="<?= e($fecha) ?>">
      <button name="accion" value="bloquear_dia" class="peligro">Bloquear día completo</button>
    </form>
    <form method="post">
      <input type="hidden" name="mes" value="<?= e($mes) ?>"><input type="hidden" name="fecha" value="<?= e($fecha) ?>">
      <button name="accion" value="liberar_dia" class="secundario">Liberar bloqueos del día</button>
    </form>
  </div>
  <?php endif; ?>
  <div class="scroll"><table>
    <tr><th>Hora</th><th>Estado</th><th>Detalle</th><?php if ($puedeEditar): ?><th>Acción</th><?php endif; ?></tr>
    <?php foreach (horasDelDia($fecha) as $h):
        $r = $detalle[$h] ?? null;
        $estado = $r ? ($r['estado'] === 'bloqueada' ? 'bloqueada' : 'ocupada') : 'libre'; ?>
      <tr>
        <td><?= e($h) ?></td>
        <td><span class="estado <?= $estado ?>"><?= $estado === 'ocupada' ? 'con cita' : $estado ?></span></td>
        <td><?= $estado === 'ocupada' ? e($r['nombre']) : ($estado === 'bloqueada' ? 'Cerrado por el consultorio' : '—') ?></td>
        <?php if ($puedeEditar): ?>
        <td>
          <?php if ($estado !== 'ocupada'): ?>
          <form method="post">
            <input type="hidden" name="mes" value="<?= e($mes) ?>"><input type="hidden" name="fecha" value="<?= e($fecha) ?>">
            <input type="hidden" name="hora" value="<?= e($h) ?>">
            <?php if ($estado === 'libre'): ?>
              <button class="chico peligro" name="accion" value="bloquear_hora">Bloquear</button>
            <?php else: ?>
              <button class="chico" name="accion" value="liberar_hora">Liberar</button>
            <?php endif; ?>
          </form>
          <?php else: ?><?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<h2>Fechas y horarios agendados del mes</h2>
<div class="scroll"><table>
  <tr><th>Fecha</th><th>Hora</th><th>Estado</th><th>Paciente</th><th>Servicio</th></tr>
  <?php foreach ($agendadas as $a): ?>
    <tr><td><?= e($a['fecha']) ?></td><td><?= e($a['h']) ?></td>
        <td><span class="estado <?= $a['estado'] === 'bloqueada' ? 'bloqueada' : 'ocupada' ?>"><?= $a['estado'] === 'bloqueada' ? 'bloqueado' : 'con cita' ?></span></td>
        <td><?= e((string)$a['paciente']) ?: '—' ?></td><td><?= e((string)$a['servicio']) ?: '—' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$agendadas): ?><tr><td colspan="5">Sin citas ni bloqueos este mes.</td></tr><?php endif; ?>
</table></div>
<p><a class="boton secundario" href="panel.php">Salir</a></p>
<?php pie(); ?>
