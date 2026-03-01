<?php
/**
 * ============================================================
 *  Kyana CMS — Installer
 *  Drop this file in your web root and visit it in a browser.
 *  Delete it after installation is complete.
 * ============================================================
 */

define('LOCK_FILE',    __DIR__ . '/install.lock');
define('CONFIG_FILE',  __DIR__ . '/config.php');
define('SETTINGS_FILE',__DIR__ . '/Configuration/settings.json');

session_start();
error_reporting(0);
ini_set('display_errors', 0);

$step   = (int)($_GET['step'] ?? 1);
$errors = [];

// ── Helpers ──────────────────────────────────────────────────
function encrypt_pass(string $password, string $key): string {
    $cipher = 'aes-256-cbc';
    $iv     = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $enc    = openssl_encrypt($password, $cipher, $key, 0, $iv);
    return base64_encode($enc . '::' . $iv);
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
        $errs = sqlsrv_errors();
        return ['ok' => false, 'msg' => $errs ? $errs[0]['message'] : 'Unknown connection error', 'conn' => null];
    }
    return ['ok' => true, 'msg' => 'Connected!', 'conn' => $conn];
}

function check_prereqs(): array {
    $checks = [];

    $checks[] = ['label' => 'PHP Version (7.4+)',           'ok' => version_compare(PHP_VERSION, '7.4.0', '>='), 'val' => PHP_VERSION];
    $checks[] = ['label' => 'sqlsrv Extension',             'ok' => function_exists('sqlsrv_connect'),           'val' => function_exists('sqlsrv_connect') ? 'Loaded' : 'MISSING — install php_sqlsrv driver'];
    $checks[] = ['label' => 'OpenSSL Extension',            'ok' => extension_loaded('openssl'),                 'val' => extension_loaded('openssl')  ? 'Loaded' : 'MISSING'];
    $checks[] = ['label' => 'cURL Extension',               'ok' => extension_loaded('curl'),                    'val' => extension_loaded('curl')     ? 'Loaded' : 'MISSING (required for PayPal / PayMongo)'];
    $checks[] = ['label' => 'GD / Image Extension',         'ok' => extension_loaded('gd'),                      'val' => extension_loaded('gd')       ? 'Loaded' : 'MISSING (required for guild emblems)'];

    $cfg_ok = is_writable(CONFIG_FILE) || (!file_exists(CONFIG_FILE) && is_writable(__DIR__));
    $checks[] = ['label' => 'config.php writable',          'ok' => $cfg_ok,  'val' => $cfg_ok  ? 'OK' : 'NOT writable — chmod 644 the file or 755 the folder'];

    $dir_ok = is_writable(__DIR__ . '/Configuration') || (!is_dir(__DIR__ . '/Configuration') && is_writable(__DIR__));
    $checks[] = ['label' => 'Configuration/ folder writable','ok' => $dir_ok, 'val' => $dir_ok  ? 'OK' : 'NOT writable — chmod 755'];

    $upl_ok = is_writable(__DIR__ . '/uploads') || (!is_dir(__DIR__ . '/uploads') && is_writable(__DIR__));
    $checks[] = ['label' => 'uploads/ folder writable',     'ok' => $upl_ok,  'val' => $upl_ok  ? 'OK' : 'NOT writable — chmod 755'];

    return $checks;
}

// ── POST handlers ─────────────────────────────────────────────

// Step 2 → 3
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2 && isset($_POST['next'])) {
    header('Location: ?step=3'); exit;
}

