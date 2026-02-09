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

// Vérifier si c'est du FormData ou du JSON
$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';

// Récupérer les données
if (strpos($contentType, 'multipart/form-data') !== false) {
    // FormData
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $title = isset($_POST['title']) ? sanitize($_POST['title']) : '';
    $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
    $max_points = isset($_POST['max_points']) ? intval($_POST['max_points']) : 100;
    $allow_late = isset($_POST['allow_late_submission']) ? true : false;
    $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
} else {
    // JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Données JSON invalides']);
        exit();
    }
    
    $course_id = isset($data['course_id']) ? intval($data['course_id']) : 0;
    $title = isset($data['title']) ? sanitize($data['title']) : '';
    $description = isset($data['description']) ? sanitize($data['description']) : '';
    $due_date = isset($data['due_date']) ? $data['due_date'] : '';
    $max_points = isset($data['max_points']) ? intval($data['max_points']) : 100;
    $allow_late = isset($data['allow_late_submission']) ? (bool)$data['allow_late_submission'] : false;
    $hasFile = false;
}

// Validation
if (!$course_id || empty($title) || empty($due_date)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cours, titre et date de remise requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'ID de l'enseignant
    $teacher_sql = "SELECT id FROM teachers WHERE user_id = :user_id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->fetch();
    
    if (!$teacher) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé']);
        exit();
    }
    
    $teacher_id = $teacher['id'];
    
    // Vérifier que le cours appartient à l'enseignant
    $check_sql = "SELECT id FROM courses WHERE id = :course_id AND teacher_id = :teacher_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':course_id', $course_id);
    $check_stmt->bindParam(':teacher_id', $teacher_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Cours non trouvé ou accès refusé']);
        exit();
    }
    
    // Traiter l'upload de fichier si présent
    $file_path = null;
    $file_name = null;
    $file_size = 0;
    
    if ($hasFile) {
        $upload_dir = '../../uploads/assignments/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['file']['name']);
        $file_path = $upload_dir . $file_name;
        
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'upload du fichier']);
            exit();
        }
        
        $file_size = $_FILES['file']['size'];
    }
    
    // Insérer le devoir
    $sql = "INSERT INTO assignments (course_id, title, description, due_date, max_points, allow_late_submission, is_published) 
            VALUES (:course_id, :title, :description, :due_date, :max_points, :allow_late, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':due_date', $due_date);
    $stmt->bindParam(':max_points', $max_points);
    $stmt->bindParam(':allow_late', $allow_late, PDO::PARAM_BOOL);
    $stmt->execute();
    
    $assignment_id = $conn->lastInsertId();
    
    // Si un fichier a été uploadé, l'enregistrer
    if ($file_path) {
        $file_sql = "INSERT INTO assignment_files (assignment_id, file_name, file_path, file_size, uploaded_by) 
                     VALUES (:assignment_id, :file_name, :file_path, :file_size, :teacher_id)";
        $file_stmt = $conn->prepare($file_sql);
        $file_stmt->bindParam(':assignment_id', $assignment_id);
        $file_stmt->bindParam(':file_name', $_FILES['file']['name']);
        $file_stmt->bindParam(':file_path', $file_path);
        $file_stmt->bindParam(':file_size', $file_size);
        $file_stmt->bindParam(':teacher_id', $teacher_id);
        $file_stmt->execute();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Devoir créé avec succès',
        'data' => [
            'assignment_id' => $assignment_id,
            'title' => $title,
            'has_file' => $file_path !== null
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>