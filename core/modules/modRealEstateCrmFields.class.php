<?php
/* ============================================================
 * Módulo: RealEstateCrmFields
 * Descripción: CRM inmobiliario - visibilidad de campos por tipo/subtipo
 * Prefijo DB: zu4s_
 * ============================================================ */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modRealEstateCrmFields extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db              = $db;
        $this->numero          = 500100;
        $this->rights_class    = 'realestatecrmfields';
        $this->family          = 'crm';
        $this->family_position = 100;
        $this->name            = 'RealEstateCrmFields';
        $this->description     = 'CRM Inmobiliario - Visibilidad de campos por tipo y subtipo de tercero';
        $this->version         = '1.0.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto           = 'building';
        $this->editor_name     = 'CRM Inmobiliario';
        $this->editor_url      = '';

        // Hooks donde actúa el módulo
        // Dolibarr 22+: registra dinámicamente desde module_parts
        // Archivo: hooks/actions_realestatecrmfields.class.php
        // Clase:   Actions_realestatecrmfields
        $this->module_parts = [
            'hooks' => [
                'thirdpartycard',
                'thirdpartylist',
                'extrafields',
                'admin',
                'actioncomm',
                'main',           // contexto inicializado en main.inc.php — necesario para printCommonFooter
            ],
            'hookfilepath' => '/custom/realestatecrmfields/hooks/actions_realestatecrmfields.class.php',
            'css' => ['/custom/realestatecrmfields/css/realestate.css'],
            'js'  => ['/custom/realestatecrmfields/js/realestate.js', '/custom/realestatecrmfields/js/consultas.js', '/custom/realestatecrmfields/js/gestion.js', '/custom/realestatecrmfields/js/propietario.js'],
        ];

        // Permisos
        $this->rights = [];
        $r = 0;

        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = 'Administrar configuración CRM inmobiliario';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'setup';
        $r++;

        // Diccionario de subtipos (aparece en /admin/dict.php)
        $this->dictionaries = [
            'langs'            => 'realestatecrmfields',
            'tabname'          => ['c_re_subtypent'],
            'tablib'           => ['SubtipoTercero'],
            'tabsql'           => ['SELECT f.rowid as rowid, f.code, f.libelle, f.fk_typent, f.active, f.position FROM ' . MAIN_DB_PREFIX . 'c_re_subtypent as f'],
            'tabsqlsort'       => ['fk_typent ASC, position ASC'],
            'tabfield'         => ['code,libelle,fk_typent,position'],
            'tabfieldvalue'    => ['code,libelle,fk_typent,position'],
            'tabfieldinsert'   => ['code,libelle,fk_typent,position'],
            'tabrowid'         => ['rowid'],
            'tabcond'          => [$conf->realestatecrmfields->enabled ?? 0],
            'tabhelp'          => [['code' => 'Código único (ej: GARAJE)', 'fk_typent' => 'Tipo padre: ACTIVO, ACTOR o SERVICIO']],
        ];

        // Menú de administración
        $this->menu = [];
        $r = 0;
        $this->menu[$r] = [
            'fk_menu'  => 'fk_mainmenu=home,fk_leftmenu=setup',
            'type'     => 'left',
            'titre'    => 'CRM Inmobiliario',
            'mainmenu' => 'home',
            'leftmenu' => 'realestatecrmfields',
            'url'      => '/custom/realestatecrmfields/admin/setup.php',
            'langs'    => 'realestatecrmfields',
            'position' => 500,
            'enabled'  => '$conf->realestatecrmfields->enabled',
            'perms'    => '$user->rights->realestatecrmfields->setup',
            'target'   => '',
            'user'     => 2,
        ];
        $r++;

        $this->menu[$r] = [
            'fk_menu'  => 'fk_mainmenu=home,fk_leftmenu=realestatecrmfields',
            'type'     => 'left',
            'titre'    => 'Visibilidad de Campos',
            'mainmenu' => 'home',
            'leftmenu' => 'realestatecrmfields_visibility',
            'url'      => '/custom/realestatecrmfields/admin/visibility.php',
            'langs'    => 'realestatecrmfields',
            'position' => 501,
            'enabled'  => '$conf->realestatecrmfields->enabled',
            'perms'    => '$user->rights->realestatecrmfields->setup',
            'target'   => '',
            'user'     => 2,
        ];
        $r++;

        $this->menu[$r] = [
            'fk_menu'  => 'fk_mainmenu=companies',
            'type'     => 'left',
            'titre'    => 'Actores',
            'mainmenu' => 'companies',
            'leftmenu' => 'realestatecrmfields_actores',
            'url'      => '/custom/realestatecrmfields/actores.php',
            'langs'    => 'realestatecrmfields',
            'position' => 30,
            'enabled'  => '$conf->realestatecrmfields->enabled',
            'perms'    => '1',
            'target'   => '',
            'user'     => 0,
        ];
        $r++;

        $this->menu[$r] = [
            'fk_menu'  => 'fk_mainmenu=companies',
            'type'     => 'left',
            'titre'    => 'Pendientes',
            'mainmenu' => 'companies',
            'leftmenu' => 'realestatecrmfields_pendientes',
            'url'      => '/custom/realestatecrmfields/pendientes.php',
            'langs'    => 'realestatecrmfields',
            'position' => 31,
            'enabled'  => '$conf->realestatecrmfields->enabled',
            'perms'    => '1',
            'target'   => '',
            'user'     => 0,
        ];
        $r++;
        $this->menu[$r] = [
            'fk_menu'  => 'fk_mainmenu=companies',
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => 'companies',
            'leftmenu' => 'realestatecrmfields_dashboard',
            'url'      => '/custom/realestatecrmfields/dashboard.php',
            'langs'    => 'realestatecrmfields',
            'position' => 32,
            'enabled'  => '$conf->realestatecrmfields->enabled',
            'perms'    => '1',
            'target'   => '',
            'user'     => 0,
        ];
    }

    /**
     * Ejecutado al activar el módulo
     */
    public function init($options = '')
    {
        $sql = [];

        // Crear tablas
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_c_re_subtypent.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_extrafields_visibility.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_consulta.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_consulta_alter.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_consulta_log.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_gestion_propietario.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_propietario_activo.sql', 0));
        $sql[] = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/llx_re_consulta_cierre.sql', 0));

        $result = $this->_init($sql, $options);

        if ($result) {
            // Índices (ignorar error si ya existen)
            $this->db->query("CREATE INDEX idx_re_consulta_recordatorio ON " . MAIN_DB_PREFIX . "re_consulta (fecha_recordatorio, recordatorio_done)");
            $this->db->query("CREATE INDEX idx_re_consulta_log_fk ON " . MAIN_DB_PREFIX . "re_consulta_log (fk_consulta)");

            // Ejecutar datos iniciales separados (ALTER + INSERTs)
            $dataSql = file_get_contents(dol_buildpath('/custom/realestatecrmfields/sql/data.sql', 0));
            $statements = array_filter(array_map('trim', explode(';', $dataSql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    $this->db->query($stmt); // Ignorar errores de IGNORE/IF EXISTS
                }
            }
        }

        return $result;
    }

    /**
     * Ejecutado al desactivar el módulo
     */
    public function remove($options = '')
    {
        $sql = [];
        return $this->_remove($sql, $options);
    }
}
