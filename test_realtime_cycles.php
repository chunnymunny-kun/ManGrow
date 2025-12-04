<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Cycle Rankings Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            color: #333;
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .cycle-info {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .cycle-info h2 {
            margin: 0 0 10px 0;
        }
        .cycle-dates {
            font-size: 18px;
            opacity: 0.9;
        }
        .rankings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .ranking-card {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .ranking-card h3 {
            color: #667eea;
            margin-top: 0;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .ranking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }
        .ranking-item:nth-child(1) { border-left-color: #FFD700; }
        .ranking-item:nth-child(2) { border-left-color: #C0C0C0; }
        .ranking-item:nth-child(3) { border-left-color: #CD7F32; }
        .rank {
            font-weight: bold;
            color: #667eea;
            margin-right: 10px;
        }
        .name {
            flex: 1;
            font-weight: 500;
        }
        .points {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        .refresh-status {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 10px;
            color: #1976d2;
        }
        .last-updated {
            font-size: 14px;
            color: #666;
            text-align: center;
            margin-top: 10px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
            font-size: 18px;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏆 Real-Time Cycle Rankings</h1>
        <p class="subtitle">Updates every 30 seconds - Just like "Earned this week"!</p>
        
        <div id="cycle-info" class="cycle-info">
            <div class="loading">Loading cycle information...</div>
        </div>
        
        <div id="stats-container"></div>
        
        <div id="rankings-container" class="rankings-grid">
            <div class="loading">Loading rankings...</div>
        </div>
        
        <div class="refresh-status">
            <strong>🔄 Auto-refresh enabled:</strong> Rankings update every 30 seconds
            <div class="last-updated" id="last-updated">Never updated</div>
        </div>
    </div>

    <script>
        let refreshCount = 0;
        
        function loadCycleRankings() {
            fetch('get_cycle_rankings_ajax.php')
                .then(response => response.json())
                .then(data => {
                    refreshCount++;
                    updateLastRefreshTime();
                    
                    if(!data.success || !data.hasActiveCycle) {
                        document.getElementById('cycle-info').innerHTML = `
                            <div class="error">
                                <strong>⚠️ No Active Cycle</strong><br>
                                Please create an active cycle in the admin panel first.
                            </div>
                        `;
                        document.getElementById('rankings-container').innerHTML = `
                            <div class="error">No rankings available without an active cycle</div>
                        `;
                        return;
                    }
                    
                    // Update cycle info
                    const cycle = data.currentCycle;
                    document.getElementById('cycle-info').innerHTML = `
                        <h2>🏅 ${cycle.cycle_name}</h2>
                        <div class="cycle-dates">
                            📅 ${formatDate(cycle.start_date)} - ${formatDate(cycle.end_date)}
                        </div>
                    `;
                    
                    // Update stats
                    const stats = data.cycleStats || {};
                    document.getElementById('stats-container').innerHTML = `
                        <div class="stats">
                            <div class="stat-card">
                                <div class="stat-value">${stats.total_participants || 0}</div>
                                <div class="stat-label">Active Users</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${formatNumber(stats.trans_points || 0)}</div>
                                <div class="stat-label">Activity Points</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${formatNumber(stats.event_points || 0)}</div>
                                <div class="stat-label">Event Points</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${formatNumber((stats.trans_points || 0) + (stats.event_points || 0))}</div>
                                <div class="stat-label">Total Points</div>
                            </div>
                        </div>
                    `;
                    
                    // Update rankings
                    let rankingsHTML = '';
                    
                    // Individual Rankings
                    rankingsHTML += createRankingCard(
                        '👤 Top Individuals',
                        data.cycleTopIndividuals,
                        (item) => item.fullname,
                        (item) => `${item.cycle_points} pts`
                    );
                    
                    // Barangay Rankings
                    rankingsHTML += createRankingCard(
                        '🏘️ Top Barangays',
                        data.cycleTopBarangays,
                        (item) => item.barangay,
                        (item) => `${item.cycle_points} pts (${item.active_members} members)`
                    );
                    
                    // Municipality Rankings
                    rankingsHTML += createRankingCard(
                        '🏙️ Top Municipalities',
                        data.cycleTopMunicipalities,
                        (item) => item.city_municipality,
                        (item) => `${item.cycle_points} pts (${item.active_members} members)`
                    );
                    
                    // Organization Rankings
                    rankingsHTML += createRankingCard(
                        '🏢 Top Organizations',
                        data.cycleTopOrganizations,
                        (item) => item.organization,
                        (item) => `${item.cycle_points} pts (${item.active_members} members)`
                    );
                    
                    document.getElementById('rankings-container').innerHTML = rankingsHTML;
                    
                    console.log('✅ Rankings refreshed successfully (Refresh #' + refreshCount + ')');
                })
                .catch(error => {
                    console.error('❌ Error loading rankings:', error);
                    document.getElementById('cycle-info').innerHTML = `
                        <div class="error">
                            <strong>❌ Error Loading Data</strong><br>
                            ${error.message}
                        </div>
                    `;
                });
        }
        
        function createRankingCard(title, items, getNameFn, getDetailsFn) {
            if(!items || items.length === 0) {
                return `
                    <div class="ranking-card">
                        <h3>${title}</h3>
                        <div style="text-align:center; padding:20px; color:#999;">
                            No data available yet
                        </div>
                    </div>
                `;
            }
            
            let html = `
                <div class="ranking-card">
                    <h3>${title}</h3>
            `;
            
            items.forEach((item, index) => {
                html += `
                    <div class="ranking-item">
                        <span class="rank">#${index + 1}</span>
                        <span class="name">${getNameFn(item)}</span>
                        <span class="points">${getDetailsFn(item)}</span>
                    </div>
                `;
            });
            
            html += `</div>`;
            return html;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function formatNumber(num) {
            return parseInt(num || 0).toLocaleString();
        }
        
        function updateLastRefreshTime() {
            const now = new Date();
            document.getElementById('last-updated').textContent = 
                `Last updated: ${now.toLocaleTimeString()} (Refresh #${refreshCount})`;
        }
        
        // Load immediately
        loadCycleRankings();
        
        // Auto-refresh every 30 seconds (like "Earned this week")
        setInterval(loadCycleRankings, 30000);
        
        console.log('🚀 Real-time cycle rankings started! Auto-refreshes every 30 seconds.');
    </script>
</body>
</html>
