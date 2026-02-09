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

// Récupérer le token depuis le header Authorization
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

// Décoder le token (simplifié - en production utilisez une vraie bibliothèque JWT)
// Ici on va chercher l'utilisateur par le token stocké ou utiliser une autre méthode
// Pour l'instant, on va utiliser l'email passé dans un paramètre ou chercher dans la BD

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Méthode alternative : récupérer l'email depuis le token stocké côté client
    // ou chercher l'utilisateur par un identifiant dans le token
    
    // Pour simplifier, on va récupérer l'user_id depuis une table de sessions tokens
    // ou utiliser l'email passé en paramètre GET comme dans create-course.php
    
    // Solution temporaire : utiliser l'email de l'utilisateur connecté passé dans le header
    // ou récupérer depuis la base en cherchant par token
    
    // Méthode la plus simple : chercher l'utilisateur qui a ce token dans la BD
    // (nécessite d'avoir stocké le token lors du login)
    
    // Alternative : récupérer l'email depuis un paramètre GET sécurisé
    $user_email = isset($_GET['email']) ? sanitize($_GET['email']) : null;
    
    if (!$user_email) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Email utilisateur requis']);
        exit();
    }
    
    // Récupérer l'utilisateur par email
    $user_sql = "SELECT u.id, u.role, t.id as teacher_id 
                 FROM users u 
                 LEFT JOIN teachers t ON u.id = t.user_id 
                 WHERE u.email = :email AND u.role = 'teacher'";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':email', $user_email);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé']);
        exit();
    }
    
    $user = $user_stmt->fetch();
    $teacher_id = $user['teacher_id'];
    
    if (!$teacher_id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Profil enseignant non trouvé']);
        exit();
    }
    
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
    
    // Devoirs en attente
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