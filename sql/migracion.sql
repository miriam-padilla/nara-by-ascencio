-- MIGRACIÓN SIN BORRAR DATOS. Ejecutar UNA sola vez en phpMyAdmin > SQL (base clinica ya existente).
-- Después ejecuta sql/privilegios.sql (se puede repetir sin problema).
USE clinica;

-- 1) Nuevo rol "trabajador" y columna de permisos
ALTER TABLE usuarios MODIFY rol ENUM('administrador','trabajador','paciente') NOT NULL;
ALTER TABLE usuarios ADD COLUMN permisos TEXT NULL;

-- 2) Agenda: permitir bloqueos (sin paciente ni servicio) y estado 'bloqueada'
ALTER TABLE agenda DROP INDEX uq_bloque;
ALTER TABLE agenda DROP COLUMN bloque;
ALTER TABLE agenda MODIFY paciente_id INT NULL;
ALTER TABLE agenda MODIFY servicio_id INT NULL;
ALTER TABLE agenda MODIFY estado ENUM('programada','cancelada','atendida','bloqueada') NOT NULL DEFAULT 'programada';
ALTER TABLE agenda ADD COLUMN bloque VARCHAR(20) GENERATED ALWAYS AS (
  IF(estado IN ('programada','bloqueada'), CONCAT(fecha, ' ', hora), NULL)
) STORED;
ALTER TABLE agenda ADD UNIQUE KEY uq_bloque (bloque);
