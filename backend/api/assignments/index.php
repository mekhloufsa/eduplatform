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
    
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID du cours requis']);
        exit();
    }
    
    // Vérifier que l'étudiant est inscrit au cours
    $user_id = $_SESSION['user_id'];
    $check_sql = "SELECT e.id, s.id as student_id FROM enrollments e 
                  JOIN students s ON e.student_id = s.id 
                  WHERE e.course_id = :course_id AND s.user_id = :user_id AND e.status = 'active'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':course_id', $course_id);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Non inscrit à ce cours']);
        exit();
    }
    
    $enrollment = $check_stmt->fetch();
    $student_id = $enrollment['student_id'];
    
    // Récupérer les devoirs du cours avec le statut de soumission
    $sql = "SELECT a.*, 
            CASE 
                WHEN ass.id IS NOT NULL THEN ass.status 
                ELSE 'not_submitted' 
            END as submission_status,
            ass.submitted_at,
            ass.grade
            FROM assignments a 
            LEFT JOIN assignment_submissions ass ON a.id = ass.assignment_id AND ass.student_id = :student_id
            WHERE a.course_id = :course_id AND a.is_published = 1
            ORDER BY a.due_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':student_id', $student_id);
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