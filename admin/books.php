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

// Ajouter un livre
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $query = "INSERT INTO books (title, author, isbn, category_id, publisher, publication_year, 
              description, quantity, available_quantity, location) 
              VALUES (:title, :author, :isbn, :category_id, :publisher, :publication_year, 
              :description, :quantity, :quantity, :location)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $_POST['title']);
    $stmt->bindParam(':author', $_POST['author']);
    $stmt->bindParam(':isbn', $_POST['isbn']);
    $stmt->bindParam(':category_id', $_POST['category_id']);
    $stmt->bindParam(':publisher', $_POST['publisher']);
    $stmt->bindParam(':publication_year', $_POST['publication_year']);
    $stmt->bindParam(':description', $_POST['description']);
    $stmt->bindParam(':quantity', $_POST['quantity']);
    $stmt->bindParam(':location', $_POST['location']);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Livre ajouté avec succès';
        header('Location: books.php');
        exit();
    } else {
        $error = 'Erreur lors de l\'ajout du livre';
    }
}

// Modifier un livre
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit' && $id) {
    // Récupérer l'ancienne quantité pour ajuster la disponibilité
    $query = "SELECT quantity, available_quantity FROM books WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $old_book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_quantity = $_POST['quantity'];
    $difference = $new_quantity - $old_book['quantity'];
    $new_available = $old_book['available_quantity'] + $difference;
    
    $query = "UPDATE books SET title=:title, author=:author, isbn=:isbn, category_id=:category_id,
              publisher=:publisher, publication_year=:publication_year, description=:description,
              quantity=:quantity, available_quantity=:available_quantity, location=:location 
              WHERE id=:id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $_POST['title']);
    $stmt->bindParam(':author', $_POST['author']);
    $stmt->bindParam(':isbn', $_POST['isbn']);
    $stmt->bindParam(':category_id', $_POST['category_id']);
    $stmt->bindParam(':publisher', $_POST['publisher']);
    $stmt->bindParam(':publication_year', $_POST['publication_year']);
    $stmt->bindParam(':description', $_POST['description']);
    $stmt->bindParam(':quantity', $_POST['quantity']);
    $stmt->bindParam(':available_quantity', $new_available);
    $stmt->bindParam(':location', $_POST['location']);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Livre modifié avec succès';
        header('Location: books.php');
        exit();
    } else {
        $error = 'Erreur lors de la modification';
    }
}

// Supprimer un livre
if($action == 'delete' && $id) {
    // Vérifier si le livre est emprunté
    $query = "SELECT COUNT(*) as count FROM loans WHERE book_id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result['count'] > 0) {
        $_SESSION['error'] = 'Impossible de supprimer un livre actuellement emprunté';
    } else {
        $query = "DELETE FROM books WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if($stmt->execute()) {
            $_SESSION['success'] = 'Livre supprimé avec succès';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression';
        }
    }
    header('Location: books.php');
    exit();
}

// Récupérer les catégories pour le formulaire
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le livre pour modification
$book = null;
if($action == 'edit' && $id) {
    $query = "SELECT * FROM books WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer la liste des livres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$query = "SELECT b.*, c.name as category_name 
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          WHERE b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search";
$params = [':search' => "%$search%"];

if($category_filter) {
    $query .= " AND b.category_id = :category";
    $params[':category'] = $category_filter;
}

$query .= " ORDER BY b.title ASC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$query = "SELECT 
            COUNT(*) as total,
            SUM(available_quantity) as available,
            SUM(quantity) as total_quantity,
            COUNT(DISTINCT category_id) as categories_count
          FROM books";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Gestion des Livres</h1>
        
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
        
        <?php if($action == 'add' || ($action == 'edit' && $book)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $action == 'add' ? 'Ajouter un livre' : 'Modifier le livre'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Titre *</label>
                                <input type="text" class="form-control" name="title" 
                                       value="<?php echo $book ? htmlspecialchars($book['title']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Auteur *</label>
                                <input type="text" class="form-control" name="author" 
                                       value="<?php echo $book ? htmlspecialchars($book['author']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ISBN</label>
                                <input type="text" class="form-control" name="isbn" 
                                       value="<?php echo $book ? htmlspecialchars($book['isbn']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Catégorie</label>
                                <select class="form-control" name="category_id">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo ($book && $book['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Maison d'édition</label>
                                <input type="text" class="form-control" name="publisher" 
                                       value="<?php echo $book ? htmlspecialchars($book['publisher']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Année de publication</label>
                                <input type="number" class="form-control" name="publication_year" 
                                       value="<?php echo $book ? $book['publication_year'] : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantité *</label>
                                <input type="number" class="form-control" name="quantity" 
                                       value="<?php echo $book ? $book['quantity'] : '1'; ?>" min="1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emplacement</label>
                                <input type="text" class="form-control" name="location" 
                                       value="<?php echo $book ? htmlspecialchars($book['location']) : ''; ?>"
                                       placeholder="Ex: Étagère A1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $book ? htmlspecialchars($book['description']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="books.php" class="btn btn-secondary">Annuler</a>
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
                                    <h6 class="card-title">Total Livres</h6>
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                </div>
                                <i class="fas fa-book fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Exemplaires disponibles</h6>
                                    <h2 class="mb-0"><?php echo $stats['available']; ?></h2>
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
                                    <h6 class="card-title">Total exemplaires</h6>
                                    <h2 class="mb-0"><?php echo $stats['total_quantity']; ?></h2>
                                </div>
                                <i class="fas fa-layer-group fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Catégories</h6>
                                    <h2 class="mb-0"><?php echo $stats['categories_count']; ?></h2>
                                </div>
                                <i class="fas fa-tags fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <a href="books.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter un livre
                            </a>
                        </div>
                        <div class="col-md-9">
                            <form method="GET" class="row g-2">
                                <div class="col-md-5">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Rechercher par titre, auteur, ISBN..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4">
                                    <select name="category" class="form-control">
                                        <option value="">Toutes les catégories</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
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
                                    <th>Titre</th>
                                    <th>Auteur</th>
                                    <th>Catégorie</th>
                                    <th>ISBN</th>
                                    <th>Quantité</th>
                                    <th>Disponible</th>
                                    <th>Emplacement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($books as $book): ?>
                                <tr>
                                    <td><?php echo $book['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                        <?php if($book['description']): ?>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($book['description']), 0, 50); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['category_name']); ?></td>
                                    <td><small><?php echo htmlspecialchars($book['isbn']); ?></small></td>
                                    <td class="text-center"><?php echo $book['quantity']; ?></td>
                                    <td>
                                        <?php 
                                        $percentage = ($book['available_quantity'] / $book['quantity']) * 100;
                                        $color = $percentage > 50 ? 'success' : ($percentage > 20 ? 'warning' : 'danger');
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-<?php echo $color; ?> me-2">
                                                <?php echo $book['available_quantity']; ?>
                                            </span>
                                            <div class="progress flex-grow-1" style="height: 5px; width: 60px;">
                                                <div class="progress-bar bg-<?php echo $color; ?>" 
                                                     style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['location']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="books.php?action=delete&id=<?php echo $book['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce livre ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if(empty($books)): ?>
                        <div class="alert alert-info text-center">
                            Aucun livre trouvé.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>