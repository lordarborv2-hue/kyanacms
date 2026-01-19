<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
$current_wallpaper = $settings['wallpaper_url'] ?? 'uploads/default-wallpaper.jpg';
?>
<style>.wallpaper-preview { max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 5px; margin-top: 10px; }</style>
<form action="actions/manage-wallpaper.php" method="POST" enctype="multipart/form-data">
    <h2>Manage Website Wallpaper</h2>
    <label for="wallpaper_file">Upload New Wallpaper (Recommended: 1920x1080, <5MB):</label>
    <input type="file" id="wallpaper_file" name="wallpaper_file" accept="image/jpeg, image/png, image/gif" required>
    <button type="submit" class="button" style="margin-top:15px;">Upload and Set Wallpaper</button>
    <h3 style="margin-top:30px;">Current Wallpaper:</h3>
    <img src="../<?php echo htmlspecialchars($current_wallpaper); ?>?v=<?php echo time(); ?>" alt="Current Wallpaper" class="wallpaper-preview">
</form>