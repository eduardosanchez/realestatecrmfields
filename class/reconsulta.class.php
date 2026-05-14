<?php
/**
 * Clase para gestionar consultas de interesados sobre propiedades
 */
class ReConsulta
{
    public $db;
    public $error = '';

    // Campos del objeto
    public $rowid;
    public $date_consulta;
    public $fk_societe_activo;
    public $fk_societe_actor;
    public $actor_nombre;
    public $actor_telefono;
    public $canal;
    public $estado;
    public $nota;
    public $busqueda;
    public $rango_usd_min;
    public $rango_usd_max;
    public $fk_user_vendedor;
    public $fecha_recordatorio;
    public $nota_recordatorio;
    public $fk_user_recordatorio;
    public $recordatorio_done;
    public $fecha_cierre;
    public $motivo_cierre;
    public $date_creation;
    public $fk_user_creat;

    const CANALES = [
        'WHATSAPP'  => 'WhatsApp',
        'INSTAGRAM' => 'Instagram DM',
        'EMAIL'     => 'Email',
        'TELEFONO'  => 'Teléfono',
    ];

    const ESTADOS = [
        'CONSULTO'          => 'Consultó',
        'VISITO'            => 'Visitó',
        'OFRECIO'           => 'Ofreció',
        'CERRO'             => 'Cerró',
    ];

    // Subtipos de cierre — se registran cuando estado = CERRO
    const MOTIVOS_CIERRE = [
        'COMPRA'            => '✅ Compró',
        'NO_INTERES'        => '❌ Sin interés',
        'PRECIO'            => '💲 Precio no convenció',
        'OTRO_INMUEBLE'     => '🏠 Eligió otro inmueble',
        'SIN_RESOLUCION'    => '⏸ Sin resolución',
    ];

    const ESTADO_COLORS = [
        'CONSULTO'          => '#6c757d',
        'VISITO'            => '#0d6efd',
        'OFRECIO'           => '#fd7e14',
        'CERRO'             => '#198754',
    ];

    public function __construct($db) { $this->db = $db; }

