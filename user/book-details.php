<!-- Dans user/book-details.php, ajouter un choix de durée personnalisé -->
<?php if(isset($_SESSION['user_id']) && $book['available_quantity'] > 0): ?>
    <div class="card mt-3">
        <div class="card-header">
            <h6>Choisir la durée d'emprunt</h6>
        </div>
        <div class="card-body">
            <form method="POST" id="borrowForm">
                <div class="mb-3">
                    <label class="form-label">Durée d'emprunt (jours)</label>
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <input type="range" class="form-range" 
                                   id="loan_duration" 
                                   name="loan_duration" 
                                   min="1" 
                                   max="<?php echo $user['max_loan_duration']; ?>" 
                                   step="1" 
                                   value="<?php echo $user['preferred_loan_duration']; ?>">
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="fw-bold text-primary" id="duration_value">
                                <?php echo $user['preferred_loan_duration']; ?>
                            </span> jours
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Date de retour prévue: <strong id="return_date"></strong>
                            <br>
                            Durée maximale autorisée: <?php echo $user['max_loan_duration']; ?> jours
                        </small>
                    </div>
                </div>
                <input type="hidden" name="due_date" id="due_date">
                <button type="submit" name="borrow" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-hand-holding"></i> Emprunter ce livre
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Calculer et afficher la date de retour
        const durationSlider = document.getElementById('loan_duration');
        const durationValue = document.getElementById('duration_value');
        const returnDateSpan = document.getElementById('return_date');
        const dueDateInput = document.getElementById('due_date');
        
        function updateReturnDate() {
            const days = parseInt(durationSlider.value);
            const today = new Date();
            const returnDate = new Date(today);
            returnDate.setDate(today.getDate() + days);
            
            const formattedDate = returnDate.toLocaleDateString('fr-FR');
            returnDateSpan.textContent = formattedDate;
            dueDateInput.value = returnDate.toISOString().split('T')[0];
            durationValue.textContent = days;
        }
        
        durationSlider.addEventListener('input', updateReturnDate);
        updateReturnDate();
    </script>
<?php endif; ?>

<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$book_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Récupérer les détails du livre
$query = "SELECT b.*, c.name as category_name 
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          WHERE b.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $book_id);
$stmt->execute();
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$book) {
    header('Location: search.php');
    exit();
}

 // Dans user/book-details.php, modifier la partie emprunt
if(isset($_POST['borrow']) && isset($_SESSION['user_id'])) {
    require_once '../includes/functions.php';
    $libFunc = new LibraryFunctions($db);
    
    // Récupérer la durée préférée de l'utilisateur
    $query = "SELECT preferred_loan_duration FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user_duration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $loan_duration = $user_duration['preferred_loan_duration'];
    $due_date = date('Y-m-d', strtotime("+$loan_duration days"));
    
    $result = $libFunc->borrowBook($_SESSION['user_id'], $book_id, $due_date);
    if($result['success']) {
        $_SESSION['success'] = $result['message'] . " (Durée: $loan_duration jours)";
        header('Location: my-loans.php');
        exit();
    } else {
        $error = $result['message'];
    }
}
// Récupérer les avis
$query = "SELECT r.*, u.first_name, u.last_name 
          FROM reviews r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.book_id = :book_id 
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':book_id', $book_id);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer la note moyenne
$avg_rating = 0;
if(count($reviews) > 0) {
    $total = 0;
    foreach($reviews as $review) {
        $total += $review['rating'];
    }
    $avg_rating = $total / count($reviews);
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="search.php">Recherche</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($book['title']); ?></li>
            </ol>
        </nav>
        
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="book-cover bg-light p-5 rounded">
                            <i class="fas fa-book fa-5x text-primary"></i>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                        <h4 class="text-muted"><?php echo htmlspecialchars($book['author']); ?></h4>
                        
                        <div class="mb-3">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted">(<?php echo count($reviews); ?> avis)</span>
                        </div>
                        
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">Catégorie:</th>
                                <td><?php echo htmlspecialchars($book['category_name']); ?></td>
                            </tr>
                            <tr>
                                <th>ISBN:</th>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                            </tr>
                            <tr>
                                <th>Éditeur:</th>
                                <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                            </tr>
                            <tr>
                                <th>Année:</th>
                                <td><?php echo $book['publication_year']; ?></td>
                            </tr>
                            <tr>
                                <th>Emplacement:</th>
                                <td><?php echo htmlspecialchars($book['location']); ?></td>
                            </tr>
                            <tr>
                                <th>Disponibilité:</th>
                                <td>
                                    <?php if($book['available_quantity'] > 0): ?>
                                        <span class="badge bg-success">
                                            <?php echo $book['available_quantity']; ?> exemplaire(s) disponible(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Indisponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <?php if($book['available_quantity'] > 0): ?>
                                <form method="POST">
                                    <button type="submit" name="borrow" class="btn btn-primary btn-lg">
                                        <i class="fas fa-hand-holding"></i> Emprunter ce livre
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg" disabled>
                                    <i class="fas fa-times"></i> Indisponible
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Connectez-vous pour emprunter
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <hr>
                
                <h4>Description</h4>
                <p><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
            </div>
        </div>
        
        <!-- Section des avis -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Avis des lecteurs</h5>
            </div>
            <div class="card-body">
                <?php if(empty($reviews)): ?>
                    <p class="text-muted">Aucun avis pour ce livre pour le moment.</p>
                <?php else: ?>
                    <?php foreach($reviews as $review): ?>
                        <div class="review-item mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></small>
                            </div>
                            <div class="mb-2">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>