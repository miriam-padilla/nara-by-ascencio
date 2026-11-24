<?php
require_once "../includes/auth.php";
require_once "../includes/layout.php";
require_once "../includes/serviciosinfo.php";

$id = (int)($_GET['id'] ?? 0);
$servicio = null;
if (!in_array($_SESSION['rol'] ?? '', ['administrador', 'trabajador'], true)) requerirNivelRol('ver_servicios', 'lectura');
foreach (listaServicios() as $s) { if ((int)$s['id'] === $id) { $servicio = $s; break; } }

if (!$servicio) {
    encabezado('Servicio no encontrado');
    echo '<p>No encontramos ese servicio.</p><p><a class="boton" href="index.php">Regresar al inicio</a></p>';
    pie(); exit;
}
$info = infoServicio($servicio);
$rol = $_SESSION['rol'] ?? '';
$categorias = ['nutricion' => 'Nutrición', 'corporal' => 'Tratamiento corporal', 'facial' => 'Tratamiento facial', 'producto' => 'Producto'];
$esProducto = $servicio['categoria'] === 'producto';
$agendar = $rol === 'paciente' ? 'agenda.php?servicio=' . $id : 'login.php?siguiente=agenda&servicio=' . $id;
$beneficios = $servicio['categoria'] === 'nutricion' ? 'Qué incluye' : 'Beneficios';

encabezado($servicio['nombre']);
?>
<div class="servicio-detalle">
  <div class="foto"><img src="<?= e(fotoServicio($servicio)) ?>" alt="<?= e($servicio['nombre']) ?>"></div>
  <div>
    <div class="etiquetas">
      <span class="etiqueta"><?= e($categorias[$servicio['categoria']] ?? '') ?></span>
      <span class="etiqueta"><?= e(textoCosto($servicio)) ?></span>
    </div>
    <h2>En qué consiste</h2>
    <p><?= e($info['consiste']) ?></p>
    <?php if ($info['beneficios']): ?>
      <h2><?= e($beneficios) ?></h2>
      <ul><?php foreach ($info['beneficios'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($info['para_quien']): ?>
      <h2>¿Para quién es?</h2>
      <ul><?php foreach ($info['para_quien'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($info['precauciones']): ?>
      <h2>Qué debes saber</h2>
      <ul><?php foreach ($info['precauciones'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ((float)$servicio['costo'] <= 0): ?>
      <p>Para conocer el costo, <a href="<?= e(enlaceWhatsApp('Hola, quiero informes de: ' . $servicio['nombre'])) ?>" target="_blank" rel="noopener">escríbenos por WhatsApp</a>.</p>
    <?php endif; ?>
    <div class="botones">
      <?php if (!in_array($rol, ['administrador', 'trabajador'], true) && !$esProducto): ?>
        <a class="boton" href="<?= e($agendar) ?>">Agendar cita</a>
      <?php endif; ?>
      <a class="boton secundario" href="index.php">Regresar al inicio</a>
    </div>
    <?php if ($rol === ''): ?><p class="nota">Para agendar necesitas una cuenta. Si ya tienes una, <a href="login.php">inicia sesión</a>.</p><?php endif; ?>
  </div>
</div>
<?php pie(); ?>
