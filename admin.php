<?php
/**
 * Compatibility entry point for local/admin links that omit /wp-admin/.
 *
 * WordPress's normal administration bootstrap performs authentication,
 * capability checks, and dispatches the requested admin page.
 */
require_once __DIR__ . '/wp-admin/admin.php';
