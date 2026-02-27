<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$username = $_SESSION['user_id'];
$server = $_SESSION['user_server'];

$amount_to_spend = (int)($_POST['amount'] ?? 0);
$coin_type = $_POST['type'] ?? '';

if ($amount_to_spend <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount.']); exit;
}

$allowed_types = [
    'wcoinc' => 'WCoinC',
    'wcoinp' => 'WCoinP',
    'goblin' => 'GoblinPoint'
];

if (!array_key_exists($coin_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coin type.']); exit;
}

$db_column = $allowed_types[$coin_type];
$rate = (int)($settings['conversion_rates'][$coin_type] ?? 1);
$coins_to_give = $amount_to_spend * $rate;

// DB Connection
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

$db_config = ($server === 'mid') ? $settings['database']['mid_rate'] : $settings['database']['hard_rate'];
$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'] ?? 'MuOnline',
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "TrustServerCertificate" => 1, "Encrypt" => 0
]);

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB Error']); exit; }

// 1. Check if user is offline
$statStmt = sqlsrv_query($conn, "SELECT ConnectStat FROM MEMB_STAT WHERE memb___id = ?", [$username]);
$statRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC);
if ($statRow && $statRow['ConnectStat'] == 1) {
    echo json_encode(['success' => false, 'message' => 'You must be OFFLINE in-game to convert credits.']); exit;
}

// 2. Check WebCredits Balance
$credStmt = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$username]);
$credRow = sqlsrv_fetch_array($credStmt, SQLSRV_FETCH_ASSOC);
if (!$credRow || $credRow['credits'] < $amount_to_spend) {
    echo json_encode(['success' => false, 'message' => 'Not enough WebCredits.']); exit;
}

// 3. Deduct WebCredits
sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits - ? WHERE memb___id = ?", [$amount_to_spend, $username]);

// 4. Add In-Game Coins (UPSERT logic in case they don't have a CashShopData row yet)
$checkShop = sqlsrv_query($conn, "SELECT 1 FROM CashShopData WHERE AccountID = ?", [$username]);
if (sqlsrv_has_rows($checkShop)) {
    $sql = "UPDATE CashShopData SET $db_column = ISNULL($db_column, 0) + ? WHERE AccountID = ?";
    sqlsrv_query($conn, $sql, [$coins_to_give, $username]);
} else {
    $wc = ($db_column === 'WCoinC') ? $coins_to_give : 0;
    $wp = ($db_column === 'WCoinP') ? $coins_to_give : 0;
    $gp = ($db_column === 'GoblinPoint') ? $coins_to_give : 0;
    $sql = "INSERT INTO CashShopData (AccountID, WCoinC, WCoinP, GoblinPoint) VALUES (?, ?, ?, ?)";
    sqlsrv_query($conn, $sql, [$username, $wc, $wp, $gp]);
}

echo json_encode(['success' => true, 'message' => "Successfully converted $amount_to_spend WebCredits into $coins_to_give $db_column."]);
sqlsrv_close($conn);
?>