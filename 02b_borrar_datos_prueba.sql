-- 02b_borrar_datos_prueba.sql | Quita los datos de prueba antes de entregar o de usar el sistema de verdad.
-- Borra solo lo creado por 02_datos_prueba.sql (correos @prueba.nara y bitácora marcada "prueba").
USE clinica;
DELETE FROM usuarios WHERE email LIKE '%@prueba.nara';   -- en cascada: paciente, citas y seguimientos
DELETE FROM bitacora WHERE detalle = 'prueba';
SELECT 'agenda' AS tabla, COUNT(*) AS registros FROM agenda UNION SELECT 'usuarios', COUNT(*) FROM usuarios;
