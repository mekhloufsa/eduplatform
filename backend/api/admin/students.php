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
    
    // CORRECTION : Retiré s.major qui n'existe pas
    $sql = "SELECT 
                s.id as student_id,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.created_at as registered_at,
                u.is_active,
                s.student_card,
                s.year,
                (SELECT COUNT(*) FROM enrollments WHERE student_id = s.id) as enrolled_courses,
                (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = s.id) as submissions_count,
                (SELECT AVG(grade) FROM assignment_submissions WHERE student_id = s.id AND grade IS NOT NULL) as average_grade
            FROM students s
            JOIN users u ON s.user_id = u.id
            ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $students
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>