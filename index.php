<?php
define('DND_APP', true);
date_default_timezone_set('America/New_York');

if (!file_exists(__DIR__ . '/config.php')) {
    file_put_contents(__DIR__ . '/config.php',
        "<?php\nif (!defined('DND_APP')) { http_response_code(403); exit; }\ndefine('ADMIN_PASSWORD', 'admin123');\ndefine('SITE_PASSWORD', 'NEON');\ndefine('SITE_TITLE', 'D&D Session Scheduler');\ndefine('SITE_SUBTITLE', 'Insert coin to continue \xe2\x80\x94 select your session');\n");
}
require_once __DIR__ . '/config.php';

if (!defined('SITE_TITLE'))    define('SITE_TITLE',    'D&D Session Scheduler');
if (!defined('SITE_SUBTITLE')) define('SITE_SUBTITLE', 'Insert coin to continue \xe2\x80\x94 select your session');
if (!defined('SITE_PASSWORD')) define('SITE_PASSWORD', 'NEON');

session_start();

// ── Site-level password gate ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'site_login') {
    if (($_POST['site_password'] ?? '') === SITE_PASSWORD) {
        $_SESSION['dnd_site_auth']  = true;
        unset($_SESSION['dnd_site_flash']);
    } else {
        $_SESSION['dnd_site_flash'] = 'Incorrect password.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

if (empty($_SESSION['dnd_site_auth'])) {
    $siteFlash = $_SESSION['dnd_site_flash'] ?? '';
    unset($_SESSION['dnd_site_flash']);
    $ajaxActions = ['toggle_signup', 'toggle_day'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', $ajaxActions, true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit;
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(SITE_TITLE) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="alive-banner" id="main-banner" style="background-image:url('banner.png')"></div>
<div class="wrap">
<header class="hdr">
    <h1 class="hdr-title"><?= htmlspecialchars(SITE_TITLE) ?></h1>
    <p class="hdr-sub"><?= htmlspecialchars(SITE_SUBTITLE) ?></p>
</header>
<hr class="hdr-rule">
<div class="card" style="max-width:320px;margin:40px auto">
    <div class="card-title">Enter Site Password</div>
    <?php if ($siteFlash): ?><div class="flash error"><?= htmlspecialchars($siteFlash) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="site_login">
        <div class="field">
            <label>Password</label>
            <input type="password" name="site_password" placeholder="Password…" autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Enter</button>
    </form>
</div>
<footer style="text-align:center;padding:28px 0 0;color:#2a1040;font-size:.72rem;letter-spacing:2px;text-transform:uppercase">
    Game Over &nbsp;//&nbsp; <?= date('Y') ?>
</footer>
</div>
</body>
</html><?php
    exit;
}
// ─────────────────────────────────────────────────────────────────────
define('DATA_FILE', __DIR__ . '/dnd_data.json');

function loadData(): array {
    if (file_exists(DATA_FILE)) {
        $d = json_decode(file_get_contents(DATA_FILE), true);
        if (is_array($d)) return $d;
    }
    return [
        'settings' => [
            'allowed_days' => [4, 5, 6, 0],
            'time_start'   => '19:00',
            'time_end'     => '23:00',
            'max_slots'    => 0,
        ],
        'signups' => []
    ];
}

function saveData(array $data): void {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function updatePassword(string $pw): void {
    $title    = defined('SITE_TITLE')    ? SITE_TITLE    : 'D&D Session Scheduler';
    $subtitle = defined('SITE_SUBTITLE') ? SITE_SUBTITLE : 'Insert coin to continue — select your session';
    $sitepw   = defined('SITE_PASSWORD') ? SITE_PASSWORD : 'NEON';
    file_put_contents(__DIR__ . '/config.php',
        "<?php\nif (!defined('DND_APP')) { http_response_code(403); exit; }\ndefine('ADMIN_PASSWORD', " . var_export($pw, true) . ");\ndefine('SITE_PASSWORD', " . var_export($sitepw, true) . ");\ndefine('SITE_TITLE', " . var_export($title, true) . ");\ndefine('SITE_SUBTITLE', " . var_export($subtitle, true) . ");\n");
}

function getAvailableDates(array $settings): array {
    $dates = [];
    $today = new DateTime('today');
    $end   = (new DateTime('today'))->modify('+2 months');
    for ($d = clone $today; $d <= $end; $d->modify('+1 day')) {
        if (in_array((int)$d->format('w'), $settings['allowed_days'], true)) {
            $dates[] = $d->format('Y-m-d');
        }
    }
    return $dates;
}

function isAdmin(): bool { return !empty($_SESSION['dnd_admin']); }
function fmt12(string $t): string {
    [$h, $m] = explode(':', $t);
    $h = (int)$h; $ampm = $h >= 12 ? 'PM' : 'AM';
    return ($h % 12 ?: 12) . ($m !== '00' ? ":$m" : '') . ' ' . $ampm;
}

function slotsFull(array $data, string $date): bool {
    $max = (int)($data['settings']['max_slots'] ?? 0);
    if ($max === 0) return false;
    $count = 0;
    foreach ($data['signups'] as $s) {
        if ($s['date'] === $date) $count++;
    }
    return $count >= $max;
}

$data     = loadData();
$maxSlots = (int)($data['settings']['max_slots'] ?? 0);
$flash    = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── AJAX: toggle single date ──────────────────────────────────────
    if ($action === 'toggle_signup') {
        header('Content-Type: application/json');
        $name  = trim($_POST['name'] ?? '');
        $date  = $_POST['date'] ?? '';
        $avail = getAvailableDates($data['settings']);

        if ($name === '' || strlen($name) > 60 || !in_array($date, $avail, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid input']); exit;
        }

        $removed = false;
        foreach ($data['signups'] as $i => $s) {
            if (strcasecmp($s['name'], $name) === 0 && $s['date'] === $date) {
                array_splice($data['signups'], $i, 1);
                $removed = true; break;
            }
        }
        if (!$removed) {
            if (slotsFull($data, $date)) {
                echo json_encode(['success' => false, 'error' => 'Session is full.']); exit;
            }
            $data['signups'][] = ['name' => $name, 'date' => $date];
            usort($data['signups'], fn($a, $b) => $a['date'] <=> $b['date']);
        }
        saveData($data);
        $data = loadData();
        echo json_encode(['success' => true, 'removed' => $removed, 'signups' => $data['signups']]);
        exit;
    }

    // ── AJAX: toggle all dates for a day-of-week ─────────────────────
    if ($action === 'toggle_day') {
        header('Content-Type: application/json');
        $name  = trim($_POST['name'] ?? '');
        $dow   = (int)($_POST['dow'] ?? -1);
        $avail = getAvailableDates($data['settings']);

        if ($name === '' || strlen($name) > 60 || $dow < 0 || $dow > 6) {
            echo json_encode(['success' => false, 'error' => 'Invalid input']); exit;
        }

        $dayDates = array_values(array_filter($avail, fn($d) => (int)date('w', strtotime($d)) === $dow));

        $hasAny = false;
        foreach ($data['signups'] as $s) {
            if (strcasecmp($s['name'], $name) === 0 && in_array($s['date'], $dayDates, true)) {
                $hasAny = true; break;
            }
        }

        if ($hasAny) {
            $data['signups'] = array_values(array_filter($data['signups'],
                fn($s) => !(strcasecmp($s['name'], $name) === 0 && in_array($s['date'], $dayDates, true))
            ));
        } else {
            $existing = [];
            foreach ($data['signups'] as $s) {
                if (strcasecmp($s['name'], $name) === 0) $existing[] = $s['date'];
            }
            foreach ($dayDates as $d) {
                if (!in_array($d, $existing, true) && !slotsFull($data, $d)) {
                    $data['signups'][] = ['name' => $name, 'date' => $d];
                }
            }
            usort($data['signups'], fn($a, $b) => $a['date'] <=> $b['date']);
        }
        saveData($data);
        $data = loadData();
        echo json_encode(['success' => true, 'removed' => $hasAny, 'signups' => $data['signups']]);
        exit;
    }

    // ── Standard form actions ─────────────────────────────────────────
    if ($action === 'admin_login') {
        if (($_POST['password'] ?? '') === ADMIN_PASSWORD) {
            $_SESSION['dnd_admin'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']); exit;
        }
        $flash = 'Incorrect password.'; $flashType = 'error';

    } elseif ($action === 'admin_logout') {
        unset($_SESSION['dnd_admin']);
        header('Location: ' . $_SERVER['PHP_SELF']); exit;

    } elseif ($action === 'save_settings' && isAdmin()) {
        $days = array_unique(array_map('intval', (array)($_POST['days'] ?? [])));
        $days = array_values(array_filter($days, fn($d) => $d >= 0 && $d <= 6));
        $data['settings']['allowed_days'] = $days;
        $ts = $_POST['time_start'] ?? '';
        $te = $_POST['time_end']   ?? '';
        if (preg_match('/^\d{2}:\d{2}$/', $ts)) $data['settings']['time_start'] = $ts;
        if (preg_match('/^\d{2}:\d{2}$/', $te)) $data['settings']['time_end']   = $te;
        $data['settings']['max_slots'] = max(0, (int)($_POST['max_slots'] ?? 0));
        saveData($data);
        // Update config.php if any credential or title changed
        $newpw       = trim($_POST['new_pw']       ?? '');
        $newsitepw   = trim($_POST['new_site_pw']  ?? '');
        $newtitle    = trim($_POST['new_title']    ?? '');
        $newsubtitle = trim($_POST['new_subtitle'] ?? '');
        if ($newpw !== '' || $newsitepw !== '' || $newtitle !== '' || $newsubtitle !== '') {
            $pw       = $newpw       !== '' ? $newpw       : ADMIN_PASSWORD;
            $sitepw   = $newsitepw   !== '' ? $newsitepw   : SITE_PASSWORD;
            $title    = $newtitle    !== '' ? $newtitle    : SITE_TITLE;
            $subtitle = $newsubtitle !== '' ? $newsubtitle : SITE_SUBTITLE;
            file_put_contents(__DIR__ . '/config.php',
                "<?php\nif (!defined('DND_APP')) { http_response_code(403); exit; }\ndefine('ADMIN_PASSWORD', " . var_export($pw, true) . ");\ndefine('SITE_PASSWORD', " . var_export($sitepw, true) . ");\ndefine('SITE_TITLE', " . var_export($title, true) . ");\ndefine('SITE_SUBTITLE', " . var_export($subtitle, true) . ");\n");
        }
        $flash = 'Settings saved.'; $flashType = 'success';

    } elseif ($action === 'remove' && isAdmin()) {
        $idx = (int)($_POST['idx'] ?? -1);
        if (isset($data['signups'][$idx])) {
            array_splice($data['signups'], $idx, 1);
            saveData($data);
            $flash = 'Sign-up removed.'; $flashType = 'success';
        }
    }

    $data     = loadData();
    $maxSlots = (int)($data['settings']['max_slots'] ?? 0);
}

$available = getAvailableDates($data['settings']);

// Index signups by date (with original idx for admin remove buttons)
$byDate = [];
foreach ($data['signups'] as $i => $s) {
    $byDate[$s['date']][] = ['name' => $s['name'], 'idx' => $i];
}

// Admin report: counts per DOW
$sortedDays = $data['settings']['allowed_days'];
usort($sortedDays, fn($a, $b) => ($a === 0 ? 7 : $a) <=> ($b === 0 ? 7 : $b));

$dowCounts = [];
foreach ($sortedDays as $dow) $dowCounts[$dow] = 0;
foreach ($data['signups'] as $s) {
    $dow = (int)date('w', strtotime($s['date']));
    if (isset($dowCounts[$dow])) $dowCounts[$dow]++;
}

// Week grouping
$byWeek = [];
foreach ($available as $d) {
    $dt  = new DateTime($d);
    $mon = clone $dt;
    $mon->modify('Monday this week');
    $byWeek[$mon->format('Y-m-d')][] = $d;
}

$DAY_FULL = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$DAY_ABBR = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(SITE_TITLE) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="alive-banner" id="main-banner" style="background-image:url('banner.png')"></div>

<div class="wrap">

<header class="hdr">
    <h1 class="hdr-title"><?= htmlspecialchars(SITE_TITLE) ?></h1>
    <p class="hdr-sub"><?= htmlspecialchars(SITE_SUBTITLE) ?></p>
</header>
<hr class="hdr-rule">

<div class="admin-bar">
    <?php if (isAdmin()): ?>
        <span class="admin-pill">⚙ Admin Mode</span>
        <form method="post" style="margin:0">
            <input type="hidden" name="action" value="admin_logout">
            <button class="btn btn-ghost btn-sm" type="submit">Log Out</button>
        </form>
    <?php else: ?>
        <a href="#admin" class="btn btn-ghost btn-sm">Admin</a>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="flash <?= $flashType ?>"><?= $flash ?></div>
<?php endif; ?>

<!-- Name + day picker (non-admin) -->
<?php if (!isAdmin()): ?>
<div class="card">
    <div class="card-title">Pick Your Sessions</div>
    <div class="time-badge">
        🕖 Sessions run <?= fmt12($data['settings']['time_start']) ?> &ndash; <?= fmt12($data['settings']['time_end']) ?> EST
        <?php if ($maxSlots > 0): ?>
        <span class="slots-badge">Max <?= $maxSlots ?> per session</span>
        <?php endif; ?>
    </div>
    <div class="field">
        <label for="player-name">Your Name</label>
        <input type="text" id="player-name" placeholder="Enter your name…" maxlength="60" autocomplete="off">
    </div>
    <div class="day-btns">
        <?php foreach ($sortedDays as $dow): ?>
        <button class="day-btn" data-dow="<?= $dow ?>" onclick="toggleDay(this)">
            <?= $DAY_FULL[$dow] ?>
        </button>
        <?php endforeach; ?>
    </div>
    <p class="day-hint">Click a day to join every session &mdash; or toggle individual dates below.</p>
</div>
<?php endif; ?>

<!-- Date tiles (captured; output position depends on admin vs non-admin) -->
<?php ob_start(); ?>
<div class="card" id="dates-card">
    <div class="card-title"><?= isAdmin() ? '⚙ Session Sign-ups' : 'Available Sessions' ?></div>
    <?php if (empty($available)): ?>
        <p class="no-dates">No session dates configured.</p>
    <?php else: ?>
        <?php foreach ($byWeek as $weekDates):
            $first = reset($weekDates);
            $last  = end($weekDates);
            $label = date('M j', strtotime($first));
            $label .= date('M', strtotime($first)) !== date('M', strtotime($last))
                    ? ' – ' . date('M j', strtotime($last))
                    : ' – '  . date('j',   strtotime($last));
        ?>
        <div class="tile-month">
            <div class="tile-month-label"><?= $label ?></div>
            <?php foreach ($weekDates as $d):
                $players = $byDate[$d] ?? [];
                $cnt     = count($players);
                $isFull  = $maxSlots > 0 && $cnt >= $maxSlots;
                $dow     = (int)date('w', strtotime($d));
            ?>
            <div class="date-tile <?= $isFull ? 'tile-full' : '' ?>" data-date="<?= $d ?>"
                 <?= !isAdmin() ? 'onclick="toggleDate(this)"' : '' ?>>
                <div class="dt-meta">
                    <span class="dt-dow"><?= $DAY_FULL[$dow] ?></span>
                    <span class="dt-mdate"><?= date('M j', strtotime($d)) ?></span>
                </div>
                <div class="dt-players">
                    <?php if (isAdmin()): foreach ($players as $p): ?>
                    <span class="dt-chip">
                        🗡 <?= htmlspecialchars($p['name']) ?>
                        <form method="post" style="margin:0;display:inline">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="idx" value="<?= $p['idx'] ?>">
                            <button class="btn-x" type="submit"
                                onclick="return confirm('Remove <?= htmlspecialchars(addslashes($p['name'])) ?>?')">×</button>
                        </form>
                    </span>
                    <?php endforeach; endif; ?>
                </div>
                <?php if ($cnt > 0 || $maxSlots > 0): ?>
                <div class="dt-count <?= $isFull ? 'count-full' : '' ?>">
                    <?php if ($isFull): ?>Full
                    <?php elseif ($maxSlots > 0): ?><?= $cnt ?> / <?= $maxSlots ?>
                    <?php else: ?><?= $cnt ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php $dateTilesHtml = ob_get_clean(); ?>
<?php if (!isAdmin()) echo $dateTilesHtml; ?>

<!-- Admin report -->
<?php if (isAdmin()): ?>
<div class="card">
    <div class="card-title">📊 Best Days</div>
    <?php if (empty($data['signups'])): ?>
        <p class="no-dates">No sign-ups yet.</p>
    <?php else:
        $maxDow = max($dowCounts); ?>
    <table class="report-table">
        <thead><tr>
            <?php foreach ($sortedDays as $dow): ?>
            <th><?= $DAY_FULL[$dow] ?></th>
            <?php endforeach; ?>
            <th>Total</th>
        </tr></thead>
        <tbody><tr>
            <?php foreach ($sortedDays as $dow): $v = $dowCounts[$dow] ?? 0; ?>
            <td class="report-count <?= ($v === $maxDow && $v > 0) ? 'report-best' : '' ?>"><?= $v ?: '—' ?></td>
            <?php endforeach; ?>
            <td class="report-total"><?= array_sum($dowCounts) ?></td>
        </tr></tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Admin settings -->
<div class="card" id="admin">
    <div class="card-title">⚙ Admin Settings</div>
    <form method="post">
        <input type="hidden" name="action" value="save_settings">

        <div class="field">
            <label>Available Days of the Week</label>
            <div class="days-row">
                <?php for ($i = 0; $i < 7; $i++): ?>
                <div class="day-opt">
                    <input type="checkbox" name="days[]" value="<?= $i ?>"
                           id="d<?= $i ?>" <?= in_array($i, $data['settings']['allowed_days'], true) ? 'checked' : '' ?>>
                    <label for="d<?= $i ?>"><?= $DAY_ABBR[$i] ?></label>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="field" style="margin-top:18px">
            <label>Session Time</label>
            <div class="time-pair">
                <div class="field">
                    <label>Start</label>
                    <input type="time" name="time_start" value="<?= htmlspecialchars($data['settings']['time_start']) ?>">
                </div>
                <div class="time-sep">&ndash;</div>
                <div class="field">
                    <label>End</label>
                    <input type="time" name="time_end" value="<?= htmlspecialchars($data['settings']['time_end']) ?>">
                </div>
            </div>
        </div>

        <div class="field">
            <label>Max Players per Session <span class="field-note">(0 = unlimited)</span></label>
            <input type="number" name="max_slots" min="0" max="99"
                   value="<?= $maxSlots ?>" style="max-width:120px">
        </div>

        <div class="field">
            <label>Site Title <span class="field-note">— leave blank to keep current</span></label>
            <input type="text" name="new_title" placeholder="<?= htmlspecialchars(SITE_TITLE) ?>" maxlength="80" style="max-width:340px">
        </div>

        <div class="field">
            <label>Site Subtitle <span class="field-note">— leave blank to keep current</span></label>
            <input type="text" name="new_subtitle" placeholder="<?= htmlspecialchars(SITE_SUBTITLE) ?>" maxlength="120" style="max-width:460px">
        </div>

        <div class="field">
            <label>New Site Password <span class="field-note">— leave blank to keep current</span></label>
            <input type="password" name="new_site_pw" placeholder="New site password…" style="max-width:280px">
        </div>

        <div class="field">
            <label>New Admin Password <span class="field-note">— leave blank to keep current</span></label>
            <input type="password" name="new_pw" placeholder="New admin password…" style="max-width:280px">
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php echo $dateTilesHtml; ?>
<?php endif; ?>

<!-- Admin login -->
<?php if (!isAdmin()): ?>
<div class="card" id="admin" style="max-width:320px;margin:0 auto 18px">
    <div class="card-title">Admin Login</div>
    <form method="post">
        <input type="hidden" name="action" value="admin_login">
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="Admin password…">
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
</div>
<?php endif; ?>

<footer style="text-align:center;padding:28px 0 0;color:#2a1040;font-size:.72rem;letter-spacing:2px;text-transform:uppercase">
    Game Over &nbsp;//&nbsp; <?= date('Y') ?>
</footer>
</div>

<script>
const IS_ADMIN  = <?= isAdmin() ? 'true' : 'false' ?>;
const nameInput = document.getElementById('player-name');

let state = {
    signups:  <?= json_encode(array_values($data['signups'])) ?>,
    maxSlots: <?= $maxSlots ?>
};

if (nameInput) {
    nameInput.value = localStorage.getItem('dnd_name') || '';
    nameInput.addEventListener('input', () => {
        localStorage.setItem('dnd_name', nameInput.value.trim());
        refreshTiles(); refreshDayBtns();
    });
    refreshTiles(); refreshDayBtns();
}

function getByDate() {
    const bd = {};
    for (const s of state.signups) (bd[s.date] = bd[s.date] || []).push(s.name);
    return bd;
}

async function toggleDay(btn) {
    const name = nameInput?.value.trim();
    if (!name) { flashInput(nameInput); return; }
    btn.classList.add('day-btn-loading');
    try {
        const r = await post({ action:'toggle_day', name, dow: btn.dataset.dow });
        if (r.success) { state.signups = r.signups; refreshTiles(); refreshDayBtns(); }
    } finally { btn.classList.remove('day-btn-loading'); }
}

async function toggleDate(tile) {
    const name = nameInput?.value.trim();
    if (!name) { flashInput(nameInput); nameInput.scrollIntoView({behavior:'smooth',block:'center'}); return; }

    const bd    = getByDate();
    const mine  = (bd[tile.dataset.date] || []).some(p => p.toLowerCase() === name.toLowerCase());
    const count = (bd[tile.dataset.date] || []).length;
    if (!mine && state.maxSlots > 0 && count >= state.maxSlots) return;

    tile.classList.add('tile-loading');
    try {
        const r = await post({ action:'toggle_signup', name, date: tile.dataset.date });
        if (r.success) { state.signups = r.signups; refreshTiles(); refreshDayBtns(); }
    } finally { tile.classList.remove('tile-loading'); }
}

function refreshDayBtns() {
    const name = (nameInput?.value.trim() || '').toLowerCase();
    const bd   = getByDate();
    document.querySelectorAll('.day-btn').forEach(btn => {
        const dow = parseInt(btn.dataset.dow);
        btn.classList.toggle('day-btn-active',
            Object.entries(bd).some(([d, ps]) => dateDow(d) === dow && ps.some(p => p.toLowerCase() === name))
        );
    });
}

function refreshTiles() {
    if (IS_ADMIN) return;
    const name     = (nameInput?.value.trim() || '').toLowerCase();
    const bd       = getByDate();
    const maxCount = Math.max(0, ...Object.values(bd).map(a => a.length), 0);

    document.querySelectorAll('.date-tile').forEach(tile => {
        const players = bd[tile.dataset.date] || [];
        const count   = players.length;
        const mine    = name && players.some(p => p.toLowerCase() === name);
        const isFull  = state.maxSlots > 0 && count >= state.maxSlots;
        const best    = !isFull && maxCount > 0 && count === maxCount;

        tile.classList.toggle('signed-up',    mine);
        tile.classList.toggle('most-popular', best);
        tile.classList.toggle('tile-full',    isFull && !mine);

        tile.querySelector('.dt-players').innerHTML =
            players.map(p => `<span class="dt-chip">🗡 ${esc(p)}</span>`).join('');

        let c = tile.querySelector('.dt-count');
        const showCount = count > 0 || state.maxSlots > 0;
        if (showCount) {
            if (!c) { c = Object.assign(document.createElement('div'), {className:'dt-count'}); tile.appendChild(c); }
            c.classList.toggle('count-full', isFull);
            if (isFull)                  c.textContent = 'Full';
            else if (state.maxSlots > 0) c.textContent = `${count} / ${state.maxSlots}`;
            else if (count > 0)          c.textContent = count;
            else                         { c.remove(); c = null; }
        } else if (c) { c.remove(); }
    });
}

async function post(params) {
    const body = Object.entries(params).map(([k,v]) => encodeURIComponent(k)+'='+encodeURIComponent(v)).join('&');
    return (await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})).json();
}

function dateDow(str) { const [y,m,d] = str.split('-').map(Number); return new Date(y,m-1,d).getDay(); }
function flashInput(el) { el.classList.add('input-flash'); el.focus(); setTimeout(()=>el.classList.remove('input-flash'),600); }
function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
