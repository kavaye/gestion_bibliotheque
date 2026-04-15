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

// Ajouter un emprunt
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $user_id = $_POST['user_id'];
    $book_id = $_POST['book_id'];
    $due_date = $_POST['due_date'];
    $borrow_date = date('Y-m-d');
    
    // Vérifier la disponibilité du livre
    $query = "SELECT available_quantity, title FROM books WHERE id = :book_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':book_id', $book_id);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($book['available_quantity'] <= 0) {
        $_SESSION['error'] = 'Ce livre n\'est pas disponible';
        header('Location: loans.php');
        exit();
    }
    
    // Vérifier les emprunts en cours du membre
    $query = "SELECT COUNT(*) as count FROM loans WHERE user_id = :user_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $active_loans = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($active_loans['count'] >= 5) {
        $_SESSION['error'] = 'Ce membre a déjà 5 livres empruntés';
        header('Location: loans.php');
        exit();
    }
    
    // Créer l'emprunt
    $query = "INSERT INTO loans (user_id, book_id, borrow_date, due_date, status) 
              VALUES (:user_id, :book_id, :borrow_date, :due_date, 'active')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':book_id', $book_id);
    $stmt->bindParam(':borrow_date', $borrow_date);
    $stmt->bindParam(':due_date', $due_date);
    
    if($stmt->execute()) {
        // Mettre à jour la disponibilité
        $query = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = :book_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        
        $_SESSION['success'] = 'Emprunt enregistré avec succès';
    } else {
        $_SESSION['error'] = 'Erreur lors de l\'enregistrement';
    }
    header('Location: loans.php');
    exit();
}

// Retourner un livre
if($action == 'return' && $id) {
    $return_date = date('Y-m-d');
    
    $query = "SELECT * FROM loans WHERE id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($loan) {
        $is_overdue = $return_date > $loan['due_date'];
        
        // Mettre à jour l'emprunt
        $query = "UPDATE loans SET return_date = :return_date, status = 'returned' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':return_date', $return_date);
        $stmt->bindParam(':id', $id);
        
        if($stmt->execute()) {
            // Mettre à jour la disponibilité du livre
            $query = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = :book_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':book_id', $loan['book_id']);
            $stmt->execute();
            
            // Créer une amende si nécessaire
            if($is_overdue) {
                $days_overdue = (strtotime($return_date) - strtotime($loan['due_date'])) / (60 * 60 * 24);
                $amount = $days_overdue * 0.5; // 0.5€ par jour
                
                $query = "INSERT INTO fines (user_id, loan_id, amount, reason, status) 
                          VALUES (:user_id, :loan_id, :amount, 'Retard de retour', 'pending')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $loan['user_id']);
                $stmt->bindParam(':loan_id', $id);
                $stmt->bindParam(':amount', $amount);
                $stmt->execute();
                
                $_SESSION['warning'] = "Livre retourné avec retard. Amende de $amount€ générée.";
            } else {
                $_SESSION['success'] = 'Livre retourné avec succès';
            }
        }
    }
    header('Location: loans.php');
    exit();
}

// Prolonger un emprunt
if($action == 'extend' && $id) {
    $query = "SELECT due_date FROM loans WHERE id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($loan) {
        $new_due_date = date('Y-m-d', strtotime($loan['due_date'] . ' + 7 days'));
        
        $query = "UPDATE loans SET due_date = :due_date, status = 'extended' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':due_date', $new_due_date);
        $stmt->bindParam(':id', $id);
        
        if($stmt->execute()) {
            $_SESSION['success'] = 'Emprunt prolongé jusqu\'au ' . $new_due_date;
        }
    }
    header('Location: loans.php');
    exit();
}

// Supprimer un emprunt
if($action == 'delete' && $id) {
    $query = "DELETE FROM loans WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if($stmt->execute()) {
        $_SESSION['success'] = 'Emprunt supprimé avec succès';
    } else {
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: loans.php');
    exit();
}

// Récupérer la liste des emprunts
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT l.*, b.title as book_title, b.author, u.first_name, u.last_name, u.email 
          FROM loans l 
          JOIN books b ON l.book_id = b.id 
          JOIN users u ON l.user_id = u.id 
          WHERE 1=1";
$conditions = [];
$params = [];

if($search) {
    $conditions[] = "(b.title LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if($status_filter) {
    $conditions[] = "l.status = :status";
    $params[':status'] = $status_filter;
}

