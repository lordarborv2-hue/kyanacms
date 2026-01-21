// ================================================
// MAIN SYSTEM: AUTH, LAYOUT, & DYNAMIC CONTENT
// ================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Main system initializing...');
    checkUserSession();
    loadAllData();
    setInterval(loadAllData, 60000);
    setupNewsClickHandlers();
});

// ================================================
// 1. SESSION & AUTHENTICATION CHECK
// ================================================
async function checkUserSession() {
    try {
        const response = await fetch('Configuration/check-session.php');
        const data = await response.json();

        if (data.loggedIn) {
            const loginBtn = document.querySelector('.login-btn');
            const registerBtn = document.querySelector('.register-btn');

            if (loginBtn) {
                loginBtn.textContent = '👤 My Dashboard';
                loginBtn.style.background = '#eebb00';
                loginBtn.style.borderColor = '#d4a875';
                loginBtn.style.color = '#000';
                
                // Clone to remove old listeners and add redirect
                const newBtn = loginBtn.cloneNode(true);
                newBtn.onclick = function(e) { 
                    e.preventDefault();
                    window.location.href = 'user-dashboard.html'; 
                };
                loginBtn.parentNode.replaceChild(newBtn, loginBtn);
            }
            if (registerBtn) registerBtn.style.display = 'none'; 
        }
    } catch (error) {
        console.error('Session check failed:', error);
    }
}

// ================================================
// 2. DATA LOADING
// ================================================
async function loadAllData() {
    console.log('📡 Loading site data...');
    try {
        const timestamp = Date.now();
        const settingsResponse = await fetch(`Configuration/settings.json?v=${timestamp}`);
        const settings = await settingsResponse.json();
        
        // 1. Apply Website Basics
        document.title = settings.website_title;
        if (settings.favicon_url) {
            const fav = document.getElementById('favicon');
            if (fav) fav.href = settings.favicon_url;
        }
        if (settings.wallpaper_url) {
            document.body.style.backgroundImage = `url('${settings.wallpaper_url}')`;
        }

        const link1 = document.getElementById('dl-link-1');
        const link2 = document.getElementById('dl-link-2');
        if (link1) { link1.textContent = settings.download_link_1.label; link1.href = settings.download_link_1.url; }
        if (link2) { link2.textContent = settings.download_link_2.label; link2.href = settings.download_link_2.url; }

        // --- VISIBILITY LOGIC (NEW) ---
        const col1 = document.getElementById('col-server-1');
        const col2 = document.getElementById('col-server-2');
        const separator = document.getElementById('server-separator');

        // Check visibility settings (Default to true if missing)
        const showMid = settings.mid_rate_server.visible !== false; // true if undefined
        const showHard = settings.hard_rate_server.visible !== false;

        if (col1) col1.style.display = showMid ? 'block' : 'none';
        if (col2) col2.style.display = showHard ? 'block' : 'none';

        // Hide separator if only one (or zero) servers are showing
        if (separator) {
            separator.style.display = (showMid && showHard) ? 'block' : 'none';
        }

        // Apply Server Names
        const midNameEl = document.getElementById('mid-rate-name');
        const hardNameEl = document.getElementById('hard-rate-name');
        if (midNameEl) midNameEl.textContent = settings.mid_rate_server.name;
        if (hardNameEl) hardNameEl.textContent = settings.hard_rate_server.name;

        // 2. DYNAMIC REGISTRATION DROPDOWN
        // Only show options if the server is Visible
        const regSelect = document.getElementById('reg-server');
        const loginSelect = document.getElementById('login-server');
        
        const populateSelect = (selectEl) => {
            if (!selectEl) return;
            const currentVal = selectEl.value;
            selectEl.innerHTML = '<option value="">-- Choose Server --</option>';
            
            if (showMid) {
                const opt1 = document.createElement('option');
                opt1.value = 'mid';
                opt1.textContent = settings.mid_rate_server.name;
                selectEl.appendChild(opt1);
            }
            
            if (showHard) {
                const opt2 = document.createElement('option');
                opt2.value = 'hard';
                opt2.textContent = settings.hard_rate_server.name;
                selectEl.appendChild(opt2);
            }
            
            if (currentVal) selectEl.value = currentVal;
        };
        
        populateSelect(regSelect);
        populateSelect(loginSelect);

        // --- FETCH STATUS & COUNTS ---
        if (showMid) {
            fetch(`Configuration/api.php?server=mid&v=${timestamp}`).then(r=>r.json()).then(d=>{ 
                const el = document.getElementById('mid-rate-count'); if(el) el.textContent = d.online;
            });
            loadEmblem('mid', 'mid-rate-owner-name', 'mid-rate-emblem', timestamp);
        }

        if (showHard) {
            fetch(`Configuration/api.php?server=hard&v=${timestamp}`).then(r=>r.json()).then(d=>{ 
                const el = document.getElementById('hard-rate-count'); if(el) el.textContent = d.online;
            });
            loadEmblem('hard', 'hard-rate-owner-name', 'hard-rate-emblem', timestamp);
        }

        fetch(`Configuration/status-api.php?v=${timestamp}`).then(r=>r.json()).then(statusData => {
            const s1 = document.getElementById('mid-rate-status');
            const s2 = document.getElementById('hard-rate-status');
            if (s1) { s1.textContent = statusData.mid_rate_status; s1.className = 'server-status ' + statusData.mid_rate_status.toLowerCase(); }
            if (s2) { s2.textContent = statusData.hard_rate_status; s2.className = 'server-status ' + statusData.hard_rate_status.toLowerCase(); }
        });

        // --- NEWS ---
        const newsResponse = await fetch(`Configuration/news.json?v=${timestamp}`);
        const newsData = await newsResponse.json();

        const createNewsHTML = (posts) => {
            if (!posts || posts.length === 0) return '<p>No news available.</p>';
            return posts.map(post => `
                <div class="news-post">
                    <h3 class="news-subject">${post.subject}</h3>
                    <div class="news-content">
                        <div class="news-details">${post.details}</div>
                        <span class="news-date">${post.date}</span>
                    </div>
                </div>
            `).join('');
        };

        const midNewsEl = document.getElementById('mid-rate-news-container');
        const hardNewsEl = document.getElementById('hard-rate-news-container');
        if (midNewsEl && showMid) midNewsEl.innerHTML = createNewsHTML(newsData.mid_rate_news);
        if (hardNewsEl && showHard) hardNewsEl.innerHTML = createNewsHTML(newsData.hard_rate_news);

        setTimeout(checkAndExpand, 200);

    } catch (error) {
        console.error('❌ Error loading data:', error);
    }
}

