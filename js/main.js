// NICE Insurance - Client-side JS

// Confirm before delete actions
function confirmDelete(formId) {
    if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
        document.getElementById(formId).submit();
    }
}

// Auto-dismiss alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });
});
