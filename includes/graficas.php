<?php
// Gráfica de evolución del peso (SVG). $seg = filas de seguimiento (cualquier orden) con 'fecha' y 'peso'.
function graficaPeso(array $seg): string
{
    $pts = array_values(array_filter($seg, fn($s) => isset($s['peso']) && $s['peso'] !== null && $s['peso'] !== ''));
    usort($pts, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    if (count($pts) < 2) {
        return '<p class="nota">La gráfica aparecerá cuando haya al menos dos registros de peso.</p>';
    }
    $w = 640; $h = 240; $mx = 56; $my = 34;
    $pesos = array_map(fn($p) => (float)$p['peso'], $pts);
    $min = min($pesos); $max = max($pesos);
    if ($max == $min) { $max += 1; $min -= 1; }
    $n = count($pts);
    $coords = [];
    foreach ($pts as $i => $p) {
        $x = $mx + ($w - 2 * $mx) * ($i / ($n - 1));
        $y = ($h - $my) - ($h - 2 * $my) * (((float)$p['peso'] - $min) / ($max - $min));
        $coords[] = [$x, $y, $p];
    }
    $linea = implode(' ', array_map(fn($c) => round($c[0], 1) . ',' . round($c[1], 1), $coords));
    $svg  = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="grafica" role="img" aria-label="Evolución del peso">';
    $svg .= '<line x1="' . $mx . '" y1="' . ($h - $my) . '" x2="' . ($w - $mx) . '" y2="' . ($h - $my) . '" stroke="#b7c9bd"/>';
    $svg .= '<text x="' . ($mx - 8) . '" y="' . ($my + 4) . '" text-anchor="end" font-size="12" fill="#6b7d72">' . round($max, 1) . ' kg</text>';
    $svg .= '<text x="' . ($mx - 8) . '" y="' . ($h - $my) . '" text-anchor="end" font-size="12" fill="#6b7d72">' . round($min, 1) . ' kg</text>';
    $svg .= '<polyline points="' . $linea . '" fill="none" stroke="#2e7d4f" stroke-width="3" stroke-linejoin="round"/>';
    foreach ($coords as $i => [$x, $y, $p]) {
        $svg .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="5" fill="#2e7d4f"/>';
        $svg .= '<text x="' . round($x, 1) . '" y="' . round($y - 10, 1) . '" text-anchor="middle" font-size="12" fill="#1e5c39">' . htmlspecialchars((string)$p['peso']) . '</text>';
        if ($n <= 8 || $i === 0 || $i === $n - 1) {
            $svg .= '<text x="' . round($x, 1) . '" y="' . ($h - $my + 16) . '" text-anchor="middle" font-size="11" fill="#6b7d72">' . htmlspecialchars((string)$p['fecha']) . '</text>';
        }
    }
    return $svg . '</svg>';
}
