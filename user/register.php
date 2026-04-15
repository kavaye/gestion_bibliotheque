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
    $preferred_loan_duration = intval($_POST['preferred_loan_duration']);
    
    // Validation
    if($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif(strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } elseif($preferred_loan_duration < 1 || $preferred_loan_duration > 60) {
        $error = 'La durée d\'emprunt doit être comprise entre 1 et 60 jours';
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
            
            $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role, status, registration_date, preferred_loan_duration, max_loan_duration) 
                      VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, 'user', 'active', CURDATE(), :preferred_duration, 30)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':preferred_duration', $preferred_loan_duration);
            
            if($stmt->execute()) {
                $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
            } else {
                $error = 'Erreur lors de l\'inscription';
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
    <title>Inscription - Bibliothèque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .register-container {
            max-width: 550px;
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
        .duration-slider {
            width: 100%;
            margin: 15px 0;
        }
        .duration-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .duration-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                        <h2>Créer un compte</h2>
                        <p class="text-muted">Inscrivez-vous pour emprunter des livres</p>
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
                            
                            <!-- Sélection de la durée d'emprunt -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i> Durée d'emprunt préférée *
                                </label>
                                <div class="duration-info">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <input type="range" class="duration-slider" 
                                                   id="preferred_loan_duration" 
                                                   name="preferred_loan_duration" 
                                                   min="1" max="30" step="1" value="14">
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="duration-value" id="duration_display">14</span> jours
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Choisissez la durée pendant laquelle vous souhaitez emprunter les livres 
                                            (de 1 à 30 jours). Cette durée sera utilisée par défaut pour vos emprunts.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Minimum 6 caractères</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus"></i> S'inscrire
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="text-center">
                            <p>Déjà inscrit ? <a href="login.php">Se connecter</a></p>
                            <a href="../index.php" class="btn btn-link">Retour à l'accueil</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mettre à jour l'affichage de la durée
        const slider = document.getElementById('preferred_loan_duration');
        const display = document.getElementById('duration_display');
        
        slider.addEventListener('input', function() {
            display.textContent = this.value;
        });
    </script>
</body>
</html>