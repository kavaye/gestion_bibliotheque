<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer les informations de l'utilisateur
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement de la modification du profil
$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $preferred_loan_duration = intval($_POST['preferred_loan_duration']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation de la durée
    if($preferred_loan_duration < 1 || $preferred_loan_duration > $user['max_loan_duration']) {
        $error = "La durée d'emprunt doit être comprise entre 1 et " . $user['max_loan_duration'] . " jours";
    } else {
        // Vérifier si l'email est déjà utilisé par un autre utilisateur
        $query = "SELECT id FROM users WHERE email = :email AND id != :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $error = 'Cet email est déjà utilisé par un autre compte';
        } else {
            // Commencer la transaction
            $db->beginTransaction();
            
            try {
                // Mettre à jour les informations de base
                $query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                          email = :email, phone = :phone, address = :address, 
                          preferred_loan_duration = :preferred_duration
                          WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':preferred_duration', $preferred_loan_duration);
                $stmt->bindParam(':id', $_SESSION['user_id']);
                $stmt->execute();
                
                // Changer le mot de passe si demandé
                if(!empty($new_password)) {
                    // Vérifier le mot de passe actuel
                    if(empty($current_password)) {
                        throw new Exception('Veuillez entrer votre mot de passe actuel');
                    }
                    
                    if(!password_verify($current_password, $user['password'])) {
                        throw new Exception('Mot de passe actuel incorrect');
                    }
                    
                    if(strlen($new_password) < 6) {
                        throw new Exception('Le nouveau mot de passe doit contenir au moins 6 caractères');
                    }
                    
                    if($new_password !== $confirm_password) {
                        throw new Exception('Les nouveaux mots de passe ne correspondent pas');
                    }
                    
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = :password WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $_SESSION['user_id']);
                    $stmt->execute();
                }
                
                $db->commit();
                
                // Mettre à jour le nom dans la session
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $success = 'Profil mis à jour avec succès';
                
                // Recharger les données de l'utilisateur
                $query = "SELECT * FROM users WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_SESSION['user_id']);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch(Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Récupérer les statistiques de l'utilisateur
$query = "SELECT 
            COUNT(*) as total_loans,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_loans,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_loans,
            SUM(CASE WHEN due_date < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as overdue_loans
          FROM loans 
          WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$loan_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les amendes impayées
$query = "SELECT SUM(amount) as total_fines FROM fines 
          WHERE user_id = :user_id AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$fines = $stmt->fetch(PDO::FETCH_ASSOC);
$total_fines = $fines['total_fines'] ? $fines['total_fines'] : 0;

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Mon Profil</h1>
        
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
            <!-- Informations personnelles -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Informations personnelles</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Le nom d'utilisateur ne peut pas être modifié</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <!-- Modification de la durée d'emprunt préférée -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i> Durée d'emprunt préférée
                                </label>
                                <div class="duration-info bg-light p-3 rounded">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <input type="range" class="form-range" 
                                                   id="preferred_loan_duration" 
                                                   name="preferred_loan_duration" 
                                                   min="1" max="<?php echo $user['max_loan_duration']; ?>" 
                                                   step="1" value="<?php echo $user['preferred_loan_duration']; ?>">
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="duration-value fw-bold text-primary" id="duration_display">
                                                <?php echo $user['preferred_loan_duration']; ?>
                                            </span> jours
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Durée maximale autorisée : <?php echo $user['max_loan_duration']; ?> jours
                                            <?php if($user['role'] == 'admin'): ?>
                                                <span class="badge bg-danger">Administrateur</span>
                                            <?php elseif($user['role'] == 'librarian'): ?>
                                                <span class="badge bg-info">Bibliothécaire</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h5 class="mb-3">Changer le mot de passe</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" name="current_password">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" name="new_password">
                                    <small class="text-muted">Minimum 6 caractères</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                                    <input type="password" class="form-control" name="confirm_password">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Mettre à jour le profil
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques et informations -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Statistiques</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted">Date d'inscription</label>
                            <p class="mb-0">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo date('d/m/Y', strtotime($user['registration_date'])); ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted">Durée d'emprunt par défaut</label>
                            <p class="mb-0">
                                <i class="fas fa-clock"></i> 
                                <strong><?php echo $user['preferred_loan_duration']; ?> jours</strong>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted">Durée maximale autorisée</label>
                            <p class="mb-0">
                                <i class="fas fa-hourglass-half"></i> 
                                <?php echo $user['max_loan_duration']; ?> jours
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted">Statut du compte</label>
                            <p class="mb-0">
                                <?php if($user['status'] == 'active'): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php elseif($user['status'] == 'suspended'): ?>
                                    <span class="badge bg-warning">Suspendu</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactif</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="text-muted">Total des emprunts</label>
                            <p class="h3"><?php echo $loan_stats['total_loans']; ?></p>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="text-muted">En cours</label>
                                <p class="h5 text-info"><?php echo $loan_stats['active_loans']; ?></p>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="text-muted">En retard</label>
                                <p class="h5 text-warning"><?php echo $loan_stats['overdue_loans']; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted">Emprunts retournés</label>
                            <p class="h5 text-success"><?php echo $loan_stats['returned_loans']; ?></p>
                        </div>
                        
                        <?php if($total_fines > 0): ?>
                            <hr>
                            <div class="mb-3">
                                <label class="text-muted">Amendes impayées</label>
                                <p class="h4 text-danger">
                                    <i class="fas fa-euro-sign"></i> <?php echo number_format($total_fines, 2); ?>
                                </p>
                                <a href="my-loans.php" class="btn btn-sm btn-warning">
                                    <i class="fas fa-eye"></i> Voir les détails
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions rapides -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Actions rapides</h5>
                    </div>
                    <div class="card-body">
                        <a href="search.php" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-search"></i> Rechercher des livres
                        </a>
                        <a href="my-loans.php" class="btn btn-info w-100 mb-2">
                            <i class="fas fa-book"></i> Mes emprunts
                        </a>
                        <?php if($user['role'] == 'admin' || $user['role'] == 'librarian'): ?>
                            <a href="../admin/index.php" class="btn btn-warning w-100">
                                <i class="fas fa-cog"></i> Accéder à l'administration
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Mettre à jour l'affichage de la durée
    const slider = document.getElementById('preferred_loan_duration');
    const display = document.getElementById('duration_display');
    
    if(slider && display) {
        slider.addEventListener('input', function() {
            display.textContent = this.value;
        });
    }
</script>

<?php include '../includes/footer.php'; ?>