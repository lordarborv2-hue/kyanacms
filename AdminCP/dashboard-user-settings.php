<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);

$u_dash = $settings['user_dashboard'] ?? [];
$en_webshop = $u_dash['enable_webshop'] ?? true; 
$en_reset = $u_dash['enable_reset'] ?? false;
$en_stats = $u_dash['enable_reset_stats'] ?? false;
$en_pk = $u_dash['enable_clear_pk'] ?? false;
$en_master = $u_dash['enable_reset_master'] ?? false;
$en_unstuck = $u_dash['enable_unstuck'] ?? false;

$rates = $settings['conversion_rates'] ?? ['wcoinc' => 1, 'wcoinp' => 1, 'goblin' => 1]; 

// Fallback logic for older settings
$ws_mid = $settings['webshop']['mid_rate'] ?? $settings['webshop'] ?? [];
$ws_hard = $settings['webshop']['hard_rate'] ?? $settings['webshop'] ?? [];
?>

<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_user_dashboard">
    <h2>User Dashboard Settings</h2>
    
    <div style="background:#f9f9f9; padding:20px; border:1px solid #ddd; border-radius:5px;">
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold; color:#007bff;">Webshop Tab:</label>
            <input type="checkbox" id="en_webshop" name="enable_webshop" <?php echo $en_webshop ? 'checked' : ''; ?>>
            <label for="en_webshop">Enable</label>
        </div>
        <hr>
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Reset Character:</label>
            <input type="checkbox" id="en_reset" name="enable_reset" <?php echo $en_reset ? 'checked' : ''; ?>>
            <label for="en_reset">Enable</label>
        </div>
        <hr>
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Reset Stats:</label>
            <input type="checkbox" id="en_stats" name="enable_reset_stats" <?php echo $en_stats ? 'checked' : ''; ?>>
            <label for="en_stats">Enable</label>
        </div>
        <hr>
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Clear PK:</label>
            <input type="checkbox" id="en_pk" name="enable_clear_pk" <?php echo $en_pk ? 'checked' : ''; ?>>
            <label for="en_pk">Enable</label>
        </div>
        <hr>
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Reset Master ML:</label>
            <input type="checkbox" id="en_master" name="enable_reset_master" <?php echo $en_master ? 'checked' : ''; ?>>
            <label for="en_master">Enable</label>
        </div>
        <hr>
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Unstuck Character:</label>
            <input type="checkbox" id="en_unstuck" name="enable_unstuck" <?php echo $en_unstuck ? 'checked' : ''; ?>>
            <label for="en_unstuck">Enable</label>
        </div>
        <hr>

        <h3 style="margin-bottom: 5px;">Currency Conversion Rates</h3>
        <div style="display:flex; gap:15px; margin-bottom:15px;">
            <div style="flex:1;"><label>WCoinC per 1 Credit:</label><input type="number" name="rate_wcoinc" value="<?php echo (int)$rates['wcoinc']; ?>" min="1" required style="width:100%; padding:8px;"></div>
            <div style="flex:1;"><label>WCoinP per 1 Credit:</label><input type="number" name="rate_wcoinp" value="<?php echo (int)$rates['wcoinp']; ?>" min="1" required style="width:100%; padding:8px;"></div>
            <div style="flex:1;"><label>GoblinPoint per 1 Credit:</label><input type="number" name="rate_goblin" value="<?php echo (int)$rates['goblin']; ?>" min="1" required style="width:100%; padding:8px;"></div>
        </div>
    </div>
    <button type="submit" class="button" style="margin-top:20px;">Save Dashboard Settings</button>
</form>

<hr style="border: 0; border-top: 2px solid #ddd; margin: 30px 0;">

<div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
    <h3>Manage User WebCredits</h3>
    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #007bff;">
        <strong>Current User Balance:</strong> <span id="current-balance-display" style="color: #28a745; font-weight: bold;">Enter Username...</span>
    </div>
    <form action="actions/manage-settings.php" method="POST">
        <input type="hidden" name="action" value="manage_user_credits">
        <label style="font-weight:bold;">Select Server:</label>
        <select name="server_select" id="server_select" onchange="lookupUserCredits(document.getElementById('target_user').value)" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:4px;">
            <option value="mid">Server 1 (<?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?>)</option>
            <option value="hard">Server 2 (<?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?>)</option>
        </select>
        <label style="font-weight:bold;">Account ID (Username):</label>
        <input type="text" id="target_user" name="target_user" placeholder="Enter Account ID" required oninput="lookupUserCredits(this.value)" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:4px;">
        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;"><label style="font-weight:bold;">Action:</label><select name="operation" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"><option value="add">Add (+)</option><option value="minus">Subtract (-)</option><option value="set">Set Exact Amount</option></select></div>
            <div style="flex: 1;"><label style="font-weight:bold;">Amount:</label><input type="number" name="credit_amount" value="0" min="0" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
        </div>
        <button type="submit" class="button" style="margin-top:20px; background:#28a745; width: 100%;">Update Credits</button>
    </form>
