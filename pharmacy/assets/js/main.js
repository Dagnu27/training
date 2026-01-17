// ===== PharmaSys Core JS =====
// Vanilla JS | No jQuery | Bootstrap 5 Compatible

document.addEventListener('DOMContentLoaded', function () {
    // 🔹 Auto-focus first visible input/textarea with 'autofocus' or first form field
    function autoFocus() {
        const focused = document.querySelector('input[autofocus], textarea[autofocus], [autofocus]');
        if (focused) {
            focused.focus();
            return;
        }
        // Fallback: first visible input in main content
        const firstInput = document.querySelector('main input:not([type="hidden"]):not(:disabled), main textarea:not(:disabled)');
        if (firstInput && firstInput.offsetParent !== null) { // visible check
            firstInput.focus();
        }
    }

    // 🔹 Toast Notification (uses Bootstrap Toast)
    function showToast(message, type = 'info', title = 'PharmaSys') {
        const toastEl = document.getElementById('liveToast');
        if (!toastEl) return;

        const toastBody = toastEl.querySelector('.toast-body');
        const toastHeader = toastEl.querySelector('.toast-header');
        
        // Reset
        toastBody.textContent = message;
        toastHeader.querySelector('strong').textContent = title;

        // Apply type styling
        const typeClasses = {
            success: 'bg-success text-white',
            danger: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-primary text-white'
        };
        toastBody.className = 'toast-body ' + (typeClasses[type] || typeClasses.info);

        // Show
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    }

    // 🔹 Confirm before delete (data-confirm)
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const msg = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // 🔹 Auto-format expiry date (YYYY-MM-DD) on batch input
    document.querySelectorAll('input[name="expiry_date"]').forEach(input => {
        input.addEventListener('blur', function () {
            const val = this.value.trim();
            if (val && !/^\d{4}-\d{2}-\d{2}$/.test(val)) {
                showToast('⚠️ Expiry must be YYYY-MM-DD (e.g., 2026-12-31)', 'warning');
                this.focus();
            }
        });
    });

    // 🔹 Search as you type (for medicine list)
    const searchInput = document.getElementById('search-medicines');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim().toLowerCase();
                const rows = document.querySelectorAll('#medicine-table tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            }, 300);
        });
    }

    // 🔹 Initialize
    autoFocus();

    // 🔹 Expose globally (for inline onclick, e.g., cart)
    window.showToast = showToast;
    window.autoFocus = autoFocus;
});