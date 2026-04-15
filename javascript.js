// Données initiales
let books = JSON.parse(localStorage.getItem('books')) || [
    { id: 1, title: "Le Petit Prince", author: "Antoine de Saint-Exupéry", isbn: "978-2-07-040850-4", year: 1943, status: "available" },
    { id: 2, title: "1984", author: "George Orwell", isbn: "978-0-452-28423-4", year: 1949, status: "borrowed" },
    { id: 3, title: "Dune", author: "Frank Herbert", isbn: "978-0-441-17271-9", year: 1965, status: "available" }
];

let members = JSON.parse(localStorage.getItem('members')) || [
    { id: 1, name: "Jean Dupont", email: "jean@email.com", phone: "0612345678", registrationDate: "2024-01-15", borrowedBooks: [] },
    { id: 2, name: "Marie Martin", email: "marie@email.com", phone: "0698765432", registrationDate: "2024-02-20", borrowedBooks: [2] }
];

let loans = JSON.parse(localStorage.getItem('loans')) || [
    { id: 1, bookId: 2, memberId: 2, borrowDate: "2024-03-01", dueDate: "2024-03-15", returnDate: null, status: "active" }
];

let nextBookId = books.length > 0 ? Math.max(...books.map(b => b.id)) + 1 : 1;
let nextMemberId = members.length > 0 ? Math.max(...members.map(m => m.id)) + 1 : 1;
let nextLoanId = loans.length > 0 ? Math.max(...loans.map(l => l.id)) + 1 : 1;

// Sauvegarder les données
function saveData() {
    localStorage.setItem('books', JSON.stringify(books));
    localStorage.setItem('members', JSON.stringify(members));
    localStorage.setItem('loans', JSON.stringify(loans));
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    updateStats();
    displayBooks();
    displayMembers();
    displayLoans();
    setupEventListeners();
});

// Configuration des événements
function setupEventListeners() {
    // Navigation par onglets
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.dataset.tab;
            switchTab(tabId);
        });
    });

    // Recherche
    document.getElementById('searchBooks').addEventListener('input', (e) => {
        searchBooks(e.target.value);
    });
    
    document.getElementById('searchMembers').addEventListener('input', (e) => {
        searchMembers(e.target.value);
    });

    // Fermeture des modals
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            closeModals();
        });
    });

    // Soumission des formulaires
    document.getElementById('bookForm').addEventListener('submit', saveBook);
    document.getElementById('memberForm').addEventListener('submit', saveMember);
    document.getElementById('loanForm').addEventListener('submit', saveLoan);
}

// Changer d'onglet
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === tabId);
    });
    
    if (tabId === 'stats') {
        loadCharts();
    }
}

// Mettre à jour les statistiques
function updateStats() {
    document.getElementById('totalBooks').textContent = books.length;
    document.getElementById('availableBooks').textContent = books.filter(b => b.status === 'available').length;
    document.getElementById('borrowedBooks').textContent = books.filter(b => b.status === 'borrowed').length;
    document.getElementById('totalMembers').textContent = members.length;
    document.getElementById('activeLoans').textContent = loans.filter(l => l.status === 'active').length;
}

