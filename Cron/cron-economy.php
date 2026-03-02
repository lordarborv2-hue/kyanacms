<?php
// C:\xampp\htdocs\Cron\cron-economy.php

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php'; 

function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

$settingsPath = __DIR__ . '/../Configuration/settings.json';
$cachePath = __DIR__ . '/../Configuration/economy_cache.json';

if (!file_exists($settingsPath)) {
    die("Settings not found at: " . $settingsPath);
}

$settings = json_decode(file_get_contents($settingsPath), true);
$trackedItems = $settings['tracked_items'] ?? [];

// New array structure to hold data for multiple servers
$results = [
    'LastUpdated' => date('Y-m-d H:i:s'),
    'Servers' => [] 
];

// The two servers we want to check based on your settings.json
$serversToCheck = ['mid_rate', 'hard_rate'];

foreach ($serversToCheck as $serverKey) {
    if (!isset($settings['database'][$serverKey])) continue;
    
    $dbConfig = $settings['database'][$serverKey];
    $db_name = $dbConfig['name'] ?? 'MuOnline';
    $dbPass = decrypt_pass($dbConfig['pass_encrypted'], ENCRYPTION_KEY);

    $connectionOptions = [
        "Database" => $db_name,
        "Uid" => $dbConfig['user'],
        "PWD" => $dbPass,
        "CharacterSet" => "UTF-8",
        "LoginTimeout" => 5,
        "TrustServerCertificate" => 1,
        "Encrypt" => 0
    ];

    $conn = sqlsrv_connect($dbConfig['host'], $connectionOptions);
    
    $serverData = [
        'JewelBank' => [],
        'CustomItemBank' => []
    ];

    if ($conn) {
        // 1. Process Custom Jewel Bank
        $jewelSelects = [];
        foreach ($trackedItems as $item) {
            if (isset($item['col']) && !empty($item['col'])) {
                $colName = preg_replace('/[^a-zA-Z0-9_]/', '', $item['col']);
                $jewelSelects[] = "SUM([$colName]) as [$colName]";
            }
        }

        if (!empty($jewelSelects)) {
            $jewelQuery = "SELECT " . implode(', ', $jewelSelects) . " FROM CustomJewelBank";
            $stmt = sqlsrv_query($conn, $jewelQuery);
            if ($stmt) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                foreach ($row as $key => $val) {
                    // Convert nulls to 0
                    $serverData['JewelBank'][$key] = $val ?? 0;
                }
            }
        }

        // 2. Process Custom Item Bank
        foreach ($trackedItems as $item) {
            if (!isset($item['col']) || empty($item['col'])) {
                $name = $item['name'];
                $index = (int)$item['index'];
                
                $sql = "SELECT SUM(ItemCount) as Total FROM CustomItemBank WHERE ItemIndex = ?";
                $stmt = sqlsrv_query($conn, $sql, [$index]);
                if ($stmt) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    $serverData['CustomItemBank'][$name] = $row['Total'] ?? 0;
                }
            }
        }
        sqlsrv_close($conn);
    } else {
         $serverData['Error'] = "Database connection failed.";
    }
    
    // Save this server's data into the main results array
    $results['Servers'][$serverKey] = $serverData;
}

// Save to Cache
file_put_contents($cachePath, json_encode($results, JSON_PRETTY_PRINT));
echo "Economy cache updated successfully for both servers at " . $results['LastUpdated'];
?>