<?php
session_start();
include 'database.php';
include 'event_qr_system.php';

// Initialize QR system
EventQRSystem::init($connection);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if (!$eventId) {
    header("Location: events.php");
    exit();
}

// Get event details and check permissions
$eventQuery = "SELECT e.*, a.fullname as author_name 
               FROM eventstbl e 
               JOIN accountstbl a ON e.author = a.account_id 
               WHERE e.event_id = ?";
$stmt = $connection->prepare($eventQuery);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'Event not found'];
    header("Location: events.php");
    exit();
}

$event = $result->fetch_assoc();

// Check if user can manage this event (author or admin)
$canManage = false;
if ($event['author'] == $userId) {
    $canManage = true;
} elseif (isset($_SESSION['accessrole']) && in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
    $canManage = true;
}

if (!$canManage) {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'You do not have permission to manage this event'];
    header("Location: events.php");
    exit();
}

$message = '';
$messageType = '';

// Handle QR code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_qr') {
        $result = EventQRSystem::generateEventQRCodes($eventId, $userId);
        if ($result['success']) {
            $message = 'QR codes generated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $result['message'];
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'deactivate_qr') {
        if (EventQRSystem::deactivateEventQRCodes($eventId)) {
            $message = 'QR codes deactivated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deactivating QR codes';
            $messageType = 'error';
        }
    }
}

// Get existing QR codes
$qrCodes = EventQRSystem::getEventQRCodes($eventId);

error_log("QR Codes: " . print_r($qrCodes, true));
// Get attendance statistics
$attendanceStats = EventQRSystem::getEventAttendanceStats($eventId);

// Get detailed attendee list with scan information
$attendeesQuery = "SELECT 
    a.account_id,
    a.fullname,
    COALESCE(NULLIF(a.organization, ''), 'N/A') as organization,
    a.profile_thumbnail,
    ea.checkin_time,
    ea.checkout_time,
    ea.attendance_status,
    ea.points_awarded,
    CASE 
        WHEN ea.checkin_time IS NOT NULL AND ea.checkout_time IS NOT NULL 
        THEN TIMESTAMPDIFF(SECOND, ea.checkin_time, ea.checkout_time)
        ELSE NULL
    END as duration_seconds
FROM event_attendance ea
JOIN accountstbl a ON ea.user_id = a.account_id
WHERE ea.event_id = ?
ORDER BY ea.checkin_time DESC, a.fullname ASC";

