<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);
set_time_limit(0); 
require_once '../config.php';

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false]); exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$server_key = ($_SESSION['user_server'] === 'mid') ? 'mid_rate' : 'hard_rate';
$db_config = $settings['database'][$server_key];

// Cache System
$cacheFile = 'sidebar_cache_' . $server_key . '.json';
$cacheTime = 300; // 5 Minutes
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo json_encode(json_decode(file_get_contents($cacheFile), true)); exit;
}

function decrypt_pass($g, $k) { list($d, $i) = explode('::', base64_decode($g), 2); return openssl_decrypt($d, ENCRYPTION_CIPHER, $k, 0, $i); }

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'], "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1
]);

if (!$conn) { echo json_encode(['success' => false]); exit; }

// SETUP TRACKED ITEMS FROM ADMIN SETTINGS
$tracked_config = $settings['tracked_items'] ?? [];
$itemCounts = [];
foreach ($tracked_config as $ti) { $itemCounts[$ti['name']] = 0; }

// 1. STANDARD WAREHOUSE & BUNDLES (HEX PARSER)
$whStmt = sqlsrv_query($conn, "SELECT CONVERT(VARCHAR(MAX), Items, 2) AS ItemsHex FROM Warehouse");
if ($whStmt) {
    while ($whRow = sqlsrv_fetch_array($whStmt, SQLSRV_FETCH_ASSOC)) {
        if (empty($whRow['ItemsHex'])) continue;
        foreach (str_split($whRow['ItemsHex'], 32) as $item) {
            if (strlen($item) < 32 || strtoupper($item) === 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF') continue;
            
            $id = hexdec(substr($item, 0, 2));
            $level = (floor(hexdec(substr($item, 2, 2)) / 8)) & 15; 
            $hexType = hexdec(substr($item, 18, 2));
            $category = floor($hexType / 16);
            if ($hexType & 128) $id += 256;

            foreach ($tracked_config as $ti) {
                // Direct Match
                if ($category == $ti['type'] && $id == $ti['index']) {
                    $itemCounts[$ti['name']]++;
                }
                // Dynamic Bundle Match (Type 12)
                if ($category == 12 && !empty($ti['bundle']) && $id == $ti['bundle']) {
                    $itemCounts[$ti['name']] += (($level + 1) * 10);
                }
            }
        }
    }
}

// 2. DYNAMIC CUSTOM JEWEL BANK
// Builds a query dynamically based on the exact columns you configured in AdminCP
$jewelSelects = [];
$validCols = [];
foreach ($tracked_config as $ti) {
    if (!empty($ti['col'])) {
        $safeCol = $ti['col']; // Already sanitized in manage-settings.php
        $jewelSelects[] = "SUM({$safeCol}) AS {$safeCol}";
        $validCols[$ti['name']] = $safeCol;
    }
}

if (!empty($jewelSelects)) {
    $hasJewelBank = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'CustomJewelBank'");
    if ($hasJewelBank && sqlsrv_has_rows($hasJewelBank)) {
        $query = "SELECT " . implode(', ', $jewelSelects) . " FROM CustomJewelBank";
        $cjbStmt = sqlsrv_query($conn, $query);
        if ($cjbStmt && $cjbRow = sqlsrv_fetch_array($cjbStmt, SQLSRV_FETCH_ASSOC)) {
            foreach ($validCols as $name => $colName) {
                $itemCounts[$name] += (int)$cjbRow[$colName];
            }
        }
    }
}

// 3. DYNAMIC CUSTOM ITEM BANK
$hasItemBank = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'CustomItemBank'");
if ($hasItemBank && sqlsrv_has_rows($hasItemBank)) {
    $cibStmt = sqlsrv_query($conn, "SELECT ItemIndex, ItemLevel, SUM(ItemCount) as TotalCount FROM CustomItemBank GROUP BY ItemIndex, ItemLevel");
    if ($cibStmt) {
        while ($cibRow = sqlsrv_fetch_array($cibStmt, SQLSRV_FETCH_ASSOC)) {
            $idx = (int)$cibRow['ItemIndex'];
            $lvl = (int)$cibRow['ItemLevel'];
            $qty = (int)$cibRow['TotalCount'];
            
            $cType = floor($idx / 512);
            $cId = $idx % 512;

            foreach ($tracked_config as $ti) {
                // Direct Match
                if ($cType == $ti['type'] && $cId == $ti['index']) {
                    $itemCounts[$ti['name']] += $qty;
                }
                // Dynamic Bundle Match
                if ($cType == 12 && !empty($ti['bundle']) && $cId == $ti['bundle']) {
                    $itemCounts[$ti['name']] += ($qty * (($lvl + 1) * 10));
                }
            }
        }
    }
}

// 4. SERVER CLASS COUNTS
$classStmt = sqlsrv_query($conn, "SELECT Class, COUNT(*) as Count FROM Character GROUP BY Class");
$classCounts = ['DK'=>0,'DW'=>0,'ELF'=>0,'MG'=>0,'DL'=>0,'SUM'=>0,'RF'=>0];
if ($classStmt) {
    while ($row = sqlsrv_fetch_array($classStmt, SQLSRV_FETCH_ASSOC)) {
        $c = (int)$row['Class']; $cnt = (int)$row['Count'];
        if ($c<=3) $classCounts['DW']+=$cnt; elseif($c<=19) $classCounts['DK']+=$cnt; elseif($c<=35) $classCounts['ELF']+=$cnt; elseif($c<=50) $classCounts['MG']+=$cnt; elseif($c<=66) $classCounts['DL']+=$cnt; elseif($c<=83) $classCounts['SUM']+=$cnt; elseif($c<=98) $classCounts['RF']+=$cnt;
    }
}

// 5. TOP 5 RANKINGS
$rankings = [];
$rankStmt = sqlsrv_query($conn, "SELECT TOP 5 Name, cLevel, ResetCount FROM Character ORDER BY ResetCount DESC, cLevel DESC");
while ($row = sqlsrv_fetch_array($rankStmt, SQLSRV_FETCH_ASSOC)) { $rankings[] = $row; }

$finalResponse = ['success' => true, 'tracked_items' => $itemCounts, 'classes' => $classCounts, 'rankings' => $rankings];
file_put_contents($cacheFile, json_encode($finalResponse));

echo json_encode($finalResponse);
sqlsrv_close($conn);
?>