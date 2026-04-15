<?php
session_start();
require_once '../config/database.php';

// Cette page est accessible uniquement en local ou avec un code spécial
// Pour des raisons de sécurité, désactivez cette fonctionnalité en production

$reset_code = isset($_GET['code']) ? $_GET['code'] : '';
$valid_code = 'RESET2024'; // Code temporaire, à changer après installation

if($reset_code !== $valid_code) {
    die("Accès non autorisé. Utilisez un code de réinitialisation valide.");
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_username = $_POST['admin_username'];
    
    if($new_password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif(strlen($new_password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } else {
        // Vérifier que l'admin existe
        $query = "SELECT id FROM users WHERE username = :username AND role = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $admin_username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET password = :password WHERE username = :username AND role = 'admin'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':username', $admin_username);
            
            if($stmt->execute()) {
                $success = 'Mot de passe réinitialisé avec succès !';
            } else {
                $error = 'Erreur lors de la réinitialisation';
            }
        } else {
            $error = 'Administrateur non trouvé';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation Admin - Bibliothèque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-key"></i> Réinitialisation du mot de passe administrateur
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <hr>
                                <a href="login.php" class="btn btn-primary">Se connecter</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Cette fonctionnalité est réservée à l'administrateur principal.
                                Après réinitialisation, supprimez ce fichier ou protégez l'accès.
                            </div>
                            
                            <?php if($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Nom d'utilisateur administrateur</label>
                                    <input type="text" class="form-control" name="admin_username" 
                                           value="admin" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                    <small class="text-muted">Minimum 6 caractères</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-save"></i> Réinitialiser
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-link">Retour à la connexion</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>