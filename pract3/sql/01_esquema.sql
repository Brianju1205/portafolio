-- =====================================================================
--  Sistema de Gestión de Documentos de una Oficina
--  UNACH · ETDA · Campus IV Tapachula
--  Script 1/2: creación de la base de datos, tablas e índices (MySQL 8)
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS gestion_documentos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gestion_documentos;

-- ---------------------------------------------------------------------
--  documentos: datos de registro de cada oficio recibido
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documentos (
  id_documento   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  no_oficio      VARCHAR(50)   NOT NULL,
  fecha          DATE          NOT NULL,
  asunto         VARCHAR(255)  NOT NULL,
  remitente      VARCHAR(150)  NOT NULL,
  destinatario   VARCHAR(150)  NOT NULL,
  estatus        ENUM('Recibido', 'En proceso', 'Atendido') NOT NULL DEFAULT 'Recibido',
  archivo_pdf    VARCHAR(255)  NULL COMMENT 'Nombre del archivo PDF digitalizado',
  creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_documento),
  UNIQUE KEY uq_documentos_no_oficio (no_oficio),
  KEY idx_documentos_fecha (fecha),
  KEY idx_documentos_estatus_fecha (estatus, fecha),
  KEY idx_documentos_remitente (remitente),
  KEY idx_documentos_destinatario (destinatario)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
--  documentos_archivos: contenido binario del PDF (1:1 con documentos).
--  Solo se usa en servidores sin disco persistente (Vercel). En XAMPP o
--  Docker los PDF se guardan en la carpeta pdf/ y aquí no queda nada.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documentos_archivos (
  id_documento INT UNSIGNED NOT NULL,
  contenido    MEDIUMBLOB   NOT NULL,
  tamano_bytes INT UNSIGNED NOT NULL,
  hash_sha256  CHAR(64)     NOT NULL,
  subido_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_documento),
  CONSTRAINT fk_archivos_documento
    FOREIGN KEY (id_documento) REFERENCES documentos (id_documento)
    ON DELETE CASCADE
) ENGINE = InnoDB;
