<?php
// Fichier à exécuter une seule fois pour générer les hashs des mots de passe
// Placez ce fichier dans le dossier racine et exécutez-le via le navigateur
// Puis supprimez-le après utilisation

echo "<h2>Génération des mots de passe hashés pour la bibliothèque</h2>";

$passwords = [
    'admin' => 'admin123',
    'librarian' => 'librarian123',
    'user' => 'user123'
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Compte</th><th>Mot de passe</th><th>Hash à insérer dans la base de données</th></tr>";

foreach($passwords as $account => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<tr>";
    echo "<td><strong>$account</strong></td>";
    echo "<td>$password</td>";
    echo "<td><code>$hash</code></td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><br>";
echo "<h3>Instructions :</h3>";
echo "<ol>";
echo "<li>Copiez les hashs générés ci-dessus</li>";
echo "<li>Remplacez <code>'$2y$10\$YourHashedPasswordHere'</code> dans le fichier SQL par les hashs correspondants</li>";
echo "<li>Exécutez le script SQL dans phpMyAdmin</li>";
echo "<li><strong>IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité !</strong></li>";
echo "</ol>";
?>