-- 03_consultas_documentadas.sql | Las 6 consultas de la sección 10.4 (ejecutar una por una y tomar captura del resultado)
USE clinica;

-- Consulta 1: citas por mes y por servicio  (JOIN + GROUP BY)  -> Reportes
SELECT DATE_FORMAT(a.fecha, '%Y-%m') AS mes, s.nombre AS servicio, COUNT(*) AS citas
FROM agenda a JOIN servicio s ON s.id = a.servicio_id
WHERE a.estado IN ('programada','atendida')
GROUP BY DATE_FORMAT(a.fecha, '%Y-%m'), s.nombre
ORDER BY mes, citas DESC;

-- Consulta 2: servicios con 100 o más citas atendidas  (GROUP BY + HAVING)  -> Reportes
SELECT s.nombre AS servicio, s.categoria, COUNT(a.id) AS total
FROM servicio s JOIN agenda a ON a.servicio_id = s.id
WHERE a.estado = 'atendida'
GROUP BY s.id, s.nombre, s.categoria
HAVING COUNT(a.id) >= 100
ORDER BY total DESC;

-- Consulta 3: agenda de un día con paciente y servicio  (JOIN de 4 tablas)  -> Calendario / Citas
SELECT TIME_FORMAT(a.hora, '%H:%i') AS hora, u.nombre AS paciente, sv.nombre AS servicio, a.estado
FROM agenda a
JOIN paciente p  ON p.id = a.paciente_id
JOIN usuarios u  ON u.id = p.usuario_id
JOIN servicio sv ON sv.id = a.servicio_id
WHERE a.fecha = '2026-01-12'
ORDER BY a.hora;

-- Consulta 4: pacientes con 15 o más cancelaciones  (JOIN + GROUP BY + HAVING)  -> Reportes
SELECT u.nombre AS paciente, COUNT(*) AS cancelaciones
FROM agenda a
JOIN paciente p ON p.id = a.paciente_id
JOIN usuarios u ON u.id = p.usuario_id
WHERE a.estado = 'cancelada'
GROUP BY p.id, u.nombre
HAVING COUNT(*) >= 15
ORDER BY cancelaciones DESC;

-- Consulta 5: cancelar una cita  (UPDATE)  -> Mis citas (paciente) y Citas (personal)
-- Cambia el estado: la llave única "bloque" queda en NULL y el horario se libera.
-- Por seguridad, esta prueba solo cancela una cita de un paciente de PRUEBA (nunca una real).
UPDATE agenda SET estado = 'cancelada'
WHERE id = (SELECT id FROM (SELECT MIN(a.id) AS id
                            FROM agenda a JOIN paciente p ON p.id = a.paciente_id
                            JOIN usuarios u ON u.id = p.usuario_id
                            WHERE a.estado = 'programada' AND u.email LIKE '%@prueba.nara') AS t);

-- Consulta 6: depurar movimientos de la bitácora de más de un año  (DELETE)  -> Mantenimiento anual
DELETE FROM bitacora
WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND accion IN ('login', 'logout');
