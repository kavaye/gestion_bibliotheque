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

// Ajouter une catégorie
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $query = "INSERT INTO categories (name, description) VALUES (:name, :description)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':description', $_POST['description']);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Catégorie ajoutée avec succès';
        header('Location: categories.php');
        exit();
    } else {
        $error = 'Erreur lors de l\'ajout de la catégorie';
    }
}

// Modifier une catégorie
if($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit' && $id) {
    $query = "UPDATE categories SET name=:name, description=:description WHERE id=:id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':description', $_POST['description']);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Catégorie modifiée avec succès';
        header('Location: categories.php');
        exit();
    } else {
        $error = 'Erreur lors de la modification';
    }
}

// Supprimer une catégorie
if($action == 'delete' && $id) {
    // Vérifier si des livres utilisent cette catégorie
    $query = "SELECT COUNT(*) as count FROM books WHERE category_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result['count'] > 0) {
        $_SESSION['error'] = 'Impossible de supprimer cette catégorie car elle contient des livres';
    } else {
        $query = "DELETE FROM categories WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if($stmt->execute()) {
            $_SESSION['success'] = 'Catégorie supprimée avec succès';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression';
        }
    }
    header('Location: categories.php');
    exit();
}

// Récupérer la catégorie pour modification
$category = null;
if($action == 'edit' && $id) {
    $query = "SELECT * FROM categories WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer la liste des catégories
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT c.*, COUNT(b.id) as books_count 
          FROM categories c 
          LEFT JOIN books b ON c.id = b.category_id 
          WHERE c.name LIKE :search 
          GROUP BY c.id 
          ORDER BY c.name ASC";
$stmt = $db->prepare($query);
$searchTerm = "%$search%";
$stmt->bindParam(':search', $searchTerm);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Gestion des Catégories</h1>
        
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
        
        <?php if($action == 'add' || ($action == 'edit' && $category)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $action == 'add' ? 'Ajouter une catégorie' : 'Modifier la catégorie'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nom de la catégorie *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $category ? htmlspecialchars($category['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $category ? htmlspecialchars($category['description']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="categories.php" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <a href="categories.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter une catégorie
                            </a>
                        </div>
                        <div class="col-md-8">
                            <form method="GET" class="d-flex">
                                <input type="text" name="search" class="form-control me-2" 
                                       placeholder="Rechercher une catégorie..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i>
                                </button>
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
                                    <th>Description</th>
                                    <th>Nombre de livres</th>
                                    <th>Date de création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categories as $cat): ?>
                                <tr>
                                    <td><?php echo $cat['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $cat['books_count']; ?> livres</span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($cat['created_at'])); ?></td>
                                    <td>
                                        <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if($cat['books_count'] == 0): ?>
                                            <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled title="Cette catégorie contient des livres">
                                                <i class="fas fa-trash"></i>
                                            </button>
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