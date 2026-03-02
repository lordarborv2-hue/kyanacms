<?php
$settingsPath = '../Configuration/settings.json';
$settings = json_decode(file_get_contents($settingsPath), true);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_economy'])) {
    $newTrackedItems = [];
    
    // Loop through posted arrays
    if(isset($_POST['item_name'])) {
        foreach($_POST['item_name'] as $i => $name) {
            if(!empty($name)) {
                $newTrackedItems[] = [
                    "name" => htmlspecialchars($name),
                    "type" => (int)$_POST['item_type'][$i],
                    "index" => (int)$_POST['item_index'][$i],
                    "bundle" => (int)$_POST['item_bundle'][$i],
                    "col" => htmlspecialchars($_POST['item_col'][$i]) // Leave blank for CustomItemBank items
                ];
            }
        }
    }
    
    $settings['tracked_items'] = $newTrackedItems;
    file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "<div class='alert alert-success'>Economy Tracking Settings Saved!</div>";
}
?>

<div class="card">
    <div class="card-header">Configure Tracked Economy Items</div>
    <div class="card-body">
        <form method="POST">
            <table class="table table-bordered" id="economyTable">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Type (Cat)</th>
                        <th>Index</th>
                        <th>DB Column (JewelBank Only)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($settings['tracked_items'] as $item): ?>
                    <tr>
                        <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($item['name']) ?>"></td>
                        <td><input type="number" name="item_type[]" class="form-control" value="<?= $item['type'] ?>"></td>
                        <td><input type="number" name="item_index[]" class="form-control" value="<?= $item['index'] ?>"></td>
                        <input type="hidden" name="item_bundle[]" value="<?= isset($item['bundle']) ? $item['bundle'] : 0 ?>">
                        <td><input type="text" name="item_col[]" class="form-control" value="<?= htmlspecialchars($item['col'] ?? '') ?>" placeholder="e.g. Bless (Leave blank if CustomItemBank)"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">Remove</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <button type="button" class="btn btn-secondary" onclick="addEconomyRow()">+ Add New Item</button>
            <button type="submit" name="update_economy" class="btn btn-primary float-right">Save Settings</button>
        </form>
    </div>
</div>

<script>
function addEconomyRow() {
    const html = `
        <tr>
            <td><input type="text" name="item_name[]" class="form-control"></td>
            <td><input type="number" name="item_type[]" class="form-control"></td>
            <td><input type="number" name="item_index[]" class="form-control"></td>
            <input type="hidden" name="item_bundle[]" value="0">
            <td><input type="text" name="item_col[]" class="form-control" placeholder="Leave blank if CustomItemBank"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">Remove</button></td>
        </tr>
    `;
    document.querySelector('#economyTable tbody').insertAdjacentHTML('beforeend', html);
}
</script>