// Step 3: Save DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3 && isset($_POST['save_db'])) {
    $_SESSION['db'] = [
        'mid'  => ['host' => trim($_POST['mid_host']  ?? ''), 'name' => trim($_POST['mid_name']  ?? 'MuOnline'),    'user' => trim($_POST['mid_user']  ?? 'sa'), 'pass' => trim($_POST['mid_pass']  ?? '')],
        'hard' => ['host' => trim($_POST['hard_host'] ?? ''), 'name' => trim($_POST['hard_name'] ?? 'MuOnlineEly'), 'user' => trim($_POST['hard_user'] ?? 'sa'), 'pass' => trim($_POST['hard_pass'] ?? '')],
    ];
    $mid_ok = $hard_ok = false;
    if (!empty($_SESSION['db']['mid']['host'])) {
        $r = test_mssql($_SESSION['db']['mid']['host'], $_SESSION['db']['mid']['name'], $_SESSION['db']['mid']['user'], $_SESSION['db']['mid']['pass']);
        $mid_ok = $r['ok'];
        if (!$r['ok']) $errors[] = 'Server 1: ' . $r['msg'];
    }
    if (!empty($_SESSION['db']['hard']['host'])) {
        $r = test_mssql($_SESSION['db']['hard']['host'], $_SESSION['db']['hard']['name'], $_SESSION['db']['hard']['user'], $_SESSION['db']['hard']['pass']);
        $hard_ok = $r['ok'];
        if (!$r['ok']) $errors[] = 'Server 2: ' . $r['msg'];
    }
    if (!$mid_ok && !$hard_ok) $errors[] = 'At least one database connection must succeed.';
    if (empty($errors)) { header('Location: ?step=4'); exit; }
}

// Step 4: Save site settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 4 && isset($_POST['save_names'])) {
    $_SESSION['site'] = [
        'title'       => trim($_POST['site_title']    ?? 'Kyana MU'),
        'admin_pass'  => trim($_POST['admin_pass']    ?? ''),
        'admin_pass2' => trim($_POST['admin_pass2']   ?? ''),
        'mid_name'    => trim($_POST['mid_srv_name']  ?? 'Server 1'),
        'mid_addr'    => trim($_POST['mid_address']   ?? '127.0.0.1'),
        'mid_port'    => (int)($_POST['mid_port']     ?? 55901),
        'hard_name'   => trim($_POST['hard_srv_name'] ?? 'Server 2'),
        'hard_addr'   => trim($_POST['hard_address']  ?? '127.0.0.1'),
        'hard_port'   => (int)($_POST['hard_port']    ?? 55901),
    ];
    if (empty($_SESSION['site']['admin_pass']))                                   $errors[] = 'Admin password cannot be empty.';
    if ($_SESSION['site']['admin_pass'] !== $_SESSION['site']['admin_pass2'])     $errors[] = 'Admin passwords do not match.';
    if (strlen($_SESSION['site']['admin_pass']) < 8)                              $errors[] = 'Admin password must be at least 8 characters.';
    if (empty($_SESSION['site']['mid_name']) && empty($_SESSION['site']['hard_name'])) $errors[] = 'At least one server name is required.';
    if (empty($errors)) { header('Location: ?step=5'); exit; }
}

