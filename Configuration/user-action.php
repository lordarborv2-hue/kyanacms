<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';

// Auth Check
if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$action = $_POST['action'] ?? '';
$charName = $_POST['character'] ?? '';
$username = $_SESSION['user_id'];
$server = $_SESSION['user_server'];

// DB Connection
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = $db_config['name'] ?? 'MuOnline';
} else {
    $db_config = $settings['database']['hard_rate'];
    $db_name = $db_config['name'] ?? 'MuOnlineEly';
}

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => 1,
    "Encrypt" => 0
]);

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB Connection Failed']); exit; }

// VERIFY OWNERSHIP & ONLINE STATUS
// Important: Character must be OFFLINE for most actions
$checkSql = "SELECT AccountID FROM Character WHERE Name = ?";
$stmt = sqlsrv_query($conn, $checkSql, [$charName]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row || $row['AccountID'] !== $username) {
    echo json_encode(['success' => false, 'message' => 'Character not found on this account.']); exit;
}

// Check Online Status
$onlineSql = "SELECT ConnectStat FROM MEMB_STAT WHERE memb___id = ?";
$onlineStmt = sqlsrv_query($conn, $onlineSql, [$username]);
$onlineRow = sqlsrv_fetch_array($onlineStmt, SQLSRV_FETCH_ASSOC);
if ($onlineRow && $onlineRow['ConnectStat'] == 1) {
    echo json_encode(['success' => false, 'message' => 'Please logout from the game first.']); exit;
}

// --- ACTIONS ---
$features = $settings['user_dashboard'] ?? [];
$message = 'Action failed.';
$success = false;

switch ($action) {
    case 'reset_char':
        if (empty($features['enable_reset'])) { $message = 'Reset is disabled.'; break; }
        // Simple Reset: Level 400 -> 1, ResetCount +1. (Customize stats/zen requirements here)
        $sql = "UPDATE Character SET cLevel = 1, Experience = 0, ResetCount = ResetCount + 1, MapNumber = 0, MapPosX = 125, MapPosY = 125 WHERE Name = ? AND cLevel >= 400";
        $r = sqlsrv_query($conn, $sql, [$charName]);
        if ($r && sqlsrv_rows_affected($r) > 0) { $success = true; $message = 'Character Reset Successful!'; }
        else { $message = 'Requirement: Level 400.'; }
        break;

    case 'reset_stats':
        if (empty($features['enable_reset_stats'])) { $message = 'Reset Stats is disabled.'; break; }
        
        // Reset Stats Logic
        // 1. Resets STR, DEX, VIT, ENE to 25.
        // 2. If Dark Lord (Class 64,65,66), resets Leadership to 25 and refunds points.
        // 3. Adds all refunded points to LevelUpPoint.
        
        $sql = "UPDATE Character SET 
                LevelUpPoint = LevelUpPoint + 
                               (Strength - 15) + 
                               (Dexterity - 15) + 
                               (Vitality - 15) + 
                               (Energy - 15) + 
                               CASE WHEN Class IN (64, 65, 66) THEN (Leadership - 15) ELSE 0 END,
                Strength = 15, 
                Dexterity = 15, 
                Vitality = 15, 
                Energy = 15,
                Leadership = CASE WHEN Class IN (64, 65, 66) THEN 15 ELSE Leadership END
                WHERE Name = ?";
                
        $r = sqlsrv_query($conn, $sql, [$charName]);
        if ($r) { $success = true; $message = 'Stats have been reset!'; }
        else { $message = 'Database error during stats reset.'; }
        break;

    case 'clear_pk':
        if (empty($features['enable_clear_pk'])) { $message = 'Clear PK is disabled.'; break; }
        // Set to Commoner (3)
        $sql = "UPDATE Character SET PkLevel = 3, PkCount = 0, PkTime = 0 WHERE Name = ?";
        $r = sqlsrv_query($conn, $sql, [$charName]);
        if ($r) { $success = true; $message = 'PK Status cleared!'; }
        break;

    case 'reset_master':
        if (empty($features['enable_reset_master'])) { $message = 'Master Reset is disabled.'; break; }
        // Clear Master Tree
        $sql = "UPDATE MasterSkillTree SET MasterLevel = 0, MasterPoint = 0, MasterExperience = 0 WHERE Name = ?";
        $r = sqlsrv_query($conn, $sql, [$charName]);
        if ($r) { $success = true; $message = 'Master Skill Tree reset!'; }
        break;
		
	case 'unstuck_char':
        if (empty($features['enable_unstuck'])) { $message = 'Unstuck is disabled.'; break; }
        
        // Move to Lorencia (Map 0) Coordinates 125, 125
        $sql = "UPDATE Character SET MapNumber = 0, MapPosX = 125, MapPosY = 125 WHERE Name = ?";
        $r = sqlsrv_query($conn, $sql, [$charName]);
        
        if ($r) { $success = true; $message = 'Character moved to Lorencia!'; }
        else { $message = 'Database error.'; }
        break;	

    default:
        $message = 'Invalid Action.';
}

echo json_encode(['success' => $success, 'message' => $message]);
sqlsrv_close($conn);
?>