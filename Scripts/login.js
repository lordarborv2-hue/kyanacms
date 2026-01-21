// ================================================
// LOGIN SYSTEM
// ================================================

function openLoginModal() {
    document.getElementById('loginModal').classList.add('active');
    document.getElementById('loginForm').reset();
    document.getElementById('loginMessage').innerHTML = '';
}

function closeLoginModal() {
    document.getElementById('loginModal').classList.remove('active');
}

async function handleLogin(event) {
    event.preventDefault();
    const btn = document.getElementById('loginSubmitBtn');
    const msg = document.getElementById('loginMessage');
    
    btn.disabled = true;
    btn.textContent = 'Verifying...';
    
    const formData = new FormData(document.getElementById('loginForm'));
    
    try {
        const response = await fetch('Configuration/login.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch (e) { throw new Error(text); }

        if (data.success) {
            // REDIRECT TO DASHBOARD PAGE
            window.location.href = data.redirect;
        } else {
            msg.innerHTML = `<div class="message error">❌ ${data.message}</div>`;
            btn.disabled = false;
            btn.textContent = 'Login';
        }
    } catch (error) {
        console.error('Login Error:', error);
        msg.innerHTML = '<div class="message error">❌ System Error. Check console.</div>';
        btn.disabled = false;
        btn.textContent = 'Login';
    }
}

// Close modal on outside click
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('loginModal')) closeLoginModal();
});