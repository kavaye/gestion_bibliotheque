<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est admin
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'librarian')) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Ajouter un membre
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role, status, registration_date) 
              VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, :role, 'active', CURDATE())";
    
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
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Membre ajouté avec succès';
        header('Location: members.php');
        exit();
    } else {
        $error = 'Erreur lors de l\'ajout du membre';
    }
}

// Modifier un membre
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit' && $id) {
    $query = "UPDATE users SET first_name=:first_name, last_name=:last_name, email=:email, 
              phone=:phone, address=:address, role=:role, status=:status WHERE id=:id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':first_name', $_POST['first_name']);
    $stmt->bindParam(':last_name', $_POST['last_name']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':phone', $_POST['phone']);
    $stmt->bindParam(':address', $_POST['address']);
    $stmt->bindParam(':role', $_POST['role']);
    $stmt->bindParam(':status', $_POST['status']);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Membre modifié avec succès';
        header('Location: members.php');
        exit();
    } else {
        $error = 'Erreur lors de la modification';
    }
}

// Supprimer un membre
if($action == 'delete' && $id) {
    // Vérifier si le membre a des emprunts en cours
    $query = "SELECT COUNT(*) as count FROM loans WHERE user_id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result['count'] > 0) {
        $_SESSION['error'] = 'Impossible de supprimer un membre avec des emprunts en cours';
    } else {
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if($stmt->execute()) {
            $_SESSION['success'] = 'Membre supprimé avec succès';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression';
        }
    }
    header('Location: members.php');
    exit();
}

// Suspendre/Réactiver un membre
if($action == 'toggle' && $id) {
    $query = "UPDATE users SET status = IF(status = 'active', 'suspended', 'active') WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header('Location: members.php');
    exit();
}

// Récupérer le membre pour modification
$member = null;
if($action == 'edit' && $id) {
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer la liste des membres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM users WHERE role != 'admin'";
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
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
            SUM(CASE WHEN role = 'librarian' THEN 1 ELSE 0 END) as librarians
          FROM users WHERE role != 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Gestion des Membres</h1>
        
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
        
        <?php if($action == 'add' || ($action == 'edit' && $member)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $action == 'add' ? 'Ajouter un membre' : 'Modifier le membre'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prénom *</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?php echo $member ? htmlspecialchars($member['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?php echo $member ? htmlspecialchars($member['last_name']) : ''; ?>" required>
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
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo $member ? htmlspecialchars($member['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo $member ? htmlspecialchars($member['phone']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rôle</label>
                                <select class="form-control" name="role">
                                    <option value="user" <?php echo ($member && $member['role'] == 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                    <option value="librarian" <?php echo ($member && $member['role'] == 'librarian') ? 'selected' : ''; ?>>Bibliothécaire</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo $member ? htmlspecialchars($member['address']) : ''; ?></textarea>
                        </div>
                        
                        <?php if($action == 'edit'): ?>
                        <div class="mb-3">
                            <label class="form-label">Statut</label>
                            <select class="form-control" name="status">
                                <option value="active" <?php echo ($member && $member['status'] == 'active') ? 'selected' : ''; ?>>Actif</option>
                                <option value="suspended" <?php echo ($member && $member['status'] == 'suspended') ? 'selected' : ''; ?>>Suspendu</option>
                                <option value="inactive" <?php echo ($member && $member['status'] == 'inactive') ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="members.php" class="btn btn-secondary">Annuler</a>
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
                                    <h6 class="card-title">Total Membres</h6>
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
                                    <h6 class="card-title">Membres Actifs</h6>
                                    <h2 class="mb-0"><?php echo $stats['active']; ?></h2>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Membres Suspendus</h6>
                                    <h2 class="mb-0"><?php echo $stats['suspended']; ?></h2>
                                </div>
                                <i class="fas fa-ban fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
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
                            <a href="members.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter un membre
                            </a>
                        </div>
                        <div class="col-md-8">
                            <form method="GET" class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="role" class="form-control">
                                        <option value="">Tous les rôles</option>
                                        <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                                        <option value="librarian" <?php echo $role_filter == 'librarian' ? 'selected' : ''; ?>>Bibliothécaire</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="status" class="form-control">
                                        <option value="">Tous les statuts</option>
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
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Date inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($members as $member): ?>
                                <tr>
                                    <td><?php echo $member['id']; ?></td>
                                    <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                    <td>
                                        <?php if($member['role'] == 'admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php elseif($member['role'] == 'librarian'): ?>
                                            <span class="badge bg-info">Bibliothécaire</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Utilisateur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($member['status'] == 'active'): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php elseif($member['status'] == 'suspended'): ?>
                                            <span class="badge bg-warning">Suspendu</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $member['registration_date']; ?></td>
                                    <td>
                                        <a href="members.php?action=edit&id=<?php echo $member['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="members.php?action=toggle&id=<?php echo $member['id']; ?>" 
                                           class="btn btn-sm btn-<?php echo $member['status'] == 'active' ? 'secondary' : 'success'; ?>">
                                            <i class="fas fa-<?php echo $member['status'] == 'active' ? 'ban' : 'check'; ?>"></i>
                                        </a>
                                        <a href="members.php?action=delete&id=<?php echo $member['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce membre ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>