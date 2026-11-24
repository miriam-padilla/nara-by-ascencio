<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";

$mensaje = "";
// A dónde regresar después de iniciar sesión (solo destinos permitidos)
$destinos = ['agenda' => 'agenda.php', 'mis_citas' => 'mis_citas.php'];
$siguiente = $_POST['siguiente'] ?? $_GET['siguiente'] ?? '';
if (!isset($destinos[$siguiente])) $siguiente = '';
$servicioSel = (int)($_POST['servicio'] ?? $_GET['servicio'] ?? 0);
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    $pdo = conectarRol('publico');
    if ($password === '') {
        registrarBitacora($pdo, 'acceso_sin_password', 'usuarios', 'Intento de acceso sin contraseña: ' . substr($email, 0, 100), null);
        $mensaje = 'Escribe tu contraseña.';
    } else {
    $st = $pdo->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($password, $u["password"])) {
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $u["id"];
        $_SESSION["nombre"]     = $u["nombre"];
        $_SESSION["rol"]        = $u["rol"];
        registrarBitacora($pdo, 'login', 'usuarios', 'Inicio de sesión', (int)$u["id"]);
        $ir = 'panel.php';
        if ($u['rol'] === 'paciente' && $siguiente !== '') {
            $ir = $destinos[$siguiente] . (($siguiente === 'agenda' && $servicioSel > 0) ? '?servicio=' . $servicioSel : '');
        }
        header("Location: $ir");
        exit;
    }
    registrarBitacora($pdo, 'login_fallido', 'usuarios', 'Intento con: ' . substr($email, 0, 100), null);
    $mensaje = "Correo o contraseña incorrectos.";
    }
}
encabezado($siguiente === 'agenda' ? 'Inicia sesión para agendar tu cita' : ($siguiente === 'mis_citas' ? 'Inicia sesión para ver tus citas' : 'Inicia sesión con tu cuenta'));
?>
<?php if ($mensaje): ?><p class="alerta error"><?= e($mensaje) ?></p><?php endif; ?>
<form method="post" class="validar tarjeta formulario" novalidate>
  <input type="hidden" name="siguiente" value="<?= e($siguiente) ?>">
  <input type="hidden" name="servicio" value="<?= (int)$servicioSel ?>">
  <label>Correo <input type="email" name="email" required></label>
  <label>Contraseña <input type="password" name="password" required></label>
  <div class="botones">
    <button type="submit">Entrar</button>
    <a class="boton secundario" href="index.php">Salir</a>
  </div>
</form>
<?php if (nivelRol('registro') !== 'ninguno'): ?>
<p class="centrado">¿Aún no tienes cuenta? <a href="registro.php">Regístrate aquí</a> y después podrás agendar tu cita.</p>
<?php endif; ?>
<?php pie(); ?>
