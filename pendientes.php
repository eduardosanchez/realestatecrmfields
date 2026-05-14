<?php
/**
 * Vista de recordatorios pendientes — CRM Inmobiliario
 * URL: /custom/realestatecrmfields/pendientes.php
 */
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('main.inc.php not found');
require_once dol_buildpath('/custom/realestatecrmfields/helpers.php', 0);

require_once dol_buildpath('/custom/realestatecrmfields/class/reconsulta.class.php', 0);
require_once dol_buildpath('/custom/realestatecrmfields/class/regestion.class.php', 0);

$action = GETPOST('action', 'alpha');
$token  = GETPOST('token',  'alphanohtml');

// ── Acción: marcar contactado gestión propietario ────────────
if ($action === 'done_gest' && $token) {
    $tokenOk = false;
    if (!empty($_SESSION['newtoken']) && hash_equals($_SESSION['newtoken'], $token)) $tokenOk = true;
    if (!$tokenOk && function_exists('checkToken')) $tokenOk = checkToken(0);
    if ($tokenOk) {
        $rowid       = (int)GETPOST('rowid', 'int');
        $nota_done   = GETPOST('nota_done', 'restricthtml');
        $resultado_seg = GETPOST('resultado_seguimiento', 'alpha');
        $res_labels_g = ['ATENDIO'=>'✅ Llamó/habló','NO_ATENDIO'=>'📵 No atendió','WHATSAPP'=>'💬 WA enviado'];
        if ($resultado_seg && isset($res_labels_g[$resultado_seg])) {
            $nota_done = '[' . $res_labels_g[$resultado_seg] . '] ' . $nota_done;
        }
        $nueva_fecha = GETPOST('nueva_fecha_recordatorio', 'alpha');
        $nueva_nota  = GETPOST('nueva_nota_recordatorio', 'alphanohtml');
        if ($nueva_fecha) {
            $sql = "UPDATE " . MAIN_DB_PREFIX . "re_gestion_propietario SET
                        fecha_recordatorio = '" . $db->escape($nueva_fecha) . "',
                        nota_recordatorio  = " . ($nueva_nota ? "'" . $db->escape($nueva_nota) . "'" : "nota_recordatorio") .
                   ($nota_done ? ", nota = CONCAT(COALESCE(nota,''), '\n[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_done) . "')" : "") .
                   " WHERE rowid = " . $rowid;
        } else {
            $sql = "UPDATE " . MAIN_DB_PREFIX . "re_gestion_propietario SET
                        recordatorio_done = 1,
                        fecha_recordatorio = NULL" .
                   ($nota_done ? ", nota = CONCAT(COALESCE(nota,''), '\n[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_done) . "')" : "") .
                   " WHERE rowid = " . $rowid;
        }
        $db->query($sql);

        // Crear nueva entrada en re_gestion_propietario para que aparezca en captacion
        if ($nota_done || $resultado_seg) {
            // Leer datos de la gestión original (activo, propietario, vendedor)
            $rOrig = $db->query("SELECT fk_societe_activo, fk_societe_propietario,
                                        propietario_nombre, propietario_telefono, fk_user_vendedor
                                 FROM " . MAIN_DB_PREFIX . "re_gestion_propietario
                                 WHERE rowid = " . $rowid);
            if ($rOrig && ($oOrig = $db->fetch_object($rOrig))) {
                $canal_map = ['ATENDIO'=>'TELEFONO','NO_ATENDIO'=>'TELEFONO','WHATSAPP'=>'WHATSAPP'];
                $canal_seg = $canal_map[$resultado_seg] ?? 'TELEFONO';
                $res_map   = ['ATENDIO'=>'ATENDIO','NO_ATENDIO'=>'NO_ATENDIO','WHATSAPP'=>'ATENDIO'];
                $res_seg   = $res_map[$resultado_seg] ?? 'ATENDIO';
                $now = date('Y-m-d H:i:s');
                $db->query("INSERT INTO " . MAIN_DB_PREFIX . "re_gestion_propietario
                    (fk_societe_activo, fk_societe_propietario, propietario_nombre, propietario_telefono,
                     canal, resultado, nota, fecha, fk_user_vendedor,
                     recordatorio_done, date_creation)
                    VALUES (
                        " . (int)$oOrig->fk_societe_activo . ",
                        " . ($oOrig->fk_societe_propietario ? (int)$oOrig->fk_societe_propietario : 'NULL') . ",
                        " . ($oOrig->propietario_nombre ? "'" . $db->escape($oOrig->propietario_nombre) . "'" : 'NULL') . ",
                        " . ($oOrig->propietario_telefono ? "'" . $db->escape($oOrig->propietario_telefono) . "'" : 'NULL') . ",
                        '" . $db->escape($canal_seg) . "',
                        '" . $db->escape($res_seg) . "',
                        '" . $db->escape($nota_done) . "',
                        '" . $now . "',
                        " . ($oOrig->fk_user_vendedor ? (int)$oOrig->fk_user_vendedor : 'NULL') . ",
                        1,
                        '" . $now . "'
                    )");
            }
        }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Acción: marcar contactado ────────────────────────────────
if ($action === 'done' && $token) {
    // Validar token CSRF
    $tokenOk = false;
    if (!empty($_SESSION['newtoken']) && hash_equals($_SESSION['newtoken'], $token)) $tokenOk = true;
    if (!$tokenOk && function_exists('checkToken')) $tokenOk = checkToken(0);

    if ($tokenOk) {
        $rowid          = (int)GETPOST('rowid', 'int');
        $nota_done      = GETPOST('nota_done', 'restricthtml');
        $resultado_seg  = GETPOST('resultado_seguimiento', 'alpha');
        $nueva_fecha    = GETPOST('nueva_fecha_recordatorio', 'alpha');
        $nueva_nota_rec = GETPOST('nueva_nota_recordatorio', 'alphanohtml');
        // Prefijar la nota con el resultado si se seleccionó uno
        $res_labels = ['ATENDIO'=>'✅ Atendió','NO_ATENDIO'=>'📵 No contestó','WHATSAPP'=>'💬 WA enviado','OFRECIO'=>'💰 Hizo oferta','SIN_INTERES'=>'❌ Sin interés'];
        if ($resultado_seg && isset($res_labels[$resultado_seg])) {
            $nota_done = '[' . $res_labels[$resultado_seg] . '] ' . $nota_done;
        }

        if ($nueva_fecha) {
            // Reprogramar recordatorio — no cerrar, solo mover la fecha
            $sql = "UPDATE " . MAIN_DB_PREFIX . "re_consulta SET
                        fecha_recordatorio = '" . $db->escape($nueva_fecha) . "',
                        nota_recordatorio  = " . ($nueva_nota_rec ? "'" . $db->escape($nueva_nota_rec) . "'" : "nota_recordatorio") .
                   ($nota_done ? ", nota = CONCAT(COALESCE(nota,''), '\n[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_done) . "')" : "") .
                   " WHERE rowid = " . $rowid;
        } else {
            // Marcar como contactado y cerrar el recordatorio
            $sql = "UPDATE " . MAIN_DB_PREFIX . "re_consulta SET
                        recordatorio_done = 1,
                        fecha_recordatorio = NULL" .
                   ($nota_done ? ", nota = CONCAT(COALESCE(nota,''), '\n[Seguimiento " . date('d/m/Y') . "]: " . $db->escape($nota_done) . "')" : "") .
                   " WHERE rowid = " . $rowid;
        }
        $db->query($sql);

        // Crear nueva entrada en re_gestion_propietario para que aparezca en captacion
        if ($nota_done || $resultado_seg) {
            $rOrig = $db->query("SELECT fk_societe_activo, fk_societe_propietario,
                                        propietario_nombre, propietario_telefono, fk_user_vendedor
                                 FROM " . MAIN_DB_PREFIX . "re_gestion_propietario
                                 WHERE rowid = " . $rowid);
            if ($rOrig && ($oOrig = $db->fetch_object($rOrig))) {
                $canal_map = ['ATENDIO'=>'TELEFONO','NO_ATENDIO'=>'TELEFONO','WHATSAPP'=>'WHATSAPP'];
                $canal_seg = $canal_map[$resultado_seg] ?? 'TELEFONO';
                $res_map   = ['ATENDIO'=>'ATENDIO','NO_ATENDIO'=>'NO_ATENDIO','WHATSAPP'=>'ATENDIO'];
                $res_seg   = $res_map[$resultado_seg] ?? 'ATENDIO';
                $now = date('Y-m-d H:i:s');
                $db->query("INSERT INTO " . MAIN_DB_PREFIX . "re_gestion_propietario
                    (fk_societe_activo, fk_societe_propietario, propietario_nombre, propietario_telefono,
                     canal, resultado, nota, fecha, fk_user_vendedor,
                     recordatorio_done, date_creation)
                    VALUES (
                        " . (int)$oOrig->fk_societe_activo . ",
                        " . ($oOrig->fk_societe_propietario ? (int)$oOrig->fk_societe_propietario : 'NULL') . ",
                        " . ($oOrig->propietario_nombre ? "'" . $db->escape($oOrig->propietario_nombre) . "'" : 'NULL') . ",
                        " . ($oOrig->propietario_telefono ? "'" . $db->escape($oOrig->propietario_telefono) . "'" : 'NULL') . ",
                        '" . $db->escape($canal_seg) . "',
                        '" . $db->escape($res_seg) . "',
                        '" . $db->escape($nota_done) . "',
                        '$now',
                        " . ($oOrig->fk_user_vendedor ? (int)$oOrig->fk_user_vendedor : 'NULL') . ",
                        1,
                        '$now'
                    )");
            }
        }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Query de pendientes (vencidos + hoy) ─────────────────────
$hoy = date('Y-m-d');

$sql = "SELECT c.*,
            sa.nom  AS activo_nom,
            so.nom  AS actor_nom,
            so.phone AS actor_phone,
            CONCAT(u.firstname, ' ', u.lastname) AS vendedor_nom,
            CONCAT(ur.firstname, ' ', ur.lastname) AS recordatorio_user_nom,
            ef.calle, ef.numero,
            ef.prioridad_de_contacto,
            DATEDIFF('$hoy', DATE(c.fecha_recordatorio)) AS dias_vencido
        FROM " . MAIN_DB_PREFIX . "re_consulta c
        LEFT JOIN " . MAIN_DB_PREFIX . "societe sa     ON sa.rowid = c.fk_societe_activo
        LEFT JOIN " . MAIN_DB_PREFIX . "societe so     ON so.rowid = c.fk_societe_actor
        LEFT JOIN " . MAIN_DB_PREFIX . "user u         ON u.rowid  = c.fk_user_vendedor
        LEFT JOIN " . MAIN_DB_PREFIX . "user ur        ON ur.rowid = c.fk_user_recordatorio
        LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef      ON ef.fk_object = sa.rowid
        LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef_actor ON ef_actor.fk_object = so.rowid
        WHERE c.recordatorio_done = 0
          AND c.fecha_recordatorio IS NOT NULL
          AND c.fecha_recordatorio <= '$hoy'
        ORDER BY c.fecha_recordatorio ASC";

// Paginación compradores — COUNT simple + query principal con LIMIT/OFFSET
$pagePend  = max(0, (int)GETPOST('pp', 'int'));
$limitPend = 30;

// COUNT simple — evita problemas con CASE en subquery
$sqlCount2 = "SELECT COUNT(*) AS cnt FROM " . MAIN_DB_PREFIX . "re_consulta
              WHERE recordatorio_done = 0
                AND fecha_recordatorio IS NOT NULL
                AND fecha_recordatorio <= '$hoy'";
$resCount2 = $db->query($sqlCount2);
$totalRows = ($resCount2) ? (int)$db->fetch_object($resCount2)->cnt : 0;

$sqlPaged = $sql . " LIMIT $limitPend OFFSET " . ($pagePend * $limitPend);
$res2 = $db->query($sqlPaged);
$rows = [];
while ($res2 && ($o = $db->fetch_object($res2))) $rows[] = $o;

// ── Historial últimos 2 contactos por actor ───────────────────
$historialPorActor = [];
if (!empty($rows)) {
    $actorIds = array_filter(array_unique(array_column($rows, 'fk_societe_actor')));
    if (!empty($actorIds)) {
        $idsStr = implode(',', array_map('intval', $actorIds));
        $sqlHist = "SELECT fk_societe_actor, date_consulta, canal, estado, LEFT(nota, 150) AS nota
                    FROM " . MAIN_DB_PREFIX . "re_consulta
                    WHERE fk_societe_actor IN ($idsStr)
                      AND recordatorio_done = 1
                    ORDER BY fk_societe_actor, date_consulta DESC";
        $resHist = $db->query($sqlHist);
        $contadorPorActor = [];
        while ($resHist && ($h = $db->fetch_object($resHist))) {
            $aid = (int)$h->fk_societe_actor;
            if (!isset($contadorPorActor[$aid])) $contadorPorActor[$aid] = 0;
            if ($contadorPorActor[$aid] >= 2) continue;
            $historialPorActor[$aid][] = $h;
            $contadorPorActor[$aid]++;
        }
    }
}

// ── Query pendientes de gestión con propietarios ──────────────
$sqlGest = "SELECT g.*,
                sa.nom AS activo_nom,
                ef.calle, ef.numero,
                COALESCE(sp.nom, g.propietario_nombre) AS prop_nom,
                COALESCE(sp.phone, g.propietario_telefono) AS prop_phone,
                CONCAT(u.firstname,' ',u.lastname) AS vendedor_nom
            FROM " . MAIN_DB_PREFIX . "re_gestion_propietario g
            LEFT JOIN " . MAIN_DB_PREFIX . "societe sa  ON sa.rowid = g.fk_societe_activo
            LEFT JOIN " . MAIN_DB_PREFIX . "societe sp  ON sp.rowid = g.fk_societe_propietario
            LEFT JOIN " . MAIN_DB_PREFIX . "user u      ON u.rowid  = g.fk_user_vendedor
            LEFT JOIN " . MAIN_DB_PREFIX . "societe_extrafields ef ON ef.fk_object = g.fk_societe_activo
            WHERE g.recordatorio_done = 0
              AND g.fecha_recordatorio IS NOT NULL
              AND g.fecha_recordatorio <= '$hoy'
            ORDER BY g.fecha_recordatorio ASC";
// Paginación propietarios — COUNT + LIMIT/OFFSET en SQL (no cargar todo en memoria)
$pageGest  = max(0, (int)GETPOST('pg', 'int'));
$limitGest = 30;

// COUNT para UI de paginación y badge de vencidos
$sqlGestCount = "SELECT COUNT(*) AS cnt FROM (" . $sqlGest . ") AS tbl_cnt";
$resGestCount = $db->query($sqlGestCount);
$totalGest    = ($resGestCount) ? (int)$db->fetch_object($resGestCount)->cnt : 0;
// Todos los registros del sqlGest ya son vencidos (fecha <= hoy) — el COUNT es el total de vencidos
$vencidosGest = $totalGest;

// Query principal con LIMIT/OFFSET
$sqlGestPaged = $sqlGest . " LIMIT $limitGest OFFSET " . ($pageGest * $limitGest);
$resGest  = $db->query($sqlGestPaged);
$rowsGest = [];
while ($resGest && ($o = $db->fetch_object($resGest))) $rowsGest[] = $o;

$canalesGest    = ReGestion::CANALES;
$resultadosGest = ReGestion::RESULTADOS;

$canales   = ReConsulta::CANALES;
$estados   = ReConsulta::ESTADOS;
$estColors = ReConsulta::ESTADO_COLORS;

// ── Header ───────────────────────────────────────────────────
llxHeader('', 'Pendientes de seguimiento');

$total    = count($rows);
$totalG   = count($rowsGest);
$totalAll = $total + $totalG;
$vencidos = 0;
foreach ($rows    as $r) { if ($r->fecha_recordatorio < $hoy) $vencidos++; }
$vencidos += $vencidosGest; // todos los registros del sqlGest son vencidos

// Nav modos
echo '<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">';
echo '<a href="/custom/realestatecrmfields/captacion.php" class="butAction">'
   . '<span class="fas fa-building" style="margin-right:5px"></span>Captación</a>';
echo '<a href="/custom/realestatecrmfields/compradores.php" class="butAction">'
   . '<span class="fas fa-users" style="margin-right:5px"></span>Compradores</a>';
echo '<span class="butActionDelete" style="cursor:default;opacity:1;background:#c0392b;border-color:#c0392b;margin-left:auto">'
   . '<span class="fas fa-bell" style="margin-right:5px"></span>Pendientes'
   . ($totalAll ? ' <span style="background:rgba(255,255,255,.3);border-radius:10px;padding:0 7px">' . $totalAll . '</span>' : '')
   . '</span>';
echo '</div>';

echo '<div class="titre inline-block" style="margin-bottom:12px">';
echo '<span class="fas fa-bell" style="margin-right:6px;color:' . ($vencidos ? '#c0392b' : '#6c6aa8') . '"></span>';
echo '<strong>Pendientes de seguimiento</strong>';
if ($totalAll) {
    echo ' <span style="background:' . ($vencidos ? '#c0392b' : '#6c6aa8') . ';color:#fff;border-radius:12px;padding:2px 10px;font-size:.85em;margin-left:8px">' . $totalAll . '</span>';
}
echo '</div>';

if (empty($rows) && empty($rowsGest)) {
    echo '<div class="info" style="padding:20px;text-align:center">';
    echo '<span class="fas fa-check-circle" style="font-size:2em;color:#28a745;display:block;margin-bottom:8px"></span>';
    echo 'Sin recordatorios pendientes para hoy. ¡Todo al día!';
    echo '</div>';
}

// ── SECCIÓN COMPRADORES ───────────────────────────────────────
if (!empty($rows)) {
    $vencComp = count(array_filter($rows, function($r) use ($hoy) { return $r->fecha_recordatorio < $hoy; }));
    echo '<div style="display:flex;align-items:center;gap:10px;margin:16px 0 8px">';
    echo '<span class="fas fa-users" style="color:#6c6aa8"></span>';
    echo '<strong style="font-size:1.05em">Compradores / Interesados</strong>';
    echo '<span style="background:' . ($vencComp ? '#c0392b' : '#6c6aa8') . ';color:#fff;border-radius:10px;padding:0 8px;font-size:.82em">' . count($rows) . '</span>';
    echo '<a href="/custom/realestatecrmfields/compradores.php" style="font-size:.82em;color:#6c6aa8;margin-left:auto">Ver todos →</a>';
    echo '</div>';

    echo '<div class="div-table-responsive">';
    echo '<table class="noborder centpercent liste">';
    echo '<tr class="liste_titre">';
    echo '<th>Actor / Interesado</th><th>Propiedad</th><th>Canal</th>';
    echo '<th>Estado</th><th>Nota</th><th>Recordatorio</th>';
    echo '<th class="center">Acción</th>';
    echo '</tr>';

    foreach ($rows as $r) {
        $esVencido = ($r->fecha_recordatorio < $hoy);
        $rowBg = $esVencido ? 'background:#fff5f5' : '';

        if ($r->fk_societe_actor) {
            $actorLink = '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . $r->fk_societe_actor . '">'
                       . dol_htmlentities($r->actor_nom) . '</a>';
            if ($r->actor_phone) $actorLink .= '<br><small class="opacitymedium">' . dol_htmlentities($r->actor_phone) . '</small>';
        } else {
            $actorLink = dol_htmlentities($r->actor_nombre ?: '—');
            if ($r->actor_telefono) $actorLink .= '<br><small class="opacitymedium">' . dol_htmlentities($r->actor_telefono) . '</small>';
        }

        $dir = trim(($r->calle ?: '') . ' ' . ($r->numero ?: ''));
        $activoLink = $r->fk_societe_activo
            ? '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . $r->fk_societe_activo . '">' . dol_htmlentities($dir ?: $r->activo_nom) . '</a>'
            : '<span class="opacitymedium">Sin propiedad</span>';

        $fechaRec = date('d/m/Y', strtotime($r->fecha_recordatorio));
        $fechaTag = $esVencido
            ? '<span style="color:#c0392b;font-weight:600"><span class="fas fa-exclamation-circle"></span> ' . $fechaRec . '</span>'
            : '<span style="color:#0d6efd">' . $fechaRec . '</span>';
        // Fecha de primer contacto
        if ($r->date_consulta) {
            $fechaTag .= '<br><small style="color:#aaa;font-size:.78em">1er contacto: ' . date('d/m/Y', is_numeric($r->date_consulta) ? (int)$r->date_consulta : strtotime($r->date_consulta)) . '</small>';
        }

        // Historial últimos 2 contactos
        $historialHtml = '';
        $historialFull = '';
        $entradas = $historialPorActor[(int)$r->fk_societe_actor] ?? [];
        if (!empty($entradas)) {
            $labels_estado = ['CONSULTO'=>'Consultó','VISITO'=>'Visitó','OFRECIO'=>'Ofreció','CERRO'=>'Cerró'];
            $labels_canal  = ['WHATSAPP'=>'WA','TELEFONO'=>'Tel','EMAIL'=>'Email','INSTAGRAM'=>'IG'];
            foreach ($entradas as $idx => $h) {
                $fecha       = $h->date_consulta ? date('d/m/Y', is_numeric($h->date_consulta) ? (int)$h->date_consulta : strtotime($h->date_consulta)) : '—';
                $estadoLabel = $labels_estado[$h->estado] ?? $h->estado;
                $canalLabel  = $labels_canal[$h->canal] ?? $h->canal;
                $nota        = $h->nota ?? '';
                $notaTrunc   = mb_strlen($nota) > 60 ? mb_substr($nota, 0, 60) . '…' : $nota;
                $linea = '<div style="border-left:2px solid #dee2e6;padding-left:6px;margin-top:4px;font-size:.78em">'
                       . '<span style="color:#888">' . $fecha . ' · ' . $canalLabel . ' · ' . $estadoLabel . '</span>';
                if ($nota) {
                    $linea .= '<br><span class="hist-corta">' . dol_htmlentities($notaTrunc) . '</span>'
                            . '<span class="hist-completa" style="display:none">' . dol_htmlentities($nota) . '</span>';
                }
                $linea .= '</div>';
                if ($idx === 0) $historialHtml .= $linea;
                $historialFull .= $linea;
            }
        }

        // Nota: mostrar directamente
        $notaTexto = $r->nota ?? '';

        $estadoBadge = '<span style="background:' . ($estColors[$r->estado] ?? '#6c757d') . ';color:#fff;padding:2px 8px;border-radius:4px;font-size:.82em">'
                     . dol_htmlentities($estados[$r->estado] ?? $r->estado) . '</span>';

        echo '<tr class="oddeven" style="' . $rowBg . '">';
        echo '<td>' . $actorLink . '</td>';
        echo '<td>' . $activoLink . '</td>';
        echo '<td><small>' . ((['WHATSAPP'=>'🟢','INSTAGRAM'=>'📸','EMAIL'=>'📧','TELEFONO'=>'📞'][$r->canal] ?? '') . ' ' . dol_htmlentities($canales[$r->canal] ?? $r->canal)) . '</small></td>';
        echo '<td>' . $estadoBadge . '</td>';
        echo '<td class="pend-nota-celda" style="cursor:pointer;max-width:180px" title="Clic para expandir">'
            . '<small><span class="pend-nota-corta">' . dol_htmlentities(dol_trunc($notaTexto, 50)) . '</span>'
            . '<span class="pend-nota-completa" style="display:none;white-space:pre-wrap">' . dol_htmlentities($notaTexto) . '</span></small>'
            . '</td>';
        echo '<td class="pend-rec-celda" style="cursor:pointer;min-width:160px;vertical-align:top">'
            . $fechaTag
            . '<div class="hist-resumen">' . $historialHtml . '</div>'
            . '<div class="hist-detalle" style="display:none">' . $historialFull . '</div>'
            . (!empty($historialFull) ? '<div class="hist-toggle" style="font-size:.72em;color:#6c6aa8;margin-top:3px">▼ ver historial</div>' : '')
            . '</td>';
        echo '<td class="center nowrap"><button type="button" class="butAction re-done-btn" style="font-size:.8em;padding:3px 10px"
                data-rowid="' . $r->rowid . '" data-action-type="done"
                data-tipo="comp"
                data-actor="' . dol_escape_htmltag($r->actor_nom ?: $r->actor_nombre) . '">📋 Seguimiento</button>
              <form method="POST" action="" style="display:inline;margin-left:4px">
                <input type="hidden" name="action" value="done">
                <input type="hidden" name="token" value="' . getToken() . '">
                <input type="hidden" name="rowid" value="' . $r->rowid . '">
                <button type="submit" class="butActionDelete" style="font-size:.8em;padding:3px 10px"
                  onclick="return confirm(\'¿Marcar como resuelto sin nota?\')">✓ Resuelto</button>
              </form></td>';
        echo '</tr>';
    }
    echo '</table></div>';

    // ── Paginación compradores ─────────────────────────────────
    if ($totalRows > $limitPend) {
        $pagesPend = (int)ceil($totalRows / $limitPend);
        $pwindow   = 3;
        $pS = max(0, $pagePend - $pwindow);
        $pE = min($pagesPend - 1, $pagePend + $pwindow);
        echo '<div style="margin-top:8px;display:flex;gap:4px;align-items:center;justify-content:center;flex-wrap:wrap">';
        if ($pagePend > 0)
            echo '<a href="?' . http_build_query(array_merge($_GET,['pp'=>$pagePend-1,'pg'=>$pageGest])) . '" class="butAction" style="padding:2px 8px;font-size:.82em">← Ant.</a>';
        if ($pS > 0) {
            echo '<a href="?' . http_build_query(array_merge($_GET,['pp'=>0,'pg'=>$pageGest])) . '" style="margin:0 2px">1</a>';
            if ($pS > 1) echo '<span style="color:#aaa">…</span>';
        }
        for ($i = $pS; $i <= $pE; $i++) {
            $style = $i === $pagePend ? 'padding:2px 8px;border-radius:4px;background:#6c6aa8;color:#fff;font-weight:bold;' : 'margin:0 2px;color:#555;';
            echo '<a href="?' . http_build_query(array_merge($_GET,['pp'=>$i,'pg'=>$pageGest])) . '" style="' . $style . '">' . ($i+1) . '</a>';
        }
        if ($pE < $pagesPend - 1) {
            if ($pE < $pagesPend - 2) echo '<span style="color:#aaa">…</span>';
            echo '<a href="?' . http_build_query(array_merge($_GET,['pp'=>$pagesPend-1,'pg'=>$pageGest])) . '" style="margin:0 2px">' . $pagesPend . '</a>';
        }
        if ($pagePend < $pagesPend - 1)
            echo '<a href="?' . http_build_query(array_merge($_GET,['pp'=>$pagePend+1,'pg'=>$pageGest])) . '" class="butAction" style="padding:2px 8px;font-size:.82em">Sig. →</a>';
        echo '</div>';
        echo '<div class="opacitymedium" style="text-align:center;font-size:.85em;margin-top:4px">' . $totalRows . ' compradores · mostrando ' . ($pagePend*$limitPend+1) . '–' . min(($pagePend+1)*$limitPend,$totalRows) . '</div>';
    }
}

// ── SECCIÓN CAPTACIÓN / PROPIETARIOS ─────────────────────────
if (!empty($rowsGest)) {
    $vencCap = count(array_filter($rowsGest, function($r) use ($hoy) { return $r->fecha_recordatorio < $hoy; }));
    echo '<div style="display:flex;align-items:center;gap:10px;margin:24px 0 8px">';
    echo '<span class="fas fa-building" style="color:#0d6efd"></span>';
    echo '<strong style="font-size:1.05em">Captación / Propietarios</strong>';
    echo '<span style="background:' . ($vencCap ? '#c0392b' : '#0d6efd') . ';color:#fff;border-radius:10px;padding:0 8px;font-size:.82em">' . $totalGest . '</span>';
    echo '<a href="/custom/realestatecrmfields/captacion.php" style="font-size:.82em;color:#0d6efd;margin-left:auto">Ver todos →</a>';
    echo '</div>';

    echo '<div class="div-table-responsive">';
    echo '<table class="noborder centpercent liste">';
    echo '<tr class="liste_titre">';
    echo '<th>Activo</th><th>Propietario</th><th>Último resultado</th><th>Nota</th><th>Recordatorio</th>';
    echo '<th class="center">Acción</th>';
    echo '</tr>';

    foreach ($rowsGest as $r) {
        $esVencido = ($r->fecha_recordatorio < $hoy);
        $rowBg = $esVencido ? 'background:#fff5f5' : 'background:rgba(13,110,253,.03)';
        $dir   = trim(($r->calle ?: '') . ' ' . ($r->numero ?: ''));
        $activoLink = '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . $r->fk_societe_activo . '">'
                    . dol_htmlentities($dir ?: $r->activo_nom) . '</a>';

        $propTxt = dol_htmlentities($r->prop_nom ?: '—');
        if ($r->prop_phone) $propTxt .= '<br><small class="opacitymedium">' . dol_htmlentities($r->prop_phone) . '</small>';

        $resColors = ['ATENDIO'=>'#6c757d','NO_ATENDIO'=>'#adb5bd','QUIERE_VENDER'=>'#198754',
                      'NO_QUIERE'=>'#c0392b','QUIERE_TASAR'=>'#0d6efd','TASADO'=>'#6f42c1','MAIL_ENVIADO'=>'#0dcaf0'];
        $resBadge = $r->resultado
            ? '<span style="background:' . ($resColors[$r->resultado] ?? '#dee2e6') . ';color:#fff;padding:2px 8px;border-radius:4px;font-size:.82em">'
              . dol_htmlentities($resultadosGest[$r->resultado] ?? $r->resultado) . '</span>'
            : '<span class="opacitymedium">—</span>';

        $fechaRec = date('d/m/Y', strtotime($r->fecha_recordatorio));
        $fechaTag = $esVencido
            ? '<span style="color:#c0392b;font-weight:600"><span class="fas fa-exclamation-circle"></span> ' . $fechaRec . '</span>'
            : '<span style="color:#0d6efd">' . $fechaRec . '</span>';
        if ($r->nota_recordatorio) $fechaTag .= '<br><small class="opacitymedium">' . dol_htmlentities($r->nota_recordatorio) . '</small>';

        echo '<tr class="oddeven" style="' . $rowBg . '">';
        echo '<td>' . $activoLink . '</td>';
        echo '<td>' . $propTxt . '</td>';
        echo '<td>' . $resBadge . '</td>';
        echo '<td class="pend-nota-celda" style="cursor:pointer;max-width:180px" title="Clic para expandir">'
            . '<small><span class="pend-nota-corta">' . dol_htmlentities(dol_trunc($r->nota, 50)) . '</span>'
            . '<span class="pend-nota-completa" style="display:none;white-space:pre-wrap">' . dol_htmlentities($r->nota) . '</span></small>'
            . '</td>';
        echo '<td class="nowrap">' . $fechaTag . '</td>';
        echo '<td class="center nowrap"><button type="button" class="butAction re-done-gest-btn" style="font-size:.8em;padding:3px 10px"
                data-rowid="' . $r->rowid . '" data-action-type="done_gest"
                data-tipo="gest"
                data-actor="' . dol_escape_htmltag($dir ?: $r->activo_nom) . '">📋 Seguimiento</button>
              <form method="POST" action="" style="display:inline;margin-left:4px">
                <input type="hidden" name="action" value="done_gest">
                <input type="hidden" name="token" value="' . getToken() . '">
                <input type="hidden" name="rowid" value="' . $r->rowid . '">
                <button type="submit" class="butActionDelete" style="font-size:.8em;padding:3px 10px"
                  onclick="return confirm(\'¿Marcar como resuelto sin nota?\')">✓ Resuelto</button>
              </form></td>';
        echo '</tr>';
    }
    echo '</table></div>';

    // Paginación sección propietarios
    if ($totalGest > $limitGest) {
        $pagesGest = (int)ceil($totalGest / $limitGest);
        echo '<div style="margin-top:10px;display:flex;gap:4px;align-items:center;justify-content:center;flex-wrap:wrap">';
        if ($pageGest > 0) {
            echo '<a href="?' . http_build_query(array_merge($_GET, ['pg'=>$pageGest-1,'pp'=>$pagePend])) . '" class="butAction" style="padding:2px 8px;font-size:.82em">← Ant.</a>';
        }
        for ($i = 0; $i < $pagesGest; $i++) {
            $style = $i === $pageGest ? 'background:#6c6aa8;color:#fff;border-radius:4px;padding:2px 8px;font-weight:bold;' : 'padding:2px 6px;color:#555;';
            echo '<a href="?' . http_build_query(array_merge($_GET, ['pg'=>$i,'pp'=>$pagePend])) . '" style="' . $style . '">' . ($i+1) . '</a>';
        }
        if ($pageGest < $pagesGest - 1) {
            echo '<a href="?' . http_build_query(array_merge($_GET, ['pg'=>$pageGest+1,'pp'=>$pagePend])) . '" class="butAction" style="padding:2px 8px;font-size:.82em">Sig. →</a>';
        }
        echo '</div>';
        echo '<div class="opacitymedium" style="text-align:center;font-size:.85em;margin-top:4px">' . $totalGest . ' propietarios · mostrando ' . ($pageGest*$limitGest+1) . '–' . min(($pageGest+1)*$limitGest,$totalGest) . '</div>';
    }
}


echo '<div id="re-done-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;overflow-y:auto">
  <div style="background:#fff;max-width:540px;margin:60px auto;border-radius:6px;padding:28px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <button type="button" id="re-done-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.3em;cursor:pointer">✕</button>
    <h3 style="margin-top:0;margin-bottom:16px">
      <span class="fas fa-clipboard-list" style="color:#6c6aa8;margin-right:6px"></span>
      Seguimiento — <span id="done-actor-nom" style="color:#6c6aa8"></span>
    </h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="done">
      <input type="hidden" name="token" value=' . getToken() . '>
      <input type="hidden" name="rowid" id="done-rowid">
      <input type="hidden" name="resultado_seguimiento" id="done-resultado-val">
      <div style="margin-bottom:14px">
        <label style="font-weight:600;display:block;margin-bottom:8px">Resultado del contacto</label>
        <div id="done-resultado-comp" style="display:flex;flex-wrap:wrap;gap:6px">
          <button type="button" class="done-res-btn" data-val="ATENDIO"     style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">✅ Atendió</button>
          <button type="button" class="done-res-btn" data-val="NO_ATENDIO"  style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">📵 No contestó</button>
          <button type="button" class="done-res-btn" data-val="WHATSAPP"    style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">💬 WA enviado</button>
          <button type="button" class="done-res-btn" data-val="OFRECIO"     style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">💰 Hizo oferta</button>
          <button type="button" class="done-res-btn" data-val="SIN_INTERES" style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">❌ Sin interés</button>
        </div>
        <div id="done-resultado-gest" style="display:none;flex-wrap:wrap;gap:6px">
          <button type="button" class="done-res-btn" data-val="ATENDIO"    style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">✅ Llamó / habló</button>
          <button type="button" class="done-res-btn" data-val="NO_ATENDIO" style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">📵 No atendió</button>
          <button type="button" class="done-res-btn" data-val="WHATSAPP"   style="padding:5px 12px;border:1px solid #dee2e6;border-radius:20px;cursor:pointer;font-size:.85em;background:#fff">💬 WA enviado</button>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-weight:600;display:block;margin-bottom:4px">Nota</label>
        <div style="margin-bottom:5px">
          <span style="font-size:.78em;color:#999;margin-right:5px">Rápido:</span>
          <button type="button" class="done-respuesta-rapida" style="font-size:.76em;padding:2px 7px;border:1px solid #dee2e6;border-radius:4px;background:#f8f9fa;cursor:pointer"
            data-texto="Se consultó si pudo ver la ficha del garaje">📋 Consulta ficha</button>
        <button type="button" class="done-respuesta-rapida" style="font-size:.76em;padding:2px 7px;border:1px solid #dee2e6;border-radius:4px;background:#f8f9fa;cursor:pointer"
            data-texto="Se consultó si quiere coordinar una visita.">🚪 Consulta Visita</button>
        </div>
        <textarea name="nota_done" class="flat" rows="2" style="width:100%;box-sizing:border-box"
                  placeholder="Qué pasó, qué dijo…"></textarea>
      </div>
      <div style="background:#f8f9fa;border-radius:6px;padding:14px;margin-bottom:14px">
        <label style="font-weight:600;display:block;margin-bottom:8px;color:#555">
          <span class="fas fa-bell" style="margin-right:5px;color:#6c6aa8"></span>Próximo recordatorio
        </label>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div>
            <label style="font-size:.85em;color:#666;display:block;margin-bottom:3px">Días desde hoy</label>
            <input type="number" id="done-rec-dias" min="1" class="flat" style="width:70px" placeholder="días">
          </div>
          <div style="padding-top:18px;color:#6c6aa8;font-weight:600" id="done-rec-preview"></div>
          <div>
            <label style="font-size:.85em;color:#666;display:block;margin-bottom:3px">O fecha exacta</label>
            <input type="date" name="nueva_fecha_recordatorio" id="done-rec-fecha" class="flat">
          </div>
        </div>
        <div style="margin-top:8px;font-size:.82em;color:#888">
          <span class="fas fa-info-circle"></span>
          Si cargás fecha, se <strong>reprograma</strong>. Si la dejás vacía, se <strong>cierra</strong>.
        </div>
      </div>
      <div style="text-align:right">
        <button type="button" id="re-done-cancel" class="butActionDelete" style="margin-right:8px">Cancelar</button>
        <button type="button" id="re-done-cerrar" class="butAction" style="display:none;background:#c0392b;border-color:#c0392b;margin-right:8px">✕ Cerrar contacto</button>
        <button type="button" id="re-done-guardar" class="butAction">Guardar seguimiento</button>
      </div>
    </form>
  </div>
</div>';

echo <<<'JSEOF'
<script>
$(function() {
    $(document).on("click", ".re-done-btn", function() {
        var tipo = $(this).data("tipo") || "comp";
        var actionType = $(this).data("action-type") || "done";
        $("#done-rowid").val($(this).data("rowid"));
        $("#done-actor-nom").text($(this).data("actor") || "este interesado");
        $("input[name='action']").val(actionType);
        // Mostrar panel de resultados correcto
        $("#done-resultado-comp").toggle(tipo === "comp").css("display", tipo === "comp" ? "flex" : "none");
        $("#done-resultado-gest").toggle(tipo === "gest").css("display", tipo === "gest" ? "flex" : "none");
        // Resetear selección de resultado
        $(".done-res-btn").css({background:"#fff", borderColor:"#dee2e6", color:"inherit", fontWeight:"normal"});
        $("#done-resultado-val").val("");
        $("#re-done-cerrar").hide();
        $("#re-done-guardar").text("Guardar seguimiento");
        // Resetear otros campos
        $("#done-rec-dias").val("2");
        // Calcular fecha default = hoy + 2 días
        var dtDef = new Date(); dtDef.setDate(dtDef.getDate() + 2);
        var yyyyDef = dtDef.getFullYear();
        var mmDef   = String(dtDef.getMonth()+1).padStart(2,"0");
        var ddDef   = String(dtDef.getDate()).padStart(2,"0");
        $("#done-rec-fecha").val(yyyyDef+"-"+mmDef+"-"+ddDef);
        $("#done-rec-preview").text("→ "+ddDef+"/"+mmDef+"/"+yyyyDef);
        $("textarea[name=nota_done]").val("");
        $("input[name=nueva_nota_recordatorio]").val("");
        $("#re-done-modal").fadeIn(150);
        setTimeout(function(){ $("textarea[name=nota_done]").focus(); }, 150);
    });
    // Manejar selección de botón de resultado
    $(document).on("click", ".done-res-btn", function() {
        $(".done-res-btn").css({background:"#fff", borderColor:"#dee2e6", color:"inherit", fontWeight:"normal"});
        $(this).css({background:"#6c6aa8", borderColor:"#6c6aa8", color:"#fff", fontWeight:"600"});
        var val = $(this).data("val");
        $("#done-resultado-val").val(val);
        // Mostrar botón Cerrar solo cuando selecciona No contestó
        if (val === "NO_ATENDIO") {
            $("#re-done-cerrar").show();
            $("#re-done-guardar").text("Reprogramar");
        } else {
            $("#re-done-cerrar").hide();
            $("#re-done-guardar").text("Guardar seguimiento");
        }
    });

    // Botón Cerrar contacto — guarda el resultado y cierra sin reprogramar
    $("#re-done-cerrar").on("click", function() {
        var $btn = $(this).prop("disabled", true).text("Cerrando…");
        var tokenFresco = $('meta[name="anti-csrf-newtoken"]').attr('content')
                       || $('input[name="token"]').first().val() || '';
        var actionType = $("input[name='action']").val() || 'done';
        var data = {
            action:                   actionType,
            token:                    tokenFresco,
            rowid:                    $("#done-rowid").val(),
            resultado_seguimiento:    "NO_ATENDIO",
            nota_done:                $("textarea[name='nota_done']").val(),
            nueva_fecha_recordatorio: "", // sin fecha = cierra
            nueva_nota_recordatorio:  ""
        };
        $.post('/custom/realestatecrmfields/ajax/pendiente_done.php', data, function(r) {
            if (r && r.success) {
                $("#re-done-modal").fadeOut(150);
                location.reload();
            } else {
                alert("Error: " + (r ? JSON.stringify(r) : "sin respuesta"));
                $btn.prop("disabled", false).text("✕ Cerrar contacto");
            }
        }, 'json').fail(function(xhr) {
            alert("Error HTTP " + xhr.status);
            $btn.prop("disabled", false).text("✕ Cerrar contacto");
        });
    });
    // Respuestas rápidas — insertar texto en el campo Nota
    $(document).on('click', '.done-respuesta-rapida', function() {
        var texto = $(this).data('texto');
        var $nota = $("textarea[name='nota_done']");
        var actual = $nota.val().trim();
        $nota.val(actual ? actual + ' ' + texto : texto).focus();
    });

    // Guardar seguimiento via endpoint dedicado con token fresco
    $("#re-done-guardar").on("click", function() {
        var $btn = $(this).prop("disabled", true).text("Guardando…");
        // Leer token fresco del meta tag (siempre actualizado por Dolibarr)
        var tokenFresco = $('meta[name="anti-csrf-newtoken"]').attr('content')
                       || $('input[name="token"]').first().val() || '';
        var actionType = $("input[name='action']").val() || 'done';
        var data = {
            action:                   actionType,
            token:                    tokenFresco,
            rowid:                    $("#done-rowid").val(),
            resultado_seguimiento:    $("#done-resultado-val").val(),
            nota_done:                $("textarea[name='nota_done']").val(),
            nueva_fecha_recordatorio: $("#done-rec-fecha").val(),
            nueva_nota_recordatorio:  $("input[name='nueva_nota_recordatorio']").val()
        };
        $.post('/custom/realestatecrmfields/ajax/pendiente_done.php', data, function(r) {
            if (r && r.success) {
                $("#re-done-modal").fadeOut(150);
                location.reload();
            } else {
                alert("Error al guardar: " + (r ? JSON.stringify(r) : "sin respuesta"));
                $btn.prop("disabled", false).text("Guardar seguimiento");
            }
        }, 'json').fail(function(xhr) {
            alert("Error HTTP " + xhr.status);
            $btn.prop("disabled", false).text("Guardar seguimiento");
        });
    });

    $("#re-done-cancel, #re-done-close").on("click", function() { $("#re-done-modal").fadeOut(150); });

    // Modal para gestión propietarios
    $(document).on("click", ".re-done-gest-btn", function() {
        var rowid = $(this).data("rowid");
        var actor = $(this).data("actor") || "este activo";
        var actionType = $(this).data("action-type") || "done_gest";
        $("#done-rowid").val(rowid);
        $("#done-actor-nom").text(actor);
        $("input[name='action']").val(actionType);
        $("textarea[name='nota_done']").val("");
        $("#done-rec-dias").val("2");
        var dtDef = new Date(); dtDef.setDate(dtDef.getDate() + 2);
        var yyyyDef = dtDef.getFullYear();
        var mmDef   = String(dtDef.getMonth()+1).padStart(2,"0");
        var ddDef   = String(dtDef.getDate()).padStart(2,"0");
        $("#done-rec-fecha").val(yyyyDef+"-"+mmDef+"-"+ddDef);
        $("#done-rec-preview").text("→ "+ddDef+"/"+mmDef+"/"+yyyyDef);
        $("input[name='nueva_nota_recordatorio']").val("");
        $("#re-done-modal").fadeIn(150);
        setTimeout(function(){ $("textarea[name='nota_done']").focus(); }, 150);
    });

    // Asegurar action correcto para re-done-btn
    $(document).on("click", ".re-done-btn", function() {
        var actionType = $(this).data("action-type") || "done";
        $("input[name='action']").val(actionType);
    });
    $("#re-done-modal").on("click", function(e) {
        if ($(e.target).is("#re-done-modal")) $("#re-done-modal").fadeOut(150);
    });
    // Calcular fecha desde días
    $("#done-rec-dias").on("input", function() {
        var d = parseInt($(this).val());
        if (isNaN(d) || d < 1) { $("#done-rec-preview").text(""); $("#done-rec-fecha").val(""); return; }
        var dt = new Date(); dt.setDate(dt.getDate() + d);
        var yyyy = dt.getFullYear();
        var mm   = String(dt.getMonth()+1).padStart(2,"0");
        var dd   = String(dt.getDate()).padStart(2,"0");
        $("#done-rec-fecha").val(yyyy+"-"+mm+"-"+dd);
        $("#done-rec-preview").text("→ "+dd+"/"+mm+"/"+yyyy);
    });
    // Si se edita la fecha directamente, limpiar días
    $("#done-rec-fecha").on("change", function() {
        $("#done-rec-dias").val("");
        var v = $(this).val();
        if (v) {
            var parts = v.split("-");
            $("#done-rec-preview").text("→ "+parts[2]+"/"+parts[1]+"/"+parts[0]);
        } else {
            $("#done-rec-preview").text("");
        }
    });

    // Expandir/colapsar historial en columna Recordatorio
    $(document).on('click', '.pend-rec-celda', function() {
        var $resumen = $(this).find('.hist-resumen');
        var $detalle = $(this).find('.hist-detalle');
        var $toggle  = $(this).find('.hist-toggle');
        var expandida = $detalle.is(':visible');
        $resumen.toggle(expandida);
        $detalle.toggle(!expandida);
        $toggle.text(expandida ? '▼ ver historial' : '▲ ocultar');
        // También expandir notas internas del historial
        if (!expandida) {
            $(this).find('.hist-corta').hide();
            $(this).find('.hist-completa').show();
        } else {
            $(this).find('.hist-corta').show();
            $(this).find('.hist-completa').hide();
        }
    });

    // Expandir/colapsar nota al hacer clic
    $(document).on('click', '.pend-nota-celda', function() {
        var $corta    = $(this).find('.pend-nota-corta');
        var $completa = $(this).find('.pend-nota-completa');
        var expandida = $completa.is(':visible');
        $corta.toggle(expandida);
        $completa.toggle(!expandida);
        $(this).css('max-width', expandida ? '180px' : 'none');
        $(this).attr('title', expandida ? 'Clic para expandir' : 'Clic para colapsar');
    });
});
</script>
JSEOF;

llxFooter();
$db->close();
