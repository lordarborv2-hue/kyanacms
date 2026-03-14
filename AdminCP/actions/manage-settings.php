<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { die('Access Denied.'); }
require_once '../../config.php';

// ============================================================
// AJAX GET HANDLERS
// ============================================================

// --- Lookup Credits ---
if (isset($_GET['action']) && $_GET['action'] === 'lookup_credits') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $user = $_GET['user'] ?? '';
    $admin_srv = $_SESSION['admin_server'] ?? 'mid';
    $admin_server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$admin_server_key];

    if (empty($db_config['host'])) { echo json_encode(['success' => false]); exit; }
    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    if (!$conn) { echo json_encode(['success' => false]); exit; }

    $stmt = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$user]);
    $row  = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    header('Content-Type: application/json');
    echo $row ? json_encode(['success' => true, 'credits' => $row['credits']]) : json_encode(['success' => false]);
    sqlsrv_close($conn); exit;
}

// --- View Webshop Logs ---
if (isset($_GET['action']) && $_GET['action'] === 'view_webshop_logs') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
    $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config  = $settings['database'][$server_key];

    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    if (!$conn) { echo "Database connection failed."; exit; }

    $stmt = sqlsrv_query($conn, "SELECT TOP 50 * FROM Webshop_Logs ORDER BY PurchaseDate DESC");
    echo '<table style="width:100%; border-collapse: collapse; font-size: 0.9em; text-align: left;">';
    echo '<tr style="background:#444; color:white;"><th style="padding:10px;">Date</th><th style="padding:10px;">Account</th><th style="padding:10px;">Item & Options</th><th style="padding:10px;">Price</th></tr>';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $date = $row['PurchaseDate']->format('Y-m-d H:i');
        echo "<tr style='border-bottom: 1px solid #ddd;'>
                <td style='padding:8px;'>{$date}</td>
                <td style='padding:8px;'><strong>{$row['AccountID']}</strong></td>
                <td style='padding:8px;'>
                    <div>{$row['ItemName']}</div>
                    <div style='font-size:0.8em; color:#666;'>{$row['ItemOptions']}</div>
                </td>
                <td style='padding:8px; color:#d35400;'>{$row['Price']} Credits</td>
              </tr>";
    }
    echo '</table>';
    sqlsrv_close($conn); exit;
}

// --- Load Category Items ---
if (isset($_GET['action']) && $_GET['action'] === 'load_category_items') {
    $settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $cat = (int)$_GET['cat'];
    $admin_srv        = $_SESSION['admin_server'] ?? 'mid';
    $admin_server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config        = $settings['database'][$admin_server_key];

    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    if (!$conn) { echo "Database connection failed."; exit; }

    $stmt = sqlsrv_query($conn, "SELECT * FROM WebshopItems WHERE ItemType = ? ORDER BY ItemIndex ASC", [$cat]);
    echo "<table style='border-collapse:collapse; width:100%; font-size:0.85em;'>
          <tr style='background:#eee;'>
            <th style='padding:5px;'>ID</th><th style='padding:5px;'>Name</th><th style='padding:5px;'>Active</th>
            <th style='padding:5px;'>Lvl</th><th style='padding:5px;'>Luck</th><th style='padding:5px;'>Skill</th>
            <th style='padding:5px;'>Exc</th><th style='padding:5px;'>380</th><th style='padding:5px;'>Harm</th>
            <th style='padding:5px;'>Sock</th><th style='padding:5px;'>Anc</th>
            <th style='padding:5px;'>Base Price</th><th style='padding:5px;'>MaxExc</th><th style='padding:5px;'>MaxSock</th>
          </tr>";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $t = $row['ItemType']; $i = $row['ItemIndex'];
        echo "<tr style='border-bottom:1px solid #ddd;'>
              <td style='padding:5px;'>{$i}</td>
              <td style='padding:5px;'>{$row['ItemName']}</td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'IsActive',this)\" ".($row['IsActive']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowLevel',this)\" ".($row['AllowLevel']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowLuck',this)\" ".($row['AllowLuck']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowSkill',this)\" ".($row['AllowSkill']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowExc',this)\" ".($row['AllowExc']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'Allow380',this)\" ".($row['Allow380']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowHarmony',this)\" ".($row['AllowHarmony']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowSocket',this)\" ".($row['AllowSocket']?'checked':'')."></td>
              <td style='padding:5px;'><input type='checkbox' onchange=\"updateItemData($t,$i,'AllowAncient',this)\" ".($row['AllowAncient']?'checked':'')."></td>
              <td style='padding:5px;'><input type='number' min='0' style='width:60px;padding:3px;' value='{$row['BasePrice']}' onchange=\"updateItemData($t,$i,'BasePrice',this)\"></td>
              <td style='padding:5px;'><input type='number' min='0' max='6' style='width:40px;padding:3px;' value='{$row['MaxExc']}' onchange=\"updateItemData($t,$i,'MaxExc',this)\"></td>
              <td style='padding:5px;'><input type='number' min='0' max='5' style='width:40px;padding:3px;' value='{$row['MaxSocket']}' onchange=\"updateItemData($t,$i,'MaxSocket',this)\"></td>
              </tr>";
    }
    echo '</table>';
    sqlsrv_close($conn); exit;
}

