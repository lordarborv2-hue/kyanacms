<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
// Defaults
$u_dash = $settings['user_dashboard'] ?? [];
$en_reset = $u_dash['enable_reset'] ?? false;
$en_stats = $u_dash['enable_reset_stats'] ?? false;
$en_pk = $u_dash['enable_clear_pk'] ?? false;
$en_master = $u_dash['enable_reset_master'] ?? false;
?>
<form action="actions/manage-settings.php" method="POST">
    <input type="hidden" name="action" value="save_user_dashboard">
    <h2>User Dashboard Settings</h2>
    <p>Enable or disable features for players on their dashboard.</p>
    
    <div style="background:#f9f9f9; padding:20px; border:1px solid #ddd; border-radius:5px;">
        
        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Reset Character:</label>
            <input type="checkbox" id="en_reset" name="enable_reset" <?php echo $en_reset ? 'checked' : ''; ?>>
            <label for="en_reset">Enable</label>
            <p style="margin:5px 0 0 205px; font-size:0.85em; color:#666;">Allows players to perform a normal Reset (Level 400 -> 1).</p>
        </div>
        <hr>

        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Reset Stats:</label>
            <input type="checkbox" id="en_stats" name="enable_reset_stats" <?php echo $en_stats ? 'checked' : ''; ?>>
            <label for="en_stats">Enable</label>
            <p style="margin:5px 0 0 205px; font-size:0.85em; color:#666;">Allows players to reset STR/DEX/VIT/ENE to base and reclaim points.</p>
        </div>
        <hr>

        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Clear PK:</label>
            <input type="checkbox" id="en_pk" name="enable_clear_pk" <?php echo $en_pk ? 'checked' : ''; ?>>
            <label for="en_pk">Enable</label>
            <p style="margin:5px 0 0 205px; font-size:0.85em; color:#666;">Allows players to clear Murderer status.</p>
        </div>
        <hr>

        <div style="margin-bottom:15px;">
            <label style="display:inline-block; width:200px; font-weight:bold;">Reset Master ML:</label>
            <input type="checkbox" id="en_master" name="enable_reset_master" <?php echo $en_master ? 'checked' : ''; ?>>
            <label for="en_master">Enable</label>
            <p style="margin:5px 0 0 205px; font-size:0.85em; color:#666;">Allows players to reset their Master Skill Tree.</p>
        </div>

		<hr>
		
		<?php $en_unstuck = $u_dash['enable_unstuck'] ?? false; ?>
		<div style="margin-bottom:15px;">
			<label style="display:inline-block; width:200px; font-weight:bold;">Unstuck Character:</label>
			<input type="checkbox" id="en_unstuck" name="enable_unstuck" <?php echo $en_unstuck ? 'checked' : ''; ?>>
			<label for="en_unstuck">Enable</label>
			<p style="margin:5px 0 0 205px; font-size:0.85em; color:#666;">Moves a stuck character to Lorencia (Safe Zone).</p>
		</div>
		
    </div>

    <button type="submit" class="button" style="margin-top:20px;">Save Dashboard Settings</button>
</form>