/**
 * JS para la sección de consultas en card.php
 * Maneja el modal, autocomplete de actores/activos, y CRUD via AJAX
 */
// Exponer como función global para llamar después de inyectar el HTML via AJAX
window.initConsultasHandlers = function() {
    if ($('#re-consultas-section').length === 0) return;
    // Evitar doble inicialización
    if ($('#re-consultas-section').data('initialized')) return;
    $('#re-consultas-section').data('initialized', true);

    // Mover modales al body y asegurar que estén ocultos
    ['#re-consulta-modal', '#re-log-modal'].forEach(function(sel) {
        var $m = $(sel);
        if ($m.length) {
            if (!$m.parent().is('body')) $m.appendTo('body');
            $m.addClass('re-modal-hidden').css('display','none');
        }
    });

    var AJAX_URL = '/custom/realestatecrmfields/ajax/consulta_save.php';
    var mode     = $('#re-consultas-section').data('mode');   // 'activo' | 'actor'
    var socid    = $('#re-consultas-section').data('socid');

    // Recargar solo la sección de consultas preservando posición de scroll
    function recargarSeccionConsultas() {
        var scrollY = window.scrollY || window.pageYOffset;
        $.get('/custom/realestatecrmfields/ajax/consultas_section.php',
            { socid: socid, mode: mode },
            function(html) {
                if (!html) return;
                var $wrapper = $('#re-consultas-section').closest('.re-consultas-wrapper');
                if ($wrapper.length) {
                    $wrapper.find('> div').html(html);
                    $('#re-consultas-section').removeData('initialized');
                    if (typeof window.initConsultasHandlers === 'function') {
                        window.initConsultasHandlers();
                    }
                    window.scrollTo(0, scrollY);
                } else {
                    location.reload(); // fallback
                }
            }
        );
    }
    // Usar TOKEN global de realestate.js si está disponible, si no leerlo del DOM
    var RC_TOKEN = (typeof TOKEN !== 'undefined' ? TOKEN : null)
                || $('meta[name="anti-csrf-newtoken"]').attr('content')
                || $('input[name="token"]').first().val() || '';

    // ── Abrir modal ─────────────────────────────────────────────
    function openModal(title) {
        $('#re-modal-title').text(title);
        $('#rc-error').hide();
        $('#re-consulta-modal').removeClass('re-modal-hidden').css('display','block');
    }

    function closeModal() {
        $('#re-consulta-modal').addClass('re-modal-hidden').css('display','none');
        resetForm();
    }

    $('#re-modal-close, #re-modal-cancel').on('click', closeModal);
    $('#re-consulta-modal').on('click', function(e) {
        if ($(e.target).is('#re-consulta-modal')) closeModal();
    });

    // ── Reset form ──────────────────────────────────────────────
    function resetForm() {
        $('#rc-rowid').val('');
        $('#rc-activo-id').val(mode === 'activo' ? socid : '');
        // En modo actor, el interesado siempre es este actor
        $('#rc-actor-id').val(mode === 'actor' ? socid : '');
        $('#rc-actor-found').hide();
        $('#rc-actor-search-wrap').show();
        $('#rc-actor-search').val('');
        $('#rc-actor-nuevo-chk').prop('checked', false);
        $('#rc-actor-nuevo-fields').hide();
        $('#rc-actor-nombre, #rc-actor-telefono').val('');
        $('#rc-crear-actor-chk').prop('checked', false);
        // Fecha ahora
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        $('#rc-fecha').val(now.toISOString().slice(0,16));
        $('#rc-canal').val('WHATSAPP');
        $('#rc-estado').val('CONSULTO');
        $('#rc-rango-min, #rc-rango-max').val('');
        $('#rc-nota, #rc-busqueda').val('');
        $('#rc-vendedor').val('');
        // Recordatorio
        $('#rc-rec-dias').val('');
        $('#rc-rec-fecha').val('');
        $('#rc-rec-nota').val('');
        $('#rc-rec-user').val('');
        $('#rc-rec-fecha-preview').text('');
        // modo actor: limpiar activo
        if (mode === 'actor') {
            $('#rc-activo-id').val('');
            $('#rc-activo-search').val('');
            $('#rc-activo-found').hide();
        }
    }

    // ── Botón nueva consulta ─────────────────────────────────────
    $(document).on('click', '.re-consulta-nueva-btn', function(e) {
        e.preventDefault();
        resetForm();
        var activoId = $(this).data('socid-activo');
        if (activoId) $('#rc-activo-id').val(activoId);
        openModal('Registrar consulta');
    });

    // ── Botón editar ────────────────────────────────────────────
    $(document).on('click', '.re-consulta-edit-btn', function(e) {
        e.preventDefault();
        var rowid = $(this).data('rowid');
        // Cargar datos desde la fila DOM
        var $tr = $(this).closest('tr');
        resetForm();
        $('#rc-rowid').val(rowid);
        openModal('Editar consulta');
        // Cargar via AJAX para tener todos los campos
        $.getJSON(AJAX_URL, { action: 'get', rowid: rowid }, function(c) {
            if (!c || c.error) return;
            fillForm(c);
        });
    });

    function fillForm(c) {
        $('#rc-rowid').val(c.rowid);
        $('#rc-activo-id').val(c.fk_societe_activo);
        if (c.fk_societe_actor) {
            $('#rc-actor-id').val(c.fk_societe_actor);
            $('#rc-actor-found-nom').text(c.actor_nom || 'Actor #' + c.fk_societe_actor);
            $('#rc-actor-found').show();
            $('#rc-actor-search-wrap').hide();
        } else if (c.actor_nombre) {
            $('#rc-actor-nuevo-chk').prop('checked', true).trigger('change');
            $('#rc-actor-nombre').val(c.actor_nombre);
            $('#rc-actor-telefono').val(c.actor_telefono);
        }
        // fecha
        if (c.date_consulta) {
            var d = new Date(c.date_consulta * 1000);
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            $('#rc-fecha').val(d.toISOString().slice(0,16));
        }
        $('#rc-canal').val(c.canal);
        $('#rc-estado').val(c.estado);
        $('#rc-rango-min').val(c.rango_usd_min || '');
        $('#rc-rango-max').val(c.rango_usd_max || '');
        $('#rc-nota').val(c.nota || '');
        $('#rc-busqueda').val(c.busqueda || '');
        $('#rc-vendedor').val(c.fk_user_vendedor || '');
        // Recordatorio
        if (c.fecha_recordatorio) {
            $('#rc-rec-fecha').val(c.fecha_recordatorio);
            $('#rc-rec-fecha-preview').text(c.fecha_recordatorio);
        }
        $('#rc-rec-nota').val(c.nota_recordatorio || '');
        $('#rc-rec-user').val(c.fk_user_recordatorio || '');
    }

    // ── Botón eliminar ───────────────────────────────────────────
    $(document).on('click', '.re-consulta-del-btn', function(e) {
        e.preventDefault();
        if (!confirm('¿Eliminar esta consulta?')) return;
        var rowid = $(this).data('rowid');
        var $tr   = $(this).closest('tr');
        $.post(AJAX_URL, { action: 'delete', rowid: rowid, token: RC_TOKEN }, function(r) {
            if (r.success) {
                $tr.fadeOut(300, function() { $(this).remove(); });
            } else {
                console.error('Error: ' + (r.error || 'desconocido'));
            }
        }, 'json');
    });

    // ── Autocomplete Actor ───────────────────────────────────────
    var actorTimer;
    $('#rc-actor-search').on('input', function() {
        clearTimeout(actorTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#rc-actor-results').hide(); return; }
        actorTimer = setTimeout(function() {
            $.getJSON(AJAX_URL, { action: 'search_actores', q: q }, function(rows) {
                var html = '';
                if (!rows.length) {
                    html = '<div style="padding:8px;color:#888">Sin resultados</div>';
                } else {
                    rows.forEach(function(r) {
                        html += '<div class="rc-actor-option" data-id="' + r.rowid + '" data-nom="' + $('<span>').text(r.nom).html() + '" style="padding:8px;cursor:pointer;border-bottom:1px solid #f0f0f0">' +
                            '<strong>' + $('<span>').text(r.nom).html() + '</strong>' +
                            (r.phone ? ' <small class="opacitymedium">· ' + $('<span>').text(r.phone).html() + '</small>' : '') +
                            '</div>';
                    });
                }
                $('#rc-actor-results').html(html).show();
            });
        }, 300);
    });

    $(document).on('click', '.rc-actor-option', function() {
        var id  = $(this).data('id');
        var nom = $(this).data('nom');
        $('#rc-actor-id').val(id);
        $('#rc-actor-found-nom').text(nom);
        $('#rc-actor-found').show();
        $('#rc-actor-search-wrap').hide();
        $('#rc-actor-results').hide();
    });

    $('#rc-actor-clear').on('click', function(e) {
        e.preventDefault();
        $('#rc-actor-id').val('');
        $('#rc-actor-found').hide();
        $('#rc-actor-search-wrap').show();
        $('#rc-actor-search').val('').focus();
    });

    // ── Toggle "nuevo interesado" ────────────────────────────────
    $('#rc-actor-nuevo-chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rc-actor-search-wrap').hide();
            $('#rc-actor-found').hide();
            $('#rc-actor-id').val('');
            $('#rc-actor-nuevo-fields').show();
        } else {
            $('#rc-actor-nuevo-fields').hide();
            $('#rc-actor-search-wrap').show();
        }
    });

    // ── Autocomplete Activo (modo actor) ─────────────────────────
    if (mode === 'actor') {
        var activoTimer;
        $('#rc-activo-search').on('input', function() {
            clearTimeout(activoTimer);
            var q = $(this).val().trim();
            if (q.length < 2) { $('#rc-activo-results').hide(); return; }
            activoTimer = setTimeout(function() {
                $.getJSON(AJAX_URL, { action: 'search_activos', q: q }, function(rows) {
                    var html = '';
                    if (!rows.length) {
                        html = '<div style="padding:8px;color:#888">Sin resultados</div>';
                    } else {
                        rows.forEach(function(r) {
                            var dir = r.calle ? (r.calle + (r.numero ? ' ' + r.numero : '')) : '';
                            html += '<div class="rc-activo-option" data-id="' + r.rowid + '" data-nom="' + $('<span>').text(r.nom).html() + '" style="padding:8px;cursor:pointer;border-bottom:1px solid #f0f0f0">' +
                                '<strong>' + $('<span>').text(r.nom).html() + '</strong>' +
                                (dir ? ' <small class="opacitymedium">· ' + $('<span>').text(dir).html() + '</small>' : '') +
                                '</div>';
                        });
                    }
                    $('#rc-activo-results').html(html).show();
                });
            }, 300);
        });

        $(document).on('click', '.rc-activo-option', function() {
            var id  = $(this).data('id');
            var nom = $(this).data('nom');
            $('#rc-activo-id').val(id);
            $('#rc-activo-found-nom').text(nom);
            $('#rc-activo-found').show();
            $('#rc-activo-search').hide();
            $('#rc-activo-results').hide();
        });

        $('#rc-activo-clear').on('click', function(e) {
            e.preventDefault();
            $('#rc-activo-id').val('');
            $('#rc-activo-found').hide();
            $('#rc-activo-search').show().val('').focus();
        });
    }

    // ── Submit ───────────────────────────────────────────────────
    $('#re-consulta-submit').on('click', function() {
        $('#rc-error').hide();

        var activoId = $('#rc-activo-id').val();
        // Propiedad no obligatoria — puede registrarse sin propiedad específica
        // En modo actor, asegurar que el actor (socid) viaja como fk_societe_actor
        if (mode === 'actor' && !$('#rc-actor-id').val()) {
            $('#rc-actor-id').val(socid);
        }

        var rowid    = $('#rc-rowid').val();
        var isNuevo  = $('#rc-actor-nuevo-chk').is(':checked');
        var nombre   = isNuevo ? $('#rc-actor-nombre').val().trim() : '';
        var telefono = isNuevo ? $('#rc-actor-telefono').val().trim() : '';
        var crearActor = isNuevo && $('#rc-crear-actor-chk').is(':checked') ? 1 : 0;

        var data = {
            action:           rowid ? 'update' : 'create',
            token:            RC_TOKEN,
            rowid:            rowid,
            fk_societe_activo: activoId,
            fk_societe_actor:  $('#rc-actor-id').val(),
            actor_nombre:     nombre,
            actor_telefono:   telefono,
            crear_actor:      crearActor,
            canal:            $('#rc-canal').val(),
            estado:           $('#rc-estado').val(),
            rango_usd_min:    $('#rc-rango-min').val(),
            rango_usd_max:    $('#rc-rango-max').val(),
            nota:             $('#rc-nota').val(),
            busqueda:         $('#rc-busqueda').val(),
            fk_user_vendedor:     $('#rc-vendedor').val(),
            date_consulta:        $('#rc-fecha').val(),
            fecha_recordatorio:   $('#rc-rec-fecha').val(),
            nota_recordatorio:    $('#rc-rec-nota').val(),
            fk_user_recordatorio: $('#rc-rec-user').val(),
        };

        var $btn = $(this).prop('disabled', true).text('Guardando…');

        $.post(AJAX_URL, data, function(r) {
            if (r.success) {
                closeModal();
                recargarSeccionConsultas(); // recargar solo la sección
            } else {
                $('#rc-error').text('Error: ' + (r.error || 'desconocido')).show();
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).text('Guardar consulta');
        });
    });

    // ── Recordatorio: calcular fecha al ingresar días ────────────
    $('#rc-rec-dias').on('input', function() {
        var dias = parseInt($(this).val());
        if (isNaN(dias) || dias < 1) {
            $('#rc-rec-fecha').val('');
            $('#rc-rec-fecha-preview').text('');
            return;
        }
        var d = new Date();
        d.setDate(d.getDate() + dias);
        var yyyy = d.getFullYear();
        var mm   = String(d.getMonth()+1).padStart(2,'0');
        var dd   = String(d.getDate()).padStart(2,'0');
        var fechaStr = yyyy + '-' + mm + '-' + dd;
        $('#rc-rec-fecha').val(fechaStr);
        $('#rc-rec-fecha-preview').text('→ ' + dd + '/' + mm + '/' + yyyy);
    });

    // ── Cerrar autocompletes al hacer click fuera ────────────────
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#rc-actor-search, #rc-actor-results').length) {
            $('#rc-actor-results').hide();
        }
        if (!$(e.target).closest('#rc-activo-search, #rc-activo-results').length) {
            $('#rc-activo-results').hide();
        }
    });

    // ── Log de seguimiento ───────────────────────────────────────

    var estLabels = {
        'CONSULTO': 'Consultó', 'VISITO': 'Visitó',
        'OFRECIO': 'Ofreció',   'CERRO': 'Cerró'
    };
    var estColors = {
        'CONSULTO': '#6c757d', 'VISITO': '#0d6efd',
        'OFRECIO': '#fd7e14',  'CERRO': '#198754'
    };

    function estBadge(code) {
        if (!code) return '';
        var c = estColors[code] || '#6c757d';
        var l = estLabels[code] || code;
        return '<span style="background:' + c + ';color:#fff;padding:1px 7px;border-radius:4px;font-size:.8em">' + l + '</span>';
    }

    function renderLogEntry(e) {
        var estadoCambio = '';
        if (e.estado_nuevo && e.estado_nuevo !== e.estado_anterior) {
            estadoCambio = '<span style="margin:0 4px;font-size:.85em">'
                + estBadge(e.estado_anterior) + ' → ' + estBadge(e.estado_nuevo)
                + '</span>';
        }
        return '<div class="re-log-entry" data-logid="' + e.rowid + '" '
            + 'style="padding:8px 0;border-bottom:1px solid #e8e8e8;display:flex;gap:10px;align-items:flex-start">'
            + '<div style="min-width:110px;color:#888;font-size:.82em;padding-top:2px">'
            + e.date_log + '<br><em>' + $('<span>').text(e.user_nom || '').html() + '</em></div>'
            + '<div style="flex:1">'
            + (e.nota ? '<div style="margin-bottom:4px">' + $('<span>').text(e.nota).html().replace(/\n/g,'<br>') + '</div>' : '')
            + estadoCambio
            + '</div>'
            + '<div><a href="#" class="re-log-del-btn opacitymedium" data-logid="' + e.rowid + '" title="Eliminar">'
            + '<span class="fas fa-times" style="font-size:.8em"></span></a></div>'
            + '</div>';
    }

    // Toggle panel de historial
    $(document).on('click', '.re-log-toggle-btn', function(e) {
        e.preventDefault();
        var rowid  = $(this).data('rowid');
        var $panel = $('#re-log-panel-' + rowid);
        if ($panel.is(':visible')) {
            $panel.hide();
            return;
        }
        $panel.show();
        var $entries = $('#re-log-entries-' + rowid);
        // Cargar solo si no fue cargado antes
        if ($entries.data('loaded')) return;
        $.getJSON(AJAX_URL, { action: 'log_list', fk_consulta: rowid }, function(logs) {
            $entries.data('loaded', true);
            if (!logs.length) {
                $entries.html('<span class="opacitymedium" style="font-size:.85em">Sin seguimientos registrados todavía.</span>');
                return;
            }
            var html = '';
            logs.forEach(function(e) { html += renderLogEntry(e); });
            $entries.html(html);
        });
    });

    // Abrir modal de nuevo seguimiento
    $(document).on('click', '.re-log-add-btn', function(e) {
        e.preventDefault();
        var rowid  = $(this).data('rowid');
        var estado = $(this).data('estado') || '';
        $('#re-log-fk-consulta').val(rowid);
        $('#re-log-nota').val('');
        $('#re-log-estado').val('');
        $('#re-log-motivo-cierre').val('');
        $('#re-log-cierre-wrap').hide();
        // Fecha cierre por defecto = hoy
        var hoy = new Date();
        $('#re-log-fecha-cierre').val(hoy.toISOString().slice(0,10));
        $('#re-log-estado-actual').text(estado ? 'Estado actual: ' + (estLabels[estado] || estado) : '');
        $('#re-log-error').hide();
        $('#re-log-modal').removeClass('re-modal-hidden').css('display','block');
        setTimeout(function() { $('#re-log-nota').focus(); }, 200);
    });

    // Mostrar/ocultar bloque de cierre según estado elegido
    $(document).on('change', '#re-log-estado', function() {
        if ($(this).val() === 'CERRO') {
            $('#re-log-cierre-wrap').slideDown(150);
        } else {
            $('#re-log-cierre-wrap').slideUp(150);
        }
    });

    // Usar $(document).on para garantizar que funciona aunque el HTML se inyecte via AJAX
    $(document).on('click', '#re-log-modal-close, #re-log-modal-cancel', function() {
        $('#re-log-modal').addClass('re-modal-hidden').css('display','none');
    });
    $(document).on('click', '#re-log-modal', function(e) {
        if ($(e.target).is('#re-log-modal')) $('#re-log-modal').addClass('re-modal-hidden').css('display','none');
    });

    // Guardar seguimiento
    $(document).on('click', '#re-log-submit', function() {
        var fkConsulta   = $('#re-log-fk-consulta').val();
        var nota         = $('#re-log-nota').val().trim();
        var estadoNuevo  = $('#re-log-estado').val();
        var motivoCierre = (estadoNuevo === 'CERRO') ? $('#re-log-motivo-cierre').val() : '';
        var fechaCierre  = (estadoNuevo === 'CERRO') ? $('#re-log-fecha-cierre').val() : '';

        if (!nota && !estadoNuevo) {
            $('#re-log-error').text('Ingresá una nota o seleccioná un cambio de estado.').show();
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Guardando…');

        $.post(AJAX_URL, {
            action:        'log_add',
            fk_consulta:   fkConsulta,
            nota:          nota,
            estado_nuevo:  estadoNuevo,
            motivo_cierre: motivoCierre,
            fecha_cierre:  fechaCierre,
            token:         RC_TOKEN,
        }, function(r) {
            if (!r || !r.success) {
                $('#re-log-error').text('Error: ' + (r && r.error ? r.error : 'respuesta inválida')).show();
                return;
            }
            $('#re-log-modal').addClass('re-modal-hidden').css('display','none');

            // Agregar entrada al panel si está abierto
            var $entries = $('#re-log-entries-' + fkConsulta);
            var placeholder = $entries.find('.opacitymedium');
            if (placeholder.length) placeholder.remove();
            $entries.data('loaded', true);
            $entries.append(renderLogEntry(r));

            // Abrir el panel si estaba cerrado
            var $panel = $('#re-log-panel-' + fkConsulta);
            if (!$panel.is(':visible')) $panel.show();

            // Actualizar badge de conteo
            var $badge = $('.re-log-count[data-rowid="' + fkConsulta + '"]');
            if ($badge.length) {
                $badge.text(parseInt($badge.text()) + 1);
            } else {
                // Crear el badge si no existía (primera entrada)
                $('.re-log-toggle-btn[data-rowid="' + fkConsulta + '"] span.fas')
                    .after('<span class="re-log-count" data-rowid="' + fkConsulta + '" ' +
                           'style="background:#6c6aa8;color:#fff;border-radius:10px;padding:0 5px;font-size:.75em;margin-left:2px;vertical-align:middle">1</span>');
            }

            // Actualizar badge de estado en la fila si cambió
            if (r.estado_changed && r.estado_nuevo) {
                var $tr = $('tr.re-consulta-row[data-rowid="' + fkConsulta + '"]');
                $tr.find('.re-log-add-btn').data('estado', r.estado_nuevo);
                $tr.find('[style*="border-radius:4px"]').first()
                   .css('background', estColors[r.estado_nuevo] || '#6c757d')
                   .text(estLabels[r.estado_nuevo] || r.estado_nuevo);
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).text('Guardar seguimiento');
        });
    });

    // Eliminar entrada de log
    $(document).on('click', '.re-log-del-btn', function(e) {
        e.preventDefault();
        if (!confirm('¿Eliminar esta entrada del historial?')) return;
        var logid = $(this).data('logid');
        var $entry = $(this).closest('.re-log-entry');
        $.post(AJAX_URL, { action: 'log_delete', rowid: logid, token: RC_TOKEN }, function(r) {
            if (r.success) $entry.fadeOut(200, function() { $(this).remove(); });
            else console.error('Error: ' + (r.error || 'desconocido'));
        }, 'json');
    });
};