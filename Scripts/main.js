// ================================================
// EXPANSION SYSTEM & LAYOUT MANAGER
// ================================================

console.log('✅ Main system loaded!');

document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.container');
    if (!container) return;
    
    loadAllData();
    setInterval(loadAllData, 20000); // Refresh every 20s
    setupNewsClickHandlers();
});

async function loadAllData() {
    try {
        const timestamp = Date.now();
        const settingsResponse = await fetch(`Configuration/settings.json?v=${timestamp}`);
        const settingsData = await settingsResponse.json();
        
        // --- LAYOUT LOGIC START ---
        const col1 = document.getElementById('col-server-1');
        const col2 = document.getElementById('col-server-2');
        const separator = document.getElementById('server-separator');
        const regDropdown = document.getElementById('reg-server');
        
        // Defaults
        const s1Visible = settingsData.mid_rate_server.visible !== false;
        const s2Visible = settingsData.hard_rate_server.visible !== false;
        
        // Handle Visibility
        if (s1Visible && s2Visible) {
            // Both Visible (Default Layout)
            col1.style.display = 'flex';
            col2.style.display = 'flex';
            separator.style.display = 'block';
        } else if (s1Visible && !s2Visible) {
            // Only Server 1 Visible (Centered)
            col1.style.display = 'flex';
            col2.style.display = 'none';
            separator.style.display = 'none';
        } else if (!s1Visible && s2Visible) {
            // Only Server 2 Visible (Centered)
            col1.style.display = 'none';
            col2.style.display = 'flex';
            separator.style.display = 'none';
        } else {
            // Both Hidden (Rare)
            col1.style.display = 'none';
            col2.style.display = 'none';
            separator.style.display = 'none';
        }
        
        // Update Registration Dropdown Options
        if (regDropdown) {
             // Reset options first
             regDropdown.innerHTML = '<option value="">-- Choose Server --</option>';
             if (s1Visible) {
                 const opt = document.createElement('option');
                 opt.value = 'mid';
                 opt.textContent = settingsData.mid_rate_server.name;
                 regDropdown.appendChild(opt);
             }
             if (s2Visible) {
                 const opt = document.createElement('option');
                 opt.value = 'hard';
                 opt.textContent = settingsData.hard_rate_server.name;
                 regDropdown.appendChild(opt);
             }
        }
        // --- LAYOUT LOGIC END ---

        // Update Text & Links
        document.title = settingsData.website_title;
        document.getElementById('mid-rate-name').textContent = settingsData.mid_rate_server.name;
        document.getElementById('hard-rate-name').textContent = settingsData.hard_rate_server.name;
        
        const fav = document.getElementById('favicon');
        if (fav && settingsData.favicon_url) fav.href = settingsData.favicon_url;
        
        const l1 = document.getElementById('dl-link-1');
        const l2 = document.getElementById('dl-link-2');
        l1.textContent = settingsData.download_link_1.label;
        l1.href = settingsData.download_link_1.url;
        l2.textContent = settingsData.download_link_2.label;
        l2.href = settingsData.download_link_2.url;
        
        if (settingsData.wallpaper_url) {
            document.body.style.backgroundImage = `url('${settingsData.wallpaper_url}')`;
        }
        
        // Fetch News
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
        
        if (s1Visible) {
            document.getElementById('mid-rate-news-container').innerHTML = createNewsHTML(newsData.mid_rate_news);
            // Fetch Status & Emblem for S1
            fetch(`Configuration/api.php?server=mid&v=${timestamp}`).then(r=>r.json()).then(d => {
                document.getElementById('mid-rate-count').textContent = d.online;
            });
            fetch(`Configuration/cs-emblem-api.php?server=mid&v=${timestamp}`).then(r=>r.json()).then(d => {
                document.getElementById('mid-rate-owner-name').textContent = d.owner_name;
                const img = document.getElementById('mid-rate-emblem');
                if(d.emblem_hex) { img.src = `emblem.php?data=${d.emblem_hex}`; img.style.display='block'; }
                else { img.style.display='none'; }
            });
        }
        
        if (s2Visible) {
            document.getElementById('hard-rate-news-container').innerHTML = createNewsHTML(newsData.hard_rate_news);
             // Fetch Status & Emblem for S2
            fetch(`Configuration/api.php?server=hard&v=${timestamp}`).then(r=>r.json()).then(d => {
                document.getElementById('hard-rate-count').textContent = d.online;
            });
             fetch(`Configuration/cs-emblem-api.php?server=hard&v=${timestamp}`).then(r=>r.json()).then(d => {
                document.getElementById('hard-rate-owner-name').textContent = d.owner_name;
                const img = document.getElementById('hard-rate-emblem');
                if(d.emblem_hex) { img.src = `emblem.php?data=${d.emblem_hex}`; img.style.display='block'; }
                else { img.style.display='none'; }
            });
        }

        setTimeout(() => checkAndExpand(), 200);
        
        // Status Colors
        const statusResponse = await fetch(`Configuration/status-api.php?v=${timestamp}`);
        const statusData = await statusResponse.json();
        
        if (s1Visible) {
            const el = document.getElementById('mid-rate-status');
            el.textContent = statusData.mid_rate_status;
            el.className = 'server-status ' + statusData.mid_rate_status.toLowerCase();
        }
        if (s2Visible) {
             const el = document.getElementById('hard-rate-status');
            el.textContent = statusData.hard_rate_status;
            el.className = 'server-status ' + statusData.hard_rate_status.toLowerCase();
        }

    } catch (error) {
        console.error('❌ Error loading data:', error);
    }
}

function checkAndExpand() {
    const container = document.querySelector('.container');
    if (!container) return;
    
    // Logic: If active news has wide table -> Expand
    const allTables = document.querySelectorAll('.news-post.active table');
    let maxColumns = 0;
    
    allTables.forEach((table) => {
        const firstRow = table.querySelector('thead tr, tbody tr');
        if (firstRow) maxColumns = Math.max(maxColumns, firstRow.querySelectorAll('th, td').length);
    });
    
    if (maxColumns >= 4) {
        container.classList.add('wide');
    } else {
        container.classList.remove('wide');
    }
}

function setupNewsClickHandlers() {
    const handleClick = (event) => {
        const subject = event.target.closest('.news-subject');
        if (subject) {
            const newsPost = subject.closest('.news-post');
            newsPost.classList.toggle('active');
            setTimeout(() => checkAndExpand(), 100);
        }
    };
    
    const c1 = document.getElementById('mid-rate-news-container');
    const c2 = document.getElementById('hard-rate-news-container');
    if (c1) c1.addEventListener('click', handleClick);
    if (c2) c2.addEventListener('click', handleClick);
}