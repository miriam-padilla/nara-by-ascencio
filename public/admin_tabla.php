<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
require_once "../includes/tablas.php";
requerirRol(['administrador', 'trabajador']);

$pdo = conectarRol($_SESSION['rol']);
$cfg = configTablas();
$t   = $_GET['t'] ?? '';
if (!isset($cfg[$t])) { header('Location: panel.php'); exit; }
$c = $cfg[$t];

$nivel       = requerirSeccion($pdo, $c['seccion'], 'lectura');
$puedeEditar = $nivel === 'editar';
$esAdmin     = $_SESSION['rol'] === 'administrador';

$id     = (int)($_GET['id'] ?? 0);
$accion = $_GET['accion'] ?? '';
$error  = '';
$valores = [];

function volver(string $t, int $pid = 0): string
{
    if ($t === 'seguimiento') return 'historial.php' . ($pid > 0 ? "?p=$pid" : '');
    return "admin_tabla.php?t=$t";
}

if ($t === 'seguimiento' && $accion === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: historial.php');
    exit;
}
if (($accion === 'nuevo' || $accion === 'editar') && !$puedeEditar) {
    $_SESSION['flash_error'] = 'Solo tienes permiso de lectura en esta sección.';
    header('Location: ' . volver($t));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPost  = (int)($_POST['id'] ?? 0);
    $pidPost = (int)($_POST['paciente_id'] ?? 0);

    if (!$puedeEditar) {
        $_SESSION['flash_error'] = 'Solo tienes permiso de lectura en esta sección.';
        header('Location: ' . volver($t, $pidPost));
        exit;
    }

    if (($_POST['operacion'] ?? '') === 'eliminar') {
        if (!$esAdmin) {
            $_SESSION['flash_error'] = 'Solo la administradora puede eliminar registros.';
            header('Location: ' . volver($t, $pidPost));
            exit;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM $t WHERE id = ?")->execute([$idPost]);
            registrarBitacora($pdo, 'eliminar', $t, "Registro $idPost eliminado");
            $pdo->commit();
            $_SESSION['flash'] = 'Registro eliminado.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'No se puede eliminar: tiene registros relacionados.';
        }
        header('Location: ' . volver($t, $pidPost));
        exit;
    }

    $cols = []; $vals = [];
    foreach ($c['campos'] as $col => $def) {
        $v = trim($_POST[$col] ?? '');
        $valores[$col] = $v;
        if ($v === '') {
            if ($def['req']) $error = 'Llena el campo: ' . $def['etiqueta'];
            $v = null;
        } elseif ($def['tipo'] === 'enum' && !in_array($v, $def['opciones'], true)) {
            $error = 'Valor no válido en: ' . $def['etiqueta'];
        }
        $cols[] = $col;
        $vals[] = $v;
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            if ($idPost > 0) {
                $set = implode(', ', array_map(fn($x) => "$x = ?", $cols));
                $pdo->prepare("UPDATE $t SET $set WHERE id = ?")->execute([...$vals, $idPost]);
                registrarBitacora($pdo, 'editar', $t, "Registro $idPost editado");
            } else {
                $marcas = implode(', ', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO $t (" . implode(', ', $cols) . ") VALUES ($marcas)")->execute($vals);
                registrarBitacora($pdo, 'crear', $t, "Registro " . $pdo->lastInsertId() . " creado");
            }
            $pdo->commit();
            $_SESSION['flash'] = 'Guardado correctamente.';
            header('Location: ' . volver($t, $pidPost));
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $cod = (int)($e->errorInfo[1] ?? 0);
            $error = match ($cod) {
                1062 => 'Ya existe un registro igual (horario ocupado o paciente duplicado).',
                1452 => 'El paciente, servicio o usuario elegido no existe.',
                default => 'No se pudo guardar. Revisa los datos.',
            };
        }
    }
    $id = $idPost;
    $accion = $idPost > 0 ? 'editar' : 'nuevo';
}

