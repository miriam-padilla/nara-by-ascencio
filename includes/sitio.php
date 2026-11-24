<?php
// Datos del consultorio (cámbialos aquí y se actualizan en todo el sitio)
const SITIO_NOMBRE     = 'NARA by Ascencio';
const SITIO_TEL        = '6681015028';
const SITIO_TEL_BONITO = '668 101 5028';
const SITIO_WA         = '526681015028';
const SITIO_DIRECCION  = 'Privada Gil #2103 entre Premier y Mariano Escobedo, Real de Santiago, C.P. 81293, Los Mochis, Sinaloa';
const SITIO_HORARIO_1  = 'Lunes a viernes: 9:00 a 13:00 y 15:00 a 19:00';
const SITIO_HORARIO_2  = 'Sábados: 9:00 a 13:00';

function enlaceWhatsApp(string $mensaje = 'Hola, quisiera información sobre una consulta en NARA by Ascencio.'): string
{
    return 'https://wa.me/' . SITIO_WA . '?text=' . rawurlencode($mensaje);
}

function enlaceMapa(): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(SITIO_DIRECCION);
}

// Mapa incrustado (se puede acercar, alejar y arrastrar)
function mapaIncrustado(): string
{
    return 'https://www.google.com/maps?q=' . rawurlencode(SITIO_DIRECCION) . '&z=16&hl=es&output=embed';
}

// Ícono de WhatsApp (SVG en línea)
function iconoWhatsApp(int $px = 18): string
{
    return '<svg viewBox="0 0 24 24" width="' . $px . '" height="' . $px . '" aria-hidden="true"><path fill="#25a244" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/><path fill="#fff" d="M8.6 7.3c.3-.3.8-.3 1 .1l.8 1.6c.1.3 0 .6-.2.8l-.5.6a7 7 0 0 0 3.3 3.3l.6-.6c.2-.2.5-.3.8-.1l1.6.9c.4.2.5.7.2 1-.6.9-1.6 1.3-2.7 1a8.6 8.6 0 0 1-5.8-5.8c-.3-1 .2-2 .9-2.8z"/></svg>';
}

// Foto de la nutrióloga (la puede cambiar la administradora desde su panel)
function fotoNutriologa(): string
{
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $f = __DIR__ . "/../public/img/nutriologa.$ext";
        if (is_file($f)) return "img/nutriologa.$ext?v=" . filemtime($f);
    }
    return 'img/nutriologa.svg';
}

// Servicios y productos (se leen una sola vez por página). Usa la cuenta pública: solo puede leer.
function listaServicios(): array
{
    static $lista = null;
    if ($lista !== null) return $lista;
    $lista = [];
    try {
        require_once __DIR__ . '/../config/conexion.php';
        $pdo = conectarRol('publico');
        try {
            $lista = $pdo->query("SELECT id, categoria, nombre, descripcion, imagen, costo FROM servicio WHERE categoria <> 'promocion' ORDER BY id")->fetchAll();
        } catch (Throwable $e) {   // base sin la migración 2
            $lista = $pdo->query("SELECT id, categoria, nombre, costo FROM servicio ORDER BY id")->fetchAll();
        }
        $orden = ['nutricion' => 1, 'corporal' => 2, 'facial' => 3, 'producto' => 4];
        usort($lista, fn($x, $y) => [$orden[$x['categoria']] ?? 9, $x['id']] <=> [$orden[$y['categoria']] ?? 9, $y['id']]);
    } catch (Throwable $e) {
        $lista = [];
    }
    return $lista;
}

// Promociones vigentes (sin fecha límite o con fecha de hoy en adelante)
function listaPromociones(): array
{
    try {
        require_once __DIR__ . '/../config/conexion.php';
        $pdo = conectarRol('publico');
        return $pdo->query("SELECT id, nombre, descripcion, imagen, vigencia_hasta FROM servicio
                            WHERE categoria = 'promocion' AND (vigencia_hasta IS NULL OR vigencia_hasta >= CURDATE())
                            ORDER BY id DESC")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
