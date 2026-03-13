<?php
/**
 * ============================================================
 *  Kyana CMS — Installer
 *  Place in: /install/install.php
 *  Visit in browser, then DELETE this file after installation.
 * ============================================================
 */

// Paths relative to the web root (one level up from /install/)
define('ROOT',          dirname(__DIR__));
define('LOCK_FILE',     ROOT . '/install.lock');
define('CONFIG_FILE',   ROOT . '/config.php');
define('SETTINGS_FILE', ROOT . '/Configuration/settings.json');

session_start();
error_reporting(0);
ini_set('display_errors', 0);

$step   = (int)($_GET['step'] ?? 1);
$errors = [];

// ── Helpers ──────────────────────────────────────────────────
function encrypt_value(string $plaintext, string $key): string {
    $cipher = 'aes-256-cbc';
    $iv     = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $enc    = openssl_encrypt($plaintext, $cipher, $key, 0, $iv);
    return base64_encode($enc . '::' . base64_encode($iv));
}

function test_mssql(string $host, string $db, string $user, string $pass): array {
    if (!function_exists('sqlsrv_connect')) {
        return ['ok' => false, 'msg' => 'php_sqlsrv driver is not loaded.', 'conn' => null];
    }
    $conn = @sqlsrv_connect($host, [
        'Database'               => $db,
        'Uid'                    => $user,
        'PWD'                    => $pass,
        'TrustServerCertificate' => 1,
        'Encrypt'                => 0,
        'LoginTimeout'           => 5,
    ]);
    if (!$conn) {
        $e = sqlsrv_errors();
        return ['ok' => false, 'msg' => $e ? $e[0]['message'] : 'Unknown error', 'conn' => null];
    }
    return ['ok' => true, 'msg' => 'Connected', 'conn' => $conn];
}

function check_prereqs(): array {
    $c = [];
    $c[] = ['label' => 'PHP Version (7.4+)',             'ok' => version_compare(PHP_VERSION, '7.4.0', '>='), 'val' => PHP_VERSION];
    $c[] = ['label' => 'sqlsrv Extension',               'ok' => function_exists('sqlsrv_connect'),           'val' => function_exists('sqlsrv_connect') ? 'Loaded' : 'MISSING — install php_sqlsrv'];
    $c[] = ['label' => 'OpenSSL Extension',              'ok' => extension_loaded('openssl'),                 'val' => extension_loaded('openssl')  ? 'Loaded' : 'MISSING'];
    $c[] = ['label' => 'cURL Extension',                 'ok' => extension_loaded('curl'),                    'val' => extension_loaded('curl')     ? 'Loaded' : 'MISSING'];
    $c[] = ['label' => 'GD Extension',                   'ok' => extension_loaded('gd'),                      'val' => extension_loaded('gd')       ? 'Loaded' : 'MISSING'];
    $cfg_ok = is_writable(CONFIG_FILE) || (!file_exists(CONFIG_FILE) && is_writable(ROOT));
    $c[] = ['label' => 'config.php writable',            'ok' => $cfg_ok,  'val' => $cfg_ok  ? 'OK' : 'NOT writable'];
    $dir_ok = is_dir(ROOT . '/Configuration') ? is_writable(ROOT . '/Configuration') : is_writable(ROOT);
    $c[] = ['label' => 'Configuration/ writable',        'ok' => $dir_ok,  'val' => $dir_ok  ? 'OK' : 'NOT writable'];
    $upl_ok = is_dir(ROOT . '/uploads') ? is_writable(ROOT . '/uploads') : is_writable(ROOT);
    $c[] = ['label' => 'uploads/ writable',              'ok' => $upl_ok,  'val' => $upl_ok  ? 'OK' : 'NOT writable'];
    return $c;
}

function load_settings(): array {
    if (!file_exists(SETTINGS_FILE)) return [];
    $d = json_decode(file_get_contents(SETTINGS_FILE), true);
    return is_array($d) ? $d : [];
}

function load_config_values(): array {
    $out = ['key' => '', 'admin_pass' => ''];
    if (!file_exists(CONFIG_FILE)) return $out;
    $src = file_get_contents(CONFIG_FILE);
    if (preg_match("/define\('ENCRYPTION_KEY',\s*'([^']+)'\)/", $src, $m)) $out['key']        = $m[1];
    if (preg_match("/define\('ADMIN_PASSWORD',\s*'([^']+)'\)/", $src, $m)) $out['admin_pass'] = $m[1];
    return $out;
}

// ── Step POST handlers ────────────────────────────────────────

// Step 3 — DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3 && isset($_POST['save_db'])) {
    $_SESSION['db'] = [
        'mid'  => ['host' => trim($_POST['mid_host'] ?? ''),  'name' => trim($_POST['mid_name'] ?? 'MuOnlineTest'), 'user' => trim($_POST['mid_user'] ?? 'sa'), 'pass' => trim($_POST['mid_pass'] ?? '')],
        'hard' => ['host' => trim($_POST['hard_host'] ?? ''), 'name' => trim($_POST['hard_name'] ?? 'MuOnlineMid'),  'user' => trim($_POST['hard_user'] ?? 'sa'), 'pass' => trim($_POST['hard_pass'] ?? '')],
        'skip' => isset($_POST['skip_test']),
    ];
    $db = &$_SESSION['db'];
    if (!$db['skip']) {
        $any_ok = false;
        foreach (['mid', 'hard'] as $s) {
            if (empty($db[$s]['host'])) continue;
            $r = test_mssql($db[$s]['host'], $db[$s]['name'], $db[$s]['user'], $db[$s]['pass']);
            if ($r['ok']) { $any_ok = true; }
            else          { $errors[] = strtoupper($s) . ': ' . $r['msg']; }
        }
        if (!$any_ok && empty($errors)) $errors[] = 'At least one host must be filled.';
        if (!$any_ok && !empty($errors)) { /* stay on step 3 */ }
    }
    if (empty($errors)) { header('Location: ?step=4'); exit; }
}

