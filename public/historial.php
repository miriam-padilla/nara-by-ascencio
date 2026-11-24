<?php
// Historial clínico: expediente de SOLO CONSULTA
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/graficas.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
requerirSeccion($pdo, 'historial', 'lectura');

$q = trim($_GET['q'] ?? '');
$p = (int)($_GET['p'] ?? 0);

$pac = null;
if ($p > 0) {
    $st = $pdo->prepare("SELECT p.*, u.nombre, u.email, u.creado_en FROM paciente p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?");
    $st->execute([$p]);
    $pac = $st->fetch() ?: null;
}

encabezado($pac ? 'Historial clínico' : 'Pacientes');

if (!$pac):
    $st = $pdo->prepare("SELECT p.id, u.nombre, p.edad, p.tipo_atencion, p.activo FROM paciente p JOIN usuarios u ON u.id = p.usuario_id
                         WHERE u.nombre LIKE ? ORDER BY u.nombre");
    $st->execute(['%' . $q . '%']);
    $lista = $st->fetchAll();
?>
<form method="get" class="filtro buscador">
  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar paciente por nombre">
  <button>Buscar</button>
</form>
<div class="scroll"><table>
  <tr><th>Expediente</th><th>Paciente</th><th>Edad</th><th>Tipo de atención</th><th>En seguimiento</th><th></th></tr>
  <?php foreach ($lista as $f): ?>
    <tr><td>No. <?= str_pad((string)$f['id'], 4, '0', STR_PAD_LEFT) ?></td><td><?= e($f['nombre']) ?></td><td><?= e((string)$f['edad']) ?></td><td><?= e((string)$f['tipo_atencion']) ?></td><td><?= e($f['activo']) ?></td>
        <td><a class="boton chico" href="historial.php?p=<?= (int)$f['id'] ?>">Abrir expediente</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="6">No se encontraron pacientes.</td></tr><?php endif; ?>
</table></div>
<p><a class="boton secundario" href="panel.php">Salir</a></p>

<?php else:
    $st = $pdo->prepare("SELECT fecha, peso, cintura_cm, adherencia, observaciones, proxima_revision FROM seguimiento WHERE paciente_id = ? ORDER BY fecha DESC, id DESC");
    $st->execute([$p]);
    $seg = $st->fetchAll();

    $st = $pdo->prepare("SELECT a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, s.nombre AS servicio, a.estado
                         FROM agenda a JOIN servicio s ON s.id = a.servicio_id WHERE a.paciente_id = ? ORDER BY a.fecha DESC, a.hora DESC");
    $st->execute([$p]);
    $citas = $st->fetchAll();

    $st = $pdo->prepare("SELECT fecha_hora, accion, detalle FROM bitacora WHERE usuario_id = ? ORDER BY fecha_hora DESC LIMIT 100");
    $st->execute([$pac['usuario_id']]);
    $movs = $st->fetchAll();

    $pesoIni = $pac['peso_inicial'] !== null ? (float)$pac['peso_inicial'] : null;
    $talla = $pac['talla_cm'] !== null ? (float)$pac['talla_cm'] : null;
    $imc = ($pesoIni && $talla) ? round($pesoIni / pow($talla / 100, 2), 1) : null;
    $clasif = $imc === null ? '' : ($imc < 18.5 ? 'Bajo peso' : ($imc < 25 ? 'Peso normal' : ($imc < 30 ? 'Sobrepeso' : 'Obesidad')));
    $pesoActual = null; $cintura = null;
    foreach ($seg as $s) {   // el más reciente primero
        if ($pesoActual === null && $s['peso'] !== null) $pesoActual = (float)$s['peso'];
        if ($cintura === null && $s['cintura_cm'] !== null) $cintura = (float)$s['cintura_cm'];
    }
    $cambio = ($pesoActual !== null && $pesoIni !== null) ? round($pesoActual - $pesoIni, 1) : null;
    $dato = fn($v, $suf = '') => ($v === null || $v === '') ? '—' : e((string)$v) . $suf;
    $sexos = ['F' => 'Femenino', 'M' => 'Masculino', 'Otro' => 'Otro'];
    $notas = array_values(array_filter($seg, fn($s) => trim((string)$s['observaciones']) !== ''));
?>
<article class="hoja">
  <div class="hoja-enc">
    <div><strong>NARA by Ascencio</strong><span>Consultoría nutricional · Lic. Carolina Ascencio · Cédula prof. 9741268</span></div>
    <div class="folio">EXPEDIENTE CLÍNICO<br><b>No. <?= str_pad((string)$pac['id'], 4, '0', STR_PAD_LEFT) ?></b><br>Apertura: <?= e(substr((string)$pac['creado_en'], 0, 10) ?: date('Y-m-d')) ?></div>
  </div>

  <section class="hoja-sec">
    <h3>I. Ficha de identificación</h3>
    <div class="campos">
      <div class="campo c2"><span>Nombre del paciente</span><div class="valor"><?= e($pac['nombre']) ?></div></div>
      <div class="campo"><span>Edad</span><div class="valor"><?= $dato($pac['edad'], ' años') ?></div></div>
      <div class="campo"><span>Sexo</span><div class="valor"><?= e($sexos[$pac['sexo']] ?? '—') ?></div></div>
      <div class="campo"><span>Teléfono</span><div class="valor"><?= $dato($pac['telefono']) ?></div></div>
      <div class="campo c2"><span>Correo electrónico</span><div class="valor"><?= e($pac['email']) ?></div></div>
      <div class="campo"><span>En seguimiento</span><div class="valor"><?= e($pac['activo'] === 'Si' ? 'Sí' : 'No') ?></div></div>
    </div>
  </section>

  <section class="hoja-sec">
    <h3>II. Motivo de consulta y antecedentes</h3>
    <div class="campos">
      <div class="campo c2"><span>Tipo de atención</span><div class="valor"><?= $dato($pac['tipo_atencion']) ?></div></div>
      <div class="campo c2"><span>Objetivo del paciente</span><div class="valor"><?= $dato($pac['objetivo']) ?></div></div>
      <div class="campo c4"><span>Alergias e intolerancias</span><div class="valor"><?= $dato($pac['alergias']) ?></div></div>
    </div>
  </section>

  <section class="hoja-sec">
    <h3>III. Somatometría</h3>
    <div class="campos">
      <div class="campo"><span>Peso inicial</span><div class="valor"><?= $dato($pesoIni, ' kg') ?></div></div>
      <div class="campo"><span>Talla</span><div class="valor"><?= $dato($talla, ' cm') ?></div></div>
      <div class="campo"><span>IMC</span><div class="valor"><?= $imc !== null ? e((string)$imc) . ' <span class="imc-etiqueta">' . e($clasif) . '</span>' : '—' ?></div></div>
      <div class="campo"><span>Cintura (última)</span><div class="valor"><?= $dato($cintura, ' cm') ?></div></div>
      <div class="campo"><span>Peso actual</span><div class="valor"><?= $dato($pesoActual, ' kg') ?></div></div>
      <div class="campo"><span>Cambio de peso</span><div class="valor"><?= $cambio === null ? '—' : e(($cambio > 0 ? '+' : '') . $cambio) . ' kg' ?></div></div>
    </div>
  </section>

  <section class="hoja-sec">
    <h3>IV. Plan alimenticio</h3>
    <div class="campos"><div class="campo c4"><div class="valor bloque"><?= trim((string)$pac['plan_alimenticio']) !== '' ? e($pac['plan_alimenticio']) : 'Sin plan asignado.' ?></div></div></div>
  </section>

  <section class="hoja-sec">
    <h3>V. Notas de evolución</h3>
    <?= graficaPeso($seg) ?>
    <?php foreach ($notas as $n): ?>
      <div class="nota-evolucion">
        <div class="cab"><b><?= e($n['fecha']) ?></b><?= $n['peso'] !== null ? ' · Peso ' . e((string)$n['peso']) . ' kg' : '' ?><?= $n['cintura_cm'] !== null ? ' · Cintura ' . e((string)$n['cintura_cm']) . ' cm' : '' ?><?= $n['adherencia'] ? ' · Adherencia ' . e($n['adherencia']) : '' ?><?= $n['proxima_revision'] ? ' · Próxima revisión ' . e($n['proxima_revision']) : '' ?></div>
        <?= nl2br(e($n['observaciones'])) ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$notas): ?><p>Sin notas de evolución.</p><?php endif; ?>
  </section>

  <section class="hoja-sec">
    <h3>VI. Citas</h3>
    <div class="scroll"><table>
      <tr><th>Fecha</th><th>Hora</th><th>Servicio</th><th>Estado</th></tr>
      <?php foreach ($citas as $c): ?>
        <tr><td><?= e($c['fecha']) ?></td><td><?= e($c['hora']) ?></td><td><?= e($c['servicio']) ?></td>
            <td><span class="estado <?= e($c['estado']) ?>"><?= e($c['estado']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$citas): ?><tr><td colspan="4">Sin citas registradas.</td></tr><?php endif; ?>
    </table></div>
  </section>

  <section class="hoja-sec">
    <h3>VII. Registro de movimientos</h3>
    <div class="scroll"><table>
      <tr><th>Fecha y hora</th><th>Movimiento</th><th>Detalle</th></tr>
      <?php foreach ($movs as $m): ?>
        <tr><td><?= e($m['fecha_hora']) ?></td><td><?= e(etiquetaAccion($m['accion'])) ?></td><td><?= e((string)$m['detalle']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$movs): ?><tr><td colspan="3">Sin movimientos.</td></tr><?php endif; ?>
    </table></div>
  </section>

  <div class="hoja-pie">
    <small>Consultado el <?= date('Y-m-d H:i') ?></small>
    <div class="firma">Lic. Carolina Ascencio<small>Nutrióloga · Cédula prof. 9741268</small></div>
  </div>
  <div class="hoja-acciones">
    <button type="button" onclick="window.print()">Imprimir expediente</button>
    <a class="boton secundario" href="historial.php">Buscar otro paciente</a>
    <a class="boton secundario" href="panel.php">Salir</a>
  </div>
</article>
<?php endif; ?>
<?php pie(); ?>
