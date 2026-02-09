<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['submission_id']) || !isset($data['grade'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de soumission et note requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $submission_id = intval($data['submission_id']);
    $grade = floatval($data['grade']);
    
    // Vérifier que la soumission appartient à un devoir de l'enseignant
    $user_id = $_SESSION['user_id'];
    $teacher_sql = "SELECT t.id FROM teachers t WHERE t.user_id = :user_id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bindParam(':user_id', $user_id);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->fetch();
    
    $check_sql = "SELECT ass.id FROM assignment_submissions ass
                  JOIN assignments a ON ass.assignment_id = a.id
                  JOIN courses c ON a.course_id = c.id
                  WHERE ass.id = :submission_id AND c.teacher_id = :teacher_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':submission_id', $submission_id);
    $check_stmt->bindParam(':teacher_id', $teacher['id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Soumission non trouvée ou accès refusé']);
        exit();
    }
    
    // Mettre à jour la note
    $sql = "UPDATE assignment_submissions 
            SET grade = :grade, status = 'graded', graded_at = NOW() 
            WHERE id = :submission_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':grade', $grade);
    $stmt->bindParam(':submission_id', $submission_id);
    $stmt->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Note enregistrée avec succès'
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>