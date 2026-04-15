<?php
require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$libFunc = new LibraryFunctions($db);

$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$results = [];

if($keyword) {
    $results = $libFunc->searchBooks($keyword, $category);
}

// Récupérer les catégories
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Recherche de livres</h1>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Rechercher</label>
                        <input type="text" class="form-control" name="keyword" 
                               placeholder="Titre, auteur, ISBN..." 
                               value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Catégorie</label>
                        <select class="form-control" name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if($keyword): ?>
            <h3>Résultats de recherche (<?php echo count($results); ?> livres trouvés)</h3>
            
            <div class="row">
                <?php foreach($results as $book): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <?php echo htmlspecialchars($book['author']); ?>
                                </h6>
                                <p class="card-text">
                                    <small>
                                        <strong>Catégorie:</strong> <?php echo htmlspecialchars($book['category_name']); ?><br>
                                        <strong>Année:</strong> <?php echo $book['publication_year']; ?><br>
                                        <strong>Disponible:</strong> <?php echo $book['available_quantity']; ?>/<?php echo $book['quantity']; ?>
                                    </small>
                                </p>
                                <?php if(isset($_SESSION['user_id'])): ?>
                                    <?php if($book['available_quantity'] > 0): ?>
                                        <button class="btn btn-sm btn-primary borrow-book" 
                                                data-book-id="<?php echo $book['id']; ?>"
                                                data-book-title="<?php echo htmlspecialchars($book['title']); ?>">
                                            <i class="fas fa-hand-holding"></i> Emprunter
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-times"></i> Indisponible
                                        </button>
                                    <?php endif; ?>
                                    <a href="book-details.php?id=<?php echo $book['id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-info-circle"></i> Détails
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmation d'emprunt -->
<div class="modal fade" id="borrowModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer l'emprunt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Voulez-vous vraiment emprunter <strong id="bookTitle"></strong> ?</p>
                <p class="text-muted">La durée d'emprunt est de 14 jours.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmBorrow">
                    <i class="fas fa-check"></i> Confirmer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let selectedBookId = null;
    
    $('.borrow-book').click(function() {
        selectedBookId = $(this).data('book-id');
        $('#bookTitle').text($(this).data('book-title'));
        $('#borrowModal').modal('show');
    });
    
    $('#confirmBorrow').click(function() {
        $.ajax({
            url: '/api/borrow.php',
            method: 'POST',
            data: { book_id: selectedBookId },
            success: function(response) {
                if(response.success) {
                    alert('Livre emprunté avec succès !');
                    location.reload();
                } else {
                    alert('Erreur : ' + response.message);
                }
                $('#borrowModal').modal('hide');
            },
            error: function() {
                alert('Erreur lors de l\'emprunt');
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>