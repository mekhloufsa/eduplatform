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
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['assignment_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID du devoir requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $assignment_id = intval($data['assignment_id']);
    $submission_text = isset($data['submission_text']) ? sanitize($data['submission_text']) : null;
    
    // Récupérer l'ID de l'étudiant
    $student_sql = "SELECT id FROM students WHERE user_id = :user_id";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bindParam(':user_id', $user_id);
    $student_stmt->execute();
    $student = $student_stmt->fetch();
    
    if (!$student) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Étudiant non trouvé']);
        exit();
    }
    
    $student_id = $student['id'];
    
    // Vérifier si déjà soumis
    $check_sql = "SELECT * FROM assignment_submissions WHERE student_id = :student_id AND assignment_id = :assignment_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->bindParam(':assignment_id', $assignment_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Mettre à jour
        $update_sql = "UPDATE assignment_submissions 
                       SET submission_text = :submission_text, status = 'submitted', submitted_at = NOW() 
                       WHERE student_id = :student_id AND assignment_id = :assignment_id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':submission_text', $submission_text);
        $update_stmt->bindParam(':student_id', $student_id);
        $update_stmt->bindParam(':assignment_id', $assignment_id);
        $update_stmt->execute();
    } else {
        // Créer
        $insert_sql = "INSERT INTO assignment_submissions (student_id, assignment_id, submission_text, status, submitted_at) 
                       VALUES (:student_id, :assignment_id, :submission_text, 'submitted', NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bindParam(':student_id', $student_id);
        $insert_stmt->bindParam(':assignment_id', $assignment_id);
        $insert_stmt->bindParam(':submission_text', $submission_text);
        $insert_stmt->execute();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Devoir soumis avec succès'
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
