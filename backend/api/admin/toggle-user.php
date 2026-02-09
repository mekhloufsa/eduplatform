<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$action = isset($data['action']) ? $data['action'] : ''; // 'activate' ou 'deactivate'

if (!$user_id || !in_array($action, ['activate', 'deactivate'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Paramètres invalides']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier que l'utilisateur existe
    $check_sql = "SELECT first_name, last_name, is_active FROM users WHERE id = :user_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Utilisateur non trouvé']);
        exit();
    }
    
    $user = $check_stmt->fetch();
    $user_name = $user['first_name'] . ' ' . $user['last_name'];
    $new_status = ($action === 'activate') ? 1 : 0;
    $status_text = ($action === 'activate') ? 'activé' : 'désactivé';
    
    $sql = "UPDATE users SET is_active = :status WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Utilisateur "' . $user_name . '" ' . $status_text . ' avec succès',
        'data' => ['is_active' => $new_status]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>