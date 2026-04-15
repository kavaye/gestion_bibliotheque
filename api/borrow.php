<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$libFunc = new LibraryFunctions($db);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $book_id = isset($_POST['book_id']) ? $_POST['book_id'] : null;
    
    if(!$book_id) {
        echo json_encode(['success' => false, 'message' => 'ID du livre manquant']);
        exit();
    }
    
    $result = $libFunc->borrowBook($_SESSION['user_id'], $book_id);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>