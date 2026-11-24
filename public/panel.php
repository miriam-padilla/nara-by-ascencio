<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador', 'trabajador', 'paciente']);
$rol = $_SESSION['rol'];

$tarjetas = [];
$miProxima = null;
$cajas = [];

if ($rol === 'paciente') {
    $todas = [['mis_datos', 'Mis datos', 'perfil.php'], ['mi_historial', 'Historial clínico', 'expediente.php'], ['mi_plan', 'Plan alimenticio', 'plan.php'],
              ['mis_citas', 'Agendar nueva cita', 'agenda.php'], ['mis_citas', 'Cancelar o modificar cita', 'mis_citas.php']];
    foreach ($todas as [$k, $t, $h]) if (nivelRol($k) !== 'ninguno') $tarjetas[] = [$t, $h, ''];
    $pdoP = conectarRol('paciente');
    $pid = pacienteIdActual($pdoP);
    $st = $pdoP->prepare("SELECT a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, s.nombre AS servicio
                          FROM agenda a JOIN servicio s ON s.id = a.servicio_id
                          WHERE a.paciente_id = ? AND a.estado = 'programada' AND a.fecha >= CURDATE()
                          ORDER BY a.fecha, a.hora LIMIT 1");
    $st->execute([$pid]);
    $miProxima = $st->fetch() ?: null;
} else {
    $pdo = conectarRol($rol);
    $esAdmin = $rol === 'administrador';
    $ver = fn(string $k) => nivelPermiso($pdo, $k) !== 'ninguno';
    $citasHoy = 0;
    if ($ver('citas')) {
        $citasHoy = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE estado='programada' AND fecha = CURDATE()")->fetchColumn();
        $cajas[] = ['clase' => 'c-citas', 'titulo' => 'Citas', 'href' => 'citas.php', 'numero' => $citasHoy];
    }
    if ($ver('historial')) $cajas[] = ['clase' => 'c-pacientes', 'titulo' => 'Pacientes', 'href' => 'historial.php'];
    if ($ver('seguimiento')) $cajas[] = ['clase' => 'c-seguimiento', 'titulo' => 'Seguimiento', 'href' => 'seguimiento.php', 'lupa' => true];
    if ($ver('movimientos')) $cajas[] = ['clase' => 'c-movimientos', 'titulo' => 'Movimientos', 'href' => 'bitacora.php'];
    $otros = [];
    if ($ver('productos'))   $otros[] = ['Agregar producto o servicio', 'catalogo.php?m=servicio'];
    if ($ver('promociones')) $otros[] = ['Promociones', 'catalogo.php?m=promocion'];
    if ($ver('calendario'))  $otros[] = ['Calendario', 'calendario.php'];
    if ($ver('citas'))       $otros[] = ['Historial de citas', 'historial_citas.php'];
    if ($esAdmin) { $otros[] = ['Permisos', 'permisos.php']; $otros[] = ['Reportes', 'reportes.php']; }
    $otros[] = ['Cambiar contraseña', 'cambiar_password.php'];
    $cajas[] = ['clase' => 'c-otros', 'titulo' => 'Otros', 'lista' => $otros];
}
if ($rol === 'paciente') $tarjetas[] = ['Cambiar contraseña', 'cambiar_password.php', ''];
if ($rol !== 'paciente') define('PANEL_FONDO', true);

encabezado('Bienvenido, ' . $_SESSION['nombre']);
if ($rol !== 'paciente'): ?>
<style id="estilo-panel">
body { background: #1f2d3a url("img/fondo-panel.jpg") center / cover fixed no-repeat !important; display: flex; flex-direction: column; min-height: 100vh; }
body main { flex: 1; width: 100%; }
body main > h1 { color: #fff; text-align: center; font-family: "Playfair Display", Georgia, serif; text-shadow: 0 2px 8px rgba(0,0,0,.7); }
body main > .alerta { font-weight: 600; }
body footer { background: rgba(255,255,255,.95); }
body .cajas { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: flex-start !important; gap: .7rem !important; margin: 1.2rem auto 2rem !important; width: 100%; max-width: 280px; }
body .caja, body details.caja { width: 100% !important; }
body .caja { position: relative; display: flex !important; align-items: center; justify-content: center; gap: .5rem; height: auto !important; border-radius: 8px !important; background: #f4f7f8 !important; background-image: none !important; color: #2b3a4a !important; text-decoration: none; font-size: 1rem !important; font-weight: 700 !important; box-shadow: 0 2px 6px rgba(0,0,0,.35); padding: .7rem 1.2rem; border: 1px solid #d5dde1 !important; text-align: center; }
body details.caja { padding: 0 !important; display: block !important; }
body details.caja summary { color: #2b3a4a !important; font-weight: 700; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: .5rem; padding: .7rem 1.2rem; cursor: pointer; list-style: none; border-radius: 8px; width: 100%; background: none !important; }
body details.caja summary::-webkit-details-marker { display: none; }
body a.caja:hover, body details.caja summary:hover { background: #e3edf0 !important; color: #1b2733 !important; transform: none; }
body .caja .titulo { font-family: "Nunito", sans-serif !important; font-size: 1rem !important; font-weight: 700 !important; }
body .c-citas, body .c-pacientes, body .c-seguimiento, body .c-historial, body .c-movimientos, body .c-otros { background-color: #f4f7f8 !important; }
body .circulo { display: inline-flex; align-items: center; justify-content: center; min-width: 1.5rem !important; height: 1.5rem !important; padding: 0 .35rem; border-radius: 50%; background: #2a9d8f !important; color: #fff !important; font-size: .8rem !important; font-weight: 700; }
body .lista-caja { position: absolute; top: calc(100% + 4px); left: 0; right: 0; min-width: 0; z-index: 30; max-height: 320px; overflow-y: auto; background: #fff; border: 1px solid #d5dde1; border-radius: 8px; box-shadow: 0 8px 18px rgba(0,0,0,.35); font-size: .95rem; font-weight: 600; text-align: left; }
body .lista-caja a { display: block; padding: .6rem 1rem; color: #2b3a4a; text-decoration: none; border-bottom: 1px solid #edf1f3; }
body .lista-caja a:hover { background: #e3edf0; }
body .lista-caja .vacio { display: block; padding: .8rem 1rem; color: #7a8a96; font-weight: 400; }
</style>
<?php endif;

avisoFlash();
?>
<?php if ($rol === 'paciente'): ?>
  <?php if (hayAvisoSeguimiento()): ?>
    <div class="alerta ok">Tu nutrióloga agregó una actualización a tu seguimiento. <a href="plan.php">Verla ahora</a></div>
  <?php endif; ?>
  <div class="tarjeta proxima">
    <?php if ($miProxima): ?>
      <strong>Tu próxima cita:</strong> <?= e($miProxima['fecha']) ?> a las <?= e($miProxima['hora']) ?> · <?= e($miProxima['servicio']) ?>
      <a class="boton chico" href="mis_citas.php">Ver o modificar</a>
    <?php else: ?>
      No tienes citas programadas. <a class="boton chico" href="agenda.php">Agendar una cita</a>
    <?php endif; ?>
  </div>
  <div class="tarjetas">
    <?php foreach ($tarjetas as [$titulo, $href, $nota]): ?>
      <a class="tarjeta enlace" href="<?= e($href) ?>"><?= e($titulo) ?><?= ($href === 'plan.php' && hayAvisoSeguimiento()) ? ' <span class="insignia">1</span>' : '' ?></a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="cajas">
    <?php foreach ($cajas as $c): ?>
      <?php if (isset($c['href'])): ?>
        <a class="caja <?= e($c['clase']) ?>" href="<?= e($c['href']) ?>">
          <?php if (!empty($c['lupa'])): ?><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><?php endif; ?>
          <span class="titulo"><?= e($c['titulo']) ?></span>
          <?php if (isset($c['numero'])): ?><span class="circulo" title="Citas activas de hoy"><?= (int)$c['numero'] ?></span><?php endif; ?>
        </a>
      <?php else: ?>
        <details class="caja desplegable <?= e($c['clase']) ?>">
          <summary><span class="titulo"><?= e($c['titulo']) ?></span><span aria-hidden="true">▾</span></summary>
          <div class="lista-caja">
            <?php if (!empty($c['filtro'])): ?><input type="search" class="filtrar-lista" placeholder="Buscar paciente" aria-label="Buscar paciente"><?php endif; ?>
            <?php foreach ($c['lista'] as [$t, $h]): ?><a href="<?= e($h) ?>"><?= e($t) ?></a><?php endforeach; ?>
            <?php if (!$c['lista']): ?><span class="vacio"><?= e($c['vacio'] ?? '') ?></span><?php endif; ?>
          </div>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php pie(); ?>
