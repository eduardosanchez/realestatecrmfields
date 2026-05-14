<?php
// Test: parsear consulta_save.php sin ejecutarlo
$file = __DIR__ . '/consulta_save.php';
$code = file_get_contents($file);

// Verificar sintaxis via tokenización básica
$tokens = token_get_all($code);
$errors = [];

// Contar llaves
$braces = 0;
foreach ($tokens as $t) {
    if (is_array($t)) continue;
    if ($t === '{') $braces++;
    if ($t === '}') $braces--;
}

header('Content-Type: application/json');
echo json_encode([
    'file_size'    => strlen($code),
    'token_count'  => count($tokens),
    'brace_balance'=> $braces,
    'last_100_chars' => substr($code, -100),
]);
