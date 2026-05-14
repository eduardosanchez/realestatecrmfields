-- ============================================================
-- Datos iniciales del módulo RealEstateCrmFields
-- PREFIJO: zu4s_
-- Nota: zu4s_c_typent no tiene columna entity en esta versión
--       code es varchar(12) → usamos códigos cortos RE_ACT/RE_AOR/RE_SRV
-- ============================================================

-- Agregar columna subtipo a zu4s_societe si no existe
ALTER TABLE zu4s_societe
    ADD COLUMN IF NOT EXISTS fk_re_subtypent VARCHAR(32) DEFAULT NULL;

-- ============================================================
-- Desactivar tipos nativos de Dolibarr que no aplican al CRM
-- ============================================================
UPDATE zu4s_c_typent SET active = 0
WHERE code NOT IN ('RE_ACT', 'RE_AOR', 'RE_SRV');

-- ============================================================
-- Insertar tipos principales (code max 12 chars)
-- RE_ACT = Activo Inmobiliario
-- RE_AOR = Actor
-- RE_SRV = Servicio
-- ============================================================
INSERT IGNORE INTO zu4s_c_typent (code, libelle, active, module, position) VALUES
('RE_ACT', 'Activo Inmobiliario', 1, 'realestatecrmfields', 10),
('RE_AOR', 'Actor',               1, 'realestatecrmfields', 20),
('RE_SRV', 'Servicio',            1, 'realestatecrmfields', 30);

-- ============================================================
-- Insertar subtipos (fk_typent referencia code de zu4s_c_typent)
-- ============================================================
INSERT IGNORE INTO zu4s_c_re_subtypent (code, libelle, fk_typent, position) VALUES
-- ACTIVOS
('GARAJE',        'Garaje',                   'RE_ACT', 10),
('PLAYA_ESTAC',   'Playa de Estacionamiento', 'RE_ACT', 20),
('ESTAC_SERV',    'Estación de Servicio',     'RE_ACT', 30),
('EDIF_POTENC',   'Edificio con Potencial',   'RE_ACT', 40),
('GALPON',        'Galpón',                   'RE_ACT', 50),
('TERRENO',       'Terreno',                  'RE_ACT', 60),
-- ACTORES
('INVERSOR',      'Inversor',                 'RE_AOR', 10),
('PROPIETARIO',   'Propietario',              'RE_AOR', 20),
('OPERADOR',      'Operador',                 'RE_AOR', 30),
('CORREDOR',      'Corredor',                 'RE_AOR', 40),
('DESARROLLADOR', 'Desarrollador',            'RE_AOR', 50),
('ADMINISTRADOR', 'Administrador',            'RE_AOR', 60),
-- SERVICIOS
('ESCRIBANIA',    'Escribanía',               'RE_SRV', 10),
('ABOGADO',       'Abogado',                  'RE_SRV', 20),
('ARQUITECTO',    'Arquitecto',               'RE_SRV', 30),
('CONTADOR',      'Contador',                 'RE_SRV', 40),
('CONSULTOR',     'Consultor',                'RE_SRV', 50);

-- Agregar TERRENO si no existe
INSERT IGNORE INTO zu4s_c_re_subtypent (code, libelle, fk_typent, position)
VALUES ('TERRENO', 'Terreno', 'RE_ACT', 60);
