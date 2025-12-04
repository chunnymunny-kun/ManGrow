<?php
include 'database.php';
include 'badge_system.php';

// Get all badges for demonstration
$all_badges = BadgeSystem::getAllBadges();
$categories = BadgeSystem::getCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge System Preview - ManGrow</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        h1 {
            text-align: center;
            color: #2c5e3f;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }

        .category-section {
            margin-bottom: 40px;
        }

        .category-title {
            color: #2c5e3f;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            border-radius: 12px;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
        }

        .badge:hover {
            transform: scale(1.05) translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .badge-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .badge-icon i {
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .badge p {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.3;
        }

        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .stats h3 {
            margin: 0 0 10px 0;
            color: #2c5e3f;
        }

        .demo-notice {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .demo-notice i {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-trophy"></i> ManGrow Badge System</h1>
        <p class="subtitle">Complete badge collection with images, descriptions, and interactive modals</p>

        <div class="demo-notice">
            <i class="fas fa-info-circle"></i>
            <strong>Demo Mode:</strong> Click any badge to view its details in a modal popup!
        </div>

        <div class="stats">
            <h3>Badge System Statistics</h3>
            <p><strong><?php echo BadgeSystem::getTotalBadgeCount(); ?></strong> total badges available across <strong><?php echo count($categories); ?></strong> categories</p>
        </div>

        <?php foreach ($categories as $category): ?>
            <?php $category_badges = BadgeSystem::getBadgesByCategory($category); ?>
            <div class="category-section">
                <h2 class="category-title">
                    <i class="fas fa-layer-group"></i>
                    <?php echo htmlspecialchars($category); ?> 
                    <span style="font-size: 0.8em; color: #999;">(<?php echo count($category_badges); ?> badges)</span>
                </h2>
                <div class="badges-grid">
                    <?php foreach ($category_badges as $badge): ?>
                        <?php echo BadgeSystem::generateBadgeHTML($badge, true); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Badge Modal -->
    <?php echo generateBadgeModal(); ?>

    <!-- Badge System CSS -->
    <?php echo generateBadgeModalCSS(); ?>

    <!-- Badge System JavaScript -->
    <?php echo generateBadgeModalJS($connection); ?>
</body>
</html>
