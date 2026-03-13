<?php
header('Content-Type: application/json');
session_start();
error_reporting(0); 
require_once '../config.php';

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$server = $_SESSION['user_server'] ?? 'mid';
$server_key = ($server === 'mid') ? 'mid_rate' : 'hard_rate';

if (isset($settings['user_dashboard'][$server_key]['enable_webshop']) && $settings['user_dashboard'][$server_key]['enable_webshop'] == false) {
    echo json_encode(['success' => false, 'message' => 'The Webshop is currently disabled for this server.']); exit;
}

$username = $_SESSION['user_id'];
$db_config = $settings['database'][$server_key];

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'] ?? 'MuOnline',
    "Uid"      => $db_config['user'],
    "PWD"      => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "TrustServerCertificate" => 1, "Encrypt" => 0
]);
if (!$conn) { echo json_encode(['success' => false, 'message' => 'Database connection failed.']); exit; }

$priceCfg = $settings['webshop'][$server_key] ?? $settings['webshop'] ?? [];

// ============================================================
// ROUTE: Additional Jewel of Life (jolLevel posted)
// ============================================================
if (isset($_POST['jolLevel'])) {

    $jolLevel = (int)$_POST['jolLevel'];
    if ($jolLevel < 4 || $jolLevel > 28) {
        echo json_encode(['success' => false, 'message' => 'Invalid JoL level. Must be +4 to +28.']); exit;
    }

    $jolBase     = (int)($priceCfg['price_jol_base']      ?? 100);
    $jolPerLevel = (int)($priceCfg['price_jol_per_level'] ?? 50);
    $totalPrice  = $jolBase + (($jolLevel - 4) * $jolPerLevel);

    // Check offline
    $statRow = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT ConnectStat FROM MEMB_STAT WHERE memb___id = ?", [$username]), SQLSRV_FETCH_ASSOC);
    if ($statRow && $statRow['ConnectStat'] == 1) {
        echo json_encode(['success' => false, 'message' => 'You must be OFFLINE to buy items.']); exit;
    }

    // Check credits
    $credRow = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$username]), SQLSRV_FETCH_ASSOC);
    if (!$credRow || $credRow['credits'] < $totalPrice) {
        echo json_encode(['success' => false, 'message' => 'Not enough WebCredits.']); exit;
    }

    // Load warehouse
    $whRow = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(MAX), Items, 2) AS ItemsHex FROM Warehouse WHERE AccountID = ?", [$username]), SQLSRV_FETCH_ASSOC);
    if (!$whRow || empty($whRow['ItemsHex'])) {
        echo json_encode(['success' => false, 'message' => 'Open your Warehouse in-game first.']); exit;
    }

    $currentWarehouseHex = $whRow['ItemsHex'];
    $totalSlots = (int)(strlen($currentWarehouseHex) / 32);

    // Build grid (mark occupied slots)
    $grid = array_fill(0, $totalSlots, false);
    for ($i = 0; $i < $totalSlots; $i++) {
        $itemHex = substr($currentWarehouseHex, $i * 32, 32);
        if (strlen($itemHex) < 32 || strtoupper($itemHex) === 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF') continue;
        $grid[$i] = true;
    }

    // Find free 1x1 slot
    $foundSlot = -1;
    for ($s = 0; $s < $totalSlots; $s++) {
        if (!$grid[$s]) { $foundSlot = $s; break; }
    }
    if ($foundSlot === -1) {
        echo json_encode(['success' => false, 'message' => 'Not enough space in Warehouse!']); exit;
    }

    // Build JoL hex: ItemType=14 (0xE0 in byte9), ItemIndex=16 (0x10)
    $byte0  = sprintf("%02X", 16);                         // ItemIndex = 16
    $byte1  = sprintf("%02X", ($jolLevel * 8) & 0xFF);    // level encoded
    $byte9  = sprintf("%02X", 14 * 16);                   // ItemType*16 = 0xE0
    $hex    = strtoupper($byte0 . $byte1 . "FF00000000" . "00" . "00" . $byte9 . "00" . "FFFFFFFFFF");

    sqlsrv_begin_transaction($conn);
    $newWarehouseHex = substr_replace($currentWarehouseHex, $hex, $foundSlot * 32, 32);

    $upd = sqlsrv_query($conn, "UPDATE Warehouse SET Items = CONVERT(VARBINARY(MAX), ?, 2) WHERE AccountID = ?", [$newWarehouseHex, $username]);
    if (!$upd) { sqlsrv_rollback($conn); echo json_encode(['success' => false, 'message' => 'Failed to deliver item.']); exit; }

    $ded = sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits - ? WHERE memb___id = ?", [$totalPrice, $username]);
    if (!$ded) { sqlsrv_rollback($conn); echo json_encode(['success' => false, 'message' => 'Failed to deduct credits.']); exit; }

    sqlsrv_query($conn, "INSERT INTO Webshop_Logs (AccountID, ItemName, Price, ServerKey, ItemOptions) VALUES (?, ?, ?, ?, ?)",
        [$username, 'Additional Jewel of Life', $totalPrice, $server_key, "+{$jolLevel}"]);

    sqlsrv_commit($conn);
    echo json_encode(['success' => true, 'message' => "Additional Jewel of Life +{$jolLevel} delivered to your Warehouse!"]);
    sqlsrv_close($conn);
    exit;
}

