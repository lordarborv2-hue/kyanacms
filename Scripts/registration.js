// ================================================
// REGISTRATION SYSTEM
// ================================================

console.log('✅ Registration system loaded!');

// Open registration modal
function openRegistrationModal() {
    console.log('📝 Opening registration modal...');
    const modal = document.getElementById('registrationModal');
    modal.classList.add('active');
    
    // Clear form
    document.getElementById('registrationForm').reset();
    document.getElementById('registrationMessage').innerHTML = '';
}

// Close registration modal
function closeRegistrationModal() {
    console.log('❌ Closing registration modal...');
    const modal = document.getElementById('registrationModal');
    modal.classList.remove('active');
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
    
    const form = document.getElementById('registrationForm');
    const messageDiv = document.getElementById('registrationMessage');
    const submitBtn = document.getElementById('registerSubmitBtn');
    
    // Get form values
    const server = document.getElementById('reg-server').value;
    const username = document.getElementById('reg-username').value;
    const password = document.getElementById('reg-password').value;
    const confirmPassword = document.getElementById('reg-confirm-password').value;
    
    // Validate passwords match
    if (password !== confirmPassword) {
        messageDiv.innerHTML = '<div class="message error">❌ Passwords do not match!</div>';
        console.error('❌ Password mismatch');
        return;
    }
    
    // Validate username format
    const usernameRegex = /^[a-zA-Z0-9]{4,10}$/;
    if (!usernameRegex.test(username)) {
        messageDiv.innerHTML = '<div class="message error">❌ Username must be 4-10 characters (letters and numbers only)</div>';
        console.error('❌ Invalid username format');
        return;
    }
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('server', server);
    formData.append('username', username);
    formData.append('password', password);
    
    try {
        // Send registration request
        const response = await fetch('Configuration/register.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log('📥 Registration response:', result);
        
        if (result.success) {
            // Success!
            messageDiv.innerHTML = `
                <div class="message success">
                    ✅ Registration Successful!<br>
                    <strong>Username:</strong> ${username}<br>
                    <strong>Server:</strong> ${server === 'mid' ? 'Mid Rate' : 'Hard Rate'}<br><br>
                    <strong>Next Steps:</strong><br>
                    1. Download the game using the buttons above<br>
                    2. Install and launch the game<br>
                    3. Login with your credentials<br><br>
                    Have fun playing!
                </div>
            `;
            console.log('✅ Registration successful!');
            
            // Reset form
            form.reset();
            
        } else {
            // Error
            messageDiv.innerHTML = `<div class="message error">❌ ${result.message || 'Registration failed. Please try again.'}</div>`;
            console.error('❌ Registration failed:', result.message);
            
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
        }
        
    } catch (error) {
        console.error('❌ Registration error:', error);
        messageDiv.innerHTML = '<div class="message error">❌ An error occurred. Please try again later.</div>';
        
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Register';
    }
}

console.log('📄 Registration script loaded completely');
