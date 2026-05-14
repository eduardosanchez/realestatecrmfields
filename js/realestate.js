/* ============================================================
   RealEstateCrmFields - JavaScript
   Contextos: societe/card.php (create, edit, view) y list.php
   ============================================================ */

jQuery(document).ready(function($) {

    var phpSelf = window.location.pathname;
    var params  = new URLSearchParams(window.location.search);
    var action  = params.get('action') || 'view';
    var socid   = params.get('socid') || params.get('id') || '0';

    // TOKEN CSRF — Dolibarr lo expone en el meta anti-csrf-newtoken o en inputs hidden
    var TOKEN = $('meta[name="anti-csrf-newtoken"]').attr('content')
             || $('input[name="token"]').first().val()
             || '';

    var subtypeLabels = {}; // code → libelle, cargado una vez
    var subtypeToType = {}; // code → typeCode (RE_ACT/RE_AOR/RE_SRV)

    // Caché global para get_subtypes.php — evita llamadas redundantes
    var _subtypesCache = null;
    function getSubtypes(callback) {
        if (_subtypesCache) { callback(_subtypesCache); return; }
        $.getJSON('/custom/realestatecrmfields/ajax/get_subtypes.php', function(data) {
            _subtypesCache = data;
            $.each(data, function(tc, subs) {
                $.each(subs, function(i, sub) {
                    subtypeLabels[sub.code] = sub.libelle;
                    subtypeToType[sub.code] = tc;
                });
            });
            callback(data);
        });
    }

    // Verifica si typeCode y subtypeCode son incompatibles
    function isMismatch(typeCode, subtypeCode) {
        if (!typeCode || !subtypeCode) return false;
        var expected = subtypeToType[subtypeCode];
        if (!expected) return false;
        return expected !== typeCode;
    }

    // Aplica o quita el resaltado de error en un <tr>
    // Usa style inline para garantizar que pisa el CSS de Dolibarr (.oddeven, etc.)
    function markMismatch($tr, bad) {
        if (bad) {
            $tr.addClass('re-mismatch');
            $tr.find('td').css({ 'background-color': '#fff0f0', 'border-top-color': '#f5c2c2', 'border-bottom-color': '#f5c2c2' });
            $tr.find('td:first-child').css('border-left', '3px solid #c0392b');
            $tr.find('td.re-subtipo-td').css({ 'color': '#c0392b', 'font-weight': '600' });
        } else {
            $tr.removeClass('re-mismatch');
            $tr.find('td').css({ 'background-color': '', 'border-top-color': '', 'border-bottom-color': '' });
            $tr.find('td:first-child').css('border-left', '');
            $tr.find('td.re-subtipo-td').css({ 'color': '', 'font-weight': '' });
        }
    }

    // Pre-cargar etiquetas de subtipos — usa caché
    function loadSubtypeLabels(callback) {
        getSubtypes(function(data) { callback(data); });
    }

    // =========================================================
    // LIST.PHP
    // =========================================================
    if (phpSelf.indexOf('/societe/list.php') !== -1) {

        var typeIdToCode = { '0': 'RE_ACT', '101': 'RE_AOR', '102': 'RE_SRV' };

        // Leer tipo seleccionado del DOM (ya renderizado por Dolibarr)
        var $tipoSelect     = $('select#search_type_thirdparty');
        var currentTypeId   = $tipoSelect.val() || '-1';
        var currentTypeCode = typeIdToCode[String(currentTypeId)] || '';

        // Subtipo activo viene en GET (lo ponemos nosotros en la URL)
        var currentSubtype  = params.get('search_re_subtypent') || '';

        // ── 0. Pestaña "Actores" en la barra de navegación ───────
        // Buscar los tabs de tipo (Terceros / Contactos / etc.) e inyectar "Actores"
        var $tabsBar = $('div.tabsAction, div.tabs, ul.tabBar').first();
        if (!$tabsBar.length) {
            // Fallback: buscar el link a list.php en los tabs superiores
            $tabsBar = $('a[href*="societe/list.php"]').closest('div, ul').first();
        }
        // Inyectar como link destacado junto al breadcrumb si no hay barra de tabs
        if (!$('#re-actores-tab').length) {
            var $breadcrumb = $('div.titre.inline-block').first();
            if ($breadcrumb.length) {
                $breadcrumb.after(
                    '<div class="inline-block" style="margin-left:16px;margin-bottom:8px">' +
                    '<a id="re-actores-tab" href="/custom/realestatecrmfields/actores.php" ' +
                    'class="butAction" style="background:#6c6aa8;color:#fff;border-color:#6c6aa8">' +
                    '<span class="fas fa-users" style="margin-right:4px"></span>Actores</a></div>'
                );
            }
        }

        // ── 1. TH cabecera ─────────────────────────────────────
        $('th[title="Tipo de Tercero"]').after(
            '<th class="wrapcolumntitle center liste_titre re-subtipo-th" title="Subtipo">Subtipo</th>'
        );

        // ── 2. Select de filtro (vacío hasta que cargue AJAX) ──
        var $tdTipo = $('td:has(select#search_type_thirdparty)');
        if ($tdTipo.length) {
            $tdTipo.after(
                '<td class="liste_titre maxwidthonsmartphone center re-subtipo-filter-td">' +
                '<select id="search_re_subtypent" class="flat minwidth100 maxwidth150">' +
                '<option value="">-- Subtipo --</option>' +
                '</select></td>'
            );
        }

        // ── 3. TD de datos en cada fila (placeholder) ──────────
        $('tr[data-rowid]').each(function() {
            var $tr   = $(this);
            var rowId = $tr.data('rowid');
            if (!rowId) return;
            var $tipoCel = $tr.find('td[title="Activo Inmobiliario"], td[title="Actor"], td[title="Servicio"]');
            if ($tipoCel.length) {
                $tipoCel.first().after(
                    '<td class="center tdoverflowmax125 re-subtipo-td" data-rowid="' + rowId + '">' +
                    '<span class="opacitymedium">…</span></td>'
                );
            }
        });

        // ── 4. Cargar subtipos de todas las filas en batch ─────
        var rowIds = [];
        $('td.re-subtipo-td').each(function() { rowIds.push($(this).data('rowid')); });

        if (rowIds.length === 0) {
            initSubtipoSelect({}, []);
            return;
        }

        $.getJSON('/custom/realestatecrmfields/ajax/get_subtypes_batch.php',
            { ids: rowIds.join(',') },
            function(subtypesMap) {
                // subtypesMap = { "8620": "GARAJE", "8621": "", ... }

                // Cargar labels de subtipos — usa caché
                getSubtypes(function(subtypesByType) {

                    // ── Reemplazar ícono de edificio según tipo/subtipo ──
                    var iconMap = {
                        // Activos (RE_ACT)
                        'GARAJE':      { icon: 'fa-car',              color: '#2980b9' },
                        'PLAYA_ESTAC': { icon: 'fa-parking',          color: '#2980b9' },
                        'ESTAC_SERV':  { icon: 'fa-gas-pump',         color: '#e67e22' },
                        'EDIF_POTENC': { icon: 'fa-building',         color: '#8e44ad' },
                        'GALPON':      { icon: 'fa-warehouse',         color: '#7f8c8d' },
                        // Actores (RE_AOR)
                        'INVERSOR':    { icon: 'fa-chart-line',        color: '#27ae60' },
                        'PROPIETARIO': { icon: 'fa-home',              color: '#16a085' },
                        'OPERADOR':    { icon: 'fa-cogs',              color: '#2c3e50' },
                        'CORREDOR':    { icon: 'fa-handshake',         color: '#d35400' },
                        'DESARROLLADOR':{ icon: 'fa-city',             color: '#8e44ad' },
                        'ADMINISTRADOR':{ icon: 'fa-user-tie',         color: '#2c3e50' },
                        // Servicios (RE_SRV)
                        'ESCRIBANIA':  { icon: 'fa-stamp',             color: '#c0392b' },
                        'ABOGADO':     { icon: 'fa-balance-scale',     color: '#c0392b' },
                        'ARQUITECTO':  { icon: 'fa-drafting-compass',  color: '#1abc9c' },
                        'CONTADOR':    { icon: 'fa-calculator',        color: '#2980b9' },
                        'CONSULTOR':   { icon: 'fa-lightbulb',         color: '#f39c12' },
                    };
                    // Por tipo (fallback si no hay subtipo)
                    var typeIconMap = {
                        'RE_ACT': { icon: 'fa-building',  color: '#6c6aa8' },
                        'RE_AOR': { icon: 'fa-users',     color: '#27ae60' },
                        'RE_SRV': { icon: 'fa-briefcase', color: '#e74c3c' },
                    };

                    $('tr[data-rowid]').each(function() {
                        var $tr      = $(this);
                        var rowId    = String($tr.data('rowid'));
                        var subCode  = subtypesMap[rowId] || '';
                        var typeCode = 'RE_ACT';
                        if ($tr.find('td[title="Actor"]').length)    typeCode = 'RE_AOR';
                        if ($tr.find('td[title="Servicio"]').length) typeCode = 'RE_SRV';

                        var cfg = iconMap[subCode] || typeIconMap[typeCode];
                        if (!cfg) return;

                        // El ícono está dentro del <a> del nombre — primer span.fas
                        var $icon = $tr.find('td[data-key="ref"] span.fas').first();
                        if ($icon.length) {
                            $icon.attr('class', 'fas ' + cfg.icon + ' paddingright')
                                 .css('color', cfg.color);
                        }
                    });

                    // ── Poblar celdas de datos y marcar filas con mismatch ──
                    $('td.re-subtipo-td').each(function() {
                        var $td   = $(this);
                        var $tr   = $td.closest('tr');
                        var rid   = String($td.data('rowid'));
                        var code  = subtypesMap[rid] || '';
                        var label = subtypeLabels[code] || code;
                        $td.html(label || '<span class="opacitymedium">—</span>');
                        $td.data('subtype', code);

                        // Determinar el typeCode de esta fila desde el td de tipo
                        var typeIdToCodeLocal = { '0': 'RE_ACT', '101': 'RE_AOR', '102': 'RE_SRV' };
                        var $tipoCel = $tr.find('td[title="Activo Inmobiliario"]');
                        var rowTypeCode = 'RE_ACT';
                        if ($tr.find('td[title="Actor"]').length)    rowTypeCode = 'RE_AOR';
                        if ($tr.find('td[title="Servicio"]').length) rowTypeCode = 'RE_SRV';

                        if (isMismatch(rowTypeCode, code)) {
                            markMismatch($tr, true);
                            $td.attr('title', 'Subtipo incompatible con el tipo de tercero');
                        }
                    });

                    // Inicializar el select de subtipo
                    initSubtipoSelect(subtypesByType, subtypesMap);

                    // Aplicar filtro activo si hay uno en la URL
                    if (currentSubtype) {
                        applySubtipoFilter(currentSubtype);
                    }
                });
            }
        );

        // ── 5. Inicializar select de subtipo ───────────────────
        function initSubtipoSelect(subtypesByType, subtypesMap) {
            renderSubtipoOptions(subtypesByType, currentTypeCode, currentSubtype);

            // Al cambiar tipo: repoblar subtipo y limpiar filtro
            $tipoSelect.on('change', function() {
                var typeCode = typeIdToCode[String($(this).val())] || '';
                renderSubtipoOptions(subtypesByType, typeCode, '');
                applySubtipoFilter(''); // mostrar todo
            });

            // Al cambiar subtipo: filtrar filas en el DOM
            $('#search_re_subtypent').on('change', function() {
                var subCode = $(this).val();
                applySubtipoFilter(subCode);
                // Actualizar URL sin recargar (para que el valor persista si recargan)
                var url = new URL(window.location.href);
                if (subCode) url.searchParams.set('search_re_subtypent', subCode);
                else url.searchParams.delete('search_re_subtypent');
                window.history.replaceState({}, '', url.toString());
            });
        }

        // ── 6. Filtrar filas por subtipo en el DOM ─────────────
        function applySubtipoFilter(subCode) {
            if (!subCode) {
                $('tr[data-rowid]').show();
                updateCounter($('tr[data-rowid]').length);
                return;
            }
            var visible = 0;
            $('tr[data-rowid]').each(function() {
                var $tr     = $(this);
                var rowCode = $tr.find('td.re-subtipo-td').data('subtype') || '';
                var match   = (subCode === '__EMPTY__') ? (rowCode === '') : (rowCode === subCode);
                if (match) { $tr.show(); visible++; }
                else        { $tr.hide(); }
            });
            updateCounter(visible);
        }

        // Actualizar el contador de resultados en el DOM
        // Dolibarr usa: <span class="...totalnboflines...">(2447)</span>
        var $counter     = $('span.totalnboflines');
        var originalCount = parseInt($counter.text().replace(/[()]/g, '')) || 0;

        function updateCounter(n) {
            if (!$counter.length) return;
            if (n === originalCount) {
                $counter.text('(' + originalCount + ')').css('color', '');
            } else {
                $counter.text('(' + n + ' de ' + originalCount + ')').css('color', '#e65c00');
            }
        }

        // Reconstruir opciones del select filtradas por tipo
        function renderSubtipoOptions(subtypesByType, typeCode, selected) {
            var selEmpty = (selected === '__EMPTY__') ? ' selected' : '';
            var opts = '<option value="">-- Subtipo --</option>'
                     + '<option value="__EMPTY__"' + selEmpty + '>— Sin subtipo —</option>';
            $.each(subtypesByType, function(tc, subs) {
                if (typeCode && tc !== typeCode) return;
                $.each(subs, function(i, sub) {
                    var sel = (sub.code === selected) ? ' selected' : '';
                    opts += '<option value="' + sub.code + '" data-type="' + tc + '"' + sel + '>' + sub.libelle + '</option>';
                });
            });
            $('#search_re_subtypent').html(opts);
        }

        return;
    }

    // =========================================================
    // CARD.PHP — salir si no corresponde
    // =========================================================
    if (phpSelf.indexOf('/societe/card.php') === -1) return;

    // ── VISTA (view) ──────────────────────────────────────────
    if (action === 'view' || action === '') {
        hideAllExtrafieldsView(socid);

        if (socid > 0) {
            // Cargar subtipos + clasificación en paralelo, inyectar cuando ambos están listos
            $.getJSON('/custom/realestatecrmfields/ajax/get_classification.php', { socid: socid })
            .done(function(classif) {
                // Defender contra respuesta vacía o inválida
                if ( !classif || typeof classif.typent_code === 'undefined' ) {
                    classif = { typent_code: '', subtypent_code: '', all_types: {} };
                }
                getSubtypes(function(subtypesByType) {

                // Construir mapa code→libelle
                $.each(subtypesByType, function(typeCode, subs) {
                    $.each(subs, function(i, sub) {
                        subtypeLabels[sub.code] = sub.libelle;
                    });
                });

                var typeCode    = classif.typent_code    || '';
                var subtypeCode = classif.subtypent_code || '';

                // Poblar subtypeToType para validación
                $.each(subtypesByType, function(tc, subs) {
                    $.each(subs, function(i, sub) { subtypeToType[sub.code] = tc; });
                });

                // Inyectar tipo en el td vacío y subtipo debajo
                injectTipoAndSubtipoView(typeCode, subtypeCode, classif.all_types || {}, subtypesByType);

                // Ocultar campos irrelevantes para Actores (RE_AOR)
                if (typeCode === 'RE_AOR') {
                    $('td').filter(function() {
                        var t = $(this).text().trim();
                        return t === 'Tipo de entidad comercial' || t === 'Capital' || t === 'Empresa padre';
                    }).closest('tr').hide();
                }

                // Marcar error si tipo y subtipo son incompatibles
                if (isMismatch(typeCode, subtypeCode)) {
                    // En vista la fila de subtipo tiene id dinámico — buscar por contenido
                    $('tr').filter(function() {
                        return $(this).find('td').first().text().trim() === 'Subtipo';
                    }).addClass('re-mismatch');
                    $('tr').filter(function() {
                        return $(this).find('td').first().text().trim() === 'Tipo';
                    }).addClass('re-mismatch');
                }

                if (typeCode) {
                    applyVisibilityView(typeCode, subtypeCode, socid);
                }

                // Inyectar secciones según tipo
                if (typeCode === 'RE_ACT' || typeCode === 'RE_AOR') {
                    var mode = (typeCode === 'RE_ACT') ? 'activo' : 'actor';

                    var $builddoc = $('#builddoc_form').closest('div.fichehalfleft');
                    var $target   = $builddoc.closest('div.fichecenter');
                    var $fallback = $('div.fiche').last();

                    // Función que carga las secciones
                    function cargarSecciones() {
                        // Sección de consultas (activos y actores)
                        $.get('/custom/realestatecrmfields/ajax/consultas_section.php',
                            { socid: socid, mode: mode },
                            function(html) {
                                if (!html) return;
                                var $wrapper = $('<div class="fichecenter re-consultas-wrapper"><div style="width:100%;padding:0 8px"></div></div>');
                                $wrapper.find('div').html(html);
                                if ($target.length) $target.after($wrapper);
                                else $fallback.append($wrapper);
                                if (typeof window.initConsultasHandlers === 'function') {
                                    window.initConsultasHandlers();
                                }

                                // Secciones adicionales solo RE_ACT
                                if (typeCode === 'RE_ACT') {
                                    // Gestión con propietario
                                    $.get('/custom/realestatecrmfields/ajax/gestion_section.php',
                                        { socid: socid },
                                        function(html2) {
                                            if (!html2) return;
                                            var $wrapper2 = $('<div class="fichecenter re-gestion-wrapper"><div style="width:100%;padding:0 8px"></div></div>');
                                            $wrapper2.find('div').html(html2);
                                            $wrapper.after($wrapper2);
                                            if (typeof window.initGestionHandlers === 'function') {
                                                window.initGestionHandlers();
                                            }

                                            // Propietarios/vinculados — carga después de gestión
                                            $.get('/custom/realestatecrmfields/ajax/propietario_section.php',
                                                { socid: socid },
                                                function(html3) {
                                                    if (!html3) return;
                                                    var $wrapper3 = $('<div class="fichecenter re-prop-wrapper"><div style="width:100%;padding:0 8px"></div></div>');
                                                    $wrapper3.find('div').html(html3);
                                                    $wrapper2.after($wrapper3);
                                                    if (typeof window.initPropietarioHandlers === 'function') {
                                                        window.initPropietarioHandlers();
                                                    }
                                                }
                                            );
                                        }
                                    );
                                }
                            }
                        );
                    }

                    // Cargar JS dinámicamente si no están disponibles, luego cargar secciones
                    function cargarScriptsYSecciones() {
                        if (typeCode === 'RE_ACT') {
                            var scripts = [];
                            if (typeof window.initGestionHandlers !== 'function')
                                scripts.push('/custom/realestatecrmfields/js/gestion.js');
                            if (typeof window.initPropietarioHandlers !== 'function')
                                scripts.push('/custom/realestatecrmfields/js/propietario.js');
                            if (scripts.length === 0) { cargarSecciones(); return; }
                            // Cargar en serie
                            (function cargarNext(idx) {
                                if (idx >= scripts.length) { cargarSecciones(); return; }
                                $.getScript(scripts[idx], function() { cargarNext(idx+1); });
                            })(0);
                        } else {
                            cargarSecciones();
                        }
                    }
                    cargarScriptsYSecciones();
                }
            }); // fin getSubtypes
            }); // fin done
        }
        return;
    }

    // ── CREATE y EDIT ─────────────────────────────────────────
    if (action !== 'create' && action !== 'edit') return;

    hideAllExtrafields();

    loadSubtypeLabels(function(subtypesByType) {
        $.getJSON('/custom/realestatecrmfields/ajax/get_classification.php', { socid: socid }, function(classif) {

            var currentType    = classif.typent_code    || '';
            var currentSubtype = classif.subtypent_code || '';
            var allTypes       = classif.all_types      || {};

            // Construir select de subtipo
            var selectHtml = '<select id="re_subtypent" name="re_subtypent" class="flat minwidth200">' +
                             buildSubtipoOptions(subtypesByType, currentSubtype) +
                             '</select>';

            var $tipoSelect = $('select#typent_id');
            if (!$tipoSelect.length) return;

            var tdClass = action === 'edit' ? 'titlefield' : 'titlefieldcreate titlefieldmax45';
            $tipoSelect.closest('tr').after(
                '<tr class="realestate-subtype-row">' +
                '<td class="' + tdClass + '">Subtipo</td>' +
                '<td colspan="3" class="maxwidthonsmartphone">' + selectHtml + '</td>' +
                '</tr>'
            );

            var $subtipo = $('#re_subtypent');

            filterSubtiposByTipo($tipoSelect.val(), allTypes, $subtipo);

            $tipoSelect.on('change', function() {
                $subtipo.val('');
                filterSubtiposByTipo($(this).val(), allTypes, $subtipo);
                hideAllExtrafields();
            });

            $subtipo.on('change', function() {
                var opt        = this.options[this.selectedIndex];
                var typeCode   = opt ? opt.getAttribute('data-type') : '';
                var subCode    = this.value;
                applyVisibilityForm(typeCode, subCode);

                // Guardar subtipo inmediatamente via AJAX (no esperar el submit)
                if (socid > 0) {
                    var fd = new URLSearchParams();
                    fd.append('socid',     socid);
                    fd.append('subtypent', subCode);
                    fd.append('token',     TOKEN);
                    fetch('/custom/realestatecrmfields/ajax/save_subtype.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: fd.toString()
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) console.warn('RealEstate: save_subtype error', data.error);
                    })
                    .catch(function(e) { console.warn('RealEstate: save_subtype fetch error', e); });
                }

                // También actualizar el input hidden para que llegue en el POST del submit
                $('#re_subtypent_hidden').val(subCode);
            });

            // Input hidden para submit (refuerzo por si el select JS no llega en POST)
            if (!$('#re_subtypent_hidden').length) {
                $tipoSelect.closest('form').append(
                    '<input type="hidden" id="re_subtypent_hidden" name="re_subtypent" value="' + currentSubtype + '">'
                );
                // Sincronizar hidden con el select
                $subtipo.on('change', function() {
                    $('#re_subtypent_hidden').val(this.value);
                });
            }

            // Si ya tiene tipo+subtipo (edit), mostrar campos y verificar mismatch
            if (currentType && currentSubtype) {
                applyVisibilityForm(currentType, currentSubtype);
            }

            // Sobrescribir el handler de "Más..." de Dolibarr
            // Dolibarr muestra los tr.trextrafields_collapse_N al hacer click en #morefieldslnk
            // Necesitamos re-aplicar la visibilidad DESPUÉS de que Dolibarr los muestre
            var $masBtn = $('#morefieldslnk');
            if ($masBtn.length && currentType && currentSubtype) {
                // Clonar el elemento para quitar todos los handlers existentes
                var $masBtnNuevo = $masBtn.clone(false);
                $masBtn.replaceWith($masBtnNuevo);

                $masBtnNuevo.on('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Ejecutar lo que Dolibarr haría: mostrar los extrafields colapsados
                    var sid = socid;
                    $('.trextrafields_collapse_' + sid).show();
                    // Cambiar el texto del link a "Menos..."
                    $(this).html('Menos<span class="badge badge-secondary socialnetworklnk marginleftonly">...</span>');
                    $(this).attr('id', 'lessfieldslnk');

                    // Re-aplicar visibilidad inmediatamente
                    applyVisibilityForm(currentType, currentSubtype);
                });
            }

            // Ocultar campos irrelevantes para Actores en edit/create
            function hideActorIrrelevantFields(typeCode) {
                var labels = ['Tipo de entidad comercial', 'Capital', 'Empresa padre'];
                if (typeCode === 'RE_AOR') {
                    $('td').filter(function() {
                        return labels.indexOf($(this).text().trim()) >= 0;
                    }).closest('tr').hide();
                } else {
                    $('td').filter(function() {
                        return labels.indexOf($(this).text().trim()) >= 0;
                    }).closest('tr').show();
                }
            }

            hideActorIrrelevantFields(currentType);
            $tipoSelect.on('change', function() {
                var opt = this.options[this.selectedIndex];
                var tCode = '';
                $.each(classif.all_types || {}, function(id, code) {
                    if (String(id) === String(opt ? opt.value : '')) { tCode = code; return false; }
                });
                hideActorIrrelevantFields(tCode);
            });

            // Marcar mismatch en edit/create al cargar y al cambiar selects
            function checkMismatchForm() {
                var tCode = '';
                var opt   = $tipoSelect[0] && $tipoSelect[0].options[$tipoSelect[0].selectedIndex];
                // Buscar el code a partir del id numérico del select
                if (opt) {
                    $.each(classif.all_types || {}, function(id, code) {
                        if (String(id) === String(opt.value)) { tCode = code; return false; }
                    });
                }
                var sCode = $subtipo.val() || '';
                var bad   = isMismatch(tCode, sCode);
                $subtipo.closest('tr').toggleClass('re-mismatch', bad);
                $tipoSelect.closest('tr').toggleClass('re-mismatch', bad);
                if (bad) {
                    $subtipo.attr('title', 'Subtipo incompatible con el tipo de tercero');
                } else {
                    $subtipo.removeAttr('title');
                }
            }

            $tipoSelect.on('change', checkMismatchForm);
            $subtipo.on('change', checkMismatchForm);
            if (currentType && currentSubtype) checkMismatchForm();
        });
    });

    // =========================================================
    // HELPERS
    // =========================================================

    function buildSubtipoOptions(subtypesByType, selected) {
        var typeLabels = {
            'RE_ACT': 'Activo Inmobiliario',
            'RE_AOR': 'Actor',
            'RE_SRV': 'Servicio'
        };
        var html = '<option value="">-- Subtipo --</option>';
        $.each(subtypesByType, function(typeCode, subs) {
            var label = typeLabels[typeCode] || typeCode;
            html += '<optgroup label="' + label + '" data-type="' + typeCode + '">';
            $.each(subs, function(i, sub) {
                var sel = (sub.code === selected) ? ' selected' : '';
                html += '<option value="' + sub.code + '" data-type="' + typeCode + '"' + sel + '>' + sub.libelle + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    function filterSubtiposByTipo(tipoId, allTypes, $subtipo) {
        var selectedCode = allTypes[String(tipoId)] || '';
        $subtipo.find('optgroup').each(function() {
            var match = !selectedCode || $(this).data('type') === selectedCode;
            $(this).prop('disabled', !match);
            $(this).find('option').prop('disabled', !match).toggle(match);
        });
        var $sel = $subtipo.find('option:selected');
        if ($sel.val() && $sel.data('type') !== selectedCode) $subtipo.val('');
    }

    // ── Ocultar todos los extrafields (create/edit) ───────────
    // create: tr.field_options_{name} o tr.societe_extras_{name}
    // edit:   tr#trextrafields_societe_{name}
    function hideAllExtrafields() {
        $('tr[class*="field_options_"], tr[class*="societe_extras_"]').hide();
        $('tr[id^="trextrafields_societe_"]').hide();
    }

    // ── Visibilidad create/edit ───────────────────────────────
    function applyVisibilityForm(typeCode, subtypeCode) {
        hideAllExtrafields();
        if (!typeCode) return;
        $.getJSON('/custom/realestatecrmfields/ajax/get_visible_fields.php',
            { typent: typeCode, subtypent: subtypeCode || '' },
            function(data) {
                if (!data.visible || !data.visible.length) return;
                $.each(data.visible, function(i, fieldName) {
                    $('tr.field_options_'  + fieldName).show();
                    $('tr.societe_extras_' + fieldName).show();
                    $('#trextrafields_societe_' + fieldName).show();
                });
            }
        );
    }

    // ── Ocultar extrafields en modo VISTA ─────────────────────
    function hideAllExtrafieldsView(sid) {
        $('tr.trextrafields_collapse_' + sid).hide();
    }

    // ── Visibilidad en modo VISTA ─────────────────────────────
    function applyVisibilityView(typeCode, subtypeCode, sid) {
        if (!typeCode) return;
        $.getJSON('/custom/realestatecrmfields/ajax/get_visible_fields.php',
            { typent: typeCode, subtypent: subtypeCode || '' },
            function(data) {
                if (!data.visible || !data.visible.length) return;
                $.each(data.visible, function(i, fieldName) {
                    $('#societe_extras_' + fieldName + '_' + sid).closest('tr').show();
                });
            }
        );
    }

    // ── Tipo y Subtipo en modo VISTA ──────────────────────────
    // Estructura HTML de Dolibarr:
    // <tr>
    //   <td class="titlefieldmiddle"><table>...<td>Tipo de Tercero</td>...🖊️...</table></td>
    //   <td>&nbsp;</td>   ← valor vacío aunque el tipo esté asignado (bug Dolibarr o campo no mostrado)
    // </tr>
    function injectTipoAndSubtipoView(typeCode, subtypeCode, allTypes, subtypesByType) {
        var typeLabels = {
            'RE_ACT': 'Activo Inmobiliario',
            'RE_AOR': 'Actor',
            'RE_SRV': 'Servicio'
        };

        // Buscar el <tr> que contiene "Tipo de Tercero" en su sub-tabla
        var $tipoRow = null;
        $('td.titlefieldmiddle').each(function() {
            if ($(this).find('td').filter(function() {
                return $.trim($(this).text()) === 'Tipo de Tercero';
            }).length) {
                $tipoRow = $(this).closest('tr');
                return false; // break
            }
        });

        if (!$tipoRow || !$tipoRow.length) {
            // Fallback: buscar por texto directo
            $tipoRow = $('td:contains("Tipo de Tercero")').closest('tr');
        }
        if (!$tipoRow || !$tipoRow.length) return;

        // Completar el valor del tipo en el <td> de valor (segundo td del tr)
        var $valorTd = $tipoRow.find('td').last();
        if (typeCode && ($valorTd.html() === '&nbsp;' || $.trim($valorTd.text()) === '')) {
            var tipoLabel = typeLabels[typeCode] || typeCode;
            $valorTd.html('<span class="re-tipo-value">' + tipoLabel + '</span>');
        }

        // Insertar fila Subtipo después del tr de Tipo
        if ($('#re-subtipo-view-row').length) return;
        var subLabel = subtypeLabels[subtypeCode] || (subtypeCode ? subtypeCode : '—');
        $tipoRow.after(
            '<tr id="re-subtipo-view-row">' +
            '<td>Subtipo</td>' +
            '<td>' + subLabel + '</td>' +
            '</tr>'
        );
    }

});


// ── Ocultar campos irrelevantes en societe/card.php ──────────────────────────
(function() {
    var LABELS_OCULTAR = [
        'iibb',
        'id iva',
        'fax',
        'tipo de entidad comercial',
        'aplica iva',
        'εstablishment date of company',   // Epsilon griega (bug Dolibarr)
        'establishment date of company',
        'capital',
        // Campos idprof nativos de Dolibarr (modo vista)
        'id profesional 1', 'id profesional 2', 'id profesional 3',
        'id profesional 4', 'id profesional 5', 'id profesional 6',
        'cuit/cuil', 'ingresos brutos', 'nro. ingresos brutos',
        'siren', 'siret', 'ape', 'tva intra', 'nif',
        // Campos de módulo que no se editan directamente en la ficha
    ];
    var INPUTS_OCULTAR = [
        'tva_assuj', 'tva_intra',
        'idprof1', 'idprof2', 'idprof3', 'idprof4', 'idprof5', 'idprof6',
        'fax', 'typent_id',
        'datec',
    ];

    function ocultarCampos() {
        // Por input name (modo edición)
        INPUTS_OCULTAR.forEach(function(name) {
            document.querySelectorAll('[name="' + name + '"], #' + name).forEach(function(el) {
                var tr = el.closest('tr');
                if (tr) tr.style.setProperty('display', 'none', 'important');
            });
        });
        // Por texto del label (modo vista)
        document.querySelectorAll('table.tableforfield tr, table.border tr').forEach(function(tr) {
            var td = tr.cells && tr.cells[0];
            if (!td) return;
            var texto = td.textContent.trim().toLowerCase();
            if (LABELS_OCULTAR.indexOf(texto) >= 0) {
                tr.style.setProperty('display', 'none', 'important');
            }
        });
    }

    // Ejecutar cuando el DOM esté listo y otra vez 300ms después
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ocultarCampos();
            setTimeout(ocultarCampos, 300);
        });
    } else {
        ocultarCampos();
        setTimeout(ocultarCampos, 300);
    }
})();

