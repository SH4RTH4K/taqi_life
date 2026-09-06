/**
 * React-style notification system for WordPress admin
 * Uses vanilla JS with React-like styling
 */
(function() {
    'use strict';

    const NotificationManager = {
        notifications: [],
        container: null,

        init: function() {
            // Create container if it doesn't exist
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'bdcourier-notification-root';
                this.container.style.cssText = 'position: fixed; top: 32px; right: 20px; z-index: 999999; max-width: 400px; pointer-events: none;';
                document.body.appendChild(this.container);
            }

            // Add CSS if not already added
            if (!document.getElementById('bdcourier-notification-styles')) {
                const style = document.createElement('style');
                style.id = 'bdcourier-notification-styles';
                style.textContent = `
                    @keyframes bdcourier-slide-in-right {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    .bdcourier-notification {
                        animation: bdcourier-slide-in-right 0.3s ease-out;
                        border: 1px solid;
                        border-radius: 8px;
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                        padding: 16px;
                        margin-bottom: 12px;
                        display: flex;
                        align-items: flex-start;
                        gap: 12px;
                        pointer-events: auto;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    }
                    .bdcourier-notification-success {
                        background-color: #f0fdf4;
                        border-color: #bbf7d0;
                        color: #166534;
                    }
                    .bdcourier-notification-error {
                        background-color: #fef2f2;
                        border-color: #fecaca;
                        color: #991b1b;
                    }
                    .bdcourier-notification-warning {
                        background-color: #fffbeb;
                        border-color: #fde68a;
                        color: #92400e;
                    }
                    .bdcourier-notification-info {
                        background-color: #eff6ff;
                        border-color: #bfdbfe;
                        color: #1e40af;
                    }
                    .bdcourier-notification-icon {
                        flex-shrink: 0;
                        margin-top: 2px;
                        width: 20px;
                        height: 20px;
                    }
                    .bdcourier-notification-content {
                        flex: 1;
                        min-width: 0;
                    }
                    .bdcourier-notification-title {
                        font-weight: 600;
                        font-size: 14px;
                        margin-bottom: 4px;
                    }
                    .bdcourier-notification-message {
                        font-size: 14px;
                        line-height: 1.5;
                    }
                    .bdcourier-notification-close {
                        flex-shrink: 0;
                        background: none;
                        border: none;
                        cursor: pointer;
                        opacity: 0.7;
                        transition: opacity 0.2s;
                        padding: 0;
                        width: 16px;
                        height: 16px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .bdcourier-notification-close:hover {
                        opacity: 1;
                    }
                    .bdcourier-notification-close svg {
                        width: 16px;
                        height: 16px;
                    }
                `;
                document.head.appendChild(style);
            }
        },

        show: function(type, message, title, duration) {
            this.init();

            const id = Date.now() + Math.random();
            const notification = {
                id: id,
                type: type || 'info',
                message: message,
                title: title,
                duration: duration || 5000
            };

            this.notifications.push(notification);
            this.render();

            if (notification.duration > 0) {
                setTimeout(() => {
                    this.remove(id);
                }, notification.duration);
            }

            return id;
        },

        remove: function(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
            this.render();
        },

        getIcon: function(type) {
            const icons = {
                success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
            };
            return icons[type] || icons.info;
        },

        render: function() {
            if (!this.container) {
                return;
            }

            if (this.notifications.length === 0) {
                this.container.innerHTML = '';
                return;
            }

            let html = '';
            this.notifications.forEach(notification => {
                html += `
                    <div class="bdcourier-notification bdcourier-notification-${notification.type}">
                        <div class="bdcourier-notification-icon">
                            ${this.getIcon(notification.type)}
                        </div>
                        <div class="bdcourier-notification-content">
                            ${notification.title ? `<div class="bdcourier-notification-title">${this.escapeHtml(notification.title)}</div>` : ''}
                            <div class="bdcourier-notification-message">${this.escapeHtml(notification.message)}</div>
                        </div>
                        <button class="bdcourier-notification-close" onclick="window.bdcourierNotifications.remove(${notification.id})" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                `;
            });

            this.container.innerHTML = html;
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            NotificationManager.init();
        });
    } else {
        NotificationManager.init();
    }

    // Expose to global scope
    window.bdcourierNotifications = NotificationManager;

    // Convenience function
    window.bdcourierShowNotification = function(type, message, title, duration) {
        return NotificationManager.show(type, message, title, duration);
    };
})();

