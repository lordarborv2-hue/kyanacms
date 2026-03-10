document.addEventListener('DOMContentLoaded', function() {
    loadUserData();
    loadWebshopItems();
    loadWallpaper();
    loadSidebars();
});

// --- GLOBAL VARIABLES ---
let dashboardFeatures = {}; 
let conversionRates = { wcoinc: 1, wcoinp: 1, goblin: 1 };
let webshopPricing = { price_level: 10, price_exc: 50, price_luck_skill: 25, price_380: 100, price_harmony: 100, price_socket: 50, price_ancient: 100 };
let selectedCharacter = '';
let availableShopItems = [];
let shoppingCart = [];

// Donation Globals
let paypalConfig = { enabled: false, rate: 100, currency: 'USD' };
let paymongoConfig = { enabled: false, rate: 100, public_key: '' }; // FIXED: Added PayMongo Global
let paypalScriptLoaded = false;
window.qrRatio = 100; // Global QR Ph Ratio

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
    
    // Add active class to the clicked button based on the tabId
    const btn = document.querySelector(`.tab-btn[onclick="switchTab('${tabId}')"]`);
    if(btn) btn.classList.add('active');
}

// --- USER DATA ---
async function loadUserData() {
    try {
        const response = await fetch('Configuration/get-user-data.php?t=' + new Date().getTime());
        const data = await response.json();
        
        if (!data.success) {
            if (data.error === 'auth_required' || data.error === 'timeout') window.location.href = 'index.html'; 
            else document.getElementById('loading-msg').textContent = 'Error: ' + data.error;
            return;
        }
        
        dashboardFeatures = data.features || {};
        conversionRates = data.rates || { wcoinc: 1, wcoinp: 1, goblin: 1 };

        // Setup Webshop Tab
        const webshopTabBtn = document.querySelector('.tab-btn[onclick="switchTab(\'webshop\')"]');
        const webshopTabContent = document.getElementById('tab-webshop');
        
        if (!dashboardFeatures.enable_webshop) {
            if (webshopTabBtn) webshopTabBtn.style.display = 'none';
            if (webshopTabContent) webshopTabContent.innerHTML = '<div style="padding: 50px; text-align: center; color: #ff4444; font-size: 1.5em;">The Webshop is currently disabled.</div>';
            switchTab('characters'); 
        } else {
            if (webshopTabBtn) webshopTabBtn.style.display = 'inline-block';
        }

        // Setup Donation Tab (PayPal, QR Ph, & PayMongo)
        paypalConfig = data.paypal || { enabled: false, rate: 100, currency: 'USD' };
        paymongoConfig = data.paymongo || { enabled: false, rate: 100, public_key: '' }; // FIXED: Load PayMongo data
        const qrConfig = data.qr_ph || { enabled: false, ratio: 100 };
        window.qrRatio = qrConfig.ratio;
		const qrImg = document.getElementById('qr-ph-image');
		if (qrImg && qrConfig.image_url) {
			qrImg.src = qrConfig.image_url + '?t=' + Date.now();
		}

        const donateTabBtn = document.getElementById('tab-btn-donate');
        const paypalContainer = document.getElementById('paypal-container');
        const qrContainer = document.getElementById('qr-ph-container');
        const paymongoContainer = document.getElementById('paymongo-container'); // FIXED: Get PayMongo HTML
        
        // --- Handle PayMongo Visibility ---
        if (paymongoConfig.enabled && paymongoConfig.public_key) {
            if (paymongoContainer) paymongoContainer.style.display = 'block';
            const rateDisp = document.getElementById('paymongo-rate-disp');
            if (rateDisp) rateDisp.textContent = paymongoConfig.rate;
        } else {
            if (paymongoContainer) paymongoContainer.style.display = 'none';
        }

        // --- Handle PayPal Visibility ---
        if (paypalConfig.enabled && paypalConfig.client_id) {
            if (paypalContainer) paypalContainer.style.display = 'block';
            document.getElementById('paypal-currency-label').textContent = paypalConfig.currency;
            if (typeof calculateDonation === 'function') calculateDonation(); 
            
            if (!paypalScriptLoaded) {
                const script = document.createElement('script');
                script.src = `https://www.paypal.com/sdk/js?client-id=${paypalConfig.client_id}&currency=${paypalConfig.currency}`;
                script.onload = () => { if(typeof renderPayPalButtons === 'function') renderPayPalButtons(); paypalScriptLoaded = true; };
                document.body.appendChild(script);
            }
        } else {
            if (paypalContainer) paypalContainer.style.display = 'none';
        }

        // --- Handle QR Ph Visibility ---
        if (qrConfig.enabled) {
            if (qrContainer) qrContainer.style.display = 'block';
        } else {
            if (qrContainer) qrContainer.style.display = 'none';
        }

        // Show/Hide Main Donation Tab (Now checks all 3 payment methods)
        if (!qrConfig.enabled && (!paypalConfig.enabled || !paypalConfig.client_id) && (!paymongoConfig.enabled || !paymongoConfig.public_key)) {
            if (donateTabBtn) donateTabBtn.style.display = 'none';
        } else {
            if (donateTabBtn) donateTabBtn.style.display = 'inline-block';
        }

        // Set Converstion Rates text
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
        const response = await fetch('Configuration/get-webshop-items.php?t=' + new Date().getTime());
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
        if(document.getElementById('shop-group-ancient')) document.getElementById('shop-group-ancient').style.display = 'none';
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

    // Toggle Ancient
    const ancientContainer = document.getElementById('shop-group-ancient');
    const ancientSelect = document.getElementById('shop-ancient');
    if (ancientContainer && ancientSelect) {
        if (opt.dataset.allowAncient == "1") {
            let optionsAdded = false;
            ancientSelect.innerHTML = '<option value="0">None</option>';
            
            const name1 = opt.dataset.ancName1;
            const name2 = opt.dataset.ancName2;

            if (name1 && name1 !== 'null' && name1 !== '' && name1 !== 'undefined' && name1 !== '0') {
                ancientSelect.innerHTML += `<option value="1">${name1} (+5 Stamina)</option>`;
                ancientSelect.innerHTML += `<option value="2">${name1} (+10 Stamina)</option>`;
                optionsAdded = true;
            }

            if (name2 && name2 !== 'null' && name2 !== '' && name2 !== 'undefined' && name2 !== '0') {
                ancientSelect.innerHTML += `<option value="3">${name2} (+5 Stamina)</option>`;
                ancientSelect.innerHTML += `<option value="4">${name2} (+10 Stamina)</option>`;
                optionsAdded = true;
            }

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
    if (ancient > 0) totalPrice += (webshopPricing.price_ancient || 100);

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
            
            // ---> INSTANT REFRESH ADDED HERE <---
            loadUserData(); 
            
            setTimeout(() => {
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

// --- PAYMONGO DONATION SYSTEM ---
function calculatePayMongo() {
    const credits = parseInt(document.getElementById('paymongo-credits').value) || 0;
    const rate = paymongoConfig.rate || 100;
    
    // Calculate the PHP price
    const pricePhp = (credits / rate).toFixed(2);
    document.getElementById('paymongo-price-php').textContent = pricePhp;
    
    // Calculate minimum credits required to hit the 1 PHP limit
    const minCredits = rate * 1;
    const minMsg = document.getElementById('paymongo-min-msg');
    if (minMsg) minMsg.textContent = `Minimum purchase: ${minCredits} Credits (1 PHP)`;
}

let paymongoCheckInterval; // Global variable to manage the timer

async function payWithPayMongo() {
    const credits = parseInt(document.getElementById('paymongo-credits').value) || 0;
    const rate = paymongoConfig.rate || 100; //
    const pricePhp = credits / rate;
    const msgDiv = document.getElementById('paymongo-message');

    if (credits <= 0) return;

    // Minimum transaction check
    if (pricePhp < 100) {
        const minCredits = rate * 100;
        msgDiv.innerHTML = `<span style="color:#dc3545;">❌ Minimum transaction is 100 PHP (${minCredits} Credits).</span>`;
        return;
    }

    msgDiv.innerHTML = '<span style="color:#f1c40f;">⏳ Generating Secure Payment Link...</span>';

    // Open blank tab to bypass popup blockers
    let paymentWindow = window.open('', '_blank');
    if (paymentWindow) {
        paymentWindow.document.write("<h2 style='font-family:sans-serif; text-align:center; margin-top:50px;'>Connecting to PayMongo Secure Checkout... Please wait.</h2>");
    }

    const formData = new FormData();
    formData.append('credits', credits);
    formData.append('account', document.getElementById('user-name').textContent.trim());

    try {
        const response = await fetch('Configuration/paymongo-create-link.php', { method: 'POST', body: formData });
        const result = await response.json(); //
        
        if (result.success) {
            msgDiv.innerHTML = `<span style="color:#f1c40f;">⏳ Waiting for payment confirmation...</span><br><small>Checkout opened in a new tab.</small>`;
            
            if (paymentWindow) {
                paymentWindow.location.href = result.checkout_url;
            }

            // --- START POLLING FOR CONFIRMATION ---
            // Clear any existing interval first
            if (paymongoCheckInterval) clearInterval(paymongoCheckInterval);

            paymongoCheckInterval = setInterval(async () => {
                try {
                    const statusRes = await fetch('Configuration/check-payment-status.php?t=' + Date.now());
                    const statusData = await statusRes.json();
                    
                    if (statusData.status === 'success') {
                        clearInterval(paymongoCheckInterval);
                        msgDiv.innerHTML = `<span style="color:#28a745; font-size:1.1em; font-weight:bold;">✅ ${statusData.message}</span>`;
                        
                        // Show visual notification
                        alert("Success! " + statusData.message);
                        
                        // Refresh user credits in the header
                        loadUserData(); 
                    }
                } catch (err) {
                    console.error("Polling error:", err);
                }
            }, 3000); // Check every 3 seconds

        } else {
            if (paymentWindow) paymentWindow.close();
            msgDiv.innerHTML = `<span style="color:#dc3545;">❌ ${result.message}</span>`;
        }
    } catch (e) {
        if (paymentWindow) paymentWindow.close();
        msgDiv.innerHTML = '<span style="color:#dc3545;">❌ Error connecting to server.</span>';
    }
}
// --- PAYPAL DONATION SYSTEM ---
function calculateDonation() {
    const amount = parseFloat(document.getElementById('donate-amount').value) || 0;
    const credits = Math.floor(amount * paypalConfig.rate);
    document.getElementById('donate-receive-amount').textContent = credits;
}

function renderPayPalButtons() {
    paypal.Buttons({
        createOrder: function(data, actions) {
            const amount = parseFloat(document.getElementById('donate-amount').value);
            if(amount <= 0) return;
            return actions.order.create({
                purchase_units: [{
                    amount: { value: amount.toFixed(2) }
                }]
            });
        },
        onApprove: function(data, actions) {
            const msgDiv = document.getElementById('paypal-message');
            msgDiv.innerHTML = '<span style="color:#f1c40f;">Capturing payment... Do not close window.</span>';
            
            return fetch('Configuration/paypal-capture.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderID: data.orderID })
            }).then(res => res.json()).then(result => {
                if(result.success) {
                    msgDiv.innerHTML = `<span style="color:#28a745;">✅ ${result.message}</span>`;
                    loadUserData(); 
                } else {
                    msgDiv.innerHTML = `<span style="color:#dc3545;">❌ ${result.message}</span>`;
                }
            }).catch(err => {
                msgDiv.innerHTML = `<span style="color:#dc3545;">❌ Connection error verifying payment.</span>`;
            });
        },
        onCancel: function (data) {
            document.getElementById('paypal-message').innerHTML = '<span style="color:#dc3545;">Payment cancelled.</span>';
        }
    }).render('#paypal-button-container');
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

// --- QR PH MANUAL DONATION SYSTEM ---
function updateQRPrice() {
    const ratio = window.qrRatio || 100; 
    const creditsInput = document.getElementById('qr-credits');
    const priceDisplay = document.getElementById('qr-total-php');
    
    if (!creditsInput || !priceDisplay) return;

    const credits = parseInt(creditsInput.value) || 0;
    const totalPhp = (credits / ratio).toFixed(2);
    priceDisplay.textContent = totalPhp;
}

async function sendQRProof() {
    const credits = document.getElementById('qr-credits').value;
    const ref = document.getElementById('qr-ref-no').value;
    const fileInput = document.getElementById('qr-file');
    const msg = document.getElementById('qr-status-msg');

    if (!credits || !ref || fileInput.files.length === 0) {
        msg.style.color = "#dc3545";
        msg.innerHTML = "❌ Please fill all fields and upload a screenshot.";
        return;
    }

    const formData = new FormData();
    formData.append('credits', credits);
    formData.append('reference', ref);
    formData.append('proof', fileInput.files[0]);

    msg.style.color = "#f1c40f";
    msg.innerHTML = "⏳ Uploading proof... please wait.";

    try {
        const response = await fetch('Configuration/submit-qr-donation.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error(`Server returned ${response.status}`);
        const result = await response.json();

        if (result.success) {
            msg.style.color = "#28a745";
            msg.innerHTML = "✅ Proof sent! Waiting for Admin approval.";
            
            document.getElementById('qr-credits').value = '';
            document.getElementById('qr-ref-no').value = '';
            document.getElementById('qr-file').value = '';
            updateQRPrice();
        } else {
            msg.style.color = "#dc3545";
            msg.innerHTML = "❌ " + result.message;
        }
    } catch (e) {
        msg.style.color = "#dc3545";
        msg.innerHTML = "❌ System Error. Check folder permissions or DB connection.";
    }
}

// --- LOAD WALLPAPER ---
async function loadWallpaper() {
    try {
        const response = await fetch('Configuration/settings.json?v=' + Date.now());
        const settings = await response.json();
        if (settings.wallpaper_url) {
            document.body.style.backgroundImage = `url('${settings.wallpaper_url}')`;
        }
    } catch (e) {
        console.log("No wallpaper set or failed to load.");
    }
}

// --- SIDEBAR SYSTEM (JEWELS, CLASSES, RANKINGS) ---
async function loadSidebars() {
    try {
        const res = await fetch('Configuration/get-sidebar-stats.php', { cache: 'no-store' }); 
        const data = await res.json();
        if (data.success) {
            
            // 1. UPDATE SERVER ECONOMY (JEWELS/COINS)
            const jUl = document.getElementById('sidebar-jewels'); 
            if (jUl) {
                jUl.innerHTML = '';
                if (data.tracked_items && Object.keys(data.tracked_items).length > 0) { 
                    for (const [name, count] of Object.entries(data.tracked_items)) { 
                        jUl.innerHTML += `<li><span>${name}:</span> <span class="val">${count}</span></li>`; 
                    } 
                } else {
                    jUl.innerHTML += `<li><span style="color:#aaa;">No items configured.</span></li>`;
                }
                
                jUl.innerHTML += `<li style="margin-top:10px; font-size:0.8em; color:#aaa; text-align:center; display:block;">(Server-wide Vault Economy)</li>`;
            }
            
            // 2. UPDATE CLASSES
            ['dk','dw','elf','mg','dl','sum','rf'].forEach(c => {
                const el = document.getElementById(`class-${c}`);
                if (el) el.textContent = data.classes[c.toUpperCase()] || 0;
            });
            
            // 3. UPDATE RANKINGS
            const rUl = document.getElementById('sidebar-rankings'); 
            if (rUl) {
                rUl.innerHTML = '';
                if (data.rankings.length === 0) {
                    rUl.innerHTML = '<li style="text-align:center; display:block; padding:10px; color:#888;">No players found.</li>';
                } else {
                    data.rankings.forEach((char, i) => {
                        let color = i === 0 ? '#f1c40f' : (i === 1 ? '#e0e0e0' : (i === 2 ? '#cd7f32' : '#aaa'));
                        rUl.innerHTML += `<li style="align-items:center;"><strong style="color:${color}; font-size:1.2em; width:20px;">${i+1}.</strong><span style="flex:1; margin-left:10px; font-weight:bold;">${char.Name}</span><div style="text-align:right;"><span style="display:block; color:#28a745; font-size:0.9em;">${char.ResetCount} Resets</span><span style="display:block; color:#aaa; font-size:0.8em;">Lvl ${char.cLevel}</span></div></li>`;
                    });
                }
            }
        }
    } catch(e) { console.log("Sidebar load error", e); }
}

function toggleLeftSidebar() {
    const isClasses = document.getElementById('sidebar-toggle').checked;
    document.getElementById('sidebar-jewels').style.display = isClasses ? 'none' : 'block';
    document.getElementById('sidebar-classes').style.display = isClasses ? 'block' : 'none';
    document.getElementById('left-sidebar-title').textContent = isClasses ? 'Server Classes' : 'My Warehouse';
}

function openQRModal(src) {
    const lightbox = document.getElementById('qr-lightbox');
    document.getElementById('qr-lightbox-img').src = src;
    lightbox.style.display = 'flex';
}

function closeQRModal() {
    document.getElementById('qr-lightbox').style.display = 'none';
}

// --- ADD TO CART ---
function addToCart() {
    const itemSelect = document.getElementById('shop-item');
    const msgDiv = document.getElementById('shop-message'); // Reuse existing message div

    if (!itemSelect.value) {
        msgDiv.innerHTML = '<span style="color:#dc3545">❌ Please select an item first.</span>';
        return;
    }

    const opt = itemSelect.options[itemSelect.selectedIndex];
    const [itemType, itemIndex] = itemSelect.value.split('-');
    
    // Capture visual labels for the tooltip
    const selectedExcNames = [];
    document.querySelectorAll('.shop-exc:checked').forEach(cb => {
        selectedExcNames.push(cb.parentElement.textContent.trim());
    });

    const cartItem = {
        uniqueId: Date.now(),
        name: opt.textContent.split('] ')[1],
        type: parseInt(itemType),
        index: parseInt(itemIndex),
        level: parseInt(document.getElementById('shop-level').value),
        luck: document.getElementById('shop-luck').checked ? 1 : 0,
        skill: document.getElementById('shop-skill').checked ? 1 : 0,
        excValue: Array.from(document.querySelectorAll('.shop-exc:checked')).reduce((acc, cb) => acc + parseInt(cb.value), 0),
        excNames: selectedExcNames,
        opt380: document.getElementById('shop-380').checked ? 1 : 0,
        harmonyVal: parseInt(document.getElementById('shop-harmony').value),
        harmonyName: document.getElementById('shop-harmony').options[document.getElementById('shop-harmony').selectedIndex].text,
        sockets: parseInt(document.getElementById('shop-sockets').value),
        ancient: parseInt(document.getElementById('shop-ancient')?.value || 0),
        price: parseInt(document.getElementById('shop-total-price').textContent),
        img: `uploads/items/${itemType}-${itemIndex}.gif` //
    };

    shoppingCart.push(cartItem);
    updateCartUI(); //

    // --- VISUAL NOTIFICATION ---
    msgDiv.innerHTML = `<span style="color:#28a745">✅ Added ${cartItem.name} to your cart!</span>`;
    
    // Optional: Auto-open the cart dropdown to show the user it's there
    const cartDropdown = document.getElementById('cart-dropdown');
    if (cartDropdown && cartDropdown.style.display === 'none') {
        toggleCart(); //
    }

    // Clear the notification after 3 seconds
    setTimeout(() => {
        msgDiv.innerHTML = '';
    }, 3000);
}

// --- UI UPDATES & TOOLTIPS ---
function updateCartUI() {
    const list = document.getElementById('cart-items-list');
    const count = document.getElementById('cart-count');
    const totalDisplay = document.getElementById('cart-total');
    
    if (count) count.textContent = shoppingCart.length;
    if (!list) return;

    list.innerHTML = '';
    let total = 0;

    shoppingCart.forEach(item => {
        total += item.price;
        list.innerHTML += `
            <div class="cart-item-row" 
                 onmouseover="showItemTooltip(event, ${item.uniqueId})" 
                 onmousemove="moveTooltip(event)"
                 onmouseout="hideItemTooltip()"
                 style="display:flex; align-items:center; gap:10px; margin-bottom:8px; background:#1a1a1a; padding:8px; border-radius:4px; border:1px solid #444; cursor:help;">
                <img src="${item.img}" style="width:32px; height:32px;" onerror="this.src='uploads/items/placeholder.gif'">
                <div style="flex:1;">
                    <div style="font-size:0.9em; font-weight:bold;">${item.name} +${item.level}</div>
                    <div style="font-size:0.8em; color:#f1c40f;">${item.price} Credits</div>
                </div>
                <button onclick="removeFromCart(${item.uniqueId})" style="background:none; border:none; color:#ff4444; cursor:pointer;">&times;</button>
            </div>
        `;
    });
    if (totalDisplay) totalDisplay.textContent = total + " Credits";
}

function showItemTooltip(e, uniqueId) {
    const item = shoppingCart.find(i => i.uniqueId === uniqueId);
    if (!item) return;

    const tooltip = document.getElementById('item-tooltip');
    let html = `<div style="text-align:center; font-weight:bold; color:${item.ancient > 0 ? '#28a745' : '#fff'};">${item.name}</div>`;
    html += `<div style="color:#f1c40f; text-align:center;">+${item.level}</div>`;
    
    if (item.skill) html += `<div style="color:#28a745;">(Skill)</div>`;
    if (item.luck) html += `<div style="color:#28a745;">(Luck)</div>`;
    
    item.excNames.forEach(name => {
        html += `<div style="color:#3498db;">${name}</div>`;
    });

    if (item.harmonyVal > 0) html += `<div style="color:#ffc107;">${item.harmony}</div>`;

    tooltip.innerHTML = html;
    tooltip.style.display = 'block';
    moveTooltip(e);
}

function moveTooltip(e) {
    const tooltip = document.getElementById('item-tooltip');
    tooltip.style.left = (e.clientX + 15) + 'px';
    tooltip.style.top = (e.clientY + 15) + 'px';
}

function hideItemTooltip() {
    document.getElementById('item-tooltip').style.display = 'none';
}

function toggleCart() {
    const cart = document.getElementById('cart-dropdown');
    cart.style.display = (cart.style.display === 'none') ? 'block' : 'none';
}

function removeFromCart(id) {
    shoppingCart = shoppingCart.filter(i => i.uniqueId !== id);
    updateCartUI();
    hideItemTooltip();
}

async function checkoutCart() {
    if (shoppingCart.length === 0) return alert("Cart is empty!");
    
    const response = await fetch('Configuration/webshop-checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: shoppingCart })
    });
    
    const result = await response.json();
    if (result.success) {
        alert(result.message);
        shoppingCart = [];
        updateCartUI();
        toggleCart();
        loadUserData(); //
    } else {
        alert(result.message);
    }
}

function updateItemPreview() {
    const itemSelect = document.getElementById('shop-item');
    const previewImg = document.getElementById('item-preview-image');
    
    if (!itemSelect || !itemSelect.value || !previewImg) return;

    const [type, index] = itemSelect.value.split('-');

    // Targets the new folder structure: uploads/items/Category/ID.gif
    previewImg.src = `uploads/items/${type}/${index}.gif`;

    // --- FALLBACK LOGIC ---
    previewImg.onerror = function() {
        // Points to your requested noimage.gif
        this.src = 'uploads/items/noimage.gif';
        
        // Prevent infinite loops if noimage.gif itself is missing
        this.onerror = null; 
    };
}

function onItemSelect(data) {
    const nameLabel = document.getElementById('item-display-name');
    if (data.AncientName) {
        nameLabel.innerHTML = `<span style="color: #00ff00;">${data.AncientName}</span> ${data.ItemName}`;
    } else {
        nameLabel.textContent = data.ItemName;
    }
}

// Ensure this runs whenever the item dropdown changes
document.getElementById('shop-item').addEventListener('change', updateItemPreview);



// --- LOGOUT LOGIC ---
async function logout() { 
    try {
        // Add timestamp to prevent the browser from caching the logout request itself
        await fetch('Configuration/logout.php?t=' + new Date().getTime(), { method: 'POST' }); 
    } catch(e) {
        console.log("Logout request failed, forcing redirect.");
    }
    
    // Wipe all local browser storage just to be safe
    localStorage.clear();
    sessionStorage.clear();
    
    // Force a Hard Redirect to the index page (bypasses browser cache)
    window.location.replace('index.html?logout=success&t=' + new Date().getTime()); 
}

window.onclick = function(event) {
    if (event.target == document.getElementById('manageModal')) closeManageModal();
    if (event.target == document.getElementById('passwordModal')) closePasswordModal();
    if (event.target == document.getElementById('convertModal')) closeConvertModal();
}