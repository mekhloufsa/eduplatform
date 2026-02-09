<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "SELECT t.*, u.email, u.first_name, u.last_name, u.is_active, u.created_at,
                   (SELECT COUNT(*) FROM courses WHERE teacher_id = t.id) as course_count
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $teachers = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $teachers
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
