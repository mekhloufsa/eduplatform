<?php
// backend/api/quizzes/questions.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Vérifier que l'utilisateur est un enseignant
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // GET - Récupérer les questions d'un quiz
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
        
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID du quiz requis']);
            exit();
        }
        
        // Vérifier que le quiz appartient à l'enseignant
        $teacher_sql = "SELECT t.id FROM teachers t WHERE t.user_id = :user_id";
        $teacher_stmt = $conn->prepare($teacher_sql);
        $teacher_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $teacher_stmt->execute();
        $teacher = $teacher_stmt->fetch();
        
        $check_sql = "SELECT id FROM quizzes WHERE id = :quiz_id AND course_id IN 
                     (SELECT id FROM courses WHERE teacher_id = :teacher_id)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':quiz_id', $quiz_id);
        $check_stmt->bindParam(':teacher_id', $teacher['id']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Quiz non trouvé ou accès refusé']);
            exit();
        }
        
        // Récupérer les questions avec leurs options
        $sql = "SELECT * FROM quiz_questions WHERE quiz_id = :quiz_id ORDER BY order_index ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':quiz_id', $quiz_id);
        $stmt->execute();
        $questions = $stmt->fetchAll();
        
        // Pour chaque question, récupérer les options
        foreach ($questions as &$question) {
            $opt_sql = "SELECT * FROM question_options WHERE question_id = :question_id";
            $opt_stmt = $conn->prepare($opt_sql);
            $opt_stmt->bindParam(':question_id', $question['id']);
            $opt_stmt->execute();
            $question['options'] = $opt_stmt->fetchAll();
        }
        
        echo json_encode(['status' => 'success', 'data' => $questions]);
    }
    
    // POST - Ajouter une question
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['quiz_id']) || !isset($data['question']) || !isset($data['options'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données incomplètes']);
            exit();
        }
        
        $quiz_id = intval($data['quiz_id']);
        
        // Vérifier que le quiz appartient à l'enseignant
        $teacher_sql = "SELECT t.id FROM teachers t WHERE t.user_id = :user_id";
        $teacher_stmt = $conn->prepare($teacher_sql);
        $teacher_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $teacher_stmt->execute();
        $teacher = $teacher_stmt->fetch();
        
        $check_sql = "SELECT id FROM quizzes WHERE id = :quiz_id AND course_id IN 
                     (SELECT id FROM courses WHERE teacher_id = :teacher_id)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':quiz_id', $quiz_id);
        $check_stmt->bindParam(':teacher_id', $teacher['id']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Quiz non trouvé ou accès refusé']);
            exit();
        }
        
        $conn->beginTransaction();
        
        try {
            // Insérer la question
            $question_sql = "INSERT INTO quiz_questions (quiz_id, question, question_type, points, explanation, order_index) 
                           VALUES (:quiz_id, :question, :question_type, :points, :explanation, :order_index)";
            $question_stmt = $conn->prepare($question_sql);
            $question_stmt->bindParam(':quiz_id', $quiz_id);
            $question_stmt->bindParam(':question', $data['question']);
            $question_type = isset($data['question_type']) ? $data['question_type'] : 'multiple_choice';
            $question_stmt->bindParam(':question_type', $question_type);
            $points = isset($data['points']) ? intval($data['points']) : 1;
            $question_stmt->bindParam(':points', $points);
            $explanation = isset($data['explanation']) ? $data['explanation'] : null;
            $question_stmt->bindParam(':explanation', $explanation);
            $order_index = isset($data['order_index']) ? intval($data['order_index']) : 0;
            $question_stmt->bindParam(':order_index', $order_index);
            $question_stmt->execute();
            
            $question_id = $conn->lastInsertId();
            
            // Insérer les options
            $option_sql = "INSERT INTO question_options (question_id, option_text, is_correct) 
                          VALUES (:question_id, :option_text, :is_correct)";
            $option_stmt = $conn->prepare($option_sql);
            
            foreach ($data['options'] as $option) {
                $option_stmt->bindParam(':question_id', $question_id);
                $option_stmt->bindParam(':option_text', $option['text']);
                $is_correct = isset($option['is_correct']) ? (bool)$option['is_correct'] : false;
                $option_stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);
                $option_stmt->execute();
            }
            
            $conn->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Question ajoutée avec succès',
                'data' => ['question_id' => $question_id]
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
    
    // DELETE - Supprimer une question
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $question_id = isset($data['question_id']) ? intval($data['question_id']) : 0;
        
        if (!$question_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID de la question requis']);
            exit();
        }
        
        // Vérifier que la question appartient à un quiz de l'enseignant
        $teacher_sql = "SELECT t.id FROM teachers t WHERE t.user_id = :user_id";
        $teacher_stmt = $conn->prepare($teacher_sql);
        $teacher_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $teacher_stmt->execute();
        $teacher = $teacher_stmt->fetch();
        
        $check_sql = "SELECT qq.id FROM quiz_questions qq 
                     JOIN quizzes q ON qq.quiz_id = q.id 
                     JOIN courses c ON q.course_id = c.id 
                     WHERE qq.id = :question_id AND c.teacher_id = :teacher_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':question_id', $question_id);
        $check_stmt->bindParam(':teacher_id', $teacher['id']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Question non trouvée ou accès refusé']);
            exit();
        }
        
        $sql = "DELETE FROM quiz_questions WHERE id = :question_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':question_id', $question_id);
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Question supprimée']);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>