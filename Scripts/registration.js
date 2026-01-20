// ================================================
// REGISTRATION SYSTEM
// ================================================

console.log('✅ Registration system loaded!');

// Open registration modal
function openRegistrationModal() {
    console.log('📝 Opening registration modal...');
    const modal = document.getElementById('registrationModal');
    if (modal) {
        modal.classList.add('active');
        // Clear form and messages
        const form = document.getElementById('registrationForm');
        if (form) form.reset();
        const msg = document.getElementById('registrationMessage');
        if (msg) msg.innerHTML = '';
    } else {
        console.error('❌ Modal element not found!');
    }
}

// Close registration modal
function closeRegistrationModal() {
    const modal = document.getElementById('registrationModal');
    if (modal) modal.classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('registrationModal');
    if (event.target === modal) {
        closeRegistrationModal();
    }
}

// Handle registration form submission
async function handleRegistration(event) {
    event.preventDefault();
    console.log('📤 Submitting registration...');
    
    const messageDiv = document.getElementById('registrationMessage');
    const submitBtn = document.getElementById('registerSubmitBtn');
    
    // 1. Get Values
    const serverSelect = document.getElementById('reg-server');
    const serverValue = serverSelect.value; 
    // DYNAMIC NAME FIX: Get the text of the selected option (e.g., "Zion")
    const serverName = serverSelect.options[serverSelect.selectedIndex].text;
    
    const username = document.getElementById('reg-username').value;
    const password = document.getElementById('reg-password').value;
    const confirmPassword = document.getElementById('reg-confirm-password').value;
    
    // 2. Client-side Validation
    if (password !== confirmPassword) {
        messageDiv.innerHTML = '<div class="message error">❌ Passwords do not match!</div>';
        return;
    }
    
    // 3. Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering...';
    
    // 4. Prepare Data
    const formData = new FormData();
    formData.append('server', serverValue);
    formData.append('username', username);
    formData.append('password', password);
    
    try {
        const response = await fetch('Configuration/register.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Server returned invalid JSON:', text);
            messageDiv.innerHTML = `
                <div class="message error" style="text-align:left; font-size:12px; overflow:auto; max-height:150px;">
                    <strong>❌ SYSTEM ERROR:</strong><br>
                    ${text}
                </div>`;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
            return;
        }

        // 5. Handle Success/Failure
        if (result.success) {
            messageDiv.innerHTML = `
                <div class="message success">
                    ✅ <strong>Success!</strong><br>
                    Account: <strong>${result.username}</strong><br>
                    Server: <strong>${serverName}</strong><br><br>
                    You can now login to the game!
                </div>
            `;
            document.getElementById('registrationForm').reset();
        } else {
            messageDiv.innerHTML = `<div class="message error">❌ ${result.message}</div>`;
        }
        
    } catch (error) {
        console.error('❌ Network error:', error);
        messageDiv.innerHTML = '<div class="message error">❌ Network Error. Check console (F12) for details.</div>';
    }
    
    submitBtn.disabled = false;
    submitBtn.textContent = 'Register';
}