-- ROLES Y PRIVILEGIOS (MariaDB de XAMPP). Ejecutar DESPUÉS de nara.sql.
CREATE ROLE IF NOT EXISTS r_publico;
CREATE ROLE IF NOT EXISTS r_paciente;
CREATE ROLE IF NOT EXISTS r_trabajador;
CREATE ROLE IF NOT EXISTS r_admin;

-- Público (inicio, login y registro)
GRANT SELECT, INSERT ON clinica.usuarios TO r_publico;
GRANT INSERT ON clinica.paciente TO r_publico;
GRANT SELECT ON clinica.servicio TO r_publico;
GRANT INSERT ON clinica.bitacora TO r_publico;

-- Paciente: lee su información; agenda con insertar/actualizar; sin DELETE
GRANT SELECT ON clinica.usuarios TO r_paciente;
GRANT UPDATE (password) ON clinica.usuarios TO r_paciente;
GRANT SELECT ON clinica.paciente TO r_paciente;
GRANT SELECT ON clinica.servicio TO r_paciente;
GRANT SELECT ON clinica.seguimiento TO r_paciente;
GRANT SELECT, INSERT, UPDATE ON clinica.agenda TO r_paciente;
GRANT INSERT ON clinica.bitacora TO r_paciente;

-- Trabajador (secretaria): según los permisos que le dé la administradora; no borra ni gestiona usuarios
GRANT SELECT ON clinica.usuarios TO r_trabajador;
GRANT UPDATE (password) ON clinica.usuarios TO r_trabajador;
GRANT SELECT, INSERT, UPDATE ON clinica.paciente TO r_trabajador;
GRANT SELECT, INSERT, UPDATE ON clinica.servicio TO r_trabajador;
GRANT SELECT, INSERT, UPDATE ON clinica.agenda TO r_trabajador;
GRANT SELECT, INSERT, UPDATE ON clinica.seguimiento TO r_trabajador;
GRANT INSERT ON clinica.bitacora TO r_trabajador;

-- Administrador: control total
GRANT ALL PRIVILEGES ON clinica.* TO r_admin;

CREATE USER IF NOT EXISTS 'app_publico'@'localhost'    IDENTIFIED BY 'Publico#2026';
CREATE USER IF NOT EXISTS 'app_paciente'@'localhost'   IDENTIFIED BY 'Paciente#2026';
CREATE USER IF NOT EXISTS 'app_trabajador'@'localhost' IDENTIFIED BY 'Trabajador#2026';
CREATE USER IF NOT EXISTS 'app_admin'@'localhost'      IDENTIFIED BY 'Admin#2026';

GRANT r_publico    TO 'app_publico'@'localhost';
GRANT r_paciente   TO 'app_paciente'@'localhost';
GRANT r_trabajador TO 'app_trabajador'@'localhost';
GRANT r_admin      TO 'app_admin'@'localhost';

SET DEFAULT ROLE r_publico    FOR 'app_publico'@'localhost';
SET DEFAULT ROLE r_paciente   FOR 'app_paciente'@'localhost';
SET DEFAULT ROLE r_trabajador FOR 'app_trabajador'@'localhost';
SET DEFAULT ROLE r_admin      FOR 'app_admin'@'localhost';
FLUSH PRIVILEGES;
