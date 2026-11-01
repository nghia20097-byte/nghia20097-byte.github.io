/**
 * NChanger Server - Admin Dashboard JavaScript
 * ==============================================
 */
(function () {
    'use strict';

    /* ========================================================
       Toast Notification System
       ======================================================== */
    window.showToast = function (message, type) {
        type = type || 'info';
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
            info: 'bi-info-circle-fill'
        };

        const toast = document.createElement('div');
        toast.className = 'admin-toast toast-' + type;
        toast.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i><span>' + escapeHtml(message) + '</span>';
        container.appendChild(toast);

        toast.addEventListener('click', function () {
            dismissToast(toast);
        });

        setTimeout(function () {
            dismissToast(toast);
        }, 5000);
    };

    function dismissToast(toast) {
        if (toast.classList.contains('fade-out')) return;
        toast.classList.add('fade-out');
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
    }

    /* ========================================================
       Confirm Dialog
       ======================================================== */
    window.confirmAction = function (message) {
        return confirm(message || 'Are you sure you want to perform this action?');
    };

    /* ========================================================
       AJAX API Helper
       ======================================================== */
    window.apiCall = function (method, endpoint, data) {
        const options = {
            method: method.toUpperCase(),
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        if (data && (method.toUpperCase() === 'POST' || method.toUpperCase() === 'PUT' || method.toUpperCase() === 'PATCH')) {
            options.body = JSON.stringify(data);
        }

        return fetch(endpoint, options)
            .then(function (response) {
                if (response.status === 401) {
                    window.location.href = 'login.php?expired=1';
                    return Promise.reject(new Error('Session expired'));
                }
                return response.json();
            })
            .catch(function (error) {
                showToast('Network error: ' + error.message, 'error');
                return Promise.reject(error);
            });
    };

    /* ========================================================
       Table Search / Filter
       ======================================================== */
    window.initTableSearch = function (inputSelector, tableSelector) {
        const input = document.querySelector(inputSelector);
        const table = document.querySelector(tableSelector);
        if (!input || !table) return;

        input.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
                if (row.classList.contains('no-results-row')) {
                    row.remove();
                    return;
                }
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });

            // Check if any visible rows
            const visibleRows = table.querySelectorAll('tbody tr:not([style*="display: none"])');
            const existingEmpty = table.querySelector('.no-results-row');
            if (visibleRows.length === 0 && !existingEmpty) {
                const cols = table.querySelectorAll('thead th').length;
                const emptyRow = document.createElement('tr');
                emptyRow.className = 'no-results-row';
                emptyRow.innerHTML = '<td colspan="' + cols + '" class="text-center text-muted py-3">No matching records found</td>';
                table.querySelector('tbody').appendChild(emptyRow);
            } else if (visibleRows.length > 0 && existingEmpty) {
                existingEmpty.remove();
            }
        });
    };

    /* ========================================================
       Dashboard Auto-Refresh
       ======================================================== */
    let dashboardRefreshTimer = null;

    window.startDashboardRefresh = function (intervalMs) {
        intervalMs = intervalMs || 30000;
        if (dashboardRefreshTimer) clearInterval(dashboardRefreshTimer);
        dashboardRefreshTimer = setInterval(function () {
            const statsContainer = document.getElementById('dashboard-stats');
            if (!statsContainer) {
                clearInterval(dashboardRefreshTimer);
                return;
            }
            // Reload the page to refresh stats
            fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                if (response.status === 401) {
                    window.location.href = 'login.php?expired=1';
                    return;
                }
                return response.text();
            }).then(function (html) {
                if (!html) return;
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newStats = doc.getElementById('dashboard-stats');
                if (newStats && statsContainer) {
                    statsContainer.innerHTML = newStats.innerHTML;
                }
            }).catch(function () {
                // silently fail on refresh
            });
        }, intervalMs);
    };

    window.stopDashboardRefresh = function () {
        if (dashboardRefreshTimer) {
            clearInterval(dashboardRefreshTimer);
            dashboardRefreshTimer = null;
        }
    };

    /* ========================================================
       Session Check
       ======================================================== */
    window.checkSession = function () {
        fetch('login.php?check=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (!data.authenticated) {
                window.location.href = 'login.php?expired=1';
            }
        }).catch(function () {
            // ignore network errors in session check
        });
    };

    // Check session every 5 minutes
    setInterval(window.checkSession, 300000);

    /* ========================================================
       Sidebar Toggle (Mobile)
       ======================================================== */
    window.toggleSidebar = function () {
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar) sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    };

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('sidebar-overlay')) {
            const sidebar = document.querySelector('.admin-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });

    /* ========================================================
       Copy to Clipboard
       ======================================================== */
    window.copyToClipboard = function (text, btnElement) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                onCopySuccess(btnElement);
            }).catch(function () {
                fallbackCopy(text, btnElement);
            });
        } else {
            fallbackCopy(text, btnElement);
        }
    };

    function fallbackCopy(text, btnElement) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            onCopySuccess(btnElement);
        } catch (err) {
            showToast('Failed to copy', 'error');
        }
        document.body.removeChild(textarea);
    }

    function onCopySuccess(btnElement) {
        showToast('Copied to clipboard!', 'success');
        if (btnElement) {
            const origText = btnElement.innerHTML;
            btnElement.classList.add('copied');
            btnElement.innerHTML = '<i class="bi bi-check"></i>';
            setTimeout(function () {
                btnElement.classList.remove('copied');
                btnElement.innerHTML = origText;
            }, 1500);
        }
    }

    /* ========================================================
       Date Formatting Helper
       ======================================================== */
    window.formatDate = function (dateString, includeTime) {
        if (!dateString) return '—';
        const d = new Date(dateString);
        if (isNaN(d.getTime())) return dateString;

        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');

        let result = year + '-' + month + '-' + day;

        if (includeTime !== false) {
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            const seconds = String(d.getSeconds()).padStart(2, '0');
            result += ' ' + hours + ':' + minutes + ':' + seconds;
        }

        return result;
    };

    window.timeAgo = function (dateString) {
        if (!dateString) return '—';
        const d = new Date(dateString);
        if (isNaN(d.getTime())) return dateString;

        const seconds = Math.floor((Date.now() - d.getTime()) / 1000);

        if (seconds < 60) return 'just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';

        return formatDate(dateString, false);
    };

    /* ========================================================
       Utility: HTML Escape
       ======================================================== */
    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /* ========================================================
       Confirm Delete Forms
       ======================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-init table search if elements exist
        const searchInput = document.querySelector('.table-search-input');
        const dataTable = document.querySelector('.admin-data-table');
        if (searchInput && dataTable) {
            initTableSearch('.table-search-input', '.admin-data-table');
        }

        // Attach confirm to delete forms/buttons
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!confirmAction(this.getAttribute('data-confirm'))) {
                    e.preventDefault();
                    return false;
                }
            });
        });

        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible.auto-dismiss').forEach(function (alert) {
            setTimeout(function () {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) closeBtn.click();
            }, 5000);
        });
    });

})();
