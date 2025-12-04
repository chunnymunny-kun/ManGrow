<?php
session_start();
include 'database.php';

echo "=== DIRECT DATABASE TEST ===\n";

// Check if join_requests table exists and its structure
echo "1. Checking join_requests table...\n";
$result = $connection->query("SHOW TABLES LIKE 'join_requests'");
if ($result->num_rows == 0) {
    echo "❌ join_requests table DOES NOT EXIST!\n";
    echo "Available tables:\n";
    $result = $connection->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        echo "   - " . $row[0] . "\n";
    }
    exit();
}

echo "✅ join_requests table exists\n";

// Check table structure
echo "\n2. Table structure:\n";
$result = $connection->query("DESCRIBE join_requests");
while ($row = $result->fetch_assoc()) {
    echo "   " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Test direct insert
echo "\n3. Testing direct insert...\n";
try {
    $stmt = $connection->prepare("INSERT INTO join_requests (org_id, user_id, status, requested_at) VALUES (1, 1, 'pending', NOW())");
    if ($stmt->execute()) {
        $id = $connection->insert_id;
        echo "✅ Insert successful, ID: $id\n";
        
        // Check if it's actually there
        $check = $connection->query("SELECT * FROM join_requests WHERE id = $id");
        if ($check->num_rows > 0) {
            echo "✅ Record confirmed in database\n";
            $connection->query("DELETE FROM join_requests WHERE id = $id");
            echo "✅ Test record cleaned up\n";
        }
    } else {
        echo "❌ Insert failed: " . $stmt->error . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING FORM SIMULATION ===\n";

// Simulate the exact POST data
$_POST['action'] = 'request_join';
$_POST['organization_name'] = 'Test Org';

// Simulate session
$_SESSION['user_id'] = 1;
$_SESSION['organization'] = '';

// Get a real private organization
echo "4. Finding a private organization...\n";
$result = $connection->query("SELECT name FROM organizations WHERE privacy_setting = 'private' LIMIT 1");
if ($result->num_rows > 0) {
    $org = $result->fetch_assoc();
    $_POST['organization_name'] = $org['name'];
    echo "✅ Using org: " . $org['name'] . "\n";
} else {
    echo "❌ No private organizations found\n";
    // Create one
    $connection->query("UPDATE organizations SET privacy_setting = 'private' WHERE org_id = 1");
    $result = $connection->query("SELECT name FROM organizations WHERE org_id = 1");
    $org = $result->fetch_assoc();
    $_POST['organization_name'] = $org['name'];
    echo "✅ Set first org to private: " . $org['name'] . "\n";
}

// Now test the exact logic from organizations.php
echo "\n5. Testing request_join logic...\n";

$user_id = $_SESSION['user_id'];
$current_user_organization = $_SESSION['organization'];
$org_name = trim($_POST['organization_name']);

echo "Variables: user_id=$user_id, current_org='$current_user_organization', target_org='$org_name'\n";

if (empty($current_user_organization) && !empty($org_name)) {
    echo "✅ Conditions met\n";
    
    // Get organization details  
    $orgQuery = "SELECT org_id, privacy_setting, capacity_limit FROM organizations WHERE name = ?";
    $stmt = $connection->prepare($orgQuery);
    $stmt->bind_param("s", $org_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $orgData = $result->fetch_assoc();
    $stmt->close();
    
    if ($orgData) {
        echo "✅ Organization found: " . json_encode($orgData) . "\n";
        
        if ($orgData['privacy_setting'] === 'private') {
            echo "✅ Is private\n";
            
            // Check existing request
            $checkRequestQuery = "SELECT id FROM join_requests WHERE org_id = ? AND user_id = ? AND status = 'pending'";
            $stmt = $connection->prepare($checkRequestQuery);
            $stmt->bind_param("ii", $orgData['org_id'], $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingRequest = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingRequest) {
                echo "❌ Request already exists\n";
            } else {
                echo "✅ No existing request, creating...\n";
                
                // THE CRITICAL PART - CREATE JOIN REQUEST
                $insertRequestQuery = "INSERT INTO join_requests (org_id, user_id, status, requested_at) VALUES (?, ?, 'pending', NOW())";
                $stmt = $connection->prepare($insertRequestQuery);
                $stmt->bind_param("ii", $orgData['org_id'], $user_id);
                
                if ($stmt->execute()) {
                    echo "✅ SUCCESS! Request created with ID: " . $connection->insert_id . "\n";
                } else {
                    echo "❌ FAILED! Error: " . $stmt->error . "\n";
                    echo "MySQL Error: " . $connection->error . "\n";
                }
                $stmt->close();
            }
        } else {
            echo "❌ Not private: " . $orgData['privacy_setting'] . "\n";
        }
    } else {
        echo "❌ Organization not found\n";
    }
} else {
    echo "❌ Conditions not met\n";
}

$connection->close();
?>