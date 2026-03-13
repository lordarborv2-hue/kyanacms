<?php
header('Content-Type: application/json');
session_start();
error_reporting(0); 
require_once '../config.php'; // Inherits decrypt_data and ENCRYPTION_KEY

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$server = $_SESSION['user_server'] ?? 'mid';
$server_key = ($server === 'mid') ? 'mid_rate' : 'hard_rate';

// Check if Webshop is enabled for this specific server
if (isset($settings['user_dashboard'][$server_key]['enable_webshop']) && $settings['user_dashboard'][$server_key]['enable_webshop'] == false) {
    echo json_encode(['success' => false, 'message' => 'The Webshop is currently disabled for this server.']); exit;
}

$username = $_SESSION['user_id'];
$db_config = $settings['database'][$server_key];

// Use the global decrypt_data function instead of the local one
$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'] ?? 'MuOnline',
    "Uid" => $db_config['user'],
    "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), // Updated function
    "TrustServerCertificate" => 1, "Encrypt" => 0
]);

if (!$conn) { echo json_encode(['success' => false, 'message' => 'Database connection failed.']); exit; }

// 1. Collect Inputs
$itemType = (int)$_POST['itemType'];
$itemIndex = (int)$_POST['itemIndex'];
$level = (int)$_POST['level'];
$luck = (int)$_POST['luck'];
$skill = (int)$_POST['skill'];
$excOpt = (int)$_POST['excOpt'];
$opt380 = (int)($_POST['opt380'] ?? 0);
$harmony = (int)($_POST['harmony'] ?? 0);
$sockets = (int)($_POST['sockets'] ?? 0);
$ancient = (int)($_POST['ancient'] ?? 0);

// 2. Verify Item exists
$stmt = sqlsrv_query($conn, "SELECT * FROM WebshopItems WHERE ItemType = ? AND ItemIndex = ? AND IsActive = 1", [$itemType, $itemIndex]);
$itemData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$itemData) { echo json_encode(['success' => false, 'message' => 'Item is not available in the shop.']); exit; }

// 3. Verify Limits
$excCount = substr_count(decbin($excOpt), '1');
if ($excCount > $itemData['MaxExc']) { echo json_encode(['success' => false, 'message' => "Max {$itemData['MaxExc']} Exc options allowed."]); exit; }
if ($sockets > $itemData['MaxSocket']) { echo json_encode(['success' => false, 'message' => "Max {$itemData['MaxSocket']} Sockets allowed."]); exit; }

// 4. Calculate Final Price
$priceCfg = $settings['webshop'][$server_key] ?? $settings['webshop'] ?? [];
$totalPrice = $itemData['BasePrice'] 
    + ($level * (int)($priceCfg['price_level'] ?? 10)) 
    + (($luck + $skill) * (int)($priceCfg['price_luck_skill'] ?? 25))
    + ($excCount * (int)($priceCfg['price_exc'] ?? 50))
    + ($opt380 * (int)($priceCfg['price_380'] ?? 100))
    + (($harmony > 0 ? 1 : 0) * (int)($priceCfg['price_harmony'] ?? 100))
    + ($sockets * (int)($priceCfg['price_socket'] ?? 50))
    + (($ancient > 0 ? 1 : 0) * (int)($priceCfg['price_ancient'] ?? 100));

// 5. Check Offline Status
$statStmt = sqlsrv_query($conn, "SELECT ConnectStat FROM MEMB_STAT WHERE memb___id = ?", [$username]);
$statRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC);
if ($statRow && $statRow['ConnectStat'] == 1) { echo json_encode(['success' => false, 'message' => 'You must be OFFLINE to buy items.']); exit; }

// 6. Check Credits
$credStmt = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$username]);
$credRow = sqlsrv_fetch_array($credStmt, SQLSRV_FETCH_ASSOC);
if (!$credRow || $credRow['credits'] < $totalPrice) { echo json_encode(['success' => false, 'message' => 'Not enough WebCredits.']); exit; }

// 7. WAREHOUSE TETRIS ALGORITHM
$whQuery = sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(MAX), Items, 2) AS ItemsHex FROM Warehouse WHERE AccountID = ?", [$username]);
$whRow = sqlsrv_fetch_array($whQuery, SQLSRV_FETCH_ASSOC);

if (!$whRow || empty($whRow['ItemsHex'])) { echo json_encode(['success' => false, 'message' => 'Open your Warehouse in-game first.']); exit; }

$currentWarehouseHex = $whRow['ItemsHex'];
$sizeQuery = sqlsrv_query($conn, "SELECT ItemType, ItemIndex, Width, Height FROM WebshopItems");
$itemSizes = [];
while ($row = sqlsrv_fetch_array($sizeQuery, SQLSRV_FETCH_ASSOC)) {
    $itemSizes[$row['ItemType'] . '-' . $row['ItemIndex']] = ['w' => $row['Width'], 'h' => $row['Height']];
}

// Dynamically detect warehouse slot count from actual hex length (avoids out-of-bounds writes)
$totalSlots = (int)(strlen($currentWarehouseHex) / 32); // each slot = 16 bytes = 32 hex chars
$totalRows  = (int)($totalSlots / 8);                   // warehouse is always 8 columns wide

