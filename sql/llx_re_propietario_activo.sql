-- Tabla de relación propietario <-> activo inmobiliario (con historial)
-- Permite múltiples propietarios por activo y registro de cambios
CREATE TABLE IF NOT EXISTS zu4s_re_propietario_activo (
    rowid                   INT          AUTO_INCREMENT PRIMARY KEY,
    fk_societe_activo       INT          NOT NULL,
    fk_societe_propietario  INT          DEFAULT NULL,
    propietario_nombre      VARCHAR(255) DEFAULT NULL,
    propietario_telefono    VARCHAR(64)  DEFAULT NULL,
    rol                     VARCHAR(32)  NOT NULL DEFAULT 'PROPIETARIO',
    fecha_desde             DATE         NOT NULL,
    fecha_hasta             DATE         DEFAULT NULL,
    activo                  TINYINT(1)   NOT NULL DEFAULT 1,
    nota                    TEXT         DEFAULT NULL,
    date_creation           DATETIME     DEFAULT NULL,
    fk_user_creat           INT          DEFAULT NULL,
    date_modification       DATETIME     DEFAULT NULL,
    fk_user_modif           INT          DEFAULT NULL,
    INDEX idx_activo (fk_societe_activo),
    INDEX idx_propietario (fk_societe_propietario),
    INDEX idx_activo_activo (fk_societe_activo, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
