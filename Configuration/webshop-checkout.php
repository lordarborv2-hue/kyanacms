<?php
header('Content-Type: application/json');
session_start();
error_reporting(0); 
require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$cartItems = $data['items'] ?? [];

if (empty($cartItems)) { echo json_encode(['success' => false, 'message' => 'Cart is empty.']); exit; }
if (!isset($_SESSION['user_loggedin'])) { echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit; }

$settings = json_decode(file_get_contents('settings.json'), true);
$server = $_SESSION['user_server'] ?? 'mid';
$server_key = ($server === 'mid') ? 'mid_rate' : 'hard_rate';
$username = $_SESSION['user_id'];
$db_config = $settings['database'][$server_key];

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'] ?? 'MuOnline',
    "Uid" => $db_config['user'],
    "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "TrustServerCertificate" => 1, "Encrypt" => 0
]);

if (!$conn) { echo json_encode(['success' => false, 'message' => 'Database connection failed.']); exit; }

// 1. Check Offline Status
$statStmt = sqlsrv_query($conn, "SELECT ConnectStat FROM MEMB_STAT WHERE memb___id = ?", [$username]);
$statRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC);
if ($statRow && $statRow['ConnectStat'] == 1) { echo json_encode(['success' => false, 'message' => 'You must be OFFLINE to buy items.']); exit; }

// 2. Fetch Warehouse & Item Sizes
$whQuery = sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(MAX), Items, 2) AS ItemsHex FROM Warehouse WHERE AccountID = ?", [$username]);
$whRow = sqlsrv_fetch_array($whQuery, SQLSRV_FETCH_ASSOC);
if (!$whRow || empty($whRow['ItemsHex'])) { echo json_encode(['success' => false, 'message' => 'Open Warehouse in-game first.']); exit; }

$currentWarehouseHex = $whRow['ItemsHex'];
$sizeQuery = sqlsrv_query($conn, "SELECT ItemType, ItemIndex, Width, Height, BasePrice, MaxExc, MaxSocket FROM WebshopItems WHERE IsActive = 1");
$itemDbData = [];
while ($row = sqlsrv_fetch_array($sizeQuery, SQLSRV_FETCH_ASSOC)) {
    $itemDbData[$row['ItemType'] . '-' . $row['ItemIndex']] = $row;
}

// 3. Process Cart and Calculate Total
$totalPrice = 0;
$priceCfg = $settings['webshop'][$server_key] ?? $settings['webshop'] ?? [];
$itemsToInject = [];

foreach ($cartItems as $ci) {
    $key = $ci['type'] . '-' . $ci['index'];
    if (!isset($itemDbData[$key])) continue;
    $db = $itemDbData[$key];

    // Recalculate Price Server-Side for Security
    $excCount = substr_count(decbin($ci['excValue']), '1');
    $itemPrice = $db['BasePrice'] 
        + ($ci['level'] * (int)($priceCfg['price_level'] ?? 10)) 
        + (($ci['luck'] + $ci['skill']) * (int)($priceCfg['price_luck_skill'] ?? 25))
        + ($excCount * (int)($priceCfg['price_exc'] ?? 50))
        + (($ci['opt380'] ?? 0) * (int)($priceCfg['price_380'] ?? 100))
        + (($ci['harmonyVal'] > 0 ? 1 : 0) * (int)($priceCfg['price_harmony'] ?? 100))
        + (($ci['sockets'] ?? 0) * (int)($priceCfg['price_socket'] ?? 50))
        + (($ci['ancient'] > 0 ? 1 : 0) * (int)($priceCfg['price_ancient'] ?? 100));
    
    $totalPrice += $itemPrice;

    // Generate Hex using your working logic
    $hex = sprintf("%02X", $ci['index'] % 256); 
    $byte1 = ($ci['level'] * 8) + ($ci['skill'] ? 128 : 0) + ($ci['luck'] ? 4 : 0);
    $hex .= sprintf("%02X", $byte1) . "FF00000000" . sprintf("%02X", $ci['excValue']); 
    
    $ancientByte = 0x00;
    if ($ci['ancient'] == 1) $ancientByte = 0x05; 
    elseif ($ci['ancient'] == 2) $ancientByte = 0x09; 
    elseif ($ci['ancient'] == 3) $ancientByte = 0x06; 
    elseif ($ci['ancient'] == 4) $ancientByte = 0x0A; 
    
    $hex .= sprintf("%02X", $ancientByte); 
    $byte9 = ($ci['type'] * 16) + ($ci['index'] > 255 ? 128 : 0) + (($ci['opt380'] ?? 0) ? 8 : 0);
    $hex .= sprintf("%02X", $byte9); 
    $byte10 = ($ci['harmonyVal'] > 0) ? (($ci['harmonyVal'] << 4) | 0x0D) : 0x00;
    $hex .= sprintf("%02X", $byte10);

    for ($i = 1; $i <= 5; $i++) { $hex .= ($i <= ($ci['sockets'] ?? 0)) ? "FE" : "FF"; }
    
    $itemsToInject[] = ['hex' => strtoupper($hex), 'w' => $db['Width'], 'h' => $db['Height']];
}

