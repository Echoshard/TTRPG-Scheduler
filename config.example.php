<?php
// Copy this file to config.php and adjust the values below.
// If config.php does not exist, the app will auto-generate one with these defaults.
if (!defined('DND_APP')) { http_response_code(403); exit; }

define('ADMIN_PASSWORD', 'admin123');                                  // Password for the Admin panel
define('SITE_PASSWORD', 'NEON');                                       // Password for the site gate (when REQUIRE_LOGIN is true)
define('SITE_TITLE', 'D&D Session Scheduler');                         // Header title
define('SITE_SUBTITLE', 'Insert coin to continue — select your session'); // Header subtitle
define('SITE_TIMEZONE', 'America/New_York');                           // PHP timezone identifier
define('REQUIRE_LOGIN', true);                                         // Require the site password to view the page
define('DEFAULT_THEME', 'neon');                                       // neon | scifi | fantasy | grimdark | steampunk
