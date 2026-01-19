<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);

// Default visibility to true if not set
$mid_visible = $settings['mid_rate_server']['visible'] ?? true;
$hard_visible = $settings['hard_rate_server']['visible'] ?? true;
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
    <p>Configure which servers are displayed on the homepage.</p>

    <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px;">
        <h5 style="margin-top:0;">Server 1 (Left Side)</h5>
        
        <label>Visibility on Homepage:</label>
        <div style="margin-bottom:15px;">
            <input type="radio" id="mid_vis_on" name="mid_visible" value="1" <?php echo $mid_visible ? 'checked' : ''; ?>>
            <label for="mid_vis_on" style="font-weight:normal; margin-right:15px;">Show</label>
            
            <input type="radio" id="mid_vis_off" name="mid_visible" value="0" <?php echo !$mid_visible ? 'checked' : ''; ?>>
            <label for="mid_vis_off" style="font-weight:normal;">Hide</label>
        </div>

        <label for="mid_name">Server Name:</label> 
        <input type="text" id="mid_name" name="mid_name" value="<?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?>" required>
        
        <label for="mid_address">Address (IP or Domain):</label>
        <input type="text" id="mid_address" name="mid_address" value="<?php echo htmlspecialchars($settings['mid_rate_server']['address']); ?>" required>
        
        <label for="mid_port">Port:</label>
        <input type="number" id="mid_port" name="mid_port" value="<?php echo htmlspecialchars($settings['mid_rate_server']['port']); ?>" required>
    </div>

    <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:5px;">
        <h5 style="margin-top:0;">Server 2 (Right Side)</h5>
        
        <label>Visibility on Homepage:</label>
        <div style="margin-bottom:15px;">
            <input type="radio" id="hard_vis_on" name="hard_visible" value="1" <?php echo $hard_visible ? 'checked' : ''; ?>>
            <label for="hard_vis_on" style="font-weight:normal; margin-right:15px;">Show</label>
            
            <input type="radio" id="hard_vis_off" name="hard_visible" value="0" <?php echo !$hard_visible ? 'checked' : ''; ?>>
            <label for="hard_vis_off" style="font-weight:normal;">Hide</label>
        </div>

        <label for="hard_name">Server Name:</label> 
        <input type="text" id="hard_name" name="hard_name" value="<?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?>" required>
        
        <label for="hard_address">Address (IP or Domain):</label>
        <input type="text" id="hard_address" name="hard_address" value="<?php echo htmlspecialchars($settings['hard_rate_server']['address']); ?>" required>
        
        <label for="hard_port">Port:</label>
        <input type="number" id="hard_port" name="hard_port" value="<?php echo htmlspecialchars($settings['hard_rate_server']['port']); ?>" required>
    </div>

    <button type="submit" class="button" style="margin-top:20px;">Save Site Settings</button>
</form>