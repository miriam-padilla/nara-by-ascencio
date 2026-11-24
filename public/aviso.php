<?php
require_once "../includes/auth.php";
require_once "../includes/layout.php";
require_once "../includes/sitio.php";
encabezado('Aviso de privacidad');
?>
<div class="tarjeta">
  <p><?= e(SITIO_NOMBRE) ?> guarda tus datos personales y de salud (nombre, contacto, peso, talla, alergias y seguimiento) únicamente para
  llevar tu consulta nutricional y administrar tus citas.</p>
  <p>Tus datos no se comparten con terceros. Solo tú y el personal autorizado del consultorio pueden verlos.
  Puedes pedir que se corrijan o se eliminen comunicándote al <?= e(SITIO_TEL_BONITO) ?>.</p>
</div>
<?php pie(); ?>
