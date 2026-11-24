<?php
// Agregar producto o servicio (m=servicio) y Promociones (m=promocion)
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador']);

$modo = ($_GET['m'] ?? $_POST['m'] ?? '') === 'promocion' ? 'promocion' : 'servicio';
$promo = $modo === 'promocion';
$pdo = conectarRol($_SESSION['rol']);
$nivel = requerirSeccion($pdo, $promo ? 'promociones' : 'productos', 'lectura');
$puedeEditar = $nivel === 'editar';
$titulo = $promo ? 'Promociones' : 'Agregar producto o servicio';

function guardarImagen(?array $f, string &$error): ?string
{
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024) { $error = 'La imagen no se pudo subir (máximo 3 MB).'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) { $error = 'Solo se permiten imágenes JPG, PNG o WEBP.'; return null; }
    $dir = __DIR__ . '/img/catalogo';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nombre = 'img_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nombre")) { $error = 'No se pudo guardar la imagen (revisa permisos de public/img/catalogo).'; return null; }
    return $nombre;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puedeEditar) {
        $_SESSION['flash_error'] = 'Solo tienes permiso de lectura en esta sección.';
        header("Location: catalogo.php?m=$modo"); exit;
    }
    $op = $_POST['operacion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($op === 'eliminar') {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM servicio WHERE id = ? AND categoria " . ($promo ? "= 'promocion'" : "<> 'promocion'"))->execute([$id]);
            registrarBitacora($pdo, 'eliminar', 'servicio', ($promo ? 'Promoción ' : 'Servicio/producto ') . $id . ' eliminado');
            $pdo->commit();
            $_SESSION['flash'] = 'Eliminado.';
            header("Location: catalogo.php?m=$modo"); exit;
        }
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        $cat    = $promo ? 'promocion' : ($_POST['categoria'] ?? '');
        $dur    = 0;   // la duración ya no se captura
        $costo  = trim((string)($_POST['costo'] ?? '')) === '' ? '0' : $_POST['costo'];
        $vig    = $promo ? ($_POST['vigencia_hasta'] ?? '') : '';
        if ($nombre === '' || mb_strlen($nombre) > 100) $error = 'Escribe el nombre (máximo 100 caracteres).';
        elseif (!$promo && !in_array($cat, ['nutricion', 'corporal', 'facial', 'producto'], true)) $error = 'Elige la categoría.';
        elseif (!is_numeric($costo) || $costo < 0) $error = 'El costo debe ser un número mayor o igual a 0.';
        elseif ($vig !== '' && !DateTime::createFromFormat('Y-m-d', $vig)) $error = 'Fecha de vigencia no válida.';
        if ($error === '') {
            $img = guardarImagen($_FILES['imagen'] ?? null, $error);
        }
        if ($error === '') {
            $pdo->beginTransaction();
            if ($op === 'editar' && $id > 0) {
                $pdo->prepare("UPDATE servicio SET categoria = ?, nombre = ?, descripcion = ?, duracion_min = ?, costo = ?, vigencia_hasta = ?" . ($img ? ", imagen = ?" : "") . " WHERE id = ?")
                    ->execute(array_merge([$cat, $nombre, $desc ?: null, (int)$dur, $costo, $vig ?: null], $img ? [$img] : [], [$id]));
                registrarBitacora($pdo, 'editar', 'servicio', "Modificado: $nombre");
            } else {
                $pdo->prepare("INSERT INTO servicio (categoria, nombre, descripcion, duracion_min, costo, vigencia_hasta, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$cat, $nombre, $desc ?: null, (int)$dur, $costo, $vig ?: null, $img]);
                registrarBitacora($pdo, 'crear', 'servicio', "Agregado: $nombre");
            }
            $pdo->commit();
            $_SESSION['flash'] = 'Guardado correctamente.';
            header("Location: catalogo.php?m=$modo"); exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = ((int)($e->errorInfo[1] ?? 0) === 1451) ? 'No se puede eliminar: ya tiene citas registradas.' : 'No se pudo completar la operación.';
    }
}

