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

// Get user information
$userQuery = "SELECT fullname, email FROM accountstbl WHERE account_id = ?";
$stmt = $connection->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's completed events
$completedEvents = EventQRSystem::getUserCompletedEventsList($userId);
$totalCompleted = EventQRSystem::getUserCompletedEventsCount($userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Completed Events - ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .header h1 {
            color: #2c5e3f;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .events-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .events-table th {
            background: #2c5e3f;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        .events-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .events-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-checked-in {
            background: #fff3cd;
            color: #856404;
        }

        .rewards-info {
            font-size: 0.9rem;
        }

        .points {
            color: #4CAF50;
            font-weight: bold;
        }

        .badges {
            color: #FF9800;
            font-weight: bold;
        }

        .no-events {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-events i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }

        .back-link {
            display: inline-block;
            color: #4CAF50;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #45a049;
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }

            .events-table {
                font-size: 0.9rem;
            }

            .events-table th,
            .events-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-trophy"></i> My Completed Events</h1>
            <p>Welcome back, <?= htmlspecialchars($user['fullname']) ?>!</p>
        </div>

        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $totalCompleted ?></div>
                <div class="stat-label">Events Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= array_sum(array_column($completedEvents, 'points_awarded')) ?>
                </div>
                <div class="stat-label">Total Points Earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($completedEvents, function($e) { return !empty($e['badges_awarded']); })) ?>
                </div>
                <div class="stat-label">Events with Badges</div>
            </div>
        </div>

        <?php if (empty($completedEvents)): ?>
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <h3>No Completed Events Yet</h3>
                <p>Start participating in events and check in to build your event history!</p>
            </div>
        <?php else: ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> Event</th>
                        <th><i class="fas fa-map-marker-alt"></i> Venue</th>
                        <th><i class="fas fa-clock"></i> Check-in</th>
                        <th><i class="fas fa-clock"></i> Check-out</th>
                        <th><i class="fas fa-star"></i> Status</th>
                        <th><i class="fas fa-gift"></i> Rewards</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedEvents as $event): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($event['subject']) ?></strong><br>
                                <small><?= date('M j, Y', strtotime($event['start_date'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($event['venue']) ?></td>
                            <td>
                                <?php if ($event['checkin_time']): ?>
                                    <?= date('M j, Y g:i A', strtotime($event['checkin_time'])) ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($event['checkout_time']): ?>
                                    <?= date('M j, Y g:i A', strtotime($event['checkout_time'])) ?>
                                <?php else: ?>
                                    <span style="color: #999;">Not checked out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($event['checkout_time']): ?>
                                    <span class="status-badge status-completed">Completed</span>
                                <?php else: ?>
                                    <span class="status-badge status-checked-in">Checked In</span>
                                <?php endif; ?>
                            </td>
                            <td class="rewards-info">
                                <?php if ($event['points_awarded'] > 0): ?>
                                    <div class="points">
                                        <i class="fas fa-coins"></i> <?= $event['points_awarded'] ?> points
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['badges_awarded'])): ?>
                                    <div class="badges">
                                        <i class="fas fa-medal"></i> <?= htmlspecialchars($event['badges_awarded']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!$event['points_awarded'] && empty($event['badges_awarded'])): ?>
                                    <span style="color: #999;">No rewards</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="events.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Events
        </a>
    </div>
</body>
</html>
