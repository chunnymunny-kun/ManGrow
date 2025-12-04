<?php
// Add Mangrove Guardian and Report badges to the system
include 'database.php';

// Create badges if they don't exist
$badges = [
    [
        'name' => 'Mangrove Guardian',
        'description' => 'Awarded for vigilantly protecting mangrove ecosystems by reporting your first illegal activity.',
        'instructions' => 'Report any illegal activities threatening mangrove areas to earn this badge.',
        'icon' => 'fas fa-shield-alt',
        'color' => '#27ae60',
        'category' => 'Environmental'
    ],
    [
        'name' => 'Watchful Eye',
        'description' => 'Dedicated protector who has reported 5 illegal activities.',
        'instructions' => 'Continue monitoring and reporting illegal activities to protect our mangroves.',
        'icon' => 'fas fa-eye',
        'color' => '#3498db',
        'category' => 'Environmental'
    ],
    [
        'name' => 'Vigilant Protector',
        'description' => 'Committed guardian who has reported 10 illegal activities.',
        'instructions' => 'Your vigilance helps preserve our precious mangrove ecosystems.',
        'icon' => 'fas fa-binoculars',
        'color' => '#9b59b6',
        'category' => 'Environmental'
    ],
    [
        'name' => 'Conservation Champion',
        'description' => 'Exceptional defender who has reported 20 illegal activities.',
        'instructions' => 'Your dedication to conservation is truly remarkable.',
        'icon' => 'fas fa-trophy',
        'color' => '#f39c12',
        'category' => 'Environmental'
    ],
    [
        'name' => 'Ecosystem Sentinel',
        'description' => 'Elite guardian who has reported 50 illegal activities.',
        'instructions' => 'You are a true sentinel of our mangrove ecosystems.',
        'icon' => 'fas fa-crown',
        'color' => '#e74c3c',
        'category' => 'Environmental'
    ],
    [
        'name' => 'Mangrove Legend',
        'description' => 'Legendary protector who has reported 100 illegal activities.',
        'instructions' => 'Your legendary status as a mangrove protector is unmatched.',
        'icon' => 'fas fa-gem',
        'color' => '#8e44ad',
        'category' => 'Environmental'
    ]
];

foreach ($badges as $badge) {
    // Check if badge already exists
    $checkQuery = "SELECT badge_id FROM badgestbl WHERE badge_name = ?";
    $stmt = $connection->prepare($checkQuery);
    $stmt->bind_param("s", $badge['name']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Insert new badge
        $insertQuery = "INSERT INTO badgestbl (badge_name, description, instructions, icon_class, color, category, is_active, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bind_param("ssssss", $badge['name'], $badge['description'], $badge['instructions'], $badge['icon'], $badge['color'], $badge['category']);
        
        if ($stmt->execute()) {
            echo "✅ Added badge: " . $badge['name'] . "\n";
        } else {
            echo "❌ Failed to add badge: " . $badge['name'] . " - " . $stmt->error . "\n";
        }
    } else {
        echo "⚠️ Badge already exists: " . $badge['name'] . "\n";
    }
    $stmt->close();
}

echo "\n🎉 Badge setup complete!\n";
?>