</div>

<hr style="border: 0; border-top: 2px solid #ddd; margin: 30px 0;">

<div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius: 8px;">
    <h3>Upload Webshop Files</h3>
    <p style="color:#666; margin-bottom: 15px;">Upload your Server's text files to automatically map out item sizes, Socket limits, 380 options, and Ancient items.</p>
    
    <form action="actions/manage-settings.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_item_txt">
        
        <label style="font-weight:bold;">Target Database:</label>
        <select name="upload_target" style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ccc; border-radius:4px;">
            <option value="both">Upload to BOTH Servers</option>
            <option value="mid_rate">Server 1 Only</option>
            <option value="hard_rate">Server 2 Only</option>
        </select>

        <div style="background:#f4f4f4; padding:15px; border-radius:5px; margin-bottom:15px; border:1px solid #ddd;">
            <label style="font-weight:bold; color:#d9534f;">1. Item.txt (REQUIRED)</label>
            <input type="file" name="item_txt" accept=".txt" required style="margin-bottom: 15px; display:block;">

            <label style="font-weight:bold; color:#0275d8;">2. SocketItemType.txt</label>
            <input type="file" name="socket_txt" accept=".txt" style="margin-bottom: 15px; display:block;">

            <label style="font-weight:bold; color:#5cb85c;">3. 380ItemType.txt</label>
            <input type="file" name="380_txt" accept=".txt" style="margin-bottom: 15px; display:block;">

            <label style="font-weight:bold; color:#f0ad4e;">4. SetItemType.txt (Maps Ancient Items)</label>
            <input type="file" name="ancient_txt" accept=".txt" style="margin-bottom: 15px; display:block;">

            <label style="font-weight:bold; color:#8e44ad;">5. SetItemOption.txt (Grabs Ancient Names)</label>
            <input type="file" name="anc_opt_txt" accept=".txt" style="display:block;">
        </div>

        <button type="submit" class="button" style="background:#007bff; width:100%;">Parse & Upload All Files</button>
    </form>

    <hr style="margin: 20px 0;">

    <form action="actions/manage-settings.php" method="POST" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="save_webshop_prices">
        <h3 style="margin-bottom: 5px;">Webshop Global Pricing</h3>
        
        <h4 style="margin:10px 0 5px 0; color:#007bff;">Server 1 (<?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?>) Multipliers</h4>
        <div style="display:flex; gap:10px; flex-wrap: wrap; background:#f4f4f4; padding:10px; border-radius:4px; border:1px solid #ddd;">
            <div style="flex:1; min-width: 100px;"><label>Per Level:</label><input type="number" name="mid_price_level" value="<?php echo $ws_mid['price_level'] ?? 10; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Per Exc:</label><input type="number" name="mid_price_exc" value="<?php echo $ws_mid['price_exc'] ?? 50; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Luck/Skill:</label><input type="number" name="mid_price_luck_skill" value="<?php echo $ws_mid['price_luck_skill'] ?? 25; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>380 Opt:</label><input type="number" name="mid_price_380" value="<?php echo $ws_mid['price_380'] ?? 100; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Harmony:</label><input type="number" name="mid_price_harmony" value="<?php echo $ws_mid['price_harmony'] ?? 100; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Per Socket:</label><input type="number" name="mid_price_socket" value="<?php echo $ws_mid['price_socket'] ?? 50; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label style="color:#28a745;">Ancient:</label><input type="number" name="mid_price_ancient" value="<?php echo $ws_mid['price_ancient'] ?? 100; ?>" required></div>
        </div>

        <h4 style="margin:15px 0 5px 0; color:#dc3545;">Server 2 (<?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?>) Multipliers</h4>
        <div style="display:flex; gap:10px; flex-wrap: wrap; background:#f4f4f4; padding:10px; border-radius:4px; border:1px solid #ddd;">
            <div style="flex:1; min-width: 100px;"><label>Per Level:</label><input type="number" name="hard_price_level" value="<?php echo $ws_hard['price_level'] ?? 10; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Per Exc:</label><input type="number" name="hard_price_exc" value="<?php echo $ws_hard['price_exc'] ?? 50; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Luck/Skill:</label><input type="number" name="hard_price_luck_skill" value="<?php echo $ws_hard['price_luck_skill'] ?? 25; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>380 Opt:</label><input type="number" name="hard_price_380" value="<?php echo $ws_hard['price_380'] ?? 100; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Harmony:</label><input type="number" name="hard_price_harmony" value="<?php echo $ws_hard['price_harmony'] ?? 100; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label>Per Socket:</label><input type="number" name="hard_price_socket" value="<?php echo $ws_hard['price_socket'] ?? 50; ?>" required></div>
            <div style="flex:1; min-width: 100px;"><label style="color:#28a745;">Ancient:</label><input type="number" name="hard_price_ancient" value="<?php echo $ws_hard['price_ancient'] ?? 100; ?>" required></div>
        </div>

        <button type="submit" class="button" style="margin-top:15px; background:#28a745;">Save All Pricing</button>
    </form>

    <hr style="margin: 20px 0;">

    <h3 style="margin-top: 20px;">Manage Specific Items (Base Price & Limits)</h3>
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label style="font-weight:bold;">Select Target Server:</label>
            <select id="admin-item-server" onchange="loadAdminCategory()" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
                <option value="mid_rate">Server 1 (<?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?>)</option>
                <option value="hard_rate">Server 2 (<?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?>)</option>
            </select>
        </div>
        <div style="flex: 1;">
            <label style="font-weight:bold;">Select Item Category:</label>
            <select id="admin-category-select" onchange="loadAdminCategory()" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
                <option value="">-- Select Category --</option>
                <option value="0">0 - Swords</option>
                <option value="1">1 - Axes</option>
                <option value="2">2 - Maces & Scepters</option>
                <option value="3">3 - Spears</option>
                <option value="4">4 - Bows & Crossbows</option>
                <option value="5">5 - Staffs</option>
                <option value="6">6 - Shields</option>
                <option value="7">7 - Helms</option>
                <option value="8">8 - Armors</option>
                <option value="9">9 - Pants</option>
                <option value="10">10 - Gloves</option>
                <option value="11">11 - Boots</option>
                <option value="12">12 - Wings, Orbs, Seeds</option>
                <option value="13">13 - Pets, Rings, Tickets</option>
                <option value="14">14 - Pendants & Jewels</option>
                <option value="15">15 - Scrolls & Spells</option>
            </select>
        </div>
    </div>
    
    <div id="admin-item-results" style="max-height: 500px; overflow-y: auto; background: #f9f9f9; border: 1px solid #ccc; padding: 10px; display: none;"></div>
