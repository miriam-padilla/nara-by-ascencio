<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/graficas.php";
requerirRol(['paciente']);
requerirNivelRol('mi_historial', 'lectura');

$pdo = conectarRol('paciente');
$st = $pdo->prepare("SELECT id, peso_inicial, talla_cm, objetivo, alergias FROM paciente WHERE usuario_id = ?");
$st->execute([$_SESSION['usuario_id']]);
$ex = $st->fetch();

$st = $pdo->prepare("SELECT fecha, peso, cintura_cm, adherencia, observaciones, proxima_revision
                     FROM seguimiento WHERE paciente_id = ? ORDER BY fecha DESC");
$st->execute([$ex['id']]);
$seg = $st->fetchAll();

$imc = null;
if ($ex['peso_inicial'] && $ex['talla_cm']) {
    $m = (float)$ex['talla_cm'] / 100;
    $imc = round((float)$ex['peso_inicial'] / ($m * $m), 1);
}
$vacio = fn($v) => ($v === null || $v === '') ? 'Sin registrar' : $v;
encabezado('Historial clínico');
?>
<h2>Valoración inicial</h2>
<div class="tarjeta">
  <p><strong>Peso inicial:</strong> <?= e($vacio($ex['peso_inicial'])) ?> kg · <strong>Talla:</strong> <?= e($vacio($ex['talla_cm'])) ?> cm
     <?php if ($imc !== null): ?> · <strong>IMC:</strong> <?= $imc ?><?php endif; ?></p>
  <p><strong>Objetivo:</strong> <?= e($vacio($ex['objetivo'])) ?></p>
  <p><strong>Alergias o intolerancias:</strong> <?= e($vacio($ex['alergias'])) ?></p>
</div>

<h2>Mi evolución</h2>
<?= graficaPeso($seg) ?>
<div class="scroll"><table>
  <tr><th>Fecha</th><th>Peso (kg)</th><th>Cintura (cm)</th><th>Adherencia</th><th>Observaciones</th><th>Próxima revisión</th></tr>
  <?php foreach ($seg as $s): ?>
    <tr><td><?= e($s['fecha']) ?></td><td><?= e($s['peso']) ?></td><td><?= e($s['cintura_cm']) ?></td>
        <td><?= e($s['adherencia']) ?></td><td><?= e($s['observaciones']) ?></td><td><?= e($s['proxima_revision']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$seg): ?><tr><td colspan="6">Sin seguimientos todavía.</td></tr><?php endif; ?>
</table></div>
<?php pie(); ?>