// Afficher les livres
function displayBooks(filteredBooks = null) {
    const booksToShow = filteredBooks || books;
    const tbody = document.getElementById('booksList');
    tbody.innerHTML = '';
    
    booksToShow.forEach(book => {
        const row = tbody.insertRow();
        row.insertCell(0).textContent = book.title;
        row.insertCell(1).textContent = book.author;
        row.insertCell(2).textContent = book.isbn;
        row.insertCell(3).textContent = book.year;
        
        const statusCell = row.insertCell(4);
        const statusClass = book.status === 'available' ? 'status-available' : 'status-borrowed';
        const statusText = book.status === 'available' ? 'Disponible' : 'Emprunté';
        statusCell.innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
        
        const actionsCell = row.insertCell(5);
        actionsCell.innerHTML = `
            <div class="action-buttons">
                <button class="btn-primary" onclick="editBook(${book.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-danger" onclick="deleteBook(${book.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
}

// Rechercher des livres
function searchBooks(query) {
    if (!query) {
        displayBooks();
        return;
    }
    
    const filtered = books.filter(book => 
        book.title.toLowerCase().includes(query.toLowerCase()) ||
        book.author.toLowerCase().includes(query.toLowerCase()) ||
        book.isbn.includes(query)
    );
    displayBooks(filtered);
}

// Afficher les membres
function displayMembers(filteredMembers = null) {
    const membersToShow = filteredMembers || members;
    const tbody = document.getElementById('membersList');
    tbody.innerHTML = '';
    
    membersToShow.forEach(member => {
        const borrowedCount = member.borrowedBooks.length;
        const row = tbody.insertRow();
        row.insertCell(0).textContent = member.name;
        row.insertCell(1).textContent = member.email;
        row.insertCell(2).textContent = member.phone || '-';
        row.insertCell(3).textContent = member.registrationDate;
        row.insertCell(4).textContent = borrowedCount;
        
        const actionsCell = row.insertCell(5);
        actionsCell.innerHTML = `
            <div class="action-buttons">
                <button class="btn-primary" onclick="editMember(${member.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-danger" onclick="deleteMember(${member.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
}

// Rechercher des membres
function searchMembers(query) {
    if (!query) {
        displayMembers();
        return;
    }
    
    const filtered = members.filter(member => 
        member.name.toLowerCase().includes(query.toLowerCase()) ||
        member.email.toLowerCase().includes(query.toLowerCase())
    );
    displayMembers(filtered);
}

// Afficher les emprunts
function displayLoans() {
    const tbody = document.getElementById('loansList');
    tbody.innerHTML = '';
    
    loans.forEach(loan => {
        const book = books.find(b => b.id === loan.bookId);
        const member = members.find(m => m.id === loan.memberId);
        
        if (!book || !member) return;
        
        const row = tbody.insertRow();
        row.insertCell(0).textContent = book.title;
        row.insertCell(1).textContent = member.name;
        row.insertCell(2).textContent = loan.borrowDate;
        row.insertCell(3).textContent = loan.dueDate;
        
        const statusCell = row.insertCell(4);
        const isOverdue = loan.status === 'active' && new Date(loan.dueDate) < new Date();
        const statusClass = loan.status === 'returned' ? 'status-available' : (isOverdue ? 'status-overdue' : 'status-borrowed');
        const statusText = loan.status === 'returned' ? 'Retourné' : (isOverdue ? 'En retard' : 'En cours');
        statusCell.innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
        
        const actionsCell = row.insertCell(5);
        if (loan.status === 'active') {
            actionsCell.innerHTML = `
                <button class="btn-success" onclick="returnBook(${loan.id})">
                    <i class="fas fa-undo"></i> Retourner
                </button>
            `;
        } else {
            actionsCell.textContent = '-';
        }
    });
}

// Gestion des livres
function openBookModal(bookId = null) {
    const modal = document.getElementById('bookModal');
    const title = document.getElementById('bookModalTitle');
    const form = document.getElementById('bookForm');
    
    if (bookId) {
        const book = books.find(b => b.id === bookId);
        if (book) {
            title.textContent = 'Modifier le livre';
            document.getElementById('bookId').value = book.id;
            document.getElementById('bookTitle').value = book.title;
            document.getElementById('bookAuthor').value = book.author;
            document.getElementById('bookIsbn').value = book.isbn;
            document.getElementById('bookYear').value = book.year;
        }
    } else {
        title.textContent = 'Ajouter un livre';
        form.reset();
        document.getElementById('bookId').value = '';
    }
    
    modal.style.display = 'block';
}

function saveBook(e) {
    e.preventDefault();
    
    const id = document.getElementById('bookId').value;
    const bookData = {
        title: document.getElementById('bookTitle').value,
        author: document.getElementById('bookAuthor').value,
        isbn: document.getElementById('bookIsbn').value,
        year: parseInt(document.getElementById('bookYear').value) || null,
        status: 'available'
    };
    
    if (id) {
        // Modification
        const index = books.findIndex(b => b.id === parseInt(id));
        if (index !== -1) {
            books[index] = { ...books[index], ...bookData };
        }
    } else {
        // Ajout
        bookData.id = nextBookId++;
        books.push(bookData);
    }
    
    saveData();
    updateStats();
    displayBooks();
    closeModals();
}

function editBook(id) {
    openBookModal(id);
}

function deleteBook(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce livre ?')) {
        const book = books.find(b => b.id === id);
        if (book && book.status === 'borrowed') {
            alert('Impossible de supprimer un livre emprunté !');
            return;
        }
        
        books = books.filter(b => b.id !== id);
        saveData();
        updateStats();
        displayBooks();
    }
}

// Gestion des membres
function openMemberModal(memberId = null) {
    const modal = document.getElementById('memberModal');
    const title = document.getElementById('memberModalTitle');
    const form = document.getElementById('memberForm');
    
    if (memberId) {
        const member = members.find(m => m.id === memberId);
        if (member) {
            title.textContent = 'Modifier le membre';
            document.getElementById('memberId').value = member.id;
            document.getElementById('memberName').value = member.name;
            document.getElementById('memberEmail').value = member.email;
            document.getElementById('memberPhone').value = member.phone || '';
        }
    } else {
        title.textContent = 'Ajouter un membre';
        form.reset();
        document.getElementById('memberId').value = '';
    }
    
    modal.style.display = 'block';
}

function saveMember(e) {
    e.preventDefault();
    
    const id = document.getElementById('memberId').value;
    const memberData = {
        name: document.getElementById('memberName').value,
        email: document.getElementById('memberEmail').value,
        phone: document.getElementById('memberPhone').value,
        registrationDate: new Date().toISOString().split('T')[0],
        borrowedBooks: []
    };
    
    if (id) {
        // Modification
        const index = members.findIndex(m => m.id === parseInt(id));
        if (index !== -1) {
            members[index] = { ...members[index], ...memberData, borrowedBooks: members[index].borrowedBooks };
        }
    } else {
        // Ajout
        memberData.id = nextMemberId++;
        members.push(memberData);
    }
    
    saveData();
    updateStats();
    displayMembers();
    closeModals();
}

function editMember(id) {
    openMemberModal(id);
}

function deleteMember(id) {
    const member = members.find(m => m.id === id);
    if (member && member.borrowedBooks.length > 0) {
        alert('Impossible de supprimer un membre qui a des livres empruntés !');
        return;
    }
    
    if (confirm('Êtes-vous sûr de vouloir supprimer ce membre ?')) {
        members = members.filter(m => m.id !== id);
        saveData();
        updateStats();
        displayMembers();
    }
}

// Gestion des emprunts
function openLoanModal() {
    const modal = document.getElementById('loanModal');
    const bookSelect = document.getElementById('loanBookId');
    const memberSelect = document.getElementById('loanMemberId');
    
    // Remplir la liste des livres disponibles
    bookSelect.innerHTML = '<option value="">Sélectionnez un livre</option>';
    books.filter(book => book.status === 'available').forEach(book => {
        bookSelect.innerHTML += `<option value="${book.id}">${book.title} - ${book.author}</option>`;
    });
    
    // Remplir la liste des membres
    memberSelect.innerHTML = '<option value="">Sélectionnez un membre</option>';
    members.forEach(member => {
        memberSelect.innerHTML += `<option value="${member.id}">${member.name} (${member.email})</option>`;
    });
    
    // Définir la date de retour par défaut (14 jours)
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 14);
    document.getElementById('loanDueDate').value = dueDate.toISOString().split('T')[0];
    
    modal.style.display = 'block';
}