// Step 4 — Site settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 4 && isset($_POST['save_site'])) {
    $_SESSION['site'] = [
        // General
        'title'          => trim($_POST['site_title']     ?? 'Kyana MU'),
        'show_online'    => isset($_POST['show_online']),
        // Admin
        'admin_pass'     => trim($_POST['admin_pass']     ?? ''),
        'admin_pass2'    => trim($_POST['admin_pass2']    ?? ''),
        'regen_key'      => isset($_POST['regen_key']),
        // Server display
        'mid_name'       => trim($_POST['mid_name']       ?? 'Server 1'),
        'mid_address'    => trim($_POST['mid_address']    ?? '127.0.0.1'),
        'mid_port'       => (int)($_POST['mid_port']      ?? 55902),
        'mid_visible'    => isset($_POST['mid_visible']),
        'hard_name'      => trim($_POST['hard_name']      ?? 'Server 2'),
        'hard_address'   => trim($_POST['hard_address']   ?? '127.0.0.1'),
        'hard_port'      => (int)($_POST['hard_port']     ?? 55902),
        'hard_visible'   => isset($_POST['hard_visible']),
        // server_names block
        'srv_mid_label'  => trim($_POST['srv_mid_label']  ?? 'Server 1'),
        'srv_hard_label' => trim($_POST['srv_hard_label'] ?? 'Server 2'),
        // Downloads
        'dl1_label'      => trim($_POST['dl1_label']      ?? 'Mediafire'),
        'dl1_url'        => trim($_POST['dl1_url']        ?? '#'),
        'dl2_label'      => trim($_POST['dl2_label']      ?? 'Mega'),
        'dl2_url'        => trim($_POST['dl2_url']        ?? '#'),
        // Session
        'sess_admin'     => (int)($_POST['sess_admin']    ?? 30),
        'sess_user'      => (int)($_POST['sess_user']     ?? 10),
        // Conversion rates
        'wcoinc'         => (int)($_POST['wcoinc']        ?? 30),
        'wcoinp'         => (int)($_POST['wcoinp']        ?? 25),
        'goblin'         => (int)($_POST['goblin']        ?? 5),
        // Webshop prices
        'price_level'    => (int)($_POST['price_level']   ?? 10),
        'price_exc'      => (int)($_POST['price_exc']     ?? 50),
        'price_luck_skill'=> (int)($_POST['price_luck_skill'] ?? 25),
    ];
    $s = $_SESSION['site'];
    if (empty($s['admin_pass']))                        $errors[] = 'Admin password is required.';
    elseif ($s['admin_pass'] !== $s['admin_pass2'])     $errors[] = 'Passwords do not match.';
    elseif (strlen($s['admin_pass']) < 8)               $errors[] = 'Password must be at least 8 characters.';
    if (empty($errors)) { header('Location: ?step=5'); exit; }
}

