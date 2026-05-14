-- Agregar campo fecha_cierre y subtipo de cierre a re_consulta
ALTER TABLE zu4s_re_consulta
    ADD COLUMN IF NOT EXISTS fecha_cierre DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS motivo_cierre VARCHAR(32) DEFAULT NULL;
