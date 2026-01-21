<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_security">
    <h2>Security Settings</h2>
    
    <div style="margin-bottom: 20px;">
        <label for="admin_timeout">Admin Panel Timeout (minutes):</label>
        <p style="font-size:0.9em; color:#666; margin-top:5px;">Auto-logout admins after inactivity.</p>
        <input type="number" id="admin_timeout" name="session_timeout_minutes" value="<?php echo htmlspecialchars($settings['security']['session_timeout_minutes'] ?? 30); ?>" required>
    </div>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

    <div style="margin-top: 20px;">
        <label for="user_timeout">User Dashboard Timeout (minutes):</label>
        <p style="font-size:0.9em; color:#666; margin-top:5px;">Auto-logout players from the User Dashboard after inactivity.</p>
        <input type="number" id="user_timeout" name="user_session_timeout_minutes" value="<?php echo htmlspecialchars($settings['security']['user_session_timeout_minutes'] ?? 10); ?>" required>
    </div>

    <button type="submit" class="button" style="margin-top:20px;">Save Security Settings</button>
</form>