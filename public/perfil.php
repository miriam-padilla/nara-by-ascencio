<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['paciente']);
requerirNivelRol('mis_datos', 'lectura');

$pdo = conectarRol('paciente');
$st = $pdo->prepare("SELECT u.nombre, u.email, p.edad, p.sexo, p.telefono, p.activo, p.tipo_atencion
                     FROM paciente p JOIN usuarios u ON u.id = p.usuario_id WHERE p.usuario_id = ?");
$st->execute([$_SESSION['usuario_id']]);
$d = $st->fetch();
$sexos = ['F' => 'Femenino', 'M' => 'Masculino', 'Otro' => 'Otro'];
$vacio = fn($v) => ($v === null || $v === '') ? 'No registrado' : $v;
encabezado('Mis datos');
?>
<div class="tarjeta formulario">
  <p><strong>Nombre:</strong> <?= e($d['nombre']) ?></p>
  <p><strong>Edad:</strong> <?= e($vacio($d['edad'])) ?></p>
  <p><strong>Sexo:</strong> <?= e($sexos[$d['sexo']] ?? 'No registrado') ?></p>
  <p><strong>Correo:</strong> <?= e($d['email']) ?></p>
  <p><strong>Teléfono:</strong> <?= e($vacio($d['telefono'])) ?></p>
  <p><strong>Motivo de consulta:</strong> <?= e($vacio($d['tipo_atencion'])) ?></p>
  <p><strong>En seguimiento actualmente:</strong> <?= e($d['activo']) ?></p>
</div>
<?php pie(); ?>
