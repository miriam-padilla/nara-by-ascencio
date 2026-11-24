-- NARA by Ascencio: 6 tablas (ejecuta TODO en phpMyAdmin > SQL)
-- usuarios, paciente, servicio, agenda, seguimiento, bitacora
DROP DATABASE IF EXISTS clinica;
CREATE DATABASE clinica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinica;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('administrador','trabajador','paciente') NOT NULL,
  permisos TEXT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE paciente (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  edad INT,
  sexo ENUM('F','M','Otro'),
  telefono VARCHAR(15),
  activo ENUM('Si','No') NOT NULL DEFAULT 'Si',
  tipo_atencion ENUM('Reducción de peso','Diabetes','Resistencia a la insulina','Hipertensión',
                     'Dislipidemias','Hipotiroidismo','Menopausia','Síndrome poliendocrino metabólico',
                     'Embarazo','Niños','Otro'),
  peso_inicial DECIMAL(5,2),
  talla_cm DECIMAL(5,2),
  objetivo VARCHAR(255),
  alergias TEXT,
  plan_alimenticio TEXT,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE servicio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria ENUM('nutricion','corporal','facial') NOT NULL DEFAULT 'nutricion',
  nombre VARCHAR(100) NOT NULL,
  duracion_min INT NOT NULL,
  costo DECIMAL(8,2) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE agenda (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT NULL,
  servicio_id INT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  estado ENUM('programada','cancelada','atendida','bloqueada') NOT NULL DEFAULT 'programada',
  bloque VARCHAR(20) GENERATED ALWAYS AS (
    IF(estado IN ('programada','bloqueada'), CONCAT(fecha, ' ', hora), NULL)
  ) STORED,
  UNIQUE KEY uq_bloque (bloque),
  FOREIGN KEY (paciente_id) REFERENCES paciente(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicio(id)
) ENGINE=InnoDB;

CREATE TABLE seguimiento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT NOT NULL,
  agenda_id INT NULL,
  fecha DATE NOT NULL,
  peso DECIMAL(5,2),
  cintura_cm DECIMAL(5,2),
  adherencia ENUM('baja','media','alta'),
  observaciones TEXT,
  proxima_revision DATE,
  FOREIGN KEY (paciente_id) REFERENCES paciente(id) ON DELETE CASCADE,
  FOREIGN KEY (agenda_id) REFERENCES agenda(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE bitacora (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  accion VARCHAR(50) NOT NULL,
  tabla_afectada VARCHAR(50),
  detalle VARCHAR(255),
  fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO servicio (categoria, nombre, duracion_min, costo) VALUES
  ('nutricion', 'Primera consulta nutricional', 60, 600.00),
  ('nutricion', 'Consulta de seguimiento', 45, 400.00),
  ('nutricion', 'Plan alimenticio personalizado', 60, 500.00),
  ('corporal', 'Tratamiento corporal', 60, 0.00),
  ('facial', 'Tratamiento facial', 60, 0.00);
