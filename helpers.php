<?php
/**
 * Helpers compartidos del módulo RealEstateCrmFields
 * Incluir al inicio de cada vista: require_once __DIR__ . '/helpers.php';
 */

/**
 * Genera la URL para ordenar por un campo, invirtiendo el orden si ya está activo.
 */
function reSort($field, $cf, $co) {
    $order = ($cf === $field && $co === 'ASC') ? 'DESC' : 'ASC';
    // Lista blanca — no propagar parámetros no controlados de $_GET
    $allowed = ['search_nom','search_subtipo','search_resultado','search_dias',
                'search_enficha','search_prioridad','search_vendedor_capt',
                'search_contel','search_tasado','search_semaforo',
                'search_phone','search_busqueda','search_vendedor_comp',
                'search_activo_sub','search_subtipo_aor'];
    $params = [];
    foreach ($allowed as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
    }
    $params['sortfield'] = $field;
    $params['sortorder'] = $order;
    $params['page']      = 0;
    return '?' . http_build_query($params);
}

/**
 * Genera el icono de ordenamiento para un encabezado de columna.
 */
function reSortIcon($field, $cf, $co) {
    if ($cf !== $field) return ' <span class="fas fa-sort opacitymedium"></span>';
    return $co === 'ASC' ? ' <span class="fas fa-sort-up"></span>' : ' <span class="fas fa-sort-down"></span>';
}

/**
 * Normaliza un teléfono argentino para usar en links wa.me y tel:
 * Devuelve el número con prefijo 54 (sin +).
 */
function reTelWa($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 10 && $digits[0] === '1') return '549' . $digits;
    if (strlen($digits) >= 10) return '54' . ltrim($digits, '0');
    return '54' . $digits;
}

/**
 * Genera los links de WhatsApp y llamada para un teléfono.
 * @param string $phone  Teléfono tal como está en la DB
 * @param bool   $solo_iconos  Si true, solo muestra los íconos sin el número
 */
function reTelLinks($phone, $solo_iconos = false) {
    if (!$phone) return '';
    $wa  = reTelWa($phone);
    $num = $solo_iconos ? '' : ' ' . htmlspecialchars($phone, ENT_QUOTES);
    return '<a href="https://wa.me/' . $wa . '" target="_blank" '
         . 'style="font-size:.8em;color:#25d366;text-decoration:none;margin-right:4px" '
         . 'title="WhatsApp">💬</a>'
         . '<a href="tel:+' . $wa . '" '
         . 'style="font-size:.8em;color:#555;text-decoration:none" '
         . 'title="Llamar">' . $num . '</a>';
}

/**
 * Obtiene el token CSRF de Dolibarr de forma compatible con v14+
 */
function getToken() {
    // currentToken() lee el token existente sin generar uno nuevo (Dolibarr 14+)
    // newToken() lo regenera e invalida el anterior — NO usar para leer
    if (function_exists('currentToken')) return currentToken();
    if (!empty($_SESSION['newtoken']))   return $_SESSION['newtoken'];
    if (!empty($_SESSION['token']))      return $_SESSION['token'];
    return '';
}

/**
 * Genera un nuevo token CSRF y lo guarda en sesión.
 * Usar solo cuando se necesita inicializar el token (primer render de página).
 */
function generateToken() {
    if (function_exists('newToken')) return newToken();
    return getToken();
}

/**
 * Abreviaciones de subtipos de activos RE_ACT
 * Fuente única — usar en captacion, compradores, actores
 */
function subAbrev($label) {
    static $map = [
        'Playa de Estacionamiento' => 'Playa Est.',
        'Estación de Servicio'     => 'Est. Serv.',
        'Edificio con Potencial'   => 'Edif. c/Pot.',
    ];
    return $map[$label] ?? $label;
}

/**
 * Devuelve el color hex para un resultado de gestión/contacto.
 */
function getResColor($resultado) {
    static $map = [
        ''               => '#dee2e6',
        'ATENDIO'        => '#6c757d',
        'NO_ATENDIO'     => '#adb5bd',
        'QUIERE_VENDER'  => '#198754',
        'NO_QUIERE'      => '#c0392b',
        'QUIERE_TASAR'   => '#0d6efd',
        'TASADO'         => '#6f42c1',
        'MAIL_ENVIADO'   => '#0dcaf0',
    ];
    return $map[$resultado] ?? '#dee2e6';
}

// ── Constantes de umbrales del semáforo ─────────────────────
// Días sin contacto antes de pasar a naranja / rojo por nivel
const UMBRAL_FICHA       = ['verde' => 60,  'naranja' => 60];
const UMBRAL_CALIENTE    = ['verde' => 7,   'naranja' => 7];
const UMBRAL_TIBIO       = ['verde' => 30,  'naranja' => 30];
const UMBRAL_PRIO_ALTA   = ['verde' => 80,  'naranja' => 180];
const UMBRAL_PRIO_MEDIA  = ['verde' => 180, 'naranja' => 360];
const UMBRAL_PRIO_BAJA   = ['verde' => 360, 'naranja' => 999];

