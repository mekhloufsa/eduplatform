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

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // CORRECTION : Retiré t.specialization et t.department qui n'existent pas
    $sql = "SELECT 
                t.id as teacher_id,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.created_at as registered_at,
                u.is_active,
                (SELECT COUNT(*) FROM courses WHERE teacher_id = t.id) as courses_count,
                (SELECT COUNT(*) FROM assignments WHERE course_id IN 
                    (SELECT id FROM courses WHERE teacher_id = t.id)) as assignments_count,
                (SELECT COUNT(*) FROM quizzes WHERE course_id IN 
                    (SELECT id FROM courses WHERE teacher_id = t.id)) as quizzes_count
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $teachers
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>