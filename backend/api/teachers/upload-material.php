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

if (!isset($_FILES['file']) || !isset($_POST['course_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Fichier et cours requis']);
    exit();
}

$course_id = intval($_POST['course_id']);
$title = isset($_POST['title']) ? sanitize($_POST['title']) : $_FILES['file']['name'];

// Vérifier que le cours appartient à l'enseignant
try {
    $db = new Database();
    $conn = $db->getConnection();
    
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
    
    // Créer le dossier uploads s'il n'existe pas
    $upload_dir = '../../uploads/materials/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Générer un nom de fichier unique
    $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['file']['name']);
    $file_path = $upload_dir . $file_name;
    
    // Déplacer le fichier
    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
        
        // Enregistrer dans la base de données
        $sql = "INSERT INTO course_materials (course_id, title, file_type, file_path, file_size, is_published) 
                VALUES (:course_id, :title, :file_type, :file_path, :file_size, 1)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':file_type', $file_extension);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_size', $_FILES['file']['size']);
        $stmt->execute();
        
        $material_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Fichier uploadé avec succès',
            'data' => [
                'material_id' => $material_id,
                'file_name' => $file_name
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'upload du fichier']);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>