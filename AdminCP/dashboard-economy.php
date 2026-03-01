<?php
$server_key = (isset($_SESSION['admin_server']) && $_SESSION['admin_server'] === 'hard') ? 'hard_rate' : 'mid_rate';
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
$server_label = ($server_key === 'mid_rate') ? ($settings['server_names']['mid_rate'] ?? 'Mid Rate') : ($settings['server_names']['hard_rate'] ?? 'Hard Rate');

// Load tracked items specifically for the active server
$tracked_items = $settings['economy_tracking'][$server_key] ?? [];
?>

<div style="background: #007bff; color: white; padding: 12px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-size: 1.2em; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    Currently Editing: <?php echo htmlspecialchars($server_label); ?>
</div>

<h2>Economy Tracker Settings</h2>
<p style="text-align:center; color:#666;">Track the total amount of specific Jewels/Items inside all player CustomJewelBanks on <strong><?php echo htmlspecialchars($server_label); ?></strong>.</p>

<?php if(isset($_GET['success'])): ?>
    <div style="background:#d4edda; color:#155724; padding:10px; border-radius:4px; margin-bottom:15px; text-align:center; font-weight:bold;">
        Economy Tracker Updated for <?php echo htmlspecialchars($server_label); ?>!
    </div>
<?php endif; ?>

<form action="actions/manage-settings.php" method="POST" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd; max-width:700px; margin:auto;">
    <input type="hidden" name="action" value="save_economy">
    
    <table style="width:100%; text-align:left; margin-bottom:15px;">
        <thead>
            <tr>
                <th style="padding-bottom:10px;">Display Name (e.g. Jewel of Bless)</th>
                <th style="padding-bottom:10px;">CustomJewelBank Column Name</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="economy-list">
            <?php foreach ($tracked_items as $name => $col): ?>
            <tr>
                <td><input type="text" name="item_names[]" value="<?php echo htmlspecialchars($name); ?>" required></td>
                <td><input type="text" name="item_cols[]" value="<?php echo htmlspecialchars($col); ?>" required></td>
                <td><button type="button" class="button delete" onclick="this.parentElement.parentElement.remove()">Remove</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button type="button" class="button edit" style="margin-bottom:15px;" onclick="addEconomyRow()">+ Add New Item to Track</button>
    <button type="submit" class="button" style="width:100%;">Save Economy Settings for <?php echo htmlspecialchars($server_label); ?></button>
</form>

<script>
function addEconomyRow() {
    const tbody = document.getElementById('economy-list');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_names[]" placeholder="e.g. Jewel of Chaos" required></td>
        <td><input type="text" name="item_cols[]" placeholder="e.g. Chaos" required></td>
        <td><button type="button" class="button delete" onclick="this.parentElement.parentElement.remove()">Remove</button></td>
    `;
    tbody.appendChild(tr);
}
</script>