// Step 5 — Write files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 5 && isset($_POST['install'])) {
    if (file_exists(LOCK_FILE)) {
        $errors[] = 'Already installed. Delete install.lock to re-run.';
    } else {
        $db      = $_SESSION['db']   ?? [];
        $site    = $_SESSION['site'] ?? [];
        $ex_cfg  = load_config_values();
        $ex_set  = load_settings();

        // 1. Encryption key
        $enc_key = ($site['regen_key'] || empty($ex_cfg['key']))
            ? bin2hex(random_bytes(16))
            : $ex_cfg['key'];

        // 2. Write config.php — preserve comment style
        $cfg  = "<?php\n";
        $cfg .= "// config.php\n\n";
        $cfg .= "// --- IMPORTANT SECURITY ---\n";
        $cfg .= "define('ENCRYPTION_KEY', '" . addslashes($enc_key) . "'); //\n\n";
        $cfg .= "// --- ADMIN CONFIGURATION ---\n";
        $cfg .= "define('ADMIN_PASSWORD', '" . addslashes($site['admin_pass']) . "'); //\n\n";
        $cfg .= "// --- DO NOT EDIT BELOW ---\n";
        $cfg .= "define('ENCRYPTION_CIPHER', 'aes-256-cbc'); //\n\n";
        $cfg .= "// Automatically load the global encryption functions for all scripts\n";
        $cfg .= "require_once __DIR__ . '/encryption.php'; //\n";
        $cfg .= "?>\n";

        if (file_put_contents(CONFIG_FILE, $cfg) === false)
            $errors[] = 'Cannot write config.php — check file permissions.';

        // 3. Build settings.json — merge over existing so payment keys etc. survive
        if (empty($errors)) {
            if (!is_dir(ROOT . '/Configuration'))
                mkdir(ROOT . '/Configuration', 0755, true);

            // Decide encrypted passwords: re-encrypt if a new plaintext was given
            $mid_enc  = $ex_set['database']['mid_rate']['pass_encrypted']  ?? '';
            $hard_enc = $ex_set['database']['hard_rate']['pass_encrypted'] ?? '';
            if (!empty($db['mid']['pass']))  $mid_enc  = encrypt_value($db['mid']['pass'],  $enc_key);
            if (!empty($db['hard']['pass'])) $hard_enc = encrypt_value($db['hard']['pass'], $enc_key);

            // Start from existing settings so we never lose payment keys, tracked_items, etc.
            $s = $ex_set;

            // --- Keys we always overwrite ---
            $s['website_title']    = $site['title'];
            $s['show_online_count']= $site['show_online'];

            $s['security']['session_timeout_minutes']      = $site['sess_admin'];
            $s['security']['user_session_timeout_minutes'] = $site['sess_user'];

            $s['database']['mid_rate']['host']          = $db['mid']['host']  ?? ($ex_set['database']['mid_rate']['host']  ?? '');
            $s['database']['mid_rate']['name']          = $db['mid']['name']  ?? ($ex_set['database']['mid_rate']['name']  ?? '');
            $s['database']['mid_rate']['user']          = $db['mid']['user']  ?? ($ex_set['database']['mid_rate']['user']  ?? 'sa');
            $s['database']['mid_rate']['pass_encrypted']= $mid_enc;

            $s['database']['hard_rate']['host']          = $db['hard']['host'] ?? ($ex_set['database']['hard_rate']['host'] ?? '');
            $s['database']['hard_rate']['name']          = $db['hard']['name'] ?? ($ex_set['database']['hard_rate']['name'] ?? '');
            $s['database']['hard_rate']['user']          = $db['hard']['user'] ?? ($ex_set['database']['hard_rate']['user'] ?? 'sa');
            $s['database']['hard_rate']['pass_encrypted']= $hard_enc;

            $s['mid_rate_server']  = ['name' => $site['mid_name'],  'address' => $site['mid_address'],  'port' => $site['mid_port'],  'visible' => $site['mid_visible']];
            $s['hard_rate_server'] = ['name' => $site['hard_name'], 'address' => $site['hard_address'], 'port' => $site['hard_port'], 'visible' => $site['hard_visible']];

            $s['server_names'] = ['mid_rate' => $site['srv_mid_label'], 'hard_rate' => $site['srv_hard_label']];

            $s['download_link_1'] = ['label' => $site['dl1_label'], 'url' => $site['dl1_url']];
            $s['download_link_2'] = ['label' => $site['dl2_label'], 'url' => $site['dl2_url']];

            $s['conversion_rates'] = ['wcoinc' => $site['wcoinc'], 'wcoinp' => $site['wcoinp'], 'goblin' => $site['goblin']];

            $s['webshop'] = ['price_level' => $site['price_level'], 'price_exc' => $site['price_exc'], 'price_luck_skill' => $site['price_luck_skill']];

            // --- Keys that must exist on fresh install but are never overwritten if present ---
            $s['favicon_url']   = $s['favicon_url']   ?? 'uploads/favicon.ico';
            $s['wallpaper_url'] = $s['wallpaper_url'] ?? 'uploads/wallpaper.jpg';
            $s['tracked_items'] = $s['tracked_items'] ?? [];
            $s['qr_ph']         = $s['qr_ph']         ?? ['enabled' => false, 'ratio' => 100, 'mid_rate' => ['enabled' => false, 'ratio' => 100], 'hard_rate' => ['enabled' => false, 'ratio' => 100]];
            $s['paypal']        = $s['paypal']        ?? ['mid_rate' => ['enabled' => false, 'mode' => 'sandbox', 'currency' => 'USD', 'rate' => 100, 'client_id' => '', 'secret' => '']];
            $s['paymongo']      = $s['paymongo']      ?? ['mid_rate' => ['enabled' => false, 'rate' => 100, 'public_key' => '', 'secret_key' => ''], 'hard_rate' => ['enabled' => false, 'rate' => 100, 'public_key' => '', 'secret_key' => '']];
            $s['user_dashboard']= $s['user_dashboard']?? ['mid_rate' => ['enable_webshop' => true, 'enable_reset' => true, 'enable_reset_stats' => true, 'enable_clear_pk' => true, 'enable_reset_master' => true, 'enable_unstuck' => true]];

            if (file_put_contents(SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false)
                $errors[] = 'Cannot write Configuration/settings.json — check permissions.';
        }

        // 4. Create upload folders
        if (empty($errors)) {
            foreach (['uploads', 'uploads/proofs', 'uploads/qr-ph', 'uploads/items'] as $dir) {
                $p = ROOT . '/' . $dir;
                if (!is_dir($p)) mkdir($p, 0755, true);
            }
        }

        // 5. Create SQL tables on both DB servers
        $sql_map = [
            'WebCredits' =>
                "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'WebCredits') AND type='U')
                 CREATE TABLE WebCredits (memb___id varchar(10) NOT NULL, credits int NOT NULL DEFAULT 0,
                 CONSTRAINT PK_WebCredits PRIMARY KEY (memb___id))",
            'WebshopItems' =>
                "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'WebshopItems') AND type='U')
                 CREATE TABLE WebshopItems (
                     ItemType int NOT NULL, ItemIndex int NOT NULL, ItemName varchar(100) NULL,
                     Width int DEFAULT 1, Height int DEFAULT 1, BasePrice int DEFAULT 100,
                     IsActive bit DEFAULT 1, AllowExc bit DEFAULT 1, AllowLevel bit DEFAULT 1,
                     Allow380 bit DEFAULT 0, AllowHarmony bit DEFAULT 1, AllowSocket bit DEFAULT 0,
                     MaxExc int DEFAULT 6, MaxSocket int DEFAULT 0, AllowLuck bit DEFAULT 1,
                     AllowSkill bit DEFAULT 1, AllowAncient bit DEFAULT 0,
                     AncName1 varchar(50) NULL, AncName2 varchar(50) NULL,
                     CONSTRAINT PK_WebshopItems PRIMARY KEY (ItemType, ItemIndex))",
            'PendingDonations' =>
                "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'PendingDonations') AND type='U')
                 CREATE TABLE PendingDonations (
                     ID int IDENTITY(1,1) PRIMARY KEY, AccountID varchar(50) NOT NULL,
                     CreditsToReceive int NOT NULL, ReferenceNumber varchar(100) NOT NULL,
                     ProofImage varchar(255) NOT NULL, DateSubmitted datetime DEFAULT GETDATE(), Status tinyint DEFAULT 0)",
            'Webshop_Logs' =>
                "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'Webshop_Logs') AND type='U')
                 CREATE TABLE Webshop_Logs (
                     ID int IDENTITY(1,1) PRIMARY KEY, AccountID varchar(50) NOT NULL,
                     ItemName varchar(100) NULL, ItemOptions varchar(500) NULL,
                     Price int DEFAULT 0, ServerKey varchar(20) NULL, PurchaseDate datetime DEFAULT GETDATE())",
        ];

        $db_results = [];
        if (empty($errors)) {
            foreach (['mid', 'hard'] as $srv) {
                if (empty($db[$srv]['host'])) continue;
                $r = test_mssql($db[$srv]['host'], $db[$srv]['name'], $db[$srv]['user'], $db[$srv]['pass']);
                if (!$r['ok']) { $db_results[$srv] = ['ok' => false, 'tables' => [], 'msg' => $r['msg']]; continue; }
                $tables = [];
                foreach ($sql_map as $tbl => $sql) {
                    $stmt = sqlsrv_query($r['conn'], $sql);
                    $e    = sqlsrv_errors();
                    $tables[$tbl] = ($stmt === false) ? '❌ ' . ($e ? $e[0]['message'] : 'error') : '✅ OK';
                }
                sqlsrv_close($r['conn']);
                $db_results[$srv] = ['ok' => true, 'tables' => $tables, 'msg' => ''];
            }
            $_SESSION['db_results']  = $db_results;
            $_SESSION['key_renewed'] = $site['regen_key'];

            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
            header('Location: ?step=6'); exit;
        }
    }
}

