<?php
$server_key = (isset($_SESSION['admin_server']) && $_SESSION['admin_server'] === 'hard') ? 'hard_rate' : 'mid_rate';
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
$server_label = ($server_key === 'mid_rate') ? ($settings['server_names']['mid_rate'] ?? 'Mid Rate') : ($settings['server_names']['hard_rate'] ?? 'Hard Rate');

$paypal = $settings['paypal'][$server_key] ?? ['enabled' => false, 'client_id' => '', 'secret' => '', 'rate' => 100, 'currency' => 'USD', 'mode' => 'sandbox'];
$qr_ph = $settings['qr_ph'][$server_key] ?? ['enabled' => false, 'ratio' => 100];
$paymongo = $settings['paymongo'][$server_key] ?? ['enabled' => false, 'public_key' => '', 'secret_key' => '', 'rate' => 100];
$qr_image_name = "uploads/qr-ph/{$server_key}.png";
?>

<div style="background: #007bff; color: white; padding: 12px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-size: 1.2em; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    Currently Editing: <?php echo htmlspecialchars($server_label); ?>
</div>

<h2>Donation Settings</h2>
<p style="text-align:center; color:#666;">Set your rates and payment methods for <strong><?php echo htmlspecialchars($server_label); ?></strong>.</p>

<?php if(isset($_GET['success'])): ?>
    <div style="background:#d4edda; color:#155724; padding:10px; border-radius:4px; margin-bottom:15px; text-align:center; font-weight:bold;">
        Settings Saved Successfully for <?php echo htmlspecialchars($server_label); ?>!
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
        <h3 style="margin-top:0; color: #007bff;">PayPal Checkout</h3>
        <form action="actions/manage-settings.php" method="POST">
            <input type="hidden" name="action" value="save_paypal_settings">
            
            <label style="display:block; margin-bottom:10px; font-weight: bold;">
                <input type="checkbox" name="paypal_enabled" <?php echo ($paypal['enabled'] ?? false) ? 'checked' : ''; ?>> Enable PayPal Donations
            </label>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">PayPal Mode:</label>
                <select name="paypal_mode" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="sandbox" <?php echo ($paypal['mode'] ?? '') == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                    <option value="live" <?php echo ($paypal['mode'] ?? '') == 'live' ? 'selected' : ''; ?>>Live (Real Money)</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">Client ID:</label>
                <input type="text" name="paypal_client_id" value="<?php echo htmlspecialchars($paypal['client_id'] ?? ''); ?>" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">Secret Key:</label>
                <input type="password" name="paypal_secret" value="<?php echo htmlspecialchars($paypal['secret'] ?? ''); ?>" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">Currency Code:</label>
                <input type="text" name="paypal_currency" value="<?php echo htmlspecialchars($paypal['currency'] ?? 'USD'); ?>" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">Credits per 1 <?php echo htmlspecialchars($paypal['currency'] ?? 'USD'); ?>:</label>
                <input type="number" name="paypal_rate" value="<?php echo $paypal['rate'] ?? 100; ?>" step="1" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <button type="submit" class="button" style="width:100%; background: #007bff; color: white;">Save PayPal Config</button>
        </form>
    </div>

    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
        <h3 style="margin-top:0; color: #f1c40f;">QR Ph / Maya</h3>
        <form action="actions/manage-settings.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_qr_settings">
            
            <label style="display:block; margin-bottom:10px; font-weight: bold;">
                <input type="checkbox" name="qr_enabled" <?php echo ($qr_ph['enabled'] ?? false) ? 'checked' : ''; ?>> Enable QR Ph Donation Tab
            </label>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">QR Ph Ratio (WebCredits per 1 PHP):</label>
                <input type="number" name="qr_ratio" value="<?php echo $qr_ph['ratio'] ?? 100; ?>" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="text-align:center; padding: 15px; background: #fff; border: 1px dashed #ccc; margin-bottom: 15px;">
    <p style="margin:0; font-weight:bold;">QR Code Image 
        <span style="font-size:0.8em; color:#666;">(<?php echo htmlspecialchars($server_label); ?>)</span>:
    </p>
    <img src="../uploads/qr-ph/<?php echo $server_key; ?>.png?t=<?php echo time(); ?>" 
         alt="QR Ph" 
         style="width:120px; height:120px; margin-top:10px; border: 1px solid #ddd;"
         onerror="this.style.opacity='0.3'; this.title='No QR image uploaded yet for this server.'">
    <p style="font-size:0.75em; color:#999; margin:5px 0 0 0;">
        <?php echo htmlspecialchars($server_label); ?> — 
        <?php echo file_exists('../uploads/qr-ph/' . $server_key . '.png') ? '<span style="color:green;">✔ Image found</span>' : '<span style="color:red;">✘ No image uploaded</span>'; ?>
    </p>
</div>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-weight:bold;">Upload New QR Code (PNG only):</label>
                <input type="file" name="qr_image" accept="image/png" style="padding:5px;">
            </div>
            <button type="submit" class="button" style="width:100%; background: #6c757d; color: white;">Update QR Settings</button>
        </form>
    </div>
	
	<div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; grid-column: span 2;">
        <h3 style="margin-top:0; color: #6f42c1;">PayMongo (Auto GCash, Maya, Cards)</h3>
        <form action="actions/manage-settings.php" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <input type="hidden" name="action" value="save_paymongo_settings">
            
            <div style="grid-column: span 2;">
                <label style="display:block; font-weight: bold; cursor: pointer;">
                    <input type="checkbox" name="paymongo_enabled" <?php echo ($paymongo['enabled'] ?? false) ? 'checked' : ''; ?> style="transform: scale(1.2); margin-right: 8px;"> Enable PayMongo Automated Donations
                </label>
            </div>

            <div class="form-group" style="margin:0;">
                <label style="display:block; font-weight:bold;">Public Key (pk_...):</label>
                <input type="text" name="paymongo_public" value="<?php echo htmlspecialchars($paymongo['public_key'] ?? ''); ?>" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin:0;">
                <label style="display:block; font-weight:bold;">Secret Key (sk_...):</label>
                <input type="password" name="paymongo_secret" value="<?php echo htmlspecialchars($paymongo['secret_key'] ?? ''); ?>" style="width:100%; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="grid-column: span 2; margin:0;">
                <label style="display:block; font-weight:bold;">Credits per 1 PHP (Rate):</label>
                <input type="number" name="paymongo_rate" value="<?php echo $paymongo['rate'] ?? 100; ?>" step="1" style="width:100%; max-width: 200px; padding:8px; border: 1px solid #ccc; border-radius: 4px;">
                <p style="font-size:0.85em; color:#666; margin-top:5px;">Example: If you set 100, 1 PHP = 100 WebCredits.</p>
            </div>

            <div style="grid-column: span 2;">
                <button type="submit" class="button" style="width:100%; background: #6f42c1; color: white;">Save PayMongo Config</button>
            </div>
        </form>
    </div>
</div>