<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['paciente']);
requerirNivelRol('mi_plan', 'lectura');

$pdo = conectarRol('paciente');
$st = $pdo->prepare("SELECT id, plan_alimenticio FROM paciente WHERE usuario_id = ?");
$st->execute([$_SESSION['usuario_id']]);
$p = $st->fetch();

$st = $pdo->prepare("SELECT proxima_revision FROM seguimiento WHERE paciente_id = ? AND proxima_revision IS NOT NULL ORDER BY fecha DESC LIMIT 1");
$st->execute([$p['id']]);
$revision = $st->fetchColumn();
$st = $pdo->prepare("SELECT fecha, observaciones FROM seguimiento WHERE paciente_id = ? AND observaciones IS NOT NULL AND observaciones <> '' ORDER BY fecha DESC, id DESC LIMIT 10");
$st->execute([$p['id']]);
$comentarios = $st->fetchAll();
$hayNuevo = hayAvisoSeguimiento();
// Al entrar a Seguimiento se considera vista la actualización
$pdo->prepare("UPDATE paciente SET aviso_seguimiento = 0 WHERE id = ?")->execute([$p['id']]);
encabezado('Seguimiento: plan alimenticio');
if ($hayNuevo) echo '<div class="alerta ok">Tienes una nueva actualización de tu nutrióloga.</div>';
?>
<div class="tarjeta">
  <?php if (trim((string)$p['plan_alimenticio']) !== ''): ?>
    <p><?= nl2br(e($p['plan_alimenticio'])) ?></p>
    <?php if ($revision): ?><p><strong>Próxima revisión:</strong> <?= e($revision) ?></p><?php endif; ?>
  <?php else: ?>
    <p>Tu nutrióloga todavía no registra tu plan alimenticio. Aparecerá aquí después de tu consulta.</p>
  <?php endif; ?>
</div>
<h2>Comentarios de tu nutrióloga</h2>
<?php foreach ($comentarios as $c): ?>
  <div class="tarjeta"><small><?= e($c['fecha']) ?></small><p><?= nl2br(e($c['observaciones'])) ?></p></div>
<?php endforeach; ?>
<?php if (!$comentarios): ?><p>Todavía no hay comentarios.</p><?php endif; ?>
<?php pie(); ?>
