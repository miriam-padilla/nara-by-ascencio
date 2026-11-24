-- ============================================================
-- 02_datos_prueba.sql  |  NARA by Ascencio
-- Genera datos de prueba POR SCRIPT (no se escribe fila por fila):
--   1 administradora, 1 trabajadora, 30 pacientes, 1,200 citas,
--   300 seguimientos y 1,000 movimientos de bitácora.
-- Ejecutar en phpMyAdmin > base "clinica" > pestaña SQL, DESPUÉS de nara.sql.
-- Contraseñas de prueba: Admin123 (administradora y trabajadora) y Paciente123 (pacientes).
-- Se puede ejecutar de nuevo: primero borra los datos de prueba.
-- ============================================================
USE clinica;

-- 0) Limpieza SOLO de datos de prueba anteriores (correos @prueba.nara).
--    Al borrar un usuario se borran en cascada su paciente, sus citas y sus seguimientos.
--    Tus datos reales NO se tocan.
DELETE FROM usuarios WHERE email LIKE '%@prueba.nara';
DELETE FROM bitacora WHERE detalle = 'prueba';

-- 1) Tabla auxiliar con los dígitos 0 a 9 (sirve para "contar" hasta 1,000 y más)
DROP TEMPORARY TABLE IF EXISTS digitos;
CREATE TEMPORARY TABLE digitos (d INT);
INSERT INTO digitos VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

-- 2) Usuarios: 1 administradora, 1 trabajadora y 30 pacientes
INSERT INTO usuarios (nombre, email, password, rol) VALUES
  ('Administradora de prueba', 'admin@prueba.nara',      '$2y$10$QMRAwku6eEqC9IPLTcTO5eZ.47kLLvKYy8hitGZuvVII954zgHOy.', 'administrador'),
  ('Trabajadora de prueba',    'trabajadora@prueba.nara', '$2y$10$QMRAwku6eEqC9IPLTcTO5eZ.47kLLvKYy8hitGZuvVII954zgHOy.', 'trabajador');

INSERT INTO usuarios (nombre, email, password, rol)
SELECT CONCAT('Paciente ', LPAD(n.i, 2, '0')),
       CONCAT('paciente', LPAD(n.i, 2, '0'), '@prueba.nara'),
       '$2y$10$Iv6CujDTVm0KwN3d0pSle.nc9TZP2J7orTkO3kFk7IhOBSC1mQM9S',
       'paciente'
FROM (SELECT a.d + b.d * 10 + 1 AS i FROM digitos a, digitos b) AS n
WHERE n.i <= 30;

-- 3) Un registro clínico por cada paciente
INSERT INTO paciente (usuario_id, edad, sexo, telefono, activo, tipo_atencion, peso_inicial, talla_cm, objetivo, alergias)
SELECT u.id,
       18 + (u.id MOD 50),
       ELT(1 + (u.id MOD 3), 'F', 'M', 'Otro'),
       CONCAT('668', LPAD(1000000 + u.id, 7, '0')),
       IF(u.id MOD 5 = 0, 'No', 'Si'),
       ELT(1 + (u.id MOD 4), 'Reducción de peso', 'Diabetes', 'Hipertensión', 'Otro'),
       60 + (u.id MOD 30),
       150 + (u.id MOD 30),
       'Mejorar hábitos de alimentación',
       NULL
FROM usuarios u
WHERE u.email LIKE 'paciente%@prueba.nara';

-- 4) 1,200 citas pasadas (tabla principal), repartidas entre los 30 pacientes de PRUEBA. Estados: ~70 % atendida, ~30 % cancelada.
--    Las canceladas y atendidas NO ocupan la llave única "bloque", por eso no chocan.
INSERT INTO agenda (paciente_id, servicio_id, fecha, hora, estado)
SELECT p.id,
       1 + (n.i MOD 5),
       DATE_ADD('2026-01-05', INTERVAL FLOOR(n.i / 8) DAY),
       ELT(1 + (n.i MOD 8), '09:00:00','10:00:00','11:00:00','12:00:00','15:00:00','16:00:00','17:00:00','18:00:00'),
       IF(n.i MOD 10 IN (3, 7, 9), 'cancelada', 'atendida')
