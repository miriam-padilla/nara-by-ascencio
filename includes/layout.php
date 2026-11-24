<?php
require_once __DIR__ . '/sitio.php';

function iconoCalendario(int $px = 18): string
{
    return '<svg viewBox="0 0 24 24" width="' . $px . '" height="' . $px . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';
}

// Lista desplegable del menú
function menuDesplegable(string $titulo, array $items): void
{
    echo '<li class="drop"><button type="button" class="drop-btn" aria-haspopup="true" aria-expanded="false">' . e($titulo) . '</button><ul class="sub">';
    foreach ($items as [$texto, $href, $icono]) {
        echo '<li><a href="' . e($href) . '">' . $icono . e($texto) . '</a></li>';
    }
    echo '</ul></li>';
}

function encabezado(string $titulo, bool $mostrarTitulo = true): void
{
    $rol = $_SESSION['rol'] ?? '';
    $personal = in_array($rol, ['administrador', 'trabajador'], true);
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?> - <?= SITIO_NOMBRE ?></title>
  <link rel="icon" href="img/icono.png" type="image/png">
  <link rel="stylesheet" href="css/estilos.css?v=<?= (int)@filemtime(__DIR__ . '/../public/css/estilos.css') ?>">
</head>
<body<?= defined("PANEL_FONDO") ? " class=\"panel-fondo\"" : "" ?>>
<header>
  <div class="barra">
    <button type="button" class="menu-toggle" aria-label="Menú">☰ Menú</button>
    <nav>
      <ul>
      <?php if (defined('MENU_MINIMO')): ?>
        <li><a href="index.php">Ir al sitio</a></li>
      <?php elseif ($personal): ?>
        <li><a href="panel.php">Regresar al panel principal</a></li>
        <li><a href="logout.php">Cerrar sesión</a></li>
      <?php else:
        $servicios = array_map(fn($s) => [$s['nombre'], 'servicio.php?id=' . (int)$s['id'], ''], listaServicios());
        ?>
        <li><a href="index.php">Inicio</a></li>
        <?php if ($rol === 'paciente'): ?><li><a href="panel.php">Mi panel</a></li><?php endif; ?>
        <?php if ($servicios && nivelRol('ver_servicios') !== 'ninguno') menuDesplegable('Servicios', $servicios); ?>
        <?php if ($rol === 'paciente'):
            $info = [];
            if (nivelRol('mis_datos') !== 'ninguno') $info[] = ['Mis datos', 'perfil.php', ''];
            if (nivelRol('mi_historial') !== 'ninguno') $info[] = ['Historial clínico', 'expediente.php', ''];
            if ($info) menuDesplegable('Mi información', $info);
            if (nivelRol('mi_plan') !== 'ninguno') menuDesplegable('Seguimiento' . (hayAvisoSeguimiento() ? ' (1)' : ''), [['Plan alimenticio', 'plan.php', '']]);
            if (nivelRol('mis_citas') !== 'ninguno') menuDesplegable('Cita', [['Agendar nueva cita', 'agenda.php', iconoCalendario() . ' '], ['Cancelar o modificar cita', 'mis_citas.php', '']]);
            ?>
            <li><a href="logout.php">Cerrar sesión</a></li>
        <?php else:
            menuDesplegable('Cita', [['Agendar nueva cita', 'login.php?siguiente=agenda', iconoCalendario() . ' '], ['Cancelar o modificar cita', 'login.php?siguiente=mis_citas', '']]);
            $ses = [['Ingresar', 'login.php', '']];
            if (nivelRol('registro') !== 'ninguno') $ses[] = ['Aún no tengo una cuenta', 'registro.php', ''];
            menuDesplegable('Inicio de sesión', $ses);
        endif; ?>
      <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>
<main class="<?= $mostrarTitulo ? '' : 'ancho' ?>">
<?php if ($mostrarTitulo): ?><h1><?= e($titulo) ?></h1><?php endif; ?>
<?php
}

// Ventana emergente de éxito. Redirige sola a $destino después de unos segundos.
function modalExito(string $mensaje, string $destino, string $boton = 'Aceptar', int $segundos = 4): void
{
    ?>
<div class="modal-fondo" id="modal-exito" data-destino="<?= e($destino) ?>" data-segundos="<?= $segundos ?>">
  <div class="modal" role="alertdialog" aria-live="assertive">
    <svg class="palomita" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#e4f5ea"/><path d="M6.5 12.5l3.6 3.6 7.4-7.6" fill="none" stroke="#1e8e3e" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <p class="mensaje"><?= e($mensaje) ?></p>
    <a class="boton" href="<?= e($destino) ?>"><?= e($boton) ?></a>
  </div>
</div>
<?php
}

function pie(): void
{
    $rol  = $_SESSION['rol'] ?? '';
    $chat = !in_array($rol, ['administrador', 'trabajador'], true);
    ?>
</main>
<footer>
  <strong><?= SITIO_NOMBRE ?></strong> · Consultoría nutricional<br>
  <?= e(SITIO_DIRECCION) ?><br>
  Citas: <a class="wa-numero" href="<?= e(enlaceWhatsApp('Hola, quiero agendar una cita.')) ?>" target="_blank" rel="noopener"><?= iconoWhatsApp(16) ?> <?= SITIO_TEL_BONITO ?></a> ·
  <a href="aviso.php">Aviso de privacidad</a>
</footer>
<?php if ($chat): ?>
  <div id="chat-config" hidden data-wa="<?= SITIO_WA ?>" data-tel="<?= SITIO_TEL ?>" data-rol="<?= e($rol) ?>"
       data-dir="<?= e(SITIO_DIRECCION) ?>"></div>
  <script src="js/chat.js"></script>
<?php endif; ?>
<script src="js/sitio.js?v=<?= (int)@filemtime(__DIR__ . '/../public/js/sitio.js') ?>"></script>
<script src="js/validaciones.js"></script>
</body>
</html>
<?php
}
