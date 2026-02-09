<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
$course_id = isset($data['course_id']) ? intval($data['course_id']) : 0;

if (!$course_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID du cours requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier que le cours existe
    $check_sql = "SELECT c.*, u.first_name, u.last_name 
                  FROM courses c 
                  JOIN teachers t ON c.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE c.id = :course_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':course_id', $course_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Cours non trouvé']);
        exit();
    }
    
    $course = $check_stmt->fetch();
    $course_title = $course['title'];
    $teacher_name = $course['first_name'] . ' ' . $course['last_name'];
    
    // Supprimer les soumissions de devoirs
    $conn->prepare("DELETE FROM assignment_submissions WHERE assignment_id IN 
                   (SELECT id FROM assignments WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les fichiers de devoirs
    $conn->prepare("DELETE FROM assignment_files WHERE assignment_id IN 
                   (SELECT id FROM assignments WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les devoirs
    $conn->prepare("DELETE FROM assignments WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les options de questions de quiz
    $conn->prepare("DELETE FROM question_options WHERE question_id IN 
                   (SELECT id FROM quiz_questions WHERE quiz_id IN 
                   (SELECT id FROM quizzes WHERE course_id = :course_id))")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les questions de quiz
    $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id IN 
                   (SELECT id FROM quizzes WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // CORRECTION: Supprimé une parenthèse en trop sur la ligne suivante
    $conn->prepare("DELETE FROM quiz_submissions WHERE quiz_id IN 
                   (SELECT id FROM quizzes WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les quiz
    $conn->prepare("DELETE FROM quizzes WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les ressources complétées
    $conn->prepare("DELETE FROM completed_resources WHERE material_id IN 
                   (SELECT id FROM course_materials WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les supports de cours
    $conn->prepare("DELETE FROM course_materials WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les posts et likes du forum
    $conn->prepare("DELETE FROM forum_likes WHERE post_id IN 
                   (SELECT id FROM forum_posts WHERE topic_id IN 
                   (SELECT id FROM forum_topics WHERE course_id = :course_id))")
         ->execute([':course_id' => $course_id]);
    
    $conn->prepare("DELETE FROM forum_posts WHERE topic_id IN 
                   (SELECT id FROM forum_topics WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    $conn->prepare("DELETE FROM forum_topics WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer les inscriptions
    $conn->prepare("DELETE FROM enrollments WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // Supprimer le cours
    $conn->prepare("DELETE FROM courses WHERE id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cours "' . $course_title . '" (par ' . $teacher_name . ') supprimé avec succès'
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>