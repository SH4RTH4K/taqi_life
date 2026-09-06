/**
 * BD Courier Rejected Logs Admin JavaScript
 * Handles initialization and integration with WordPress admin
 */

jQuery(document).ready(function($) {
    'use strict';

    // Ensure required globals are available
    if (typeof bdcourierRejectedLogsAjax === 'undefined') {
        return;
    }

    // Initialize React app if containers exist
    const rejectedLogsContainer = document.getElementById('bd-courier-rejected-logs-root');
    const blockedEntitiesContainer = document.getElementById('bd-courier-blocked-entities-root');

    // Handle rejected logs container
    if (rejectedLogsContainer) {
        // Check if React app is already loaded
        if (typeof window.BDCourierRejectedLogs !== 'undefined' && typeof window.BDCourierRejectedLogs.init === 'function') {
            window.BDCourierRejectedLogs.init('bd-courier-rejected-logs-root');
        } else {
            // Retry after a short delay to allow scripts to load
            setTimeout(function() {
                if (typeof window.BDCourierRejectedLogs !== 'undefined' && typeof window.BDCourierRejectedLogs.init === 'function') {
                    window.BDCourierRejectedLogs.init('bd-courier-rejected-logs-root');
                }
            }, 500);
        }
    }

    // Handle blocked entities container
    if (blockedEntitiesContainer) {
        // Check if React app is already loaded
        if (typeof window.BDCourierBlockedEntities !== 'undefined' && typeof window.BDCourierBlockedEntities.init === 'function') {
            window.BDCourierBlockedEntities.init('bd-courier-blocked-entities-root');
        } else {
            // Retry after a short delay to allow scripts to load
            setTimeout(function() {
                if (typeof window.BDCourierBlockedEntities !== 'undefined' && typeof window.BDCourierBlockedEntities.init === 'function') {
                    window.BDCourierBlockedEntities.init('bd-courier-blocked-entities-root');
                }
            }, 500);
        }
    }
});
