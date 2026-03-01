<?php
$server_key = (isset($_SESSION['admin_server']) && $_SESSION['admin_server'] === 'hard') ? 'hard_rate' : 'mid_rate';
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
$server_label = ($server_key === 'mid_rate') ? ($settings['server_names']['mid_rate'] ?? 'Mid Rate') : ($settings['server_names']['hard_rate'] ?? 'Hard Rate');
$db_config = $settings['database'][$server_key];

// Decrypt function
if (!function_exists('decrypt_pass')) {
    function decrypt_pass($g, $k) { 
        list($d, $i) = explode('::', base64_decode($g), 2); 
        return openssl_decrypt($d, ENCRYPTION_CIPHER, $k, 0, $i); 
    }
}

// Connect to the ACTIVE server's database
$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'], 
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "TrustServerCertificate" => 1
]);

$pendingOrders = [];
if ($conn) {
    $sql = "SELECT * FROM PendingDonations WHERE Status = 0 ORDER BY DateSubmitted ASC";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $pendingOrders[] = $row;
        }
    }
    sqlsrv_close($conn);
}
?>

<div style="background: #007bff; color: white; padding: 12px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-size: 1.2em; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    Viewing Orders for: <?php echo htmlspecialchars($server_label); ?>
</div>

<h2>Pending QR Ph Donations</h2>

<?php if (empty($pendingOrders)): ?>
    <div style="background: #f9f9f9; padding: 30px; text-align: center; border-radius: 8px; border: 1px solid #ddd; color: #666;">
        No pending orders for <?php echo htmlspecialchars($server_label); ?>.
    </div>
<?php else: ?>
    <table style="width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead>
            <tr style="background: #333; color: white;">
                <th>ID</th>
                <th>Account</th>
                <th>Requested Credits</th>
                <th>Ref No.</th>
                <th>Date Submitted</th>
                <th>Proof Image</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingOrders as $order): ?>
                <tr style="border-bottom: 1px solid #eee; text-align: center;">
                    <td><?php echo $order['ID']; ?></td>
                    <td style="font-weight: bold;"><?php echo htmlspecialchars($order['AccountID']); ?></td>
                    <td style="color: #28a745; font-weight: bold;"><?php echo $order['CreditsToReceive']; ?></td>
                    <td><?php echo htmlspecialchars($order['ReferenceNumber']); ?></td>
                    <td><?php echo $order['DateSubmitted']->format('Y-m-d H:i'); ?></td>
                    <td>
                        <a href="../uploads/proofs/<?php echo htmlspecialchars($order['ProofImage']); ?>" target="_blank" style="color: #007bff; font-weight: bold;">View Proof</a>
                    </td>
                    <td style="padding: 10px;">
                        <form action="actions/manage-settings.php" method="POST" style="display:inline; padding:0; border:none; margin:0;">
                            <input type="hidden" name="action" value="approve_order">
                            <input type="hidden" name="order_id" value="<?php echo $order['ID']; ?>">
                            <button type="submit" class="button" style="background:#28a745; padding:5px 10px; font-size:0.9em;">Approve</button>
                        </form>
                        <form action="actions/manage-settings.php" method="POST" style="display:inline; padding:0; border:none; margin:0;">
                            <input type="hidden" name="action" value="reject_order">
                            <input type="hidden" name="order_id" value="<?php echo $order['ID']; ?>">
                            <button type="submit" class="button delete" style="padding:5px 10px; font-size:0.9em;" onclick="return confirm('Reject this order?');">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>