// --- Update Single Item Field ---
if (isset($_GET['action']) && $_GET['action'] === 'update_item_data') {
    $settings         = json_decode(file_get_contents('../../Configuration/settings.json'), true);
    $admin_srv        = $_SESSION['admin_server'] ?? 'mid';
    $admin_server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config        = $settings['database'][$admin_server_key];

    $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
    $allowed_cols = ['IsActive','AllowExc','AllowLevel','Allow380','AllowHarmony','AllowSocket','AllowAncient','BasePrice','MaxExc','MaxSocket','AllowLuck','AllowSkill'];
    $col = $_GET['col'] ?? '';
    if (in_array($col, $allowed_cols)) {
        sqlsrv_query($conn, "UPDATE WebshopItems SET $col = ? WHERE ItemType = ? AND ItemIndex = ?", [(int)$_GET['val'], (int)$_GET['type'], (int)$_GET['index']]);
    }
    sqlsrv_close($conn); exit;
}

// ============================================================
// POST FORM HANDLERS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_file = '../../Configuration/settings.json';

    if (!is_writable($settings_file) && $_POST['action'] !== 'upload_item_txt') {
        die("<h2 style='color:red;'>CRITICAL ERROR: Permission Denied!</h2><p>Cannot write to settings.json.</p>");
    }

    $settings = json_decode(file_get_contents($settings_file), true);
    $action   = $_POST['action'] ?? '';
    $page     = 'settings';
    $status   = 'Settings saved successfully!';

    switch ($action) {

        // ---- MANAGE USER CREDITS ----
        case 'manage_user_credits':
            $target_user = $_POST['target_user'];
            $amount      = (int)$_POST['credit_amount'];
            $op          = $_POST['operation'];
            $admin_srv   = $_SESSION['admin_server'] ?? 'mid';
            $server_key  = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config   = $settings['database'][$server_key];

            $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
            if (!$conn) { die("Database Connection Failed for selected server."); }

            sqlsrv_query($conn, "IF NOT EXISTS (SELECT 1 FROM WebCredits WHERE memb___id = ?) INSERT INTO WebCredits (memb___id, credits) VALUES (?, 0)", [$target_user, $target_user]);

            if ($op == 'add')        { $sql = "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?"; }
            elseif ($op == 'minus')  { $sql = "UPDATE WebCredits SET credits = CASE WHEN credits >= ? THEN credits - ? ELSE 0 END WHERE memb___id = ?"; $amount = [$amount, $amount]; }
            else                     { $sql = "UPDATE WebCredits SET credits = ? WHERE memb___id = ?"; }

            sqlsrv_query($conn, $sql, is_array($amount) ? array_merge($amount, [$target_user]) : [$amount, $target_user]);
            sqlsrv_close($conn);
            $page   = 'user_settings';
            $status = "Credits updated for " . htmlspecialchars($target_user);
            break;

        // ---- WEBSHOP PRICES (includes JoL) ----
        case 'save_webshop_prices':
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['webshop'])) $settings['webshop'] = [];
            $settings['webshop'][$server_key] = [
                'price_level'         => (int)$_POST['price_level'],
                'price_exc'           => (int)$_POST['price_exc'],
                'price_luck_skill'    => (int)$_POST['price_luck_skill'],
                'price_380'           => (int)$_POST['price_380'],
                'price_harmony'       => (int)$_POST['price_harmony'],
                'price_socket'        => (int)$_POST['price_socket'],
                'price_ancient'       => (int)$_POST['price_ancient'],
                'price_jol_base'      => (int)($_POST['price_jol_base']      ?? 100),
                'price_jol_per_level' => (int)($_POST['price_jol_per_level'] ?? 50),
            ];
            $page = 'user_settings';
            break;

        // ---- USER DASHBOARD FEATURES ----
        case 'save_user_settings':
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['user_dashboard'])) $settings['user_dashboard'] = [];
            $settings['user_dashboard'][$server_key] = [
                'enable_webshop'      => isset($_POST['enable_webshop']),
                'enable_reset'        => isset($_POST['enable_reset']),
                'enable_reset_stats'  => isset($_POST['enable_reset_stats']),
                'enable_clear_pk'     => isset($_POST['enable_clear_pk']),
                'enable_reset_master' => isset($_POST['enable_reset_master']),
                'enable_unstuck'      => isset($_POST['enable_unstuck']),
            ];
            if (isset($_POST['rate_wcoinc'])) {
                $settings['conversion_rates']['wcoinc'] = (int)$_POST['rate_wcoinc'];
                $settings['conversion_rates']['wcoinp'] = (int)$_POST['rate_wcoinp'];
                $settings['conversion_rates']['goblin']  = (int)$_POST['rate_goblin'];
            }
            $page = 'user_settings';
            break;

        // ---- PAYMONGO SETTINGS ----
        case 'save_paymongo_settings':
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['paymongo']))               $settings['paymongo'] = [];
            if (!isset($settings['paymongo'][$server_key])) $settings['paymongo'][$server_key] = [];
            $settings['paymongo'][$server_key]['enabled'] = isset($_POST['paymongo_enabled']);
            $settings['paymongo'][$server_key]['rate']    = (int)$_POST['paymongo_rate'];
            $pm_pub = trim($_POST['paymongo_public']);
            $pm_sec = trim($_POST['paymongo_secret']);
            if (!empty($pm_pub)) $settings['paymongo'][$server_key]['public_key'] = encrypt_data($pm_pub, ENCRYPTION_KEY);
            if (!empty($pm_sec)) $settings['paymongo'][$server_key]['secret_key'] = encrypt_data($pm_sec, ENCRYPTION_KEY);
            $page = 'donations';
            break;

        // ---- PAYPAL SETTINGS ----
        case 'save_paypal_settings':
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['paypal']))               $settings['paypal'] = [];
            if (!isset($settings['paypal'][$server_key])) $settings['paypal'][$server_key] = [];
            $settings['paypal'][$server_key]['enabled']  = isset($_POST['paypal_enabled']);
            $settings['paypal'][$server_key]['mode']     = $_POST['paypal_mode'];
            $settings['paypal'][$server_key]['currency'] = strtoupper(trim($_POST['paypal_currency']));
            $settings['paypal'][$server_key]['rate']     = (int)$_POST['paypal_rate'];
            $pp_cli = trim($_POST['paypal_client_id']);
            $pp_sec = trim($_POST['paypal_secret']);
            if (!empty($pp_cli)) $settings['paypal'][$server_key]['client_id'] = encrypt_data($pp_cli, ENCRYPTION_KEY);
            if (!empty($pp_sec)) $settings['paypal'][$server_key]['secret']    = encrypt_data($pp_sec, ENCRYPTION_KEY);
            $page = 'donations';
            break;

        // ---- QR PH SETTINGS ----
        case 'save_qr_settings':
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            if (!isset($settings['qr_ph'])) $settings['qr_ph'] = [];
            $settings['qr_ph'][$server_key] = [
                'enabled' => isset($_POST['qr_enabled']),
                'ratio'   => (int)($_POST['qr_ratio'] ?? 100),
            ];
            if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] == 0) {
                $targetDir = '../../uploads/qr-ph/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                move_uploaded_file($_FILES['qr_image']['tmp_name'], $targetDir . $server_key . '.png');
            }
            $page = 'donations';
            break;

        // ---- SITE SETTINGS ----
        case 'save_site_settings':
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] == 0) {
                $target_dir = '../../uploads/';
                $file       = $_FILES['favicon_file'];
                $allowed    = ['ico', 'png', 'jpg', 'jpeg'];
                $extension  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['size'] <= 1048576 && in_array($extension, $allowed)) {
                    $new_filename = 'favicon-' . uniqid() . '.' . $extension;
                    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
                        $old_favicon = $settings['favicon_url'] ?? '';
                        if (!empty($old_favicon) && file_exists('../../' . $old_favicon) && strpos($old_favicon, 'default') === false) {
                            unlink('../../' . $old_favicon);
                        }
                        $settings['favicon_url'] = 'uploads/' . $new_filename;
                    } else {
                        $status = "Error: Failed to move uploaded file. Check folder permissions.";
                    }
                } else {
                    $status = "Error: Invalid file size (Max 1MB) or format (ICO/PNG/JPG).";
                }
            }
            $settings['website_title']              = $_POST['website_title'];
            $settings['show_online_count']          = (bool)$_POST['show_online'];
            $settings['mid_rate_server']['name']    = $_POST['mid_name'];
            $settings['mid_rate_server']['address'] = $_POST['mid_address'];
            $settings['mid_rate_server']['port']    = (int)$_POST['mid_port'];
            $settings['mid_rate_server']['visible'] = (bool)$_POST['mid_visible'];
            $settings['hard_rate_server']['name']    = $_POST['hard_name'];
            $settings['hard_rate_server']['address'] = $_POST['hard_address'];
            $settings['hard_rate_server']['port']    = (int)$_POST['hard_port'];
            $settings['hard_rate_server']['visible'] = (bool)$_POST['hard_visible'];
            $page = 'settings';
            break;

        // ---- DATABASE SETTINGS ----
        case 'save_database':
            $settings['database']['mid_rate']['host'] = $_POST['mid_db_host'];
            $settings['database']['mid_rate']['name'] = $_POST['mid_db_name'];
            $settings['database']['mid_rate']['user'] = $_POST['mid_db_user'];
            if (!empty($_POST['mid_db_pass'])) {
                $settings['database']['mid_rate']['pass_encrypted'] = encrypt_data($_POST['mid_db_pass'], ENCRYPTION_KEY);
            }
            $settings['database']['hard_rate']['host'] = $_POST['hard_db_host'];
            $settings['database']['hard_rate']['name'] = $_POST['hard_db_name'];
            $settings['database']['hard_rate']['user'] = $_POST['hard_db_user'];
            if (!empty($_POST['hard_db_pass'])) {
                $settings['database']['hard_rate']['pass_encrypted'] = encrypt_data($_POST['hard_db_pass'], ENCRYPTION_KEY);
            }
            $page   = 'database';
            $status = "Database configurations updated successfully.";
            break;

        // ---- SECURITY SETTINGS ----
        case 'save_security':
            $settings['security']['session_timeout_minutes']      = (int)$_POST['session_timeout_minutes'];
            $settings['security']['user_session_timeout_minutes'] = (int)$_POST['user_session_timeout_minutes'];
            $page = 'security';
            break;

        // ---- DOWNLOAD LINKS ----
        case 'save_links':
            $settings['download_link_1']['label'] = $_POST['label1'];
            $settings['download_link_1']['url']   = $_POST['url1'];
            $settings['download_link_2']['label'] = $_POST['label2'];
            $settings['download_link_2']['url']   = $_POST['url2'];
            $page = 'links';
            break;

        // ---- WALLPAPER ----
        case 'save_wallpaper':
            if (isset($_FILES['wallpaper_file']) && $_FILES['wallpaper_file']['error'] == 0) {
                $target_dir = '../../uploads/';
                $file       = $_FILES['wallpaper_file'];
                $allowed    = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $extension  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['size'] <= 5242880 && in_array($extension, $allowed)) {
                    $new_filename = 'wallpaper-' . uniqid() . '.' . $extension;
                    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
                        $old_wp = $settings['wallpaper_url'] ?? '';
                        if (!empty($old_wp) && file_exists('../../' . $old_wp) && strpos($old_wp, 'default') === false) {
                            unlink('../../' . $old_wp);
                        }
                        $settings['wallpaper_url'] = 'uploads/' . $new_filename;
                        $status = "Wallpaper updated successfully.";
                    } else {
                        $status = "Error: Failed to move wallpaper file.";
                    }
                } else {
                    $status = "Error: Max 5MB, formats: JPG/PNG/WEBP/GIF.";
                }
            }
            $page = 'wallpaper';
            break;

        // ---- APPROVE QR DONATION ORDER ----
        case 'approve_order':
            $order_id   = (int)($_POST['order_id'] ?? 0);
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config  = $settings['database'][$server_key];
            $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
            if (!$conn) { $status = 'Error: Database connection failed.'; $page = 'orders'; break; }
            $orderStmt = sqlsrv_query($conn, "SELECT * FROM PendingDonations WHERE ID = ? AND Status = 0", [$order_id]);
            $order     = sqlsrv_fetch_array($orderStmt, SQLSRV_FETCH_ASSOC);
            if ($order) {
                $acc        = $order['AccountID'];
                $credsToAdd = (int)$order['CreditsToReceive'];
                $check      = sqlsrv_query($conn, "SELECT credits FROM WebCredits WHERE memb___id = ?", [$acc]);
                $row        = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
                if ($row) {
                    sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?", [$credsToAdd, $acc]);
                } else {
                    sqlsrv_query($conn, "INSERT INTO WebCredits (memb___id, credits) VALUES (?, ?)", [$acc, $credsToAdd]);
                }
                sqlsrv_query($conn, "UPDATE PendingDonations SET Status = 1 WHERE ID = ?", [$order_id]);
                $status = "Success: Added $credsToAdd Credits to $acc.";
            } else {
                $status = 'Error: Order not found or already processed.';
            }
            sqlsrv_close($conn);
            $page = 'orders';
            break;

        // ---- REJECT QR DONATION ORDER ----
        case 'reject_order':
            $order_id   = (int)($_POST['order_id'] ?? 0);
            $admin_srv  = $_SESSION['admin_server'] ?? 'mid';
            $server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
            $db_config  = $settings['database'][$server_key];
            $conn = sqlsrv_connect($db_config['host'], ["Database" => $db_config['name'], "Uid" => $db_config['user'], "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
            if ($conn) {
                sqlsrv_query($conn, "UPDATE PendingDonations SET Status = 2 WHERE ID = ?", [$order_id]);
                sqlsrv_close($conn);
                $status = 'Order rejected successfully.';
            } else {
                $status = 'Error: Database connection failed.';
            }
            $page = 'orders';
            break;

        // ---- UPLOAD ITEM.TXT ----
        case 'upload_item_txt':
            if (isset($_FILES['item_txt']) && $_FILES['item_txt']['error'] == 0) {
                // 1. Parse SetItemOption.txt for ancient names
                $ancNames = [];
                if (isset($_FILES['anc_opt_txt']) && $_FILES['anc_opt_txt']['error'] == 0) {
                    $optLines = file($_FILES['anc_opt_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($optLines as $oLine) {
                        if (preg_match('/^(\d+)\s+"([^"]+)"/', trim($oLine), $oMatches)) {
                            $ancNames[(int)$oMatches[1]] = $oMatches[2];
                        }
                    }
                }

                // 2. Parse SetItemType.txt for ancient set mapping
                $ancSets = [];
                if (isset($_FILES['ancient_txt']) && $_FILES['ancient_txt']['error'] == 0) {
                    $setLines = file($_FILES['ancient_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($setLines as $sLine) {
                        if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', trim($sLine), $sMatches)) {
                            $type = (int)$sMatches[1]; $idx = (int)$sMatches[2];
                            $key  = "$type-$idx";
                            if (!isset($ancSets[$key])) $ancSets[$key] = [];
                            $setIdx = (int)$sMatches[3];
                            if (isset($ancNames[$setIdx])) {
                                if (!isset($ancSets[$key]['name1'])) $ancSets[$key]['name1'] = $ancNames[$setIdx];
                                else                                 $ancSets[$key]['name2'] = $ancNames[$setIdx];
                            }
                        }
                    }
                }

                // 3. Parse SocketItemType.txt and 380ItemType.txt
                $sockets = ''; $item380 = '';
                if (isset($_FILES['socket_txt']) && $_FILES['socket_txt']['error'] == 0) $sockets = file_get_contents($_FILES['socket_txt']['tmp_name']);
                if (isset($_FILES['380_txt'])    && $_FILES['380_txt']['error']    == 0) $item380  = file_get_contents($_FILES['380_txt']['tmp_name']);

                // 4. Parse Item.txt
                $itemsToInsert = [];
                $lines   = file($_FILES['item_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $section = -1;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^\/\/\s*Section\s+(\d+)/i', $line, $m)) { $section = (int)$m[1]; continue; }
                    if ($line === '' || substr($line, 0, 2) === '//' || $section < 0) continue;
                    if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+"([^"]+)"/', $line, $matches)) {
                        $id      = (int)$matches[1];
                        $ancData = $ancSets["$section-$id"] ?? null;
                        $targetFolder = "../../uploads/items/" . $section . "/";
                        if (!is_dir($targetFolder)) mkdir($targetFolder, 0777, true);
                        $itemsToInsert[] = [
                            'type'     => $section, 'id' => $id, 'name' => $matches[4],
                            'w'        => (int)$matches[2], 'h' => (int)$matches[3],
                            'sck'      => (preg_match("/\b$section\s+$id\b/", $sockets)) ? 1 : 0,
                            'opt380'   => (preg_match("/\b$section\s+$id\b/", $item380)) ? 1 : 0,
                            'ancName1' => $ancData['name1'] ?? null,
                            'ancName2' => $ancData['name2'] ?? null,
                        ];
                    }
                }

                // 5. Sync to Database
                $target  = $_POST['upload_target'] ?? 'both';
                $servers = ($target === 'both') ? ['mid_rate', 'hard_rate'] : [$target];
                foreach ($servers as $srv) {
                    $db   = $settings['database'][$srv];
                    $conn = sqlsrv_connect($db['host'], ["Database" => $db['name'], "Uid" => $db['user'], "PWD" => decrypt_data($db['pass_encrypted'], ENCRYPTION_KEY), "TrustServerCertificate" => 1, "Encrypt" => 0]);
                    if ($conn) {
                        sqlsrv_query($conn, "TRUNCATE TABLE WebshopItems");
                        foreach ($itemsToInsert as $item) {
                            $sql = "INSERT INTO WebshopItems (ItemType, ItemIndex, ItemName, Width, Height, BasePrice, AllowAncient, AncName1, AncName2, AllowSocket, Allow380)
                                    VALUES (?, ?, ?, ?, ?, 100, ?, ?, ?, ?, ?)";
                            sqlsrv_query($conn, $sql, [$item['type'], $item['id'], $item['name'], $item['w'], $item['h'], ($item['ancName1'] ? 1 : 0), $item['ancName1'], $item['ancName2'], $item['sck'], $item['opt380']]);
                        }
                        sqlsrv_close($conn);
                    }
                }
                $status = "Success: Webshop DB and image folders synchronized!";
            } else {
                $status = "Error: Item.txt is required.";
            }
            $page = 'user_settings';
            break;
    }

    // Save settings.json for all cases except DB-only actions
    if (!in_array($action, ['manage_user_credits', 'upload_item_txt', 'approve_order', 'reject_order'])) {
        $saveResult = file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($saveResult === false) {
            die("<h2 style='color:red;'>Save Failed</h2><p>Could not write to settings.json. Check permissions.</p>");
        }
    }

    header('Location: ../dashboard.php?page=' . $page . '&status=' . urlencode($status));
    exit;
}
?>