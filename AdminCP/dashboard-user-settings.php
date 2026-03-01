<?php
$admin_srv = $_SESSION['admin_server'] ?? 'mid';
$server_key = ($admin_srv === 'hard') ? 'hard_rate' : 'mid_rate';
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
$server_label = ($server_key === 'mid_rate') ? ($settings['server_names']['mid_rate'] ?? 'Mid Rate') : ($settings['server_names']['hard_rate'] ?? 'Hard Rate');

$dash_settings = $settings['user_dashboard'][$server_key] ?? [
    'enable_reset' => true, 'enable_reset_stats' => true, 'enable_clear_pk' => true,
    'enable_reset_master' => true, 'enable_unstuck' => true, 'enable_webshop' => true
];

$prices = $settings['webshop'][$server_key] ?? [
    'price_level' => 10, 'price_exc' => 50, 'price_luck_skill' => 25, 
    'price_380' => 100, 'price_harmony' => 100, 'price_socket' => 50, 'price_ancient' => 100
];

$rates = $settings['conversion_rates'] ?? ['wcoinc' => 1, 'wcoinp' => 1, 'goblin' => 1];
?>

<div style="background: #007bff; color: white; padding: 12px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-size: 1.2em; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    Currently Editing: <?php echo htmlspecialchars($server_label); ?>
</div>

<h2 style="margin-bottom: 5px;">User Dashboard & Webshop Settings</h2>

<?php if(isset($_GET['success'])): ?>
    <div style="background:#d4edda; color:#155724; padding:10px; border-radius:4px; margin-bottom:15px; text-align:center; font-weight:bold;">
        Settings Updated for <?php echo htmlspecialchars($server_label); ?>!
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
    
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <form action="actions/manage-settings.php" method="POST" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd; margin:0;">
            <input type="hidden" name="action" value="save_user_settings">
            <h3 style="margin-top:0;">Enable/Disable Features</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <label><input type="checkbox" name="enable_reset" <?php echo !empty($dash_settings['enable_reset']) ? 'checked' : ''; ?>> Character Reset</label>
                <label><input type="checkbox" name="enable_reset_stats" <?php echo !empty($dash_settings['enable_reset_stats']) ? 'checked' : ''; ?>> Reset Stats</label>
                <label><input type="checkbox" name="enable_clear_pk" <?php echo !empty($dash_settings['enable_clear_pk']) ? 'checked' : ''; ?>> Clear PK</label>
                <label><input type="checkbox" name="enable_reset_master" <?php echo !empty($dash_settings['enable_reset_master']) ? 'checked' : ''; ?>> Reset Master Level</label>
                <label><input type="checkbox" name="enable_unstuck" <?php echo !empty($dash_settings['enable_unstuck']) ? 'checked' : ''; ?>> Unstuck</label>
            </div>

            <div style="margin-top: 15px; padding: 15px; background: #e8f5e9; border: 1px solid #c3e6cb; border-radius: 5px;">
                <label style="color:#155724; font-weight:bold; font-size: 1.1em; cursor: pointer;">
                    <input type="checkbox" name="enable_webshop" <?php echo !empty($dash_settings['enable_webshop']) ? 'checked' : ''; ?> style="transform: scale(1.2); margin-right: 10px;"> 
                    Enable Webshop Tab
                </label>
            </div>

            <hr style="border: 1px solid #ddd; margin: 15px 0;">
            <h4 style="margin-top:0;">Credit Conversion Rates (Global)</h4>
            <div style="display: flex; gap: 10px;">
                <div><label>WCoinC:</label><input type="number" name="rate_wcoinc" value="<?php echo $rates['wcoinc']; ?>" style="width:100%;"></div>
                <div><label>WCoinP:</label><input type="number" name="rate_wcoinp" value="<?php echo $rates['wcoinp']; ?>" style="width:100%;"></div>
                <div><label>Goblin Point:</label><input type="number" name="rate_goblin" value="<?php echo $rates['goblin']; ?>" style="width:100%;"></div>
            </div>

            <button type="submit" class="button" style="width:100%; margin-top:15px;">Save Dashboard Features</button>
        </form>

        <form action="actions/manage-settings.php" method="POST" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd; margin:0;">
            <input type="hidden" name="action" value="manage_user_credits">
            <h3 style="margin-top:0;">Manage User Credits</h3>
            <p style="font-size:0.9em; color:#666; margin-top:-5px;">Targeting: <strong><?php echo htmlspecialchars($server_label); ?></strong></p>

            <div class="form-group" style="display: flex; gap: 10px; margin-bottom: 10px;">
                <input type="text" id="target_user" name="target_user" placeholder="Account ID" required style="flex: 2;">
                <button type="button" class="button edit" style="flex: 1;" onclick="lookupCredits()">Check</button>
            </div>
            
            <div id="credit_result" style="margin-bottom:10px; font-weight:bold; color:#007bff;"></div>
            
            <div class="form-group" style="display: flex; gap: 10px;">
                <select name="operation" style="flex: 1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="add">Add (+)</option>
                    <option value="minus">Remove (-)</option>
                    <option value="set">Set Exact (=)</option>
                </select>
                <input type="number" name="credit_amount" placeholder="Amount" required style="flex: 1;">
            </div>

            <button type="submit" class="button" style="width:100%; background:#28a745; margin-top:10px;">Update Credits</button>
        </form>
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px;">
        <form action="actions/manage-settings.php" method="POST" enctype="multipart/form-data" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd; margin:0;">
            <input type="hidden" name="action" value="upload_item_txt">
            <h3 style="margin-top:0;">Upload Item.txt</h3>
            
            <div class="form-group" style="margin-bottom: 10px;">
                <label>Target Database:</label>
                <select name="upload_target" style="width:100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="<?php echo $server_key; ?>">Only <?php echo htmlspecialchars($server_label); ?></option>
                    <option value="both">Both Servers (Sync)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
                <label>Item.txt (Required):</label>
                <input type="file" name="item_txt" accept=".txt" required style="width: 100%;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                <div class="form-group"><label>SocketItem.txt:</label><input type="file" name="socket_txt" accept=".txt" style="width: 100%;"></div>
                <div class="form-group"><label>Item380.txt:</label><input type="file" name="380_txt" accept=".txt" style="width: 100%;"></div>
                <div class="form-group"><label>AncientOption.txt:</label><input type="file" name="anc_opt_txt" accept=".txt" style="width: 100%;"></div>
                <div class="form-group"><label>ItemSetType.txt:</label><input type="file" name="ancient_txt" accept=".txt" style="width: 100%;"></div>
            </div>

            <button type="submit" class="button" style="width:100%; background:#dc3545;">Upload & Overwrite DB</button>
        </form>

        <form action="actions/manage-settings.php" method="POST" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd; margin:0;">
            <input type="hidden" name="action" value="save_webshop_prices">
            <h3 style="margin-top:0;">Webshop Base Prices</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: left;">
                <div class="form-group"><label>Per Item Level (+1):</label><input type="number" name="price_level" value="<?php echo $prices['price_level']; ?>"></div>
                <div class="form-group"><label>Per Excellent Option:</label><input type="number" name="price_exc" value="<?php echo $prices['price_exc']; ?>"></div>
                <div class="form-group"><label>Luck or Skill Add:</label><input type="number" name="price_luck_skill" value="<?php echo $prices['price_luck_skill']; ?>"></div>
                <div class="form-group"><label>380 PvP Option:</label><input type="number" name="price_380" value="<?php echo $prices['price_380']; ?>"></div>
                <div class="form-group"><label>Harmony Option:</label><input type="number" name="price_harmony" value="<?php echo $prices['price_harmony']; ?>"></div>
                <div class="form-group"><label>Per Empty Socket:</label><input type="number" name="price_socket" value="<?php echo $prices['price_socket']; ?>"></div>
                <div class="form-group" style="grid-column: span 2;"><label>Ancient Option:</label><input type="number" name="price_ancient" value="<?php echo $prices['price_ancient']; ?>"></div>
            </div>

            <button type="submit" class="button" style="width:100%; margin-top:15px;">Save Prices for <?php echo htmlspecialchars($server_label); ?></button>
        </form>
    </div>
