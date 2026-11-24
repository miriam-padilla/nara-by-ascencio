<?php
// Permisos: qué puede hacer cada tipo de usuario en cada función de la página
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador']);

$pdo = conectarRol('administrador');
$secciones = seccionesPermisos();
$columnas = ['visitante' => 'Visitante', 'paciente' => 'Paciente', 'usuario' => 'Usuario (personal)'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'guardar_matriz') {
            $nueva = [];
            foreach ($secciones as $f => [$nombre, $aplica]) {
                foreach ($aplica as $r) {
                    $n = $_POST['perm'][$f][$r] ?? 'ninguno';
                    $nueva[$f][$r] = isset(NIVELES[$n]) ? $n : 'ninguno';
                }
            }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE usuarios SET permisos = ? WHERE id = ? AND rol = 'administrador'")->execute([json_encode($nueva), $_SESSION['usuario_id']]);
            registrarBitacora($pdo, 'permisos', 'usuarios', 'Tabla de permisos actualizada');
            $pdo->commit();
            $_SESSION['flash'] = 'Permisos guardados.';
            header('Location: permisos.php'); exit;
        } elseif ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $pass   = $_POST['password'] ?? '';
            if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
                $error = 'Escribe nombre, un correo válido y una contraseña de mínimo 6 caracteres.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'trabajador')")
                    ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                registrarBitacora($pdo, 'crear', 'usuarios', "Usuario (personal) creado: $nombre");
                $pdo->commit();
                $_SESSION['flash'] = 'Usuario creado.';
                header('Location: permisos.php'); exit;
            }
        } elseif ($accion === 'password') {
            $uid = (int)($_POST['usuario_id'] ?? 0);
            $nueva = $_POST['nueva_password'] ?? '';
            if (strlen($nueva) < 6) $error = 'La nueva contraseña debe tener mínimo 6 caracteres.';
            else {
                $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ? AND rol = 'trabajador'")->execute([password_hash($nueva, PASSWORD_DEFAULT), $uid]);
                registrarBitacora($pdo, 'cambio_password', 'usuarios', "Contraseña restablecida del usuario $uid");
                $_SESSION['flash'] = 'Contraseña actualizada.';
                header('Location: permisos.php'); exit;
            }
        } elseif ($accion === 'eliminar') {
            $uid = (int)($_POST['usuario_id'] ?? 0);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND rol = 'trabajador'")->execute([$uid]);
            registrarBitacora($pdo, 'eliminar', 'usuarios', "Usuario (personal) $uid eliminado");
            $pdo->commit();
            $_SESSION['flash'] = 'Usuario eliminado.';
            header('Location: permisos.php'); exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = ((int)($e->errorInfo[1] ?? 0) === 1062) ? 'Ya existe un usuario con ese correo.' : 'No se pudo completar la operación.';
    }
}

$actual = matrizPermisos($pdo);
$personal = $pdo->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'trabajador' ORDER BY nombre")->fetchAll();

encabezado('Permisos');
avisoFlash();
if ($error) echo '<p class="alerta error">' . e($error) . '</p>';
?>
<form method="post">
  <input type="hidden" name="accion" value="guardar_matriz">
  <div class="scroll"><table class="matriz">
    <tr><th>Función</th><?php foreach ($columnas as $c): ?><th><?= e($c) ?></th><?php endforeach; ?><th>Administrador</th></tr>
    <?php foreach ($secciones as $f => [$nombre, $aplica]): ?>
      <tr>
        <td><?= e($nombre) ?></td>
        <?php foreach ($columnas as $r => $_): ?>
          <td>
          <?php if (in_array($r, $aplica, true)): $n = $actual[$f][$r] ?? 'ninguno'; ?>
            <select name="perm[<?= e($f) ?>][<?= e($r) ?>]" aria-label="<?= e($nombre . ' - ' . $columnas[$r]) ?>">
              <?php foreach (ETIQUETAS_NIVEL as $k => $t): ?><option value="<?= $k ?>" <?= $n === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
          <?php else: ?>—<?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td>Permiso total</td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <div class="botones"><button>Guardar permisos</button><a class="boton secundario" href="panel.php">Salir</a></div>
</form>

<h2>Usuarios (personal)</h2>
<?php foreach ($personal as $t): ?>
  <form method="post" class="tarjeta fila-usuario">
    <input type="hidden" name="usuario_id" value="<?= (int)$t['id'] ?>">
    <span><strong><?= e($t['nombre']) ?></strong> · <?= e($t['email']) ?></span>
    <input type="password" name="nueva_password" minlength="6" placeholder="Nueva contraseña" autocomplete="new-password">
    <button name="accion" value="password" class="chico">Restablecer</button>
    <button name="accion" value="eliminar" class="chico peligro" onclick="return confirm('¿Eliminar a este usuario?')">Eliminar</button>
  </form>
<?php endforeach; ?>
<?php if (!$personal): ?><p>Todavía no hay personal registrado.</p><?php endif; ?>

<h2>Registrar usuario (personal)</h2>
<form method="post" class="tarjeta formulario validar" novalidate>
  <label>Nombre completo <input name="nombre" required minlength="3"></label>
  <label>Correo <input type="email" name="email" required></label>
  <label>Contraseña (mínimo 6 caracteres) <input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
  <div class="botones"><button name="accion" value="crear">Registrar</button></div>
</form>
<?php pie(); ?>