/**
 * Devuelve los umbrales [verde, naranja] de días para un activo
 * según en_ficha, calor y prioridad calculada.
 */
function calcUmbrales($enFicha, $calor, $prioridad) {
    if ($enFicha)               return UMBRAL_FICHA;
    if ($calor === 'caliente')  return UMBRAL_CALIENTE;
    if ($calor === 'tibio')     return UMBRAL_TIBIO;
    if ($prioridad === 'alta')  return UMBRAL_PRIO_ALTA;
    if ($prioridad === 'media') return UMBRAL_PRIO_MEDIA;
    return UMBRAL_PRIO_BAJA;
}

/**
 * Devuelve el color hex del semáforo según días transcurridos.
 */
function colorSemaforo($dias, $enFicha, $calor, $prioridad) {
    if ($dias === null) return '#212529'; // nunca contactado
    $u = calcUmbrales($enFicha, $calor, $prioridad);
    if ($dias < $u['verde'])   return '#198754'; // verde
    if ($dias <= $u['naranja']) return '#fd7e14'; // naranja
    return '#c0392b'; // rojo
}


/**
 * Genera el CASE SQL del semáforo de urgencia (1=urgente, 2=atención, 3=ok).
 * Usa las mismas constantes UMBRAL_* que colorSemaforo() en PHP.
 * Así ORDER BY semaforo es consistente con los colores que ve el usuario.
 *
 * @param string $dias  Expresión SQL que devuelve días desde último contacto
 * @param string $coch  Expresión SQL para cocheras efectivas
 */
function semaforo_sql(
    $dias = 'ug.dias_desde_contacto',
    $coch = "COALESCE(NULLIF(ef.cocheras_fijas,0), ef.coch_fijas_estimadas, 0)"
) {
    // Extraer umbrales desde las constantes — única fuente de verdad
    $uFicha    = UMBRAL_FICHA;
    $uCal      = UMBRAL_CALIENTE;
    $uTibio    = UMBRAL_TIBIO;
    $uAlta     = UMBRAL_PRIO_ALTA;
    $uMedia    = UMBRAL_PRIO_MEDIA;
    $uBaja     = UMBRAL_PRIO_BAJA;

    $bc = prio_barrio_sql();

    // Helper: genera el sub-CASE de urgencia por días para un umbral dado
    $sub = function($verde, $naranja) use ($dias) {
        return "CASE WHEN $dias IS NULL OR $dias >= $naranja THEN 1
                     WHEN $dias >= $verde THEN 2
                     ELSE 3 END";
    };

    return "CASE
                WHEN COALESCE(ef.enficha,0) = 1
                     THEN " . $sub($uFicha['verde'], $uFicha['naranja']) . "
                WHEN LOWER(COALESCE(ef.prioridad_de_contacto,'')) = 'alta'
                  OR $coch >= 100
                     THEN " . $sub($uAlta['verde'], $uAlta['naranja']) . "
                WHEN LOWER(COALESCE(ef.prioridad_de_contacto,'')) = 'media'
                  OR $coch >= 60
                     THEN " . $sub($uMedia['verde'], $uMedia['naranja']) . "
                ELSE " . $sub($uBaja['verde'], $uBaja['naranja']) . "
            END";
}

// ── Modelo de prioridad ────────────────────────────────────

/**
 * Devuelve el array de prioridades por barrio.
 * Usa static cache — se inicializa una sola vez por request.
 * Fuente única de verdad para PHP y SQL.
 */
function getPrioBarrios() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [
        'Recoleta'=>100,'Belgrano'=>100,'Núñez'=>100,'Retiro'=>100,
        'Villa Devoto'=>90,
        'Caballito'=>80,'Palermo'=>80,'Parque Chas'=>80,'Puerto Madero'=>80,'Saavedra'=>80,
        'Colegiales'=>70,'Villa del Parque'=>70,'Villa Urquiza'=>70,
        'Chacarita'=>60,'Coghlan'=>60,'Flores'=>60,'Parque Patricios'=>60,'Villa Pueyrredón'=>60,
        'Boedo'=>50,'Balvanera'=>50,'Floresta'=>50,'Parque Avellaneda'=>50,
        'Parque Chacabuco'=>50,'Villa Crespo'=>50,'Villa General Mitre'=>50,'Villa Ortúzar'=>50,
        'San Telmo'=>40,'Agronomía'=>40,
        'Barracas'=>35,
        'Almagro'=>30,'San Cristóbal'=>30,'San Nicolás'=>30,'San Nicolas'=>30,'Villa Luro'=>30,
        'La Paternal'=>20,'Liniers'=>20,'Monte Castro'=>20,'Monserrat'=>20,'Montserrat'=>20,'Villa Santa Rita'=>20,
        'Constitución'=>10,'Mataderos'=>10,'Vélez Sársfield'=>10,'Versalles'=>10,
        'La Boca'=>5,'Nueva Pompeya'=>5,'Villa Lugano'=>5,'Villa Real'=>5,
        'Villa Riachuelo'=>5,'Villa Soldati'=>5,
    ];
    return $map;
}


