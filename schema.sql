-- Base de datos de la cartelera del teatro
-- Archivo guardado en UTF-8 (sin BOM).
-- Importar con: mysql -u root -p --default-character-set=utf8mb4 < schema.sql

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS TEATRO;
CREATE DATABASE TEATRO DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE TEATRO;

CREATE TABLE obra (
    cod_obra    INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    fecha_obra  DATETIME NOT NULL,
    aforo       INT NOT NULL,
    disponibles INT NOT NULL,
    sala        VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ventas (
    numero_venta INT AUTO_INCREMENT PRIMARY KEY,
    cod_obra     INT NOT NULL,
    comprador    VARCHAR(80) NOT NULL,
    fecha_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ventas_obra (cod_obra),
    CONSTRAINT fk_ventas_obra FOREIGN KEY (cod_obra)
        REFERENCES obra (cod_obra)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las fechas se generan a partir de NOW() para que los registros de prueba
-- sigan siendo útiles (una obra futura, una pasada) sin editarlos a mano.
INSERT INTO obra (nombre, fecha_obra, aforo, disponibles, sala) VALUES
('La casa de Bernarda Alba', DATE_ADD(NOW(), INTERVAL 4 DAY), 220, 180, 'Sala Principal'),
('Bodas de sangre',          DATE_ADD(NOW(), INTERVAL 12 DAY), 160, 6,  'Sala Alterna'),
('El avaro',                 DATE_ADD(NOW(), INTERVAL 20 DAY), 90,  0,  'Sala Alterna'),
('Fuenteovejuna',            DATE_SUB(NOW(), INTERVAL 9 DAY), 150, 25, 'Sala Principal'),
('Sueño de una noche de verano', DATE_ADD(NOW(), INTERVAL 2 DAY), 120, 120, 'Patio Central');
