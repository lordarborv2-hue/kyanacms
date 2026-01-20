<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_database">
    <h2>Database Settings</h2>
    <p style="color:red;"><strong>Warning:</strong> Incorrect settings here will break your website.</p>
    
    <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px;">
        <h4 style="margin-top:0;">Server 1 Database (<?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?>)</h4>
        
        <label for="mid_db_host">Host IP:</label>
        <input type="text" id="mid_db_host" name="mid_db_host" value="<?php echo htmlspecialchars($settings['database']['mid_rate']['host']); ?>" required>
        
        <label for="mid_db_name">Database Name:</label>
        <input type="text" id="mid_db_name" name="mid_db_name" value="<?php echo htmlspecialchars($settings['database']['mid_rate']['name'] ?? 'MuOnline'); ?>" placeholder="MuOnline" required>
        
        <label for="mid_db_user">User:</label>
        <input type="text" id="mid_db_user" name="mid_db_user" value="<?php echo htmlspecialchars($settings['database']['mid_rate']['user']); ?>" required>
        
        <label for="mid_db_pass">Password:</label>
        <input type="password" id="mid_db_pass" name="mid_db_pass" placeholder="Leave blank to keep current password">
    </div>

    <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:5px;">
        <h4 style="margin-top:0;">Server 2 Database (<?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?>)</h4>
        
        <label for="hard_db_host">Host IP:</label>
        <input type="text" id="hard_db_host" name="hard_db_host" value="<?php echo htmlspecialchars($settings['database']['hard_rate']['host']); ?>" required>
        
        <label for="hard_db_name">Database Name:</label>
        <input type="text" id="hard_db_name" name="hard_db_name" value="<?php echo htmlspecialchars($settings['database']['hard_rate']['name'] ?? 'MuOnlineEly'); ?>" placeholder="MuOnlineEly" required>
        
        <label for="hard_db_user">User:</label>
        <input type="text" id="hard_db_user" name="hard_db_user" value="<?php echo htmlspecialchars($settings['database']['hard_rate']['user']); ?>" required>
        
        <label for="hard_db_pass">Password:</label>
        <input type="password" id="hard_db_pass" name="hard_db_pass" placeholder="Leave blank to keep current password">
    </div>

    <button type="submit" class="button" style="margin-top:15px;">Save Database Settings</button>
</form>