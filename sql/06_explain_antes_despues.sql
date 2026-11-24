-- 06_explain_antes_despues.sql | Mejora con índice (sección 10.8). Ejecutar DESPUÉS de 02_datos_prueba.sql.
-- Toma una captura del resultado de CADA EXPLAIN (antes y después). Fíjate en las columnas type, key y rows.
-- Con pocas filas el tiempo en milisegundos casi no cambia; la mejora se ve en 'rows' (filas revisadas) y en 'type'.
USE clinica;

-- Consulta del calendario: citas programadas de un mes
-- (la usan calendario.php y la lista de citas del panel)

-- ===== ANTES: sin índice =====
DROP INDEX IF EXISTS idx_agenda_fecha_estado ON agenda;
ANALYZE TABLE agenda;
EXPLAIN SELECT fecha, estado, COUNT(*)
        FROM agenda
        WHERE fecha BETWEEN '2026-03-01' AND '2026-03-31' AND estado = 'atendida'
        GROUP BY fecha, estado;


-- ===== CREAR EL ÍNDICE (el mismo de indices_y_mantenimiento.sql) =====
CREATE INDEX idx_agenda_fecha_estado ON agenda (fecha, estado);
ANALYZE TABLE agenda;

-- ===== DESPUÉS: con índice =====
EXPLAIN SELECT fecha, estado, COUNT(*)
        FROM agenda
        WHERE fecha BETWEEN '2026-03-01' AND '2026-03-31' AND estado = 'atendida'
        GROUP BY fecha, estado;

