<div class="check-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Webshop Purchase Logs</h2>
        <button onclick="loadWebshopLogs()" class="button" style="padding: 5px 15px; font-size: 0.9em;">Refresh Logs</button>
    </div>
    
    <div id="logs-container" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; min-height: 200px; padding: 10px;">
        Loading logs...
    </div>
</div>

<script>
function loadWebshopLogs() {
    const container = document.getElementById('logs-container');
    
    // Change this path to match your actual file location
    const handlerPath = 'actions/manage-settings.php?action=view_webshop_logs';

    fetch(handlerPath)
        .then(response => {
            if (!response.ok) throw new Error('File not found at ' + handlerPath);
            return response.text();
        })
        .then(html => {
            container.innerHTML = html; // This will display the table seen in SQL
        })
        .catch(err => {
            container.innerHTML = `<p style="color:red; text-align:center;">Error: ${err.message}</p>`;
        });
}

// Load logs immediately on page enter
document.addEventListener('DOMContentLoaded', loadWebshopLogs);
</script>