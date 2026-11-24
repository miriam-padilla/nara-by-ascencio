@echo off
REM Respaldo completo de la base "clinica" (XAMPP en Windows).
REM Programar en el Programador de tareas de Windows: diario a las 22:00.
set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DESTINO=C:\respaldos_nara
set RETENCION_DIAS=30

if not exist "%DESTINO%" mkdir "%DESTINO%"
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmm"') do set FECHA=%%i
set ARCHIVO=%DESTINO%\clinica_%FECHA%.sql

"%MYSQLDUMP%" --user=respaldo --password=Respaldo#2026 --single-transaction --routines --triggers --events clinica > "%ARCHIVO%"
if errorlevel 1 (
  echo %date% %time% ERROR en el respaldo %ARCHIVO% >> "%DESTINO%\bitacora_respaldos.txt"
  exit /b 1
)
echo %date% %time% OK %ARCHIVO% >> "%DESTINO%\bitacora_respaldos.txt"

REM Elimina respaldos con mas de %RETENCION_DIAS% dias
forfiles /p "%DESTINO%" /m clinica_*.sql /d -%RETENCION_DIAS% /c "cmd /c del @path" 2>nul
