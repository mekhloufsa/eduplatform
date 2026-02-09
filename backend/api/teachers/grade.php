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

// Récupérer le token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
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
    
    // Mettre à jour la note
    $sql = "UPDATE assignment_submissions 
            SET grade = :grade, 
                status = 'graded', 
                graded_at = NOW() 
            WHERE id = :submission_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':grade', $grade);
    $stmt->bindParam(':submission_id', $submission_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Note enregistrée avec succès'
        ]);
    } else {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Aucune modification effectuée'
        ]);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>