-- Base de datos de la práctica 3

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS gestion_documentos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gestion_documentos;

-- documentos: datos de cada oficio
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

-- documentos_archivos: PDF guardado en la BD (solo en Vercel)
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
