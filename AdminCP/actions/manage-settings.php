<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { die('Access Denied.'); }
require_once '../../config.php';

function encrypt_pass($password, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));
    $encrypted = openssl_encrypt($password, ENCRYPTION_CIPHER, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_file = '../../Configuration/settings.json';
    $settings = json_decode(file_get_contents($settings_file), true);
    $action = $_POST['action'] ?? '';
    $page = 'settings';
    $status = 'Settings saved successfully!';

    switch ($action) {
        case 'save_site_settings':
            // 1. Handle Favicon
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] == 0) {
                $target_dir = '../../uploads/';
                $file = $_FILES['favicon_file'];
                $max_size = 1 * 1024 * 1024;
                $allowed_extensions = ['ico', 'png', 'jpg', 'jpeg'];
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['size'] <= $max_size && in_array($extension, $allowed_extensions)) {
                    $new_filename = 'favicon-' . uniqid() . '.' . $extension;
                    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
                        $old_favicon = $settings['favicon_url'] ?? '';
                        if ($old_favicon && basename($old_favicon) !== 'default-favicon.ico' && file_exists('../../' . $old_favicon)) {
                            unlink('../../' . $old_favicon);
                        }
                        $settings['favicon_url'] = 'uploads/' . $new_filename;
                    }
                } else { $status = 'Error: Invalid favicon file type or size.'; }
            }
            $settings['website_title'] = $_POST['website_title'];
            
            // 2. Save Server 1 (Mid Rate) + VISIBILITY
            $settings['mid_rate_server']['name'] = $_POST['mid_name'];
            $settings['mid_rate_server']['address'] = $_POST['mid_address'];
            $settings['mid_rate_server']['port'] = (int)$_POST['mid_port'];
            // This line was missing:
            $settings['mid_rate_server']['visible'] = (bool)($_POST['mid_visible'] ?? true); 

            // 3. Save Server 2 (Hard Rate) + VISIBILITY
            $settings['hard_rate_server']['name'] = $_POST['hard_name'];
            $settings['hard_rate_server']['address'] = $_POST['hard_address'];
            $settings['hard_rate_server']['port'] = (int)$_POST['hard_port'];
            // This line was missing:
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
            // This line was missing:
            $settings['security']['user_session_timeout_minutes'] = (int)$_POST['user_session_timeout_minutes']; 
            $page = 'security';
            break;

        case 'save_database':
            // Server 1 DB
            $settings['database']['mid_rate']['host'] = $_POST['mid_db_host'];
            $settings['database']['mid_rate']['name'] = $_POST['mid_db_name']; // <--- Save Name
            $settings['database']['mid_rate']['user'] = $_POST['mid_db_user'];
            if (!empty($_POST['mid_db_pass'])) {
                $settings['database']['mid_rate']['pass_encrypted'] = encrypt_pass($_POST['mid_db_pass'], ENCRYPTION_KEY);
            }
            
            // Server 2 DB
            $settings['database']['hard_rate']['host'] = $_POST['hard_db_host'];
            $settings['database']['hard_rate']['name'] = $_POST['hard_db_name']; // <--- Save Name
            $settings['database']['hard_rate']['user'] = $_POST['hard_db_user'];
            if (!empty($_POST['hard_db_pass'])) {
                $settings['database']['hard_rate']['pass_encrypted'] = encrypt_pass($_POST['hard_db_pass'], ENCRYPTION_KEY);
            }
            $page = 'database';
            break;
			
		case 'save_user_dashboard':
            $settings['user_dashboard']['enable_reset'] = isset($_POST['enable_reset']);
            $settings['user_dashboard']['enable_reset_stats'] = isset($_POST['enable_reset_stats']);
            $settings['user_dashboard']['enable_clear_pk'] = isset($_POST['enable_clear_pk']);
            $settings['user_dashboard']['enable_reset_master'] = isset($_POST['enable_reset_master']);
			$settings['user_dashboard']['enable_unstuck'] = isset($_POST['enable_unstuck']);
            $page = 'user_settings';
            break;	
    }
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    header('Location: ../dashboard.php?page=' . $page . '&status=' . urlencode($status));
    exit;
}
?>