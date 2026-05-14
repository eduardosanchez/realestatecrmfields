<?php
/**
 * Generador de PDF - Ficha A5 para Activos Inmobiliarios
 * ====================================================== 
 */

// Configuración básica de Dolibarr
$config_root = str_replace('/custom/realestatecrmfields/ajax', '', dirname(__FILE__));
if (!file_exists($config_root . '/master.inc.php')) {
    $config_root = $_SERVER['DOCUMENT_ROOT'] . '/dolibarr';
}
require_once $config_root . '/master.inc.php';

// Verificación de token CSRF
$token = GETPOST('token', 'alpha');
if (!checkCSRFToken($token)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

// Solo POST por seguridad
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$socid = (int)GETPOST('socid', 'int');
if ($socid <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID de tercero inválido']);
    exit;
}

// Verificar que sea un activo inmobiliario
$sql = "SELECT s.rowid, s.nom as nombre, s.name_alias, s.address, s.zip, s.town, 
               s.phone, s.email, s.url, s.note_public, s.note_private,
               t.code as tipo_code, t.libelle as tipo_label,
               st.code as subtipo_code, st.libelle as subtipo_label
        FROM " . MAIN_DB_PREFIX . "societe s
        INNER JOIN " . MAIN_DB_PREFIX . "c_typent t ON t.id = s.fk_typent
        LEFT JOIN " . MAIN_DB_PREFIX . "c_re_subtypent st ON st.code = s.fk_re_subtypent
        WHERE s.rowid = " . $socid . " AND t.code = 'RE_ACT'";

$result = $db->query($sql);
if (!$result || !$db->num_rows($result)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Activo inmobiliario no encontrado']);
    exit;
}

$activo = $db->fetch_object($result);

try {
    // Cargar datos extras del activo
    $sqlExtras = "SELECT * FROM " . MAIN_DB_PREFIX . "societe_extrafields WHERE fk_object = " . $socid;
    $resExtras = $db->query($sqlExtras);
    $extras = $resExtras ? $db->fetch_assoc($resExtras) : [];

    // Generar PDF usando mPDF
    require_once DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php';
    
    // Configuración del PDF
    $pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
    
    // Configuración del documento
    $pdf->SetCreator('Dolibarr CRM Real Estate');
    $pdf->SetAuthor($conf->global->MAIN_INFO_SOCIETE_NOM ?? 'Real Estate CRM');
    $pdf->SetTitle('Ficha Activo: ' . $activo->nombre);
    $pdf->SetSubject('Ficha de Activo Inmobiliario A5');
    $pdf->SetKeywords('inmobiliario, activo, ficha, propiedades');

    // Configurar márgenes para A5
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 15);

    // Configurar fuente
    $pdf->SetFont('helvetica', '', 9);

    // Agregar página
    $pdf->AddPage();

    // HTML del contenido
    $html = generateFichaHTML($activo, $extras);
    
    // Escribir HTML
    $pdf->writeHTML($html, true, false, true, false, '');

    // Configurar nombre del archivo
    $filename = 'ficha_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $activo->nombre) . '_' . date('Ymd_His') . '.pdf';
    $filepath = $conf->realestatecrmfields->dir_output . '/fichas/' . $filename;

    // Crear directorio si no existe
    if (!file_exists(dirname($filepath))) {
        dol_mkdir(dirname($filepath));
    }

    // Guardar archivo
    $pdf->Output($filepath, 'F');

    // Verificar que se creó correctamente
    if (!file_exists($filepath)) {
        throw new Exception('Error al crear el archivo PDF');
    }
    
    // URL para descargar
    $pdf_url = DOL_URL_ROOT . '/document.php?modulepart=realestatecrmfields_fichas&file=' . urlencode($filename);

    // Respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'pdf_url' => $pdf_url,
        'filename' => $filename,
        'message' => 'PDF generado correctamente'
    ]);

} catch (Exception $e) {
    // Log del error
    dol_syslog('Error generando ficha PDF para socid=' . $socid . ': ' . $e->getMessage(), LOG_ERR);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}

