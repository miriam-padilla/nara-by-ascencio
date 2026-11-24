@echo off
REM Restaura un respaldo en una base de PRUEBA (clinica_prueba) para verificarlo sin tocar la base real.
REM Uso: restaurar.bat C:\respaldos_nara\clinica_20261026_2200.sql
set MYSQL=C:\xampp\mysql\bin\mysql.exe
if "%~1"=="" (echo Indica el archivo de respaldo. & exit /b 1)
"%MYSQL%" -u root -e "DROP DATABASE IF EXISTS clinica_prueba; CREATE DATABASE clinica_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"%MYSQL%" -u root clinica_prueba < "%~1"
"%MYSQL%" -u root -e "SELECT 'usuarios' t, COUNT(*) n FROM clinica_prueba.usuarios UNION SELECT 'paciente', COUNT(*) FROM clinica_prueba.paciente UNION SELECT 'servicio', COUNT(*) FROM clinica_prueba.servicio UNION SELECT 'agenda', COUNT(*) FROM clinica_prueba.agenda UNION SELECT 'seguimiento', COUNT(*) FROM clinica_prueba.seguimiento UNION SELECT 'bitacora', COUNT(*) FROM clinica_prueba.bitacora;"
