document.addEventListener('DOMContentLoaded', function() {
    loadUserData();
    loadWebshopItems();
	loadWallpaper();
});

// --- GLOBAL VARIABLES ---
let dashboardFeatures = {}; 
let conversionRates = { wcoinc: 1, wcoinp: 1, goblin: 1 };
let webshopPricing = { price_level: 10, price_exc: 50, price_luck_skill: 25, price_380: 100, price_harmony: 100, price_socket: 50 };
let selectedCharacter = '';
let availableShopItems = [];

const categoryNames = {
    0: "Swords", 1: "Axes", 2: "Maces & Scepters", 3: "Spears",
    4: "Bows & Crossbows", 5: "Staffs", 6: "Shields", 7: "Helms",
    8: "Armors", 9: "Pants", 10: "Gloves", 11: "Boots",
    12: "Wings & Orbs", 13: "Pets & Rings", 14: "Pendants & Jewels", 15: "Scrolls"
};

// --- TABS SYSTEM ---
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    const targetTab = document.getElementById(`tab-${tabId}`);
    if (targetTab) targetTab.classList.add('active');
    if (event && event.currentTarget) event.currentTarget.classList.add('active');
}

// --- USER DATA ---
async function loadUserData() {
    try {
        const response = await fetch('Configuration/get-user-data.php');
        const data = await response.json();
        
        if (!data.success) {
            if (data.error === 'auth_required' || data.error === 'timeout') window.location.href = 'index.html'; 
            else document.getElementById('loading-msg').textContent = 'Error: ' + data.error;
            return;
        }
        
        dashboardFeatures = data.features || {};
        conversionRates = data.rates || { wcoinc: 1, wcoinp: 1, goblin: 1 };

        const webshopTabBtn = document.querySelector('.tab-btn[onclick="switchTab(\'webshop\')"]');
        const webshopTabContent = document.getElementById('tab-webshop');
        
        if (!dashboardFeatures.enable_webshop) {
            if (webshopTabBtn) webshopTabBtn.style.display = 'none';
            if (webshopTabContent) webshopTabContent.innerHTML = '<div style="padding: 50px; text-align: center; color: #ff4444; font-size: 1.5em;">The Webshop is currently disabled.</div>';
            switchTab('characters'); 
        } else {
            if (webshopTabBtn) webshopTabBtn.style.display = 'inline-block';
        }

        if(document.getElementById('rate-wcoinc-disp')) {
            document.getElementById('rate-wcoinc-disp').textContent = conversionRates.wcoinc;
            document.getElementById('rate-wcoinp-disp').textContent = conversionRates.wcoinp;
            document.getElementById('rate-goblin-disp').textContent = conversionRates.goblin;
        }

        document.getElementById('user-name').textContent = data.username;
        document.getElementById('server-name').textContent = data.server_label;
        document.getElementById('loading-msg').style.display = 'none';
        document.getElementById('char-table').style.display = 'table';
        
        const tbody = document.getElementById('char-list');
        tbody.innerHTML = '';

        if (data.characters.length > 0) {
            const firstChar = data.characters[0];
            document.getElementById('stat-web-credits').textContent = firstChar.WebCredits;
            document.getElementById('stat-wcoinc').textContent = firstChar.WCoinC;
            document.getElementById('stat-wcoinp').textContent = firstChar.WCoinP;
            document.getElementById('stat-goblin').textContent = firstChar.GoblinPoint;
        }

        if (data.characters.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center; padding:20px;">No characters found.</td></tr>';
        } else {
            data.characters.forEach(char => {
                const isDL = [64, 65, 66].includes(parseInt(char.Class));
                const ldr = isDL ? `<span style="color:#b19cd9; font-weight:bold;">${char.Leadership}</span>` : '<span style="color:#555;">-</span>';
                const pkClass = char.PkCount > 0 ? 'color:#dc3545; font-weight:bold;' : '';

                tbody.innerHTML += `
                    <tr>
                        <td style="font-weight:bold;">${char.Name}</td>
                        <td>${char.ClassName}</td>
                        <td style="color:#f1c40f;">${char.cLevel}</td>
                        <td style="color:#28a745;">${char.MasterLevel}</td>
                        <td>${char.ResetCount || 0}</td>
                        <td>${char.Strength}</td>
                        <td>${char.Dexterity}</td>
                        <td>${char.Vitality}</td>
                        <td>${char.Energy}</td>
                        <td>${ldr}</td> 
                        <td style="${pkClass}">${char.PkCount}</td>
                        <td><button class="btn" style="padding:5px 10px;" onclick="openManageModal('${char.Name}')">Manage</button></td>
                    </tr>
                `;
            });
        }
    } catch (e) { document.getElementById('loading-msg').textContent = 'Connection error.'; }
}

