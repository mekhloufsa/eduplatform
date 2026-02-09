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

// Récupérer le token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

// Récupérer les données
$data = json_decode(file_get_contents('php://input'), true);
$course_id = isset($data['course_id']) ? intval($data['course_id']) : 0;
$email = isset($data['email']) ? sanitize($data['email']) : null;

if (!$course_id || !$email) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID du cours et email requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'enseignant par email
    $user_sql = "SELECT u.id, t.id as teacher_id 
                 FROM users u 
                 JOIN teachers t ON u.id = t.user_id 
                 WHERE u.email = :email AND u.role = 'teacher'";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':email', $email);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé']);
        exit();
    }
    
    $user = $user_stmt->fetch();
    $teacher_id = $user['teacher_id'];
    
    // Vérifier que le cours appartient bien à cet enseignant
    $check_sql = "SELECT id, title FROM courses WHERE id = :course_id AND teacher_id = :teacher_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':course_id', $course_id);
    $check_stmt->bindParam(':teacher_id', $teacher_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Cours non trouvé ou accès refusé']);
        exit();
    }
    
    $course = $check_stmt->fetch();
    $course_title = $course['title'];
    
    // Supprimer les dépendances d'abord (pour respecter les contraintes de clés étrangères)
    
    // 1. Supprimer les soumissions de devoirs
    $conn->prepare("DELETE FROM assignment_submissions WHERE assignment_id IN 
                   (SELECT id FROM assignments WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // 2. Supprimer les fichiers de devoirs
    $conn->prepare("DELETE FROM assignment_files WHERE assignment_id IN 
                   (SELECT id FROM assignments WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // 3. Supprimer les devoirs
    $conn->prepare("DELETE FROM assignments WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // 4. Supprimer les options de questions de quiz
    $conn->prepare("DELETE FROM question_options WHERE question_id IN 
                   (SELECT id FROM quiz_questions WHERE quiz_id IN 
                   (SELECT id FROM quizzes WHERE course_id = :course_id))")
         ->execute([':course_id' => $course_id]);
    
    // 5. Supprimer les questions de quiz
    $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id IN 
                   (SELECT id FROM quizzes WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // 6. Supprimer les soumissions de quiz
    $conn->prepare("DELETE FROM quiz_submissions WHERE quiz_id IN 
                   (SELECT id FROM quizzes WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // 7. Supprimer les quiz
    $conn->prepare("DELETE FROM quizzes WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // 8. Supprimer les ressources complétées
    $conn->prepare("DELETE FROM completed_resources WHERE material_id IN 
                   (SELECT id FROM course_materials WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    // 9. Supprimer les supports de cours
    $conn->prepare("DELETE FROM course_materials WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // 10. Supprimer les posts et likes du forum
    $conn->prepare("DELETE FROM forum_likes WHERE post_id IN 
                   (SELECT id FROM forum_posts WHERE topic_id IN 
                   (SELECT id FROM forum_topics WHERE course_id = :course_id))")
         ->execute([':course_id' => $course_id]);
    
    $conn->prepare("DELETE FROM forum_posts WHERE topic_id IN 
                   (SELECT id FROM forum_topics WHERE course_id = :course_id)")
         ->execute([':course_id' => $course_id]);
    
    $conn->prepare("DELETE FROM forum_topics WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // 11. Supprimer les inscriptions
    $conn->prepare("DELETE FROM enrollments WHERE course_id = :course_id")
         ->execute([':course_id' => $course_id]);
    
    // 12. Finalement, supprimer le cours
    $delete_sql = "DELETE FROM courses WHERE id = :course_id";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bindParam(':course_id', $course_id);
    $delete_stmt->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cours "' . $course_title . '" supprimé avec succès',
        'data' => ['course_id' => $course_id]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>