/* ============================================================
   Visibilidad de extrafields — funciones globales
   Necesarias en card.php edit mode independientemente del hook
   ============================================================ */
window.realEstateOnSubtypeChange = function(sel) {
    var opt  = sel.options[sel.selectedIndex];
    var type = opt ? opt.dataset.type : '';
    window.realEstateApplyVisibility(type, sel.value);
};

window.realEstateApplyVisibility = function(typeCode, subtypeCode) {
    // Mostrar todos los extrafields primero
    document.querySelectorAll('[id^="extrarow-societe_"]').forEach(function(el) {
        el.style.removeProperty('display');
    });
    document.querySelectorAll('[id^="trextrafieldsrow_options_"]').forEach(function(el) {
        el.style.removeProperty('display');
    });

    if (!typeCode) return;

    var token = jQuery('meta[name="anti-csrf-newtoken"]').attr('content')
             || jQuery('input[name="token"]').first().val()
             || '';

    jQuery.getJSON(
        '/custom/realestatecrmfields/ajax/get_hidden_fields.php',
        { typent: typeCode, subtypent: subtypeCode || '', token: token },
        function(data) {
            if (!data.hidden || !data.hidden.length) return;
            data.hidden.forEach(function(fieldName) {
                // Dolibarr 23 — usar setProperty con important para sobrescribir CSS de Dolibarr
                document.querySelectorAll('[id^="extrarow-societe_' + fieldName + '"]').forEach(function(el) {
                    el.style.setProperty('display', 'none', 'important');
                });
                // Dolibarr < 23
                var old = document.getElementById('trextrafieldsrow_options_' + fieldName);
                if (old) old.style.setProperty('display', 'none', 'important');
            });
        }
    );
};

