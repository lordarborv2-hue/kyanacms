<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_links">
    <h2>Edit Download Links</h2>
    <h4>Download Button 1</h4>
    <label for="label1">Button Text (Label):</label>
    <input type="text" id="label1" name="label1" value="<?php echo htmlspecialchars($settings['download_link_1']['label']); ?>" required>
    <label for="url1">Button URL:</label>
    <input type="url" id="url1" name="url1" value="<?php echo htmlspecialchars($settings['download_link_1']['url']); ?>" required>
    <hr style="margin: 20px 0;">
    <h4>Download Button 2</h4>
    <label for="label2">Button Text (Label):</label>
    <input type="text" id="label2" name="label2" value="<?php echo htmlspecialchars($settings['download_link_2']['label']); ?>" required>
    <label for="url2">Button URL:</label>
    <input type="url" id="url2" name="url2" value="<?php echo htmlspecialchars($settings['download_link_2']['url']); ?>" required>
    <button type="submit" class="button">Save Link Settings</button>
</form>