</div>

<script>
let lookupTimeout;
function lookupUserCredits(username) {
    clearTimeout(lookupTimeout);
    const display = document.getElementById('current-balance-display');
    const server = document.getElementById('server_select').value; 
    
    if (username.length < 3) {
        display.textContent = "Enter Username...";
        display.style.color = "#28a745";
        return;
    }
    display.textContent = "Searching...";
    display.style.color = "#666";
    
    lookupTimeout = setTimeout(() => {
        fetch(`actions/manage-settings.php?action=lookup_credits&server=${server}&user=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    display.textContent = data.credits + " Credits";
                    display.style.color = "#28a745";
                } else {
                    display.textContent = "0 Credits (Not Found)";
                    display.style.color = "#dc3545";
                }
            }).catch(() => { display.textContent = "Error searching."; display.style.color = "#dc3545"; });
    }, 500);
}

function loadAdminCategory() {
    const server = document.getElementById('admin-item-server').value;
    const cat = document.getElementById('admin-category-select').value;
    const resultsDiv = document.getElementById('admin-item-results');
    
    if (cat === "") { resultsDiv.style.display = 'none'; return; }
    
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = 'Loading category items from ' + server + '...';
    
    fetch(`actions/manage-settings.php?action=load_category_items&server=${server}&cat=${cat}`)
        .then(res => res.text())
        .then(html => { resultsDiv.innerHTML = html; })
        .catch(() => resultsDiv.innerHTML = 'Error loading items.');
}

function updateItemData(type, index, column, element) {
    const server = document.getElementById('admin-item-server').value;
    const val = element.type === 'checkbox' ? (element.checked ? 1 : 0) : element.value;
    element.style.borderColor = "#eebb00"; 
    fetch(`actions/manage-settings.php?action=update_item_data&server=${server}&type=${type}&index=${index}&col=${column}&val=${val}`)
        .then(() => { element.style.borderColor = "#28a745"; setTimeout(()=>element.style.borderColor="", 1000); });
}
</script>