<?php
session_start();
require_once '../config/database.php';

// Rediriger si déjà connecté
if(isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $activation_code = $_POST['activation_code'];
    
    // Vérifier le code d'activation
    $query = "SELECT * FROM activation_codes WHERE code = :code AND used = FALSE";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':code', $activation_code);
    $stmt->execute();
    $code_valid = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$code_valid) {
        $error = 'Code d\'activation invalide ou déjà utilisé';
    } elseif($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif(strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } else {
        // Vérifier si l'utilisateur existe déjà
        $query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $error = 'Nom d\'utilisateur ou email déjà utilisé';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Commencer la transaction
            $db->beginTransaction();
            
            try {
                // Créer l'utilisateur admin
                $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role, status, registration_date, created_by) 
                          VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, 'admin', 'active', CURDATE(), :created_by)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':created_by', $code_valid['created_by']);
                $stmt->execute();
                
                $new_user_id = $db->lastInsertId();
                
                // Marquer le code comme utilisé
                $query = "UPDATE activation_codes SET used = TRUE, used_by = :used_by WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':used_by', $new_user_id);
                $stmt->bindParam(':id', $code_valid['id']);
                $stmt->execute();
                
                $db->commit();
                $success = 'Compte administrateur créé avec succès ! Vous pouvez maintenant vous connecter.';
                
            } catch(Exception $e) {
                $db->rollBack();
                $error = 'Erreur lors de la création du compte';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Administrateur - Bibliothèque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .register-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .admin-badge {
            position: absolute;
            top: -15px;
            right: -15px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card position-relative">
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-shield fa-3x text-danger mb-3"></i>
                        <h2>Inscription Administrateur</h2>
                        <p class="text-muted">Créez un compte avec des privilèges administrateur</p>
                        <p class="small text-muted">
                            <i class="fas fa-key"></i> Un code d'activation est requis
                        </p>
                    </div>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <div class="text-center">
                            <a href="login.php" class="btn btn-primary">Se connecter</a>
                            <a href="../index.php" class="btn btn-link">Retour à l'accueil</a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="activation_code" class="form-label">
                                    <i class="fas fa-key"></i> Code d'activation *
                                </label>
                                <input type="text" class="form-control" id="activation_code" name="activation_code" required>
                                <small class="text-muted">
                                    Demandez ce code à un administrateur existant
                                </small>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Adresse</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Minimum 6 caractères</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus"></i> Créer le compte administrateur
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="text-center">
                            <p>Déjà un compte ? <a href="login.php">Se connecter</a></p>
                            <p>
                                <small>
                                    <a href="register.php" class="text-muted">
                                        <i class="fas fa-user"></i> Créer un compte utilisateur standard
                                    </a>
                                </small>
                            </p>
                            <a href="../index.php" class="btn btn-link">Retour à l'accueil</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>