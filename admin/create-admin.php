<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    
    // Validation
    if($password !== $confirm_password) {
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
            
            $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role, status, registration_date, created_by) 
                      VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, :role, 'active', CURDATE(), :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success = 'Compte créé avec succès !';
            } else {
                $error = 'Erreur lors de la création du compte';
            }
        }
    }
}

// Récupérer la liste des administrateurs existants
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM users WHERE created_by = u.id) as created_count 
          FROM users u 
          WHERE u.role IN ('admin', 'librarian') 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Gestion des Administrateurs</h1>
        
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Formulaire de création -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus"></i> Créer un nouveau compte administrateur
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nom d'utilisateur *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Rôle *</label>
                                <select class="form-control" name="role" required>
                                    <option value="librarian">Bibliothécaire</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Les administrateurs ont tous les droits, les bibliothécaires ont des droits limités
                                </small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" name="password" required>
                                    <small class="text-muted">Minimum 6 caractères</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmer le mot de passe *</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Créer le compte
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Liste des administrateurs existants -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users"></i> Administrateurs et Bibliothécaires
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Créé par</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($admins as $admin): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                            <?php if($admin['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-warning">Vous</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td>
                                            <?php if($admin['role'] == 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Bibliothécaire</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if($admin['created_by']) {
                                                $query = "SELECT first_name, last_name FROM users WHERE id = :id";
                                                $stmt2 = $db->prepare($query);
                                                $stmt2->bindParam(':id', $admin['created_by']);
                                                $stmt2->execute();
                                                $creator = $stmt2->fetch(PDO::FETCH_ASSOC);
                                                echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section des codes d'activation (optionnel) -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-key"></i> Codes d'activation pour les nouveaux administrateurs
                </h5>
            </div>
            <div class="card-body">
                <p>Vous pouvez générer des codes d'activation pour permettre à d'autres utilisateurs de devenir administrateurs.</p>
                <button class="btn btn-success" onclick="generateActivationCode()">
                    <i class="fas fa-sync"></i> Générer un code d'activation
                </button>
                <div id="activationCode" class="mt-3" style="display: none;">
                    <div class="alert alert-info">
                        <strong>Code d'activation :</strong> 
                        <code id="codeValue"></code>
                        <button class="btn btn-sm btn-secondary ms-2" onclick="copyCode()">
                            <i class="fas fa-copy"></i> Copier
                        </button>
                        <hr>
                        <small>Ce code peut être utilisé une seule fois pour créer un compte administrateur.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateActivationCode() {
    // Générer un code aléatoire
    const code = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    document.getElementById('codeValue').textContent = code;
    document.getElementById('activationCode').style.display = 'block';
    
    // Sauvegarder le code en base de données (optionnel)
    $.ajax({
        url: 'save-activation-code.php',
        method: 'POST',
        data: { code: code },
        success: function(response) {
            console.log('Code sauvegardé');
        }
    });
}

function copyCode() {
    const code = document.getElementById('codeValue').textContent;
    navigator.clipboard.writeText(code);
    alert('Code copié dans le presse-papier !');
}
</script>

<?php include '../includes/footer.php'; ?>