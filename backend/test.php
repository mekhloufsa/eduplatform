<?php
// Fichier de test pour vérifier la configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$tests = [];

// Test 1: Vérifier PHP
$tests['php'] = [
    'status' => 'success',
    'version' => PHP_VERSION
];

// Test 2: Vérifier les extensions PDO
if (extension_loaded('pdo')) {
    $tests['pdo'] = ['status' => 'success', 'message' => 'PDO est installé'];
} else {
    $tests['pdo'] = ['status' => 'error', 'message' => 'PDO n\'est pas installé'];
}

if (extension_loaded('pdo_mysql')) {
    $tests['pdo_mysql'] = ['status' => 'success', 'message' => 'PDO MySQL est installé'];
} else {
    $tests['pdo_mysql'] = ['status' => 'error', 'message' => 'PDO MySQL n\'est pas installé'];
}

// Test 3: Vérifier la connexion à la base de données
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    $tests['database'] = ['status' => 'success', 'message' => 'Connexion à la base de données réussie'];
    
    // Tester une requête simple
    $stmt = $conn->query("SELECT 1 as test");
    $result = $stmt->fetch();
    $tests['query'] = ['status' => 'success', 'message' => 'Requête test réussie', 'result' => $result];
    
    // Vérifier si les tables existent
    $tables = ['users', 'students', 'teachers', 'courses', 'enrollments'];
    $existingTables = [];
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
        }
    }
    $tests['tables'] = [
        'status' => count($existingTables) === count($tables) ? 'success' : 'warning',
        'message' => 'Tables trouvées: ' . implode(', ', $existingTables),
        'missing' => array_diff($tables, $existingTables)
    ];
    
} catch (Exception $e) {
    $tests['database'] = ['status' => 'error', 'message' => 'Erreur de connexion: ' . $e->getMessage()];
}

// Test 4: Vérifier les fichiers de configuration
$config_files = [
    'config/database.php' => file_exists('config/database.php'),
    'config/constants.php' => file_exists('config/constants.php'),
    'api/auth/login.php' => file_exists('api/auth/login.php'),
    'api/auth/register.php' => file_exists('api/auth/register.php')
];

$tests['files'] = [];
foreach ($config_files as $file => $exists) {
    $tests['files'][$file] = $exists ? 'existe' : 'manquant';
}

// Résultat global
$all_success = true;
foreach ($tests as $test) {
    if (is_array($test) && isset($test['status']) && $test['status'] === 'error') {
        $all_success = false;
        break;
    }
}

echo json_encode([
    'status' => $all_success ? 'success' : 'error',
    'message' => $all_success ? 'Tous les tests ont réussi' : 'Certains tests ont échoué',
    'tests' => $tests,
    'server' => [
        'php_self' => $_SERVER['PHP_SELF'],
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'request_uri' => $_SERVER['REQUEST_URI']
    ]
], JSON_PRETTY_PRINT);
?>
