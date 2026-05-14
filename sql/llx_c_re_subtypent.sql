-- ============================================================
-- Tabla de subtipos de terceros para CRM inmobiliario
-- PREFIJO: zu4s_
-- ============================================================
CREATE TABLE IF NOT EXISTS zu4s_c_re_subtypent (
    rowid       INTEGER      AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32)  NOT NULL,
    libelle     VARCHAR(100) NOT NULL,
    fk_typent   VARCHAR(32)  NOT NULL,
    active      TINYINT      NOT NULL DEFAULT 1,
    position    INTEGER      NOT NULL DEFAULT 0,
    entity      INTEGER      NOT NULL DEFAULT 1,
    UNIQUE KEY uk_c_re_subtypent (code, entity)
) ENGINE=InnoDB;
