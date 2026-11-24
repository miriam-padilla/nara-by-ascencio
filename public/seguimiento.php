<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
$nivel = requerirSeccion($pdo, 'seguimiento', 'lectura');
$puedeEditar = $nivel === 'editar';

$q = trim($_GET['q'] ?? '');
$p = (int)($_GET['p'] ?? $_POST['p'] ?? 0);

$pac = null;
if ($p > 0) {
    $st = $pdo->prepare("SELECT p.id, p.telefono, p.plan_alimenticio, p.aviso_seguimiento, u.nombre FROM paciente p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?");
    $st->execute([$p]);
    $pac = $st->fetch() ?: null;
}

if ($pac && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puedeEditar) { $_SESSION['flash_error'] = 'Solo tienes permiso de lectura.'; header("Location: seguimiento.php?p=$p"); exit; }
    $accion = $_POST['accion'] ?? 'guardar';
    $plan = trim($_POST['plan'] ?? '');
    $coment = trim($_POST['comentario'] ?? '');
    $peso = $_POST['peso'] ?? '';
    $rev = $_POST['proxima_revision'] ?? '';
    $err = '';
    if ($peso !== '' && (!is_numeric($peso) || $peso < 1 || $peso > 400)) $err = 'Peso no válido.';
    if ($rev !== '' && !DateTime::createFromFormat('Y-m-d', $rev)) $err = 'Fecha de revisión no válida.';
    if ($err === '') {
        try {
            $pdo->beginTransaction();
            $aviso = $accion === 'actualizar' ? 1 : (int)$pac['aviso_seguimiento'];
            $pdo->prepare("UPDATE paciente SET plan_alimenticio = ?, aviso_seguimiento = ? WHERE id = ?")->execute([$plan !== '' ? $plan : null, $aviso, $p]);
            if ($coment !== '' || $peso !== '' || $rev !== '') {
                $pdo->prepare("INSERT INTO seguimiento (paciente_id, fecha, peso, observaciones, proxima_revision) VALUES (?, CURDATE(), ?, ?, ?)")
                    ->execute([$p, $peso !== '' ? $peso : null, $coment !== '' ? $coment : null, $rev !== '' ? $rev : null]);
            }
            if (isset($_POST['editar_id']) && (int)$_POST['editar_id'] > 0) {
                $pdo->prepare("UPDATE seguimiento SET observaciones = ? WHERE id = ? AND paciente_id = ?")
                    ->execute([trim($_POST['editar_texto'] ?? ''), (int)$_POST['editar_id'], $p]);
            }
            registrarBitacora($pdo, $accion === 'actualizar' ? 'actualizar_seguimiento' : 'seguimiento', 'seguimiento', ($accion === 'actualizar' ? 'Aviso de actualización enviado a ' : 'Seguimiento guardado de ') . $pac['nombre']);
            $pdo->commit();
            $_SESSION['flash'] = $accion === 'actualizar' ? 'Seguimiento actualizado. El paciente verá el aviso en su cuenta.' : 'Seguimiento guardado.';
            if ($accion === 'actualizar') $_SESSION['wa_aviso'] = $p;
            header("Location: seguimiento.php?p=$p"); exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'No se pudo guardar.';
        }
    }
    $_SESSION['flash_error'] = $err;
    header("Location: seguimiento.php?p=$p"); exit;
}

encabezado('Seguimiento');
avisoFlash();
if (!$pac):
    $st = $pdo->prepare("SELECT p.id, u.nombre, p.tipo_atencion FROM paciente p JOIN usuarios u ON u.id = p.usuario_id WHERE u.nombre LIKE ? ORDER BY u.nombre LIMIT 50");
    $st->execute(['%' . $q . '%']);
    $lista = $q !== '' ? $st->fetchAll() : [];
?>
<form method="get" class="filtro buscador">
  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar paciente por nombre" autofocus>
  <button>Buscar</button>
</form>
<?php if ($q !== ''): ?>
  <div class="resultados">
    <?php foreach ($lista as $f): ?>
      <a href="seguimiento.php?p=<?= (int)$f['id'] ?>"><strong><?= e($f['nombre']) ?></strong><span><?= e((string)$f['tipo_atencion']) ?></span></a>
    <?php endforeach; ?>
    <?php if (!$lista): ?><p>No se encontraron pacientes.</p><?php endif; ?>
  </div>
