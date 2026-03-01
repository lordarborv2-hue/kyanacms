<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';

$settings = json_decode(file_get_contents('settings.json'), true);

$timeout_minutes = $settings['security']['user_session_timeout_minutes'] ?? 10;
if (isset($_SESSION['user_last_activity']) && (time() - $_SESSION['user_last_activity']) > ($timeout_minutes * 60)) {
    session_unset(); session_destroy();
    echo json_encode(['success' => false, 'error' => 'timeout']); exit;
}
$_SESSION['user_last_activity'] = time();

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'auth_required']); exit;
}

function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

function getClassName($code) {
    $classes = [
        0=>'Dark Wizard', 1=>'Soul Master', 2=>'Grand Master', 3=>'Grand Master',
        16=>'Dark Knight', 17=>'Blade Knight', 18=>'Blade Master', 19=>'Blade Master',
        32=>'Fairy Elf', 33=>'Muse Elf', 34=>'High Elf', 35=>'High Elf',
        48=>'Magic Gladiator', 49=>'Duel Master', 50=>'Duel Master',
        64=>'Dark Lord', 65=>'Lord Emperor', 66=>'Lord Emperor',
        80=>'Summoner', 81=>'Bloody Summoner', 82=>'Dimension Master', 83=>'Dimension Master',
        96=>'Rage Fighter', 97=>'Fist Master', 98=>'Fist Master'
    ];
    return $classes[$code] ?? 'Unknown (' . $code . ')';
}

$server = $_SESSION['user_server'];
$username = $_SESSION['user_id'];

if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = $db_config['name'] ?? 'MuOnline';
} else {
    $db_config = $settings['database']['hard_rate'];
    $db_name = $db_config['name'] ?? 'MuOnlineEly';
}

$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => 1,
    "Encrypt" => 0
];

$conn = sqlsrv_connect($db_config['host'], $connectionOptions);
if (!$conn) { echo json_encode(['success' => false, 'error' => 'Database error']); exit; }

// UPDATED QUERY TO INCLUDE CURRENCIES
$charSql = "SELECT T1.Name, T1.cLevel, T1.Class, T1.Strength, T1.Dexterity, T1.Vitality, T1.Energy, T1.Leadership, T1.PkCount, T1.MapNumber, T1.ResetCount,
            ISNULL(T2.MasterLevel, 0) as MasterLevel,
            ISNULL(T3.credits, 0) as WebCredits,
            ISNULL(T4.WCoinC, 0) as WCoinC,
            ISNULL(T4.WCoinP, 0) as WCoinP,
            ISNULL(T4.GoblinPoint, 0) as GoblinPoint
            FROM Character T1 
            LEFT JOIN MasterSkillTree T2 ON T1.Name = T2.Name 
            LEFT JOIN WebCredits T3 ON T1.AccountID = T3.memb___id
            LEFT JOIN CashShopData T4 ON T1.AccountID = T4.AccountID
            WHERE T1.AccountID = ?";

$charStmt = sqlsrv_query($conn, $charSql, [$username]);

$characters = [];
$webcredits = 0;
$wcoinc = 0;
$wcoinp = 0;
$goblin = 0;

if ($charStmt) {
    while ($row = sqlsrv_fetch_array($charStmt, SQLSRV_FETCH_ASSOC)) {
        $row['ClassName'] = getClassName($row['Class']);
        $characters[] = $row;
        
        // Grab the currencies from the first character row to populate the top stats bar
        if (empty($characters) || count($characters) === 1) {
            $webcredits = $row['WebCredits'];
            $wcoinc = $row['WCoinC'];
            $wcoinp = $row['WCoinP'];
            $goblin = $row['GoblinPoint'];
        }
    }
}

// Ensure the server key matches the JSON configuration keys we save in AdminCP
$server_key = ($server === 'mid') ? 'mid_rate' : 'hard_rate';

// Get the specific settings for the user's active server, fallback to default if empty
$paypal_config = $settings['paypal'][$server_key] ?? ['enabled' => false];
$qr_ph_config = $settings['qr_ph'][$server_key] ?? ['enabled' => false, 'ratio' => 100];

echo json_encode([
    'success' => true,
    'username' => $username,
    'server_label' => $_SESSION['user_server_label'],
    'characters' => $characters,
    'features' => $settings['user_dashboard'][$server_key] ?? [],
    'rates' => $settings['conversion_rates'] ?? ['wcoinc' => 1, 'wcoinp' => 1, 'goblin' => 1],
    'paypal' => $paypal_config,
    'qr_ph' => array_merge($qr_ph_config, [
    'image_url' => 'uploads/qr-ph/' . $server_key . '.png'
	]),
	'paymongo' => $settings['paymongo'][$server_key] ?? ['enabled' => false, 'rate' => 100, 'public_key' => ''],
    'webcredits' => $webcredits,
    'wcoinc' => $wcoinc,
    'wcoinp' => $wcoinp,
    'goblin' => $goblin
]);
sqlsrv_close($conn);
?>