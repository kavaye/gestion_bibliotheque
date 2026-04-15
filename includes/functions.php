<?php
class LibraryFunctions {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getTotalBooks() {
        $query = "SELECT COUNT(*) as total FROM books";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    public function getAvailableBooks() {
        $query = "SELECT SUM(available_quantity) as total FROM books";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    public function getTotalMembers() {
        $query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    public function getActiveLoans() {
        $query = "SELECT COUNT(*) as total FROM loans WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    public function getOverdueLoans() {
        $query = "SELECT COUNT(*) as total FROM loans WHERE status = 'active' AND due_date < CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    // Modifier la fonction borrowBook pour accepter une date d'échéance personnalisée
public function borrowBook($user_id, $book_id, $due_date = null) {
    // Vérifier la disponibilité
    $query = "SELECT available_quantity, title FROM books WHERE id = :book_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':book_id', $book_id);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($book['available_quantity'] <= 0) {
        return ['success' => false, 'message' => 'Ce livre n\'est pas disponible'];
    }
    
    // Vérifier les emprunts en cours de l'utilisateur
    $query = "SELECT COUNT(*) as count FROM loans WHERE user_id = :user_id AND status = 'active'";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $active_loans = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($active_loans['count'] >= 5) {
        return ['success' => false, 'message' => 'Vous avez déjà 5 livres empruntés'];
    }
    
    // Récupérer la durée maximale autorisée pour l'utilisateur
    $query = "SELECT max_loan_duration FROM users WHERE id = :user_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Créer l'emprunt
    $borrow_date = date('Y-m-d');
    
    // Si aucune date d'échéance n'est fournie, utiliser la durée préférée
    if(!$due_date) {
        $query = "SELECT preferred_loan_duration FROM users WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user_pref = $stmt->fetch(PDO::FETCH_ASSOC);
        $duration = $user_pref['preferred_loan_duration'];
        $due_date = date('Y-m-d', strtotime("+$duration days"));
    } else {
        // Vérifier que la durée demandée ne dépasse pas la durée maximale
        $requested_days = (strtotime($due_date) - strtotime($borrow_date)) / (60 * 60 * 24);
        if($requested_days > $user['max_loan_duration']) {
            return ['success' => false, 'message' => 'La durée demandée dépasse votre durée maximale autorisée (' . $user['max_loan_duration'] . ' jours)'];
        }
    }
    
    $query = "INSERT INTO loans (user_id, book_id, borrow_date, due_date, status) 
              VALUES (:user_id, :book_id, :borrow_date, :due_date, 'active')";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':book_id', $book_id);
    $stmt->bindParam(':borrow_date', $borrow_date);
    $stmt->bindParam(':due_date', $due_date);
    
    if($stmt->execute()) {
        // Mettre à jour la disponibilité
        $query = "UPDATE books SET available_quantity = available_quantity - 1 
                  WHERE id = :book_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        
        $duration_days = (strtotime($due_date) - strtotime($borrow_date)) / (60 * 60 * 24);
        return ['success' => true, 'message' => "Livre emprunté avec succès pour $duration_days jours"];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de l\'emprunt'];
}
    public function returnBook($loan_id) {
        $query = "SELECT * FROM loans WHERE id = :loan_id AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':loan_id', $loan_id);
        $stmt->execute();
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$loan) {
            return ['success' => false, 'message' => 'Emprunt non trouvé'];
        }
        
        $return_date = date('Y-m-d');
        $is_overdue = $return_date > $loan['due_date'];
        
        // Mettre à jour l'emprunt
        $query = "UPDATE loans SET return_date = :return_date, status = 'returned' 
                  WHERE id = :loan_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':return_date', $return_date);
        $stmt->bindParam(':loan_id', $loan_id);
        
        if($stmt->execute()) {
            // Mettre à jour la disponibilité du livre
            $query = "UPDATE books SET available_quantity = available_quantity + 1 
                      WHERE id = :book_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':book_id', $loan['book_id']);
            $stmt->execute();
            
            // Créer une amende si nécessaire
            if($is_overdue) {
                $days_overdue = (strtotime($return_date) - strtotime($loan['due_date'])) / (60 * 60 * 24);
                $amount = $days_overdue * 0.5; // 0.5€ par jour de retard
                
                $query = "INSERT INTO fines (user_id, loan_id, amount, reason, status) 
                          VALUES (:user_id, :loan_id, :amount, 'Retard de retour', 'pending')";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $loan['user_id']);
                $stmt->bindParam(':loan_id', $loan_id);
                $stmt->bindParam(':amount', $amount);
                $stmt->execute();
            }
            
            return ['success' => true, 'message' => 'Livre retourné avec succès'];
        }
        
        return ['success' => false, 'message' => 'Erreur lors du retour'];
    }
    
    public function searchBooks($keyword, $category = null) {
        $query = "SELECT b.*, c.name as category_name 
                  FROM books b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  WHERE (b.title LIKE :keyword OR b.author LIKE :keyword OR b.isbn LIKE :keyword)";
        
        if($category) {
            $query .= " AND b.category_id = :category";
        }
        
        $query .= " ORDER BY b.title ASC";
        
        $stmt = $this->conn->prepare($query);
        $keyword = "%$keyword%";
        $stmt->bindParam(':keyword', $keyword);
        
        if($category) {
            $stmt->bindParam(':category', $category);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPopularBooks($limit = 5) {
        $query = "SELECT b.*, COUNT(l.id) as loan_count 
                  FROM books b 
                  LEFT JOIN loans l ON b.id = l.book_id 
                  GROUP BY b.id 
                  ORDER BY loan_count DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>