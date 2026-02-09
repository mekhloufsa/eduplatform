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
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Récupérer l'ID de l'enseignant
    $teacher_sql = "SELECT id FROM teachers WHERE user_id = :user_id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bindParam(':user_id', $user_id);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->fetch();
    $teacher_id = $teacher['id'];
    
    // Cours créés
    $courses_sql = "SELECT c.*, 
                           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrollment_count
                    FROM courses c
                    WHERE c.teacher_id = :teacher_id
                    ORDER BY c.created_at DESC";
    
    $courses_stmt = $conn->prepare($courses_sql);
    $courses_stmt->bindParam(':teacher_id', $teacher_id);
    $courses_stmt->execute();
    $courses = $courses_stmt->fetchAll();
    
    // Statistiques
    $stats_sql = "SELECT 
                    (SELECT COUNT(*) FROM courses WHERE teacher_id = :teacher_id) as total_courses,
                    (SELECT COUNT(*) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = :teacher_id AND e.status = 'active') as total_students,
                    (SELECT COUNT(*) FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE c.teacher_id = :teacher_id) as total_quizzes,
                    (SELECT COUNT(*) FROM assignments a JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = :teacher_id) as total_assignments";
    
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bindParam(':teacher_id', $teacher_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    
    // Devoirs en attente de notation
    $pending_sql = "SELECT COUNT(*) as pending FROM assignment_submissions ass
                    JOIN assignments a ON ass.assignment_id = a.id
                    JOIN courses c ON a.course_id = c.id
                    WHERE c.teacher_id = :teacher_id AND ass.status = 'submitted'";
    $pending_stmt = $conn->prepare($pending_sql);
    $pending_stmt->bindParam(':teacher_id', $teacher_id);
    $pending_stmt->execute();
    $pending = $pending_stmt->fetch()['pending'];
    $stats['pending_assignments'] = $pending;
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'courses' => $courses,
            'stats' => $stats
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