<?php endif; ?>
<p><a class="boton secundario" href="panel.php">Salir</a></p>
<?php else:
    $st = $pdo->prepare("SELECT id, fecha, peso, observaciones, proxima_revision FROM seguimiento WHERE paciente_id = ? ORDER BY fecha DESC, id DESC");
    $st->execute([$p]);
    $coments = $st->fetchAll();
    $wa = null;
    if (($_SESSION['wa_aviso'] ?? 0) === $p) {
        unset($_SESSION['wa_aviso']);
        $d = preg_replace('/\D/', '', (string)$pac['telefono']);
        if (strlen($d) === 10) $d = '52' . $d;
        if ($d !== '') $wa = 'https://wa.me/' . $d . '?text=' . rawurlencode('Hola ' . $pac['nombre'] . ', tienes una nueva actualización en tu seguimiento de NARA by Ascencio. Entra a tu cuenta para consultarla.');
    }
?>
<?php if ($wa): ?><p class="alerta ok">Aviso guardado. <a class="boton chico" href="<?= e($wa) ?>" target="_blank" rel="noopener">Enviar aviso por WhatsApp</a></p><?php endif; ?>
<?php $ro = $puedeEditar ? '' : 'readonly'; ?>
<form method="post" class="validar hoja" novalidate>
  <input type="hidden" name="p" value="<?= (int)$p ?>">
  <div class="hoja-enc">
    <div><strong>NARA by Ascencio</strong><span>Seguimiento nutricional</span></div>
    <div class="folio">EXPEDIENTE<br><b>No. <?= str_pad((string)$p, 4, '0', STR_PAD_LEFT) ?></b><br>Fecha: <?= date('Y-m-d') ?></div>
  </div>
  <section class="hoja-sec">
    <h3>I. Paciente</h3>
    <div class="campos">
      <div class="campo c2"><span>Nombre</span><div class="valor"><?= e($pac['nombre']) ?></div></div>
      <div class="campo"><span>Teléfono</span><div class="valor"><?= e((string)$pac['telefono']) ?></div></div>
      <div class="campo"><span>Aviso al paciente</span><div class="valor"><?= $pac['aviso_seguimiento'] ? 'Pendiente de ver' : 'Sin pendientes' ?></div></div>
    </div>
  </section>
  <section class="hoja-sec">
    <h3>II. Plan alimenticio</h3>
    <div class="campos"><label class="campo c4"><textarea name="plan" rows="8" <?= $ro ?>><?= e((string)$pac['plan_alimenticio']) ?></textarea></label></div>
  </section>
  <section class="hoja-sec">
    <h3>III. Nota de evolución</h3>
    <div class="campos">
      <label class="campo"><span>Peso actual (kg)</span><input type="number" name="peso" step="0.01" min="1" max="400" <?= $ro ?>></label>
      <label class="campo"><span>Próxima revisión</span><input type="date" name="proxima_revision" <?= $ro ?>></label>
      <label class="campo c4"><span>Comentario</span><textarea name="comentario" rows="3" <?= $ro ?>></textarea></label>
    </div>
  </section>
  <div class="hoja-acciones">
    <?php if ($puedeEditar): ?>
      <button type="submit" name="accion" value="guardar">Guardar</button>
      <button type="submit" name="accion" value="actualizar" class="acento">ACTUALIZAR</button>
    <?php endif; ?>
    <a class="boton secundario" href="panel.php">Salir</a>
  </div>
</form>

<?php $prev = array_values(array_filter($coments, fn($c) => trim((string)$c['observaciones']) !== '')); ?>
<div class="hoja">
  <section class="hoja-sec">
    <h3>IV. Notas anteriores</h3>
    <?php foreach ($prev as $c): ?>
      <div class="nota-evolucion">
        <div class="cab"><b><?= e($c['fecha']) ?></b><?= $c['peso'] ? ' · Peso ' . e((string)$c['peso']) . ' kg' : '' ?></div>
        <?php if ($puedeEditar): ?>
        <form method="post"><input type="hidden" name="p" value="<?= (int)$p ?>"><input type="hidden" name="editar_id" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="plan" value="<?= e((string)$pac['plan_alimenticio']) ?>">
          <div class="campos"><label class="campo c4"><textarea name="editar_texto" rows="2"><?= e($c['observaciones']) ?></textarea></label></div>
          <button class="chico" name="accion" value="guardar">Modificar</button>
        </form>
        <?php else: ?><?= nl2br(e($c['observaciones'])) ?><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$prev): ?><p>Sin notas anteriores.</p><?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php pie(); ?>
