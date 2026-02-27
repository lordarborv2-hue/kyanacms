<?php
session_start();
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
    
    $srv_choice = (isset($_GET['server']) && $_GET['server'] === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$srv_choice];
    
    if (empty($db_config['host'])) { echo json_encode(['success' => false]); exit; }

    $conn = sqlsrv_connect($db_config['host'], [
        "Database" => $db_config['name'], "Uid" => $db_config['user'],
        "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
        "TrustServerCertificate" => 1, "Encrypt" => 0
    ]);

    if (!$conn) { echo json_encode(['success' => false]); exit; }
    
    $sql = "SELECT credits FROM WebCredits WHERE memb___id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$user]);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    
    header('Content-Type: application/json');
    if ($row) { echo json_encode(['success' => true, 'credits' => $row['credits']]); } 
    else { echo json_encode(['success' => false]); }
    
    sqlsrv_close($conn); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'load_category_items') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $cat = (int)$_GET['cat'];
    $srv = ($_GET['server'] === 'hard_rate') ? 'hard_rate' : 'mid_rate';
    
    $db_config = $settings['database'][$srv];
    $conn = sqlsrv_connect($db_config['host'], [
        "Database" => $db_config['name'], "Uid" => $db_config['user'], 
        "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), 
        "TrustServerCertificate" => 1, "Encrypt" => 0
    ]);

    $stmt = sqlsrv_query($conn, "SELECT * FROM WebshopItems WHERE ItemType = ? ORDER BY ItemIndex", [$cat]);
    
    echo '<table style="width:100%; border-collapse: collapse; font-size: 0.85em; text-align: left;">';
    echo '<tr style="background:#ddd; border-bottom: 2px solid #aaa;">
            <th style="padding: 5px;">ID</th><th style="padding: 5px;">Name</th>
            <th style="padding: 5px;">Act</th><th style="padding: 5px;">Lck</th>
            <th style="padding: 5px;">Skl</th><th style="padding: 5px;">Exc</th>
            <th style="padding: 5px;">Lvl</th><th style="padding: 5px;">380</th>
            <th style="padding: 5px;">Hrm</th><th style="padding: 5px;">Sck</th>
            <th style="padding: 5px;">Anc</th>
            <th style="padding: 5px; width:60px;">Price</th>
            <th style="padding: 5px; width:50px;">MaxExc</th>
            <th style="padding: 5px; width:50px;">MaxSck</th>
          </tr>';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $t = $row['ItemType']; $i = $row['ItemIndex'];
        $name = str_replace('"', '', $row['ItemName']);
        echo "<tr style='border-bottom: 1px solid #ddd;'>";
        echo "<td style='padding: 5px;'>{$i}</td><td style='padding: 5px;'>{$name}</td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'IsActive', this)\" ".($row['IsActive'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowLuck', this)\" ".($row['AllowLuck'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowSkill', this)\" ".($row['AllowSkill'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowExc', this)\" ".($row['AllowExc'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowLevel', this)\" ".($row['AllowLevel'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'Allow380', this)\" ".($row['Allow380'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowHarmony', this)\" ".($row['AllowHarmony'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowSocket', this)\" ".($row['AllowSocket'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='checkbox' onchange=\"updateItemData($t, $i, 'AllowAncient', this)\" ".($row['AllowAncient'] ? 'checked':'')."></td>";
        echo "<td style='padding: 5px;'><input type='number' min='0' style='width:50px; padding:3px;' value='{$row['BasePrice']}' onchange=\"updateItemData($t, $i, 'BasePrice', this)\"></td>";
        echo "<td style='padding: 5px;'><input type='number' min='0' max='6' style='width:40px; padding:3px;' value='{$row['MaxExc']}' onchange=\"updateItemData($t, $i, 'MaxExc', this)\"></td>";
        echo "<td style='padding: 5px;'><input type='number' min='0' max='5' style='width:40px; padding:3px;' value='{$row['MaxSocket']}' onchange=\"updateItemData($t, $i, 'MaxSocket', this)\"></td>";
        echo "</tr>";
    }
    echo '</table>'; exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'update_item_data') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $srv = ($_GET['server'] === 'hard_rate') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$srv];
    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    
    $allowed_cols = ['IsActive', 'AllowExc', 'AllowLevel', 'Allow380', 'AllowHarmony', 'AllowSocket', 'AllowAncient', 'BasePrice', 'MaxExc', 'MaxSocket', 'AllowLuck', 'AllowSkill'];
    $col = $_GET['col'];
    if (in_array($col, $allowed_cols)) {
        $sql = "UPDATE WebshopItems SET $col = ? WHERE ItemType = ? AND ItemIndex = ?";
        sqlsrv_query($conn, $sql, [(int)$_GET['val'], (int)$_GET['type'], (int)$_GET['index']]);
    }
    exit;
}

// --- POST FORM HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_file = '../../Configuration/settings.json';
    $settings = json_decode(file_get_contents($settings_file), true);
    $action = $_POST['action'] ?? '';
    $page = 'settings';
    $status = 'Settings saved successfully!';

    switch ($action) {
        case 'manage_user_credits':
            $target_user = $_POST['target_user'];
            $amount = (int)$_POST['credit_amount'];
            $op = $_POST['operation']; 
            
            $srv_choice = (isset($_POST['server_select']) && $_POST['server_select'] === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config = $settings['database'][$srv_choice];
            
            $conn = sqlsrv_connect($db_config['host'], [
                "Database" => $db_config['name'], "Uid" => $db_config['user'],
                "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
                "TrustServerCertificate" => 1, "Encrypt" => 0
            ]);

            if (!$conn) { die("Database Connection Failed for selected server."); }

            $checkSql = "IF NOT EXISTS (SELECT 1 FROM WebCredits WHERE memb___id = ?) INSERT INTO WebCredits (memb___id, credits) VALUES (?, 0)";
            sqlsrv_query($conn, $checkSql, [$target_user, $target_user]);

            if ($op == 'add') { 
                $sql = "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?"; 
                $params = [$amount, $target_user]; 
            } elseif ($op == 'minus') { 
                $sql = "UPDATE WebCredits SET credits = CASE WHEN credits >= ? THEN credits - ? ELSE 0 END WHERE memb___id = ?"; 
                $params = [$amount, $amount, $target_user]; 
            } else { 
                $sql = "UPDATE WebCredits SET credits = ? WHERE memb___id = ?"; 
                $params = [$amount, $target_user]; 
            }
            
            sqlsrv_query($conn, $sql, $params);
            sqlsrv_close($conn);
            $page = 'user_settings';
            $status = "Credits updated for " . htmlspecialchars($target_user) . " on " . ($srv_choice === 'hard_rate' ? 'Server 2' : 'Server 1');
            break;

        case 'save_webshop_prices':
            $settings['webshop']['mid_rate'] = [
                'price_level' => (int)$_POST['mid_price_level'],
                'price_exc' => (int)$_POST['mid_price_exc'],
                'price_luck_skill' => (int)$_POST['mid_price_luck_skill'],
                'price_380' => (int)$_POST['mid_price_380'],
                'price_harmony' => (int)$_POST['mid_price_harmony'],
                'price_socket' => (int)$_POST['mid_price_socket'],
                'price_ancient' => (int)$_POST['mid_price_ancient']
            ];
            $settings['webshop']['hard_rate'] = [
                'price_level' => (int)$_POST['hard_price_level'],
                'price_exc' => (int)$_POST['hard_price_exc'],
                'price_luck_skill' => (int)$_POST['hard_price_luck_skill'],
                'price_380' => (int)$_POST['hard_price_380'],
                'price_harmony' => (int)$_POST['hard_price_harmony'],
                'price_socket' => (int)$_POST['hard_price_socket'],
                'price_ancient' => (int)$_POST['hard_price_ancient']
            ];
            $page = 'user_settings';
            break;

        case 'upload_item_txt':
            if (isset($_FILES['item_txt']) && $_FILES['item_txt']['error'] == 0) {
                
                $lines = file($_FILES['item_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $itemsToInsert = [];
                $section = -1;

                // 1. Parse Item.txt
                foreach ($lines as $index => $line) {
                    if ($index === 0) $line = preg_replace('/^[\xef\xbb\xbf]+/', '', $line); 
                    $line = trim($line);
                    
                    if ($line === '' || strpos($line, '//') === 0) continue; 
                    if (strtolower($line) === 'end') { $section = -1; continue; }
                    
                    $secCheck = trim(explode('//', $line)[0]);
                    if (is_numeric($secCheck) && strlen($secCheck) <= 2) { 
                        $section = (int)$secCheck; continue; 
                    }

                    if ($section >= 0 && $section <= 15) {
                        if (preg_match('/^(\d+)\s+(?:-?\d+\s+){2}(\d+)\s+(\d+).*?"([^"]+)"/', $line, $matches)) {
                            $id = (int)$matches[1]; $w = (int)$matches[2]; $h = (int)$matches[3]; $name = $matches[4];
                            // Add AncName1 and AncName2 to the default array
                            $itemsToInsert["$section-$id"] = [
                                'type' => $section, 'id' => $id, 'name' => $name, 'w' => $w, 'h' => $h,
                                'sck' => 0, 'maxSck' => 0, 'opt380' => 0, 'anc' => 0, 'ancName1' => null, 'ancName2' => null
                            ];
                        }
                    }
                }

                // 2. Map Sockets and 380
                function parseExtraFile($fileKey, &$itemsArray, $flag, $maxSckFlag = false) {
                    if (empty($_FILES[$fileKey]['tmp_name']) || $_FILES[$fileKey]['error'] != 0) return;
                    $xLines = file($_FILES[$fileKey]['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($xLines as $xL) {
                        $xL = trim(explode('//', $xL)[0]); 
                        $xL = trim($xL, " \t\n\r\0\x0B\xEF\xBB\xBF");
                        if ($xL === '' || strtolower($xL) === 'end') continue;
                        $parts = preg_split('/\s+/', $xL);
                        if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                            $key = (int)$parts[0].'-'.(int)$parts[1];
                            if (isset($itemsArray[$key])) {
                                $itemsArray[$key][$flag] = 1; 
                                if ($maxSckFlag && isset($parts[2]) && is_numeric($parts[2])) {
                                    $itemsArray[$key]['maxSck'] = (int)$parts[2];
                                } elseif ($maxSckFlag) $itemsArray[$key]['maxSck'] = 5;
                            }
                        }
                    }
                }
                parseExtraFile('socket_txt', $itemsToInsert, 'sck', true);
                parseExtraFile('380_txt', $itemsToInsert, 'opt380');

                // 3. Map Ancient Names!
                $ancientNames = [];
                if (!empty($_FILES['anc_opt_txt']['tmp_name'])) {
                    $ancOptLines = file($_FILES['anc_opt_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($ancOptLines as $l) {
                        $l = trim(explode('//', $l)[0]); $l = trim($l, " \t\n\r\0\x0B\xEF\xBB\xBF");
                        if ($l === '' || strtolower($l) === 'end') continue;
                        // Regex matches: 1  "Warrior"
                        if (preg_match('/^(\d+)\s+"([^"]+)"/', $l, $m)) {
                            $ancientNames[(int)$m[1]] = $m[2];
                        }
                    }
                }

                if (!empty($_FILES['ancient_txt']['tmp_name'])) {
                    $ancLines = file($_FILES['ancient_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($ancLines as $l) {
                        $l = trim(explode('//', $l)[0]); $l = trim($l, " \t\n\r\0\x0B\xEF\xBB\xBF");
                        if ($l === '' || strtolower($l) === 'end') continue;
                        $p = preg_split('/\s+/', $l);
                        // Format: Section Type StatType OptIndex1 OptIndex2
                        if (count($p) >= 5 && is_numeric($p[0]) && is_numeric($p[1])) {
                            $key = (int)$p[0].'-'.(int)$p[1];
                            if (isset($itemsToInsert[$key])) {
                                $itemsToInsert[$key]['anc'] = 1;
                                $itemsToInsert[$key]['ancName1'] = $ancientNames[(int)$p[3]] ?? null;
                                $itemsToInsert[$key]['ancName2'] = $ancientNames[(int)$p[4]] ?? null;
                            }
                        }
                    }
                }

                // 4. Send to Databases
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
                        sqlsrv_query($conn, "TRUNCATE TABLE WebshopItems");
                        sqlsrv_begin_transaction($conn);
                        
                        foreach ($itemsToInsert as $i) {
                            $sql = "INSERT INTO WebshopItems (ItemType, ItemIndex, ItemName, Width, Height, BasePrice, AllowExc, AllowLevel, Allow380, AllowHarmony, AllowSocket, MaxExc, MaxSocket, AllowLuck, AllowSkill, AllowAncient, AncName1, AncName2) VALUES (?, ?, ?, ?, ?, 100, 1, 1, ?, 1, ?, 6, ?, 1, 1, ?, ?, ?)";
                            sqlsrv_query($conn, $sql, [
                                $i['type'], $i['id'], $i['name'], $i['w'], $i['h'], 
                                $i['opt380'], $i['sck'], $i['maxSck'], $i['anc'], $i['ancName1'], $i['ancName2']
                            ]);
                        }
                        
                        sqlsrv_commit($conn);
                        sqlsrv_close($conn);
                        $serversUpdated++;
                    }
                }
                
                $status = ($serversUpdated > 0) ? "Successfully mapped & synced $totalParsed items to $serversUpdated database(s)!" : "Error: Parsed $totalParsed items but could not connect to databases.";

            } else { 
                $status = "Error uploading Item.txt. Code: " . ($_FILES['item_txt']['error'] ?? 'Unknown'); 
            }
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
            $settings['mid_rate_server']['name'] = $_POST['mid_name'];
            $settings['mid_rate_server']['address'] = $_POST['mid_address'];
            $settings['mid_rate_server']['port'] = (int)$_POST['mid_port'];
            $settings['mid_rate_server']['visible'] = (bool)($_POST['mid_visible'] ?? true); 
            $settings['hard_rate_server']['name'] = $_POST['hard_name'];
            $settings['hard_rate_server']['address'] = $_POST['hard_address'];
            $settings['hard_rate_server']['port'] = (int)$_POST['hard_port'];
            $settings['hard_rate_server']['visible'] = (bool)($_POST['hard_visible'] ?? true);
            $page = 'settings';
            break;

        case 'save_links':
            $settings['download_link_1']['label'] = $_POST['label1'];
            $settings['download_link_1']['url'] = $_POST['url1'];
            $settings['download_link_2']['label'] = $_POST['label2'];
            $settings['download_link_2']['url'] = $_POST['url2'];
            $page = 'links';
            break;

        case 'save_security':
            $settings['security']['session_timeout_minutes'] = (int)$_POST['session_timeout_minutes'];
            $settings['security']['user_session_timeout_minutes'] = (int)$_POST['user_session_timeout_minutes']; 
            $page = 'security';
            break;

        case 'save_database':
            $settings['database']['mid_rate']['host'] = $_POST['mid_db_host'];
            $settings['database']['mid_rate']['name'] = $_POST['mid_db_name']; 
            $settings['database']['mid_rate']['user'] = $_POST['mid_db_user'];
            if (!empty($_POST['mid_db_pass'])) $settings['database']['mid_rate']['pass_encrypted'] = encrypt_pass($_POST['mid_db_pass'], ENCRYPTION_KEY);
            
            $settings['database']['hard_rate']['host'] = $_POST['hard_db_host'];
            $settings['database']['hard_rate']['name'] = $_POST['hard_db_name']; 
            $settings['database']['hard_rate']['user'] = $_POST['hard_db_user'];
            if (!empty($_POST['hard_db_pass'])) $settings['database']['hard_rate']['pass_encrypted'] = encrypt_pass($_POST['hard_db_pass'], ENCRYPTION_KEY);
            $page = 'database';
            break;
            
        case 'save_user_dashboard':
            $settings['user_dashboard']['enable_webshop'] = isset($_POST['enable_webshop']);
            $settings['user_dashboard']['enable_reset'] = isset($_POST['enable_reset']);
            $settings['user_dashboard']['enable_reset_stats'] = isset($_POST['enable_reset_stats']);
            $settings['user_dashboard']['enable_clear_pk'] = isset($_POST['enable_clear_pk']);
            $settings['user_dashboard']['enable_reset_master'] = isset($_POST['enable_reset_master']);
            $settings['user_dashboard']['enable_unstuck'] = isset($_POST['enable_unstuck']);
            $settings['conversion_rates']['wcoinc'] = (int)$_POST['rate_wcoinc'];
            $settings['conversion_rates']['wcoinp'] = (int)$_POST['rate_wcoinp'];
            $settings['conversion_rates']['goblin'] = (int)$_POST['rate_goblin'];
            $page = 'user_settings';
            break;  
    }
    
    if ($action !== 'manage_user_credits' && $action !== 'upload_item_txt') {
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    }
    
    header('Location: ../dashboard.php?page=' . $page . '&status=' . urlencode($status));
    exit;
}
?>