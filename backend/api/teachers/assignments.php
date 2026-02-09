<?php
// backend/api/teachers/assignments.php
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
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'ID de l'enseignant
    $teacher_sql = "SELECT id FROM teachers WHERE user_id = :user_id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->fetch();
    $teacher_id = $teacher['id'];
    
    // Récupérer les devoirs avec le nombre de soumissions
    $sql = "SELECT a.*, c.title as course_title,
                   (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'submitted') as submission_count,
                   (SELECT file_name FROM assignment_files WHERE assignment_id = a.id LIMIT 1) as file_name
            FROM assignments a
            JOIN courses c ON a.course_id = c.id
            WHERE c.teacher_id = :teacher_id
            ORDER BY a.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();
    $assignments = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $assignments
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>