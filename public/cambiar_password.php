<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador', 'paciente']);

$pdo = conectarRol($_SESSION['rol']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['actual'] ?? '';
    $nueva  = $_POST['nueva'] ?? '';
    $conf   = $_POST['confirmar'] ?? '';
    $st = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $st->execute([$_SESSION['usuario_id']]);
    $hash = $st->fetchColumn();
    if (!$hash || !password_verify($actual, $hash)) $error = 'La contraseña actual no es correcta.';
    elseif (strlen($nueva) < 6)                      $error = 'La nueva contraseña debe tener mínimo 6 caracteres.';
    elseif ($nueva !== $conf)                        $error = 'La confirmación no coincide.';
    else {
        $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $_SESSION['usuario_id']]);
        registrarBitacora($pdo, 'cambio_password', 'usuarios', 'Contraseña actualizada');
        $_SESSION['flash'] = 'Contraseña actualizada.';
        header('Location: panel.php'); exit;
    }
}
encabezado('Cambiar contraseña');
if ($error) echo '<p class="alerta error">' . e($error) . '</p>';
?>
<form method="post" class="validar tarjeta formulario" novalidate>
  <label>Contraseña actual <input type="password" name="actual" required></label>
  <label>Nueva contraseña (mínimo 6) <input type="password" name="nueva" required minlength="6"></label>
  <label>Confirmar nueva contraseña <input type="password" name="confirmar" required minlength="6"></label>
  <div class="botones">
    <button type="submit">Guardar</button>
    <a class="boton secundario" href="panel.php">Regresar al panel principal</a>
  </div>
</form>
<?php pie(); ?>