// Gate: already locked
if (file_exists(LOCK_FILE) && $step !== 6) $step = 99;

// Pre-load existing data for form pre-fill
$ex_set = load_settings();
$ex_cfg = load_config_values();
$is_reinstall = !empty($ex_set);
$prereqs = check_prereqs();
$prereqs_ok = !in_array(false, array_column($prereqs, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kyana CMS — Installer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d0d12;--surface:#16161e;--border:#252530;
  --accent:#7dce82;--blue:#4fa3e0;--danger:#e05c5c;--warn:#e0b84f;
  --text:#e8e8f0;--muted:#888899;--r:10px;
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:40px 16px 80px;}
h1{font-size:1.9em;font-weight:800;color:var(--accent);letter-spacing:-.5px;}
.sub{color:var(--muted);margin-top:5px;font-size:.9em;}
header{text-align:center;margin-bottom:34px;}
/* stepper */
.stepper{display:flex;max-width:640px;width:100%;margin-bottom:30px;}
.si{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
.si:not(:last-child)::after{content:'';position:absolute;top:17px;left:55%;width:90%;height:2px;background:var(--border);z-index:0;}
.si.done:not(:last-child)::after{background:var(--accent);}
.dot{width:34px;height:34px;border-radius:50%;background:var(--border);color:var(--muted);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85em;z-index:1;position:relative;border:2px solid var(--border);}
.si.done .dot{background:var(--accent);color:#000;border-color:var(--accent);}
.si.active .dot{background:var(--surface);color:var(--accent);border-color:var(--accent);}
.slabel{font-size:.7em;color:var(--muted);margin-top:5px;text-align:center;}
.si.active .slabel{color:var(--accent);font-weight:600;}
/* card */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:30px;max-width:760px;width:100%;}
.card h2{font-size:1.2em;color:var(--accent);margin-bottom:5px;}
.csub{color:var(--muted);font-size:.86em;margin-bottom:22px;line-height:1.6;}
/* fields */
.f{margin-bottom:14px;}
.f label{display:block;font-size:.78em;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;}
.f input,.f select{width:100%;padding:9px 13px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:.93em;outline:none;transition:border-color .2s;}
.f input:focus,.f select:focus{border-color:var(--accent);}
.f small{color:var(--muted);font-size:.77em;margin-top:3px;display:block;}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:13px;}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:13px;}
@media(max-width:540px){.g2,.g3{grid-template-columns:1fr;}}
/* server block */
.sblock{border:1px solid var(--border);border-radius:8px;padding:18px 16px 10px;margin-bottom:18px;position:relative;}
.stag{position:absolute;top:-11px;left:13px;background:var(--surface);padding:1px 10px;border:1px solid var(--border);border-radius:20px;font-size:.72em;font-weight:700;color:var(--blue);letter-spacing:.05em;text-transform:uppercase;}
/* buttons */
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border:none;border-radius:6px;font-size:.93em;font-weight:700;cursor:pointer;text-decoration:none;transition:opacity .2s;}
.btn:hover{opacity:.82;}
.btn-p{background:var(--accent);color:#000;}
.btn-s{background:var(--border);color:var(--text);}
.btn-install{background:linear-gradient(135deg,#28a745,var(--accent));color:#000;font-size:1em;padding:13px 38px;}
.btn[disabled]{opacity:.35;cursor:not-allowed;}
.btn-row{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap;}
/* alerts */
.alert{border-radius:6px;padding:11px 15px;margin-bottom:14px;font-size:.88em;line-height:1.5;}
.alert ul{margin-left:16px;margin-top:4px;}
.ad{background:rgba(224,92,92,.13);border:1px solid var(--danger);color:#f08080;}
.as{background:rgba(125,206,130,.1);border:1px solid var(--accent);color:var(--accent);}
.aw{background:rgba(224,184,79,.1);border:1px solid var(--warn);color:var(--warn);}
.ai{background:rgba(79,163,224,.1);border:1px solid var(--blue);color:var(--blue);}
/* prereq table */
.ptable{width:100%;border-collapse:collapse;font-size:.87em;}
.ptable td{padding:9px 11px;border-bottom:1px solid var(--border);}
.ptable tr:last-child td{border-bottom:none;}
.badge{display:inline-block;padding:1px 9px;border-radius:20px;font-size:.76em;font-weight:700;}
.bok{background:rgba(125,206,130,.18);color:var(--accent);}
.bfail{background:rgba(224,92,92,.18);color:var(--danger);}
/* sql results */
.sr{border:1px solid var(--border);border-radius:8px;margin-bottom:14px;overflow:hidden;}
.sr-head{background:rgba(255,255,255,.04);padding:9px 15px;font-weight:700;font-size:.88em;}
.sr-body{padding:0 15px;}
.sr-row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:.86em;}
.sr-row:last-child{border-bottom:none;}
/* misc */
hr.div{border:none;border-top:1px solid var(--border);margin:20px 0;}
code{background:rgba(255,255,255,.07);padding:1px 5px;border-radius:4px;font-size:.87em;}
.toggle{display:flex;align-items:center;gap:9px;font-size:.9em;margin-bottom:11px;cursor:pointer;}
.toggle input{width:15px;height:15px;accent-color:var(--accent);}
.saved{display:inline-block;background:rgba(79,163,224,.15);border:1px solid var(--blue);color:var(--blue);padding:1px 7px;border-radius:4px;font-size:.72em;font-weight:700;margin-left:5px;}
.summary-table{width:100%;border-collapse:collapse;font-size:.87em;margin-bottom:18px;}
.summary-table td{padding:8px 11px;border-bottom:1px solid var(--border);vertical-align:top;}
.summary-table td:first-child{color:var(--muted);width:190px;white-space:nowrap;}
.summary-table tr:last-child td{border-bottom:none;}
.done-list{list-style:none;padding:0;margin:18px 0;}
.done-list li{padding:9px 0;border-bottom:1px solid var(--border);font-size:.89em;display:flex;gap:9px;}
.done-list li:last-child{border-bottom:none;}
</style>
</head>
<body>

<header>
  <h1>⚔ Kyana CMS Installer</h1>
  <p class="sub"><?= $is_reinstall ? '🔄 Re-configuration mode — existing settings detected' : 'Fresh installation' ?></p>
</header>

<?php if ($step < 7 && $step !== 99): ?>
<div class="stepper">
<?php foreach (['Welcome','Prerequisites','Database','Settings','Install','Done'] as $i => $label):
  $n = $i + 1; $cls = ($n < $step) ? 'done' : ($n === $step ? 'active' : ''); ?>
  <div class="si <?= $cls ?>">
    <div class="dot"><?= $n < $step ? '✓' : $n ?></div>
    <div class="slabel"><?= $label ?></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">

<?php /* ═══ 99 — Locked ═══════════════════════════════════════════ */
if ($step === 99): ?>
  <div style="text-align:center;padding:30px 0;">
    <div style="font-size:3em;margin-bottom:14px;">🔒</div>
    <h2 style="color:var(--warn);">Already Installed</h2>
    <p style="color:var(--muted);margin-top:10px;line-height:1.7;">
      <code>install.lock</code> exists. Delete it from the web root to re-run.<br>
      <strong>Never leave the installer accessible on a production server.</strong>
    </p>
    <div class="btn-row" style="justify-content:center;margin-top:24px;">
      <a href="../index.html" class="btn btn-p">🌐 Website</a>
      <a href="../AdminCP/dashboard.php" class="btn btn-s">🔧 Admin Panel</a>
    </div>
  </div>

<?php /* ═══ STEP 1 — Welcome ════════════════════════════════════════ */
elseif ($step === 1): ?>
  <h2>Welcome</h2>
  <p class="csub">This wizard configures <code>config.php</code> and <code>Configuration/settings.json</code>, then creates the required SQL tables.</p>

  <?php if ($is_reinstall): ?>
  <div class="alert ai">ℹ️ Existing <code>settings.json</code> found. Fields will be pre-filled. Payment keys, tracked items, and media URLs will be <strong>preserved</strong>.</div>
  <?php endif; ?>
  <div class="alert aw">⚠️ <strong>Delete <code>install/install.php</code></strong> immediately after installation.</div>

  <p style="font-size:.87em;color:var(--muted);line-height:2;">
    What this installer does:<br>
    ✦ Checks PHP extensions &amp; folder permissions<br>
    ✦ Tests your MS SQL connection(s)<br>
    ✦ Writes Admin CP password &amp; AES-256 encryption key to <code>config.php</code><br>
    ✦ Writes DB credentials, server config, download links, conversion rates, webshop prices, and session timeouts to <code>settings.json</code><br>
    ✦ Preserves: PayPal / PayMongo / QR keys, tracked items, favicon, wallpaper<br>
    ✦ Creates SQL tables: <code>WebCredits</code>, <code>WebshopItems</code>, <code>PendingDonations</code>, <code>Webshop_Logs</code>
  </p>

  <div class="btn-row"><a href="?step=2" class="btn btn-p">Get Started →</a></div>

<?php /* ═══ STEP 2 — Prerequisites ═════════════════════════════════ */
elseif ($step === 2): ?>
  <h2>Prerequisites</h2>
  <p class="csub">Verifying your server environment.</p>

  <?php if (!$prereqs_ok): ?>
  <div class="alert ad">❌ One or more checks failed. Fix them before continuing.</div>
  <?php else: ?>
  <div class="alert as">✅ All checks passed!</div>
  <?php endif; ?>

  <table class="ptable">
    <?php foreach ($prereqs as $c): ?>
    <tr>
      <td><?= htmlspecialchars($c['label']) ?></td>
      <td style="color:var(--muted);font-size:.84em;"><?= htmlspecialchars($c['val']) ?></td>
      <td style="width:70px;text-align:right;"><span class="badge <?= $c['ok'] ? 'bok' : 'bfail' ?>"><?= $c['ok'] ? 'PASS' : 'FAIL' ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <div class="btn-row">
    <a href="?step=1" class="btn btn-s">← Back</a>
    <a href="?step=3" class="btn btn-p <?= !$prereqs_ok ? 'disabled' : '' ?>" <?= !$prereqs_ok ? 'onclick="return false"' : '' ?>>Continue →</a>
  </div>

<?php /* ═══ STEP 3 — Database ══════════════════════════════════════ */
elseif ($step === 3):
  $d       = $_SESSION['db'] ?? [];
  $ex_mid  = $ex_set['database']['mid_rate']  ?? [];
  $ex_hard = $ex_set['database']['hard_rate'] ?? [];
?>
  <h2>Database Configuration</h2>
  <p class="csub">MS SQL Server credentials for both game databases. Leave password blank to keep the existing encrypted value.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert ad"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>

  <?php if ($is_reinstall): ?>
  <div class="alert ai">ℹ️ Pre-filled from your existing settings. <strong>Leave passwords blank</strong> to keep current encrypted values.</div>
  <?php endif; ?>

  <form method="POST" action="?step=3">
    <div class="sblock">
      <div class="stag">Server 1 — Mid Rate</div>
      <div class="g2">
        <div class="f">
          <label>Host / IP <?php if(!empty($ex_mid['host'])) echo '<span class="saved">saved</span>'; ?></label>
          <input type="text" name="mid_host" value="<?= htmlspecialchars($d['mid']['host'] ?? $ex_mid['host'] ?? '') ?>" placeholder="e.g. 139.99.24.220">
        </div>
        <div class="f">
          <label>Database Name <?php if(!empty($ex_mid['name'])) echo '<span class="saved">saved</span>'; ?></label>
          <input type="text" name="mid_name" value="<?= htmlspecialchars($d['mid']['name'] ?? $ex_mid['name'] ?? 'MuOnlineTest') ?>">
        </div>
        <div class="f">
          <label>SQL Username</label>
          <input type="text" name="mid_user" value="<?= htmlspecialchars($d['mid']['user'] ?? $ex_mid['user'] ?? 'sa') ?>">
        </div>
        <div class="f">
          <label>SQL Password <?php if(!empty($ex_mid['pass_encrypted'])) echo '<span class="saved">encrypted stored</span>'; ?></label>
          <input type="password" name="mid_pass" placeholder="<?= !empty($ex_mid['pass_encrypted']) ? 'Blank = keep existing' : 'Enter password' ?>">
        </div>
      </div>
    </div>

    <div class="sblock">
      <div class="stag">Server 2 — Hard Rate (optional)</div>
      <div class="g2">
        <div class="f">
          <label>Host / IP <?php if(!empty($ex_hard['host'])) echo '<span class="saved">saved</span>'; ?></label>
          <input type="text" name="hard_host" value="<?= htmlspecialchars($d['hard']['host'] ?? $ex_hard['host'] ?? '') ?>" placeholder="Leave blank to skip">
        </div>
        <div class="f">
          <label>Database Name <?php if(!empty($ex_hard['name'])) echo '<span class="saved">saved</span>'; ?></label>
          <input type="text" name="hard_name" value="<?= htmlspecialchars($d['hard']['name'] ?? $ex_hard['name'] ?? 'MuOnlineMid') ?>">
        </div>
        <div class="f">
          <label>SQL Username</label>
          <input type="text" name="hard_user" value="<?= htmlspecialchars($d['hard']['user'] ?? $ex_hard['user'] ?? 'sa') ?>">
        </div>
        <div class="f">
          <label>SQL Password <?php if(!empty($ex_hard['pass_encrypted'])) echo '<span class="saved">encrypted stored</span>'; ?></label>
          <input type="password" name="hard_pass" placeholder="<?= !empty($ex_hard['pass_encrypted']) ? 'Blank = keep existing' : 'Enter password' ?>">
        </div>
      </div>
    </div>

    <label class="toggle">
      <input type="checkbox" name="skip_test"> Skip connection test (use if web server can't reach the game server directly)
    </label>

    <div class="btn-row">
      <a href="?step=2" class="btn btn-s">← Back</a>
      <button type="submit" name="save_db" class="btn btn-p">Test &amp; Continue →</button>
    </div>
  </form>

<?php /* ═══ STEP 4 — Settings ═════════════════════════════════════ */
elseif ($step === 4):
  $sv   = $_SESSION['site'] ?? [];
  $exms = $ex_set['mid_rate_server']  ?? [];
  $exhs = $ex_set['hard_rate_server'] ?? [];
  $exsn = $ex_set['server_names']     ?? [];
  $exsc = $ex_set['security']         ?? [];
  $exd1 = $ex_set['download_link_1']  ?? [];
  $exd2 = $ex_set['download_link_2']  ?? [];
  $excr = $ex_set['conversion_rates'] ?? [];
  $exws = $ex_set['webshop']          ?? [];
?>
  <h2>Site Settings</h2>
  <p class="csub">All settings that go into <code>settings.json</code> and <code>config.php</code>.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert ad"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="POST" action="?step=4">

    <!-- ── General ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">🌐 General</p>
    <div class="f">
      <label>Website Title</label>
      <input type="text" name="site_title" value="<?= htmlspecialchars($sv['title'] ?? $ex_set['website_title'] ?? 'Kyana MU') ?>">
    </div>
    <label class="toggle">
      <input type="checkbox" name="show_online" <?= ($sv['show_online'] ?? ($ex_set['show_online_count'] ?? true)) ? 'checked' : '' ?>>
      Show online player count on homepage
    </label>

    <hr class="div">

    <!-- ── Admin password ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">🔐 Admin CP Password (written to config.php)</p>
    <?php if (!empty($ex_cfg['admin_pass'])): ?>
    <div class="alert ai" style="margin-bottom:12px;">Current password is set. Enter a new one to change it.</div>
    <?php endif; ?>
    <div class="g2">
      <div class="f">
        <label>New Password <span style="font-size:.8em;color:var(--muted);">(min 8 chars)</span></label>
        <input type="password" name="admin_pass" autocomplete="new-password">
      </div>
      <div class="f">
        <label>Confirm Password</label>
        <input type="password" name="admin_pass2" autocomplete="new-password">
      </div>
    </div>
    <label class="toggle" style="background:rgba(224,92,92,.08);padding:10px;border-radius:6px;border:1px solid rgba(224,92,92,.25);">
      <input type="checkbox" name="regen_key" style="accent-color:var(--danger);">
      <span style="color:var(--danger);">⚠️ Generate a NEW encryption key — invalidates all stored API keys &amp; DB passwords</span>
    </label>

    <hr class="div">

    <!-- ── Servers ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">🖥 Server Configuration</p>

    <div class="sblock">
      <div class="stag">Server 1 — Mid Rate</div>
      <label class="toggle">
        <input type="checkbox" name="mid_visible" <?= ($sv['mid_visible'] ?? ($exms['visible'] ?? true)) ? 'checked' : '' ?>>
        Visible on homepage
      </label>
      <div class="g3">
        <div class="f">
          <label>Display Name</label>
          <input type="text" name="mid_name" value="<?= htmlspecialchars($sv['mid_name'] ?? $exms['name'] ?? 'Server 1') ?>">
          <small>Used in <code>mid_rate_server.name</code></small>
        </div>
        <div class="f">
          <label>Game Server IP</label>
          <input type="text" name="mid_address" value="<?= htmlspecialchars($sv['mid_address'] ?? $exms['address'] ?? '127.0.0.1') ?>">
        </div>
        <div class="f">
          <label>Game Port</label>
          <input type="number" name="mid_port" value="<?= (int)($sv['mid_port'] ?? $exms['port'] ?? 55902) ?>">
        </div>
      </div>
      <div class="f">
        <label>server_names label <span style="font-size:.8em;color:var(--muted);">(dropdown label in user dashboard)</span></label>
        <input type="text" name="srv_mid_label" value="<?= htmlspecialchars($sv['srv_mid_label'] ?? $exsn['mid_rate'] ?? 'Nebula 1') ?>">
        <small>Written to <code>server_names.mid_rate</code></small>
      </div>
    </div>

    <div class="sblock">
      <div class="stag">Server 2 — Hard Rate</div>
      <label class="toggle">
        <input type="checkbox" name="hard_visible" <?= ($sv['hard_visible'] ?? ($exhs['visible'] ?? false)) ? 'checked' : '' ?>>
        Visible on homepage
      </label>
      <div class="g3">
        <div class="f">
          <label>Display Name</label>
          <input type="text" name="hard_name" value="<?= htmlspecialchars($sv['hard_name'] ?? $exhs['name'] ?? 'Server 2') ?>">
        </div>
        <div class="f">
          <label>Game Server IP</label>
          <input type="text" name="hard_address" value="<?= htmlspecialchars($sv['hard_address'] ?? $exhs['address'] ?? '127.0.0.1') ?>">
        </div>
        <div class="f">
          <label>Game Port</label>
          <input type="number" name="hard_port" value="<?= (int)($sv['hard_port'] ?? $exhs['port'] ?? 55902) ?>">
        </div>
      </div>
      <div class="f">
        <label>server_names label</label>
        <input type="text" name="srv_hard_label" value="<?= htmlspecialchars($sv['srv_hard_label'] ?? $exsn['hard_rate'] ?? 'Server 2') ?>">
        <small>Written to <code>server_names.hard_rate</code></small>
      </div>
    </div>

    <hr class="div">

    <!-- ── Downloads ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">⬇ Download Links</p>
    <div class="g2">
      <div class="f">
        <label>Button 1 Label</label>
        <input type="text" name="dl1_label" value="<?= htmlspecialchars($sv['dl1_label'] ?? $exd1['label'] ?? 'Mediafire') ?>">
      </div>
      <div class="f">
        <label>Button 1 URL</label>
        <input type="text" name="dl1_url" value="<?= htmlspecialchars($sv['dl1_url'] ?? $exd1['url'] ?? '#') ?>">
      </div>
      <div class="f">
        <label>Button 2 Label</label>
        <input type="text" name="dl2_label" value="<?= htmlspecialchars($sv['dl2_label'] ?? $exd2['label'] ?? 'Mega') ?>">
      </div>
      <div class="f">
        <label>Button 2 URL</label>
        <input type="text" name="dl2_url" value="<?= htmlspecialchars($sv['dl2_url'] ?? $exd2['url'] ?? '#') ?>">
      </div>
    </div>

    <hr class="div">

    <!-- ── Session timeouts ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">⏱ Session Timeouts</p>
    <div class="g2">
      <div class="f">
        <label>Admin Panel (minutes)</label>
        <input type="number" name="sess_admin" min="1" value="<?= (int)($sv['sess_admin'] ?? $exsc['session_timeout_minutes'] ?? 30) ?>">
      </div>
      <div class="f">
        <label>User Dashboard (minutes)</label>
        <input type="number" name="sess_user" min="1" value="<?= (int)($sv['sess_user'] ?? $exsc['user_session_timeout_minutes'] ?? 10) ?>">
      </div>
    </div>

    <hr class="div">

    <!-- ── Conversion Rates ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">💱 Conversion Rates (Credits per 1 unit)</p>
    <div class="g3">
      <div class="f">
        <label>WCoinC Rate</label>
        <input type="number" name="wcoinc" min="1" value="<?= (int)($sv['wcoinc'] ?? $excr['wcoinc'] ?? 30) ?>">
        <small><code>conversion_rates.wcoinc</code></small>
      </div>
      <div class="f">
        <label>WCoinP Rate</label>
        <input type="number" name="wcoinp" min="1" value="<?= (int)($sv['wcoinp'] ?? $excr['wcoinp'] ?? 25) ?>">
        <small><code>conversion_rates.wcoinp</code></small>
      </div>
      <div class="f">
        <label>Goblin Points Rate</label>
        <input type="number" name="goblin" min="1" value="<?= (int)($sv['goblin'] ?? $excr['goblin'] ?? 5) ?>">
        <small><code>conversion_rates.goblin</code></small>
      </div>
    </div>

    <hr class="div">

    <!-- ── Webshop Prices ── -->
    <p style="font-size:.76em;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:12px;">🛒 Webshop Base Prices (Credits)</p>
    <div class="g3">
      <div class="f">
        <label>Per Level</label>
        <input type="number" name="price_level" min="0" value="<?= (int)($sv['price_level'] ?? $exws['price_level'] ?? 10) ?>">
        <small><code>webshop.price_level</code></small>
      </div>
      <div class="f">
        <label>Per Exc Option</label>
        <input type="number" name="price_exc" min="0" value="<?= (int)($sv['price_exc'] ?? $exws['price_exc'] ?? 50) ?>">
        <small><code>webshop.price_exc</code></small>
      </div>
      <div class="f">
        <label>Luck / Skill</label>
        <input type="number" name="price_luck_skill" min="0" value="<?= (int)($sv['price_luck_skill'] ?? $exws['price_luck_skill'] ?? 25) ?>">
        <small><code>webshop.price_luck_skill</code></small>
      </div>
    </div>

    <div class="btn-row">
      <a href="?step=3" class="btn btn-s">← Back</a>
      <button type="submit" name="save_site" class="btn btn-p">Save &amp; Continue →</button>
    </div>
  </form>

<?php /* ═══ STEP 5 — Confirm ══════════════════════════════════════ */
elseif ($step === 5):
  $db   = $_SESSION['db']   ?? [];
  $site = $_SESSION['site'] ?? [];
?>
  <h2>Review &amp; Install</h2>
  <p class="csub">Check the summary, then click <strong>Install Now</strong>.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert ad"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>

  <table class="summary-table">
    <tr><td>Website Title</td>       <td><?= htmlspecialchars($site['title'] ?? '') ?></td></tr>
    <tr><td>Admin Password</td>      <td>••••••••</td></tr>
    <tr><td>Encryption Key</td>      <td><?= $site['regen_key'] ? '<span style="color:var(--danger)">⚠️ NEW key — old API keys invalidated</span>' : '<span style="color:var(--accent)">✅ Existing key preserved</span>' ?></td></tr>
    <tr><td>DB Server 1</td>         <td><?= htmlspecialchars($db['mid']['host'] ?? '—') ?> / <?= htmlspecialchars($db['mid']['name'] ?? '') ?> <?= empty($db['mid']['pass']) ? '<span style="color:var(--blue)">(keeping stored password)</span>' : '<span style="color:var(--warn)">(new password)</span>' ?></td></tr>
    <tr><td>DB Server 2</td>         <td><?= !empty($db['hard']['host']) ? htmlspecialchars($db['hard']['host']).' / '.htmlspecialchars($db['hard']['name'] ?? '') : '<span style="color:var(--muted)">Skipped</span>' ?> <?= (!empty($db['hard']['host']) && empty($db['hard']['pass'])) ? '<span style="color:var(--blue)">(keeping stored password)</span>' : (!empty($db['hard']['pass']) ? '<span style="color:var(--warn)">(new password)</span>' : '') ?></td></tr>
    <tr><td>Server 1 display</td>    <td><?= htmlspecialchars($site['mid_name'] ?? '') ?> @ <?= htmlspecialchars($site['mid_address'] ?? '') ?>:<?= $site['mid_port'] ?? '' ?> <?= $site['mid_visible'] ? '' : '<span style="color:var(--muted)">(hidden)</span>' ?></td></tr>
    <tr><td>Server 2 display</td>    <td><?= htmlspecialchars($site['hard_name'] ?? '') ?> @ <?= htmlspecialchars($site['hard_address'] ?? '') ?>:<?= $site['hard_port'] ?? '' ?> <?= $site['hard_visible'] ? '' : '<span style="color:var(--muted)">(hidden)</span>' ?></td></tr>
    <tr><td>server_names</td>        <td>mid_rate: <em><?= htmlspecialchars($site['srv_mid_label'] ?? '') ?></em> &nbsp;|&nbsp; hard_rate: <em><?= htmlspecialchars($site['srv_hard_label'] ?? '') ?></em></td></tr>
    <tr><td>Download Links</td>      <td><?= htmlspecialchars($site['dl1_label'] ?? '') ?> &amp; <?= htmlspecialchars($site['dl2_label'] ?? '') ?></td></tr>
    <tr><td>Session Timeouts</td>    <td>Admin: <?= $site['sess_admin'] ?? 30 ?>m &nbsp;|&nbsp; User: <?= $site['sess_user'] ?? 10 ?>m</td></tr>
    <tr><td>Conversion Rates</td>    <td>WCoinC: <?= $site['wcoinc'] ?? '' ?> &nbsp;|&nbsp; WCoinP: <?= $site['wcoinp'] ?? '' ?> &nbsp;|&nbsp; Goblin: <?= $site['goblin'] ?? '' ?></td></tr>
    <tr><td>Webshop Prices</td>      <td>Level: <?= $site['price_level'] ?? '' ?> &nbsp;|&nbsp; Exc: <?= $site['price_exc'] ?? '' ?> &nbsp;|&nbsp; Luck/Skill: <?= $site['price_luck_skill'] ?? '' ?></td></tr>
    <tr><td>Preserved</td>           <td style="color:var(--accent)">PayPal, PayMongo, QR keys &bull; tracked_items &bull; favicon &bull; wallpaper &bull; user_dashboard</td></tr>
  </table>

  <?php if ($site['regen_key']): ?>
  <div class="alert ad">⚠️ New key selected — after install, re-enter PayPal &amp; PayMongo keys in Admin Panel → Donations.</div>
  <?php else: ?>
  <div class="alert as">✅ Existing encryption key preserved — all stored API keys remain valid.</div>
  <?php endif; ?>

  <form method="POST" action="?step=5">
    <div class="btn-row">
      <a href="?step=4" class="btn btn-s">← Back</a>
      <button type="submit" name="install" class="btn btn-install">⚡ Install Now</button>
    </div>
  </form>

<?php /* ═══ STEP 6 — Done ═══════════════════════════════════════ */
elseif ($step === 6):
  $db_results  = $_SESSION['db_results']  ?? [];
  $key_renewed = $_SESSION['key_renewed'] ?? false;
?>
  <div style="text-align:center;font-size:3.5em;margin-bottom:12px;">🎉</div>
  <h2 style="text-align:center;margin-bottom:5px;">Installation Complete!</h2>
  <p class="csub" style="text-align:center;">Kyana CMS has been configured successfully.</p>

  <?php foreach ($db_results as $srv => $res): ?>
  <div class="sr">
    <div class="sr-head">
      <?= $srv === 'mid' ? 'Server 1 (Mid Rate)' : 'Server 2 (Hard Rate)' ?> —
      <?= $res['ok'] ? '<span style="color:var(--accent)">✅ Connected</span>' : '<span style="color:var(--danger)">❌ '.htmlspecialchars($res['msg']).'</span>' ?>
    </div>
    <?php if ($res['ok'] && !empty($res['tables'])): ?>
    <div class="sr-body">
      <?php foreach ($res['tables'] as $t => $status): ?>
      <div class="sr-row"><span><?= htmlspecialchars($t) ?></span><span><?= $status ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <ul class="done-list">
    <li>✅ <code>config.php</code> written — <?= $key_renewed ? '<strong>new</strong> AES-256 key generated' : 'existing key preserved' ?></li>
    <li>✅ <code>Configuration/settings.json</code> updated</li>
    <li>✅ Upload folders ready (<code>uploads/</code>, <code>uploads/proofs/</code>, <code>uploads/qr-ph/</code>, <code>uploads/items/</code>)</li>
    <li>✅ SQL tables verified / created</li>
    <li>✅ <code>install.lock</code> created — installer locked</li>
  </ul>

  <?php if ($key_renewed): ?>
  <div class="alert ad">⚠️ New encryption key was generated. Go to <strong>Admin Panel → Donations</strong> and re-enter your PayPal &amp; PayMongo API keys. Also re-save DB passwords in <strong>Admin Panel → Database</strong>.</div>
  <?php endif; ?>

  <div class="alert ad" style="margin-top:10px;">🗑️ <strong>Delete <code>install/install.php</code> from your server right now!</strong></div>

  <div class="btn-row" style="justify-content:center;margin-top:18px;">
    <a href="../index.html" class="btn btn-p">🌐 Website</a>
    <a href="../AdminCP/dashboard.php" class="btn btn-s">🔧 Admin Panel</a>
  </div>

<?php endif; ?>
</div>
</body>
</html>