    public function create($user)
    {
        $now = dol_now();
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "re_consulta
                (date_consulta, fk_societe_activo, fk_societe_actor, actor_nombre, actor_telefono,
                 canal, estado, nota, busqueda, rango_usd_min, rango_usd_max,
                 fk_user_vendedor, fecha_recordatorio, nota_recordatorio,
                 fk_user_recordatorio, date_creation, fk_user_creat)
                VALUES (
                    '" . $this->db->idate($this->date_consulta ?: $now) . "',
                    " . (int)$this->fk_societe_activo . ",
                    " . ($this->fk_societe_actor ? (int)$this->fk_societe_actor : 'NULL') . ",
                    " . ($this->actor_nombre   ? "'" . $this->db->escape($this->actor_nombre)   . "'" : 'NULL') . ",
                    " . ($this->actor_telefono ? "'" . $this->db->escape($this->actor_telefono) . "'" : 'NULL') . ",
                    '" . $this->db->escape($this->canal  ?: 'WHATSAPP') . "',
                    '" . $this->db->escape($this->estado ?: 'CONSULTO') . "',
                    " . ($this->nota      ? "'" . $this->db->escape($this->nota)      . "'" : 'NULL') . ",
                    " . ($this->busqueda  ? "'" . $this->db->escape($this->busqueda)  . "'" : 'NULL') . ",
                    " . ($this->rango_usd_min !== null ? (int)$this->rango_usd_min : 'NULL') . ",
                    " . ($this->rango_usd_max !== null ? (int)$this->rango_usd_max : 'NULL') . ",
                    " . ($this->fk_user_vendedor ? (int)$this->fk_user_vendedor : 'NULL') . ",
                    " . ($this->fecha_recordatorio   ? "'" . $this->db->escape($this->fecha_recordatorio)  . "'" : 'NULL') . ",
                    " . ($this->nota_recordatorio    ? "'" . $this->db->escape($this->nota_recordatorio)   . "'" : 'NULL') . ",
                    " . ($this->fk_user_recordatorio ? (int)$this->fk_user_recordatorio : 'NULL') . ",
                    '" . $this->db->idate($now) . "',
                    " . (int)$user->id . "
                )";
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . 're_consulta');
        return $this->rowid;
    }

    public function update($user)
    {
        $now = dol_now();
        $sql = "UPDATE " . MAIN_DB_PREFIX . "re_consulta SET
                    date_consulta     = '" . $this->db->idate($this->date_consulta) . "',
                    fk_societe_actor  = " . ($this->fk_societe_actor ? (int)$this->fk_societe_actor : 'NULL') . ",
                    actor_nombre      = " . ($this->actor_nombre   ? "'" . $this->db->escape($this->actor_nombre)   . "'" : 'NULL') . ",
                    actor_telefono    = " . ($this->actor_telefono ? "'" . $this->db->escape($this->actor_telefono) . "'" : 'NULL') . ",
                    canal             = '" . $this->db->escape($this->canal)   . "',
                    estado            = '" . $this->db->escape($this->estado)  . "',
                    nota              = " . ($this->nota     ? "'" . $this->db->escape($this->nota)     . "'" : 'NULL') . ",
                    busqueda          = " . ($this->busqueda ? "'" . $this->db->escape($this->busqueda) . "'" : 'NULL') . ",
                    rango_usd_min     = " . ($this->rango_usd_min !== null ? (int)$this->rango_usd_min : 'NULL') . ",
                    rango_usd_max     = " . ($this->rango_usd_max !== null ? (int)$this->rango_usd_max : 'NULL') . ",
                    fk_user_vendedor      = " . ($this->fk_user_vendedor ? (int)$this->fk_user_vendedor : 'NULL') . ",
                    fecha_recordatorio    = " . ($this->fecha_recordatorio   ? "'" . $this->db->escape($this->fecha_recordatorio)  . "'" : 'NULL') . ",
                    nota_recordatorio     = " . ($this->nota_recordatorio    ? "'" . $this->db->escape($this->nota_recordatorio)   . "'" : 'NULL') . ",
                    fk_user_recordatorio  = " . ($this->fk_user_recordatorio ? (int)$this->fk_user_recordatorio : 'NULL') . ",
                    date_modification     = '" . $this->db->idate($now) . "',
                    fk_user_modif     = " . (int)$user->id . "
                WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function delete($user)
    {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "re_consulta WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function fetch($rowid)
    {
        $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "re_consulta WHERE rowid = " . (int)$rowid;
        $res = $this->db->query($sql);
        if (!$res || !$this->db->num_rows($res)) return -1;
        $obj = $this->db->fetch_object($res);
        foreach ((array)$obj as $k => $v) $this->$k = $v;
        $this->date_consulta = $this->db->jdate($this->date_consulta);
        return 1;
    }

    /**
     * Lista consultas de una propiedad (activo)
     */
    public function fetchByActivo($socidActivo, $limit = 50)
    {
        return $this->_fetchList("c.fk_societe_activo = " . (int)$socidActivo, $limit);
    }

    /**
     * Lista consultas de un actor (interesado)
     */
    public function fetchByActor($socidActor, $limit = 50)
    {
        return $this->_fetchList("c.fk_societe_actor = " . (int)$socidActor, $limit);
    }

    private function _fetchList($where, $limit)
    {
        $sql = "SELECT c.*,
                    sa.nom AS activo_nom,
                    so.nom AS actor_nom,
                    CONCAT(u.firstname, ' ', u.lastname) AS vendedor_nom,
                    ef.calle AS activo_calle, ef.numero AS activo_numero,
                    (SELECT COUNT(*) FROM " . MAIN_DB_PREFIX . "re_consulta_log l WHERE l.fk_consulta = c.rowid) AS log_count
                FROM " . MAIN_DB_PREFIX . "re_consulta c
                LEFT JOIN " . MAIN_DB_PREFIX . "societe sa ON sa.rowid = c.fk_societe_activo
                LEFT JOIN " . MAIN_DB_PREFIX . "societe so ON so.rowid = c.fk_societe_actor
                LEFT JOIN " . MAIN_DB_PREFIX . "user u     ON u.rowid  = c.fk_user_vendedor
                LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef ON ef.fk_object = c.fk_societe_activo
                WHERE $where
                ORDER BY c.date_consulta DESC
                LIMIT " . (int)$limit;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return []; }
        $rows = [];
        while ($obj = $this->db->fetch_object($res)) {
            // date_consulta es string 'Y-m-d H:i:s' en esta instalación — convertir a timestamp Unix
            $obj->date_consulta = $obj->date_consulta ? strtotime($obj->date_consulta) : 0;
            // Limpiar campos de texto para json_encode seguro
            foreach (['nota','busqueda','actor_nom','activo_nom','vendedor_nom','activo_calle','actor_nombre','actor_telefono','nota_recordatorio'] as $f) {
                if (isset($obj->$f)) $obj->$f = (string)$obj->$f;
            }
            $rows[] = $obj;
        }
        return $rows;
    }

    /**
     * Busca actores (terceros RE_AOR) por nombre o teléfono para el autocomplete
     */
    public static function searchActores($db, $q, $limit = 10)
    {
        $q = $db->escape(trim($q));
        $sql = "SELECT s.rowid, s.nom, s.phone, s.email, s.fk_re_subtypent
                FROM " . MAIN_DB_PREFIX . "societe s
                INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
                WHERE t.code = 'RE_AOR'
                AND (s.nom LIKE '%$q%' OR s.phone LIKE '%$q%' OR s.email LIKE '%$q%')
                ORDER BY s.nom
                LIMIT $limit";
        $res = $db->query($sql);
        $rows = [];
        if ($res) while ($obj = $db->fetch_object($res)) $rows[] = $obj;
        return $rows;
    }
}
