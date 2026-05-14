<?php
/**
 * Clase para gestión/contacto con propietarios de activos inmobiliarios
 */
class ReGestion
{
    public $db;
    public $error = '';

    public $rowid;
    public $fk_societe_activo;
    public $fk_societe_propietario;
    public $propietario_nombre;
    public $propietario_telefono;
    public $fecha;
    public $canal;
    public $resultado;
    public $nota;
    public $fecha_recordatorio;
    public $nota_recordatorio;
    public $fk_user_vendedor;
    public $fk_user_recordatorio;
    public $recordatorio_done;
    public $date_creation;
    public $fk_user_creat;

    const CANALES = [
        ''          => '— Canal —',
        'TELEFONO'  => 'Teléfono',
        'WHATSAPP'  => 'WhatsApp',
        'MAIL'      => 'Mail',
    ];

    const RESULTADOS = [
        ''                => '— Resultado —',
        'ATENDIO'         => 'Atendió',
        'NO_ATENDIO'      => 'No atendió',
        'QUIERE_VENDER'   => 'Quiere vender',
        'NO_QUIERE'       => 'No quiere vender',
        'QUIERE_TASAR'    => 'Quiere tasar',
        'TASADO'          => 'Tasado',
        'MAIL_ENVIADO'    => 'Se envió mail',
    ];

    const RESULTADO_COLORS = [
        ''                => '#dee2e6',
        'ATENDIO'         => '#6c757d',
        'NO_ATENDIO'      => '#adb5bd',
        'QUIERE_VENDER'   => '#198754',
        'NO_QUIERE'       => '#c0392b',
        'QUIERE_TASAR'    => '#0d6efd',
        'TASADO'          => '#6f42c1',
        'MAIL_ENVIADO'    => '#0dcaf0',
    ];

    public function __construct($db) { $this->db = $db; }

