<?php
// Fichier d'installation à exécuter une seule fois
// Après installation, supprimez ce fichier pour des raisons de sécurité

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Vérifier si un administrateur existe déjà
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if($result['count'] == 0) {
    // Créer l'administrateur par défaut
    $username = 'admin';
    $email = 'admin@bibliotheque.com';
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $first_name = 'Admin';
    $last_name = 'System';
    
    $query = "INSERT INTO users (username, email, password, first_name, last_name, role, status, registration_date) 
              VALUES (:username, :email, :password, :first_name, :last_name, 'admin', 'active', CURDATE())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    
    if($stmt->execute()) {
        echo "<h2 style='color: green;'>✅ Installation réussie !</h2>";
        echo "<p>Compte administrateur créé avec succès.</p>";
        echo "<p><strong>Nom d'utilisateur:</strong> admin</p>";
        echo "<p><strong>Mot de passe:</strong> admin123</p>";
        echo "<p><a href='admin/login.php'>Se connecter à l'administration</a></p>";
    } else {
        echo "<h2 style='color: red;'>❌ Erreur lors de la création de l'administrateur</h2>";
    }
} else {
    echo "<h2 style='color: orange;'>⚠️ Un administrateur existe déjà</h2>";
    echo "<p>Si vous avez perdu le mot de passe, utilisez la fonction de réinitialisation.</p>";
    echo "<p><a href='admin/login.php'>Aller à la page de connexion</a></p>";
}

echo "<hr>";
echo "<p><strong>Important:</strong> Supprimez ce fichier (install.php) après utilisation pour des raisons de sécurité !</p>";
?>