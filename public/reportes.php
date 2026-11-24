<?php
// Reportes de la administradora: ingresos, citas, pacientes. Usa JOIN, GROUP BY y HAVING.
require_once "../includes/auth.php";
require_once "../config/conexion.php";
require_once "../includes/layout.php";
requerirRol(['administrador']);

$pdo = conectarRol('administrador');

// ---- Filtros ----
$okFecha = fn($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1 && DateTime::createFromFormat('Y-m-d', $f) !== false;
$hoy = date('Y-m-d');
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? $hoy;
if (!$okFecha($desde)) $desde = date('Y-m-01');
if (!$okFecha($hasta)) $hasta = $hoy;
if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
$min  = max(0, (int)($_GET['min'] ?? 1));
$canc = max(1, (int)($_GET['canc'] ?? 1));

$rapidos = [
    'Este mes'      => [date('Y-m-01'), $hoy],
    'Mes anterior'  => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    'Este año'      => [date('Y-01-01'), $hoy],
    'Todo'          => ['2000-01-01', $hoy],
];

// ---- Indicadores del periodo ----
$st = $pdo->prepare("SELECT COALESCE(SUM(a.estado = 'atendida'), 0) AS atendidas,
                            COALESCE(SUM(a.estado = 'cancelada'), 0) AS canceladas,
                            COALESCE(SUM(CASE WHEN a.estado = 'atendida' THEN s.costo ELSE 0 END), 0) AS ingresos,
                            COALESCE(SUM(CASE WHEN a.estado = 'atendida' AND s.costo = 0 THEN 1 ELSE 0 END), 0) AS sin_precio
                     FROM agenda a JOIN servicio s ON s.id = a.servicio_id
                     WHERE a.fecha BETWEEN ? AND ?");
$st->execute([$desde, $hasta]);
$k = $st->fetch();
$atendidas = (int)$k['atendidas']; $canceladas = (int)$k['canceladas']; $ingresos = (float)$k['ingresos']; $sinPrecio = (int)$k['sin_precio'];
$ticket = $atendidas > 0 ? $ingresos / $atendidas : 0;
$tasaCanc = ($atendidas + $canceladas) > 0 ? 100 * $canceladas / ($atendidas + $canceladas) : 0;

$st = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'paciente' AND DATE(creado_en) BETWEEN ? AND ?");
$st->execute([$desde, $hasta]);
$nuevos = (int)$st->fetchColumn();

$esp = $pdo->query("SELECT COALESCE(SUM(s.costo), 0) AS monto, COUNT(*) AS citas
                    FROM agenda a JOIN servicio s ON s.id = a.servicio_id
                    WHERE a.estado = 'programada' AND a.fecha >= CURDATE()")->fetch();

// ---- Tabla 1: por servicio (JOIN + GROUP BY + HAVING) ----
$st = $pdo->prepare("SELECT s.nombre AS servicio, s.categoria, s.costo AS precio, COUNT(a.id) AS total,
                            SUM(a.estado = 'atendida') AS atendidas, SUM(a.estado = 'cancelada') AS canceladas,
                            SUM(CASE WHEN a.estado = 'atendida' THEN s.costo ELSE 0 END) AS ingresos
                     FROM servicio s JOIN agenda a ON a.servicio_id = s.id
                     WHERE a.fecha BETWEEN ? AND ?
                     GROUP BY s.id, s.nombre, s.categoria, s.costo
                     HAVING COUNT(a.id) >= " . (int)$min . "
                     ORDER BY ingresos DESC, total DESC");
$st->execute([$desde, $hasta]);
$porServicio = $st->fetchAll();

// ---- Tabla 2: por mes ----
$st = $pdo->prepare("SELECT DATE_FORMAT(a.fecha, '%Y-%m') AS mes, SUM(a.estado = 'atendida') AS atendidas,
                            SUM(a.estado = 'cancelada') AS canceladas,
                            SUM(CASE WHEN a.estado = 'atendida' THEN s.costo ELSE 0 END) AS ingresos
                     FROM agenda a JOIN servicio s ON s.id = a.servicio_id
                     WHERE a.fecha BETWEEN ? AND ?
                     GROUP BY DATE_FORMAT(a.fecha, '%Y-%m')
                     ORDER BY mes");
$st->execute([$desde, $hasta]);
$porMes = $st->fetchAll();

// ---- Tabla 3: pacientes con cancelaciones (HAVING) ----
$st = $pdo->prepare("SELECT u.nombre AS paciente, COUNT(*) AS cancelaciones
                     FROM agenda a JOIN paciente p ON p.id = a.paciente_id JOIN usuarios u ON u.id = p.usuario_id
                     WHERE a.estado = 'cancelada' AND a.fecha BETWEEN ? AND ?
                     GROUP BY p.id, u.nombre
                     HAVING COUNT(*) >= " . (int)$canc . "
                     ORDER BY cancelaciones DESC");
$st->execute([$desde, $hasta]);
$porPaciente = $st->fetchAll();

// ---- Tabla 4: pacientes por tipo de atención ----
$porTipo = $pdo->query("SELECT p.tipo_atencion AS tipo, COUNT(*) AS pacientes, SUM(p.activo = 'Si') AS en_seguimiento
                        FROM paciente p WHERE p.tipo_atencion IS NOT NULL
                        GROUP BY p.tipo_atencion ORDER BY pacientes DESC")->fetchAll();

$dinero = fn($n) => '$' . number_format((float)$n, 2);
$nombresMes = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
               '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
$cat = ['nutricion' => 'Nutrición', 'corporal' => 'Corporal', 'facial' => 'Facial', 'producto' => 'Producto'];

function herramientas(string $archivo): void { ?>
  <div class="herramientas">
    <button type="button" class="chico secundario" data-accion="grafica">Ver gráfica</button>
    <label class="metrica" hidden>Graficar: <select data-metrica></select></label>
    <button type="button" class="chico secundario" data-accion="excel" data-archivo="<?= e($archivo) ?>">Exportar a Excel</button>
  </div>
  <div class="grafica-rep" hidden></div>
<?php }

encabezado('Reportes');
?>
<form method="get" class="filtro filtros-rep">
  <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
  <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
  <label>Servicios con al menos <input type="number" name="min" value="<?= $min ?>" min="0" class="corto"> citas</label>
  <label>Pacientes con al menos <input type="number" name="canc" value="<?= $canc ?>" min="1" class="corto"> cancelaciones</label>
  <button>Actualizar</button>
</form>
<p class="rapidos">Periodo:
  <?php foreach ($rapidos as $t => [$d, $h]): ?>
    <a class="boton chico <?= ($desde === $d && $hasta === $h) ? '' : 'secundario' ?>" href="reportes.php?desde=<?= $d ?>&hasta=<?= $h ?>&min=<?= $min ?>&canc=<?= $canc ?>"><?= e($t) ?></a>
  <?php endforeach; ?>
  <button type="button" class="chico secundario" onclick="window.print()">Imprimir</button>
</p>

<div class="kpis">
  <div class="kpi principal"><small>Ingresos del periodo</small><strong><?= $dinero($ingresos) ?></strong><span>Suma del precio de las citas atendidas</span></div>
  <div class="kpi"><small>Citas atendidas</small><strong><?= $atendidas ?></strong><span>Ticket promedio <?= $dinero($ticket) ?></span></div>
  <div class="kpi"><small>Citas canceladas</small><strong><?= $canceladas ?></strong><span>Tasa de cancelación <?= number_format($tasaCanc, 1) ?> %</span></div>
  <div class="kpi"><small>Pacientes nuevos</small><strong><?= $nuevos ?></strong><span>Registrados en el periodo</span></div>
  <div class="kpi"><small>Ingresos esperados</small><strong><?= $dinero($esp['monto']) ?></strong><span><?= (int)$esp['citas'] ?> citas programadas desde hoy</span></div>
  <div class="kpi"><small>Citas sin precio</small><strong><?= $sinPrecio ?></strong><span>Atendidas con costo $0 (no suman ingresos)</span></div>
</div>

<section class="tarjeta calculadora" id="calculadora" data-ingresos="<?= e((string)$ingresos) ?>" data-atendidas="<?= $atendidas ?>">
  <h2>Calculadora de ganancias</h2>
  <div class="gastos">
    <label>Renta <input type="number" min="0" step="0.01" data-gasto placeholder="0.00"></label>
    <label>Insumos y materiales <input type="number" min="0" step="0.01" data-gasto placeholder="0.00"></label>
    <label>Sueldos <input type="number" min="0" step="0.01" data-gasto placeholder="0.00"></label>
    <label>Otros gastos <input type="number" min="0" step="0.01" data-gasto placeholder="0.00"></label>
  </div>
  <div class="kpis resultado">
    <div class="kpi"><small>Ingresos</small><strong id="c-ingresos"><?= $dinero($ingresos) ?></strong></div>
    <div class="kpi"><small>Gastos</small><strong id="c-gastos">$0.00</strong></div>
    <div class="kpi principal"><small>Ganancia</small><strong id="c-ganancia"><?= $dinero($ingresos) ?></strong><span id="c-margen">Margen 100 %</span></div>
  </div>
  <p id="c-equilibrio" class="equilibrio"></p>
</section>

<h2>Ingresos y citas por servicio</h2>
<div class="reporte">
  <?php herramientas('reporte_servicios'); ?>
  <div class="scroll"><table class="datos" data-grafica="6">
    <tr><th>Servicio</th><th>Categoría</th><th>Precio</th><th>Citas</th><th>Atendidas</th><th>Canceladas</th><th>Ingresos</th></tr>
    <?php foreach ($porServicio as $f): ?>
      <tr><td><?= e($f['servicio']) ?></td><td><?= e($cat[$f['categoria']] ?? $f['categoria']) ?></td>
          <td data-v="<?= (float)$f['precio'] ?>"><?= (float)$f['precio'] > 0 ? $dinero($f['precio']) : 'Sin precio' ?></td>
          <td data-v="<?= (int)$f['total'] ?>"><?= (int)$f['total'] ?></td><td data-v="<?= (int)$f['atendidas'] ?>"><?= (int)$f['atendidas'] ?></td>
          <td data-v="<?= (int)$f['canceladas'] ?>"><?= (int)$f['canceladas'] ?></td><td data-v="<?= (float)$f['ingresos'] ?>"><?= $dinero($f['ingresos']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$porServicio): ?><tr><td colspan="7">Sin citas en el periodo.</td></tr><?php endif; ?>
    <?php if ($porServicio): ?><tr class="total"><td>Total</td><td></td><td></td>
      <td data-v="<?= array_sum(array_column($porServicio, 'total')) ?>"><?= array_sum(array_column($porServicio, 'total')) ?></td>
      <td data-v="<?= array_sum(array_column($porServicio, 'atendidas')) ?>"><?= array_sum(array_column($porServicio, 'atendidas')) ?></td>
      <td data-v="<?= array_sum(array_column($porServicio, 'canceladas')) ?>"><?= array_sum(array_column($porServicio, 'canceladas')) ?></td>
      <td data-v="<?= array_sum(array_column($porServicio, 'ingresos')) ?>"><?= $dinero(array_sum(array_column($porServicio, 'ingresos'))) ?></td></tr><?php endif; ?>
  </table></div>
</div>

<h2>Resumen por mes</h2>
<div class="reporte">
  <?php herramientas('reporte_meses'); ?>
  <div class="scroll"><table class="datos" data-grafica="3">
    <tr><th>Mes</th><th>Atendidas</th><th>Canceladas</th><th>Ingresos</th></tr>
    <?php foreach ($porMes as $f): [$y, $m] = explode('-', $f['mes']); ?>
      <tr><td><?= e(($nombresMes[$m] ?? $m) . ' ' . $y) ?></td><td data-v="<?= (int)$f['atendidas'] ?>"><?= (int)$f['atendidas'] ?></td>
          <td data-v="<?= (int)$f['canceladas'] ?>"><?= (int)$f['canceladas'] ?></td><td data-v="<?= (float)$f['ingresos'] ?>"><?= $dinero($f['ingresos']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$porMes): ?><tr><td colspan="4">Sin citas en el periodo.</td></tr><?php endif; ?>
  </table></div>
</div>

<h2>Pacientes con cancelaciones</h2>
<div class="reporte">
  <?php herramientas('reporte_cancelaciones'); ?>
  <div class="scroll"><table class="datos" data-grafica="1">
    <tr><th>Paciente</th><th>Cancelaciones</th></tr>
    <?php foreach ($porPaciente as $f): ?>
      <tr><td><?= e($f['paciente']) ?></td><td data-v="<?= (int)$f['cancelaciones'] ?>"><?= (int)$f['cancelaciones'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$porPaciente): ?><tr><td colspan="2">Sin resultados en el periodo.</td></tr><?php endif; ?>
  </table></div>
</div>

<h2>Pacientes por tipo de atención</h2>
<div class="reporte">
  <?php herramientas('reporte_tipo_atencion'); ?>
  <div class="scroll"><table class="datos" data-grafica="1">
    <tr><th>Tipo de atención</th><th>Pacientes</th><th>En seguimiento</th></tr>
    <?php foreach ($porTipo as $f): ?>
      <tr><td><?= e($f['tipo']) ?></td><td data-v="<?= (int)$f['pacientes'] ?>"><?= (int)$f['pacientes'] ?></td><td data-v="<?= (int)$f['en_seguimiento'] ?>"><?= (int)$f['en_seguimiento'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$porTipo): ?><tr><td colspan="3">Sin datos.</td></tr><?php endif; ?>
  </table></div>
</div>
<p class="no-imprimir"><a class="boton secundario" href="panel.php">Salir</a></p>
<script src="js/reportes.js"></script>
<?php pie(); ?>
