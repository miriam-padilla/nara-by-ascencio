<?php
// Acceso exclusivo para la administradora (nutrióloga) y el personal
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
define('MENU_MINIMO', true);

if (in_array($_SESSION['rol'] ?? '', ['administrador', 'trabajador'], true)) { header('Location: panel.php'); exit; }

$mensaje = ''; $okFoto = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'foto') {
    // Demostración: la foto se cambia desde la pantalla de acceso sin pedir credenciales
    $pdo = conectarRol('publico');
    $f = $_FILES['foto'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $mensaje = 'Elige una imagen.';
    } elseif ($f['size'] > 3 * 1024 * 1024) {
        $mensaje = 'La imagen pesa más de 3 MB.';
    } else {
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
        if (!$ext) {
            $mensaje = 'Solo se permiten imágenes JPG, PNG o WEBP.';
        } else {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $x) { $o = __DIR__ . "/img/nutriologa.$x"; if (is_file($o)) @unlink($o); }
            if (move_uploaded_file($f['tmp_name'], __DIR__ . "/img/nutriologa.$ext")) {
                registrarBitacora($pdo, 'editar', 'foto', 'Foto de la nutrióloga actualizada', null);
                $okFoto = 'Foto actualizada.';
            } else {
                $mensaje = 'No se pudo guardar. Revisa que la carpeta public/img permita escritura.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $pdo = conectarRol('publico');
    if ($password === '') {
        registrarBitacora($pdo, 'acceso_sin_password', 'usuarios', 'Intento de acceso al panel sin contraseña: ' . substr($email, 0, 100), null);
        $mensaje = 'Escribe tu contraseña.';
    } else {
        $st = $pdo->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE email = ? AND rol IN ('administrador','trabajador')");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $u['id'];
            $_SESSION['nombre'] = $u['nombre'];
            $_SESSION['rol'] = $u['rol'];
            registrarBitacora($pdo, 'login', 'usuarios', 'Inicio de sesión (panel)', (int)$u['id']);
            header('Location: panel.php');
            exit;
        }
        registrarBitacora($pdo, 'login_fallido', 'usuarios', 'Acceso fallido al panel con: ' . substr($email, 0, 100), null);
        $mensaje = 'Correo o contraseña incorrectos.';
    }
}
encabezado('Acceso del consultorio', false);
?>
<div class="acceso-admin">
  <img class="foto-admin" src="<?= e(fotoNutriologa()) ?>" alt="Nutrióloga Carolina Ascencio">
  <h1>Acceso del consultorio</h1>
  <?php if ($okFoto): ?><p class="alerta ok"><?= e($okFoto) ?></p><?php endif; ?>
  <?php if ($mensaje): ?><p class="alerta error"><?= e($mensaje) ?></p><?php endif; ?>
  <?php avisoFlash(); ?>
  <form method="post" class="validar tarjeta formulario" novalidate>
    <label>Correo <input type="email" name="email" required></label>
    <label>Contraseña <input type="password" name="password" required></label>
    <div class="botones"><button type="submit">Entrar</button><a class="boton secundario" href="index.php">Salir</a></div>
  </form>
  <details class="cambiar-foto">
    <summary>Cambiar foto</summary>
    <form method="post" enctype="multipart/form-data" class="tarjeta formulario">
      <input type="hidden" name="accion" value="foto">
      <label>Nueva foto <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required></label>
      <div class="botones"><button type="submit">Guardar foto</button></div>
    </form>
  </details>
</div>
<?php pie(); ?>
