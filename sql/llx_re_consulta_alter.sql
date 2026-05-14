-- Agregar campos de recordatorio a zu4s_re_consulta
ALTER TABLE zu4s_re_consulta
    ADD COLUMN IF NOT EXISTS fecha_recordatorio   DATE         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS nota_recordatorio    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS fk_user_recordatorio INT          DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS recordatorio_done    TINYINT(1)   DEFAULT 0;
