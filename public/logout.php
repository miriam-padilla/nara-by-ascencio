<?php
require_once "../includes/auth.php";
require_once "../config/conexion.php";
$destino = in_array($_SESSION['rol'] ?? '', ['administrador', 'trabajador'], true) ? 'admin.php' : 'index.php';
if (!empty($_SESSION['rol'])) {
    registrarBitacora(conectarRol($_SESSION['rol']), 'logout', 'usuarios', 'Cierre de sesión');
}
session_destroy();
header("Location: $destino");
exit;
