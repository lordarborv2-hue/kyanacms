document.addEventListener('DOMContentLoaded', function() {
    loadUserData();
    loadWallpaper();
});

let dashboardFeatures = {}; // Store admin settings

async function loadUserData() {
    try {
        const response = await fetch('Configuration/get-user-data.php');
        const data = await response.json();
        
        if (!data.success) {
            if (data.error === 'auth_required' || data.error === 'timeout') {
                window.location.href = 'index.html'; 
            } else {
                document.getElementById('loading-msg').textContent = 'Error: ' + data.error;
            }
            return;
        }
        
        dashboardFeatures = data.features || {};

        document.getElementById('user-name').textContent = data.username;
        document.getElementById('server-name').textContent = data.server_label;
        document.getElementById('loading-msg').style.display = 'none';
        document.getElementById('char-table').style.display = 'table';
        
        const tbody = document.getElementById('char-list');
        tbody.innerHTML = '';
        
        if (data.characters.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:20px;">No characters found.</td></tr>';
        } else {
            data.characters.forEach(char => {
                // Check if class is Dark Lord (64) or Lord Emperor (65, 66)
                const isDL = [64, 65, 66].includes(parseInt(char.Class));
                const leadershipDisplay = isDL ? `<span style="color:#b19cd9; font-weight:bold;">${char.Leadership}</span>` : '<span style="color:#555;">-</span>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="color:#fff; font-weight:bold;">${char.Name}</td>
                    <td>${char.ClassName}</td>
                    <td class="level-high">${char.cLevel}</td>
                    <td class="master-lvl">${char.MasterLevel}</td>
                    <td>${char.ResetCount || 0}</td>
                    <td>${char.Strength}</td>
                    <td>${char.Dexterity}</td>
                    <td>${char.Vitality}</td>
                    <td>${char.Energy}</td>
                    <td>${leadershipDisplay}</td> <td class="${char.PkCount > 0 ? 'pk-killer' : ''}">${char.PkCount}</td>
                    <td>
                        <button class="manage-btn" onclick="openManageModal('${char.Name}')">Manage</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
    } catch (e) {
        console.error(e);
        document.getElementById('loading-msg').textContent = 'Connection error.';
    }
}
// --- MODAL & ACTIONS ---
let selectedCharacter = '';

function openManageModal(charName) {
    selectedCharacter = charName;
    document.getElementById('modal-char-name').textContent = charName;
    document.getElementById('action-result').innerHTML = '';
    document.getElementById('manageModal').style.display = 'block';
    
    const container = document.getElementById('action-buttons-container');
    container.innerHTML = '';

    // Existing buttons...
    if (dashboardFeatures.enable_reset) {
        container.innerHTML += `<button class="action-btn btn-reset" onclick="performAction('reset_char')">Reset Character</button>`;
    }
    if (dashboardFeatures.enable_reset_stats) {
        container.innerHTML += `<button class="action-btn btn-stats" onclick="performAction('reset_stats')">Reset Stats</button>`;
    }
    if (dashboardFeatures.enable_clear_pk) {
        container.innerHTML += `<button class="action-btn btn-pk" onclick="performAction('clear_pk')">Clear PK</button>`;
    }
    if (dashboardFeatures.enable_reset_master) {
        container.innerHTML += `<button class="action-btn btn-master" onclick="performAction('reset_master')">Reset Master ML</button>`;
    }
    
    if (dashboardFeatures.enable_unstuck) {
        container.innerHTML += `<button class="action-btn" style="background:#ff9800; color:white;" onclick="performAction('unstuck_char')">Unstuck Character</button>`;
    }

    if (container.innerHTML === '') {
        container.innerHTML = '<p style="color:#aaa;">No actions enabled by Admin.</p>';
    }
}

function openPasswordModal() {
    document.getElementById('passwordModal').style.display = 'block';
    document.getElementById('pwd-message').innerHTML = '';
    document.getElementById('old-pass').value = '';
    document.getElementById('new-pass').value = '';
    document.getElementById('conf-pass').value = '';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

async function submitPasswordChange() {
    const oldPass = document.getElementById('old-pass').value;
    const newPass = document.getElementById('new-pass').value;
    const confPass = document.getElementById('conf-pass').value;
    const msgDiv = document.getElementById('pwd-message');

    if (!oldPass || !newPass || !confPass) {
        msgDiv.innerHTML = '<span style="color:red">All fields are required.</span>';
        return;
    }
    if (newPass !== confPass) {
        msgDiv.innerHTML = '<span style="color:red">New passwords do not match.</span>';
        return;
    }

    msgDiv.innerHTML = '<span style="color:yellow">Updating...</span>';
    
    const formData = new FormData();
    formData.append('old_password', oldPass);
    formData.append('new_password', newPass);
    formData.append('confirm_password', confPass);

    try {
        const response = await fetch('Configuration/change-password.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            msgDiv.innerHTML = '<span style="color:green">✅ Password Changed!</span>';
            setTimeout(closePasswordModal, 1500);
        } else {
            msgDiv.innerHTML = `<span style="color:red">❌ ${data.message}</span>`;
        }
    } catch (e) {
        msgDiv.innerHTML = '<span style="color:red">❌ System Error.</span>';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target == document.getElementById('manageModal')) closeManageModal();
    if (event.target == document.getElementById('passwordModal')) closePasswordModal();
}


function closeManageModal() {
    document.getElementById('manageModal').style.display = 'none';
}

async function performAction(actionType) {
    const resultDiv = document.getElementById('action-result');
    resultDiv.innerHTML = '<span style="color:#eebb00;">Processing...</span>';
    
    // Disable buttons to prevent double click
    const btns = document.querySelectorAll('.action-btn');
    btns.forEach(b => b.disabled = true);

    const formData = new FormData();
    formData.append('action', actionType);
    formData.append('character', selectedCharacter);

    try {
        const response = await fetch('Configuration/user-action.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `<span style="color:#4caf50;">✅ ${data.message}</span>`;
            setTimeout(loadUserData, 1500); // Refresh table data
        } else {
            resultDiv.innerHTML = `<span style="color:#ff4444;">❌ ${data.message}</span>`;
        }
    } catch (e) {
        resultDiv.innerHTML = '<span style="color:#ff4444;">❌ System Error.</span>';
    }

    // Re-enable buttons
    btns.forEach(b => b.disabled = false);
}

// --- WALLPAPER & LOGOUT ---
async function loadWallpaper() {
    try {
        const response = await fetch('Configuration/settings.json?v=' + Date.now());
        const settings = await response.json();
        if (settings.wallpaper_url) {
            document.body.style.backgroundImage = `url('${settings.wallpaper_url}')`;
        }
    } catch (e) {}
}

async function logout() {
    await fetch('Configuration/logout.php'); 
    window.location.href = 'index.html';
}

// Close modal if clicking outside
window.onclick = function(event) {
    if (event.target == document.getElementById('manageModal')) {
        closeManageModal();
    }
}