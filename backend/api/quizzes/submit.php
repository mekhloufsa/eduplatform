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

if (!isset($data['quiz_id']) || !isset($data['answers'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Quiz ID et réponses requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $quiz_id = intval($data['quiz_id']);
    $answers = json_encode($data['answers']);
    $time_taken = isset($data['time_taken']) ? intval($data['time_taken']) : 0;
    
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
    
    // Calculer le score
    $score = 0;
    $total_points = 0;
    
    $questions_sql = "SELECT id, points FROM quiz_questions WHERE quiz_id = :quiz_id";
    $questions_stmt = $conn->prepare($questions_sql);
    $questions_stmt->bindParam(':quiz_id', $quiz_id);
    $questions_stmt->execute();
    $questions = $questions_stmt->fetchAll();
    
    foreach ($questions as $question) {
        $total_points += $question['points'];
        $question_id = $question['id'];
        
        if (isset($data['answers'][$question_id])) {
            $options_sql = "SELECT id FROM question_options WHERE question_id = :question_id AND is_correct = 1";
            $options_stmt = $conn->prepare($options_sql);
            $options_stmt->bindParam(':question_id', $question_id);
            $options_stmt->execute();
            $correct_options = $options_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $user_answer = $data['answers'][$question_id];
            if (is_array($user_answer)) {
                sort($user_answer);
                sort($correct_options);
                if ($user_answer == $correct_options) {
                    $score += $question['points'];
                }
            } else {
                if (in_array($user_answer, $correct_options)) {
                    $score += $question['points'];
                }
            }
        }
    }
    
    $score_percentage = $total_points > 0 ? ($score / $total_points) * 100 : 0;
    
    // Enregistrer la soumission
    $submit_sql = "INSERT INTO quiz_submissions (student_id, quiz_id, answers, score, time_taken, status, submitted_at) 
                   VALUES (:student_id, :quiz_id, :answers, :score, :time_taken, 'submitted', NOW())";
    $submit_stmt = $conn->prepare($submit_sql);
    $submit_stmt->bindParam(':student_id', $student_id);
    $submit_stmt->bindParam(':quiz_id', $quiz_id);
    $submit_stmt->bindParam(':answers', $answers);
    $submit_stmt->bindParam(':score', $score_percentage);
    $submit_stmt->bindParam(':time_taken', $time_taken);
    $submit_stmt->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Quiz soumis avec succès',
        'data' => [
            'score' => $score_percentage,
            'points' => $score,
            'total_points' => $total_points
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
