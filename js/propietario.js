/**
 * JS para la sección de propietarios/vinculados en card.php de Activos
 */
window.initPropietarioHandlers = function() {
    if ($('#re-propietario-section').length === 0) return;
    if ($('#re-propietario-section').data('initialized')) return;
    $('#re-propietario-section').data('initialized', true);

    var AJAX_URL = (typeof RP_AJAX_URL !== 'undefined') ? RP_AJAX_URL
                : '/custom/realestatecrmfields/ajax/propietario_save.php';
    var socid    = $('#re-propietario-section').data('socid');
    var RC_TOKEN = (typeof TOKEN !== 'undefined' ? TOKEN : null)
                || $('meta[name="anti-csrf-newtoken"]').attr('content')
                || $('input[name="token"]').first().val() || '';

    // Mover modal al body
    var $modal = $('#re-prop-modal');
    if ($modal.length) {
        if (!$modal.parent().is('body')) $modal.appendTo('body');
        $modal.addClass('re-modal-hidden').css('display','none');
    }

    function openModal(title) {
        $('#re-prop-modal-title').html('<span class="fas fa-user-tie" style="color:#0d6efd;margin-right:6px"></span>' + title);
        $('#rp-error').hide();
        $('#re-prop-modal').removeClass('re-modal-hidden').css('display','block');
    }
    function closeModal() {
        $('#re-prop-modal').addClass('re-modal-hidden').css('display','none');
        resetForm();
    }
    function resetForm() {
        $('#rp-rowid').val('');
        $('#rp-prop-id').val('');
        $('#rp-prop-found').hide().css('display','none');
        $('#rp-prop-search-wrap').show();
        $('#rp-prop-search').val('');
        $('#rp-nombre, #rp-telefono').val('');
        $('#rp-rol').val('PROPIETARIO');
        var hoy = new Date();
        var yyyy = hoy.getFullYear();
        var mm   = String(hoy.getMonth()+1).padStart(2,'0');
        var dd   = String(hoy.getDate()).padStart(2,'0');
        $('#rp-desde').val(yyyy+'-'+mm+'-'+dd);
        $('#rp-hasta').val('');
        $('#rp-nota').val('');
    }

    $(document).on('click', '#re-prop-modal-close, #re-prop-modal-cancel', closeModal);
    $(document).on('click', '#re-prop-modal', function(e) {
        if ($(e.target).is('#re-prop-modal')) closeModal();
    });

    // Botón vincular nuevo
    $(document).on('click', '.re-prop-nueva-btn', function(e) {
        e.preventDefault();
        resetForm();
        openModal('Vincular propietario');
    });

    // Botón editar
    $(document).on('click', '.re-prop-edit-btn', function(e) {
        e.preventDefault();
        var rowid = $(this).data('rowid');
        resetForm();
        $('#rp-rowid').val(rowid);
        openModal('Editar vínculo');
        $.getJSON(AJAX_URL, { action: 'get', rowid: rowid }, function(g) {
            if (!g || g.error) return;
            if (g.fk_societe_propietario) {
                $('#rp-prop-id').val(g.fk_societe_propietario);
                $('#rp-prop-found-nom').text(g.propietario_nom || '#' + g.fk_societe_propietario);
                $('#rp-prop-found').show().css('display','flex');
                $('#rp-prop-search-wrap').hide();
            } else {
                $('#rp-nombre').val(g.propietario_nombre || '');
                $('#rp-telefono').val(g.propietario_telefono || '');
            }
            $('#rp-rol').val(g.rol || 'PROPIETARIO');
            $('#rp-desde').val(g.fecha_desde || '');
            $('#rp-hasta').val(g.fecha_hasta || '');
            $('#rp-nota').val(g.nota || '');
        });
    });

    // Desvincular (fecha_hasta = hoy, activo = 0)
    $(document).on('click', '.re-prop-desvincular-btn', function(e) {
        e.preventDefault();
        if (!confirm('¿Desvincular este propietario? Se registrará como fecha de baja el día de hoy.')) return;
        var rowid = $(this).data('rowid');
        $.post(AJAX_URL, { action: 'desvincular', rowid: rowid, token: RC_TOKEN }, function(r) {
            if (r.success) location.reload();
            else console.error('Error: ' + (r.error || 'desconocido'));
        }, 'json');
    });

    // Eliminar registro histórico
    $(document).on('click', '.re-prop-delete-btn', function(e) {
        e.preventDefault();
        if (!confirm('¿Eliminar este registro del historial?')) return;
        var rowid = $(this).data('rowid');
        $.post(AJAX_URL, { action: 'delete', rowid: rowid, token: RC_TOKEN }, function(r) {
            if (r.success) location.reload();
            else console.error('Error: ' + (r.error || 'desconocido'));
        }, 'json');
    });

    // Autocomplete actor
    var propTimer;
    $(document).on('input', '#rp-prop-search', function() {
        clearTimeout(propTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#rp-prop-results').hide(); return; }
        propTimer = setTimeout(function() {
            $.getJSON(AJAX_URL, { action: 'search_actores', q: q }, function(rows) {
                var html = '';
                if (!rows.length) {
                    html = '<div style="padding:8px;color:#888">Sin resultados</div>';
                } else {
                    rows.forEach(function(r) {
                        html += '<div class="rp-actor-option" data-id="' + r.rowid + '" data-nom="' + $('<span>').text(r.nom).html() + '" style="padding:8px;cursor:pointer;border-bottom:1px solid #f0f0f0">'
                            + '<strong>' + $('<span>').text(r.nom).html() + '</strong>'
                            + (r.phone ? ' <small>· ' + $('<span>').text(r.phone).html() + '</small>' : '')
                            + (r.fk_re_subtypent ? ' <span style="font-size:.78em;color:#6c6aa8">[' + r.fk_re_subtypent + ']</span>' : '')
                            + '</div>';
                    });
                }
                $('#rp-prop-results').html(html).show();
            });
        }, 300);
    });

    $(document).on('click', '.rp-actor-option', function() {
        $('#rp-prop-id').val($(this).data('id'));
        $('#rp-prop-found-nom').text($(this).data('nom'));
        $('#rp-prop-found').show().css('display','flex');
        $('#rp-prop-search-wrap').hide();
        $('#rp-prop-results').hide();
        // Limpiar campos manuales
        $('#rp-nombre, #rp-telefono').val('');
    });

    $(document).on('click', '#rp-prop-clear', function(e) {
        e.preventDefault();
        $('#rp-prop-id').val('');
        $('#rp-prop-found').hide().css('display','none');
        $('#rp-prop-search-wrap').show();
        $('#rp-prop-search').val('').focus();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#rp-prop-search, #rp-prop-results').length) {
            $('#rp-prop-results').hide();
        }
    });

    // Submit
    $(document).on('click', '#re-prop-submit', function() {
        $('#rp-error').hide();
        var rowid    = $('#rp-rowid').val();
        var propId   = $('#rp-prop-id').val();
        var nombre   = $('#rp-nombre').val().trim();
        var telefono = $('#rp-telefono').val().trim();
        var desde    = $('#rp-desde').val();

        if (!propId && !nombre) {
            $('#rp-error').text('Seleccioná un actor o ingresá nombre.').show();
            return;
        }
        if (!desde) {
            $('#rp-error').text('La fecha de inicio es obligatoria.').show();
            return;
        }

        // Si hay nombre pero no propId, siempre crear como actor
        var crearActor = (!propId && nombre) ? 1 : 0;

        var data = {
            action:                rowid ? 'update' : 'create',
            token:                 RC_TOKEN,
            rowid:                 rowid,
            fk_societe_activo:     socid,
            fk_societe_propietario: propId,
            propietario_nombre:    nombre,
            propietario_telefono:  telefono,
            crear_actor:           crearActor,
            rol:                   $('#rp-rol').val(),
            fecha_desde:           desde,
            fecha_hasta:           $('#rp-hasta').val(),
            nota:                  $('#rp-nota').val(),
            activo:                1,
        };

        var $btn = $(this).prop('disabled', true).text('Guardando…');
        $.post(AJAX_URL, data, function(r) {
            if (r.success) {
                closeModal();
                location.reload();
            } else {
                $('#rp-error').text('Error: ' + (r.error || 'desconocido')).show();
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).text('Guardar');
        });
    });
};