</div>
<div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; width: 100%; box-sizing: border-box; clear: both;">
    <h3 style="margin-top:0;">Manage Individual Items (<?php echo htmlspecialchars($server_label); ?>)</h3>
    <div class="form-group" style="max-width: 400px; margin-bottom: 15px;">
        <label>Select Category:</label>
        <select id="shop_category" onchange="loadCategoryItems()" style="width:100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
            <option value="">-- Select Category --</option>
            <option value="0">Swords</option><option value="1">Axes</option><option value="2">Maces</option>
            <option value="3">Spears</option><option value="4">Bows & Crossbows</option><option value="5">Staffs</option>
            <option value="6">Shields</option><option value="7">Helms</option><option value="8">Armors</option>
            <option value="9">Pants</option><option value="10">Gloves</option><option value="11">Boots</option>
            <option value="12">Wings & Orbs</option><option value="13">Pets & Rings</option><option value="14">Pendants</option>
            <option value="15">Scrolls</option>
        </select>
    </div>
    
    <div id="item_list_container" style="overflow-x: auto; width: 100%;">
        <p style="color:#666;">Select a category above to view and edit items.</p>
    </div>
</div>

<script>
async function lookupCredits() {
    const user = document.getElementById('target_user').value;
    const resDiv = document.getElementById('credit_result');
    if (!user) { resDiv.textContent = "Please enter an Account ID"; return; }
    
    resDiv.innerHTML = "Looking up...";
    try {
        // Added Cache-Buster Timestamp
        const response = await fetch(`actions/manage-settings.php?action=lookup_credits&user=${user}&t=${new Date().getTime()}`);
        const data = await response.json();
        
        if (data.success) {
            resDiv.innerHTML = `Current Credits for ${user}: <span style="color:#28a745; font-size:1.2em;">${data.credits}</span>`;
        } else {
            resDiv.innerHTML = `<span style="color:red;">Account not found on this server.</span>`;
        }
    } catch (e) {
        resDiv.innerHTML = "Error connecting to server.";
    }
}

function loadCategoryItems() {
    const cat = document.getElementById('shop_category').value;
    const container = document.getElementById('item_list_container');
    
    if (cat === '') { container.innerHTML = ''; return; }
    container.innerHTML = 'Loading items...';
    
    // Added Cache-Buster Timestamp
    fetch(`actions/manage-settings.php?action=load_category_items&cat=${cat}&t=${new Date().getTime()}`)
        .then(res => res.text())
        .then(html => { container.innerHTML = html; });
}

function updateItemData(type, index, col, element) {
    const val = (element.type === 'checkbox') ? (element.checked ? 1 : 0) : element.value;
    
    // Added Cache-Buster Timestamp
    fetch(`actions/manage-settings.php?action=update_item_data&type=${type}&index=${index}&col=${col}&val=${val}&t=${new Date().getTime()}`)
        .then(() => {
            const originalBg = element.style.backgroundColor;
            element.style.backgroundColor = '#d4edda';
            setTimeout(() => { element.style.backgroundColor = originalBg; }, 500);
        });
}
</script>