/**
 * Genera el HTML para la ficha del activo
 */
function generateFichaHTML($activo, $extras) {
    global $conf, $langs;

    $logoUrl = '';
    if (!empty($conf->global->MAIN_INFO_SOCIETE_LOGO)) {
        $logoPath = $conf->mycompany->dir_output . '/logos/' . $conf->global->MAIN_INFO_SOCIETE_LOGO;
        if (file_exists($logoPath)) {
            $logoUrl = DOL_URL_ROOT . '/viewimage.php?modulepart=mycompany&file=' . urlencode($conf->global->MAIN_INFO_SOCIETE_LOGO);
        }
    }

    $html = '
    <style>
        .header { 
            text-align: center; 
            border-bottom: 2px solid #e67e22; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        .logo { 
            max-height: 40px; 
            margin-bottom: 5px; 
        }
        .title { 
            font-size: 16px; 
            font-weight: bold; 
            color: #2c3e50; 
            margin: 0; 
        }
        .subtitle { 
            font-size: 11px; 
            color: #7f8c8d; 
            margin: 2px 0; 
        }
        .section { 
            margin-bottom: 12px; 
            padding: 8px; 
            border: 1px solid #ddd; 
            background-color: #f9f9f9; 
        }
        .section-title { 
            font-size: 12px; 
            font-weight: bold; 
            color: #2c3e50; 
            border-bottom: 1px solid #bdc3c7; 
            padding-bottom: 3px; 
            margin-bottom: 6px; 
        }
        .field { 
            margin-bottom: 4px; 
            font-size: 9px; 
        }
        .field-label { 
            font-weight: bold; 
            color: #34495e; 
            display: inline-block; 
            width: 35mm; 
        }
        .field-value { 
            color: #2c3e50; 
        }
        .contact-info { 
            background-color: #ecf0f1; 
            padding: 6px; 
            border-radius: 3px; 
            font-size: 8px; 
        }
        .footer { 
            position: fixed; 
            bottom: 10mm; 
            left: 10mm; 
            right: 10mm; 
            text-align: center; 
            font-size: 7px; 
            color: #7f8c8d; 
            border-top: 1px solid #bdc3c7; 
            padding-top: 3px; 
        }
    </style>

    <div class="header">';
    
    if ($logoUrl) {
        $html .= '<img src="' . $logoUrl . '" class="logo" alt="Logo"><br>';
    }
    
    $html .= '
        <h1 class="title">FICHA ACTIVO INMOBILIARIO</h1>
        <p class="subtitle">' . htmlspecialchars($activo->nombre) . '</p>
        <p class="subtitle">Código: ' . $activo->rowid . ' | Fecha: ' . date('d/m/Y H:i') . '</p>
    </div>

    <div class="section">
        <div class="section-title">INFORMACIÓN BÁSICA</div>
        <div class="field">
            <span class="field-label">Nombre:</span>
            <span class="field-value">' . htmlspecialchars($activo->nombre) . '</span>
        </div>';
        
    if ($activo->name_alias) {
        $html .= '
        <div class="field">
            <span class="field-label">Alias:</span>
            <span class="field-value">' . htmlspecialchars($activo->name_alias) . '</span>
        </div>';
    }
    
    $html .= '
        <div class="field">
            <span class="field-label">Tipo:</span>
            <span class="field-value">' . htmlspecialchars($activo->tipo_label) . '</span>
        </div>';
        
    if ($activo->subtipo_label) {
        $html .= '
        <div class="field">
            <span class="field-label">Subtipo:</span>
            <span class="field-value">' . htmlspecialchars($activo->subtipo_label) . '</span>
        </div>';
    }
    
    $html .= '</div>';

    // Ubicación
    if ($activo->address || $activo->zip || $activo->town) {
        $html .= '
        <div class="section">
            <div class="section-title">UBICACIÓN</div>';
            
        if ($activo->address) {
            $html .= '
            <div class="field">
                <span class="field-label">Dirección:</span>
                <span class="field-value">' . htmlspecialchars($activo->address) . '</span>
            </div>';
        }
        
        if ($activo->zip || $activo->town) {
            $html .= '
            <div class="field">
                <span class="field-label">CP/Ciudad:</span>
                <span class="field-value">' . htmlspecialchars(trim($activo->zip . ' ' . $activo->town)) . '</span>
            </div>';
        }
        
        $html .= '</div>';
    }

    // Contacto
    if ($activo->phone || $activo->email || $activo->url) {
        $html .= '
        <div class="section">
            <div class="section-title">CONTACTO</div>
            <div class="contact-info">';
                
        if ($activo->phone) {
            $html .= '
                <div class="field">
                    <span class="field-label">Teléfono:</span>
                    <span class="field-value">' . htmlspecialchars($activo->phone) . '</span>
                </div>';
        }
        
        if ($activo->email) {
            $html .= '
                <div class="field">
                    <span class="field-label">Email:</span>
                    <span class="field-value">' . htmlspecialchars($activo->email) . '</span>
                </div>';
        }
        
        if ($activo->url) {
            $html .= '
                <div class="field">
                    <span class="field-label">Web:</span>
                    <span class="field-value">' . htmlspecialchars($activo->url) . '</span>
                </div>';
        }
        
        $html .= '
            </div>
        </div>';
    }

    // Campos extras relevantes
    if (!empty($extras)) {
        $camposRelevantes = [
            'precio' => 'Precio',
            'superficie' => 'Superficie',
            'habitaciones' => 'Habitaciones',
            'banos' => 'Baños',
            'garage' => 'Garage',
            'jardin' => 'Jardín',
            'piscina' => 'Piscina',
            'estado' => 'Estado',
            'orientacion' => 'Orientación',
            'calefaccion' => 'Calefacción',
            'ano_construccion' => 'Año construcción'
        ];
        
        $hasExtras = false;
        foreach ($camposRelevantes as $campo => $label) {
            if (!empty($extras['options_' . $campo])) {
                if (!$hasExtras) {
                    $html .= '
                    <div class="section">
                        <div class="section-title">CARACTERÍSTICAS</div>';
                    $hasExtras = true;
                }
                
                $valor = $extras['options_' . $campo];
                if ($campo === 'precio' && is_numeric($valor)) {
                    $valor = number_format($valor, 0, ',', '.') . ' €';
                }
                
                $html .= '
                <div class="field">
                    <span class="field-label">' . $label . ':</span>
                    <span class="field-value">' . htmlspecialchars($valor) . '</span>
                </div>';
            }
        }
        
        if ($hasExtras) {
            $html .= '</div>';
        }
    }

    // Observaciones
    if ($activo->note_public) {
        $html .= '
        <div class="section">
            <div class="section-title">OBSERVACIONES</div>
            <div class="field">
                <span class="field-value">' . nl2br(htmlspecialchars($activo->note_public)) . '</span>
            </div>
        </div>';
    }

    // Footer
    $html .= '
    <div class="footer">
        Generado por ' . ($conf->global->MAIN_INFO_SOCIETE_NOM ?? 'Real Estate CRM') . ' | ' . date('d/m/Y H:i:s') . '
    </div>';
    
    return $html;
}

/**
 * Verificar token CSRF
 */
function checkCSRFToken($token) {
    if (empty($token)) return false;
    
    // Verificar con newtoken (Dolibarr 23+)
    if (isset($_SESSION['newtoken']) && $_SESSION['newtoken'] === $token) {
        return true;
    }
    
    // Verificar con token legacy (Dolibarr < 23)
    if (isset($_SESSION['token']) && $_SESSION['token'] === $token) {
        return true;
    }
    
    return false;
}