function saveLoan(e) {
    e.preventDefault();
    
    const bookId = parseInt(document.getElementById('loanBookId').value);
    const memberId = parseInt(document.getElementById('loanMemberId').value);
    const dueDate = document.getElementById('loanDueDate').value;
    
    if (!bookId || !memberId) {
        alert('Veuillez sélectionner un livre et un membre');
        return;
    }
    
    const book = books.find(b => b.id === bookId);
    if (!book || book.status !== 'available') {
        alert('Ce livre n\'est pas disponible');
        return;
    }
    
    const loan = {
        id: nextLoanId++,
        bookId: bookId,
        memberId: memberId,
        borrowDate: new Date().toISOString().split('T')[0],
        dueDate: dueDate,
        returnDate: null,
        status: 'active'
    };
    
    loans.push(loan);
    
    // Mettre à jour le statut du livre
    book.status = 'borrowed';
    
    // Mettre à jour les emprunts du membre
    const member = members.find(m => m.id === memberId);
    member.borrowedBooks.push(bookId);
    
    saveData();
    updateStats();
    displayBooks();
    displayLoans();
    closeModals();
}

function returnBook(loanId) {
    const loan = loans.find(l => l.id === loanId);
    if (!loan) return;
    
    if (confirm('Confirmer le retour de ce livre ?')) {
        loan.returnDate = new Date().toISOString().split('T')[0];
        loan.status = 'returned';
        
        // Mettre à jour le statut du livre
        const book = books.find(b => b.id === loan.bookId);
        if (book) {
            book.status = 'available';
        }
        
        // Mettre à jour les emprunts du membre
        const member = members.find(m => m.id === loan.memberId);
        if (member) {
            member.borrowedBooks = member.borrowedBooks.filter(id => id !== loan.bookId);
        }
        
        saveData();
        updateStats();
        displayBooks();
        displayLoans();
    }
}

// Graphiques
let topBooksChart, monthlyLoansChart;

function loadCharts() {
    // Top 5 des livres les plus empruntés
    const bookLoanCount = {};
    loans.forEach(loan => {
        bookLoanCount[loan.bookId] = (bookLoanCount[loan.bookId] || 0) + 1;
    });
    
    const topBooks = Object.entries(bookLoanCount)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5);
    
    const topBooksLabels = topBooks.map(([bookId]) => {
        const book = books.find(b => b.id === parseInt(bookId));
        return book ? book.title : 'Inconnu';
    });
    
    const topBooksData = topBooks.map(([, count]) => count);
    
    if (topBooksChart) topBooksChart.destroy();
    
    const ctx1 = document.getElementById('topBooksChart').getContext('2d');
    topBooksChart = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: topBooksLabels,
            datasets: [{
                label: 'Nombre d\'emprunts',
                data: topBooksData,
                backgroundColor: 'rgba(102, 126, 234, 0.5)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
    
    // Emprunts par mois
    const monthlyCount = {};
    loans.forEach(loan => {
        const month = loan.borrowDate.substring(0, 7);
        monthlyCount[month] = (monthlyCount[month] || 0) + 1;
    });
    
    const months = Object.keys(monthlyCount).sort();
    const monthlyData = months.map(month => monthlyCount[month]);
    
    if (monthlyLoansChart) monthlyLoansChart.destroy();
    
    const ctx2 = document.getElementById('monthlyLoansChart').getContext('2d');
    monthlyLoansChart = new Chart(ctx2, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Nombre d\'emprunts',
                data: monthlyData,
                fill: false,
                borderColor: 'rgba(118, 75, 162, 1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

// Fermer les modals
function closeModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

// Fermer les modals en cliquant en dehors
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}