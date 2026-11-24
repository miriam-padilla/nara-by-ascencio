#!/bin/bash
# Versión para Linux/Mac. En Windows usa 07_pruebas_seguridad.bat (mismos comandos).
M="mariadb"
echo "=== app_paciente: puede leer servicios ==="; $M -uapp_paciente -p'Paciente#2026' -e "SELECT COUNT(*) AS servicios FROM clinica.servicio;"
echo "=== app_paciente: NO puede borrar tablas (esperado: error 1142) ==="; $M -uapp_paciente -p'Paciente#2026' -e "DROP TABLE clinica.agenda;" 2>&1
echo "=== app_paciente: NO puede borrar citas (error 1142) ==="; $M -uapp_paciente -p'Paciente#2026' -e "DELETE FROM clinica.agenda;" 2>&1
echo "=== app_paciente: NO puede leer la bitácora (error 1142) ==="; $M -uapp_paciente -p'Paciente#2026' -e "SELECT * FROM clinica.bitacora;" 2>&1
echo "=== app_publico: NO puede ver citas (error 1142) ==="; $M -uapp_publico -p'Publico#2026' -e "SELECT * FROM clinica.agenda;" 2>&1
echo "=== app_trabajador: NO puede crear tablas (error 1142) ==="; $M -uapp_trabajador -p'Trabajador#2026' -e "CREATE TABLE clinica.x (id INT);" 2>&1
echo "=== app_trabajador: NO puede borrar pacientes (error 1142) ==="; $M -uapp_trabajador -p'Trabajador#2026' -e "DELETE FROM clinica.paciente;" 2>&1
echo "=== app_admin: NO puede entrar a otras bases (error 1044/1142) ==="; $M -uapp_admin -p'Admin#2026' -e "SELECT COUNT(*) FROM mysql.user;" 2>&1
echo "=== respaldo: solo lee (no puede insertar, error 1142) ==="; $M -urespaldo -p'Respaldo#2026' -e "INSERT INTO clinica.servicio (nombre,costo) VALUES ('x',1);" 2>&1
