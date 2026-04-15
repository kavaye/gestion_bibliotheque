<?php
session_start();
require_once '../config/database.php';  // OK - Remonte d'un niveau

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Traitement du retour de livre
if(isset($_POST['return_book'])) {
    $loan_id = $_POST['loan_id'];
    
    $return_date = date('Y-m-d');
    
    // Récupérer les informations de l'emprunt
    $query = "SELECT * FROM loans WHERE id = :loan_id AND user_id = :user_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':loan_id', $loan_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($loan) {
        $is_overdue = $return_date > $loan['due_date'];
        
        // Mettre à jour l'emprunt
        $query = "UPDATE loans SET return_date = :return_date, status = 'returned' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':return_date', $return_date);
        $stmt->bindParam(':id', $loan_id);
        
        if($stmt->execute()) {
            // Mettre à jour la disponibilité du livre
            $query = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = :book_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':book_id', $loan['book_id']);
            $stmt->execute();
            
            // Créer une amende si nécessaire
            if($is_overdue) {
                $days_overdue = (strtotime($return_date) - strtotime($loan['due_date'])) / (60 * 60 * 24);
                $amount = $days_overdue * 0.5; // 0.5€ par jour de retard
                
                $query = "INSERT INTO fines (user_id, loan_id, amount, reason, status) 
                          VALUES (:user_id, :loan_id, :amount, 'Retard de retour', 'pending')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':loan_id', $loan_id);
                $stmt->bindParam(':amount', $amount);
                $stmt->execute();
                
                $_SESSION['warning'] = "Livre retourné avec retard. Une amende de " . number_format($amount, 2) . "€ a été générée.";
            } else {
                $_SESSION['success'] = 'Livre retourné avec succès. Merci !';
            }
        }
    }
    header('Location: my-loans.php');
    exit();
}

// Traitement de la prolongation
if(isset($_POST['extend_loan'])) {
    $loan_id = $_POST['loan_id'];
    
    $query = "SELECT due_date FROM loans WHERE id = :loan_id AND user_id = :user_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':loan_id', $loan_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($loan) {
        $new_due_date = date('Y-m-d', strtotime($loan['due_date'] . ' + 7 days'));
        
        $query = "UPDATE loans SET due_date = :due_date, status = 'extended' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':due_date', $new_due_date);
        $stmt->bindParam(':id', $loan_id);
        
        if($stmt->execute()) {
            $_SESSION['success'] = 'Emprunt prolongé jusqu\'au ' . date('d/m/Y', strtotime($new_due_date));
        }
    }
    header('Location: my-loans.php');
    exit();
}

// Récupérer les filtres
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer la liste des emprunts
$query = "SELECT l.*, b.title as book_title, b.author, b.cover_image, b.isbn,
          CASE 
            WHEN l.due_date < CURDATE() AND l.status = 'active' THEN 'overdue'
            ELSE l.status
          END as current_status
          FROM loans l 
          JOIN books b ON l.book_id = b.id 
          WHERE l.user_id = :user_id";
$params = [':user_id' => $_SESSION['user_id']];

if($status_filter != 'all') {
    if($status_filter == 'overdue') {
        $query .= " AND l.due_date < CURDATE() AND l.status = 'active'";
    } else {
        $query .= " AND l.status = :status";
        $params[':status'] = $status_filter;
    }
}

