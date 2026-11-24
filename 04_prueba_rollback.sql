-- 04_prueba_rollback.sql | Prueba de fallo: demuestra el ROLLBACK (sección 10.5)
-- Ejecutar en phpMyAdmin > SQL con TODO el bloque junto (una sola ejecución = una sola sesión).
-- Si phpMyAdmin se detiene en el error, ejecuta en la Shell de XAMPP:  mysql -u root --force clinica < 04_prueba_rollback.sql
USE clinica;
DELETE FROM agenda WHERE fecha = '2031-01-14';

SELECT 'ANTES' AS momento, COUNT(*) AS citas_y_bitacora_de_prueba
FROM (SELECT id FROM agenda WHERE fecha = '2031-01-14' UNION ALL SELECT id FROM bitacora WHERE detalle = 'rollback-test') t;

START TRANSACTION;
  -- Paso 1: se agenda la cita (correcto)
  INSERT INTO agenda (paciente_id, servicio_id, fecha, hora)
    VALUES ((SELECT MIN(p.id) FROM paciente p JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE '%@prueba.nara'), 1, '2031-01-14', '10:00:00');
  -- Paso 2: registro en bitácora (correcto)
  INSERT INTO bitacora (usuario_id, accion, tabla_afectada, detalle)
    VALUES (NULL, 'agendar', 'agenda', 'rollback-test');
  -- Paso 3: segunda cita en el MISMO horario -> ERROR 1062 (llave única uq_bloque)
  INSERT INTO agenda (paciente_id, servicio_id, fecha, hora)
    VALUES ((SELECT MAX(p.id) FROM paciente p JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE '%@prueba.nara'), 1, '2031-01-14', '10:00:00');
ROLLBACK;   -- deshace los pasos 1 y 2: no queda nada a medias

SELECT 'DESPUÉS' AS momento, COUNT(*) AS citas_y_bitacora_de_prueba
FROM (SELECT id FROM agenda WHERE fecha = '2031-01-14' UNION ALL SELECT id FROM bitacora WHERE detalle = 'rollback-test') t;
-- Resultado esperado: ANTES = 0 y DESPUÉS = 0 (el error 1062 aparece en el paso 3)
