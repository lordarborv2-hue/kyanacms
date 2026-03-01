<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); 

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { die('Access Denied.'); }
require_once '../../config.php';

function encrypt_pass($password, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));
    $encrypted = openssl_encrypt($password, ENCRYPTION_CIPHER, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

// --- AJAX HANDLERS ---
if (isset($_GET['action']) && $_GET['action'] === 'lookup_credits') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $user = $_GET['user'] ?? '';
    
    $admin_srv = $_SESSION['admin_server'] ?? 'mid';
    $admin_server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$admin_server_key]; 
    
    if (empty($db_config['host'])) { echo json_encode(['success' => false]); exit; }
    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    if (!$conn) { echo json_encode(['success' => false]); exit; }
    
    $sql = "SELECT credits FROM WebCredits WHERE memb___id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$user]);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    
    header('Content-Type: application/json');
    if ($row) { echo json_encode(['success' => true, 'credits' => $row['credits']]); } else { echo json_encode(['success' => false]); }
    sqlsrv_close($conn); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'load_category_items') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $cat = (int)$_GET['cat'];
    
    $admin_srv = $_SESSION['admin_server'] ?? 'mid';
    $admin_server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$admin_server_key]; 
    
    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);

    if (!$conn) { echo "Database connection failed."; exit; }

    $stmt = sqlsrv_query($conn, "SELECT * FROM WebshopItems WHERE ItemType = ? ORDER BY ItemIndex", [$cat]);
    echo '<table style="width:100%; border-collapse: collapse; font-size: 0.85em; text-align: left;">';
    echo '<tr style="background:#ddd; border-bottom: 2px solid #aaa;"><th style="padding: 5px;">ID</th><th style="padding: 5px;">Name</th><th style="padding: 5px;">Act</th><th style="padding: 5px;">Lck</th><th style="padding: 5px;">Skl</th><th style="padding: 5px;">Exc</th><th style="padding: 5px;">Lvl</th><th style="padding: 5px;">380</th><th style="padding: 5px;">Hrm</th><th style="padding: 5px;">Sck</th><th style="padding: 5px;">Anc</th><th style="padding: 5px; width:60px;">Price</th><th style="padding: 5px; width:50px;">MaxExc</th><th style="padding: 5px; width:50px;">MaxSck</th></tr>';
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $t = $row['ItemType']; $i = $row['ItemIndex']; $name = str_replace('"', '', $row['ItemName']);
            echo "<tr style='border-bottom: 1px solid #ddd;'><td style='padding: 5px;'>{$i}</td><td style='padding: 5px;'>{$name}</td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'IsActive', this)\" ".($row['IsActive'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowLuck', this)\" ".($row['AllowLuck'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowSkill', this)\" ".($row['AllowSkill'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowExc', this)\" ".($row['AllowExc'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowLevel', this)\" ".($row['AllowLevel'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'Allow380', this)\" ".($row['Allow380'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowHarmony', this)\" ".($row['AllowHarmony'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowSocket', this)\" ".($row['AllowSocket'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowAncient', this)\" ".($row['AllowAncient'] ? 'checked':'')."></td><td style='padding: 5px;'><input type='number' min='0' style='width:50px; padding:3px;' value='{$row['BasePrice']}' onchange=\"updateItemData($t, $i, 'BasePrice', this)\"></td><td style='padding: 5px;'><input type='number' min='0' max='6' style='width:40px; padding:3px;' value='{$row['MaxExc']}' onchange=\"updateItemData($t, $i, 'MaxExc', this)\"></td><td style='padding: 5px;'><input type='number' min='0' max='5' style='width:40px; padding:3px;' value='{$row['MaxSocket']}' onchange=\"updateItemData($t, $i, 'MaxSocket', this)\"></td></tr>";
        }
    }
    echo '</table>'; exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'update_item_data') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    
    $admin_srv = $_SESSION['admin_server'] ?? 'mid';
    $admin_server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$admin_server_key]; 

    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    $allowed_cols = ['IsActive', 'AllowExc', 'AllowLevel', 'Allow380', 'AllowHarmony', 'AllowSocket', 'AllowAncient', 'BasePrice', 'MaxExc', 'MaxSocket', 'AllowLuck', 'AllowSkill'];
    $col = $_GET['col'];
    if (in_array($col, $allowed_cols)) {
        sqlsrv_query($conn, "UPDATE WebshopItems SET $col = ? WHERE ItemType = ? AND ItemIndex = ?", [(int)$_GET['val'], (int)$_GET['type'], (int)$_GET['index']]);
    }
    exit;
}

// --- POST FORM HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_file = '../../Configuration/settings.json';
    
    if (!is_writable($settings_file) && $_POST['action'] !== 'upload_item_txt') {
        die("<h2 style='color:red;'>CRITICAL ERROR: Permission Denied!</h2>");
    }

    $settings = json_decode(file_get_contents($settings_file), true);
    $action = $_POST['action'] ?? '';
    $page = 'settings';
    $status = 'Settings saved successfully!';

    switch ($action) {
        case 'manage_user_credits':
            $target_user = $_POST['target_user'];
            $amount = (int)$_POST['credit_amount'];
            $op = $_POST['operation']; 
            
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config = $settings['database'][$server_key];
            
            $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
            if (!$conn) { die("Database Connection Failed for selected server."); }

            sqlsrv_query($conn, "IF NOT EXISTS (SELECT 1 FROM WebCredits WHERE memb___id = ?) INSERT INTO WebCredits (memb___id, credits) VALUES (?, 0)", [$target_user, $target_user]);

            if ($op == 'add') { $sql = "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?"; } 
            elseif ($op == 'minus') { $sql = "UPDATE WebCredits SET credits = CASE WHEN credits >= ? THEN credits - ? ELSE 0 END WHERE memb___id = ?"; $amount = [$amount, $amount]; } 
            else { $sql = "UPDATE WebCredits SET credits = ? WHERE memb___id = ?"; }
            
            sqlsrv_query($conn, $sql, is_array($amount) ? array_merge($amount, [$target_user]) : [$amount, $target_user]);
            sqlsrv_close($conn);
            $page = 'user_settings';
            $status = "Credits updated for " . htmlspecialchars($target_user);
            break;

        case 'save_webshop_prices':
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['webshop'])) $settings['webshop'] = [];
            $settings['webshop'][$server_key] = [
                'price_level' => (int)$_POST['price_level'], 'price_exc' => (int)$_POST['price_exc'],
                'price_luck_skill' => (int)$_POST['price_luck_skill'], 'price_380' => (int)$_POST['price_380'],
                'price_harmony' => (int)$_POST['price_harmony'], 'price_socket' => (int)$_POST['price_socket'],
                'price_ancient' => (int)$_POST['price_ancient']
            ];
            $page = 'user_settings';
            break;
            
        case 'save_paymongo_settings':
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['paymongo'])) $settings['paymongo'] = [];
            $settings['paymongo'][$server_key] = [
                'enabled' => isset($_POST['paymongo_enabled']), 
                'public_key' => trim($_POST['paymongo_public']),
                'secret_key' => trim($_POST['paymongo_secret']), 
                'rate' => (int)$_POST['paymongo_rate']
            ];
            $page = 'donations';
            break;

        case 'save_paypal_settings':
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['paypal'])) $settings['paypal'] = [];
            $settings['paypal'][$server_key] = [
                'enabled' => isset($_POST['paypal_enabled']), 'mode' => $_POST['paypal_mode'],
                'client_id' => trim($_POST['paypal_client_id']), 'secret' => trim($_POST['paypal_secret']),
                'currency' => strtoupper(trim($_POST['paypal_currency'])), 'rate' => (int)$_POST['paypal_rate']
            ];
            $page = 'donations';
            break;

        case 'save_qr_settings':
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['qr_ph'])) $settings['qr_ph'] = [];
            $settings['qr_ph'][$server_key] = [
                'enabled' => isset($_POST['qr_enabled']), 'ratio' => (int)($_POST['qr_ratio'] ?? 100)
            ];

            if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] == 0) {
                $targetPath = '../../qr-ph-' . $server_key . '.png'; 
                move_uploaded_file($_FILES['qr_image']['tmp_name'], $targetPath);
            }
            $page = 'donations';
            break;  
            
        case 'save_economy':
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $names = $_POST['item_names'] ?? []; $cols = $_POST['item_cols'] ?? [];
            $tracked = [];
            for ($i = 0; $i < count($names); $i++) {
                if (!empty(trim($names[$i])) && !empty(trim($cols[$i]))) { $tracked[trim($names[$i])] = trim($cols[$i]); }
            }
            if (!isset($settings['economy_tracking'])) $settings['economy_tracking'] = [];
            $settings['economy_tracking'][$server_key] = $tracked;
            @unlink('../../Configuration/sidebar_cache_' . $server_key . '.json');
            $page = 'economy';
            break;
		
		case 'approve_order':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config = $settings['database'][$server_key];

            // FIXED: Added Encrypt => 0
            $conn = sqlsrv_connect($db_config['host'], [
                "Database" => $db_config['name'], 
                "Uid" => $db_config['user'],
                "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
                "TrustServerCertificate" => 1, "Encrypt" => 0
            ]);

            if (!$conn) {
                $status = 'Error: Database connection failed.';
                $page = 'orders';
                break;
            }

            $orderQuery = "SELECT * FROM PendingDonations WHERE ID = ? AND Status = 0";
            $orderStmt = sqlsrv_query($conn, $orderQuery, [$order_id]);
            $order = sqlsrv_fetch_array($orderStmt, SQLSRV_FETCH_ASSOC);

            if ($order) {
                $acc = $order['AccountID'];
                $credsToAdd = (int)$order['CreditsToReceive'];

                // FIXED: Safely check if the user already has a WebCredits row without using has_rows()
                $check = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$acc]);
                $row = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
                
                if ($row) {
                    sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?", [$credsToAdd, $acc]);
                } else {
                    sqlsrv_query($conn, "INSERT INTO WebCredits (memb___id, credits) VALUES (?, ?)", [$acc, $credsToAdd]);
                }

                // Mark the order as Approved
                sqlsrv_query($conn, "UPDATE PendingDonations SET Status = 1 WHERE ID = ?", [$order_id]);

                sqlsrv_close($conn);
                $status = "Success: Added $credsToAdd Credits to $acc.";
            } else {
                sqlsrv_close($conn);
                $status = 'Error: Order not found or already processed.';
            }
            $page = 'orders';
            break;

        case 'reject_order':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config = $settings['database'][$server_key];
            
            // FIXED: Added Encrypt => 0
            $conn = sqlsrv_connect($db_config['host'], [
                "Database" => $db_config['name'], 
                "Uid" => $db_config['user'],
                "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
                "TrustServerCertificate" => 1, "Encrypt" => 0
            ]);

            if ($conn) {
                sqlsrv_query($conn, "UPDATE PendingDonations SET Status = 2 WHERE ID = ?", [$order_id]);
                sqlsrv_close($conn);
                $status = 'Order Rejected successfully.';
            } else {
                $status = 'Error: Database connection failed.';
            }
            $page = 'orders';
            break;
			

        case 'upload_item_txt':
            if (isset($_FILES['item_txt']) && $_FILES['item_txt']['error'] == 0) {
                $lines = file($_FILES['item_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $itemsToInsert = [];
                $section = -1;

                foreach ($lines as $index => $line) {
                    if ($index === 0) $line = preg_replace('/^[\xef\xbb\xbf]+/', '', $line); 
                    $line = trim($line);
                    if ($line === '' || strpos($line, '//') === 0) continue; 
                    if (strtolower($line) === 'end') { $section = -1; continue; }
                    
                    $secCheck = trim(explode('//', $line)[0]);
                    if (is_numeric($secCheck) && strlen($secCheck) <= 2) { $section = (int)$secCheck; continue; }

                    if ($section >= 0 && $section <= 15) {
                        if (preg_match('/^(\d+)\s+(?:-?\d+\s+){2}(\d+)\s+(\d+).*?"([^"]+)"/', $line, $matches)) {
                            $id = (int)$matches[1]; $w = (int)$matches[2]; $h = (int)$matches[3]; $name = $matches[4];
                            $itemsToInsert["$section-$id"] = [
                                'type' => $section, 'id' => $id, 'name' => $name, 'w' => $w, 'h' => $h,
                                'sck' => 0, 'maxSck' => 0, 'opt380' => 0, 'anc' => 0, 'ancName1' => null, 'ancName2' => null
                            ];
                        }
                    }
                }

                $target = $_POST['upload_target'] ?? 'both';
                $servers = ($target === 'both') ? ['mid_rate', 'hard_rate'] : [$target];
                $totalParsed = count($itemsToInsert);
                $serversUpdated = 0;

                foreach ($servers as $srv) {
                    $db_config = $settings['database'][$srv];
                    if (empty($db_config['host'])) continue;

                    $conn = sqlsrv_connect($db_config['host'], [
                        "Database" => $db_config['name'], "Uid" => $db_config['user'],
                        "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
                        "TrustServerCertificate" => 1, "Encrypt" => 0
                    ]);

                    if ($conn) {
                        $createTableSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='WebshopItems' and xtype='U')
                        CREATE TABLE WebshopItems (
                            ID int IDENTITY(1,1) PRIMARY KEY, ItemType int, ItemIndex int, ItemName varchar(100), Width int, Height int,
                            BasePrice int DEFAULT 100, IsActive bit DEFAULT 1, AllowExc bit DEFAULT 1, AllowLevel bit DEFAULT 1,
                            Allow380 bit DEFAULT 0, AllowHarmony bit DEFAULT 1, AllowSocket bit DEFAULT 0, MaxExc int DEFAULT 6, MaxSocket int DEFAULT 0,
                            AllowLuck bit DEFAULT 1, AllowSkill bit DEFAULT 1, AllowAncient bit DEFAULT 0, AncName1 varchar(50), AncName2 varchar(50)
                        )";
                        
                        $create = sqlsrv_query($conn, $createTableSql);
                        if ($create === false) { die("<h2 style='color:red;'>SQL Table Error on $srv</h2><pre>".print_r(sqlsrv_errors(), true)."</pre>"); }

                        sqlsrv_query($conn, "TRUNCATE TABLE WebshopItems");
                        sqlsrv_begin_transaction($conn);
                        
                        $hasErrors = false;
                        foreach ($itemsToInsert as $i) {
                            $sql = "INSERT INTO WebshopItems (ItemType, ItemIndex, ItemName, Width, Height, BasePrice, AllowExc, AllowLevel, Allow380, AllowHarmony, AllowSocket, MaxExc, MaxSocket, AllowLuck, AllowSkill, AllowAncient, AncName1, AncName2) VALUES (?, ?, ?, ?, ?, 100, 1, 1, ?, 1, ?, 6, ?, 1, 1, ?, ?, ?)";
                            $stmt = sqlsrv_query($conn, $sql, [
                                $i['type'], $i['id'], $i['name'], $i['w'], $i['h'], 
                                $i['opt380'], $i['sck'], $i['maxSck'], $i['anc'], $i['ancName1'], $i['ancName2']
                            ]);
                            if ($stmt === false) {
                                $hasErrors = true;
                                echo "<h2 style='color:red;'>SQL Insert Error on Item: {$i['name']} ($srv)</h2><pre>";
                                die(print_r(sqlsrv_errors(), true));
                            }
                        }
                        
                        if ($hasErrors) { sqlsrv_rollback($conn); } else { sqlsrv_commit($conn); $serversUpdated++; }
                        sqlsrv_close($conn);
                    } else {
                        die("<h2 style='color:red;'>Database Connection Error on $srv</h2><pre>".print_r(sqlsrv_errors(), true)."</pre>");
                    }
                }
                
                $status = ($serversUpdated > 0) ? "Successfully synced $totalParsed items!" : "Error: Could not connect to databases.";

            } else { $status = "Error uploading Item.txt."; }
            $page = 'user_settings';
            break;

        case 'save_site_settings':
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] == 0) {
                $target_dir = '../../uploads/';
                $file = $_FILES['favicon_file'];
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['size'] <= 1048576 && in_array($extension, ['ico', 'png', 'jpg', 'jpeg'])) {
                    $new_filename = 'favicon-' . uniqid() . '.' . $extension;
                    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
                        $old_favicon = $settings['favicon_url'] ?? '';
                        if ($old_favicon && basename($old_favicon) !== 'default-favicon.ico' && file_exists('../../' . $old_favicon)) unlink('../../' . $old_favicon);
                        $settings['favicon_url'] = 'uploads/' . $new_filename;
                    }
                }
            }
            $settings['website_title'] = $_POST['website_title'];
            $settings['show_online_count'] = (bool)($_POST['show_online'] ?? true);
            $settings['server_names']['mid_rate'] = $_POST['mid_name']; 
            $settings['mid_rate_server']['address'] = $_POST['mid_address'];
            $settings['mid_rate_server']['port'] = (int)$_POST['mid_port'];
            $settings['mid_rate_server']['visible'] = (bool)($_POST['mid_visible'] ?? true); 
            $settings['server_names']['hard_rate'] = $_POST['hard_name'];
            $settings['hard_rate_server']['address'] = $_POST['hard_address'];
            $settings['hard_rate_server']['port'] = (int)$_POST['hard_port'];
            $settings['hard_rate_server']['visible'] = (bool)($_POST['hard_visible'] ?? true);
            $page = 'settings';
            break;

        case 'save_user_settings': 
            $admin_srv = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            
            if (!isset($settings['user_dashboard'])) $settings['user_dashboard'] = [];
            $settings['user_dashboard'][$server_key] = [
                'enable_webshop' => isset($_POST['enable_webshop']), 'enable_reset' => isset($_POST['enable_reset']),
                'enable_reset_stats' => isset($_POST['enable_reset_stats']), 'enable_clear_pk' => isset($_POST['enable_clear_pk']),
                'enable_reset_master' => isset($_POST['enable_reset_master']), 'enable_unstuck' => isset($_POST['enable_unstuck'])
            ];
            
            if (isset($_POST['rate_wcoinc'])) {
                $settings['conversion_rates']['wcoinc'] = (int)$_POST['rate_wcoinc'];
                $settings['conversion_rates']['wcoinp'] = (int)$_POST['rate_wcoinp'];
                $settings['conversion_rates']['goblin'] = (int)$_POST['rate_goblin'];
            }
            $page = 'user_settings';
            break;  
    }
    
    if (!in_array($action, ['manage_user_credits', 'upload_item_txt', 'approve_order', 'reject_order'])) {
        $saveResult = file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
        if ($saveResult === false) { die("<h2 style='color:red;'>Save Failed</h2><p>Could not write to settings.json.</p>"); }
    }
    
    header('Location: ../dashboard.php?page=' . $page . '&status=' . urlencode($status));
    exit;
}
?>