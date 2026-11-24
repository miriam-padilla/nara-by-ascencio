<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/citas.php";
requerirRol(['paciente']);
requerirNivelRol('mis_citas', 'editar');

$pdo = conectarRol('paciente');
$pid = pacienteIdActual($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $texto] = cancelarCita($pdo, (int)($_POST['cita_id'] ?? 0), $pid);
    if ($ok) { $_SESSION['flash'] = $texto; header('Location: mis_citas.php'); exit; }
    $error = $texto;
}

$st = $pdo->prepare("SELECT a.id, a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, s.nombre AS servicio, a.estado
                     FROM agenda a JOIN servicio s ON s.id = a.servicio_id
                     WHERE a.paciente_id = ? ORDER BY a.fecha DESC, a.hora DESC");
$st->execute([$pid]);
$citas = $st->fetchAll();
encabezado('Cancelar o modificar cita');
avisoFlash();
if ($error) echo '<p class="alerta error">' . e($error) . '</p>';
?>
<div class="scroll"><table>
  <tr><th>Fecha</th><th>Hora</th><th>Servicio</th><th>Estado</th><th>Modificar o cancelar</th></tr>
  <?php foreach ($citas as $c): ?>
  <tr>
    <td><?= e($c['fecha']) ?></td><td><?= e($c['hora']) ?></td><td><?= e($c['servicio']) ?></td>
    <td><span class="estado <?= e($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
    <td class="acciones">
      <?php if ($c['estado'] === 'programada'): ?>
        <label class="opcion"><input type="checkbox" data-modificar="<?= (int)$c['id'] ?>"> Modificar</label>
        <form method="post" onsubmit="return confirm('¿Cancelar esta cita?')">
          <input type="hidden" name="cita_id" value="<?= (int)$c['id'] ?>">
          <button class="chico peligro">Cancelar</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$citas): ?><tr><td colspan="5">Todavía no tienes citas. <a href="agenda.php">Agendar una cita</a></td></tr><?php endif; ?>
</table></div>
<?php pie(); ?>
