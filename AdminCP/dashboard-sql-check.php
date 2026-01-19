<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
?>
<h2>SQL Server Diagnostics</h2>
<p>Use this tool to verify that your website can connect to the MSSQL database and that the required tables exist.</p>

<div class="check-card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3><?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?> (Mid Rate)</h3>
        <button class="button" onclick="runCheck('mid')" id="btn-mid">Run Check</button>
    </div>
    <div id="result-mid" style="margin-top:10px;"></div>
</div>

<div class="check-card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3><?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?> (Hard Rate)</h3>
        <button class="button" onclick="runCheck('hard')" id="btn-hard">Run Check</button>
    </div>
    <div id="result-hard" style="margin-top:10px;"></div>
</div>

<script>
async function runCheck(serverType) {
    const btn = document.getElementById('btn-' + serverType);
    const resultDiv = document.getElementById('result-' + serverType);
    
    btn.disabled = true;
    btn.textContent = 'Checking...';
    resultDiv.innerHTML = '<span class="status-badge pending">Connecting...</span>';
    
    try {
        const response = await fetch('actions/test-connection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'server=' + serverType
        });
        
        const data = await response.json();
        
        let html = '';
        
        // Connection Status
        if (data.connection === true) {
            html += '<div style="margin-bottom:10px;"><strong>Connection:</strong> <span class="status-badge success">Successful</span></div>';
        } else {
            html += '<div style="margin-bottom:10px;"><strong>Connection:</strong> <span class="status-badge error">Failed</span></div>';
            html += `<p class="error" style="font-size:0.9em;">${data.error}</p>`;
        }
        
        // Table Status
        if (data.tables) {
            html += '<strong>Required Tables:</strong><ul class="check-list">';
            for (const [table, exists] of Object.entries(data.tables)) {
                const icon = exists ? '✅' : '❌';
                const style = exists ? 'color:green' : 'color:red; font-weight:bold';
                html += `<li><span>${table}</span> <span style="${style}">${icon} ${exists ? 'Found' : 'Missing'}</span></li>`;
            }
            html += '</ul>';
        }
        
        resultDiv.innerHTML = html;
        
    } catch (e) {
        resultDiv.innerHTML = '<span class="status-badge error">System Error: ' + e.message + '</span>';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Run Check';
    }
}
</script>