// ============================================================
// ROUTE: Standard Webshop Item
// ============================================================

// 1. Collect Inputs
$itemType  = (int)$_POST['itemType'];
$itemIndex = (int)$_POST['itemIndex'];
$level     = (int)$_POST['level'];
$luck      = (int)$_POST['luck'];
$skill     = (int)$_POST['skill'];
$excOpt    = (int)$_POST['excOpt'];
$opt380    = (int)($_POST['opt380']   ?? 0);
$harmony   = (int)($_POST['harmony'] ?? 0);
$sockets   = (int)($_POST['sockets'] ?? 0);
$ancient   = (int)($_POST['ancient'] ?? 0);

// 2. Verify item exists
$stmt     = sqlsrv_query($conn, "SELECT * FROM WebshopItems WHERE ItemType = ? AND ItemIndex = ? AND IsActive = 1", [$itemType, $itemIndex]);
$itemData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$itemData) { echo json_encode(['success' => false, 'message' => 'Item is not available in the shop.']); exit; }

// 3. Verify limits
$excCount = substr_count(decbin($excOpt), '1');
if ($excCount > $itemData['MaxExc'])     { echo json_encode(['success' => false, 'message' => "Max {$itemData['MaxExc']} Exc options allowed."]); exit; }
if ($sockets > $itemData['MaxSocket'])   { echo json_encode(['success' => false, 'message' => "Max {$itemData['MaxSocket']} Sockets allowed."]); exit; }

// 4. Calculate price
$totalPrice = $itemData['BasePrice']
    + ($level    * (int)($priceCfg['price_level']      ?? 10))
    + (($luck + $skill) * (int)($priceCfg['price_luck_skill'] ?? 25))
    + ($excCount * (int)($priceCfg['price_exc']        ?? 50))
    + ($opt380   * (int)($priceCfg['price_380']        ?? 100))
    + (($harmony > 0 ? 1 : 0) * (int)($priceCfg['price_harmony'] ?? 100))
    + ($sockets  * (int)($priceCfg['price_socket']     ?? 50))
    + (($ancient > 0 ? 1 : 0) * (int)($priceCfg['price_ancient'] ?? 100));

// 5. Check offline
$statStmt = sqlsrv_query($conn, "SELECT ConnectStat FROM MEMB_STAT WHERE memb___id = ?", [$username]);
$statRow  = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC);
if ($statRow && $statRow['ConnectStat'] == 1) { echo json_encode(['success' => false, 'message' => 'You must be OFFLINE to buy items.']); exit; }

// 6. Check credits
$credStmt = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$username]);
$credRow  = sqlsrv_fetch_array($credStmt, SQLSRV_FETCH_ASSOC);
if (!$credRow || $credRow['credits'] < $totalPrice) { echo json_encode(['success' => false, 'message' => 'Not enough WebCredits.']); exit; }

// 7. Warehouse Tetris
$whQuery = sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(MAX), Items, 2) AS ItemsHex FROM Warehouse WHERE AccountID = ?", [$username]);
$whRow   = sqlsrv_fetch_array($whQuery, SQLSRV_FETCH_ASSOC);
if (!$whRow || empty($whRow['ItemsHex'])) { echo json_encode(['success' => false, 'message' => 'Open your Warehouse in-game first.']); exit; }

$currentWarehouseHex = $whRow['ItemsHex'];
$sizeQuery = sqlsrv_query($conn, "SELECT ItemType, ItemIndex, Width, Height FROM WebshopItems");
$itemSizes = [];
while ($row = sqlsrv_fetch_array($sizeQuery, SQLSRV_FETCH_ASSOC)) {
    $itemSizes[$row['ItemType'] . '-' . $row['ItemIndex']] = ['w' => $row['Width'], 'h' => $row['Height']];
}

