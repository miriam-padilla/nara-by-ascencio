<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
if (($_SESSION['rol'] ?? '') !== 'administrador') {
    $_SESSION['flash_error'] = 'Inicia sesión como administradora para cambiar la foto.';
    header('Location: admin.php'); exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['foto'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $error = 'Elige una imagen.';
    } elseif ($f['size'] > 3 * 1024 * 1024) {
        $error = 'La imagen pesa más de 3 MB.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) {
            $error = 'Solo se permiten imágenes JPG, PNG o WEBP.';
        } else {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $x) { $o = __DIR__ . "/img/nutriologa.$x"; if (is_file($o)) @unlink($o); }
            if (move_uploaded_file($f['tmp_name'], __DIR__ . "/img/nutriologa.$ext")) {
                registrarBitacora(conectarRol('administrador'), 'editar', 'foto', 'Foto de la nutrióloga actualizada');
                $_SESSION['flash'] = 'Foto actualizada.';
                header('Location: panel.php'); exit;
            }
            $error = 'No se pudo guardar. Revisa que la carpeta public/img permita escritura.';
        }
    }
}
encabezado('Cambiar foto');
?>
<?php if ($error): ?><p class="alerta error"><?= e($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="tarjeta formulario">
  <img class="foto-admin" src="<?= e(fotoNutriologa()) ?>" alt="Foto actual">
  <label>Nueva foto (JPG, PNG o WEBP, máximo 3 MB) <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required></label>
  <div class="botones"><button>Guardar</button><a class="boton secundario" href="panel.php">Salir</a></div>
</form>
<?php pie(); ?>
