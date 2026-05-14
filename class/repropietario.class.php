<?php
/**
 * Clase para gestionar la relación propietario <-> activo inmobiliario
 * Soporta historial, roles y desvincular sin perder datos
 */
class RePropietario
{
    public $db;
    public $error = '';

    public $rowid;
    public $fk_societe_activo;
    public $fk_societe_propietario;
    public $propietario_nombre;
    public $propietario_telefono;
    public $rol;
    public $fecha_desde;
    public $fecha_hasta;
    public $activo;
    public $nota;
    public $date_creation;
    public $fk_user_creat;

    const ROLES = [
        'PROPIETARIO'    => 'Propietario',
        'SOCIO_PROP'     => 'Socio propietario',
        'ADMINISTRADOR'  => 'Administrador',
        'INQUILINO'      => 'Inquilino',
    ];

    const ROL_COLORS = [
        'PROPIETARIO'    => '#0d6efd',
        'SOCIO_PROP'     => '#6f42c1',
        'ADMINISTRADOR'  => '#198754',
        'INQUILINO'      => '#fd7e14',
    ];

    public function __construct($db) { $this->db = $db; }

    public function create($user)
    {
        $now = dol_now();
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "re_propietario_activo
                (fk_societe_activo, fk_societe_propietario, propietario_nombre, propietario_telefono,
                 rol, fecha_desde, fecha_hasta, activo, nota, date_creation, fk_user_creat)
                VALUES (
                    " . (int)$this->fk_societe_activo . ",
                    " . ($this->fk_societe_propietario ? (int)$this->fk_societe_propietario : 'NULL') . ",
                    " . ($this->propietario_nombre   ? "'" . $this->db->escape($this->propietario_nombre)   . "'" : 'NULL') . ",
                    " . ($this->propietario_telefono ? "'" . $this->db->escape($this->propietario_telefono) . "'" : 'NULL') . ",
                    '" . $this->db->escape($this->rol ?: 'PROPIETARIO') . "',
                    '" . $this->db->escape($this->fecha_desde ?: date('Y-m-d')) . "',
                    " . ($this->fecha_hasta ? "'" . $this->db->escape($this->fecha_hasta) . "'" : 'NULL') . ",
                    " . (isset($this->activo) ? (int)$this->activo : 1) . ",
                    " . ($this->nota ? "'" . $this->db->escape($this->nota) . "'" : 'NULL') . ",
                    '" . $this->db->idate($now) . "',
                    " . (int)$user->id . "
                )";
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . 're_propietario_activo');
        return $this->rowid;
    }

    public function update($user)
    {
        $now = dol_now();
        $sql = "UPDATE " . MAIN_DB_PREFIX . "re_propietario_activo SET
                    fk_societe_propietario = " . ($this->fk_societe_propietario ? (int)$this->fk_societe_propietario : 'NULL') . ",
                    propietario_nombre     = " . ($this->propietario_nombre   ? "'" . $this->db->escape($this->propietario_nombre)   . "'" : 'NULL') . ",
                    propietario_telefono   = " . ($this->propietario_telefono ? "'" . $this->db->escape($this->propietario_telefono) . "'" : 'NULL') . ",
                    rol                    = '" . $this->db->escape($this->rol) . "',
                    fecha_desde            = '" . $this->db->escape($this->fecha_desde) . "',
                    fecha_hasta            = " . ($this->fecha_hasta ? "'" . $this->db->escape($this->fecha_hasta) . "'" : 'NULL') . ",
                    activo                 = " . (int)$this->activo . ",
                    nota                   = " . ($this->nota ? "'" . $this->db->escape($this->nota) . "'" : 'NULL') . ",
                    date_modification      = '" . $this->db->idate($now) . "',
                    fk_user_modif          = " . (int)$user->id . "
                WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function desvincular($user)
    {
        // No elimina — pone fecha_hasta = hoy y activo = 0
        $now = dol_now();
        $sql = "UPDATE " . MAIN_DB_PREFIX . "re_propietario_activo SET
                    fecha_hasta       = '" . date('Y-m-d') . "',
                    activo            = 0,
                    date_modification = '" . $this->db->idate($now) . "',
                    fk_user_modif     = " . (int)$user->id . "
                WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function delete($user)
    {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "re_propietario_activo WHERE rowid = " . (int)$this->rowid;
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function fetch($rowid)
    {
        $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "re_propietario_activo WHERE rowid = " . (int)$rowid;
        $res = $this->db->query($sql);
        if (!$res || !$this->db->num_rows($res)) return -1;
        $obj = $this->db->fetch_object($res);
        foreach ((array)$obj as $k => $v) $this->$k = $v;
        return 1;
    }

    public function fetchByActivo($socidActivo, $soloActivos = false)
    {
        $sql = "SELECT p.*,
                    s.nom AS propietario_nom,
                    s.phone AS propietario_phone
                FROM " . MAIN_DB_PREFIX . "re_propietario_activo p
                LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = p.fk_societe_propietario
                WHERE p.fk_societe_activo = " . (int)$socidActivo;
        if ($soloActivos) $sql .= " AND p.activo = 1";
        $sql .= " ORDER BY p.activo DESC, p.fecha_desde DESC";
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return []; }
        $rows = [];
        while ($obj = $this->db->fetch_object($res)) {
            foreach (['nota','propietario_nom','propietario_nombre','propietario_telefono','propietario_phone'] as $f) {
                if (isset($obj->$f)) $obj->$f = (string)$obj->$f;
            }
            $rows[] = $obj;
        }
        return $rows;
    }

    /**
     * Devuelve los activos (garajes) vinculados a un propietario/actor.
     * Relación inversa de fetchByActivo — se usa en la ficha del propietario.
     *
     * @param int  $socidPropietario  rowid del ThirdParty propietario
     * @param bool $soloActivos       si true, solo relaciones vigentes (activo=1)
     * @return array
     */
    public function fetchByPropietario($socidPropietario, $soloActivos = false)
    {
        $sql = "SELECT p.*,
                    s.nom        AS activo_nom,
                    s.phone      AS activo_phone,
                    ef.calle     AS activo_calle,
                    ef.numero    AS activo_numero,
                    ef.barrio    AS activo_barrio,
                    COALESCE(NULLIF(ef.cocheras_fijas, 0), ef.coch_fijas_estimadas, 0) AS activo_cocheras
                FROM " . MAIN_DB_PREFIX . "re_propietario_activo p
                INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = p.fk_societe_activo
                LEFT JOIN  " . MAIN_DB_PREFIX . "societe_extrafields ef ON ef.fk_object = p.fk_societe_activo
                WHERE p.fk_societe_propietario = " . (int)$socidPropietario;
        if ($soloActivos) $sql .= " AND p.activo = 1";
        $sql .= " ORDER BY p.activo DESC, p.fecha_desde DESC";
        $res = $this->db->query($sql);
        if (!$res) { $this->error = $this->db->lasterror(); return []; }
        $rows = [];
        while ($obj = $this->db->fetch_object($res)) {
            foreach (['nota','activo_nom','activo_calle','activo_numero','activo_barrio'] as $f) {
                if (isset($obj->$f)) $obj->$f = (string)$obj->$f;
            }
            $rows[] = $obj;
        }
        return $rows;
    }
}
