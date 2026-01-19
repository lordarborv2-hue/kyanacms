<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<form action="actions/manage-settings.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_site_settings">
    <h2>Edit Site Settings</h2>
    <h4>General</h4>
    <label for="website_title">Website Title:</label>
    <input type="text" id="website_title" name="website_title" value="<?php echo htmlspecialchars($settings['website_title']); ?>" required>
    <label for="favicon_file" style="margin-top:15px;">Website Logo (Favicon):</label>
    <input type="file" id="favicon_file" name="favicon_file" accept="image/x-icon, image/png, image/jpeg">
    <p>Current Favicon: <img src="../<?php echo htmlspecialchars($settings['favicon_url']); ?>?v=<?php echo time(); ?>" alt="favicon" style="vertical-align:middle; width:32px; height:32px;"></p>
    <hr style="margin: 20px 0;">
    <h4>Server Configuration</h4>
    <p>Set the display names and connection details for each server.</p>
    <h5 style="margin-top:15px;"><?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?> Server</h5>
    <label for="mid_name">Server Name:</label> 
    <input type="text" id="mid_name" name="mid_name" value="<?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?>" required>
    <label for="mid_address">Address (IP or Domain):</label>
    <input type="text" id="mid_address" name="mid_address" value="<?php echo htmlspecialchars($settings['mid_rate_server']['address']); ?>" required>
    <label for="mid_port">Port:</label>
    <input type="number" id="mid_port" name="mid_port" value="<?php echo htmlspecialchars($settings['mid_rate_server']['port']); ?>" required>
    <h5 style="margin-top:15px;"><?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?> Server</h5>
    <label for="hard_name">Server Name:</label> 
    <input type="text" id="hard_name" name="hard_name" value="<?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?>" required>
    <label for="hard_address">Address (IP or Domain):</label>
    <input type="text" id="hard_address" name="hard_address" value="<?php echo htmlspecialchars($settings['hard_rate_server']['address']); ?>" required>
    <label for="hard_port">Port:</label>
    <input type="number" id="hard_port" name="hard_port" value="<?php echo htmlspecialchars($settings['hard_rate_server']['port']); ?>" required>
    <button type="submit" class="button" style="margin-top:15px;">Save Site Settings</button>
</form>