if(count($conditions) > 0) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les livres disponibles pour le formulaire
$query = "SELECT * FROM books WHERE available_quantity > 0 ORDER BY title";
$stmt = $db->prepare($query);
$stmt->execute();
$available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les membres actifs
$query = "SELECT * FROM users WHERE role != 'admin' AND status = 'active' ORDER BY first_name";
$stmt = $db->prepare($query);
$stmt->execute();
$active_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
            SUM(CASE WHEN due_date < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as overdue_count
          FROM loans";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Gestion des Emprunts</h1>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($action == 'add'): ?>
            <div class="card">
                <div class="card-header">
                    <h5>Nouvel emprunt</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Membre *</label>
                                <select class="form-control" name="user_id" required>
                                    <option value="">Sélectionner un membre</option>
                                    <?php foreach($active_members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' - ' . $member['email']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Livre *</label>
                                <select class="form-control" name="book_id" required>
                                    <option value="">Sélectionner un livre</option>
                                    <?php foreach($available_books as $book): ?>
                                        <option value="<?php echo $book['id']; ?>">
                                            <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author'] . ' (' . $book['available_quantity'] . ' disponibles)'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Date de retour prévue</label>
                            <input type="date" class="form-control" name="due_date" 
                                   value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                            <small class="text-muted">Durée d'emprunt standard: 14 jours</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer l'emprunt
                        </button>
                        <a href="loans.php" class="btn btn-secondary">Annuler</a>
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
                                    <h6 class="card-title">Total Emprunts</h6>
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                </div>
                                <i class="fas fa-exchange-alt fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">En cours</h6>
                                    <h2 class="mb-0"><?php echo $stats['active']; ?></h2>
                                </div>
                                <i class="fas fa-clock fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">En retard</h6>
                                    <h2 class="mb-0"><?php echo $stats['overdue_count']; ?></h2>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Retournés</h6>
                                    <h2 class="mb-0"><?php echo $stats['returned']; ?></h2>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <a href="loans.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Nouvel emprunt
                            </a>
                        </div>
                        <div class="col-md-9">
                            <form method="GET" class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Rechercher par livre ou membre..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="status" class="form-control">
                                        <option value="">Tous les statuts</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>En cours</option>
                                        <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>En retard</option>
                                        <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>Retournés</option>
                                        <option value="extended" <?php echo $status_filter == 'extended' ? 'selected' : ''; ?>>Prolongés</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
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
                                    <th>Livre</th>
                                    <th>Membre</th>
                                    <th>Date d'emprunt</th>
                                    <th>Date de retour prévue</th>
                                    <th>Date de retour</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($loans as $loan): ?>
                                <?php 
                                $is_overdue = ($loan['status'] == 'active' && $loan['due_date'] < date('Y-m-d'));
                                $statusClass = '';
                                $statusText = '';
                                
                                if($loan['status'] == 'active') {
                                    $statusClass = $is_overdue ? 'bg-warning' : 'bg-info';
                                    $statusText = $is_overdue ? 'En retard' : 'En cours';
                                } elseif($loan['status'] == 'returned') {
                                    $statusClass = 'bg-success';
                                    $statusText = 'Retourné';
                                } elseif($loan['status'] == 'extended') {
                                    $statusClass = 'bg-primary';
                                    $statusText = 'Prolongé';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $loan['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($loan['book_title']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($loan['author']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($loan['email']); ?></small>
                                    </td>
                                    <td><?php echo $loan['borrow_date']; ?></td>
                                    <td <?php echo $is_overdue ? 'class="text-danger fw-bold"' : ''; ?>>
                                        <?php echo $loan['due_date']; ?>
                                        <?php if($is_overdue): ?>
                                            <br><small>En retard</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $loan['return_date'] ? $loan['return_date'] : '-'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td>
                                        <?php if($loan['status'] == 'active'): ?>
                                            <a href="loans.php?action=return&id=<?php echo $loan['id']; ?>" 
                                               class="btn btn-sm btn-success mb-1"
                                               onclick="return confirm('Confirmer le retour de ce livre ?')">
                                                <i class="fas fa-undo"></i> Retour
                                            </a>
                                            <a href="loans.php?action=extend&id=<?php echo $loan['id']; ?>" 
                                               class="btn btn-sm btn-info mb-1"
                                               onclick="return confirm('Prolonger cet emprunt de 7 jours ?')">
                                                <i class="fas fa-clock"></i> Prolonger
                                            </a>
                                        <?php endif; ?>
                                        <?php if($_SESSION['role'] == 'admin'): ?>
                                            <a href="loans.php?action=delete&id=<?php echo $loan['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emprunt ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
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