FROM (SELECT a.d + b.d * 10 + c.d * 100 + e.d * 1000 AS i
      FROM digitos a, digitos b, digitos c, digitos e) AS n
JOIN (SELECT pa.id, ROW_NUMBER() OVER (ORDER BY pa.id) AS rn FROM paciente pa JOIN usuarios us ON us.id = pa.usuario_id WHERE us.email LIKE '%@prueba.nara') AS p
  ON p.rn = 1 + (n.i MOD 30)
WHERE n.i < 1200;

-- 5) Unas pocas citas programadas a futuro (cada una en un horario distinto).
--    INSERT IGNORE: si ese horario ya tiene una cita real, la llave única lo rechaza y se omite.
INSERT IGNORE INTO agenda (paciente_id, servicio_id, fecha, hora, estado)
SELECT p.id, 1, '2026-12-01', ADDTIME('09:00:00', SEC_TO_TIME(k.i * 3600)), 'programada'
FROM (SELECT d AS i FROM digitos WHERE d < 4) AS k
JOIN (SELECT pa.id, ROW_NUMBER() OVER (ORDER BY pa.id) AS rn FROM paciente pa JOIN usuarios us ON us.id = pa.usuario_id WHERE us.email LIKE '%@prueba.nara') AS p ON p.rn = k.i + 1;

-- 6) 300 seguimientos (peso que baja poco a poco)
INSERT INTO seguimiento (paciente_id, fecha, peso, cintura_cm, adherencia, observaciones, proxima_revision)
SELECT p.id,
       DATE_ADD('2026-02-01', INTERVAL n.i * 3 DAY),
       80 - (n.i MOD 12) * 0.5,
       90 - (n.i MOD 12) * 0.4,
       ELT(1 + (n.i MOD 3), 'baja', 'media', 'alta'),
       'Seguimiento de prueba',
       DATE_ADD('2026-02-01', INTERVAL n.i * 3 + 14 DAY)
FROM (SELECT a.d + b.d * 10 + c.d * 100 AS i FROM digitos a, digitos b, digitos c) AS n
JOIN (SELECT pa.id, ROW_NUMBER() OVER (ORDER BY pa.id) AS rn FROM paciente pa JOIN usuarios us ON us.id = pa.usuario_id WHERE us.email LIKE '%@prueba.nara') AS p
  ON p.rn = 1 + (n.i MOD 30)
WHERE n.i < 300;

-- 7) 1,000 movimientos de bitácora (inicios de sesión, citas, ediciones)
INSERT INTO bitacora (usuario_id, accion, tabla_afectada, detalle, fecha_hora)
SELECT (SELECT id FROM usuarios WHERE email = 'admin@prueba.nara'),
       ELT(1 + (n.i MOD 4), 'login', 'agendar', 'editar', 'cancelar'),
       ELT(1 + (n.i MOD 4), 'usuarios', 'agenda', 'paciente', 'agenda'),
       'prueba',
       DATE_ADD('2026-01-05 08:00:00', INTERVAL n.i * 7 HOUR)
FROM (SELECT a.d + b.d * 10 + c.d * 100 AS i FROM digitos a, digitos b, digitos c) AS n;

-- 8) Comprobación: debe mostrar agenda >= 1000
SELECT 'usuarios' AS tabla, COUNT(*) AS registros FROM usuarios
UNION SELECT 'paciente', COUNT(*) FROM paciente
UNION SELECT 'servicio', COUNT(*) FROM servicio
UNION SELECT 'agenda', COUNT(*) FROM agenda
UNION SELECT 'seguimiento', COUNT(*) FROM seguimiento
UNION SELECT 'bitacora', COUNT(*) FROM bitacora;
