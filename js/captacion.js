/**
 * captacion.js — lógica de UI para captacion.php
 * Depende de window.RE_CAPT (inyectado por PHP antes de cargar este archivo)
 */
$(function() {
    var TOKEN    = window.RE_CAPT.TOKEN;
    var AJAX_URL = window.RE_CAPT.AJAX_URL;

    var $modal = $('#capt-modal');

    // Autocomplete propietario
    var propTimer;
    $(document).on('input', '#capt-prop-search', function() {
        clearTimeout(propTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#capt-prop-results').hide(); return; }
        propTimer = setTimeout(function() {
            $.getJSON('/custom/realestatecrmfields/ajax/propietario_save.php',
                { action: 'search_actores', q: q },
                function(rows) {
                    var html = '';
                    if (!rows.length) {
                        html = '<div style="padding:8px;color:#888">Sin resultados</div>';
                    } else {
                        rows.forEach(function(r) {
                            html += '<div class="capt-prop-option" data-id="'+r.rowid+'"'
                                  + ' data-nom="'+$('<span>').text(r.nom).html()+'"'
                                  + ' data-tel="'+$('<span>').text(r.phone||'').html()+'"'
                                  + ' style="padding:8px;cursor:pointer;border-bottom:1px solid #f0f0f0">'
                                  + '<strong>'+$('<span>').text(r.nom).html()+'</strong>'
                                  + (r.phone ? ' <small class="opacitymedium">· '+$('<span>').text(r.phone).html()+'</small>' : '')
                                  + (r.fk_re_subtypent ? ' <span style="font-size:.75em;color:#6c6aa8">['+r.fk_re_subtypent+']</span>' : '')
                                  + '</div>';
                        });
                    }
                    $('#capt-prop-results').html(html).show();
                }
            );
        }, 300);
    });

    $(document).on('click', '.capt-prop-option', function() {
        $('#capt-prop-id').val($(this).data('id'));
        $('#capt-prop-found-nom').text($(this).data('nom'));
        $('#capt-prop-found-tel').text($(this).data('tel') || '');
        $('#capt-prop-found').show().css('display','flex');
        $('#capt-prop-search-wrap').hide();
        $('#capt-prop-results').hide();
        $('#capt-prop-nuevo-chk').prop('checked', false).trigger('change');
    });

    $(document).on('click', '#capt-prop-clear', function(e) {
        e.preventDefault();
        $('#capt-prop-id').val('');
        $('#capt-prop-found').hide().css('display','none');
        $('#capt-prop-search-wrap').show();
        $('#capt-prop-search').val('').focus();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#capt-prop-search, #capt-prop-results').length)
            $('#capt-prop-results').hide();
    });

    $('#capt-prop-nuevo-chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#capt-prop-search-wrap').hide();
            $('#capt-prop-found').hide().css('display','none');
            $('#capt-prop-id').val('');
            $('#capt-prop-nuevo-fields').show();
        } else {
            $('#capt-prop-nuevo-fields').hide();
            $('#capt-prop-search-wrap').show();
        }
    });

    // Calcular próximo contacto automático según calor, en_ficha y prioridad
    function calcRecordatorio(calor, enficha, prioridad) {
        var dias;
        calor    = (calor    || '').toLowerCase();
        prioridad= (prioridad|| '').toLowerCase();
        if (enficha)              dias = 60;
        else if (calor==='caliente') dias = 7;
        else if (calor==='tibio')    dias = 30;
        else if (prioridad==='alta') dias = 80;
        else if (prioridad==='media')dias = 180;
        else                         dias = 360;
        return dias;
    }

    function mostrarRecAuto(dias, fuente) {
        var dt = new Date(); dt.setDate(dt.getDate() + dias);
        var dd = String(dt.getDate()).padStart(2,'0');
        var mm = String(dt.getMonth()+1).padStart(2,'0');
        var yyyy = dt.getFullYear();
        $('#capt-rec-dias').val(dias);
        $('#capt-rec-preview').text('→ ' + dd + '/' + mm + '/' + yyyy);
        $('#capt-rec-auto-txt').text('Próximo contacto calculado: ' + dias + ' días (' + dd+'/'+mm+'/'+yyyy + ') · ' + fuente);
        $('#capt-rec-auto').show();
    }

    // Recalcular al cambiar calor o interés
    // Mostrar campo tasación solo si resultado = TASADO
    $(document).on('change', '#capt-resultado', function() {
        if ($(this).val() === 'TASADO') {
            $('#capt-tasacion-wrap').slideDown(150);
            $('#capt-tasacion').focus();
        } else {
            $('#capt-tasacion-wrap').slideUp(150);
        }
    });
    // Formatear preview al escribir
    $(document).on('input', '#capt-tasacion', function() {
        var v = parseInt($(this).val());
        if (!isNaN(v) && v > 0) {
            $('#capt-tasacion-preview').text('→ ' + v.toLocaleString('es-AR'));
        } else {
            $('#capt-tasacion-preview').text('');
        }
    });

    $(document).on('change', '#capt-calor, #capt-interes', function() {
        var calor     = $('#capt-calor').val()   || $modal.data('calor')    || '';
        var enficha   = $modal.data('enficha')   || 0;
        var prioridad = $modal.data('prioridad') || '';
        var interes   = $('#capt-interes').val();

        // Ajustar calor automáticamente según interés
        var calorf = calor;
        if (interes === 'SI'      && !calor) calorf = 'CALIENTE';
        if (interes === 'TAL_VEZ' && !calor) calorf = 'TIBIO';
        if (interes === 'NO'      && !calor) calorf = 'FRIO';

        var sinContacto2 = $modal.data('sin-contacto') || 0;
        var dias, fuente;
        if (sinContacto2) {
            dias   = 1;
            fuente = 'Sin contacto previo — primer contacto';
        } else {
            dias   = calcRecordatorio(calorf, enficha, prioridad);
            fuente = enficha ? 'En Ficha (mín. 60d)'
                   : calorf  ? 'Calor: ' + calorf
                   :           'Prioridad: ' + prioridad;
        }
        mostrarRecAuto(dias, fuente);
    });

    $(document).on('click', '.capt-contacto-btn', function(e) {
        e.preventDefault();
        var $btn    = $(this);
        var propId  = $btn.data('prop-id')  || '';
        var propNom = $btn.data('prop-nom') || '';
        var calor   = $btn.data('calor')    || '';
        var interes = $btn.data('interes')  || '';
        var enficha = $btn.data('enficha')  || 0;
        var prio    = $btn.data('prioridad')|| '';

        $modal.data('calor',    calor);
        $modal.data('enficha',  enficha);
        $modal.data('prioridad',prio);
        $modal.data('sin-contacto', $btn.data('sin-contacto') || 0);

        $('#capt-nom').text($btn.data('nom'));
        $('#capt-activo-id').val($btn.data('socid'));
        $('#capt-prop-id').val(propId);
        $('#capt-prop-search').val('');
        $('#capt-prop-results').hide();
        $('#capt-prop-nuevo-chk').prop('checked', false);
        $('#capt-prop-nuevo-fields').hide();
        $('#capt-prop-nombre, #capt-prop-telefono').val('');

        if (propId && propNom) {
            $('#capt-prop-found-nom').text(propNom);
            $('#capt-prop-found-tel').text('');
            $('#capt-prop-found').show().css('display','flex');
            $('#capt-prop-search-wrap').hide();
        } else {
            $('#capt-prop-found').hide().css('display','none');
            $('#capt-prop-search-wrap').show();
        }

        $('#capt-canal').val('');
        $('#capt-resultado').val('');
        $('#capt-nota').val('');
        $('#capt-tasacion').val(''); $('#capt-tasacion-preview').text(''); $('#capt-tasacion-wrap').hide();
        $('#capt-vendedor').val('');
        $('#capt-rec-auto').hide();

        // Pre-seleccionar calor e interés actuales
        $('#capt-calor').val(calor ? calor.toUpperCase() : '');
        $('#capt-interes').val(interes || '');

        // Calcular recordatorio automático
        var sinContacto = $btn.data('sin-contacto') || 0;
        var dias, fuente;
        if (sinContacto) {
            dias   = 1;
            fuente = 'Sin contacto previo — primer contacto';
        } else {
            dias   = calcRecordatorio(calor, enficha, prio);
            fuente = enficha ? 'En Ficha (mín. 60d)'
                   : calor   ? 'Calor: ' + calor
                   :           'Prioridad: ' + prio;
        }
        mostrarRecAuto(dias, fuente);

        $('#capt-error').hide();
        $modal.css('display','block');
        setTimeout(function(){ $('#capt-nota').focus(); }, 150);
    });

    $('#capt-close, #capt-cancel').on('click', function() { $modal.css('display','none'); });
    $modal.on('click', function(e) { if ($(e.target).is($modal)) $modal.css('display','none'); });

    $('#capt-rec-dias').on('input', function() {
        var d = parseInt($(this).val());
        if (isNaN(d)||d<1) { $('#capt-rec-preview').text(''); return; }
        var dt = new Date(); dt.setDate(dt.getDate()+d);
        $('#capt-rec-preview').text('→ '+String(dt.getDate()).padStart(2,'0')+'/'+String(dt.getMonth()+1).padStart(2,'0'));
    });

    $('#capt-submit').on('click', function() {
        var activoId = $('#capt-activo-id').val();
        var nota     = $('#capt-nota').val().trim();
        var dias     = parseInt($('#capt-rec-dias').val());

        var recFecha = '';
        if (!isNaN(dias) && dias > 0) {
            var dt = new Date(); dt.setDate(dt.getDate()+dias);
            recFecha = dt.getFullYear()+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+String(dt.getDate()).padStart(2,'0');
        }

        var now = new Date();
        now.setMinutes(now.getMinutes()-now.getTimezoneOffset());

        var isNuevo      = $('#capt-prop-nuevo-chk').is(':checked');
        var propNombre   = isNuevo ? $('#capt-prop-nombre').val().trim() : '';
        var propTelefono = isNuevo ? $('#capt-prop-telefono').val().trim() : '';
        var crearProp    = isNuevo ? 1 : 0;
        var nuevoInteres = $('#capt-interes').val();

        var $btn = $(this).prop('disabled',true).text('Guardando…');

        // Actualizar extrafields interes_venta si cambió
        var nuevoTasacion = $('#capt-tasacion').val().trim();
        var updatePromise = $.Deferred().resolve();
        if (nuevoInteres || nuevoTasacion) {
            updatePromise = $.post('/custom/realestatecrmfields/ajax/update_extrafields.php', {
                token:         TOKEN,
                socid:         activoId,
                interes_venta: nuevoInteres,
                usdtasacion:   nuevoTasacion,
            });
        }

        updatePromise.always(function() {
        $.post(AJAX_URL, {
            action:                  'create',
            token:                   TOKEN,
            fk_societe_activo:       activoId,
            fk_societe_propietario:  $('#capt-prop-id').val(),
            propietario_nombre:      propNombre,
            propietario_telefono:    propTelefono,
            crear_propietario:       crearProp,
            canal:                   $('#capt-canal').val(),
            resultado:               $('#capt-resultado').val(),
            nota:                    nota,
            fk_user_vendedor:        $('#capt-vendedor').val(),
            fecha:                   now.toISOString().slice(0,16),
            fecha_recordatorio:      recFecha,
        }, function(r) {
            if (r && r.success) {
                $modal.css('display','none');
                showToast('Contacto guardado ✓', 'success');
                setTimeout(function() { location.reload(); }, 900);
            } else {
                $('#capt-error').text('Error: '+(r&&r.error?r.error:'desconocido')).show();
            }
        }, 'json').always(function() { $btn.prop('disabled',false).text('Guardar contacto'); });
        }); // fin updatePromise
    });

    $('#captForm input[type=text], #captForm input[type=number]').on('keydown', function(e) {
        if (e.key==='Enter') { e.preventDefault(); $(this).closest('form').submit(); }
    });
});

function showToast(msg, type) {
    var bg = type === 'success' ? '#198754' : '#c0392b';
    var $t = $('<div>').text(msg).css({
        position:'fixed', bottom:'28px', right:'28px', zIndex:99999,
        background:bg, color:'#fff', padding:'10px 22px',
        borderRadius:'6px', fontSize:'.95em', boxShadow:'0 4px 16px rgba(0,0,0,.2)',
        opacity:0, transition:'opacity .2s'
    }).appendTo('body');
    setTimeout(function(){ $t.css('opacity',1); }, 10);
    setTimeout(function(){ $t.css('opacity',0); setTimeout(function(){ $t.remove(); },300); }, 2200);
}
