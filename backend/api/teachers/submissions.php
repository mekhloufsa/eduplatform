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
    
    $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
    
    if (!$assignment_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID du devoir requis']);
        exit();
    }
    
    // Vérifier que le devoir appartient à l'enseignant
    $user_id = $_SESSION['user_id'];
    $teacher_sql = "SELECT t.id FROM teachers t WHERE t.user_id = :user_id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bindParam(':user_id', $user_id);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->fetch();
    
    $check_sql = "SELECT a.id FROM assignments a 
                  JOIN courses c ON a.course_id = c.id 
                  WHERE a.id = :assignment_id AND c.teacher_id = :teacher_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':assignment_id', $assignment_id);
    $check_stmt->bindParam(':teacher_id', $teacher['id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Devoir non trouvé ou accès refusé']);
        exit();
    }
    
    // Récupérer les informations du devoir
    $assignment_sql = "SELECT a.*, c.title as course_title 
                       FROM assignments a 
                       JOIN courses c ON a.course_id = c.id 
                       WHERE a.id = :assignment_id";
    $assignment_stmt = $conn->prepare($assignment_sql);
    $assignment_stmt->bindParam(':assignment_id', $assignment_id);
    $assignment_stmt->execute();
    $assignment = $assignment_stmt->fetch();
    
    // Récupérer les soumissions avec les informations des étudiants
    $sql = "SELECT ass.*, u.first_name, u.last_name, u.email, s.student_card
            FROM assignment_submissions ass
            JOIN students s ON ass.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE ass.assignment_id = :assignment_id
            ORDER BY ass.submitted_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':assignment_id', $assignment_id);
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'assignment' => $assignment,
            'submissions' => $submissions
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>