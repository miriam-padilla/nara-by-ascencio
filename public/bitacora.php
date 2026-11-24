<?php
// Movimientos: todo lo que hace cada tipo de usuario
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
requerirSeccion($pdo, 'movimientos', 'lectura');

$ok = fn($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1;
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? $desde;
if (!$ok($desde)) $desde = date('Y-m-d');
if (!$ok($hasta)) $hasta = $desde;
$tipo = $_GET['tipo'] ?? '';
$tipos = ['administrador' => 'Administrador', 'trabajador' => 'Usuario (personal)', 'paciente' => 'Paciente', 'visitante' => 'Visitante'];

$sql = "SELECT b.fecha_hora, COALESCE(u.nombre, 'Visitante') AS usuario, COALESCE(u.rol, 'visitante') AS rol, b.accion, b.detalle
        FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id
        WHERE DATE(b.fecha_hora) BETWEEN ? AND ?";
$par = [$desde, $hasta];
if (isset($tipos[$tipo])) {
    $sql .= $tipo === 'visitante' ? " AND u.id IS NULL" : " AND u.rol = ?";
    if ($tipo !== 'visitante') $par[] = $tipo;
}
$sql .= " ORDER BY b.fecha_hora DESC, b.id DESC LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($par);
$filas = $st->fetchAll();

encabezado('Movimientos');
?>
<form method="get" class="filtro">
  <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
  <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
  <label>Tipo de usuario
    <select name="tipo"><option value="">Todos</option>
      <?php foreach ($tipos as $k => $t): ?><option value="<?= $k ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select></label>
  <button>Buscar</button>
  <a class="boton secundario" href="panel.php">Salir</a>
</form>
<div class="scroll"><table>
  <tr><th>Fecha y hora</th><th>Usuario</th><th>Tipo</th><th>Movimiento</th><th>Detalle</th></tr>
  <?php foreach ($filas as $f): ?>
    <tr><td><?= e($f['fecha_hora']) ?></td><td><?= e($f['usuario']) ?></td><td><?= e($tipos[$f['rol']] ?? $f['rol']) ?></td>
        <td><?= e(etiquetaAccion($f['accion'])) ?></td><td><?= e((string)$f['detalle']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$filas): ?><tr><td colspan="5">Sin movimientos en esas fechas.</td></tr><?php endif; ?>
</table></div>
<?php pie(); ?>
