-- ============================================================
-- Desinstalación del módulo RealEstateCrmFields
-- PREFIJO: zu4s_
-- ============================================================

DROP TABLE IF EXISTS zu4s_re_extrafields_visibility;
DROP TABLE IF EXISTS zu4s_c_re_subtypent;

ALTER TABLE zu4s_societe DROP COLUMN IF EXISTS fk_re_subtypent;

-- Reactivar tipos nativos de Dolibarr
UPDATE zu4s_c_typent SET active = 1 
WHERE module IS NULL OR module != 'realestatecrmfields';

-- Eliminar tipos custom
DELETE FROM zu4s_c_typent
WHERE module = 'realestatecrmfields';