if ($accion === 'editar' && $id > 0 && !$valores) {
    $st = $pdo->prepare("SELECT * FROM $t WHERE id = ?");
    $st->execute([$id]);
    $valores = $st->fetch() ?: [];
}
if ($accion === 'nuevo' && $t === 'seguimiento' && !$valores) {
    $valores = ['paciente_id' => (int)($_GET['paciente_id'] ?? 0) ?: '', 'fecha' => date('Y-m-d')];
}

encabezado($c['titulo']);
?>
<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
  <?php if ($error): ?><p class="alerta error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="validar tarjeta formulario" novalidate>
    <input type="hidden" name="id" value="<?= $accion === 'editar' ? $id : 0 ?>">
    <?php foreach ($c['campos'] as $col => $d):
        $val = $valores[$col] ?? '';
        $req = $d['req'] ? 'required' : ''; ?>
      <label><?= e($d['etiqueta']) ?>
      <?php if ($d['tipo'] === 'textarea'): ?>
        <textarea name="<?= $col ?>" rows="3"><?= e((string)$val) ?></textarea>
      <?php elseif ($d['tipo'] === 'enum'): ?>
        <select name="<?= $col ?>" <?= $req ?>>
          <option value="">Selecciona</option>
          <?php foreach ($d['opciones'] as $o): ?>
            <option value="<?= e($o) ?>" <?= (string)$val === $o ? 'selected' : '' ?>><?= e($o) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif ($d['tipo'] === 'fk'): $opc = $pdo->query($d['sql'])->fetchAll(); ?>
        <select name="<?= $col ?>" <?= $req ?>>
          <option value="">Selecciona</option>
          <?php foreach ($opc as $o): ?>
            <option value="<?= (int)$o['id'] ?>" <?= (string)$val === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['etiqueta']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$opc): ?><span class="msg-error">No hay opciones todavía. Primero crea el registro relacionado (por ejemplo, registra al paciente).</span><?php endif; ?>
      <?php else: ?>
        <input type="<?= $d['tipo'] ?>" name="<?= $col ?>" value="<?= e((string)$val) ?>"
               <?= isset($d['step']) ? 'step="' . $d['step'] . '"' : '' ?> <?= $req ?>>
      <?php endif; ?>
      </label>
    <?php endforeach; ?>
    <div class="botones">
      <button type="submit" name="operacion" value="guardar">Guardar</button>
      <a class="boton secundario" href="<?= e(volver($t, (int)($valores['paciente_id'] ?? 0))) ?>">Salir</a>
    </div>
  </form>
<?php else: ?>
  <?php avisoFlash(); ?>
  <p>
    <?php if ($puedeEditar && $t === 'paciente'): ?>
      <a class="boton" href="registro.php">+ Registrar paciente nuevo</a>
    <?php elseif ($puedeEditar): ?>
      <a class="boton" href="admin_tabla.php?t=<?= e($t) ?>&accion=nuevo">+ Nuevo registro</a>
    <?php endif; ?>
  </p>
  <?php $filas = $pdo->query($c['lista'])->fetchAll(); ?>
  <div class="scroll">
  <table>
    <?php if ($filas): ?>
    <tr>
      <?php foreach (array_keys($filas[0]) as $h): if ($h === 'id') continue; ?>
        <th><?= e(ucfirst(str_replace('_', ' ', $h))) ?></th>
      <?php endforeach; ?>
      <?php if ($puedeEditar): ?><th>Acciones</th><?php endif; ?>
    </tr>
    <?php foreach ($filas as $f): ?>
    <tr>
      <?php foreach ($f as $k => $v): if ($k === 'id') continue; ?>
        <td><?= e((string)$v) ?></td>
      <?php endforeach; ?>
      <?php if ($puedeEditar): ?>
      <td class="acciones">
        <a class="boton chico" href="admin_tabla.php?t=<?= e($t) ?>&accion=editar&id=<?= (int)$f['id'] ?>">Editar</a>
        <?php if ($esAdmin): ?>
        <form method="post" onsubmit="return confirm('¿Eliminar este registro?')">
          <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <button class="chico peligro" name="operacion" value="eliminar">Eliminar</button>
        </form>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php else: ?>
      <tr><td>No hay registros todavía.</td></tr>
    <?php endif; ?>
  </table>
  </div>
<?php endif; ?>
<?php pie(); ?>
