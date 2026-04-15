$(document).ready(function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Confirmation before delete
    $('.delete-confirm').click(function(e) {
        if(!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
            e.preventDefault();
        }
    });
    
    // Form validation
    $('form').submit(function(e) {
        let valid = true;
        $(this).find('input[required], select[required], textarea[required]').each(function() {
            if(!$(this).val()) {
                $(this).addClass('is-invalid');
                valid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if(!valid) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires');
        }
    });
    
    // Search with debounce
    let searchTimeout;
    $('.search-input').on('input', function() {
        clearTimeout(searchTimeout);
        let searchValue = $(this).val();
        searchTimeout = setTimeout(function() {
            // Perform search
            window.location.href = '?search=' + encodeURIComponent(searchValue);
        }, 500);
    });
    
    // Tooltip initialization
    $('[data-toggle="tooltip"]').tooltip();
    
    // Back to top button
    $(window).scroll(function() {
        if($(this).scrollTop() > 100) {
            $('#back-to-top').fadeIn();
        } else {
            $('#back-to-top').fadeOut();
        }
    });
    
    $('#back-to-top').click(function() {
        $('html, body').animate({scrollTop: 0}, 500);
        return false;
    });
});

// Fonction pour les emprunts
function borrowBook(bookId) {
    if(confirm('Voulez-vous vraiment emprunter ce livre ?')) {
        $.ajax({
            url: '/api/borrow.php',
            method: 'POST',
            data: { book_id: bookId },
            success: function(response) {
                if(response.success) {
                    alert('Livre emprunté avec succès !');
                    location.reload();
                } else {
                    alert('Erreur : ' + response.message);
                }
            },
            error: function() {
                alert('Erreur lors de l\'emprunt');
            }
        });
    }
}

// Fonction pour retourner un livre
function returnBook(loanId) {
    if(confirm('Confirmez-vous le retour de ce livre ?')) {
        $.ajax({
            url: '/api/return.php',
            method: 'POST',
            data: { loan_id: loanId },
            success: function(response) {
                if(response.success) {
                    alert('Livre retourné avec succès !');
                    location.reload();
                } else {
                    alert('Erreur : ' + response.message);
                }
            },
            error: function() {
                alert('Erreur lors du retour');
            }
        });
    }
}

// Export des données
function exportData(type) {
    window.location.href = '/admin/export.php?type=' + type;
}

// Graphiques avec Chart.js
function loadCharts() {
    if(typeof Chart !== 'undefined') {
        // Statistiques mensuelles
        $.ajax({
            url: '/api/stats.php',
            method: 'GET',
            success: function(data) {
                const ctx = document.getElementById('monthlyChart');
                if(ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.months,
                            datasets: [{
                                label: 'Emprunts par mois',
                                data: data.counts,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }
            }
        });
    }
}