-- ============================================================
-- Tabla de consultas de interesados sobre propiedades
-- PREFIJO: zu4s_
-- ============================================================
CREATE TABLE IF NOT EXISTS zu4s_re_consulta (
    rowid               INT          AUTO_INCREMENT PRIMARY KEY,
    date_consulta       DATETIME     NOT NULL,
    fk_societe_activo   INT          NOT NULL,
    fk_societe_actor    INT          DEFAULT NULL,
    actor_nombre        VARCHAR(255) DEFAULT NULL,
    actor_telefono      VARCHAR(64)  DEFAULT NULL,
    canal               VARCHAR(32)  NOT NULL DEFAULT 'WHATSAPP',
    estado              VARCHAR(32)  NOT NULL DEFAULT 'CONSULTO',
    nota                TEXT         DEFAULT NULL,
    busqueda            TEXT         DEFAULT NULL,
    rango_usd_min       INT          DEFAULT NULL,
    rango_usd_max       INT          DEFAULT NULL,
    fk_user_vendedor    INT          DEFAULT NULL,
    date_creation       DATETIME     DEFAULT NULL,
    fk_user_creat       INT          DEFAULT NULL,
    date_modification   DATETIME     DEFAULT NULL,
    fk_user_modif       INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