/**
 * Genera el fragmento SQL CASE TRIM(campo) WHEN barrio THEN prio ... ELSE 0 END
 * @param string $campo  expresión SQL del campo barrio (default: TRIM(COALESCE(ef.barrio,'')))
 */
function prio_barrio_sql($campo = "TRIM(COALESCE(ef.barrio,''))") {
    $sql = "CASE $campo\n";
    foreach (getPrioBarrios() as $barrio => $prio) {
        $b = str_replace("'", "\\'", $barrio);
        $sql .= "                  WHEN '$b' THEN $prio\n";
    }
    $sql .= "                  ELSE 0 END";
    return $sql;
}

/**
 * Calcula prioridad en PHP dado cocheras y barrio (misma lógica que SQL)
 */
function calc_prioridad_php($cocheras, $barrio, $prio_explicita = null) {
    if ($prio_explicita && $prio_explicita !== '' && $prio_explicita !== 'NULL') {
        return strtolower($prio_explicita);
    }
    if ($cocheras < 30) return 'excluir';

    $mapa = getPrioBarrios();
    $barrio_norm = trim($barrio);
    $pb = $mapa[$barrio_norm] ?? 0;

    if ($pb === 0) {
        // Fallback: cache estático del mapa normalizado (sin tildes, lowercase)
        static $mapa_norm = null;
        if ($mapa_norm === null) {
            $trans = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
                      'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','ü'=>'u','Ü'=>'u'];
            $mapa_norm = [];
            foreach ($mapa as $key => $val) {
                $mapa_norm[strtolower(strtr($key, $trans))] = $val;
            }
        }
        $trans2 = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
                   'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','ü'=>'u','Ü'=>'u'];
        $pb = $mapa_norm[strtolower(strtr($barrio_norm, $trans2))] ?? 0;
    }

    $score = $cocheras * $pb / 100;
    if ($score >= 65 && $cocheras >= 85) return 'alta';
    if ($score >= 38 && $cocheras >= 60) return 'media';
    if ($cocheras >= 100)                return 'media';
    return 'baja';
}

/**
 * Genera el CASE SQL completo de prioridad_orden (1=alta,2=media,3=baja,4=excluir)
 * Prioridad explícita siempre manda; sino score calculado.
 */
function prio_orden_sql($campo_coch = "COALESCE(NULLIF(ef.cocheras_fijas,0), ef.coch_fijas_estimadas, 0)") {
    $bc = prio_barrio_sql();
    return "CASE
                       WHEN LOWER(COALESCE(ef.prioridad_de_contacto,'')) = 'alta'  THEN 1
                       WHEN LOWER(COALESCE(ef.prioridad_de_contacto,'')) = 'media' THEN 2
                       WHEN LOWER(COALESCE(ef.prioridad_de_contacto,'')) = 'baja'  THEN 3
                       WHEN $campo_coch < 30 THEN 4
                       ELSE CASE
                         WHEN ($campo_coch * $bc / 100) >= 65
                              AND $campo_coch >= 85 THEN 1
                         WHEN ($campo_coch * $bc / 100) >= 38
                              AND $campo_coch >= 60 THEN 2
                         WHEN $campo_coch >= 100 THEN 2
                         ELSE 3
                       END
                     END";
}

/**
 * Genera el fragmento de WHERE para filtrar por prioridad calculada
 */
function prio_where_sql($valor, $campo_coch = "COALESCE(NULLIF(ef.cocheras_fijas,0), ef.coch_fijas_estimadas, 0)") {
    // $valor debe llegar ya con $db->escape() del llamador
    // Validar que solo contenga valores conocidos
    $valoresValidos = ['alta', 'media', 'baja', 'excluir'];
    $valorSafe = in_array(strtolower($valor), $valoresValidos) ? strtolower($valor) : 'baja';
    $bc = prio_barrio_sql();
    return "(\n        CASE\n"
        . "          WHEN LOWER(COALESCE(ef.prioridad_de_contacto,'')) IN ('alta','media','baja')\n"
        . "               THEN LOWER(ef.prioridad_de_contacto)\n"
        . "          WHEN $campo_coch < 30 THEN 'excluir'\n"
        . "          WHEN ($campo_coch * $bc / 100) >= 65\n"
        . "               AND $campo_coch >= 85 THEN 'alta'\n"
        . "          WHEN ($campo_coch * $bc / 100) >= 38\n"
        . "               AND $campo_coch >= 60 THEN 'media'\n"
        . "          WHEN $campo_coch >= 100 THEN 'media'\n"
        . "          ELSE 'baja'\n"
        . "        END\n"
        . "    ) = '" . $valorSafe . "'";
}

