function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    sidebar.classList.toggle('show');
}

function showToast(message, type = 'success') {
    const toastElement = document.getElementById('successToast');
    const toastMessage = document.getElementById('toastMessage');
    const toastHeader = toastElement.querySelector('.toast-header');
    
    // Update toast content
    toastMessage.textContent = message;
    
    // Update toast style based on type
    toastHeader.className = `toast-header bg-${type} text-white`;
    
    // Show toast
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}


function confirmDelete(id, title, callback) {
    document.getElementById('confirmMessage').textContent = `Are you sure you want to delete "${title}"?`;
    
    document.getElementById('confirmButton').onclick = function() {
        callback(id);
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
    };
    
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}


// Responsive sidebar handling
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('show');
    }
});


// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

