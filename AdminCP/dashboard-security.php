<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_security">
    <h2>Security Settings</h2>
    <label for="timeout">Session Timeout (minutes):</label>
    <p>Automatically log out after this many minutes of inactivity.</p>
    <input type="number" id="timeout" name="session_timeout_minutes" value="<?php echo htmlspecialchars($settings['security']['session_timeout_minutes']); ?>" required>
    <button type="submit" class="button" style="margin-top:15px;">Save Security Settings</button>
</form>