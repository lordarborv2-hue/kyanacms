<?php
// This handles the login check and session timeout
require_once 'check-auth.php';
require_once '../config.php'; // Load config for ADMIN_PASSWORD

// --- CONFIGURATION ---
// Use password from config.php, or fallback if missing
$admin_password = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'This1sDef4ult!1234';
// -------------------

// Handle login attempt
if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['loggedin'] = true;
        $_SESSION['last_activity'] = time(); // Set initial activity time
        header('Location: dashboard.php'); // Redirect to remove POST data from URL
        exit;
    } else {
        $login_error = 'Incorrect password.';
    }
}

// Determine which page to show, default to 'news'
$page = $_GET['page'] ?? 'news';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/ui/trumbowyg.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/plugins/colors/ui/trumbowyg.colors.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background-color: #f0f2f5; margin: 20px; color: #333; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
        h1, h2 { text-align: center; }
        .admin-menu { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
        .admin-menu a { text-decoration: none; color: #007bff; font-weight: bold; padding: 8px 15px; border-radius: 5px; transition: background 0.2s; }
        .admin-menu a:hover { background-color: #e2e6ea; }
        .admin-menu a.active { background-color: #007bff; color: white; }
        form { display: flex; flex-direction: column; gap: 10px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 30px; }
        label { font-weight: bold; }
        input[type="text"], input[type="url"], input[type="password"], input[type="number"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        .button { background-color: #007bff; color: white; border: none; padding: 12px; border-radius: 4px; font-size: 1em; cursor: pointer; }
        .button.edit { background-color: #ffc107; color: black; }
        .button.delete { background-color: #dc3545; font-size: 0.8em; padding: 5px 10px; }
        .news-list { list-style: none; padding: 0; }
        .news-item { background-color: #f9f9f9; border: 1px solid #eee; padding: 15px; border-radius: 5px; margin-bottom: 10px; }
        .news-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; position: relative; padding-right: 20px; }
        .news-header h3 { margin: 0; } .news-header .post-date { font-size: 0.8em; color: #666; }
        .news-header::after { content: '+'; position: absolute; right: 0; top: 50%; transform: translateY(-50%); font-size: 1.5em; color: #aaa; font-weight: bold; }
        .news-item.active .news-header::after { content: '−'; }
        .collapsible-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .news-item.active .collapsible-content { max-height: 10000px; transition: max-height 0.5s ease-in; padding-top: 15px; }
        .news-content { margin-bottom: 10px; word-wrap: break-word; overflow-wrap: break-word; }
        .news-actions { display: flex; gap: 10px; }
        .error { color: red; text-align: center; }
        .success { color: green; text-align: center; }
        /* SQL Checker Styles */
        .check-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .status-badge.pending { background: #eee; color: #555; }
        .status-badge.success { background: #d4edda; color: #155724; }
        .status-badge.error { background: #f8d7da; color: #721c24; }
        .check-list { list-style: none; padding: 0; margin-top: 10px; }
        .check-list li { margin-bottom: 5px; padding: 5px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        <?php if ($is_logged_in): ?>
            <div class="admin-menu">
                <a href="?page=news" class="<?php echo $page === 'news' ? 'active' : ''; ?>">News</a>
                <a href="?page=links" class="<?php echo $page === 'links' ? 'active' : ''; ?>">Links</a>
                <a href="?page=wallpaper" class="<?php echo $page === 'wallpaper' ? 'active' : ''; ?>">Wallpaper</a>
                <a href="?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">Settings</a>
                <a href="?page=database" class="<?php echo $page === 'database' ? 'active' : ''; ?>">Database</a>
                <a href="?page=sqlcheck" class="<?php echo $page === 'sqlcheck' ? 'active' : ''; ?>">SQL Checker</a>
                <a href="?page=security" class="<?php echo $page === 'security' ? 'active' : ''; ?>">Security</a>
                <a href="../index.html" target="_blank" rel="noopener noreferrer">View Site</a>
            </div>

            <?php if (isset($_GET['status'])) echo '<p class="success">'.htmlspecialchars($_GET['status']).'</p>'; ?>

            <?php 
            switch ($page) {
                case 'news': include 'dashboard-news.php'; break;
                case 'links': include 'dashboard-links.php'; break;
                case 'wallpaper': include 'dashboard-wallpaper.php'; break;
                case 'settings': include 'dashboard-settings.php'; break;
                case 'database': include 'dashboard-database.php'; break;
                case 'sqlcheck': include 'dashboard-sql-check.php'; break;
                case 'security': include 'dashboard-security.php'; break;
                default: include 'dashboard-news.php'; break;
            }
            ?>

            <a href="?logout=true" style="text-align:center; display:block; margin-top:20px; color:#dc3545;">Logout</a>
        <?php else: ?>
            <form action="dashboard.php" method="POST">
                <h2>Login</h2>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <?php if (isset($login_error)) echo '<p class="error">'.$login_error.'</p>'; ?>
                <?php if (isset($_GET['status']) && $_GET['status'] === 'session_expired') echo '<p class="error">Your session has expired. Please log in again.</p>'; ?>
                <button type="submit" class="button">Login</button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php if ($is_logged_in && $page === 'news'): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/trumbowyg.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/plugins/colors/trumbowyg.colors.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#details-editor').trumbowyg({
                btns: [['viewHTML'], ['undo', 'redo'], ['formatting'], ['strong', 'em', 'del'], ['foreColor', 'backColor'], ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'], ['unorderedList', 'orderedList'], ['horizontalRule'], ['removeformat'], ['fullscreen']],
                semantic: false,
                removeformatPasted: false
            });
            $('.news-header').on('click', function() { $(this).closest('.news-item').toggleClass('active'); });
        });
    </script>
    <?php endif; ?>
</body>
</html>