    public function create($user)
    {
        $now = dol_now();
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "re_gestion_propietario
                (fk_societe_activo, fk_societe_propietario, propietario_nombre, propietario_telefono,
                 fecha, canal, resultado, nota, fecha_recordatorio, nota_recordatorio,
                 fk_user_vendedor, fk_user_recordatorio, date_creation, fk_user_creat)
                VALUES (
                    " . (int)$this->fk_societe_activo . ",
                    " . ($this->fk_societe_propietario ? (int)$this->fk_societe_propietario : 'NULL') . ",
                    " . ($this->propietario_nombre    ? "'" . $this->db->escape($this->propietario_nombre)    . "'" : 'NULL') . ",
                    " . ($this->propietario_telefono  ? "'" . $this->db->escape($this->propietario_telefono)  . "'" : 'NULL') . ",
                    '" . $this->db->idate($this->fecha ?: $now) . "',
                    '" . $this->db->escape($this->canal     ?: '') . "',
                    '" . $this->db->escape($this->resultado ?: '') . "',
                    " . ($this->nota               ? "'" . $this->db->escape($this->nota)               . "'" : 'NULL') . ",
                    " . ($this->fecha_recordatorio  ? "'" . $this->db->escape($this->fecha_recordatorio)  . "'" : 'NULL') . ",
                    " . ($this->nota_recordatorio   ? "'" . $this->db->escape($this->nota_recordatorio)   . "'" : 'NULL') . ",
                    " . ($this->fk_user_vendedor    ? (int)$this->fk_user_vendedor    : 'NULL') . ",
                    " . ($this->fk_user_recordatorio ? (int)$this->fk_user_recordatorio : 'NULL') . ",
                    '" . $this->db->idate($now) . "',
                    " . (int)$user->id . "
                )";
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . 're_gestion_propietario');
        return $this->rowid;
    }

    public function update($user)
    {
        $now = dol_now();
        $sql = "UPDATE " . MAIN_DB_PREFIX . "re_gestion_propietario SET
                    fk_societe_propietario = " . ($this->fk_societe_propietario ? (int)$this->fk_societe_propietario : 'NULL') . ",
                    propietario_nombre     = " . ($this->propietario_nombre     ? "'" . $this->db->escape($this->propietario_nombre)    . "'" : 'NULL') . ",
                    propietario_telefono   = " . ($this->propietario_telefono   ? "'" . $this->db->escape($this->propietario_telefono)  . "'" : 'NULL') . ",
                    fecha                  = '" . $this->db->idate($this->fecha) . "',
                    canal                  = '" . $this->db->escape($this->canal)     . "',
                    resultado              = '" . $this->db->escape($this->resultado)  . "',
                    nota                   = " . ($this->nota              ? "'" . $this->db->escape($this->nota)              . "'" : 'NULL') . ",
                    fecha_recordatorio     = " . ($this->fecha_recordatorio ? "'" . $this->db->escape($this->fecha_recordatorio) . "'" : 'NULL') . ",
                    nota_recordatorio      = " . ($this->nota_recordatorio  ? "'" . $this->db->escape($this->nota_recordatorio)  . "'" : 'NULL') . ",
                    fk_user_vendedor       = " . ($this->fk_user_vendedor    ? (int)$this->fk_user_vendedor    : 'NULL') . ",
                    fk_user_recordatorio   = " . ($this->fk_user_recordatorio ? (int)$this->fk_user_recordatorio : 'NULL') . ",
                    date_modification      = '" . $this->db->idate($now) . "',
                    fk_user_modif          = " . (int)$user->id . "
                WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function delete($user)
    {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "re_gestion_propietario WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function fetch($rowid)
    {
        $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "re_gestion_propietario WHERE rowid = " . (int)$rowid;
        $res = $this->db->query($sql);
        if (!$res || !$this->db->num_rows($res)) return -1;
        $obj = $this->db->fetch_object($res);
        foreach ((array)$obj as $k => $v) $this->$k = $v;
        $this->fecha = $this->db->jdate($this->fecha) ?: strtotime($this->fecha ?? '');
        return 1;
    }

    public function fetchByActivo($socidActivo, $limit = 50)
    {
        $sql = "SELECT g.*,
                    sp.nom AS propietario_nom,
                    sp.phone AS propietario_phone,
                    CONCAT(u.firstname, ' ', u.lastname) AS vendedor_nom,
                    CONCAT(ur.firstname, ' ', ur.lastname) AS recordatorio_user_nom
                FROM " . MAIN_DB_PREFIX . "re_gestion_propietario g
                LEFT JOIN " . MAIN_DB_PREFIX . "societe sp ON sp.rowid = g.fk_societe_propietario
                LEFT JOIN " . MAIN_DB_PREFIX . "user u     ON u.rowid  = g.fk_user_vendedor
                LEFT JOIN " . MAIN_DB_PREFIX . "user ur    ON ur.rowid = g.fk_user_recordatorio
                WHERE g.fk_societe_activo = " . (int)$socidActivo . "
                ORDER BY g.fecha DESC
                LIMIT " . (int)$limit;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return []; }
        $rows = [];
        while ($obj = $this->db->fetch_object($res)) {
            $obj->fecha = $this->db->jdate($obj->fecha) ?: strtotime($obj->fecha ?? '');
            foreach (['nota','propietario_nom','propietario_nombre','propietario_telefono','vendedor_nom','nota_recordatorio'] as $f) {
                if (isset($obj->$f)) $obj->$f = (string)$obj->$f;
            }
            $rows[] = $obj;
        }
        return $rows;
    }

    public static function searchPropietarios($db, $q, $limit = 10)
    {
        $q = $db->escape(trim($q));
        $sql = "SELECT s.rowid, s.nom, s.phone, s.fk_re_subtypent
                FROM " . MAIN_DB_PREFIX . "societe s
                INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
                WHERE t.code = 'RE_AOR'
                AND s.fk_re_subtypent = 'PROPIETARIO'
                AND (s.nom LIKE '%$q%' OR s.phone LIKE '%$q%')
                ORDER BY s.nom
                LIMIT $limit";
        $res = $db->query($sql);
        $rows = [];
        if ($res) while ($obj = $db->fetch_object($res)) $rows[] = $obj;
        return $rows;
    }
}