$grid = array_fill(0, $totalSlots, false);
for ($i = 0; $i < $totalSlots; $i++) {
    if ($grid[$i]) continue; 
    $itemHex = substr($currentWarehouseHex, $i * 32, 32);
    if (strlen($itemHex) < 32 || strtoupper($itemHex) === 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF') continue; 

    $hexId = hexdec(substr($itemHex, 0, 2));
    $hexType = hexdec(substr($itemHex, 18, 2));
    $type = floor($hexType / 16);
    $id = $hexId + (($hexType & 128) ? 256 : 0);
    $w = 1; $h = 1; 
    if (isset($itemSizes["$type-$id"])) { $w = $itemSizes["$type-$id"]['w']; $h = $itemSizes["$type-$id"]['h']; }

    $x = $i % 8; $y = floor($i / 8);
    for ($dy = 0; $dy < $h; $dy++) {
        for ($dx = 0; $dx < $w; $dx++) {
            if (($x + $dx) < 8 && ($y + $dy) < $totalRows) { $grid[($x + $dx) + (($y + $dy) * 8)] = true; }
        }
    }
}

$newItemW = $itemData['Width'];
$newItemH = $itemData['Height'];
$foundSlot = -1;

for ($y = 0; $y <= $totalRows - $newItemH; $y++) {
    for ($x = 0; $x <= 8 - $newItemW; $x++) {
        $canFit = true;
        for ($dy = 0; $dy < $newItemH; $dy++) {
            for ($dx = 0; $dx < $newItemW; $dx++) {
                if ($grid[($x + $dx) + (($y + $dy) * 8)]) { $canFit = false; break 2; }
            }
        }
        if ($canFit) { $foundSlot = $x + ($y * 8); break 2; }
    }
}

if ($foundSlot === -1) { echo json_encode(['success' => false, 'message' => "Not enough space in Warehouse!"]); exit; }

// 8. Generate MU Season 6 Hex String
$hex = sprintf("%02X", $itemIndex % 256); 
$byte1 = ($level * 8) + ($skill ? 128 : 0) + ($luck ? 4 : 0);
$hex .= sprintf("%02X", $byte1); 
$hex .= "FF"; // Durability
$hex .= "00000000"; // Serial
$hex .= sprintf("%02X", $excOpt); // Exc

// ANCIENT BYTE (Byte 8)
$ancientByte = 0x00;
if ($ancient == 1) $ancientByte = 0x05; 
if ($ancient == 2) $ancientByte = 0x09; 
if ($ancient == 3) $ancientByte = 0x06; 
if ($ancient == 4) $ancientByte = 0x0A; 
$hex .= sprintf("%02X", $ancientByte); 

// BYTE 9: Item Type, +256 ID Flag, AND 380 PvP Option
$byte9 = ($itemType * 16) + ($itemIndex > 255 ? 128 : 0) + ($opt380 ? 8 : 0);
$hex .= sprintf("%02X", $byte9); 

// BYTE 10: HARMONY ONLY
$byte10 = 0x00;
if ($harmony > 0) { 
    $byte10 = ($harmony << 4) | 0x0D; 
}

$hex .= sprintf("%02X", $byte10);

// Sockets (Bytes 11-15)
for ($i = 1; $i <= 5; $i++) { $hex .= ($i <= $sockets) ? "FE" : "FF"; }
$hex = strtoupper($hex); 

// 9. Inject Item
$newWarehouseHex = substr_replace($currentWarehouseHex, $hex, $foundSlot * 32, 32);

// 10. Save to Database (wrapped in transaction so warehouse + credits are atomic)
sqlsrv_begin_transaction($conn);

$updateSql = "UPDATE Warehouse SET Items = CONVERT(VARBINARY(MAX), ?, 2) WHERE AccountID = ?";
$updateStmt = sqlsrv_query($conn, $updateSql, [$newWarehouseHex, $username]);
if (!$updateStmt) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to deliver item to warehouse.']); exit;
}

// 11. Deduct Credits
$deductStmt = sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits - ? WHERE memb___id = ?", [$totalPrice, $username]);
if (!$deductStmt) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to deduct credits.']); exit;
}

// 12. Log the Purchase
$excBitNames = [
    1 => 'Exc Opt 1 (+Mana/8)',
    2 => 'Exc Opt 2 (+HP)',
    4 => 'Exc Opt 3 (+Speed)',
    8 => 'Exc Opt 4 (+Dmg/Ref)',
    16 => 'Exc Opt 5 (+Zen)',
    32 => 'Exc Opt 6 (Exc Dmg/Zen)',
];

$optList = [];
if ($level > 0) $optList[] = "+$level";
if ($luck)      $optList[] = "Luck";
if ($skill)     $optList[] = "Skill";

if ($ancient == 1 && !empty($itemData['AncName1']))      $optList[] = "Ancient: " . $itemData['AncName1'];
elseif ($ancient == 2 && !empty($itemData['AncName2']))  $optList[] = "Ancient: " . $itemData['AncName2'];
elseif ($ancient > 0)                                    $optList[] = "Ancient";

if ($opt380)        $optList[] = "380";
if ($harmony > 0)   $optList[] = "Harmony";
if ($sockets > 0)   $optList[] = "Sockets: $sockets";

foreach ($excBitNames as $bit => $name) {
    if ($excOpt & $bit) $optList[] = $name;
}

$fullOptions = implode(', ', $optList);
$logSql = "INSERT INTO Webshop_Logs (AccountID, ItemName, Price, ServerKey, ItemOptions) VALUES (?, ?, ?, ?, ?)";
sqlsrv_query($conn, $logSql, [$username, $itemData['ItemName'], $totalPrice, $server_key, $fullOptions]);

sqlsrv_commit($conn);

echo json_encode(['success' => true, 'message' => "Purchase successful! Item placed in Warehouse."]);
sqlsrv_close($conn);
?>