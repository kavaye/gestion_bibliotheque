<?php
session_start();
require_once 'config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function login($username, $password) {
        $query = "SELECT * FROM users WHERE (username = :username OR email = :username) AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Log l'activité
                $this->logActivity($user['id'], 'login', 'Utilisateur connecté');
                return true;
            }
        }
        return false;
    }
    
    public function register($data) {
        $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, registration_date) 
                  VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, CURDATE())";
        
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function isLibrarian() {
        return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'librarian');
    }
    
    public function getCurrentUser() {
        if($this->isLoggedIn()) {
            $query = "SELECT * FROM users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
    
    public function logout() {
        if($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'Utilisateur déconnecté');
        }
        session_destroy();
        return true;
    }
    
    private function logActivity($user_id, $action, $details) {
        $query = "INSERT INTO logs (user_id, action, details, ip_address) 
                  VALUES (:user_id, :action, :details, :ip_address)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
    }
}

// Fonctions d'aide
function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}

function requireLogin() {
    if(!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    global $auth;
    if(!$auth->isAdmin()) {
        header('Location: /index.php');
        exit();
    }
}

$auth = new Auth();
?>