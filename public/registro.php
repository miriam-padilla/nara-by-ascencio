<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";

// ¿Quién está usando el formulario?
$rol = $_SESSION['rol'] ?? '';
$esPersonal = false;
if ($rol === 'administrador') {
    $esPersonal = true;
} elseif ($rol === 'trabajador') {
    $pdoP = conectarRol('trabajador');
    if (nivelPermiso($pdoP, 'pacientes') === 'editar') $esPersonal = true;
    else { header('Location: panel.php'); exit; }
} elseif ($rol === 'paciente') {
    header('Location: panel.php'); exit;
}
if (!$esPersonal && nivelRol('registro') !== 'editar') {
    encabezado('Registro no disponible');
    echo '<p>El registro en línea no está disponible por ahora. Escríbenos por WhatsApp y te registramos en el consultorio.</p><p><a class="boton" href="index.php">Regresar al inicio</a></p>';
    pie(); exit;
}
$salir = $esPersonal ? 'admin_tabla.php?t=paciente' : 'index.php';

$mensaje = ""; $ok = false;
$v = ['nombre' => '', 'edad' => '', 'sexo' => '', 'telefono' => '', 'email' => ''];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $password = $_POST["password"] ?? '';
    $aviso = isset($_POST['aviso']);

    // Todos los campos son obligatorios
    if (in_array('', $v, true) || $password === '') {
        $mensaje = "Debes llenar todos los campos.";
    } elseif (mb_strlen($v['nombre']) < 3) {
        $mensaje = "Escribe tu nombre completo.";
    } elseif (!ctype_digit($v['edad']) || (int)$v['edad'] > 120) {
        $mensaje = "La edad debe ser un número entre 0 y 120.";
    } elseif (!in_array($v['sexo'], ['F', 'M', 'Otro'], true)) {
        $mensaje = "Elige el sexo.";
    } elseif (!preg_match('/^\d{10}$/', $v['telefono'])) {
        $mensaje = "El teléfono debe tener 10 dígitos.";
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Escribe un correo válido.";
    } elseif (strlen($password) < 6) {
        $mensaje = "La contraseña debe tener mínimo 6 caracteres.";
    } elseif (!$aviso) {
        $mensaje = "Debes aceptar el aviso de privacidad.";
    } else {
        $pdo = conectarRol($esPersonal ? $rol : 'publico');
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'paciente')")
                ->execute([$v['nombre'], $v['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO paciente (usuario_id, edad, sexo, telefono) VALUES (?, ?, ?, ?)")
                ->execute([$uid, (int)$v['edad'], $v['sexo'], $v['telefono']]);
            registrarBitacora($pdo, 'registro', 'paciente',
                $esPersonal ? 'Paciente registrado por el personal' : 'Nuevo paciente registrado',
                $esPersonal ? (int)$_SESSION['usuario_id'] : $uid);
            $pdo->commit();
            $ok = true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = "No se pudo registrar. Es posible que ese correo ya exista.";
        }
    }
}
encabezado($esPersonal ? 'Registrar paciente' : 'Registro de paciente');
if ($ok) {
    modalExito('Tu información se agregó con éxito', $salir);
}
if ($mensaje && !$ok): ?><p class="alerta error"><?= e($mensaje) ?></p><?php endif; ?>
<form method="post" class="validar tarjeta formulario" novalidate>
  <label>Nombre completo <input name="nombre" required minlength="3" autocomplete="name" value="<?= e($ok ? '' : $v['nombre']) ?>"></label>
  <label>Edad <input type="number" name="edad" required min="0" max="120" value="<?= e($ok ? '' : $v['edad']) ?>"></label>
  <label>Sexo
    <select name="sexo" required>
      <option value="">Selecciona</option>
      <?php foreach (['F' => 'Femenino', 'M' => 'Masculino', 'Otro' => 'Otro'] as $k => $t): ?>
        <option value="<?= $k ?>" <?= (!$ok && $v['sexo'] === $k) ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Teléfono (10 dígitos) <input name="telefono" required data-tel inputmode="numeric" autocomplete="tel" value="<?= e($ok ? '' : $v['telefono']) ?>"></label>
  <label>Correo <input type="email" name="email" required autocomplete="email" value="<?= e($ok ? '' : $v['email']) ?>"></label>
  <label>Contraseña (mínimo 6 caracteres) <input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
  <label class="opcion"><input type="checkbox" name="aviso" required> Acepto haber leído el <a href="aviso.php" target="_blank">aviso de privacidad</a></label>
  <div class="botones">
    <button type="submit">Guardar</button>
    <a class="boton secundario" href="<?= $salir ?>">Salir</a>
  </div>
</form>
<?php pie(); ?>
