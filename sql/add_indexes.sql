-- Índices para mejorar performance de queries frecuentes
-- Ejecutar una sola vez en phpMyAdmin o consola MySQL
-- Todos usan IF NOT EXISTS — seguro ejecutar múltiples veces

-- ── re_consulta ──────────────────────────────────────────────
ALTER TABLE zu4s_re_consulta
    ADD INDEX IF NOT EXISTS idx_fk_societe_actor   (fk_societe_actor),
    ADD INDEX IF NOT EXISTS idx_fk_societe_activo  (fk_societe_activo),
    ADD INDEX IF NOT EXISTS idx_recordatorio       (recordatorio_done, fecha_recordatorio),
    ADD INDEX IF NOT EXISTS idx_estado             (estado),
    ADD INDEX IF NOT EXISTS idx_date_consulta      (date_consulta);

-- ── re_gestion_propietario ───────────────────────────────────
ALTER TABLE zu4s_re_gestion_propietario
    -- Índice simple para filtros por activo
    ADD INDEX IF NOT EXISTS idx_fk_societe_activo  (fk_societe_activo),
    -- Índice compuesto para la subquery MAX(fecha) GROUP BY fk_societe_activo
    -- Clave para la performance de captacion.php con 1000+ activos
    ADD INDEX IF NOT EXISTS idx_activo_fecha       (fk_societe_activo, fecha),
    ADD INDEX IF NOT EXISTS idx_recordatorio       (recordatorio_done, fecha_recordatorio),
    ADD INDEX IF NOT EXISTS idx_fecha              (fecha),
    ADD INDEX IF NOT EXISTS idx_resultado          (resultado);

-- ── re_propietario_activo ────────────────────────────────────
ALTER TABLE zu4s_re_propietario_activo
    ADD INDEX IF NOT EXISTS idx_fk_societe_activo (fk_societe_activo),
    ADD INDEX IF NOT EXISTS idx_activo            (activo);

-- ── societe_extrafields: campos usados en filtros y ORDER BY ─
ALTER TABLE zu4s_societe_extrafields
    ADD INDEX IF NOT EXISTS idx_fk_object       (fk_object),
    ADD INDEX IF NOT EXISTS idx_barrio          (barrio(50)),
    ADD INDEX IF NOT EXISTS idx_cocheras_fijas  (cocheras_fijas),
    ADD INDEX IF NOT EXISTS idx_usdventa        (usdventa),
    ADD INDEX IF NOT EXISTS idx_usdtasacion     (usdtasacion),
    ADD INDEX IF NOT EXISTS idx_enficha         (enficha),
    ADD INDEX IF NOT EXISTS idx_contactar       (contactar(20));

-- ── societe: campo JOIN principal ───────────────────────────
ALTER TABLE zu4s_societe
    ADD INDEX IF NOT EXISTS idx_fk_re_subtypent (fk_re_subtypent);

-- ── re_consulta_log ─────────────────────────────────────────
ALTER TABLE zu4s_re_consulta_log
    ADD INDEX IF NOT EXISTS idx_fk_consulta (fk_consulta);