/* ============================================================
   Auto-aplicar visibilidad en card.php modo edit
   ============================================================ */
jQuery(document).ready(function($) {
    var phpSelf = window.location.pathname;
    if (phpSelf.indexOf('/societe/card.php') === -1) return;
    var action = new URLSearchParams(window.location.search).get('action') || 'view';
    if (action !== 'edit' && action !== 'create') return;

    // Leer tipo actual del select nativo de Dolibarr
    var $tipo = $('select#typent_id, select[name="typent_id"]').first();
    // Leer subtipo del select inyectado por el hook
    var $subtipo = $('#re_subtypent');

    function aplicarVisibilidad() {
        var typeCode    = $tipo.val() ? '' : ''; // necesitamos el code, no el id
        var subtypeCode = $subtipo.val() || '';

        // Obtener el code del tipo desde el texto de la opción seleccionada
        // Dolibarr guarda el id numérico en typent_id, no el code
        // Leer desde data del subtipo seleccionado
        if ($subtipo.val()) {
            var opt = $subtipo.find('option:selected');
            typeCode = opt.data('type') || '';
        }

        if (typeCode && subtypeCode) {
            window.realEstateApplyVisibility(typeCode, subtypeCode);
        }
    }

    // Aplicar al cargar (300ms para que el hook inyecte el select primero)
    setTimeout(aplicarVisibilidad, 400);

    // Re-aplicar cuando cambie el subtipo
    $(document).on('change', '#re_subtypent', function() {
        var opt = $(this).find('option:selected');
        var typeCode    = opt.data('type') || '';
        var subtypeCode = $(this).val() || '';
        window.realEstateApplyVisibility(typeCode, subtypeCode);
    });

    // MutationObserver: re-aplicar visibilidad cuando Dolibarr expande "Más..."
    // Dolibarr muestra los extrafields colapsados cambiando display en los <tr>
    var observer = new MutationObserver(function(mutations) {
        var necesitaReaplicar = false;
        mutations.forEach(function(m) {
            if (m.type === 'attributes' && m.attributeName === 'style') {
                var el = m.target;
                if (el.id && el.id.indexOf('extrarow-societe_') === 0) {
                    // Dolibarr acaba de mostrar un extrafield — verificar si debería estar oculto
                    if (el.style.display !== 'none') {
                        necesitaReaplicar = true;
                    }
                }
            }
        });
        if (necesitaReaplicar) {
            setTimeout(aplicarVisibilidad, 50);
        }
    });

    // Observar todos los extrafields existentes
    document.querySelectorAll('[id^="extrarow-societe_"]').forEach(function(el) {
        observer.observe(el, { attributes: true, attributeFilter: ['style'] });
    });
});
