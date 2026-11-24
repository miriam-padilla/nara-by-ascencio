@echo off
chcp 65001 >nul
REM Pruebas de seguridad de roles. Ejecutar desde XAMPP (mysql.exe).
set M=C:\xampp\mysql\bin\mysql.exe

echo === app_paciente: puede leer servicios ===
"%M%" -uapp_paciente -pPaciente#2026 -e "SELECT COUNT(*) AS servicios FROM clinica.servicio;" 2>&1

echo === app_paciente: NO puede borrar tablas (esperado: error 1142) ===
"%M%" -uapp_paciente -pPaciente#2026 -e "DROP TABLE clinica.agenda;" 2>&1

echo === app_paciente: NO puede borrar citas (error 1142) ===
"%M%" -uapp_paciente -pPaciente#2026 -e "DELETE FROM clinica.agenda;" 2>&1

echo === app_paciente: NO puede leer la bitácora (error 1142) ===
"%M%" -uapp_paciente -pPaciente#2026 -e "SELECT * FROM clinica.bitacora;" 2>&1

echo === app_publico: NO puede ver citas (error 1142) ===
"%M%" -uapp_publico -pPublico#2026 -e "SELECT * FROM clinica.agenda;" 2>&1

echo === app_trabajador: NO puede crear tablas (error 1142) ===
"%M%" -uapp_trabajador -pTrabajador#2026 -e "CREATE TABLE clinica.x (id INT);" 2>&1

echo === app_trabajador: NO puede borrar pacientes (error 1142) ===
"%M%" -uapp_trabajador -pTrabajador#2026 -e "DELETE FROM clinica.paciente;" 2>&1

echo === app_admin: NO puede entrar a otras bases (error 1044/1142) ===
"%M%" -uapp_admin -pAdmin#2026 -e "SELECT COUNT(*) FROM mysql.user;" 2>&1

echo === respaldo: solo lee (no puede insertar, error 1142) ===
"%M%" -urespaldo -pRespaldo#2026 -e "INSERT INTO clinica.servicio (nombre,costo) VALUES ('x',1);" 2>&1

pause