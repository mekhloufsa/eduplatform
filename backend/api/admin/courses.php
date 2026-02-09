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
    
    $sql = "SELECT 
                c.*,
                u.first_name as teacher_first_name,
                u.last_name as teacher_last_name,
                u.email as teacher_email,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students,
                (SELECT COUNT(*) FROM course_materials WHERE course_id = c.id) as materials_count,
                (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as assignments_count,
                (SELECT COUNT(*) FROM quizzes WHERE course_id = c.id) as quizzes_count,
                (SELECT COUNT(*) FROM forum_topics WHERE course_id = c.id) as forum_topics_count
            FROM courses c
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            ORDER BY c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $courses
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>