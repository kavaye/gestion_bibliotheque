<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$code = $_POST['code'];

// Créer une table pour les codes d'activation si elle n'existe pas
$query = "CREATE TABLE IF NOT EXISTS activation_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (used_by) REFERENCES users(id)
)";

$db->exec($query);

// Sauvegarder le code
$query = "INSERT INTO activation_codes (code, created_by) VALUES (:code, :created_by)";
$stmt = $db->prepare($query);
$stmt->bindParam(':code', $code);
$stmt->bindParam(':created_by', $_SESSION['user_id']);

if($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>