// Step 5: Run install
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 5 && isset($_POST['install'])) {
    if (file_exists(LOCK_FILE)) {
        $errors[] = 'Already installed. Delete install.lock to re-run.';
    } else {
        $db      = $_SESSION['db']   ?? [];
        $site    = $_SESSION['site'] ?? [];
        $enc_key = bin2hex(random_bytes(16));

        // 1. Write config.php
        $cfg = "<?php\ndefine('ENCRYPTION_KEY', '$enc_key');\ndefine('ADMIN_PASSWORD', '" . addslashes($site['admin_pass']) . "');\ndefine('ENCRYPTION_CIPHER', 'aes-256-cbc');\n";
        if (file_put_contents(CONFIG_FILE, $cfg) === false) $errors[] = 'Cannot write config.php — check permissions.';

        // 2. Write settings.json
        if (empty($errors)) {
            if (!is_dir(__DIR__ . '/Configuration')) mkdir(__DIR__ . '/Configuration', 0755, true);
            $mid_enc  = !empty($db['mid']['host'])  ? encrypt_pass($db['mid']['pass'],  $enc_key) : '';
            $hard_enc = !empty($db['hard']['host']) ? encrypt_pass($db['hard']['pass'], $enc_key) : '';
            $settings = [
                'website_title'   => $site['title'],
                'favicon_url'     => 'uploads/default-favicon.ico',
                'security'        => ['session_timeout_minutes' => 30, 'user_session_timeout_minutes' => 10],
                'user_dashboard'  => [
                    'mid_rate'  => ['enable_webshop'=>true,'enable_reset'=>true,'enable_reset_stats'=>true,'enable_clear_pk'=>true,'enable_reset_master'=>true,'enable_unstuck'=>true],
                    'hard_rate' => ['enable_webshop'=>true,'enable_reset'=>true,'enable_reset_stats'=>true,'enable_clear_pk'=>true,'enable_reset_master'=>true,'enable_unstuck'=>true],
                ],
                'database'        => [
                    'mid_rate'  => ['host'=>$db['mid']['host']??'',  'name'=>$db['mid']['name']??'MuOnline',    'user'=>$db['mid']['user']??'sa',  'pass_encrypted'=>$mid_enc],
                    'hard_rate' => ['host'=>$db['hard']['host']??'', 'name'=>$db['hard']['name']??'MuOnlineEly','user'=>$db['hard']['user']??'sa', 'pass_encrypted'=>$hard_enc],
                ],
                'server_names'    => ['mid_rate'=>$site['mid_name'], 'hard_rate'=>$site['hard_name']],
                'mid_rate_server' => ['name'=>$site['mid_name'], 'address'=>$site['mid_addr'], 'port'=>$site['mid_port'], 'visible'=>true],
                'hard_rate_server'=> ['name'=>$site['hard_name'],'address'=>$site['hard_addr'],'port'=>$site['hard_port'],'visible'=>true],
                'download_link_1' => ['label'=>'Mediafire','url'=>'#'],
                'download_link_2' => ['label'=>'Mega',     'url'=>'#'],
                'wallpaper_url'   => 'uploads/default-wallpaper.jpg',
                'conversion_rates'=> ['wcoinc'=>1,'wcoinp'=>1,'goblin'=>1],
                'webshop'         => ['mid_rate'=>['price_level'=>10,'price_exc'=>50,'price_luck_skill'=>25,'price_380'=>100,'price_harmony'=>100,'price_socket'=>50,'price_ancient'=>100],'hard_rate'=>['price_level'=>10,'price_exc'=>50,'price_luck_skill'=>25,'price_380'=>100,'price_harmony'=>100,'price_socket'=>50,'price_ancient'=>100]],
                'show_online_count'=> true,
                'paypal'          => ['enabled'=>false,'mode'=>'sandbox','client_id'=>'','secret'=>'','currency'=>'USD','rate'=>100,'mid_rate'=>['enabled'=>false,'mode'=>'sandbox','client_id'=>'','secret'=>'','currency'=>'USD','rate'=>100],'hard_rate'=>['enabled'=>false,'mode'=>'sandbox','client_id'=>'','secret'=>'','currency'=>'USD','rate'=>100]],
                'qr_ph'           => ['enabled'=>false,'ratio'=>100,'mid_rate'=>['enabled'=>false,'ratio'=>100],'hard_rate'=>['enabled'=>false,'ratio'=>100]],
                'paymongo'        => ['mid_rate'=>['enabled'=>false,'public_key'=>'','secret_key'=>'','rate'=>100],'hard_rate'=>['enabled'=>false,'public_key'=>'','secret_key'=>'','rate'=>100]],
                'tracked_items'   => [],
                'economy_tracking'=> ['mid_rate'=>[],'hard_rate'=>[]],
            ];
            if (file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT)) === false) $errors[] = 'Cannot write Configuration/settings.json — check permissions.';
        }

        // 3. Create folders
        if (empty($errors)) {
            foreach (['uploads', 'uploads/proofs'] as $dir) {
                if (!is_dir(__DIR__ . '/' . $dir)) mkdir(__DIR__ . '/' . $dir, 0755, true);
            }
        }

        // 4. Run SQL on each DB
        if (empty($errors)) {
            $sql_tables = [
                'WebCredits' =>
                    "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'WebCredits') AND type='U')
                     CREATE TABLE WebCredits (
                         memb___id varchar(10) NOT NULL,
                         credits int NOT NULL DEFAULT 0,
                         CONSTRAINT PK_WebCredits PRIMARY KEY (memb___id)
                     )",
                'WebshopItems' =>
                    "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'WebshopItems') AND type='U')
                     CREATE TABLE WebshopItems (
                         ID int IDENTITY(1,1) PRIMARY KEY,
                         ItemType int, ItemIndex int, ItemName varchar(100),
                         Width int DEFAULT 1, Height int DEFAULT 1,
                         BasePrice int DEFAULT 100, IsActive bit DEFAULT 1,
                         AllowExc bit DEFAULT 1, AllowLevel bit DEFAULT 1,
                         Allow380 bit DEFAULT 0, AllowHarmony bit DEFAULT 1,
                         AllowSocket bit DEFAULT 0, MaxExc int DEFAULT 6,
                         MaxSocket int DEFAULT 0, AllowLuck bit DEFAULT 1,
                         AllowSkill bit DEFAULT 1, AllowAncient bit DEFAULT 0,
                         AncName1 varchar(50), AncName2 varchar(50)
                     )",
                'PendingDonations' =>
                    "IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id=OBJECT_ID(N'PendingDonations') AND type='U')
                     CREATE TABLE PendingDonations (
                         ID int IDENTITY(1,1) PRIMARY KEY,
                         AccountID varchar(50) NOT NULL,
                         CreditsToReceive int NOT NULL,
                         ReferenceNumber varchar(100) NOT NULL,
                         ProofImage varchar(255) NOT NULL,
                         DateSubmitted datetime DEFAULT GETDATE(),
                         Status tinyint DEFAULT 0
                     )",
            ];

            $db_results = [];
            foreach (['mid','hard'] as $srv) {
                if (empty($db[$srv]['host'])) continue;
                $r = test_mssql($db[$srv]['host'], $db[$srv]['name'], $db[$srv]['user'], $db[$srv]['pass']);
                if (!$r['ok']) { $db_results[$srv] = ['ok'=>false,'tables'=>[],'msg'=>$r['msg']]; continue; }
                $conn = $r['conn'];
                $tbl_results = [];
                foreach ($sql_tables as $tbl => $sql) {
                    $stmt = sqlsrv_query($conn, $sql);
                    $errs = sqlsrv_errors();
                    $tbl_results[$tbl] = ($stmt === false) ? '❌ ' . ($errs ? $errs[0]['message'] : 'error') : '✅ OK';
                }
                sqlsrv_close($conn);
                $db_results[$srv] = ['ok'=>true,'tables'=>$tbl_results,'msg'=>''];
            }
            $_SESSION['db_results'] = $db_results;

            // 5. Write lock
            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
            header('Location: ?step=6'); exit;
        }
    }
}

