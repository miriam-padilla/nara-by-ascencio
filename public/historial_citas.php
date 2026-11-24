<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
requerirSeccion($pdo, 'citas', 'lectura');

$q      = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? 'todas';

$sql = "SELECT a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, u.nombre AS paciente, s.nombre AS servicio, a.estado
        FROM agenda a JOIN paciente p ON p.id = a.paciente_id JOIN usuarios u ON u.id = p.usuario_id
        JOIN servicio s ON s.id = a.servicio_id
        WHERE u.nombre LIKE ? AND ";
$params = ['%' . $q . '%'];
if (in_array($estado, ['cancelada', 'atendida'], true)) {
    $sql .= "a.estado = ?";
    $params[] = $estado;
} else {
    $estado = 'todas';
    $sql .= "a.estado IN ('cancelada','atendida')";
}
$sql .= " ORDER BY a.fecha DESC, a.hora DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll();
encabezado('Historial de citas');
?>
<form method="get" class="filtro">
  <label>Paciente: <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nombre"></label>
  <label>Estado:
    <select name="estado">
      <option value="todas" <?= $estado === 'todas' ? 'selected' : '' ?>>Canceladas y atendidas</option>
      <option value="cancelada" <?= $estado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
      <option value="atendida" <?= $estado === 'atendida' ? 'selected' : '' ?>>Atendidas</option>
    </select>
  </label>
  <button>Filtrar</button>
</form>
<div class="scroll"><table>
  <tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Servicio</th><th>Estado</th></tr>
  <?php foreach ($filas as $f): ?>
    <tr><td><?= e($f['fecha']) ?></td><td><?= e($f['hora']) ?></td><td><?= e($f['paciente']) ?></td>
        <td><?= e($f['servicio']) ?></td><td><span class="estado <?= e($f['estado']) ?>"><?= e($f['estado']) ?></span></td></tr>
  <?php endforeach; ?>
  <?php if (!$filas): ?><tr><td colspan="5">Sin resultados.</td></tr><?php endif; ?>
</table></div>
<?php pie(); ?>