// --- WEBSHOP SYSTEM ---
async function loadWebshopItems() {
    try {
        const response = await fetch('Configuration/get-webshop-items.php');
        const data = await response.json();
        
        if (data.success) {
            webshopPricing = data.pricing || webshopPricing;
            availableShopItems = data.items;
            
            const catSelect = document.getElementById('shop-category');
            if(!catSelect) return;
            
            const availableTypes = [...new Set(availableShopItems.map(item => item.ItemType))].sort((a,b) => a-b);
            
            catSelect.innerHTML = '<option value="">-- Select Category --</option>';
            availableTypes.forEach(type => {
                catSelect.innerHTML += `<option value="${type}">${categoryNames[type] || `Category ${type}`}</option>`;
            });
        }
    } catch (e) { console.error('Webshop load error:', e); }
}

function filterShopItems() {
    const catSelect = document.getElementById('shop-category');
    const itemSelect = document.getElementById('shop-item');
    const selectedType = catSelect.value;
    
    itemSelect.innerHTML = '<option value="" data-base="0">-- Choose an Item --</option>';
    
    if (selectedType === "") {
        applyItemFilters();
        calculateShopPrice();
        return;
    }

    const filteredItems = availableShopItems.filter(item => item.ItemType == selectedType);
    
    filteredItems.forEach(item => {
        const opt = document.createElement('option');
        opt.value = `${item.ItemType}-${item.ItemIndex}`;
        
        opt.dataset.base = item.BasePrice ?? 100;
        opt.dataset.allowExc = item.AllowExc ?? 1;
        opt.dataset.allowLevel = item.AllowLevel ?? 1;
        opt.dataset.allow380 = item.Allow380 ?? 0;
        opt.dataset.allowHarmony = item.AllowHarmony ?? 0;
        opt.dataset.allowSocket = item.AllowSocket ?? 0;
        
        opt.dataset.allowAncient = item.AllowAncient ?? 0;
        opt.dataset.ancName1 = item.AncName1;
        opt.dataset.ancName2 = item.AncName2;
        
        opt.dataset.maxExc = item.MaxExc ?? 6;
        opt.dataset.maxSocket = item.MaxSocket ?? 5;
        opt.dataset.allowLuck = item.AllowLuck ?? 1;
        opt.dataset.allowSkill = item.AllowSkill ?? 1;

        const cleanName = item.ItemName.replace(/"/g, '').trim();
        opt.textContent = `[${item.ItemIndex}] ${cleanName}`;
        itemSelect.appendChild(opt);
    });
    
    applyItemFilters();
    calculateShopPrice();
}

function applyItemFilters() {
    const itemSelect = document.getElementById('shop-item');
    if (!itemSelect || !itemSelect.value) {
        document.getElementById('shop-group-luckskill').style.display = 'none';
        document.getElementById('shop-group-level').style.display = 'none';
        document.getElementById('shop-group-exc').style.display = 'none';
        document.getElementById('shop-group-advanced').style.display = 'none';
        document.getElementById('shop-group-sockets').style.display = 'none';
        document.getElementById('shop-group-ancient').style.display = 'none';
        return;
    }

    const opt = itemSelect.options[itemSelect.selectedIndex];
    
    // Toggle Luck & Skill
    const allowLuck = opt.dataset.allowLuck == 1;
    const allowSkill = opt.dataset.allowSkill == 1;
    document.getElementById('shop-luck').parentElement.parentElement.style.display = allowLuck ? 'block' : 'none';
    if(!allowLuck) document.getElementById('shop-luck').checked = false;
    document.getElementById('shop-skill').parentElement.parentElement.style.display = allowSkill ? 'block' : 'none';
    if(!allowSkill) document.getElementById('shop-skill').checked = false;
    document.getElementById('shop-group-luckskill').style.display = (allowLuck || allowSkill) ? 'grid' : 'none';

    // Toggle Level & Exc
    document.getElementById('shop-group-level').style.display = (opt.dataset.allowLevel == 1) ? 'block' : 'none';
    if(opt.dataset.allowLevel == 0) document.getElementById('shop-level').value = "0";

    document.getElementById('shop-group-exc').style.display = (opt.dataset.allowExc == 1) ? 'block' : 'none';
    if(opt.dataset.allowExc == 0) document.querySelectorAll('.shop-exc').forEach(cb => cb.checked = false);

    // Toggle Advanced Container
    const showAdvanced = (opt.dataset.allow380 == 1 || opt.dataset.allowHarmony == 1 || opt.dataset.allowAncient == 1 || opt.dataset.allowSocket == 1);
    document.getElementById('shop-group-advanced').style.display = showAdvanced ? 'flex' : 'none';
    
    // Toggle 380
    document.getElementById('shop-380').parentElement.parentElement.style.display = (opt.dataset.allow380 == 1) ? 'block' : 'none';
    if(opt.dataset.allow380 == 0) document.getElementById('shop-380').checked = false;

    // Toggle Ancient & Build Dynamic Names
    const ancientContainer = document.getElementById('shop-group-ancient');
    const ancientSelect = document.getElementById('shop-ancient');
    if (ancientContainer && ancientSelect) {
        if (opt.dataset.allowAncient == "1") {
            let optionsAdded = false;
            ancientSelect.innerHTML = '<option value="0">None</option>';
            
            const name1 = opt.dataset.ancName1;
            const name2 = opt.dataset.ancName2;

            // Only display Tier 1 if a valid name was found
            if (name1 && name1 !== 'null' && name1 !== '' && name1 !== 'undefined' && name1 !== '0') {
                ancientSelect.innerHTML += `<option value="1">${name1} (+5 Stamina)</option>`;
                ancientSelect.innerHTML += `<option value="2">${name1} (+10 Stamina)</option>`;
                optionsAdded = true;
            }

            // Only display Tier 2 if a valid name was found
            if (name2 && name2 !== 'null' && name2 !== '' && name2 !== 'undefined' && name2 !== '0') {
                ancientSelect.innerHTML += `<option value="3">${name2} (+5 Stamina)</option>`;
                ancientSelect.innerHTML += `<option value="4">${name2} (+10 Stamina)</option>`;
                optionsAdded = true;
            }

            // If at least one valid Ancient set was found, show the container. Otherwise, hide it.
            if (optionsAdded) {
                ancientContainer.style.display = 'block';
            } else {
                ancientContainer.style.display = 'none';
                ancientSelect.value = "0";
            }
        } else {
            ancientContainer.style.display = 'none';
            ancientSelect.value = "0";
        }
    }

    // Toggle Harmony
    const harmonyContainer = document.getElementById('shop-group-harmony');
    if (opt.dataset.allowHarmony == 1) {
        harmonyContainer.style.display = 'block';
        populateHarmonyOptions(opt.value.split('-')[0]); 
    } else {
        harmonyContainer.style.display = 'none';
        document.getElementById('shop-harmony').value = "0";
    }

    // Toggle Sockets
    const maxSocket = parseInt(opt.dataset.maxSocket) || 0;
    const sockGroup = document.getElementById('shop-group-sockets');
    const sockSelect = document.getElementById('shop-sockets');
    if (opt.dataset.allowSocket == 1 && maxSocket > 0) {
        sockGroup.style.display = 'block';
        sockSelect.innerHTML = '<option value="0">0</option>'; 
        for(let i = 1; i <= maxSocket; i++) {
            sockSelect.innerHTML += `<option value="${i}">${i} Socket${i !== 1 ? 's' : ''}</option>`;
        }
    } else {
        sockGroup.style.display = 'none';
        sockSelect.innerHTML = '<option value="0">0</option>';
    }
}

function populateHarmonyOptions(itemType) {
    const harmonySelect = document.getElementById('shop-harmony');
    harmonySelect.innerHTML = '<option value="0">None</option>';
    itemType = parseInt(itemType);
    
    if (itemType >= 0 && itemType <= 4) {
        harmonySelect.innerHTML += `<option value="1">Min Dmg Increase</option><option value="2">Max Dmg Increase</option><option value="5">Attack Dmg Increase</option><option value="6">Critical Dmg Increase</option><option value="7">Skill Dmg Increase</option><option value="8">Attack Rate (PVP) Increase</option><option value="9">SD Decrease Rate</option><option value="10">SD Ignore Rate (Bypass)</option>`;
    } else if (itemType === 5) {
        harmonySelect.innerHTML += `<option value="1">Magic Power Increase</option><option value="4">Skill Dmg Increase</option><option value="5">Critical Dmg Increase</option><option value="6">SD Decrease Rate</option><option value="7">SD Ignore Rate (Bypass)</option>`;
    } else if (itemType >= 6 && itemType <= 11) {
        harmonySelect.innerHTML += `<option value="1">Defense Increase</option><option value="3">Max HP Increase</option><option value="4">HP Recovery Rate</option><option value="5">Mana Recovery Rate</option><option value="6">Defense Rate (PVP)</option><option value="7">Damage Decrement</option><option value="8">SD Ratio Increase</option>`;
    }
}

function calculateShopPrice() {
    const itemSelect = document.getElementById('shop-item');
    if (!itemSelect || !itemSelect.value) {
        document.getElementById('shop-total-price').textContent = "0";
        return;
    }

    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    let totalPrice = parseInt(selectedOption.dataset.base);

    const level = parseInt(document.getElementById('shop-level').value) || 0;
    const hasLuck = document.getElementById('shop-luck').checked;
    const hasSkill = document.getElementById('shop-skill').checked;
    const has380 = document.getElementById('shop-380').checked;
    const hasHarmony = parseInt(document.getElementById('shop-harmony').value) > 0;
    const sockets = parseInt(document.getElementById('shop-sockets').value) || 0;
    const ancient = document.getElementById('shop-ancient') ? parseInt(document.getElementById('shop-ancient').value) : 0;
    
    const excCheckboxes = document.querySelectorAll('.shop-exc');
    const excCount = document.querySelectorAll('.shop-exc:checked').length;

    totalPrice += (level * (webshopPricing.price_level || 10));
    if (hasLuck) totalPrice += (webshopPricing.price_luck_skill || 25);
    if (hasSkill) totalPrice += (webshopPricing.price_luck_skill || 25);
    totalPrice += (excCount * (webshopPricing.price_exc || 50));
    if (has380) totalPrice += (webshopPricing.price_380 || 100);
    if (hasHarmony) totalPrice += (webshopPricing.price_harmony || 100);
    totalPrice += (sockets * (webshopPricing.price_socket || 50));
    if (ancient > 0) totalPrice += (webshopPricing.price_ancient || 100); // Uses Dynamic DB Price now

    const maxExc = parseInt(selectedOption.dataset.maxExc) || 0;
    if (selectedOption.dataset.allowExc == 1 && maxExc > 0) {
        if (excCount >= maxExc) {
            excCheckboxes.forEach(cb => { if (!cb.checked) cb.disabled = true; });
        } else {
            excCheckboxes.forEach(cb => cb.disabled = false);
        }
    }

    document.getElementById('shop-total-price').textContent = totalPrice;
}

async function buyWebshopItem() {
    const itemSelect = document.getElementById('shop-item');
    const msgDiv = document.getElementById('shop-message');

    if (!itemSelect.value) {
        msgDiv.innerHTML = '<span style="color:red">Please select an item first.</span>';
        return;
    }

    const [itemType, itemIndex] = itemSelect.value.split('-');
    const level = document.getElementById('shop-level').value;
    const luck = document.getElementById('shop-luck').checked ? 1 : 0;
    const skill = document.getElementById('shop-skill').checked ? 1 : 0;
    const opt380 = document.getElementById('shop-380').checked ? 1 : 0;
    const harmony = document.getElementById('shop-harmony').value;
    const sockets = document.getElementById('shop-sockets').value;
    const ancient = document.getElementById('shop-ancient') ? document.getElementById('shop-ancient').value : 0;
    
    let excOpt = 0;
    document.querySelectorAll('.shop-exc:checked').forEach(cb => { excOpt += parseInt(cb.value); });

    const totalPrice = document.getElementById('shop-total-price').textContent;

    if (!confirm(`Buy this item for ${totalPrice} WebCredits?\n\nWARNING: Make sure your character is OFFLINE and Warehouse has space.`)) return;

    msgDiv.innerHTML = '<span style="color:#f1c40f">Processing Order...</span>';

    const formData = new FormData();
    formData.append('itemType', itemType);
    formData.append('itemIndex', itemIndex);
    formData.append('level', level);
    formData.append('luck', luck);
    formData.append('skill', skill);
    formData.append('excOpt', excOpt);
    formData.append('opt380', opt380);
    formData.append('harmony', harmony);
    formData.append('sockets', sockets);
    formData.append('ancient', ancient);

    try {
        const response = await fetch('Configuration/webshop-buy.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            msgDiv.innerHTML = `<span style="color:#28a745">✅ ${data.message}</span>`;
            setTimeout(() => {
                loadUserData(); 
                msgDiv.innerHTML = '';
                itemSelect.value = '';
                applyItemFilters();
                calculateShopPrice(); 
            }, 3000);
        } else {
            msgDiv.innerHTML = `<span style="color:#dc3545">❌ ${data.message}</span>`;
        }
    } catch (e) {
        msgDiv.innerHTML = '<span style="color:#dc3545">❌ System Error. Check server connection.</span>';
    }
}

// --- MODALS & ACTIONS ---
function openManageModal(charName) {
    selectedCharacter = charName;
    document.getElementById('modal-char-name').textContent = charName;
    document.getElementById('action-result').innerHTML = '';
    document.getElementById('manageModal').style.display = 'block';
    
    const container = document.getElementById('action-buttons-container');
    container.innerHTML = '';

    if (dashboardFeatures.enable_reset) container.innerHTML += `<button class="btn" style="background:#007bff;" onclick="performAction('reset_char')">Reset Character</button>`;
    if (dashboardFeatures.enable_reset_stats) container.innerHTML += `<button class="btn" style="background:#17a2b8;" onclick="performAction('reset_stats')">Reset Stats</button>`;
    if (dashboardFeatures.enable_clear_pk) container.innerHTML += `<button class="btn" style="background:#dc3545;" onclick="performAction('clear_pk')">Clear PK</button>`;
    if (dashboardFeatures.enable_reset_master) container.innerHTML += `<button class="btn" style="background:#6f42c1;" onclick="performAction('reset_master')">Reset Master ML</button>`;
    if (dashboardFeatures.enable_unstuck) container.innerHTML += `<button class="btn" style="background:#ffc107; color:#000;" onclick="performAction('unstuck_char')">Unstuck Character</button>`;

    if (container.innerHTML === '') container.innerHTML = '<p style="color:#aaa;">No actions enabled by Admin.</p>';
}

function closeManageModal() { document.getElementById('manageModal').style.display = 'none'; }

async function performAction(actionType) {
    const resultDiv = document.getElementById('action-result');
    resultDiv.innerHTML = '<span style="color:#f1c40f;">Processing...</span>';
    
    const formData = new FormData();
    formData.append('action', actionType);
    formData.append('character', selectedCharacter);

    try {
        const response = await fetch('Configuration/user-action.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `<span style="color:#28a745;">✅ ${data.message}</span>`;
            setTimeout(loadUserData, 1500); 
        } else { resultDiv.innerHTML = `<span style="color:#dc3545;">❌ ${data.message}</span>`; }
    } catch (e) { resultDiv.innerHTML = '<span style="color:#dc3545;">❌ System Error.</span>'; }
}

function openConvertModal() { document.getElementById('convertModal').style.display = 'block'; document.getElementById('convert-message').innerHTML = ''; document.getElementById('convert-amount').value = ''; calculateConversion(); }
function closeConvertModal() { document.getElementById('convertModal').style.display = 'none'; }

function calculateConversion() {
    const type = document.getElementById('convert-type').value;
    const amount = parseInt(document.getElementById('convert-amount').value) || 0;
    const rate = conversionRates[type] || 1;
    const typeLabels = { wcoinc: 'WCoinC', wcoinp: 'WCoinP', goblin: 'GoblinPoints' };
    
    document.getElementById('receive-type').textContent = typeLabels[type];
    document.getElementById('receive-amount').textContent = amount * rate;
}

async function submitConversion() {
    const type = document.getElementById('convert-type').value;
    const amount = document.getElementById('convert-amount').value;
    const msgDiv = document.getElementById('convert-message');

    if (amount <= 0) { msgDiv.innerHTML = '<span style="color:#dc3545">Enter a valid amount.</span>'; return; }
    if (!confirm(`Converting ${amount} WebCredits.\n\nMake sure your account is OFFLINE in the game.`)) return;

    msgDiv.innerHTML = '<span style="color:#f1c40f">Processing...</span>';
    const formData = new FormData(); formData.append('type', type); formData.append('amount', amount);

    try {
        const response = await fetch('Configuration/convert-credits.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            msgDiv.innerHTML = `<span style="color:#28a745">✅ ${data.message}</span>`;
            document.getElementById('convert-amount').value = ''; calculateConversion(); 
            setTimeout(() => { closeConvertModal(); loadUserData(); }, 2000);
        } else { msgDiv.innerHTML = `<span style="color:#dc3545">❌ ${data.message}</span>`; }
    } catch (e) { msgDiv.innerHTML = '<span style="color:#dc3545">❌ System Error.</span>'; }
}

function openPasswordModal() { document.getElementById('passwordModal').style.display = 'block'; document.getElementById('pwd-message').innerHTML = ''; document.getElementById('old-pass').value = ''; document.getElementById('new-pass').value = ''; document.getElementById('conf-pass').value = ''; }
function closePasswordModal() { document.getElementById('passwordModal').style.display = 'none'; }

async function submitPasswordChange() {
    const oldPass = document.getElementById('old-pass').value;
    const newPass = document.getElementById('new-pass').value;
    const confPass = document.getElementById('conf-pass').value;
    const msgDiv = document.getElementById('pwd-message');

    if (!oldPass || !newPass || !confPass) { msgDiv.innerHTML = '<span style="color:#dc3545">All fields are required.</span>'; return; }
    if (newPass !== confPass) { msgDiv.innerHTML = '<span style="color:#dc3545">New passwords do not match.</span>'; return; }

    msgDiv.innerHTML = '<span style="color:#f1c40f">Updating...</span>';
    const formData = new FormData(); formData.append('old_password', oldPass); formData.append('new_password', newPass); formData.append('confirm_password', confPass);

    try {
        const response = await fetch('Configuration/change-password.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            msgDiv.innerHTML = '<span style="color:#28a745">✅ Password Changed!</span>';
            setTimeout(closePasswordModal, 1500);
        } else { msgDiv.innerHTML = `<span style="color:#dc3545">❌ ${data.message}</span>`; }
    } catch (e) { msgDiv.innerHTML = '<span style="color:#dc3545">❌ System Error.</span>'; }
}

async function logout() { await fetch('Configuration/logout.php'); window.location.href = 'index.html'; }

window.onclick = function(event) {
    if (event.target == document.getElementById('manageModal')) closeManageModal();
    if (event.target == document.getElementById('passwordModal')) closePasswordModal();
    if (event.target == document.getElementById('convertModal')) closeConvertModal();
}

async function loadWallpaper() {
    try {
        const response = await fetch('Configuration/settings.json?v=' + Date.now());
        const settings = await response.json();
        if (settings.wallpaper_url) {
            document.body.style.backgroundImage = `url('${settings.wallpaper_url}')`;
            document.body.style.backgroundSize = 'cover';
            document.body.style.backgroundPosition = 'center';
            document.body.style.backgroundAttachment = 'fixed';
        }
    } catch (e) {
        console.log("No wallpaper set or failed to load.");
    }
}