$stmt = $connection->prepare($attendeesQuery);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$attendeesResult = $stmt->get_result();
$attendees = $attendeesResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event QR Management - <?= htmlspecialchars($event['subject']) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- QRious library for QR code generation -->
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
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
            background: var(--accent-clr);
            margin: 0;
            padding: 20px;
            height: 100%;
            box-sizing: border-box;
        }

        .container {
            height: fit-content;
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .header h1 {
            color: var(--base-clr);
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .header h1 i{
            color: var(--placeholder-text-clr)
        }

        .event-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .event-info i, .info-item i{
            color: var(--placeholder-text-clr) !important;
        }

        .event-info h3 {
            color: var(--base-clr);
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item i {
            color: var(--base-clr);
            width: 20px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--base-clr), var(--placeholder-text-clr));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .qr-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .qr-section h3 {
            color: var(--base-clr);
            margin-bottom: 20px;
            text-align: center;
            > i {
                color: var(--placeholder-text-clr);
            }
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .qr-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .qr-card i{
            color: var(--placeholder-text-clr) !important;
        }

        .qr-card button i{
            color: azure !important;
        }

        .qr-card h4 {
            color: var(--base-clr);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            border: 2px dashed #ddd;
        }

        .qr-details {
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--text-clr);
        }

        .qr-details div {
            margin: 5px 0;
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
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }

        .btn:hover {
            background: var(--placeholder-text-clr);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #f44336;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .btn-secondary {
            background: #1e3a8a;
            color: azure;
        }

        .btn-secondary:hover {
            background: #1e40af;
        }

        .btn-download {
            background: var(--base-clr);
            color: azure;
        }

        .btn-download:hover {
            background: var(--placeholder-text-clr);
        }

        .no-qr-message {
            text-align: center;
            padding: 40px;
            color: var(--text-clr);
        }

        .no-qr-message i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }

        .action-buttons {
            text-align: center;
            margin: 20px 0;
        }

        .instructions {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }

        .instructions h4 {
            color: #1976D2;
            margin-bottom: 15px;
        }

        .instructions ol {
            margin: 0;
            padding-left: 20px;
        }

        .instructions li {
            margin: 8px 0;
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

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }

            .qr-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .qr-usage-guide {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .qr-usage-guide h4 {
            color: #856404;
            margin-bottom: 15px;
        }

        .usage-step {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 5px;
        }

        .usage-step strong {
            color: var(--base-clr);
        }

        /* Attendees List Styles */
        .attendees-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
        }

        .attendees-section h3 {
            color: var(--base-clr);
            margin-bottom: 20px;
            text-align: center;
        }

        .attendees-section h3 i {
            color: var(--placeholder-text-clr);
        }

        .attendees-table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .attendees-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendees-table thead {
            background: var(--base-clr);
            color: white;
        }

        .attendees-table thead th {
            padding: 15px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 2px solid var(--placeholder-text-clr);
        }

        .attendees-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background 0.2s ease;
        }

        .attendees-table tbody tr:hover {
            background: #f8f9fa;
        }

        .attendees-table tbody tr:last-child {
            border-bottom: none;
        }

        .attendees-table tbody td {
            padding: 15px 10px;
            color: var(--text-clr);
            vertical-align: middle;
        }

        .attendee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .attendee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-clr);
        }

        .attendee-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-clr);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--placeholder-text-clr);
            font-weight: bold;
            border: 2px solid var(--placeholder-text-clr);
        }

        .attendee-details {
            display: flex;
            flex-direction: column;
        }

        .attendee-name {
            font-weight: 600;
            color: var(--base-clr);
            margin-bottom: 2px;
        }

        .attendee-org {
            font-size: 0.85rem;
            color: #666;
        }

        .time-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            background: #e8f5e9;
            color: #2e7d32;
        }

        .time-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .time-badge.completed {
            background: #d4edda;
            color: #155724;
        }

        .duration-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--base-clr), var(--placeholder-text-clr));
            color: white;
        }

        .duration-badge i {
            font-size: 0.9rem;
        }

        .no-attendees {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .no-attendees i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }

        .attendees-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .attendees-stat-item {
            text-align: center;
        }

        .attendees-stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--base-clr);
        }

        .attendees-stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        /* Filters Section */
        .attendees-filters {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .search-input,
        .filter-select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .search-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--placeholder-text-clr);
            box-shadow: 0 0 0 3px rgba(62, 123, 39, 0.1);
        }

        .btn-reset {
            width: 100%;
            padding: 10px 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        /* Pagination Controls */
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 25px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .btn-pagination {
            padding: 10px 20px;
            background: var(--base-clr);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-pagination:hover:not(:disabled) {
            background: var(--placeholder-text-clr);
            transform: translateY(-2px);
        }

        .btn-pagination:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .page-info {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-clr);
            padding: 0 10px;
        }

        /* Loading State */
        .loading-overlay {
            position: relative;
        }

        .loading-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 11;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--base-clr);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .attendees-table-container {
                overflow-x: auto;
            }

            .attendees-table {
                min-width: 800px;
            }

            .attendees-stats {
                flex-direction: column;
                gap: 15px;
            }

            .attendees-section {
                padding: 15px;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .pagination-controls {
                flex-direction: column;
                gap: 10px;
            }

            .btn-pagination {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main>
    <div class="container">
        <a href="events.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Events
        </a>
        <div class="header">
            <h1><i class="fas fa-qrcode"></i> Event QR Management</h1>
            <p>Generate and manage QR codes for event attendance tracking</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="event-info">
            <h3><i class="fas fa-calendar-alt"></i> Event Details</h3>
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-heading"></i>
                    <span><strong>Title:</strong> <?= htmlspecialchars($event['subject']) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <span><strong>Organizer:</strong> <?= htmlspecialchars($event['author_name']) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar"></i>
                    <span><strong>Start:</strong> <?= date('M j, Y g:i A', strtotime($event['start_date'])) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-check"></i>
                    <span><strong>End:</strong> <?= date('M j, Y g:i A', strtotime($event['end_date'])) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><strong>Location:</strong> <?= htmlspecialchars($event['venue']) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-coins"></i>
                    <span><strong>Eco Points:</strong> <?= $event['eco_points'] ?? 50 ?> points</span>
                </div>
            </div>
        </div>

        <!-- Attendance Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $attendanceStats['checked_in'] ?? 0 ?></div>
                <div class="stat-label">Checked In</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $attendanceStats['completed'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $attendanceStats['total_points_awarded'] ?? 0 ?></div>
                <div class="stat-label">Points Awarded</div>
            </div>
        </div>

        <!-- QR Codes Section -->
        <div class="qr-section">
            <?php if (empty($qrCodes)): ?>
                <div class="no-qr-message">
                    <i class="fas fa-qrcode"></i>
                    <h3>No QR Codes Generated Yet</h3>
                    <p>Generate QR codes to enable attendance tracking for this event</p>
                </div>
                
                <div class="action-buttons">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="generate_qr">
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Generate QR Codes
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <h3><i class="fas fa-qrcode"></i> Generated QR Codes</h3>
                
                <div class="qr-grid">
                    <?php if (isset($qrCodes['checkin'])): ?>
                        <div class="qr-card">
                            <h4><i class="fas fa-sign-in-alt" style="color: var(--base-clr);"></i> Check-In QR Code</h4>
                            <div class="qr-code-container">
                                <div id="checkin-qr"></div>
                            </div>
                            <div class="qr-details">
                                <div><strong>Purpose:</strong> Participants scan when arriving</div>
                                <div><strong>Token:</strong> <code><?= substr($qrCodes['checkin']['token'], 0, 16) ?>...</code></div>
                                <div><strong>URL:</strong> <small><?= htmlspecialchars($qrCodes['checkin']['url']) ?></small></div>
                                <div><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($qrCodes['checkin']['created_at'])) ?></div>
                            </div>
                            <button class="btn btn-download" onclick="downloadQR('checkin-qr', 'checkin-qr-<?= $eventId ?>')">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button class="btn btn-secondary" onclick="printQR('checkin-qr')">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($qrCodes['checkout'])): ?>
                        <div class="qr-card">
                            <h4><i class="fas fa-sign-out-alt" style="color: var(--placeholder-text-clr);"></i> Check-Out QR Code</h4>
                            <div class="qr-code-container">
                                <div id="checkout-qr"></div>
                            </div>
                            <div class="qr-details">
                                <div><strong>Purpose:</strong> Participants scan when leaving</div>
                                <div><strong>Token:</strong> <code><?= substr($qrCodes['checkout']['token'], 0, 16) ?>...</code></div>
                                <div><strong>URL:</strong> <small><?= htmlspecialchars($qrCodes['checkout']['url']) ?></small></div>
                                <div><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($qrCodes['checkout']['created_at'])) ?></div>
                            </div>
                            <button class="btn btn-download" onclick="downloadQR('checkout-qr', 'checkout-qr-<?= $eventId ?>')">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button class="btn btn-secondary" onclick="printQR('checkout-qr')">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate these QR codes? This action cannot be undone.')">
                        <input type="hidden" name="action" value="deactivate_qr">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban"></i> Deactivate QR Codes
                        </button>
                    </form>
                    
                    <button class="btn btn-secondary" onclick="showInstructions()">
                        <i class="fas fa-question-circle"></i> Usage Instructions
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Usage Instructions -->
        <div class="qr-usage-guide" id="usage-guide" style="display: none;">
            <h4><i class="fas fa-book"></i> QR Code Usage Instructions</h4>
            
            <div class="usage-step">
                <strong>Step 1:</strong> Print or display both QR codes at your event venue
            </div>
            
            <div class="usage-step">
                <strong>Step 2:</strong> When participants arrive, have them scan the <strong>Check-In QR Code</strong> using their smartphones
            </div>
            
            <div class="usage-step">
                <strong>Step 3:</strong> When the event ends, have participants scan the <strong>Check-Out QR Code</strong> to receive eco points and badges
            </div>
            
            <div class="usage-step">
                <strong>Important:</strong> Participants must scan both QR codes to receive rewards. Check-in alone will not award points.
            </div>
            
            <div class="usage-step">
                <strong>Backup:</strong> If QR scanning fails, participants can manually enter the token on the scan page
            </div>
        </div>

        <!-- Instructions for first-time users -->
        <div class="instructions">
            <h4><i class="fas fa-lightbulb"></i> How It Works</h4>
            <ol>
                <li><strong>Generate QR Codes:</strong> Click "Generate QR Codes" to create check-in and check-out QR codes for your event</li>
                <li><strong>Display at Event:</strong> Print or display both QR codes prominently at your event venue</li>
                <li><strong>Participant Check-In:</strong> When participants arrive, they scan the check-in QR code with their phone</li>
                <li><strong>Event Completion:</strong> When the event ends, participants scan the check-out QR code</li>
                <li><strong>Automatic Rewards:</strong> Upon check-out, participants automatically receive eco points and any eligible badges</li>
                <li><strong>Track Progress:</strong> Monitor attendance statistics in real-time from this dashboard</li>
            </ol>
        </div>

        <!-- Attendees List Section -->
        <div class="attendees-section">
            <h3><i class="fas fa-users"></i> Event Attendees</h3>
            
            <!-- Filters and Search -->
            <div class="attendees-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <input type="text" id="attendee-search" placeholder="Search by name..." class="search-input">
                    </div>
                    <div class="filter-group">
                        <select id="status-filter" class="filter-select">
                            <option value="all">All Statuses</option>
                            <option value="completed">Completed</option>
                            <option value="checked_in">Checked In Only</option>
                            <option value="not_checked_out">Not Checked Out</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select id="items-per-page" class="filter-select">
                            <option value="5">5 per page</option>
                            <option value="10" selected>10 per page</option>
                            <option value="20">20 per page</option>
                            <option value="50">50 per page</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button id="reset-filters" class="btn-reset">Reset</button>
                    </div>
                </div>
            </div>
            
            <div id="attendees-content">
            <?php if (count($attendees) > 0): ?>
                <!-- Attendees Statistics -->
                <div class="attendees-stats">
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number"><?= count($attendees) ?></div>
                        <div class="attendees-stat-label">Total Attendees</div>
                    </div>
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">
                            <?php 
                                $checkedIn = array_filter($attendees, function($a) { 
                                    return $a['checkin_time'] !== null; 
                                });
                                echo count($checkedIn);
                            ?>
                        </div>
                        <div class="attendees-stat-label">Checked In</div>
                    </div>
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">
                            <?php 
                                $completed = array_filter($attendees, function($a) { 
                                    return $a['checkin_time'] !== null && $a['checkout_time'] !== null; 
                                });
                                echo count($completed);
                            ?>
                        </div>
                        <div class="attendees-stat-label">Completed</div>
                    </div>
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">
                            <?php 
                                $totalHours = 0;
                                foreach ($attendees as $attendee) {
                                    if ($attendee['duration_seconds']) {
                                        $totalHours += $attendee['duration_seconds'] / 3600;
                                    }
                                }
                                echo number_format($totalHours, 1);
                            ?>h
                        </div>
                        <div class="attendees-stat-label">Total Hours</div>
                    </div>
                </div>

                <!-- Attendees Table -->
                <div class="attendees-table-container">
                    <table class="attendees-table">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th>Organization</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $attendee): ?>
                                <tr>
                                    <td>
                                        <div class="attendee-info">
                                            <?php if (!empty($attendee['profile_thumbnail'])): ?>
                                                <img src="<?= htmlspecialchars($attendee['profile_thumbnail']) ?>" 
                                                     alt="<?= htmlspecialchars($attendee['fullname']) ?>" 
                                                     class="attendee-avatar">
                                            <?php else: ?>
                                                <div class="attendee-avatar-placeholder">
                                                    <?= strtoupper(substr($attendee['fullname'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="attendee-details">
                                                <span class="attendee-name"><?= htmlspecialchars($attendee['fullname']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="attendee-org"><?= htmlspecialchars($attendee['organization']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($attendee['checkin_time']): ?>
                                            <span class="time-badge completed">
                                                <i class="fas fa-sign-in-alt"></i>
                                                <?= date('M j, g:i A', strtotime($attendee['checkin_time'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="time-badge pending">Not checked in</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attendee['checkout_time']): ?>
                                            <span class="time-badge completed">
                                                <i class="fas fa-sign-out-alt"></i>
                                                <?= date('M j, g:i A', strtotime($attendee['checkout_time'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="time-badge pending">Not checked out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attendee['duration_seconds']): ?>
                                            <?php 
                                                $hours = floor($attendee['duration_seconds'] / 3600);
                                                $minutes = floor(($attendee['duration_seconds'] % 3600) / 60);
                                            ?>
                                            <span class="duration-badge">
                                                <i class="fas fa-clock"></i>
                                                <?php if ($hours > 0): ?>
                                                    <?= $hours ?>h <?= $minutes ?>m
                                                <?php else: ?>
                                                    <?= $minutes ?>m
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 0.85rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-attendees">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Attendees Yet</h4>
                    <p>Attendees will appear here once they scan the QR codes</p>
                </div>
            <?php endif; ?>
            </div>
            
            <!-- Pagination Controls -->
            <div class="pagination-controls" id="pagination-controls" style="display: none;">
                <button id="prev-page" class="btn-pagination" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span id="page-info" class="page-info">Page 1 of 1</span>
                <button id="next-page" class="btn-pagination" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    </main>
    <script>
        // Generate QR codes when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('QR Management page loaded, checking for QR codes...');
            
            <?php if (isset($qrCodes['checkin'])): ?>
                console.log('Generating check-in QR code for: <?= addslashes($qrCodes['checkin']['url']) ?>');
                // Pass the URL directly to the function
                generateQRCode('checkin-qr', '<?= addslashes($qrCodes['checkin']['url']) ?>');
            <?php else: ?>
                console.log('No check-in QR code data available');
            <?php endif; ?>
            
            <?php if (isset($qrCodes['checkout'])): ?>
                console.log('Generating check-out QR code for: <?= addslashes($qrCodes['checkout']['url']) ?>');
                // Pass the URL directly to the function
                generateQRCode('checkout-qr', '<?= addslashes($qrCodes['checkout']['url']) ?>');
            <?php else: ?>
                console.log('No check-out QR code data available');
            <?php endif; ?>
        });

        function generateQRCode(elementId, url) {
            const element = document.getElementById(elementId);
            if (!element) {
                console.error('Canvas not found for element:', elementId);
                return;
            }
            
            // Check if QRious library is available
            if (typeof QRious === 'undefined') {
                element.innerHTML = '<p style="color: red; padding: 20px;">QRious library not loaded</p>' +
                                '<p>URL: ' + url + '</p>';
                console.error('QRious library not loaded');
                return;
            }
            
            // Clear any existing content
            element.innerHTML = '';
            
            // Create a canvas element
            const canvas = document.createElement('canvas');
            canvas.style.display = 'block';
            canvas.style.margin = '0 auto';
            element.appendChild(canvas);
            
            console.log('Generating QR code for URL:', url);
            
            // Generate QR code using QRious (same as mangrove profile)
            const qr = new QRious({
                element: canvas,
                size: 200,
                value: url,
                level: 'H',
                foreground: '#123524', // Using --base-clr value
                background: '#ffffff'
            });
            
            console.log('QR code generated successfully');
            
            // Add logo after QR code is generated
            setTimeout(() => addLogoToQR(canvas), 100);
        }

        function addLogoToQR(canvas) {
            const ctx = canvas.getContext('2d');
            const logo = new Image();
            
            logo.onload = function () {
                const logoSize = 40;
                const x = (canvas.width - logoSize) / 2;
                const y = (canvas.height - logoSize) / 2;
                
                // Create a white background circle for the logo
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(canvas.width / 2, canvas.height / 2, logoSize / 2 + 3, 0, 2 * Math.PI);
                ctx.fill();
                
                // Draw the logo
                ctx.drawImage(logo, x, y, logoSize, logoSize);
                console.log('Logo added to QR code');
            };
            
            logo.onerror = function() {
                console.log('Logo failed to load, showing QR code without logo');
            };
            
            // Set logo source
            logo.src = 'images/mangrow-logo.png';
        }

        function downloadQR(elementId, filename) {
            const canvas = document.querySelector(`#${elementId} canvas`);
            if (canvas) {
                const link = document.createElement('a');
                link.download = filename + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            } else {
                alert('QR code not found. Please make sure the QR code has been generated first.');
                console.error('Canvas not found for element:', elementId);
            }
        }

        function printQR(elementId) {
            const qrElement = document.getElementById(elementId);
            const canvas = qrElement ? qrElement.querySelector('canvas') : null;
            
            if (canvas) {
                const printWindow = window.open('', '_blank');
                const dataUrl = canvas.toDataURL('image/png');
                
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>QR Code - <?= htmlspecialchars($event['subject']) ?></title>
                            <style>
                                body { 
                                    text-align: center; 
                                    font-family: Arial, sans-serif; 
                                    padding: 20px;
                                    margin: 0;
                                }
                                .qr-container {
                                    margin: 20px auto;
                                    padding: 20px;
                                    border: 2px solid #ccc;
                                    display: inline-block;
                                    border-radius: 10px;
                                }
                                h1 { 
                                    color: #123524; 
                                    margin-bottom: 10px;
                                }
                                .event-info { 
                                    margin: 15px 0; 
                                    color: #666; 
                                    font-size: 14px;
                                }
                                .qr-image {
                                    margin: 20px 0;
                                }
                                .footer {
                                    margin-top: 20px;
                                    font-size: 12px;
                                    color: #999;
                                }
                                @media print {
                                    body { padding: 10px; }
                                }
                            </style>
                        </head>
                        <body>
                            <h1><?= htmlspecialchars($event['subject']) ?></h1>
                            <div class="event-info">
                                <strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($event['start_date'])) ?><br>
                                <strong>Location:</strong> <?= htmlspecialchars($event['venue']) ?>
                            </div>
                            <div class="qr-container">
                                <div class="qr-image">
                                    <img src="${dataUrl}" alt="QR Code" style="max-width: 200px; height: auto;">
                                </div>
                                <p><strong>Scan this QR code to ${elementId.includes('checkin') ? 'check in' : 'check out'}</strong></p>
                            </div>
                            <div class="footer">
                                <p>ManGrow Platform - Environmental Events Management</p>
                            </div>
                        </body>
                    </html>
                `);
                
                printWindow.document.close();
                
                // Wait for content to load then print
                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 1000);
            } else {
                alert('QR code not found. Please make sure the QR code has been generated first.');
                console.error('Canvas not found for element:', elementId);
            }
        }

        function showInstructions() {
            const guide = document.getElementById('usage-guide');
            if (guide.style.display === 'none') {
                guide.style.display = 'block';
            } else {
                guide.style.display = 'none';
            }
        }

        // Attendees Pagination System
        const eventId = <?= $eventId ?>;
        let currentPage = 1;
        let itemsPerPage = 10;
        let searchQuery = '';
        let statusFilter = 'all';
        let totalPages = 1;
        let isLoading = false;

        // Initialize pagination on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializePagination();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search input with debounce
            const searchInput = document.getElementById('attendee-search');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchQuery = this.value;
                    currentPage = 1;
                    loadAttendees();
                }, 500);
            });

            // Status filter
            document.getElementById('status-filter').addEventListener('change', function() {
                statusFilter = this.value;
                currentPage = 1;
                loadAttendees();
            });

            // Items per page
            document.getElementById('items-per-page').addEventListener('change', function() {
                itemsPerPage = parseInt(this.value);
                currentPage = 1;
                loadAttendees();
            });

            // Reset filters
            document.getElementById('reset-filters').addEventListener('click', function() {
                document.getElementById('attendee-search').value = '';
                document.getElementById('status-filter').value = 'all';
                document.getElementById('items-per-page').value = '10';
                searchQuery = '';
                statusFilter = 'all';
                itemsPerPage = 10;
                currentPage = 1;
                loadAttendees();
            });

            // Pagination buttons
            document.getElementById('prev-page').addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    loadAttendees();
                }
            });

            document.getElementById('next-page').addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadAttendees();
                }
            });
        }

        function initializePagination() {
            const attendeesData = <?= json_encode($attendees) ?>;
            if (attendeesData.length > itemsPerPage) {
                document.getElementById('pagination-controls').style.display = 'flex';
            }
            totalPages = Math.ceil(attendeesData.length / itemsPerPage);
            updatePaginationControls();
        }

        function loadAttendees() {
            if (isLoading) return;
            isLoading = true;

            const attendeesContent = document.getElementById('attendees-content');
            attendeesContent.style.position = 'relative';
            attendeesContent.style.opacity = '0.5';

            // Show loading spinner
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.innerHTML = '<div class="spinner"></div>';
            attendeesContent.appendChild(spinner);

            // Simulate API call (in real scenario, this would be an AJAX request)
            setTimeout(() => {
                const allAttendees = <?= json_encode($attendees) ?>;
                let filteredAttendees = filterAttendees(allAttendees);
                totalPages = Math.ceil(filteredAttendees.length / itemsPerPage);
                
                // Adjust current page if needed
                if (currentPage > totalPages && totalPages > 0) {
                    currentPage = totalPages;
                }

                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                const paginatedAttendees = filteredAttendees.slice(startIndex, endIndex);

                renderAttendees(paginatedAttendees, filteredAttendees);
                updatePaginationControls();

                // Show/hide pagination
                const paginationControls = document.getElementById('pagination-controls');
                paginationControls.style.display = filteredAttendees.length > itemsPerPage ? 'flex' : 'none';

                // Remove loading state
                spinner.remove();
                attendeesContent.style.opacity = '1';
                isLoading = false;
            }, 300);
        }

        function filterAttendees(attendees) {
            return attendees.filter(attendee => {
                // Search filter
                const matchesSearch = !searchQuery || 
                    attendee.fullname.toLowerCase().includes(searchQuery.toLowerCase()) ||
                    attendee.organization.toLowerCase().includes(searchQuery.toLowerCase());

                // Status filter
                let matchesStatus = true;
                if (statusFilter === 'completed') {
                    matchesStatus = attendee.checkin_time && attendee.checkout_time;
                } else if (statusFilter === 'checked_in') {
                    matchesStatus = attendee.checkin_time && !attendee.checkout_time;
                } else if (statusFilter === 'not_checked_out') {
                    matchesStatus = attendee.checkin_time && !attendee.checkout_time;
                }

                return matchesSearch && matchesStatus;
            });
        }

        function renderAttendees(attendees, allFiltered) {
            const attendeesContent = document.getElementById('attendees-content');
            
            if (attendees.length === 0) {
                attendeesContent.innerHTML = `
                    <div class="no-attendees">
                        <i class="fas fa-user-slash"></i>
                        <h4>No Attendees Found</h4>
                        <p>Try adjusting your filters or search criteria</p>
                    </div>
                `;
                return;
            }

            // Calculate statistics
            const checkedInCount = allFiltered.filter(a => a.checkin_time).length;
            const completedCount = allFiltered.filter(a => a.checkin_time && a.checkout_time).length;
            const totalHours = allFiltered.reduce((sum, a) => {
                return sum + (a.duration_seconds ? a.duration_seconds / 3600 : 0);
            }, 0);

            let html = `
                <!-- Attendees Statistics -->
                <div class="attendees-stats">
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">${allFiltered.length}</div>
                        <div class="attendees-stat-label">Total Attendees</div>
                    </div>
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">${checkedInCount}</div>
                        <div class="attendees-stat-label">Checked In</div>
                    </div>
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">${completedCount}</div>
                        <div class="attendees-stat-label">Completed</div>
                    </div>
                    <div class="attendees-stat-item">
                        <div class="attendees-stat-number">${totalHours.toFixed(1)}h</div>
                        <div class="attendees-stat-label">Total Hours</div>
                    </div>
                </div>

                <!-- Attendees Table -->
                <div class="attendees-table-container">
                    <table class="attendees-table">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th>Organization</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            attendees.forEach(attendee => {
                const initial = attendee.fullname.charAt(0).toUpperCase();
                const avatar = attendee.profile_thumbnail ? 
                    `<img src="${attendee.profile_thumbnail}" alt="${attendee.fullname}" class="attendee-avatar">` :
                    `<div class="attendee-avatar-placeholder">${initial}</div>`;

                const checkinBadge = attendee.checkin_time ?
                    `<span class="time-badge completed"><i class="fas fa-sign-in-alt"></i> ${formatDateTime(attendee.checkin_time)}</span>` :
                    `<span class="time-badge pending">Not checked in</span>`;

                const checkoutBadge = attendee.checkout_time ?
                    `<span class="time-badge completed"><i class="fas fa-sign-out-alt"></i> ${formatDateTime(attendee.checkout_time)}</span>` :
                    `<span class="time-badge pending">Not checked out</span>`;

                let durationBadge = '<span style="color: #999; font-size: 0.85rem;">—</span>';
                if (attendee.duration_seconds) {
                    const hours = Math.floor(attendee.duration_seconds / 3600);
                    const minutes = Math.floor((attendee.duration_seconds % 3600) / 60);
                    const timeStr = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
                    durationBadge = `<span class="duration-badge"><i class="fas fa-clock"></i> ${timeStr}</span>`;
                }

                html += `
                    <tr>
                        <td>
                            <div class="attendee-info">
                                ${avatar}
                                <div class="attendee-details">
                                    <span class="attendee-name">${attendee.fullname}</span>
                                </div>
                            </div>
                        </td>
                        <td><span class="attendee-org">${attendee.organization}</span></td>
                        <td>${checkinBadge}</td>
                        <td>${checkoutBadge}</td>
                        <td>${durationBadge}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            attendeesContent.innerHTML = html;
        }

        function formatDateTime(dateTimeStr) {
            const date = new Date(dateTimeStr);
            const options = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true };
            return date.toLocaleString('en-US', options);
        }

        function updatePaginationControls() {
            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            const pageInfo = document.getElementById('page-info');

            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        }
    </script>
</body>
</html>
