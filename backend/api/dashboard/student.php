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
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_STUDENT) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Récupérer l'ID de l'étudiant
    $student_sql = "SELECT id FROM students WHERE user_id = :user_id";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bindParam(':user_id', $user_id);
    $student_stmt->execute();
    $student = $student_stmt->fetch();
    $student_id = $student['id'];
    
    // Cours inscrits
    $courses_sql = "SELECT c.*, e.enrollment_date, e.status as enrollment_status,
                           u.first_name as teacher_first_name, 
                           u.last_name as teacher_last_name
                    FROM courses c
                    JOIN enrollments e ON c.id = e.course_id
                    JOIN teachers t ON c.teacher_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE e.student_id = :student_id AND e.status = 'active'
                    ORDER BY e.enrollment_date DESC";
    
    $courses_stmt = $conn->prepare($courses_sql);
    $courses_stmt->bindParam(':student_id', $student_id);
    $courses_stmt->execute();
    $courses = $courses_stmt->fetchAll();
    
    // Statistiques
    $stats_sql = "SELECT 
                    (SELECT COUNT(*) FROM enrollments WHERE student_id = :student_id AND status = 'active') as total_courses,
                    (SELECT COUNT(*) FROM completed_resources WHERE student_id = :student_id) as completed_resources,
                    (SELECT COUNT(*) FROM quiz_submissions WHERE student_id = :student_id AND status = 'submitted') as quizzes_taken,
                    (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = :student_id AND status = 'submitted') as assignments_submitted";
    
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bindParam(':student_id', $student_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    
    // Activités récentes
    $activities_sql = "SELECT al.* FROM activity_logs al 
                       WHERE al.user_id = :user_id 
                       ORDER BY al.created_at DESC 
                       LIMIT 10";
    $activities_stmt = $conn->prepare($activities_sql);
    $activities_stmt->bindParam(':user_id', $user_id);
    $activities_stmt->execute();
    $activities = $activities_stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'courses' => $courses,
            'stats' => $stats,
            'recent_activities' => $activities
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
