<?php
/* ============================================================
 * Hooks del módulo RealEstateCrmFields - Dolibarr 23
 * ============================================================ */

require_once dol_buildpath('/custom/realestatecrmfields/class/realestatecrmfields.class.php', 0);
require_once dol_buildpath('/custom/realestatecrmfields/helpers.php', 0);

class Actions_realestatecrmfields
{
    public $results   = [];
    public $errors    = [];
    public $resprints = '';  // Dolibarr < 23
    public $resPrint  = '';  // Dolibarr 23+

    public $db;
    private $service;

    public function __construct($db)
    {
        $this->db      = $db;
        $this->service = new RealEstateCrmFields($db);
    }

    private function _setOutput(string $html): void
    {
        $this->resprints = $html;
        $this->resPrint  = $html;
    }

    // =========================================================
    // HOOK: addMoreActionsButtons
    // =========================================================
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        $phpSelf = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($phpSelf, '/societe/card.php') === false) {
            return 0;
        }

        global $langs;
        $langs->load('realestatecrmfields');

        $subtypesByType = $this->service->getSubtypes();
        $socid          = (int)($object->id ?? GETPOST('socid', 'int'));
        $classification = $socid ? $this->service->getThirdPartyClassification($socid) : [];
        $currentSubtype = $classification['subtypent_code'] ?? '';
        $currentType    = $classification['typent_code']    ?? '';

        $optionsHtml = '<option value="">-- Seleccionar --</option>';
        foreach ($subtypesByType as $typeCode => $subs) {
            $optionsHtml .= '<optgroup label="' . htmlspecialchars($typeCode) . '" data-type="' . htmlspecialchars($typeCode) . '">';
            foreach ($subs as $sub) {
                $sel = ($currentSubtype === $sub['code']) ? ' selected' : '';
                $optionsHtml .= '<option value="' . htmlspecialchars($sub['code']) . '" data-type="' . htmlspecialchars($typeCode) . '"' . $sel . '>'
                    . htmlspecialchars($sub['libelle']) . '</option>';
            }
            $optionsHtml .= '</optgroup>';
        }

        $hiddenFieldsJson = '[]';
        if ($currentType) {
            $hidden = $this->service->getHiddenFields($currentType, $currentSubtype ?: '');
            $hiddenFieldsJson = json_encode($hidden);
        }

        ob_start();
        ?>
        <script>
        jQuery(document).ready(function($) {
            var selectHtml = '<select id="re_subtypent" name="re_subtypent" class="flat minwidth200" onchange="realEstateOnSubtypeChange(this)">'
                + <?= json_encode($optionsHtml) ?>
                + '</select>';
            var newRow = '<tr class="realestate-subtype-row">'
                + '<td class="titlefieldcreate"><?= $langs->trans('SubtipoTercero') ?></td>'
                + '<td colspan="3">' + selectHtml + '</td>'
                + '</tr>';
            var $tipoRow = $('select#typent_id, select[name="typent_id"]').closest('tr');
            if ($tipoRow.length) {
                $tipoRow.after(newRow);
            } else {
                $('form[name="societe"] table.border tr:first, form table.border tr:first').first().after(newRow);
            }
            var $tipo    = $('select#typent_id, select[name="typent_id"]').first();
            var $subtipo = $('#re_subtypent');
            function filterOptgroups(tipoCode) {
                $subtipo.find('optgroup').each(function() {
                    var match = !tipoCode || $(this).data('type') === tipoCode;
                    $(this).toggle(match);
                    $(this).find('option').prop('disabled', !match);
                });
            }
            $tipo.on('change', function() {
                filterOptgroups($(this).val());
                $subtipo.val('');
                realEstateApplyVisibility($(this).val(), '');
            });
            filterOptgroups($tipo.val());
            <?php if ($currentType): ?>
            realEstateApplyVisibility('<?= dol_escape_js($currentType) ?>', '<?= dol_escape_js($currentSubtype) ?>');
            <?php endif; ?>
            var hiddenFields = <?= $hiddenFieldsJson ?>;
            hiddenFields.forEach(function(fieldName) {
                $('[id$="_options_' + fieldName + '"]').closest('tr').hide();
                $('#trextrafieldsrow_options_' + fieldName).hide();
            });
        });
        function realEstateOnSubtypeChange(sel) {
            var opt  = sel.options[sel.selectedIndex];
            var type = opt ? opt.dataset.type : '';
            realEstateApplyVisibility(type, sel.value);
        }
        function realEstateApplyVisibility(typeCode, subtypeCode) {
            $('[id^="trextrafieldsrow_options_"], [id$="_options_"]').closest('tr').show();
            if (!typeCode) return;
            jQuery.getJSON(
                '<?= DOL_URL_ROOT ?>/custom/realestatecrmfields/ajax/get_hidden_fields.php',
                { typent: typeCode, subtypent: subtypeCode || '', token: '<?= dol_escape_js(generateToken()) ?>' },
                function(data) {
                    if (!data.hidden || !data.hidden.length) return;
                    jQuery.each(data.hidden, function(i, fieldName) {
                        jQuery('[id$="_options_' + fieldName + '"]').closest('tr').hide();
                        jQuery('#trextrafieldsrow_options_' + fieldName).hide();
                    });
                }
            );
        }
        </script>
        <?php
        $html_out = ob_get_clean();
        $this->_setOutput($html_out);
        return 1;
    }

    // =========================================================
    // HOOK: doActions
    // =========================================================
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        if (strpos($_SERVER['PHP_SELF'] ?? '', '/societe/card.php') === false) return 0;
        $subtypeCode = GETPOST('re_subtypent', 'alpha');
        if (empty($subtypeCode)) return 0;
        $socid = !empty($object->id) ? (int)$object->id : (int)GETPOST('socid', 'int');
        if (!$socid) return 0;
        $this->service->saveSubtype($socid, $subtypeCode);
        return 0;
    }

    // =========================================================
    // HOOK: printFieldListTitle
    // =========================================================
    public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
    {
        if (!in_array('thirdpartylist', explode(':', $parameters['currentcontext']))) return 0;
        $this->_setOutput('<th class="wrapcolumntitle center liste_titre re-subtipo-th" title="Subtipo">Subtipo</th>');
        return 1;
    }

    // =========================================================
    // HOOK: printFieldListValue
    // =========================================================
    public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        if (!in_array('thirdpartylist', explode(':', $parameters['currentcontext']))) return 0;
        $socid = (int)($object->id ?? 0);
        $label = '—';
        if ($socid > 0) {
            $sql = "SELECT s.fk_re_subtypent AS code, st.libelle AS libelle
                    FROM " . MAIN_DB_PREFIX . "societe s
                    LEFT JOIN " . MAIN_DB_PREFIX . "c_re_subtypent st ON st.code = s.fk_re_subtypent
                    WHERE s.rowid = " . $socid;
            $res = $this->db->query($sql);
            if ($res && $this->db->num_rows($res)) {
                $obj   = $this->db->fetch_object($res);
                $label = $obj->libelle ?: ($obj->code ?: '—');
            }
        }
        $this->_setOutput('<td class="center tdoverflowmax125 re-subtipo-td">' . htmlspecialchars($label) . '</td>');
        return 1;
    }

    // =========================================================
    // HOOK: printFieldListWhere
    // =========================================================
    public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
    {
        $selectedSubtype = trim((string)GETPOST('search_re_subtypent', 'alphanohtml'));
        if ($selectedSubtype === '') return 0;
        $sql = "SELECT code FROM " . MAIN_DB_PREFIX . "c_re_subtypent WHERE code = '" . $this->db->escape($selectedSubtype) . "' LIMIT 1";
        $res = $this->db->query($sql);
        if (!$res || !$this->db->num_rows($res)) return 0;
        $this->_setOutput(" AND s.fk_re_subtypent = '" . $this->db->escape($selectedSubtype) . "'");
        return 1;
    }

    // =========================================================
    // HOOK: printTopRightMenu
    // =========================================================
    public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
    {
        if (!in_array('thirdpartylist', explode(':', $parameters['currentcontext']))) return 0;
        global $langs;
        $langs->load('realestatecrmfields');
        $selectedType    = GETPOST('search_type_thirdparty', 'int') ? GETPOST('search_type_thirdparty', 'int') : GETPOST('re_type', 'alpha');
        $selectedSubtype = GETPOST('search_re_subtypent', 'alpha');
        $subtypesByType  = $this->service->getSubtypes();
        $types           = ['RE_ACT' => 'Activos', 'RE_AOR' => 'Actores', 'RE_SRV' => 'Servicios'];
        ob_start();
        ?>
        <div class="realestate-filter-bar" style="margin-bottom:12px;">
            <div class="realestate-tabs" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <span style="font-weight:bold;margin-right:4px;">Filtrar:</span>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="butAction <?= (!$selectedType) ? 'butActionDelete' : '' ?>">Todos</a>
                <?php foreach ($types as $typeCode => $typeLabel): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?re_type=<?= urlencode($typeCode) ?>"
                   class="butAction <?= ($selectedType === $typeCode) ? 'butActionDelete' : '' ?>">
                   <?= htmlspecialchars($typeLabel) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ($selectedType && isset($subtypesByType[$selectedType])): ?>
            <div style="margin-top:6px;padding-left:20px;display:flex;gap:4px;flex-wrap:wrap;">
                <?php foreach ($subtypesByType[$selectedType] as $sub): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?re_type=<?= urlencode($selectedType) ?>&re_subtype=<?= urlencode($sub['code']) ?>"
                   class="butAction <?= ($selectedSubtype === $sub['code']) ? 'butActionDelete' : '' ?>">
                   <?= htmlspecialchars($sub['libelle']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $html_out = ob_get_clean();
        $this->_setOutput($html_out);
        return 1;
    }

    // =========================================================
    // HOOK: printCommonFooter
    // Badge de pendientes + bloque de activos vinculados al propietario
    // =========================================================
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        $phpSelf = $_SERVER['PHP_SELF'] ?? '';
        $socid   = (int)GETPOST('socid', 'int');
        $html    = '';

        // ── Bloque activos vinculados (solo en ficha de Actor) ─
        if ($socid && strpos($phpSelf, '/societe/card.php') !== false) {
            $p = MAIN_DB_PREFIX;

            $tipoCode = '';
            $resT = $this->db->query("SELECT t.code FROM {$p}societe s
                INNER JOIN {$p}c_typent t ON t.id = s.fk_typent
                WHERE s.rowid = $socid LIMIT 1");
            if ($resT && $this->db->num_rows($resT)) {
                $tipoCode = $this->db->fetch_object($resT)->code ?? '';
            }

            if ($tipoCode === 'RE_AOR') {
                $cnt = 0;
                $resC = $this->db->query("SELECT COUNT(*) AS cnt FROM {$p}re_propietario_activo
                    WHERE fk_societe_propietario = $socid");
                if ($resC) $cnt = (int)$this->db->fetch_object($resC)->cnt;

                if ($cnt > 0) {
                    ob_start();
                    include dol_buildpath('/custom/realestatecrmfields/tpl/activos_section.tpl.php', 0);
                    $bloqueHtml = ob_get_clean();

                    $html .= '<script>jQuery(document).ready(function($){';
                    $html .= 'var bloque=$(' . json_encode($bloqueHtml) . ');';
                    $html .= '$(".tabsAction").after(bloque);';
                    $html .= '});</script>';
                }
            }
        }

        // ── Badge de pendientes ───────────────────────────────
        $hoy = date('Y-m-d');
        $sql = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "re_consulta
                WHERE recordatorio_done = 0
                  AND fecha_recordatorio IS NOT NULL
                  AND fecha_recordatorio <= '$hoy'";
        $res = $this->db->query($sql);
        $cnt = ($res) ? (int)$this->db->fetch_object($res)->cnt : 0;

        if ($cnt > 0) {
            $url  = dol_buildpath('/custom/realestatecrmfields/pendientes.php', 1);
            $html .= '<div style="position:fixed;bottom:20px;right:20px;z-index:8000">'
                  . '<a href="' . $url . '" title="' . $cnt . ' recordatorio(s) pendiente(s)" '
                  . 'style="background:#c0392b;color:#fff;border-radius:50px;padding:10px 18px;'
                  . 'font-weight:bold;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,.3);'
                  . 'display:flex;align-items:center;gap:8px">'
                  . '<span class="fas fa-bell"></span>'
                  . '<span>' . $cnt . ' pendiente' . ($cnt > 1 ? 's' : '') . '</span>'
                  . '</a></div>';
        }

        if (empty($html)) return 0;
        $this->_setOutput($html);
        return 0;
    }

    // =========================================================
    // HOOK: createActionComm
    // =========================================================
    public function createActionComm($parameters, &$object, &$action, $hookmanager)
    {
        $codeAction = $object->code_action ?? $object->actioncode ?? '';
        if (!in_array($codeAction, ['AC_EMAIL', 'EMAIL'])) return 0;
        $socid = (int)($object->fk_soc ?? 0);
        if (!$socid) return 0;

        global $user;
        $p = MAIN_DB_PREFIX;

        $sqlTipo = "SELECT s.rowid, t.code AS typent_code
                    FROM {$p}societe s
                    INNER JOIN {$p}c_typent t ON t.id = s.fk_typent
                    WHERE s.rowid = $socid AND t.code IN ('RE_ACT','RE_AOR')";
        $resTipo = $this->db->query($sqlTipo);
        if (!$resTipo || !$this->db->num_rows($resTipo)) return 0;

        $oTipo = $this->db->fetch_object($resTipo);
        $now   = date('Y-m-d H:i:s');
        $nota  = '[Email enviado] ' . ($object->label ?? $object->note ?? '');
        $uid   = (int)($user->id ?? 0);

        if ($oTipo->typent_code === 'RE_AOR') {
            $this->db->query("INSERT INTO {$p}re_consulta
                (fk_societe_actor, canal, estado, nota, date_consulta,
                 recordatorio_done, fk_user_vendedor, date_creation)
                VALUES ($socid, 'EMAIL', 'CONSULTO',
                '" . $this->db->escape($nota) . "',
                '$now', 1, " . ($uid ?: 'NULL') . ", '$now')");
        } elseif ($oTipo->typent_code === 'RE_ACT') {
            $this->db->query("INSERT IGNORE INTO {$p}societe_extrafields (fk_object) VALUES ($socid)");
            $this->db->query("INSERT INTO {$p}re_gestion_propietario
                (fk_societe_activo, canal, resultado, nota, fecha,
                 recordatorio_done, fk_user_vendedor, date_creation)
                VALUES ($socid, 'EMAIL', 'ATENDIO',
                '" . $this->db->escape($nota) . "',
                '$now', 1, " . ($uid ?: 'NULL') . ", '$now')");
        }

        return 0;
    }
}
