<?php
// Prevent direct browser access — only loadable via require from within the app
if (!defined('DND_APP')) { http_response_code(403); exit; }

define('ADMIN_PASSWORD', 'admin123');
define('SITE_PASSWORD',  'NEON');
define('SITE_TITLE',    'D&D Session Scheduler');
define('SITE_SUBTITLE', 'Insert coin to continue — select your session');