// 4. Check Credits
$credRow = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$username]), SQLSRV_FETCH_ASSOC);
if (!$credRow || $credRow['credits'] < $totalPrice) { echo json_encode(['success' => false, 'message' => 'Not enough credits.']); exit; }

// 5. Advanced Multi-Item Tetris Algorithm
sqlsrv_begin_transaction($conn);
foreach ($itemsToInject as $item) {
    // Re-map the grid every time an item is added
    $grid = array_fill(0, 120, false);
    for ($i = 0; $i < 120; $i++) {
        $itemHex = substr($currentWarehouseHex, $i * 32, 32);
        if (strlen($itemHex) < 32 || strtoupper($itemHex) === 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF') continue; 
        
        // This is your working slot-filling logic
        $hexType = hexdec(substr($itemHex, 18, 2));
        $type = floor($hexType / 16);
        $id = hexdec(substr($itemHex, 0, 2)) + (($hexType & 128) ? 256 : 0);
        
        $w = $itemDbData["$type-$id"]['Width'] ?? 1;
        $h = $itemDbData["$type-$id"]['Height'] ?? 1;
        $x = $i % 8; $y = floor($i / 8);
        for ($dy = 0; $dy < $h; $dy++) {
            for ($dx = 0; $dx < $w; $dx++) {
                if (($x + $dx) < 8 && ($y + $dy) < 15) { $grid[($x + $dx) + (($y + $dy) * 8)] = true; }
            }
        }
    }

    $foundSlot = -1;
    for ($y = 0; $y <= 15 - $item['h']; $y++) {
        for ($x = 0; $x <= 8 - $item['w']; $x++) {
            $canFit = true;
            for ($dy = 0; $dy < $item['h']; $dy++) {
                for ($dx = 0; $dx < $item['w']; $dx++) {
                    if ($grid[($x + $dx) + (($y + $dy) * 8)]) { $canFit = false; break 2; }
                }
            }
            if ($canFit) { $foundSlot = $x + ($y * 8); break 2; }
        }
    }

    if ($foundSlot === -1) { sqlsrv_rollback($conn); echo json_encode(['success' => false, 'message' => "No space for all items!"]); exit; }
    $currentWarehouseHex = substr_replace($currentWarehouseHex, $item['hex'], $foundSlot * 32, 32);
}

// 6. Final Save
sqlsrv_query($conn, "UPDATE Warehouse SET Items = CONVERT(VARBINARY(MAX), ?, 2) WHERE AccountID = ?", [$currentWarehouseHex, $username]);
sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits - ? WHERE memb___id = ?", [$totalPrice, $username]);
sqlsrv_commit($conn);

echo json_encode(['success' => true, 'message' => count($itemsToInject) . " items purchased successfully!"]);
sqlsrv_close($conn);
?>