const loadEmblem = async (server, nameElId, imgElId, timestamp) => {
    try {
        const r = await fetch(`Configuration/cs-emblem-api.php?server=${server}&v=${timestamp}`);
        const d = await r.json();
        const nameEl = document.getElementById(nameElId);
        const imgEl = document.getElementById(imgElId);
        if (nameEl) nameEl.textContent = d.owner_name;
        if (imgEl) {
            if (d.emblem_hex) {
                imgEl.src = `emblem.php?data=${d.emblem_hex}`;
                imgEl.style.display = 'block';
            } else {
                imgEl.style.display = 'none';
            }
        }
    } catch(e) {}
};

// ================================================
// 3. LAYOUT EXPANSION
// ================================================
function checkAndExpand() {
    const container = document.querySelector('.container');
    if (!container) return;
    const allTables = document.querySelectorAll('.news-post.active table');
    let maxColumns = 0;
    allTables.forEach((table) => {
        const firstRow = table.querySelector('thead tr, tbody tr');
        if (firstRow) maxColumns = Math.max(maxColumns, firstRow.querySelectorAll('th, td').length);
    });
    if (maxColumns >= 4) container.classList.add('wide');
    else container.classList.remove('wide');
}

function setupNewsClickHandlers() {
    const handleClick = (event) => {
        const subject = event.target.closest('.news-subject');
        if (subject) {
            const newsPost = subject.closest('.news-post');
            newsPost.classList.toggle('active');
            setTimeout(checkAndExpand, 100);
        }
    };
    const c1 = document.getElementById('mid-rate-news-container');
    const c2 = document.getElementById('hard-rate-news-container');
    if (c1) c1.addEventListener('click', handleClick);
    if (c2) c2.addEventListener('click', handleClick);
}