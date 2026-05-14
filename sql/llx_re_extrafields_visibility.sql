-- ============================================================
-- Tabla de visibilidad de campos personalizados por tipo/subtipo
-- PREFIJO: zu4s_
-- ============================================================
CREATE TABLE IF NOT EXISTS zu4s_re_extrafields_visibility (
    rowid           INTEGER      AUTO_INCREMENT PRIMARY KEY,
    extrafield_id   INTEGER      NOT NULL,
    elementtype     VARCHAR(50)  NOT NULL DEFAULT 'societe',
    typent_code     VARCHAR(32)  DEFAULT NULL,
    subtypent_code  VARCHAR(32)  DEFAULT NULL,
    UNIQUE KEY uk_re_ef_vis (extrafield_id, typent_code, subtypent_code)
) ENGINE=InnoDB;
