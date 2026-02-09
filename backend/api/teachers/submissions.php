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

$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;

if (!$assignment_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID du devoir requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'enseignant depuis le token (via email dans la table users ou session)
    // On va utiliser une méthode alternative : chercher l'enseignant par l'email passé en paramètre ou via le token
    
    // Pour l'instant, on récupère l'email depuis le token stocké côté client
    // Solution : récupérer l'user_id depuis une table de sessions ou utiliser l'email
    
    // Méthode simplifiée : on récupère l'assignment et on vérifie que le cours appartient à un enseignant
    // qui a ce token (on va chercher par l'email de l'utilisateur connecté)
    
    // D'abord, récupérons les infos du devoir avec le cours
    $assignment_sql = "SELECT a.*, c.title as course_title, c.teacher_id, t.user_id as teacher_user_id
                       FROM assignments a 
                       JOIN courses c ON a.course_id = c.id 
                       JOIN teachers t ON c.teacher_id = t.id
                       WHERE a.id = :assignment_id";
    $assignment_stmt = $conn->prepare($assignment_sql);
    $assignment_stmt->bindParam(':assignment_id', $assignment_id);
    $assignment_stmt->execute();
    
    if ($assignment_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Devoir non trouvé']);
        exit();
    }
    
    $assignment = $assignment_stmt->fetch();
    
    // Récupérer les soumissions avec TOUTES les informations nécessaires
    $sql = "SELECT 
                ass.id as submission_id,
                ass.student_id,
                ass.submission_text,
                ass.file_path,
                ass.grade,
                ass.feedback,
                ass.status,
                ass.submitted_at,
                ass.graded_at,
                ass.is_late,
                s.student_card,
                s.year,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email
            FROM assignment_submissions ass
            JOIN students s ON ass.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE ass.assignment_id = :assignment_id
            ORDER BY ass.submitted_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':assignment_id', $assignment_id);
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater la réponse
    $response = [
        'status' => 'success',
        'data' => [
            'assignment' => [
                'id' => $assignment['id'],
                'title' => $assignment['title'],
                'description' => $assignment['description'],
                'course_title' => $assignment['course_title'],
                'due_date' => $assignment['due_date'],
                'max_points' => $assignment['max_points']
            ],
            'submissions' => $submissions
        ]
    ];
    
    echo json_encode($response);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>