$editar = null;
if ($puedeEditar && isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM servicio WHERE id = ? AND categoria " . ($promo ? "= 'promocion'" : "<> 'promocion'"));
    $st->execute([(int)$_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$filas = $pdo->query("SELECT * FROM servicio WHERE categoria " . ($promo ? "= 'promocion'" : "<> 'promocion'") . " ORDER BY categoria, id")->fetchAll();
$v = fn($k, $d = '') => e((string)($_POST[$k] ?? $editar[$k] ?? $d));

encabezado($titulo);
avisoFlash();
if ($error) echo '<p class="alerta error">' . e($error) . '</p>';
?>
<div class="scroll"><table>
  <tr><?php if (!$promo): ?><th>Categoría</th><?php endif; ?><th>Nombre</th>?><th>Costo</th><?php if ($promo): ?><th>Vigencia</th><?php endif; ?><th>Imagen</th><?php if ($puedeEditar): ?><th>Acciones</th><?php endif; ?></tr>
  <?php foreach ($filas as $f): ?>
    <tr>
      <?php if (!$promo): ?><td><?= e($f['categoria']) ?></td><?php endif; ?>
      <td><?= e($f['nombre']) ?></td>
      <td><?= (float)$f['costo'] > 0 ? '$' . number_format((float)$f['costo'], 2) : 'Informes vía WhatsApp' ?></td>
      <?php if ($promo): ?><td><?= e((string)$f['vigencia_hasta']) ?: 'Sin fecha límite' ?></td><?php endif; ?>
      <td><?= $f['imagen'] ? '<img class="mini" src="img/catalogo/' . e($f['imagen']) . '" alt="">' : '—' ?></td>
      <?php if ($puedeEditar): ?>
      <td class="acciones">
        <a class="boton chico" href="catalogo.php?m=<?= $modo ?>&editar=<?= (int)$f['id'] ?>#formulario">Modificar</a>
        <form method="post" onsubmit="return confirm('¿Eliminar este registro?')">
          <input type="hidden" name="m" value="<?= $modo ?>"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <button class="chico peligro" name="operacion" value="eliminar">Eliminar</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas): ?><tr><td colspan="7">Todavía no hay registros.</td></tr><?php endif; ?>
</table></div>

<?php if ($puedeEditar): ?>
<h2 id="formulario"><?= $editar ? 'Modificar' : 'Agregar' ?> <?= $promo ? 'promoción' : 'producto o servicio' ?></h2>
<form method="post" enctype="multipart/form-data" class="validar tarjeta formulario" novalidate>
  <input type="hidden" name="m" value="<?= $modo ?>">
  <input type="hidden" name="operacion" value="<?= $editar ? 'editar' : 'agregar' ?>">
  <input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
  <?php if (!$promo): ?>
  <label>Categoría
    <select name="categoria" required>
      <?php foreach (['nutricion' => 'Nutrición', 'corporal' => 'Tratamiento corporal', 'facial' => 'Tratamiento facial', 'producto' => 'Producto'] as $k => $t): ?>
        <option value="<?= $k ?>" <?= ($_POST['categoria'] ?? $editar['categoria'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select></label>
  <?php endif; ?>
  <label>Nombre <input name="nombre" required maxlength="100" value="<?= $v('nombre') ?>"></label>
  <label>Descripción <textarea name="descripcion" rows="3"><?= $v('descripcion') ?></textarea></label>
  <div class="dos-col">
    <label><?= $promo ? 'Precio promocional' : 'Costo' ?> <input type="number" name="costo" step="0.01" min="0" placeholder="Informes vía WhatsApp" value="<?= (float)($editar['costo'] ?? $_POST['costo'] ?? 0) > 0 ? e((string)($_POST['costo'] ?? $editar['costo'])) : '' ?>"></label>
    <?php if ($promo): ?><label>Vigente hasta <input type="date" name="vigencia_hasta" value="<?= $v('vigencia_hasta') ?>"></label><?php endif; ?>
  </div>
  <label>Imagen <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp"></label>
  <div class="botones"><button>Guardar</button><a class="boton secundario" href="panel.php">Salir</a></div>
</form>
<?php else: ?><p><a class="boton secundario" href="panel.php">Salir</a></p><?php endif; ?>
<?php pie(); ?>