// ── Gate: already installed ───────────────────────────────────
if (file_exists(LOCK_FILE) && $step !== 6) $step = 99;

$prereqs    = check_prereqs();
$prereqs_ok = !in_array(false, array_column($prereqs, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kyana CMS — Installer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f0f13;--surface:#18181f;--border:#2a2a35;
  --accent:#7dce82;--accent2:#4fa3e0;
  --danger:#e05c5c;--warn:#e0b84f;
  --text:#e8e8f0;--muted:#888899;--radius:10px;
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:40px 16px 60px;}
.installer-header{text-align:center;margin-bottom:36px;}
.installer-header h1{font-size:2em;font-weight:800;letter-spacing:-.5px;color:var(--accent);}
.installer-header p{color:var(--muted);margin-top:6px;font-size:.95em;}
/* Stepper */
.stepper{display:flex;gap:0;margin-bottom:36px;max-width:640px;width:100%;}
.step-item{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
.step-item:not(:last-child)::after{content:'';position:absolute;top:18px;left:55%;width:90%;height:2px;background:var(--border);z-index:0;}
.step-item.done:not(:last-child)::after{background:var(--accent);}
.step-dot{width:36px;height:36px;border-radius:50%;background:var(--border);color:var(--muted);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9em;position:relative;z-index:1;border:2px solid var(--border);transition:all .25s;}
.step-item.done .step-dot{background:var(--accent);color:#000;border-color:var(--accent);}
.step-item.active .step-dot{background:var(--surface);color:var(--accent);border-color:var(--accent);}
.step-label{font-size:.72em;color:var(--muted);margin-top:6px;text-align:center;}
.step-item.active .step-label{color:var(--accent);font-weight:600;}
/* Card */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:32px;max-width:700px;width:100%;}
.card h2{font-size:1.25em;margin-bottom:6px;color:var(--accent);}
.subtitle{color:var(--muted);font-size:.88em;margin-bottom:24px;line-height:1.6;}
/* Form */
.field{margin-bottom:18px;}
.field label{display:block;font-size:.82em;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;}
.field input{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:.95em;outline:none;transition:border-color .2s;}
.field input:focus{border-color:var(--accent);}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:520px){.grid-2{grid-template-columns:1fr;}}
/* Server block */
.server-block{border:1px solid var(--border);border-radius:8px;padding:20px 18px 10px;margin-bottom:22px;position:relative;}
.server-block .server-tag{position:absolute;top:-12px;left:14px;background:var(--surface);padding:2px 10px;border:1px solid var(--border);border-radius:20px;font-size:.75em;font-weight:700;color:var(--accent2);letter-spacing:.05em;text-transform:uppercase;}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 26px;border:none;border-radius:6px;font-size:.95em;font-weight:700;cursor:pointer;transition:opacity .2s,transform .1s;text-decoration:none;}
.btn:hover{opacity:.85;}
.btn:active{transform:scale(.98);}
.btn-primary{background:var(--accent);color:#000;}
.btn-secondary{background:var(--border);color:var(--text);}
.btn-install{background:linear-gradient(135deg,#28a745,var(--accent));color:#000;font-size:1.05em;padding:14px 40px;}
.btn-row{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;}
.btn[disabled]{opacity:.4;cursor:not-allowed;}
/* Alerts */
.alert{border-radius:6px;padding:12px 16px;margin-bottom:16px;font-size:.9em;line-height:1.5;}
.alert-danger {background:rgba(224,92,92,.15); border:1px solid var(--danger);color:#f08080;}
.alert-success{background:rgba(125,206,130,.12);border:1px solid var(--accent);color:var(--accent);}
.alert-warn   {background:rgba(224,184,79,.12); border:1px solid var(--warn);  color:var(--warn);}
.alert ul{margin-left:16px;margin-top:4px;}
/* Prereq table */
.prereq-table{width:100%;border-collapse:collapse;font-size:.88em;}
.prereq-table td{padding:9px 12px;border-bottom:1px solid var(--border);}
.prereq-table tr:last-child td{border-bottom:none;}
.prereq-table .label{color:var(--text);font-weight:500;}
.prereq-table .val{color:var(--muted);}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.78em;font-weight:700;}
.badge-ok  {background:rgba(125,206,130,.2);color:var(--accent);}
.badge-fail{background:rgba(224,92,92,.2);  color:var(--danger);}
/* SQL results */
.sql-result{border:1px solid var(--border);border-radius:8px;margin-bottom:16px;overflow:hidden;}
.sql-result-header{background:rgba(255,255,255,.04);padding:10px 16px;font-weight:700;font-size:.9em;}
.sql-result-body{padding:0 16px;}
.sql-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:.88em;}
.sql-row:last-child{border-bottom:none;}
/* Done */
.done-icon{font-size:4em;text-align:center;margin-bottom:16px;}
.done-steps{list-style:none;padding:0;margin:20px 0;}
.done-steps li{padding:9px 0;border-bottom:1px solid var(--border);font-size:.9em;display:flex;align-items:center;gap:10px;}
.done-steps li:last-child{border-bottom:none;}
/* Locked */
.locked{text-align:center;padding:40px 0;}
.locked .lock-icon{font-size:3.5em;margin-bottom:16px;}
hr.divider{border:none;border-top:1px solid var(--border);margin:22px 0;}
code{background:rgba(255,255,255,.07);padding:2px 6px;border-radius:4px;font-size:.88em;}
</style>
</head>
<body>

<div class="installer-header">
  <h1>⚔ Kyana CMS Installer</h1>
  <p>Configure your server step by step.</p>
</div>

<?php if ($step < 7 && $step !== 99): ?>
<div class="stepper">
<?php
$step_labels = ['Welcome','Prerequisites','Database','Settings','Install','Done'];
foreach ($step_labels as $i => $label):
    $n   = $i + 1;
    $cls = ($n < $step) ? 'done' : (($n === $step) ? 'active' : '');
?>
  <div class="step-item <?= $cls ?>">
    <div class="step-dot"><?= ($n < $step) ? '✓' : $n ?></div>
    <div class="step-label"><?= $label ?></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">

<?php // ═══════════════ STEP 99 — Locked ═══════════════
if ($step === 99): ?>
  <div class="locked">
    <div class="lock-icon">🔒</div>
    <h2 style="color:var(--warn);margin-bottom:10px;">Already Installed</h2>
    <p style="color:var(--muted);line-height:1.7;">
      <code>install.lock</code> exists — Kyana CMS has already been configured.<br><br>
      To re-run this installer, delete <strong>install.lock</strong> from your web root first.
    </p>
    <div class="btn-row" style="justify-content:center;margin-top:28px;">
      <a href="index.html" class="btn btn-primary">Go to Website →</a>
      <a href="AdminCP/dashboard.php" class="btn btn-secondary">Admin Panel</a>
    </div>
  </div>

<?php // ═══════════════ STEP 1 — Welcome ═══════════════
elseif ($step === 1): ?>
  <h2>Welcome to the Kyana CMS Installer</h2>
  <p class="subtitle">
    This wizard will write your <strong>config.php</strong> and <strong>settings.json</strong>,
    then create the required SQL tables in your MU Online database(s).<br><br>
    Have your <strong>MS SQL Server</strong> credentials ready before continuing.
  </p>

  <div class="alert alert-warn">
    ⚠️ <strong>Security reminder:</strong> Delete <code>install.php</code> from your server immediately after installation.
  </div>

  <p style="margin-top:14px; font-size:.88em; color:var(--muted); line-height:1.8;">
    This installer will:<br>
    ✦ Check PHP extensions &amp; folder permissions<br>
    ✦ Test your MS SQL database connection(s)<br>
    ✦ Set your Admin CP password &amp; server display names<br>
    ✦ Auto-create <code>WebCredits</code>, <code>WebshopItems</code>, <code>PendingDonations</code> tables<br>
    ✦ Generate a fresh AES-256 encryption key
  </p>

  <div class="btn-row">
    <a href="?step=2" class="btn btn-primary">Get Started →</a>
  </div>

<?php // ═══════════════ STEP 2 — Prerequisites ═══════════════
elseif ($step === 2): ?>
  <h2>Prerequisites Check</h2>
  <p class="subtitle">Verifying your server environment before installation.</p>

  <?php if (!$prereqs_ok): ?>
  <div class="alert alert-danger">❌ One or more requirements are not met. Fix them before continuing.</div>
  <?php else: ?>
  <div class="alert alert-success">✅ All checks passed — ready to proceed!</div>
  <?php endif; ?>

  <table class="prereq-table">
    <tbody>
    <?php foreach ($prereqs as $c): ?>
    <tr>
      <td class="label"><?= htmlspecialchars($c['label']) ?></td>
      <td class="val"><?= htmlspecialchars($c['val']) ?></td>
      <td style="width:80px;text-align:right;"><span class="badge <?= $c['ok'] ? 'badge-ok' : 'badge-fail' ?>"><?= $c['ok'] ? 'PASS' : 'FAIL' ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <form method="POST" action="?step=2">
    <div class="btn-row">
      <a href="?step=1" class="btn btn-secondary">← Back</a>
      <button type="submit" name="next" class="btn btn-primary" <?= !$prereqs_ok ? 'disabled' : '' ?>>Continue →</button>
    </div>
  </form>

<?php // ═══════════════ STEP 3 — Database ═══════════════
elseif ($step === 3):
  $d    = $_SESSION['db'] ?? [];
  $mid  = $d['mid']  ?? [];
  $hard = $d['hard'] ?? [];
?>
  <h2>Database Configuration</h2>
  <p class="subtitle">
    Enter your MS SQL Server details. At least one server must connect successfully.
    Leave the <em>Host</em> blank to skip Server 2.
  </p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <form method="POST" action="?step=3">

    <div class="server-block">
      <div class="server-tag">Server 1 — Mid Rate</div>
      <div class="grid-2">
        <div class="field"><label>Host / IP</label><input type="text" name="mid_host" value="<?= htmlspecialchars($mid['host']??'') ?>" placeholder="127.0.0.1"></div>
        <div class="field"><label>Database Name</label><input type="text" name="mid_name" value="<?= htmlspecialchars($mid['name']??'MuOnline') ?>" placeholder="MuOnline"></div>
        <div class="field"><label>SQL Username</label><input type="text" name="mid_user" value="<?= htmlspecialchars($mid['user']??'sa') ?>" placeholder="sa"></div>
        <div class="field"><label>SQL Password</label><input type="password" name="mid_pass" value="<?= htmlspecialchars($mid['pass']??'') ?>" placeholder="••••••••"></div>
      </div>
    </div>

    <div class="server-block">
      <div class="server-tag">Server 2 — Hard Rate (optional)</div>
      <div class="grid-2">
        <div class="field"><label>Host / IP</label><input type="text" name="hard_host" value="<?= htmlspecialchars($hard['host']??'') ?>" placeholder="Leave blank to skip"></div>
        <div class="field"><label>Database Name</label><input type="text" name="hard_name" value="<?= htmlspecialchars($hard['name']??'MuOnlineEly') ?>" placeholder="MuOnlineEly"></div>
        <div class="field"><label>SQL Username</label><input type="text" name="hard_user" value="<?= htmlspecialchars($hard['user']??'sa') ?>" placeholder="sa"></div>
        <div class="field"><label>SQL Password</label><input type="password" name="hard_pass" value="<?= htmlspecialchars($hard['pass']??'') ?>" placeholder="••••••••"></div>
      </div>
    </div>

    <div class="btn-row">
      <a href="?step=2" class="btn btn-secondary">← Back</a>
      <button type="submit" name="save_db" class="btn btn-primary">Test &amp; Continue →</button>
    </div>
  </form>

<?php // ═══════════════ STEP 4 — Settings ═══════════════
elseif ($step === 4):
  $s = $_SESSION['site'] ?? [];
?>
  <h2>Site Settings &amp; Admin Password</h2>
  <p class="subtitle">Name your website, set a secure Admin CP password, and configure your game server info.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <form method="POST" action="?step=4">

    <div class="field">
      <label>Website Title</label>
      <input type="text" name="site_title" value="<?= htmlspecialchars($s['title']??'Kyana MU') ?>" placeholder="e.g. KiraMU">
    </div>

    <hr class="divider">
    <p style="font-size:.8em;color:var(--muted);margin-bottom:14px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">🔐 Admin CP Password</p>
    <div class="grid-2">
      <div class="field"><label>New Password (min 8 chars)</label><input type="password" name="admin_pass" placeholder="••••••••" autocomplete="new-password"></div>
      <div class="field"><label>Confirm Password</label><input type="password" name="admin_pass2" placeholder="••••••••" autocomplete="new-password"></div>
    </div>

    <hr class="divider">
    <p style="font-size:.8em;color:var(--muted);margin-bottom:14px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">🖥 Server Display Info</p>

    <div class="server-block">
      <div class="server-tag">Server 1 — Mid Rate</div>
      <div class="grid-2">
        <div class="field"><label>Display Name</label><input type="text" name="mid_srv_name" value="<?= htmlspecialchars($s['mid_name']??'Server 1') ?>" placeholder="Server 1"></div>
        <div class="field"><label>Game Port</label><input type="number" name="mid_port" value="<?= htmlspecialchars($s['mid_port']??55901) ?>"></div>
        <div class="field" style="grid-column:span 2"><label>Game Server IP / Address</label><input type="text" name="mid_address" value="<?= htmlspecialchars($s['mid_addr']??'127.0.0.1') ?>"></div>
      </div>
    </div>

    <div class="server-block">
      <div class="server-tag">Server 2 — Hard Rate</div>
      <div class="grid-2">
        <div class="field"><label>Display Name</label><input type="text" name="hard_srv_name" value="<?= htmlspecialchars($s['hard_name']??'Server 2') ?>" placeholder="Server 2"></div>
        <div class="field"><label>Game Port</label><input type="number" name="hard_port" value="<?= htmlspecialchars($s['hard_port']??55901) ?>"></div>
        <div class="field" style="grid-column:span 2"><label>Game Server IP / Address</label><input type="text" name="hard_address" value="<?= htmlspecialchars($s['hard_addr']??'127.0.0.1') ?>"></div>
      </div>
    </div>

    <div class="btn-row">
      <a href="?step=3" class="btn btn-secondary">← Back</a>
      <button type="submit" name="save_names" class="btn btn-primary">Save &amp; Continue →</button>
    </div>
  </form>

<?php // ═══════════════ STEP 5 — Confirm & Install ═══════════════
elseif ($step === 5):
  $db   = $_SESSION['db']   ?? [];
  $site = $_SESSION['site'] ?? [];
?>
  <h2>Review &amp; Install</h2>
  <p class="subtitle">Review the summary below, then click <strong>Install Now</strong>.</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <table class="prereq-table" style="margin-bottom:22px;">
    <tr><td class="label">Website Title</td>     <td class="val"><?= htmlspecialchars($site['title']??'') ?></td></tr>
    <tr><td class="label">Admin Password</td>    <td class="val">••••••••</td></tr>
    <tr><td class="label">Server 1 Name</td>     <td class="val"><?= htmlspecialchars($site['mid_name']??'') ?> — <?= htmlspecialchars($site['mid_addr']??'') ?>:<?= $site['mid_port']??'' ?></td></tr>
    <tr><td class="label">Server 2 Name</td>     <td class="val"><?= htmlspecialchars($site['hard_name']??'') ?> — <?= htmlspecialchars($site['hard_addr']??'') ?>:<?= $site['hard_port']??'' ?></td></tr>
    <tr><td class="label">DB Server 1</td>       <td class="val"><?= htmlspecialchars($db['mid']['host']??'(skipped)') ?> / <?= htmlspecialchars($db['mid']['name']??'') ?></td></tr>
    <tr><td class="label">DB Server 2</td>       <td class="val"><?= htmlspecialchars($db['hard']['host']??'(skipped)') ?> / <?= htmlspecialchars($db['hard']['name']??'') ?></td></tr>
    <tr><td class="label">Tables to Create</td>  <td class="val">WebCredits, WebshopItems, PendingDonations</td></tr>
  </table>

  <div class="alert alert-warn">
    🔑 A new random <strong>ENCRYPTION_KEY</strong> will be generated and saved to <code>config.php</code>.<br>
    If you re-install, previously encrypted passwords in <code>settings.json</code> will become invalid.
  </div>

  <form method="POST" action="?step=5">
    <div class="btn-row">
      <a href="?step=4" class="btn btn-secondary">← Back</a>
      <button type="submit" name="install" class="btn btn-install">⚡ Install Now</button>
    </div>
  </form>

<?php // ═══════════════ STEP 6 — Done ═══════════════
elseif ($step === 6):
  $db_results = $_SESSION['db_results'] ?? [];
?>
  <div class="done-icon">🎉</div>
  <h2 style="text-align:center;margin-bottom:6px;">Installation Complete!</h2>
  <p class="subtitle" style="text-align:center;">Kyana CMS has been configured successfully.</p>

  <?php foreach ($db_results as $srv => $res): ?>
  <div class="sql-result">
    <div class="sql-result-header">
      <?= $srv === 'mid' ? 'Server 1 (Mid Rate)' : 'Server 2 (Hard Rate)' ?>
      &nbsp;—&nbsp;
      <?= $res['ok'] ? '<span style="color:var(--accent)">✅ Connected</span>' : '<span style="color:var(--danger)">❌ ' . htmlspecialchars($res['msg']) . '</span>' ?>
    </div>
    <?php if ($res['ok'] && !empty($res['tables'])): ?>
    <div class="sql-result-body">
      <?php foreach ($res['tables'] as $tbl => $status): ?>
      <div class="sql-row"><span><?= htmlspecialchars($tbl) ?></span><span><?= $status ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <ul class="done-steps">
    <li>✅ <code>config.php</code> written with a fresh AES-256 encryption key</li>
    <li>✅ <code>Configuration/settings.json</code> created</li>
    <li>✅ <code>uploads/</code> and <code>uploads/proofs/</code> folders created</li>
    <li>✅ SQL tables created on all connected databases</li>
    <li>✅ <code>install.lock</code> written — installer is now locked</li>
  </ul>

  <div class="alert alert-danger" style="margin-top:12px;">
    🗑️ <strong>Action required:</strong> Delete <code>install.php</code> from your server right now to prevent unauthorized re-configuration!
  </div>

  <div class="btn-row" style="justify-content:center;margin-top:22px;">
    <a href="index.html" class="btn btn-primary">🌐 Go to Website</a>
    <a href="AdminCP/dashboard.php" class="btn btn-secondary">🔧 Admin Panel</a>
  </div>

<?php endif; ?>

</div><!-- /.card -->
</body>
</html>