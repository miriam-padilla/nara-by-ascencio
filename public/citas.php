<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
$nivel = requerirSeccion($pdo, 'citas', 'lectura');
$puedeEditar = $nivel === 'editar';

$ver = $_GET['ver'] ?? 'hoy';
$ver = $ver === 'todas' ? 'todas' : 'hoy';
$sql = "SELECT a.id, a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, u.nombre AS paciente, p.telefono, s.nombre AS servicio
        FROM agenda a JOIN paciente p ON p.id = a.paciente_id JOIN usuarios u ON u.id = p.usuario_id
        JOIN servicio s ON s.id = a.servicio_id
        WHERE a.estado = 'programada' AND " . ($ver === 'hoy' ? "a.fecha = CURDATE()" : "a.fecha >= CURDATE()") . "
        ORDER BY a.fecha, a.hora";
$filas = $pdo->query($sql)->fetchAll();

encabezado('Citas');
avisoFlash();
?>
<div class="filtro">
  <a class="boton <?= $ver === 'hoy' ? '' : 'secundario' ?>" href="citas.php?ver=hoy">Citas de hoy</a>
  <a class="boton <?= $ver === 'todas' ? '' : 'secundario' ?>" href="citas.php?ver=todas">Todas las próximas</a>
</div>
<div class="scroll"><table>
  <tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Teléfono</th><th>Servicio</th><th></th></tr>
  <?php foreach ($filas as $f): ?>
    <tr><td><?= e($f['fecha']) ?></td><td><?= e($f['hora']) ?></td><td><?= e($f['paciente']) ?></td>
        <td><?= e($f['telefono']) ?></td><td><?= e($f['servicio']) ?></td>
        <td class="acciones">
          <?php if ($puedeEditar): ?><a class="boton chico" href="valoracion.php?cita=<?= (int)$f['id'] ?>">Valoración</a>
          <a class="boton chico secundario" href="admin_tabla.php?t=agenda&accion=editar&id=<?= (int)$f['id'] ?>">Modificar</a><?php endif; ?>
        </td></tr>
  <?php endforeach; ?>
  <?php if (!$filas): ?><tr><td colspan="6"><?= $ver === 'hoy' ? 'No hay citas activas para hoy.' : 'No hay citas próximas.' ?></td></tr><?php endif; ?>
</table></div>
<p><a class="boton secundario" href="panel.php">Salir</a></p>
<?php pie(); ?>
