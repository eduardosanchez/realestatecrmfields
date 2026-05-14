-- ============================================================
-- Tabla de log/bitácora de seguimiento por consulta
-- PREFIJO: zu4s_
-- ============================================================
CREATE TABLE IF NOT EXISTS zu4s_re_consulta_log (
    rowid           INT          AUTO_INCREMENT PRIMARY KEY,
    fk_consulta     INT          NOT NULL,
    date_log        DATETIME     NOT NULL,
    estado_anterior VARCHAR(32)  DEFAULT NULL,
    estado_nuevo    VARCHAR(32)  DEFAULT NULL,
    nota            TEXT         DEFAULT NULL,
    fk_user         INT          DEFAULT NULL,
    date_creation   DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
