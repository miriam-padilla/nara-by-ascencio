<?php
// EJECUTAR UNA SOLA VEZ y luego BORRAR este archivo.
require_once "../config/conexion.php";
$pdo = conectarRol('publico');
$hash = password_hash("Admin123", PASSWORD_DEFAULT);
try {
    $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'administrador')")
        ->execute(["Nutrióloga Ascencio", "admin@nara.com", $hash]);
    echo "Administrador creado (admin@nara.com / Admin123). Ahora borra este archivo.";
} catch (PDOException $e) {
    echo "No se pudo crear (¿ya existe?): " . htmlspecialchars($e->getMessage());
}
