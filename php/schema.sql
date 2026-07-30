-- ============================================================
--  ESQUEMA BASE DE DATOS — FACTIBILIDAD (SOLICITUD DE REQUISITOS) DFM
--  Motor: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS factibilidad_dfm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE factibilidad_dfm;

-- ------------------------------------------------------------
-- Usuarios del sistema
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(120)  NOT NULL,
    email         VARCHAR(160)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    rol           ENUM('admin','lider','ingenieria','ventas') NOT NULL DEFAULT 'ingenieria',
    departamento  VARCHAR(100)  DEFAULT NULL,
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_login  DATETIME      DEFAULT NULL
) ENGINE=InnoDB;

-- Usuario administrador inicial  (password: Admin123!)
INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol)
VALUES ('Administrador', 'admin@dfm.com',
        '$2y$12$QKVHByGCKJhq.KZLqx5B4.F0bVCKkS7aSR8R2NUQhkIMh2OuXVkrq', 'admin');

-- ------------------------------------------------------------
-- Solicitudes de Factibilidad
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitudes (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio                 VARCHAR(20)  NOT NULL UNIQUE,
    cliente               VARCHAR(200) DEFAULT NULL,
    lider_proyecto        VARCHAR(200) DEFAULT NULL,
    fecha_entrada         DATE         DEFAULT NULL,
    fecha_entrega_equipo  DATE         DEFAULT NULL,
    fecha_estimada_cierre DATE         DEFAULT NULL,
    fecha_entrega_lider   DATE         DEFAULT NULL,
    fecha_cierre          DATE         DEFAULT NULL,
    porcentaje_completado DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    estado                ENUM('borrador','enviado','en_revision','aprobado','rechazado')
                          NOT NULL DEFAULT 'borrador',
    creado_por            INT UNSIGNED NOT NULL,
    enviado_en            DATETIME     DEFAULT NULL,
    creado_en             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sol_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Valores de los campos por solicitud (EAV flexible)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitud_campos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    solicitud_id  INT UNSIGNED NOT NULL,
    campo_clave   VARCHAR(60)  NOT NULL,   -- e.g. "diseno_dibujo_maestro"
    valor         TEXT         DEFAULT NULL,
    actualizado_en DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sol_campo (solicitud_id, campo_clave),
    CONSTRAINT fk_sc_sol FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Historial de cambios de estado
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitud_historial (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT UNSIGNED NOT NULL,
    estado_desde ENUM('borrador','enviado','en_revision','aprobado','rechazado') DEFAULT NULL,
    estado_hasta ENUM('borrador','enviado','en_revision','aprobado','rechazado') NOT NULL,
    usuario_id   INT UNSIGNED NOT NULL,
    comentario   TEXT         DEFAULT NULL,
    fecha        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_sol FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_usr FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)
) ENGINE=InnoDB;