$totalSlots = (int)(strlen($currentWarehouseHex) / 32);
$totalRows  = (int)($totalSlots / 8);

$grid = array_fill(0, $totalSlots, false);
for ($i = 0; $i < $totalSlots; $i++) {
    if ($grid[$i]) continue;
    $itemHex = substr($currentWarehouseHex, $i * 32, 32);
    if (strlen($itemHex) < 32 || strtoupper($itemHex) === 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF') continue;
    $hexId   = hexdec(substr($itemHex, 0, 2));
    $hexType = hexdec(substr($itemHex, 18, 2));
    $type    = floor($hexType / 16);
    $id      = $hexId + (($hexType & 128) ? 256 : 0);
    $w = $itemSizes["$type-$id"]['w'] ?? 1;
    $h = $itemSizes["$type-$id"]['h'] ?? 1;
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

// 8. Build hex
$hex    = sprintf("%02X", $itemIndex % 256);
$byte1  = ($level * 8) + ($skill ? 128 : 0) + ($luck ? 4 : 0);
$hex   .= sprintf("%02X", $byte1);
$hex   .= "FF";
$hex   .= "00000000";
$hex   .= sprintf("%02X", $excOpt);

$ancientByte = 0x00;
if ($ancient == 1) $ancientByte = 0x05;
if ($ancient == 2) $ancientByte = 0x09;
if ($ancient == 3) $ancientByte = 0x06;
if ($ancient == 4) $ancientByte = 0x0A;
$hex .= sprintf("%02X", $ancientByte);

$byte9 = ($itemType * 16) + ($itemIndex > 255 ? 128 : 0) + ($opt380 ? 8 : 0);
$hex  .= sprintf("%02X", $byte9);

$byte10 = ($harmony > 0) ? (($harmony << 4) | 0x0D) : 0x00;
$hex   .= sprintf("%02X", $byte10);

for ($i = 1; $i <= 5; $i++) { $hex .= ($i <= $sockets) ? "FE" : "FF"; }
$hex = strtoupper($hex);

// 9. Save
$newWarehouseHex = substr_replace($currentWarehouseHex, $hex, $foundSlot * 32, 32);

sqlsrv_begin_transaction($conn);

$updateStmt = sqlsrv_query($conn, "UPDATE Warehouse SET Items = CONVERT(VARBINARY(MAX), ?, 2) WHERE AccountID = ?", [$newWarehouseHex, $username]);
if (!$updateStmt) { sqlsrv_rollback($conn); echo json_encode(['success' => false, 'message' => 'Failed to deliver item to warehouse.']); exit; }

$deductStmt = sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits - ? WHERE memb___id = ?", [$totalPrice, $username]);
if (!$deductStmt) { sqlsrv_rollback($conn); echo json_encode(['success' => false, 'message' => 'Failed to deduct credits.']); exit; }

// 10. Log
$excBitNames = [1=>'HP Hunt', 2=>'Mana Hunt', 4=>'Dmg 2%', 8=>'Speed', 16=>'EDR', 32=>'Dmg Lvl/20'];
$optList = [];
if ($level > 0)   $optList[] = "+$level";
if ($luck)        $optList[] = "Luck";
if ($skill)       $optList[] = "Skill";
if ($ancient == 1 && !empty($itemData['AncName1']))     $optList[] = "Ancient: " . $itemData['AncName1'];
elseif ($ancient == 2 && !empty($itemData['AncName2'])) $optList[] = "Ancient: " . $itemData['AncName2'];
elseif ($ancient > 0)                                   $optList[] = "Ancient";
if ($opt380)      $optList[] = "380";
if ($harmony > 0) $optList[] = "Harmony";
if ($sockets > 0) $optList[] = "Sockets: $sockets";
foreach ($excBitNames as $bit => $name) { if ($excOpt & $bit) $optList[] = $name; }

$fullOptions = implode(', ', $optList);
sqlsrv_query($conn, "INSERT INTO Webshop_Logs (AccountID, ItemName, Price, ServerKey, ItemOptions) VALUES (?, ?, ?, ?, ?)",
    [$username, $itemData['ItemName'], $totalPrice, $server_key, $fullOptions]);

sqlsrv_commit($conn);
echo json_encode(['success' => true, 'message' => "Purchase successful! Item placed in Warehouse."]);
sqlsrv_close($conn);
?>