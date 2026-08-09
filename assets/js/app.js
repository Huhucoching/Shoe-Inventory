(() => {
    'use strict';

    function setupAutoDismissAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach((alertEl) => {
            if (alertEl.classList.contains('alert-danger')) {
                return;
            }

            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                bsAlert.close();
            }, 4200);
        });
    }

    function setupConfirmActions() {
        document.addEventListener('click', (event) => {
            const target = event.target.closest('[data-confirm]');
            if (!target) {
                return;
            }

            const question = target.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(question)) {
                event.preventDefault();
            }
        });
    }

    async function fetchJSON(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        return response.json();
    }

    window.App = {
        fetchJSON,
    };

    document.addEventListener('DOMContentLoaded', () => {
        setupAutoDismissAlerts();
        setupConfirmActions();
    });
})();
