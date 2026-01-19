<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_database">
    <h2>Database Settings</h2>
    <p style="color:red;"><strong>Warning:</strong> Incorrect settings here will break your website.</p>
    <h4>Mid Rate Server Database</h4>
    <label for="mid_db_host">Host:</label>
    <input type="text" id="mid_db_host" name="mid_db_host" value="<?php echo htmlspecialchars($settings['database']['mid_rate']['host']); ?>" required>
    <label for="mid_db_user">User:</label>
    <input type="text" id="mid_db_user" name="mid_db_user" value="<?php echo htmlspecialchars($settings['database']['mid_rate']['user']); ?>" required>
    <label for="mid_db_pass">Password (enter a new password to change):</label>
    <input type="password" id="mid_db_pass" name="mid_db_pass" placeholder="Leave blank to keep current password">
    <hr style="margin: 20px 0;">
    <h4>Hard Rate Server Database</h4>
    <label for="hard_db_host">Host:</label>
    <input type="text" id="hard_db_host" name="hard_db_host" value="<?php echo htmlspecialchars($settings['database']['hard_rate']['host']); ?>" required>
    <label for="hard_db_user">User:</label>
    <input type="text" id="hard_db_user" name="hard_db_user" value="<?php echo htmlspecialchars($settings['database']['hard_rate']['user']); ?>" required>
    <label for="hard_db_pass">Password (enter a new password to change):</label>
    <input type="password" id="hard_db_pass" name="hard_db_pass" placeholder="Leave blank to keep current password">
    <button type="submit" class="button" style="margin-top:15px;">Save Database Settings</button>
</form>