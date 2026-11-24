<?php
// Valoración inicial del paciente (se abre desde Citas). Queda en el Historial clínico.
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/tablas.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
requerirSeccion($pdo, 'citas', 'editar');

$citaId = (int)($_GET['cita'] ?? $_POST['cita'] ?? 0);
$st = $pdo->prepare("SELECT a.id, a.fecha, a.estado, p.*, u.nombre, u.email, p.id AS pid
                     FROM agenda a JOIN paciente p ON p.id = a.paciente_id JOIN usuarios u ON u.id = p.usuario_id
                     WHERE a.id = ?");
$st->execute([$citaId]);
$c = $st->fetch();
if (!$c) { $_SESSION['flash_error'] = 'Cita no encontrada.'; header('Location: citas.php'); exit; }

$error = '';
$num = fn($v) => ($v === '' || $v === null) ? null : (is_numeric($v) ? (float)$v : false);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo  = $_POST['tipo_atencion'] ?? '';
    $peso  = $num($_POST['peso'] ?? '');
    $talla = $num($_POST['talla_cm'] ?? '');
    $cint  = $num($_POST['cintura_cm'] ?? '');
    $obj   = trim($_POST['objetivo'] ?? '');
    $aler  = trim($_POST['alergias'] ?? '');
    $obs   = trim($_POST['observaciones'] ?? '');
    if (!in_array($tipo, tiposAtencion(), true)) $error = 'Elige el tipo de atención.';
    elseif ($peso === false || $talla === false || $cint === false) $error = 'Peso, talla y cintura deben ser números.';
    elseif (($peso !== null && ($peso < 1 || $peso > 400)) || ($talla !== null && ($talla < 30 || $talla > 250))) $error = 'Revisa el peso (kg) y la talla (cm).';
    elseif ($peso === null || $talla === null) $error = 'El peso y la talla son obligatorios en la valoración inicial.';
    else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE paciente SET tipo_atencion = ?, peso_inicial = ?, talla_cm = ?, objetivo = ?, alergias = ?, activo = 'Si' WHERE id = ?")
                ->execute([$tipo, $peso, $talla, $obj ?: null, $aler ?: null, $c['pid']]);
            $pdo->prepare("INSERT INTO seguimiento (paciente_id, agenda_id, fecha, peso, cintura_cm, observaciones) VALUES (?, ?, CURDATE(), ?, ?, ?)")
                ->execute([$c['pid'], $c['id'], $peso, $cint, 'Valoración inicial. ' . $obs]);
            $pdo->prepare("UPDATE agenda SET estado = 'atendida' WHERE id = ? AND estado = 'programada'")->execute([$c['id']]);
            registrarBitacora($pdo, 'valoracion', 'paciente', 'Valoración inicial de ' . $c['nombre']);
            $pdo->commit();
            $_SESSION['flash'] = 'Valoración guardada en el historial clínico de ' . $c['nombre'] . '.';
            header('Location: citas.php'); exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'No se pudo guardar la valoración.';
        }
    }
}
$v = fn($k) => e((string)($_POST[$k] ?? $c[$k] ?? ''));
encabezado('Valoración inicial');
?>
<?php if ($error): ?><p class="alerta error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="validar hoja" novalidate>
  <input type="hidden" name="cita" value="<?= (int)$c['id'] ?>">
  <div class="hoja-enc">
    <div><strong>NARA by Ascencio</strong><span>Valoración nutricional inicial</span></div>
    <div class="folio">EXPEDIENTE<br><b>No. <?= str_pad((string)$c['pid'], 4, '0', STR_PAD_LEFT) ?></b><br>Fecha: <?= e($c['fecha']) ?></div>
  </div>
  <section class="hoja-sec">
    <h3>I. Datos del paciente</h3>
    <div class="campos">
      <div class="campo c2"><span>Nombre</span><div class="valor"><?= e($c['nombre']) ?></div></div>
      <div class="campo"><span>Edad</span><div class="valor"><?= e((string)$c['edad']) ?> años</div></div>
      <div class="campo"><span>Sexo</span><div class="valor"><?= e((string)$c['sexo']) ?></div></div>
      <div class="campo c2"><span>Teléfono</span><div class="valor"><?= e((string)$c['telefono']) ?></div></div>
      <div class="campo c2"><span>Correo</span><div class="valor"><?= e($c['email']) ?></div></div>
    </div>
  </section>
  <section class="hoja-sec">
    <h3>II. Motivo de consulta</h3>
    <div class="campos">
      <label class="campo c2"><span>Tipo de atención</span>
        <select name="tipo_atencion" required>
          <option value="">Seleccione</option>
          <?php foreach (tiposAtencion() as $t): ?><option <?= ($_POST['tipo_atencion'] ?? $c['tipo_atencion']) === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select></label>
      <label class="campo c2"><span>Objetivo del paciente</span><input type="text" name="objetivo" maxlength="255" value="<?= $v('objetivo') ?>"></label>
      <label class="campo c4"><span>Alergias e intolerancias</span><textarea name="alergias" rows="2"><?= $v('alergias') ?></textarea></label>
    </div>
  </section>
  <section class="hoja-sec">
    <h3>III. Somatometría</h3>
    <div class="campos">
      <label class="campo"><span>Peso (kg)</span><input type="number" name="peso" step="0.01" min="1" max="400" required value="<?= e((string)($_POST['peso'] ?? $c['peso_inicial'] ?? '')) ?>"></label>
      <label class="campo"><span>Talla (cm)</span><input type="number" name="talla_cm" step="0.01" min="30" max="250" required value="<?= $v('talla_cm') ?>"></label>
      <label class="campo"><span>Cintura (cm)</span><input type="number" name="cintura_cm" step="0.01" min="0" value="<?= e((string)($_POST['cintura_cm'] ?? '')) ?>"></label>
    </div>
  </section>
  <section class="hoja-sec">
    <h3>IV. Observaciones</h3>
    <div class="campos"><label class="campo c4"><textarea name="observaciones" rows="4"><?= e((string)($_POST['observaciones'] ?? '')) ?></textarea></label></div>
  </section>
  <div class="hoja-acciones"><button type="submit">Guardar valoración</button><a class="boton secundario" href="panel.php">Salir</a></div>
</form>
<?php pie(); ?>
