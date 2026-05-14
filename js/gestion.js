/**
 * JS para la sección de gestión con propietarios en card.php de Activos
 */
window.initGestionHandlers = function() {
    if ($('#re-gestion-section').length === 0) return;
    if ($('#re-gestion-section').data('initialized')) return;
    $('#re-gestion-section').data('initialized', true);

    var AJAX_URL = (typeof RG_AJAX_URL !== 'undefined') ? RG_AJAX_URL
                : '/custom/realestatecrmfields/ajax/gestion_save.php';
    var socid    = $('#re-gestion-section').data('socid');
    var RC_TOKEN = (typeof TOKEN !== 'undefined' ? TOKEN : null)
                || $('meta[name="anti-csrf-newtoken"]').attr('content')
                || $('input[name="token"]').first().val() || '';

    // Mover modal al body
    var $modal = $('#re-gestion-modal');
    if ($modal.length) {
        if (!$modal.parent().is('body')) $modal.appendTo('body');
        $modal.addClass('re-modal-hidden').css('display','none');
    }

    // ── Abrir/cerrar modal ───────────────────────────────────
    function openModal(title) {
        $('#re-gestion-modal-title').html('<span class="fas fa-phone" style="color:#6c6aa8;margin-right:6px"></span>' + title);
        $('#rg-error').hide();
        $('#re-gestion-modal').removeClass('re-modal-hidden').css('display','block');
    }
    function closeModal() {
        $('#re-gestion-modal').addClass('re-modal-hidden').css('display','none');
        resetForm();
    }

    $(document).on('click', '#re-gestion-modal-close, #re-gestion-modal-cancel', closeModal);
    $(document).on('click', '#re-gestion-modal', function(e) {
        if ($(e.target).is('#re-gestion-modal')) closeModal();
    });

    // ── Reset form ───────────────────────────────────────────
    function resetForm() {
        $('#rg-rowid').val('');
        $('#rg-prop-id').val('');
        $('#rg-prop-found').hide();
        $('#rg-prop-search-wrap').show();
        $('#rg-prop-search').val('');
        $('#rg-prop-nuevo-chk').prop('checked', false);
        $('#rg-prop-nuevo-fields').hide();
        $('#rg-prop-nombre, #rg-prop-telefono').val('');
        $('#rg-crear-prop-chk').prop('checked', false);
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        $('#rg-fecha').val(now.toISOString().slice(0,16));
        $('#rg-canal').val('');
        $('#rg-resultado').val('');
        $('#rg-nota').val('');
        $('#rg-vendedor').val('');
        $('#rg-rec-dias').val('');
        $('#rg-rec-fecha').val('');
        $('#rg-rec-nota').val('');
        $('#rg-rec-user').val('');
        $('#rg-rec-fecha-preview').text('');
    }

    // ── Botón nuevo contacto ─────────────────────────────────
    $(document).on('click', '.re-gestion-nueva-btn', function(e) {
        e.preventDefault();
        resetForm();
        openModal('Registrar contacto con propietario');
    });

    // ── Botón editar ─────────────────────────────────────────
    $(document).on('click', '.re-gestion-edit-btn', function(e) {
        e.preventDefault();
        var rowid = $(this).data('rowid');
        resetForm();
        $('#rg-rowid').val(rowid);
        openModal('Editar contacto');
        $.getJSON(AJAX_URL, { action: 'get', rowid: rowid }, function(g) {
            if (!g || g.error) return;
            if (g.fk_societe_propietario) {
                $('#rg-prop-id').val(g.fk_societe_propietario);
                $('#rg-prop-found-nom').text(g.propietario_nom || 'Propietario #' + g.fk_societe_propietario);
                $('#rg-prop-found').show();
                $('#rg-prop-search-wrap').hide();
            } else if (g.propietario_nombre) {
                $('#rg-prop-nuevo-chk').prop('checked', true).trigger('change');
                $('#rg-prop-nombre').val(g.propietario_nombre);
                $('#rg-prop-telefono').val(g.propietario_telefono);
            }
            if (g.fecha) {
                var d = new Date(g.fecha * 1000);
                d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                $('#rg-fecha').val(d.toISOString().slice(0,16));
            }
            $('#rg-canal').val(g.canal);
            $('#rg-resultado').val(g.resultado);
            $('#rg-nota').val(g.nota || '');
            $('#rg-vendedor').val(g.fk_user_vendedor || '');
            if (g.fecha_recordatorio) {
                $('#rg-rec-fecha').val(g.fecha_recordatorio);
                $('#rg-rec-fecha-preview').text(g.fecha_recordatorio);
            }
            $('#rg-rec-nota').val(g.nota_recordatorio || '');
            $('#rg-rec-user').val(g.fk_user_recordatorio || '');
        });
    });

    // ── Botón eliminar ───────────────────────────────────────
    $(document).on('click', '.re-gestion-del-btn', function(e) {
        e.preventDefault();
        if (!confirm('¿Eliminar este contacto?')) return;
        var rowid = $(this).data('rowid');
        var $tr   = $(this).closest('tr');
        $.post(AJAX_URL, { action: 'delete', rowid: rowid, token: RC_TOKEN }, function(r) {
            if (r.success) $tr.fadeOut(300, function() { $(this).remove(); });
            else {
                var $tr2 = arguments[0] ? $(arguments[0]).closest('tr') : null;
                var msg  = 'Error al eliminar: ' + (r.error || 'desconocido');
                if ($tr2 && $tr2.length) {
                    $tr2.find('td:last').append('<span style="color:#c0392b;font-size:.85em;margin-left:8px">' + msg + '</span>');
                } else { console.error(msg); }
            }
        }, 'json');
    });

    // ── Autocomplete propietario ─────────────────────────────
    var propTimer;
    $('#rg-prop-search').on('input', function() {
        clearTimeout(propTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#rg-prop-results').hide(); return; }
        propTimer = setTimeout(function() {
            $.getJSON(AJAX_URL, { action: 'search_propietarios', q: q }, function(rows) {
                var html = '';
                if (!rows.length) {
                    html = '<div style="padding:8px;color:#888">Sin resultados</div>';
                } else {
                    rows.forEach(function(r) {
                        html += '<div class="rg-prop-option" data-id="' + r.rowid + '" data-nom="' + $('<span>').text(r.nom).html() + '" style="padding:8px;cursor:pointer;border-bottom:1px solid #f0f0f0">'
                            + '<strong>' + $('<span>').text(r.nom).html() + '</strong>'
                            + (r.phone ? ' <small class="opacitymedium">· ' + $('<span>').text(r.phone).html() + '</small>' : '')
                            + '</div>';
                    });
                }
                $('#rg-prop-results').html(html).show();
            });
        }, 300);
    });

    $(document).on('click', '.rg-prop-option', function() {
        $('#rg-prop-id').val($(this).data('id'));
        $('#rg-prop-found-nom').text($(this).data('nom'));
        $('#rg-prop-found').show();
        $('#rg-prop-search-wrap').hide();
        $('#rg-prop-results').hide();
    });

    $('#rg-prop-clear').on('click', function(e) {
        e.preventDefault();
        $('#rg-prop-id').val('');
        $('#rg-prop-found').hide();
        $('#rg-prop-search-wrap').show();
        $('#rg-prop-search').val('').focus();
    });

    $('#rg-prop-nuevo-chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rg-prop-search-wrap').hide();
            $('#rg-prop-found').hide();
            $('#rg-prop-id').val('');
            $('#rg-prop-nuevo-fields').show();
        } else {
            $('#rg-prop-nuevo-fields').hide();
            $('#rg-prop-search-wrap').show();
        }
    });

    // ── Calcular fecha recordatorio ──────────────────────────
    $('#rg-rec-dias').on('input', function() {
        var dias = parseInt($(this).val());
        if (isNaN(dias) || dias < 1) {
            $('#rg-rec-fecha').val('');
            $('#rg-rec-fecha-preview').text('');
            return;
        }
        var d = new Date();
        d.setDate(d.getDate() + dias);
        var yyyy = d.getFullYear();
        var mm   = String(d.getMonth()+1).padStart(2,'0');
        var dd   = String(d.getDate()).padStart(2,'0');
        $('#rg-rec-fecha').val(yyyy+'-'+mm+'-'+dd);
        $('#rg-rec-fecha-preview').text('→ '+dd+'/'+mm+'/'+yyyy);
    });

    // ── Cerrar autocomplete fuera ────────────────────────────
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#rg-prop-search, #rg-prop-results').length) {
            $('#rg-prop-results').hide();
        }
    });

    // ── Submit ───────────────────────────────────────────────
    $(document).on('click', '#re-gestion-submit', function() {
        $('#rg-error').hide();
        var rowid    = $('#rg-rowid').val();
        var isNuevo  = $('#rg-prop-nuevo-chk').is(':checked');
        var nombre   = isNuevo ? $('#rg-prop-nombre').val().trim()   : '';
        var telefono = isNuevo ? $('#rg-prop-telefono').val().trim() : '';
        var crearProp = isNuevo && $('#rg-crear-prop-chk').is(':checked') ? 1 : 0;

        var data = {
            action:               rowid ? 'update' : 'create',
            token:                RC_TOKEN,
            rowid:                rowid,
            fk_societe_activo:    socid,
            fk_societe_propietario: $('#rg-prop-id').val(),
            propietario_nombre:   nombre,
            propietario_telefono: telefono,
            crear_propietario:    crearProp,
            canal:                $('#rg-canal').val(),
            resultado:            $('#rg-resultado').val(),
            nota:                 $('#rg-nota').val(),
            fk_user_vendedor:     $('#rg-vendedor').val(),
            fecha:                $('#rg-fecha').val(),
            fecha_recordatorio:   $('#rg-rec-fecha').val(),
            nota_recordatorio:    $('#rg-rec-nota').val(),
            fk_user_recordatorio: $('#rg-rec-user').val(),
        };

        var $btn = $(this).prop('disabled', true).text('Guardando…');
        $.post(AJAX_URL, data, function(r) {
            if (r.success) {
                closeModal();
                location.reload();
            } else {
                $('#rg-error').text('Error: ' + (r.error || 'desconocido')).show();
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).text('Guardar contacto');
        });
    });
};

