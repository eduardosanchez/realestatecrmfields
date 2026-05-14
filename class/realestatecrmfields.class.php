<?php
/* ============================================================
 * Clase principal: RealEstateCrmFields
 * Lógica de negocio: tipos, subtipos, visibilidad de campos
 * Prefijo DB: zu4s_
 * ============================================================ */

class RealEstateCrmFields
{
    public $db;
    public $error  = '';
    public $errors = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    // =========================================================
    // TIPOS Y SUBTIPOS
    // =========================================================

    /**
     * Retorna todos los tipos principales activos
     * @return array [ ['id'=>1, 'code'=>'ACTIVO', 'libelle'=>'Activo Inmobiliario'], ... ]
     */
    public function getTypes(): array
    {
        $sql = "SELECT id, code, libelle
                FROM " . MAIN_DB_PREFIX . "c_typent
                WHERE active = 1
                AND code IN ('RE_ACT','RE_AOR','RE_SRV')
                ORDER BY position ASC";

        $res  = $this->db->query($sql);
        $list = [];
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $list[$obj->code] = ['id' => $obj->id, 'code' => $obj->code, 'libelle' => $obj->libelle];
            }
        }
        return $list;
    }

    /**
     * Retorna todos los subtipos activos, opcionalmente filtrados por tipo padre
     * @param  string|null $typentCode  Código del tipo padre (null = todos)
     * @return array  agrupado por fk_typent
     */
    public function getSubtypes(?string $typentCode = null): array
    {
        $sql = "SELECT rowid, code, libelle, fk_typent, position
                FROM " . MAIN_DB_PREFIX . "c_re_subtypent
                WHERE active = 1";

        if ($typentCode) {
            $sql .= " AND fk_typent = '" . $this->db->escape($typentCode) . "'";
        }
        $sql .= " ORDER BY fk_typent ASC, position ASC";

        $res    = $this->db->query($sql);
        $list   = [];
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $list[$obj->fk_typent][] = [
                    'rowid'   => $obj->rowid,
                    'code'    => $obj->code,
                    'libelle' => $obj->libelle,
                ];
            }
        }
        return $list;
    }

    /**
     * Retorna tipo y subtipo de un thirdparty
     * @param  int $socid
     * @return array ['typent_code'=>'ACTIVO', 'subtypent_code'=>'GARAJE'] o vacío
     */
    public function getThirdPartyClassification(int $socid): array
    {
        // JOIN directo — funciona incluso cuando fk_typent=0 (RE_ACT tiene id=0 en esta instalación)
        $sql = "SELECT t.code AS typent_code, s.fk_re_subtypent AS subtypent_code
                FROM " . MAIN_DB_PREFIX . "societe s
                JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
                WHERE s.rowid = " . (int)$socid;

        $res = $this->db->query($sql);
        if ($res && $this->db->num_rows($res) > 0) {
            $obj = $this->db->fetch_object($res);
            return [
                'typent_code'    => $obj->typent_code    ?? '',
                'subtypent_code' => $obj->subtypent_code ?? '',
            ];
        }
        return ['typent_code' => '', 'subtypent_code' => ''];
    }

    /**
     * Guarda el subtipo de un thirdparty
     */
    public function saveSubtype(int $socid, string $subtypeCode): bool
    {
        $sql = "UPDATE " . MAIN_DB_PREFIX . "societe
                SET fk_re_subtypent = '" . $this->db->escape($subtypeCode) . "'
                WHERE rowid = " . (int)$socid;

        return (bool)$this->db->query($sql);
    }

    // =========================================================
    // EXTRAFIELDS
    // =========================================================

    /**
     * Retorna todos los extrafields de un elementtype
     */
    public function getExtrafields(string $elementtype = 'societe'): array
    {
        $sql = "SELECT rowid, name, label, type, pos
                FROM " . MAIN_DB_PREFIX . "extrafields
                WHERE elementtype = '" . $this->db->escape($elementtype) . "'
                ORDER BY pos ASC";

        $res  = $this->db->query($sql);
        $list = [];
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $list[] = [
                    'rowid' => $obj->rowid,
                    'name'  => $obj->name,
                    'label' => $obj->label,
                    'type'  => $obj->type,
                    'pos'   => $obj->pos,
                ];
            }
        }
        return $list;
    }

    // =========================================================
    // VISIBILIDAD
    // =========================================================

    /**
     * Retorna la visibilidad completa: qué subtipos están activos para cada campo
     * @return array [ extrafield_id => [ 'GARAJE', 'GALPON', ... ] ]
     */
    public function getAllVisibility(): array
    {
        $sql = "SELECT extrafield_id, typent_code, subtypent_code
                FROM " . MAIN_DB_PREFIX . "re_extrafields_visibility";

        $res  = $this->db->query($sql);
        $map  = [];
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $key = $obj->subtypent_code ?: ('TYPE:' . $obj->typent_code);
                $map[$obj->extrafield_id][] = $key;
            }
        }
        return $map;
    }

    /**
     * Retorna los nombres de campos VISIBLES para una clasificación dada
     * Lógica: sin reglas = OCULTO por defecto.
     * Un campo es visible solo si tiene una regla explícita para:
     *   1. Este subtipo exacto
     *   2. Este tipo con subtipo NULL (aplica a todos los subtipos del tipo)
     *   3. Regla global (tipo NULL y subtipo NULL)
     */
    public function getVisibleFields(string $typentCode, string $subtypentCode, string $elementtype = 'societe'): array
    {
        $sql = "SELECT ef.name
                FROM " . MAIN_DB_PREFIX . "extrafields ef
                WHERE ef.elementtype = '" . $this->db->escape($elementtype) . "'
                AND ef.rowid IN (
                    SELECT extrafield_id
                    FROM " . MAIN_DB_PREFIX . "re_extrafields_visibility
                    WHERE (
                        typent_code    = '" . $this->db->escape($typentCode) . "'
                        AND subtypent_code = '" . $this->db->escape($subtypentCode) . "'
                    )
                    OR (
                        typent_code    = '" . $this->db->escape($typentCode) . "'
                        AND subtypent_code IS NULL
                    )
                    OR (
                        typent_code IS NULL
                        AND subtypent_code IS NULL
                    )
                )";

        $res    = $this->db->query($sql);
        $fields = [];
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                $fields[] = $obj->name;
            }
        }
        return $fields;
    }

    /**
     * Retorna los nombres de campos OCULTOS para una clasificación dada
     */
    public function getHiddenFields(string $typentCode, string $subtypentCode, string $elementtype = 'societe'): array
    {
        $visibleFields = $this->getVisibleFields($typentCode, $subtypentCode, $elementtype);

        $sql = "SELECT name FROM " . MAIN_DB_PREFIX . "extrafields
                WHERE elementtype = '" . $this->db->escape($elementtype) . "'";

        $res    = $this->db->query($sql);
        $hidden = [];
        if ($res) {
            while ($obj = $this->db->fetch_object($res)) {
                if (!in_array($obj->name, $visibleFields)) {
                    $hidden[] = $obj->name;
                }
            }
        }
        return $hidden;
    }

    /**
     * Guarda la visibilidad de un campo para un subtipo específico
     */
    public function setFieldVisibility(int $extrafieldId, string $typentCode, ?string $subtypentCode, bool $visible): bool
    {
        if ($visible) {
            $subtypeVal = $subtypentCode ? "'" . $this->db->escape($subtypentCode) . "'" : 'NULL';
            $typeVal    = $typentCode    ? "'" . $this->db->escape($typentCode) . "'"    : 'NULL';

            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "re_extrafields_visibility
                        (extrafield_id, elementtype, typent_code, subtypent_code)
                    VALUES
                        (" . (int)$extrafieldId . ", 'societe', {$typeVal}, {$subtypeVal})
                    ON DUPLICATE KEY UPDATE extrafield_id = extrafield_id";
        } else {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "re_extrafields_visibility
                    WHERE extrafield_id = " . (int)$extrafieldId;

            if ($typentCode) {
                $sql .= " AND typent_code = '" . $this->db->escape($typentCode) . "'";
            } else {
                $sql .= " AND typent_code IS NULL";
            }

            if ($subtypentCode) {
                $sql .= " AND subtypent_code = '" . $this->db->escape($subtypentCode) . "'";
            } else {
                $sql .= " AND subtypent_code IS NULL";
            }
        }

        return (bool)$this->db->query($sql);
    }

    /**
     * Genera el bloque JS que oculta columnas/filas de extrafields
     */
    public function buildHiderJS(array $hiddenFields, string $context = 'form'): string
    {
        if (empty($hiddenFields)) return '';

        $js = '<script>jQuery(document).ready(function($) {' . "\n";

        if ($context === 'form') {
            // En formulario de ficha: ocultar filas <tr>
            foreach ($hiddenFields as $fieldName) {
                $f = preg_replace('/[^a-zA-Z0-9_]/', '', $fieldName); // seguro para JS
                $js .= "  $('tr[id*=\"options_{$f}\"], tr.realestate_ef_{$f}').closest('tr').hide();\n";
                // Dolibarr a veces usa id="trextrafieldsrow_options_X"
                $js .= "  $('[id$=\"_options_{$f}\"]').closest('tr').hide();\n";
            }
        } elseif ($context === 'list') {
            // En lista: ocultar columnas por índice
            foreach ($hiddenFields as $fieldName) {
                $f = preg_replace('/[^a-zA-Z0-9_]/', '', $fieldName); // seguro para JS
                $js .= "
  (function() {
    var colIdx = -1;
    $('table.tagtable thead th').each(function(i) {
      var key = $(this).data('key') || $(this).attr('class') || '';
      if (key.indexOf('options_{$f}') !== -1) { colIdx = i + 1; }
    });
    if (colIdx > 0) {
      $('table.tagtable thead th:nth-child(' + colIdx + ')').hide();
      $('table.tagtable tbody td:nth-child(' + colIdx + ')').hide();
    }
  })();\n";
            }
        }

        $js .= '});</script>' . "\n";
        return $js;
    }
}
