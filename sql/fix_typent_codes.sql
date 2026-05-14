-- ============================================================
-- CORRECCIÓN: Adaptar módulo a zu4s_c_typent sin columna entity
-- Ejecutar este script UNA VEZ en la base de datos
-- ============================================================

-- 1. Limpiar tipos viejos que no se insertaron bien (por el error de entity)
DELETE FROM zu4s_c_typent 
WHERE code IN ('ACTIVO','ACTOR','SERVICIO');

-- 2. Insertar tipos con codes cortos (varchar 12 OK)
INSERT IGNORE INTO zu4s_c_typent (code, libelle, active, module, position) VALUES
('RE_ACT', 'Activo Inmobiliario', 1, 'realestatecrmfields', 10),
('RE_AOR', 'Actor',               1, 'realestatecrmfields', 20),
('RE_SRV', 'Servicio',            1, 'realestatecrmfields', 30);

-- 3. Actualizar fk_typent en subtipos ya cargados
UPDATE zu4s_c_re_subtypent SET fk_typent = 'RE_ACT' WHERE fk_typent = 'ACTIVO';
UPDATE zu4s_c_re_subtypent SET fk_typent = 'RE_AOR' WHERE fk_typent = 'ACTOR';
UPDATE zu4s_c_re_subtypent SET fk_typent = 'RE_SRV' WHERE fk_typent = 'SERVICIO';

-- 4. Verificar resultado
SELECT 'TIPOS:' as tabla, id, code, libelle, active FROM zu4s_c_typent WHERE module = 'realestatecrmfields'
UNION ALL
SELECT 'SUBTIPOS:', rowid, code, libelle, active FROM zu4s_c_re_subtypent ORDER BY tabla, code;
