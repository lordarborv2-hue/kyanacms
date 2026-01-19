// ================================================
// EXPANSION SYSTEM - MADE FOR YOUR EXACT HTML
// ================================================

console.log('✅ Expansion system loaded!');
console.log('📍 Designed for index.html structure');

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM Ready - Starting initialization...');
    
    // Get the container element
    const container = document.querySelector('.container');
    console.log('📦 Container found:', container ? 'YES ✅' : 'NO ❌');
    
    if (!container) {
        console.error('❌ CRITICAL: Container not found! Check HTML structure.');
        return;
    }
    
    // Initialize the page
    loadAllData();
    
    // Refresh data every 20 seconds
    setInterval(loadAllData, 600000);
    
    // Set up click handlers for news
    setupNewsClickHandlers();
});

// ================================================
// MAIN FUNCTION - LOAD ALL DATA
// ================================================
async function loadAllData() {
    console.log('📡 Loading server data...');
    
    try {
        const timestamp = Date.now();
        
        // Get all the HTML elements
        const elements = {
            midCount: document.getElementById('mid-rate-count'),
            midStatus: document.getElementById('mid-rate-status'),
            midName: document.getElementById('mid-rate-name'),
            midNews: document.getElementById('mid-rate-news-container'),
            midEmblem: document.getElementById('mid-rate-emblem'),
            midOwner: document.getElementById('mid-rate-owner-name'),
            
            hardCount: document.getElementById('hard-rate-count'),
            hardStatus: document.getElementById('hard-rate-status'),
            hardName: document.getElementById('hard-rate-name'),
            hardNews: document.getElementById('hard-rate-news-container'),
            hardEmblem: document.getElementById('hard-rate-emblem'),
            hardOwner: document.getElementById('hard-rate-owner-name'),
            
            link1: document.getElementById('dl-link-1'),
            link2: document.getElementById('dl-link-2'),
            favicon: document.getElementById('favicon')
        };
        
        // Fetch emblems
        const midCsResponse = await fetch(`Configuration/cs-emblem-api.php?server=mid&v=${timestamp}`);
        const midCsData = await midCsResponse.json();
        elements.midOwner.textContent = midCsData.owner_name;
        if (midCsData.emblem_hex) {
            elements.midEmblem.src = `emblem.php?data=${midCsData.emblem_hex}`;
            elements.midEmblem.style.display = 'block';
        } else {
            elements.midEmblem.style.display = 'none';
        }
        
        const hardCsResponse = await fetch(`Configuration/cs-emblem-api.php?server=hard&v=${timestamp}`);
        const hardCsData = await hardCsResponse.json();
        elements.hardOwner.textContent = hardCsData.owner_name;
        if (hardCsData.emblem_hex) {
            elements.hardEmblem.src = `emblem.php?data=${hardCsData.emblem_hex}`;
            elements.hardEmblem.style.display = 'block';
        } else {
            elements.hardEmblem.style.display = 'none';
        }
        
        // Fetch online counts
        const midResponse = await fetch(`Configuration/api.php?server=mid&v=${timestamp}`);
        const midData = await midResponse.json();
        elements.midCount.textContent = midData.online;
        
        const hardResponse = await fetch(`Configuration/api.php?server=hard&v=${timestamp}`);
        const hardData = await hardResponse.json();
        elements.hardCount.textContent = hardData.online;
        
        // Fetch settings
        const settingsResponse = await fetch(`Configuration/settings.json?v=${timestamp}`);
        const settingsData = await settingsResponse.json();
        
        document.title = settingsData.website_title;
        elements.midName.textContent = settingsData.mid_rate_server.name;
        elements.hardName.textContent = settingsData.hard_rate_server.name;
        
        if (elements.favicon && settingsData.favicon_url) {
            elements.favicon.href = settingsData.favicon_url;
        }
        
        elements.link1.textContent = settingsData.download_link_1.label;
        elements.link1.href = settingsData.download_link_1.url;
        elements.link2.textContent = settingsData.download_link_2.label;
        elements.link2.href = settingsData.download_link_2.url;
        
        if (settingsData.wallpaper_url) {
            document.body.style.backgroundImage = `url('${settingsData.wallpaper_url}')`;
        }
        
        // Fetch and display news
        const newsResponse = await fetch(`Configuration/news.json?v=${timestamp}`);
        const newsData = await newsResponse.json();
        
        // Create news HTML
        const createNewsHTML = (posts) => {
            if (!posts || posts.length === 0) {
                return '<p>No news available.</p>';
            }
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
        
        elements.midNews.innerHTML = createNewsHTML(newsData.mid_rate_news);
        elements.hardNews.innerHTML = createNewsHTML(newsData.hard_rate_news);
        
        // Don't auto-expand - let user click to open
        console.log('✅ News loaded - click to expand');
        
        // Check if container should expand after news is loaded
        setTimeout(() => {
            checkAndExpand();
        }, 200);
        
        // Fetch server status
        const statusResponse = await fetch(`Configuration/status-api.php?v=${timestamp}`);
        const statusData = await statusResponse.json();
        
        elements.midStatus.textContent = statusData.mid_rate_status;
        elements.midStatus.className = 'server-status ' + statusData.mid_rate_status.toLowerCase();
        
        elements.hardStatus.textContent = statusData.hard_rate_status;
        elements.hardStatus.className = 'server-status ' + statusData.hard_rate_status.toLowerCase();
        
        console.log('✅ All data loaded successfully!');
        
    } catch (error) {
        console.error('❌ Error loading data:', error);
    }
}

// ================================================
// CHECK IF CONTAINER SHOULD EXPAND
// ================================================
function checkAndExpand() {
    console.log('');
    console.log('🔍 === CHECKING EXPANSION ===');
    
    const container = document.querySelector('.container');
    if (!container) {
        console.error('❌ Container not found!');
        return;
    }
    
    // Find all active news posts with tables
    const activeNewsPosts = document.querySelectorAll('.news-post.active');
    console.log(`📰 Active news posts: ${activeNewsPosts.length}`);
    
    const allTables = document.querySelectorAll('.news-post.active table');
    console.log(`📊 Tables in active news: ${allTables.length}`);
    
    if (allTables.length === 0) {
        container.classList.remove('wide');
        console.log('ℹ️  No tables found - Container stays narrow (900px)');
        console.log('🏷️  Container class:', container.className);
        return;
    }
    
    // Check each table for column count
    let maxColumns = 0;
    allTables.forEach((table, index) => {
        const firstRow = table.querySelector('thead tr, tbody tr');
        if (firstRow) {
            const columns = firstRow.querySelectorAll('th, td').length;
            console.log(`   📋 Table ${index + 1}: ${columns} columns`);
            maxColumns = Math.max(maxColumns, columns);
        }
    });
    
    console.log(`🎯 Maximum columns found: ${maxColumns}`);
    
    // Expand if any table has 4+ columns
    if (maxColumns >= 4) {
        container.classList.add('wide');
        console.log('✅ ✅ ✅ CONTAINER EXPANDED TO WIDE MODE! ✅ ✅ ✅');
        console.log('📏 Target width: 1600px');
        console.log('🌟 Look for the green badge and glow!');
        
        // Check actual width after animation
        setTimeout(() => {
            const actualWidth = window.getComputedStyle(container).width;
            console.log(`📐 Actual computed width: ${actualWidth}`);
        }, 700);
    } else {
        container.classList.remove('wide');
        console.log(`ℹ️  Tables have less than 4 columns - Container stays narrow`);
    }
    
    console.log('🏷️  Container classes:', container.className);
    console.log('=== END CHECK ===');
    console.log('');
}

// ================================================
// SETUP CLICK HANDLERS FOR NEWS
// ================================================
function setupNewsClickHandlers() {
    console.log('🖱️  Setting up click handlers...');
    
    const midNewsContainer = document.getElementById('mid-rate-news-container');
    const hardNewsContainer = document.getElementById('hard-rate-news-container');
    
    const handleClick = (event) => {
        const subject = event.target.closest('.news-subject');
        if (subject) {
            const newsPost = subject.closest('.news-post');
            const wasActive = newsPost.classList.contains('active');
            
            // Toggle active class
            newsPost.classList.toggle('active');
            
            console.log(`👆 News clicked: ${wasActive ? 'CLOSING ❌' : 'OPENING ✅'}`);
            
            // Wait for CSS animation, then check expansion
            setTimeout(() => {
                checkAndExpand();
            }, 100);
        }
    };
    
    if (midNewsContainer) {
        midNewsContainer.addEventListener('click', handleClick);
        console.log('✅ Mid rate click handler attached');
    } else {
        console.error('❌ Mid rate news container not found!');
    }
    
    if (hardNewsContainer) {
        hardNewsContainer.addEventListener('click', handleClick);
        console.log('✅ Hard rate click handler attached');
    } else {
        console.error('❌ Hard rate news container not found!');
    }
    
    console.log('✅ Click handlers ready!');
}

console.log('📄 Script fully loaded - waiting for DOM...');