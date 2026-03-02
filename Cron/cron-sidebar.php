<?php
// C:\xampp\htdocs\Cron\cron-sidebar.php
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0); // Allow script to run as long as it needs to parse Hex for both DBs

require_once __DIR__ . '/../config.php';

function decrypt_pass($g, $k) { 
    if (empty($g)) return '';
    list($d, $i) = explode('::', base64_decode($g), 2); 
    return openssl_decrypt($d, ENCRYPTION_CIPHER, $k, 0, $i); 
}

$settingsPath = __DIR__ . '/../Configuration/settings.json';
if (!file_exists($settingsPath)) {
    die("Settings file not found.");
}

$settings = json_decode(file_get_contents($settingsPath), true);
$tracked_config = $settings['tracked_items'] ?? [];

// Loop through both Mid Rate and Hard Rate servers
$serversToCheck = ['mid_rate', 'hard_rate'];

foreach ($serversToCheck as $server_key) {
    if (!isset($settings['database'][$server_key])) continue;
    
    $db_config = $settings['database'][$server_key];
    $cacheFile = __DIR__ . '/../Configuration/sidebar_cache_' . $server_key . '.json';
    
    $conn = sqlsrv_connect($db_config['host'], [
        "Database" => $db_config['name'], 
        "Uid" => $db_config['user'],
        "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), 
        "TrustServerCertificate" => 1,
        "CharacterSet" => "UTF-8"
    ]);

    if (!$conn) { 
        continue; // Skip silently if one of the databases is offline
    }

    // Initialize item counts at 0 for this server
    $itemCounts = [];
    foreach ($tracked_config as $ti) { $itemCounts[$ti['name']] = 0; }

    // 1. STANDARD WAREHOUSE & INVENTORY BUNDLES (HEX PARSER)
    // Uses UNION ALL to scan items inside Vaults AND Player Backpacks
    $hexQuery = "
        SELECT CONVERT(VARCHAR(MAX), Items, 2) AS ItemsHex FROM Warehouse
        UNION ALL
        SELECT CONVERT(VARCHAR(MAX), Inventory, 2) AS ItemsHex FROM Character
    ";
    
    $whStmt = sqlsrv_query($conn, $hexQuery);
    if ($whStmt) {
        while ($whRow = sqlsrv_fetch_array($whStmt, SQLSRV_FETCH_ASSOC)) {
            if (empty($whRow['ItemsHex'])) continue;
            
            // Split the long hex string into 32-character chunks (individual items)
            $hexArray = str_split($whRow['ItemsHex'], 32);
            foreach ($hexArray as $item) {
                // Ignore empty slots (FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF) or malformed chunks
                if (strlen($item) < 32 || strtoupper($item) === 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF') continue;
                
                $id = hexdec(substr($item, 0, 2));
                $level = (floor(hexdec(substr($item, 2, 2)) / 8)) & 15; 
                $hexType = hexdec(substr($item, 18, 2));
                $category = floor($hexType / 16);
                if ($hexType & 128) $id += 256;

                foreach ($tracked_config as $ti) {
                    // Direct Match (Single Items)
                    if ($category == $ti['type'] && $id == $ti['index']) {
                        $itemCounts[$ti['name']]++;
                    }
                    // Bundle Match (Type 12 bundled jewels usually map bundle count via level)
                    if ($category == 12 && !empty($ti['bundle']) && $id == $ti['bundle']) {
                        $itemCounts[$ti['name']] += (($level + 1) * 10);
                    }
                }
            }
        }
    }

    // 2. DYNAMIC CUSTOM JEWEL BANK
    $jewelSelects = [];
    $validCols = [];
    foreach ($tracked_config as $ti) {
        if (!empty($ti['col'])) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $ti['col']);
            $jewelSelects[] = "SUM([{$safeCol}]) AS [{$safeCol}]";
            $validCols[$ti['name']] = $safeCol;
        }
    }

    if (!empty($jewelSelects)) {
        // Check if table exists before querying to prevent crashes
        $hasJewelBank = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'CustomJewelBank'");
        if ($hasJewelBank && sqlsrv_has_rows($hasJewelBank)) {
            $query = "SELECT " . implode(', ', $jewelSelects) . " FROM CustomJewelBank";
            $cjbStmt = sqlsrv_query($conn, $query);
            if ($cjbStmt && $cjbRow = sqlsrv_fetch_array($cjbStmt, SQLSRV_FETCH_ASSOC)) {
                foreach ($validCols as $name => $colName) {
                    $itemCounts[$name] += (int)($cjbRow[$colName] ?? 0);
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
                    // Direct Match for custom items
                    if ($cType == $ti['type'] && $cId == $ti['index']) {
                        $itemCounts[$ti['name']] += $qty;
                    }
                    // Bundle Match for custom items
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
            if ($c<=3) $classCounts['DW']+=$cnt; 
            elseif($c<=19) $classCounts['DK']+=$cnt; 
            elseif($c<=35) $classCounts['ELF']+=$cnt; 
            elseif($c<=50) $classCounts['MG']+=$cnt; 
            elseif($c<=66) $classCounts['DL']+=$cnt; 
            elseif($c<=83) $classCounts['SUM']+=$cnt; 
            elseif($c<=98) $classCounts['RF']+=$cnt;
        }
    }

    // 5. TOP 5 RANKINGS
    $rankings = [];
    $rankStmt = sqlsrv_query($conn, "SELECT TOP 5 Name, cLevel, ResetCount FROM Character ORDER BY ResetCount DESC, cLevel DESC");
    if ($rankStmt) {
        while ($row = sqlsrv_fetch_array($rankStmt, SQLSRV_FETCH_ASSOC)) { 
            $rankings[] = $row; 
        }
    }

    // Save all compiled data into the JSON cache for this specific server
    $finalResponse = [
        'success' => true, 
        'last_updated' => date('Y-m-d H:i:s'),
        'tracked_items' => $itemCounts, 
        'classes' => $classCounts, 
        'rankings' => $rankings
    ];
    
    file_put_contents($cacheFile, json_encode($finalResponse));
    sqlsrv_close($conn);
}

echo "Sidebar cache successfully generated for all servers (including Inventories).";
?>