<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est admin (seul admin peut gérer les utilisateurs)
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Ajouter un utilisateur
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role, status, registration_date) 
              VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, :role, :status, CURDATE())";
    
    $stmt = $db->prepare($query);
    
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt->bindParam(':username', $_POST['username']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':first_name', $_POST['first_name']);
    $stmt->bindParam(':last_name', $_POST['last_name']);
    $stmt->bindParam(':phone', $_POST['phone']);
    $stmt->bindParam(':address', $_POST['address']);
    $stmt->bindParam(':role', $_POST['role']);
    $stmt->bindParam(':status', $_POST['status']);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Utilisateur ajouté avec succès';
        header('Location: users.php');
        exit();
    } else {
        $error = 'Erreur lors de l\'ajout de l\'utilisateur';
    }
}

// Modifier un utilisateur
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit' && $id) {
    $query = "UPDATE users SET first_name=:first_name, last_name=:last_name, email=:email, 
              phone=:phone, address=:address, role=:role, status=:status";
    
    // Ajouter le mot de passe si fourni
    $params = [
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':address' => $_POST['address'],
        ':role' => $_POST['role'],
        ':status' => $_POST['status'],
        ':id' => $id
    ];
    
    if(!empty($_POST['password'])) {
        $query .= ", password=:password";
        $params[':password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
    
    $query .= " WHERE id=:id";
    
    $stmt = $db->prepare($query);
    foreach($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Utilisateur modifié avec succès';
        header('Location: users.php');
        exit();
    } else {
        $error = 'Erreur lors de la modification';
    }
}

// Supprimer un utilisateur
if($action == 'delete' && $id) {
    // Vérifier si l'utilisateur a des emprunts
    $query = "SELECT COUNT(*) as count FROM loans WHERE user_id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result['count'] > 0) {
        $_SESSION['error'] = 'Impossible de supprimer un utilisateur avec des emprunts en cours';
    } elseif($id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'Impossible de supprimer votre propre compte';
    } else {
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if($stmt->execute()) {
            $_SESSION['success'] = 'Utilisateur supprimé avec succès';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression';
        }
    }
    header('Location: users.php');
    exit();
}

// Changer le statut
if($action == 'toggle' && $id) {
    $query = "UPDATE users SET status = IF(status = 'active', 'suspended', 'active') WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header('Location: users.php');
    exit();
}

// Réinitialiser le mot de passe
if($action == 'reset_password' && $id) {
    $new_password = bin2hex(random_bytes(4)); // Génère un mot de passe aléatoire de 8 caractères
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $query = "UPDATE users SET password = :password WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "Mot de passe réinitialisé. Nouveau mot de passe: $new_password";
    } else {
        $_SESSION['error'] = 'Erreur lors de la réinitialisation';
    }
    header('Location: users.php');
    exit();
}

// Récupérer l'utilisateur pour modification
$user = null;
if($action == 'edit' && $id) {
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer la liste des utilisateurs
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM users WHERE 1=1";
$conditions = [];
$params = [];

if($search) {
    $conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR username LIKE :search)";
    $params[':search'] = "%$search%";
}
if($role_filter) {
    $conditions[] = "role = :role";
    $params[':role'] = $role_filter;
}
if($status_filter) {
    $conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

if(count($conditions) > 0) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'librarian' THEN 1 ELSE 0 END) as librarians,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
          FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Gestion des Utilisateurs</h1>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($action == 'add' || ($action == 'edit' && $user)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $action == 'add' ? 'Ajouter un utilisateur' : 'Modifier l\'utilisateur'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prénom *</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?php echo $user ? htmlspecialchars($user['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?php echo $user ? htmlspecialchars($user['last_name']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <?php if($action == 'add'): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nom d'utilisateur *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mot de passe *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" class="form-control" name="password">
                            <small class="text-muted">Minimum 6 caractères</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo $user ? htmlspecialchars($user['phone']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rôle *</label>
                                <select class="form-control" name="role" required>
                                    <option value="user" <?php echo ($user && $user['role'] == 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                    <option value="librarian" <?php echo ($user && $user['role'] == 'librarian') ? 'selected' : ''; ?>>Bibliothécaire</option>
                                    <option value="admin" <?php echo ($user && $user['role'] == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo $user ? htmlspecialchars($user['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Statut *</label>
                            <select class="form-control" name="status" required>
                                <option value="active" <?php echo ($user && $user['status'] == 'active') ? 'selected' : ''; ?>>Actif</option>
                                <option value="suspended" <?php echo ($user && $user['status'] == 'suspended') ? 'selected' : ''; ?>>Suspendu</option>
                                <option value="inactive" <?php echo ($user && $user['status'] == 'inactive') ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="users.php" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Utilisateurs</h6>
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                </div>
                                <i class="fas fa-users fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Utilisateurs Actifs</h6>
                                    <h2 class="mb-0"><?php echo $stats['active']; ?></h2>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Administrateurs</h6>
                                    <h2 class="mb-0"><?php echo $stats['admins']; ?></h2>
                                </div>
                                <i class="fas fa-user-shield fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Bibliothécaires</h6>
                                    <h2 class="mb-0"><?php echo $stats['librarians']; ?></h2>
                                </div>
                                <i class="fas fa-user-tie fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <a href="users.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter un utilisateur
                            </a>
                        </div>
                        <div class="col-md-8">
                            <form method="GET" class="row g-2">
                                <div class="col-md-5">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="role" class="form-control">
                                        <option value="">Tous les rôles</option>
                                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="librarian" <?php echo $role_filter == 'librarian' ? 'selected' : ''; ?>>Bibliothécaire</option>
                                        <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-control">
                                        <option value="">Statut</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Actif</option>
                                        <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspendu</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-search"></i> Filtrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom d'utilisateur</th>
                                    <th>Nom complet</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Date inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td>
                                        <?php if($user['role'] == 'admin'): ?>
                                            <span class="badge bg-danger">Administrateur</span>
                                        <?php elseif($user['role'] == 'librarian'): ?>
                                            <span class="badge bg-info">Bibliothécaire</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Utilisateur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($user['status'] == 'active'): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php elseif($user['status'] == 'suspended'): ?>
                                            <span class="badge bg-warning">Suspendu</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user['registration_date']; ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="users.php?action=toggle&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-<?php echo $user['status'] == 'active' ? 'secondary' : 'success'; ?>" 
                                                   title="<?php echo $user['status'] == 'active' ? 'Suspendre' : 'Activer'; ?>">
                                                    <i class="fas fa-<?php echo $user['status'] == 'active' ? 'ban' : 'check'; ?>"></i>
                                                </a>
                                                <a href="users.php?action=reset_password&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   title="Réinitialiser le mot de passe"
                                                   onclick="return confirm('Réinitialiser le mot de passe de cet utilisateur ? Un nouveau mot de passe sera généré.')">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                                <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if(empty($users)): ?>
                        <div class="alert alert-info text-center">
                            Aucun utilisateur trouvé.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>