if($search) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les amendes
$query = "SELECT f.*, l.book_id, b.title as book_title 
          FROM fines f 
          JOIN loans l ON f.loan_id = l.id 
          JOIN books b ON l.book_id = b.id 
          WHERE f.user_id = :user_id 
          ORDER BY f.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$fines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN due_date < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
            SUM(CASE WHEN status = 'extended' THEN 1 ELSE 0 END) as extended
          FROM loans 
          WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Inclure le header avec le bon chemin
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Mes Emprunts</h1>
        
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
                                <h2 class="mb-0"><?php echo $stats['overdue']; ?></h2>
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
        
        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tous</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>En cours</option>
                            <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>En retard</option>
                            <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>Retournés</option>
                            <option value="extended" <?php echo $status_filter == 'extended' ? 'selected' : ''; ?>>Prolongés</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Titre ou auteur..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Liste des emprunts -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Historique des emprunts</h5>
            </div>
            <div class="card-body">
                <?php if(empty($loans)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucun emprunt trouvé.
                        <br>
                        <a href="search.php" class="btn btn-primary mt-3">
                            <i class="fas fa-search"></i> Rechercher des livres
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Livre</th>
                                    <th>Auteur</th>
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
                                $is_overdue = ($loan['current_status'] == 'overdue');
                                $statusClass = '';
                                $statusText = '';
                                
                                switch($loan['current_status']) {
                                    case 'active':
                                        $statusClass = 'bg-info';
                                        $statusText = 'En cours';
                                        break;
                                    case 'overdue':
                                        $statusClass = 'bg-warning';
                                        $statusText = 'En retard';
                                        break;
                                    case 'returned':
                                        $statusClass = 'bg-success';
                                        $statusText = 'Retourné';
                                        break;
                                    case 'extended':
                                        $statusClass = 'bg-primary';
                                        $statusText = 'Prolongé';
                                        break;
                                }
                                
                                // Calculer les jours restants ou de retard
                                $days_diff = '';
                                if($loan['status'] == 'active') {
                                    $due_date = new DateTime($loan['due_date']);
                                    $today = new DateTime();
                                    $interval = $today->diff($due_date);
                                    
                                    if($is_overdue) {
                                        $days_diff = '<span class="text-danger">Retard de ' . $interval->days . ' jours</span>';
                                    } else {
                                        $days_diff = '<span class="text-success">' . $interval->days . ' jours restants</span>';
                                    }
                                }
                                ?>
                                <tr class="<?php echo $is_overdue ? 'table-warning' : ''; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($loan['book_title']); ?></strong>
                                        <br>
                                        <small class="text-muted">ISBN: <?php echo htmlspecialchars($loan['isbn']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($loan['author']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($loan['borrow_date'])); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($loan['due_date'])); ?>
                                        <?php if($days_diff): ?>
                                            <br><small><?php echo $days_diff; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $loan['return_date'] ? date('d/m/Y', strtotime($loan['return_date'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td>
                                        <?php if($loan['status'] == 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button type="submit" name="return_book" class="btn btn-sm btn-success mb-1" 
                                                        onclick="return confirm('Confirmer le retour de ce livre ?')">
                                                    <i class="fas fa-undo"></i> Retourner
                                                </button>
                                            </form>
                                            
                                            <?php if(!$is_overdue): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                    <button type="submit" name="extend_loan" class="btn btn-sm btn-info mb-1"
                                                            onclick="return confirm('Prolonger cet emprunt de 7 jours ?')">
                                                        <i class="fas fa-clock"></i> Prolonger
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section des amendes -->
        <?php if(!empty($fines)): ?>
        <div class="card mt-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Mes amendes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Livre</th>
                                <th>Montant</th>
                                <th>Raison</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_pending = 0;
                            foreach($fines as $fine): 
                                if($fine['status'] == 'pending') {
                                    $total_pending += $fine['amount'];
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fine['book_title']); ?></td>
                                <td class="text-danger fw-bold"><?php echo number_format($fine['amount'], 2); ?> €</td>
                                <td><?php echo htmlspecialchars($fine['reason']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($fine['created_at'])); ?></td>
                                <td>
                                    <?php if($fine['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">En attente</span>
                                    <?php elseif($fine['status'] == 'paid'): ?>
                                        <span class="badge bg-success">Payée</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Annulée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if($total_pending > 0): ?>
                        <tfoot class="table-danger">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total à payer:</td>
                                <td class="fw-bold"><?php echo number_format($total_pending, 2); ?> €</td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                
                <?php if($total_pending > 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Veuillez vous présenter à l'accueil de la bibliothèque pour régler vos amendes.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Fonction pour actualiser les statistiques après un retour
function updateStats() {
    location.reload();
}
</script>

<?php include '../includes/footer.php'; ?>