-- ============================================================
-- Tabla de gestión/contacto con propietarios de activos
-- PREFIJO: zu4s_
-- ============================================================
CREATE TABLE IF NOT EXISTS zu4s_re_gestion_propietario (
    rowid                   INT          AUTO_INCREMENT PRIMARY KEY,
    fk_societe_activo       INT          NOT NULL,
    fk_societe_propietario  INT          DEFAULT NULL,
    propietario_nombre      VARCHAR(255) DEFAULT NULL,
    propietario_telefono    VARCHAR(64)  DEFAULT NULL,
    fecha                   DATETIME     NOT NULL,
    canal                   VARCHAR(32)  NOT NULL DEFAULT 'TELEFONO',
    resultado               VARCHAR(32)  NOT NULL DEFAULT 'ATENDIO',
    nota                    TEXT         DEFAULT NULL,
    fecha_recordatorio      DATE         DEFAULT NULL,
    nota_recordatorio       VARCHAR(255) DEFAULT NULL,
    fk_user_vendedor        INT          DEFAULT NULL,
    fk_user_recordatorio    INT          DEFAULT NULL,
    recordatorio_done       TINYINT(1)   DEFAULT 0,
    date_creation           DATETIME     DEFAULT NULL,
    fk_user_creat           INT          DEFAULT NULL,
    date_modification       DATETIME     DEFAULT NULL,
    fk_user_modif           INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
