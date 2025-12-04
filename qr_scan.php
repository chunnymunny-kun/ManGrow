<?php
session_start();
include 'database.php';
include 'event_qr_system.php';

// Initialize QR system
EventQRSystem::init($connection);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Please login to scan QR codes'
    ];
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$scanResult = null;
$showScanner = true;

// Process QR scan if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    error_log("QR Scan: Processing token: " . $token . " for user ID: " . $userId);
    $scanResult = EventQRSystem::processQRScan($token, $userId);
    error_log("QR Scan Result: " . json_encode($scanResult));
    $showScanner = false;
}

// Process manual token input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_token'])) {
    $token = trim($_POST['manual_token']);
    if (!empty($token)) {
        error_log("QR Scan: Processing manual token: " . $token . " for user ID: " . $userId);
        $scanResult = EventQRSystem::processQRScan($token, $userId);
        error_log("QR Scan Manual Result: " . json_encode($scanResult));
        $showScanner = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event QR Scanner - ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root{
            --base-clr: #123524;
            --line-clr: indigo;
            --secondarybase-clr: lavenderblush;
            --text-clr: #222533;
            --accent-clr: #EFE3C2;
            --secondary-text-clr: #123524;
            --placeholder-text-clr:#3E7B27;
            --event-clr:#FFFDF6;
        }

        body {
            display:block;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--accent-clr) 0%, var(--secondarybase-clr) 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            height:fit-content;
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: var(--base-clr);
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .header h1 i, .manual-input h3 i{
            color: var(--placeholder-text-clr);
        }

        .header p {
            color: var(--text-clr);
            font-size: 1.1rem;
        }

        .scanner-container {
            margin: 30px 0;
            text-align: center;
        }

        #qr-reader {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .manual-input {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .manual-input h3 {
            color: var(--base-clr);
            margin-bottom: 15px;
            text-align: center;
        }

        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .input-group input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--base-clr);
        }

        .btn {
            background: var(--base-clr);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: var(--placeholder-text-clr);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #1e3a8a;
            color: azure;
        }

        .btn-secondary:hover {
            background: #1e40af;
        }

        .result-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }

        .result-success {
            background: linear-gradient(135deg, var(--base-clr), var(--placeholder-text-clr));
            color: white;
        }

        .result-error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }

        .result-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .result-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .result-message {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .rewards-section {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .reward-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .reward-label {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reward-value {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .badge-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .instructions {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }

        .instructions h4 {
            color: #1976D2;
            margin-bottom: 10px;
        }

        .instructions ul {
            margin: 0;
            padding-left: 20px;
        }

        .instructions li {
            margin: 5px 0;
            color: var(--text-clr);
        }

        .back-link {
            display: inline-block;
            color: var(--base-clr);
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--placeholder-text-clr);
            transform: translateX(5px);
        }

        .scanner-status {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
        }

        .scanner-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .scanner-inactive {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .input-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="events.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Events
        </a>
        <div class="header">
            <h1><i class="fas fa-qrcode"></i> Event QR Scanner</h1>
            <p>Scan the QR code to check in or check out of events</p>
        </div>

        <?php if ($scanResult): ?>
            <!-- Show scan result -->
            <div class="result-container <?= $scanResult['success'] ? 'result-success' : 'result-error' ?>">
                <div class="result-icon">
                    <i class="fas <?= $scanResult['success'] ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                </div>
                <div class="result-title"><?= $scanResult['success'] ? 'Success!' : 'Error' ?></div>
                <div class="result-message"><?= htmlspecialchars($scanResult['message']) ?></div>
                
                <?php if ($scanResult['success'] && isset($scanResult['event_name'])): ?>
                    <div class="rewards-section">
                        <h4>Event: <?= htmlspecialchars($scanResult['event_name']) ?></h4>
                        
                        <?php if (isset($scanResult['points_awarded']) && $scanResult['points_awarded'] > 0): ?>
                            <div class="reward-item">
                                <div class="reward-label">
                                    <i class="fas fa-coins" style="color: gold;"></i>
                                    <span>Eco Points Earned</span>
                                </div>
                                <div class="reward-value">+<?= $scanResult['points_awarded'] ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($scanResult['badges_awarded']) && !empty($scanResult['badges_awarded'])): ?>
                            <div class="reward-item">
                                <div class="reward-label">
                                    <i class="fas fa-trophy" style="color: gold;"></i>
                                    <span>New Badges</span>
                                </div>
                                <div class="badge-list">
                                    <?php foreach ($scanResult['badges_awarded'] as $badge): ?>
                                        <span class="badge-item"><?= htmlspecialchars($badge) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($scanResult['total_events_completed'])): ?>
                            <div class="reward-item">
                                <div class="reward-label">
                                    <i class="fas fa-calendar-check" style="color: var(--base-clr);"></i>
                                    <span>Total Events Completed</span>
                                </div>
                                <div class="reward-value"><?= $scanResult['total_events_completed'] ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($scanResult['next_step'])): ?>
                            <div class="result-message" style="margin-top: 15px; font-style: italic;">
                                <?= htmlspecialchars($scanResult['next_step']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <button class="btn btn-secondary" onclick="window.location.href = 'qr_scan.php'">
                    <i class="fas fa-redo"></i> Scan Another QR Code
                </button>
            </div>
        <?php else: ?>
            <!-- Show scanner interface -->
            <?php if ($showScanner): ?>
                <div class="instructions">
                    <h4><i class="fas fa-info-circle"></i> How to Use</h4>
                    <ul>
                        <li><strong>Check-in:</strong> Scan the check-in QR code when you arrive at the event</li>
                        <li><strong>Check-out:</strong> Scan the check-out QR code when the event ends to receive eco points</li>
                        <li>You must check in first before you can check out</li>
                        <li>Both QR codes will be displayed by event organizers at the venue</li>
                    </ul>
                    <p style="margin:10px 0 0 0;"><strong>Note:</strong> Please request assistance in case you got a problem with scanning the QR codes</p>
                </div>

                <div class="scanner-container">
                    <div id="scanner-status" class="scanner-status scanner-inactive">
                        <i class="fas fa-camera"></i> Click "Start Scanner" to begin
                    </div>
                    
                    <div id="qr-reader" style="display: none;"></div>
                    
                    <button id="start-scanner" class="btn">
                        <i class="fas fa-camera"></i> Start Scanner
                    </button>
                    <button id="stop-scanner" class="btn btn-secondary" style="display: none;">
                        <i class="fas fa-stop"></i> Stop Scanner
                    </button>
                </div>

                <div class="manual-input">
                    <h3><i class="fas fa-keyboard"></i> Manual Token Entry</h3>
                    <p style="text-align: center; color: var(--text-clr); margin-bottom: 15px;">
                        If camera scanning doesn't work, enter the token manually:
                    </p>
                    <form method="POST">
                        <div class="input-group">
                            <input type="text" name="manual_token" placeholder="Enter QR token here..." required>
                            <button type="submit" class="btn">
                                <i class="fas fa-check"></i> Submit
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        let isProcessingScan = false;

        function startScanner() {
            if (isScanning) return;

            const startBtn = document.getElementById('start-scanner');
            const stopBtn = document.getElementById('stop-scanner');
            const readerDiv = document.getElementById('qr-reader');
            const statusDiv = document.getElementById('scanner-status');

            // Show reader and update UI
            readerDiv.style.display = 'block';
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
            statusDiv.className = 'scanner-status scanner-active';
            statusDiv.innerHTML = '<i class="fas fa-camera"></i> Scanner active - Point camera at QR code';

            // Initialize scanner
            html5QrcodeScanner = new Html5Qrcode("qr-reader");
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };

            html5QrcodeScanner.start(
                { facingMode: "environment" }, // Use back camera
                config,
                (decodedText) => {
                    // Prevent multiple scans in quick succession
                    if (isProcessingScan) return;
                    isProcessingScan = true;
                    
                    // Success callback
                    console.log('QR Code detected:', decodedText);
                    
                    // Extract token from URL or use the whole text as token
                    let token = decodedText;
                    
                    // If it's a full URL, extract the token parameter
                    if (decodedText.includes('qr_scan.php')) {
                        const urlParts = decodedText.split('?');
                        if (urlParts.length > 1) {
                            const urlParams = new URLSearchParams(urlParts[1]);
                            token = urlParams.get('token') || decodedText;
                        }
                    } else if (decodedText.includes('token=')) {
                        // Handle case where it's just the query string
                        const urlParams = new URLSearchParams(decodedText);
                        token = urlParams.get('token') || decodedText;
                    }
                    
                    console.log('Extracted token:', token);
                    
                    // Redirect to process the token
                    window.location.href = `qr_scan.php?token=${encodeURIComponent(token)}`;
                    
                    // Prevent further scanning for 3 seconds
                    setTimeout(() => {
                        isProcessingScan = false;
                        stopScanner();
                    }, 3000);
                },
                (errorMessage) => {
                    // Error callback (typically when no QR code is detected)
                    // We can ignore these as they're frequent during scanning
                }
            ).catch(err => {
                console.error('Unable to start scanning:', err);
                statusDiv.className = 'scanner-status scanner-inactive';
                statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Camera access denied or not available';
                stopScanner();
            });

            isScanning = true;
        }

        function stopScanner() {
            if (!isScanning || !html5QrcodeScanner) return;

            const startBtn = document.getElementById('start-scanner');
            const stopBtn = document.getElementById('stop-scanner');
            const readerDiv = document.getElementById('qr-reader');
            const statusDiv = document.getElementById('scanner-status');

            html5QrcodeScanner.stop().then(() => {
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
                
                // Update UI
                readerDiv.style.display = 'none';
                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
                statusDiv.className = 'scanner-status scanner-inactive';
                statusDiv.innerHTML = '<i class="fas fa-camera"></i> Click "Start Scanner" to begin';
                
                isScanning = false;
                isProcessingScan = false;
            }).catch(err => {
                console.error('Error stopping scanner:', err);
            });
        }

        // Event listeners
        document.getElementById('start-scanner').addEventListener('click', startScanner);
        document.getElementById('stop-scanner').addEventListener('click', stopScanner);

        // Auto-start scanner if URL has auto_scan parameter
        if (new URLSearchParams(window.location.search).has('auto_scan')) {
            window.addEventListener('load', () => {
                setTimeout(startScanner, 1000);
            });
        }
    </script>
</body>
</html>
