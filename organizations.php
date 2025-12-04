<?php
session_start();
include 'database.php';
include 'badge_system_db.php';
include 'eco_points_integration.php';
require_once 'getdropdown.php'; // Add this for location dropdowns

// Initialize BadgeSystem with database connection
BadgeSystem::init($connection);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_user_organization = $_SESSION['organization'] ?? '';

// update session organization upon page load everytime the user refreshes or visits organizations.php
$orgQuery = "SELECT organization FROM accountstbl WHERE account_id = ?";    
$stmt = $connection->prepare($orgQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
// current and session organization value is updated
$current_user_organization = $row ? $row['organization'] : '';
$_SESSION['organization'] = $current_user_organization;


// Handle 'N/A' case - treat it as empty
if ($current_user_organization === 'N/A' || $current_user_organization === 'n/a') {
    $current_user_organization = '';
    $_SESSION['organization'] = '';
}

// Get current user's location for proximity-based recommendations
$userLocationQuery = "SELECT barangay, city_municipality FROM accountstbl WHERE account_id = ?";
$stmt = $connection->prepare($userLocationQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userLocation = $result->fetch_assoc();
$stmt->close();

$userBarangay = $userLocation['barangay'] ?? '';
$userCity = $userLocation['city_municipality'] ?? '';

// Get user's organization role for POST handling
$userOrgRole = null;
if (!empty($current_user_organization)) {
    $roleQuery = "SELECT om.role, o.org_id
                  FROM organization_members om
                  JOIN organizations o ON om.org_id = o.org_id
                  WHERE o.name = ? AND om.account_id = ?";
    $stmt = $connection->prepare($roleQuery);
    $stmt->bind_param("si", $current_user_organization, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $roleData = $result->fetch_assoc();
    $userOrgRole = $roleData ? $roleData['role'] : null;
    $stmt->close();
}

// Handle organization actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'join_organization':
                $organization_name = trim($_POST['organization_name']);
                if (!empty($organization_name)) {
                    // Check if user is already in ANY organization
                    if (!empty($current_user_organization)) {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "You are already a member of '{$current_user_organization}'. Please leave your current organization before joining another one."
                        ];
                        break;
                    }
                    
                    // First, get organization details and check capacity
                    $orgQuery = "SELECT o.org_id, o.name, o.capacity_limit, o.privacy_setting, COUNT(om.account_id) as current_members
                                FROM organizations o
                                LEFT JOIN organization_members om ON o.org_id = om.org_id
                                WHERE o.name = ?
                                GROUP BY o.org_id, o.name, o.capacity_limit, o.privacy_setting";
                    $stmt = $connection->prepare($orgQuery);
                    $stmt->bind_param("s", $organization_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $org = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$org) {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Organization not found."
                        ];
                    } else if ($org['privacy_setting'] === 'private') {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "This is a private organization. You need an invitation to join."
                        ];
                    } else if ($org['current_members'] >= $org['capacity_limit']) {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Organization is full. Maximum capacity is " . $org['capacity_limit'] . " members."
                        ];
                    } else {
                        // Check if user is already a member
                        $memberCheckQuery = "SELECT COUNT(*) as count FROM organization_members WHERE org_id = ? AND account_id = ?";
                        $stmt = $connection->prepare($memberCheckQuery);
                        $stmt->bind_param("ii", $org['org_id'], $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $is_member = $result->fetch_assoc()['count'] > 0;
                        $stmt->close();
                        
                        if ($is_member) {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "You are already a member of this organization."
                            ];
                        } else {
                            // Add user to organization_members table with current timestamp for cooldown tracking
                            $addMemberQuery = "INSERT INTO organization_members (org_id, account_id, role, joined_at) VALUES (?, ?, 'member', NOW())";
                            $stmt = $connection->prepare($addMemberQuery);
                            $stmt->bind_param("ii", $org['org_id'], $user_id);
                            
                            if ($stmt->execute()) {
                                // Check if this is the user's first time joining ANY organization
                                $firstTimeJoinQuery = "SELECT COUNT(*) as previous_joins FROM organization_members WHERE account_id = ? AND org_id != ?";
                                $firstTimeStmt = $connection->prepare($firstTimeJoinQuery);
                                $firstTimeStmt->bind_param("ii", $user_id, $org['org_id']);
                                $firstTimeStmt->execute();
                                $firstTimeResult = $firstTimeStmt->get_result();
                                $isFirstTimeJoin = $firstTimeResult->fetch_assoc()['previous_joins'] == 0;
                                $firstTimeStmt->close();
                                
                                // Update user's organization in accountstbl for backward compatibility
                                $updateUserQuery = "UPDATE accountstbl SET organization = ? WHERE account_id = ?";
                                $userStmt = $connection->prepare($updateUserQuery);
                                $userStmt->bind_param("si", $organization_name, $user_id);
                                $userStmt->execute();
                                $userStmt->close();
                                
                                $_SESSION['organization'] = $organization_name;
                                
                                // First-time organization join rewards
                                if ($isFirstTimeJoin) {
                                    // Award 100 eco points for first-time organization join
                                    initializeEcoPointsSystem();
                                    $pointsAwarded = EcoPointsSystem::awardOrganizationJoinPoints($user_id, $org['org_id'], 100);
                                    
                                    if ($pointsAwarded && $pointsAwarded['success']) {
                                        // Award Kabalikat Badge for first-time organization join
                                        $badgeAwarded = BadgeSystem::awardBadgeToUser($user_id, 'Kabalikat Badge');
                                        
                                        if ($badgeAwarded) {
                                            // Set session for index.php-style badge notification
                                            $_SESSION['new_badge_awarded'] = [
                                                'badge_awarded' => true,
                                                'badge_name' => 'Kabalikat Badge',
                                                'eco_points_earned' => 100,
                                                'description' => 'Awarded for joining your first organization and becoming part of the ManGrow community!'
                                            ];
                                        }
                                        
                                        $_SESSION['flash_message'] = [
                                            'type' => 'success',
                                            'message' => "🎉 Welcome to your first organization! You've earned 100 eco points and the Kabalikat Badge! Note: You cannot leave for 24 hours after joining."
                                        ];
                                    } else {
                                        // Points failed but still try badge
                                        $badgeAwarded = BadgeSystem::awardBadgeToUser($user_id, 'Kabalikat Badge');
                                        
                                        if ($badgeAwarded) {
                                            $_SESSION['new_badge_awarded'] = [
                                                'badge_awarded' => true,
                                                'badge_name' => 'Kabalikat Badge',
                                                'eco_points_earned' => 0,
                                                'description' => 'Awarded for joining your first organization and becoming part of the ManGrow community!'
                                            ];
                                        }
                                        
                                        $_SESSION['flash_message'] = [
                                            'type' => 'warning',
                                            'message' => "🎉 Welcome to your first organization! You've earned the Kabalikat Badge! (Eco points couldn't be awarded - daily limit reached) Note: You cannot leave for 24 hours after joining."
                                        ];
                                    }
                                } else {
                                    $_SESSION['flash_message'] = [
                                        'type' => 'success',
                                        'message' => "Successfully joined organization: " . htmlspecialchars($organization_name) . ". Note: You cannot leave for 24 hours after joining."
                                    ];
                                }
                                
                                // Log activity
                                $activity_details = "User joined organization: " . $organization_name;
                                logActivity($connection, $user_id, 'organization_join', $activity_details);
                                
                            } else {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "Failed to join organization. Please try again."
                                ];
                            }
                            $stmt->close();
                        }
                    }
                }
                break;
                
            case 'leave_organization':
                if (!empty($current_user_organization)) {
                    // Get organization ID and user's role first
                    $orgQuery = "SELECT o.org_id, om.role, COUNT(om2.account_id) as total_members
                                FROM organizations o
                                JOIN organization_members om ON o.org_id = om.org_id
                                LEFT JOIN organization_members om2 ON o.org_id = om2.org_id
                                WHERE o.name = ? AND om.account_id = ?
                                GROUP BY o.org_id, om.role";
                    $stmt = $connection->prepare($orgQuery);
                    $stmt->bind_param("si", $current_user_organization, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $org = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($org) {
                        // Check if user is creator and prevent leaving if they are the only member or haven't transferred rights
                        if ($org['role'] === 'creator') {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "As the organization creator, you must transfer leadership to another member before leaving, or delete the organization if you're the only member."
                            ];
                        } else {
                            // Check 24-hour cooldown for non-creators
                            $cooldownQuery = "SELECT joined_at FROM organization_members WHERE org_id = ? AND account_id = ?";
                            $stmt = $connection->prepare($cooldownQuery);
                            $stmt->bind_param("ii", $org['org_id'], $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $memberData = $result->fetch_assoc();
                            $stmt->close();
                            
                            if ($memberData) {
                                $joinTime = strtotime($memberData['joined_at']);
                                $currentTime = time();
                                $hoursSinceJoin = (($currentTime - $joinTime) / 3600) + 6; // 3600 seconds = 1 hour
                                
                                if ($hoursSinceJoin < 24) {
                                    $hoursRemaining = 24 - floor($hoursSinceJoin);
                                    $_SESSION['flash_message'] = [
                                        'type' => 'error',
                                        'message' => "You must wait {$hoursRemaining} more hours before leaving the organization (24-hour cooldown after joining)."
                                    ];
                                } else {
                                    // Allow leaving - remove user from organization_members table
                                    $removeMemberQuery = "DELETE FROM organization_members WHERE org_id = ? AND account_id = ?";
                                    $stmt = $connection->prepare($removeMemberQuery);
                                    $stmt->bind_param("ii", $org['org_id'], $user_id);
                                    
                                    if ($stmt->execute()) {
                                        // Update user's organization in accountstbl
                                        $updateUserQuery = "UPDATE accountstbl SET organization = '' WHERE account_id = ?";
                                        $userStmt = $connection->prepare($updateUserQuery);
                                        $userStmt->bind_param("i", $user_id);
                                        $userStmt->execute();
                                        $userStmt->close();
                                        
                                        $old_org = $_SESSION['organization'];
                                        $_SESSION['organization'] = '';
                                        $_SESSION['flash_message'] = [
                                            'type' => 'success',
                                            'message' => "Successfully left organization: " . htmlspecialchars($old_org)
                                        ];
                                        
                                        // Log activity
                                        $activity_details = "User left organization: " . $old_org;
                                        logActivity($connection, $user_id, 'organization_leave', $activity_details);
                                    } else {
                                        $_SESSION['flash_message'] = [
                                            'type' => 'error',
                                            'message' => "Failed to leave organization. Please try again."
                                        ];
                                    }
                                    $stmt->close();
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'create_organization':
                $new_org_name = trim($_POST['new_organization_name']);
                $new_org_description = trim($_POST['new_organization_description']);
                $capacity_limit = intval($_POST['capacity_limit']);
                
                // Handle location inputs (either from dropdown or manual input)
                if (!empty($_POST['manual_city']) && !empty($_POST['manual_barangay'])) {
                    // Manual input was used
                    $city_municipality = trim($_POST['manual_city']);
                    $barangay = trim($_POST['manual_barangay']);
                } else {
                    // Dropdown was used
                    $city_municipality = trim($_POST['city_municipality']);
                    $barangay = trim($_POST['barangay']);
                }
                
                // Validation
                $errors = [];
                
                if (empty($new_org_name)) {
                    $errors[] = "Organization name is required";
                }
                
                if (empty($city_municipality)) {
                    $errors[] = "City/Municipality is required";
                }
                
                if (empty($barangay)) {
                    $errors[] = "Barangay is required";
                }
                
                if ($capacity_limit < 10 || $capacity_limit > 50) {
                    $errors[] = "Capacity limit must be between 10 and 50 members";
                }
                
                if (!empty($errors)) {
                    $_SESSION['flash_message'] = [
                        'type' => 'error',
                        'message' => implode('. ', $errors)
                    ];
                } else {
                    // Check if organization name already exists
                    $checkQuery = "SELECT COUNT(*) as count FROM organizations WHERE name = ?";
                    $stmt = $connection->prepare($checkQuery);
                    $stmt->bind_param("s", $new_org_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $exists = $result->fetch_assoc()['count'] > 0;
                    $stmt->close();
                    
                    if ($exists) {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Organization name already exists. Please choose a different name."
                        ];
                    } else {
                        // Create organization in organizations table
                        $createOrgQuery = "INSERT INTO organizations (name, description, barangay, city_municipality, capacity_limit, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $connection->prepare($createOrgQuery);
                        $stmt->bind_param("ssssii", $new_org_name, $new_org_description, $barangay, $city_municipality, $capacity_limit, $user_id);
                        
                        if ($stmt->execute()) {
                            $org_id = $connection->insert_id;
                            $stmt->close();
                            
                            // Add creator as member
                            $addMemberQuery = "INSERT INTO organization_members (org_id, account_id, role) VALUES (?, ?, 'creator')";
                            $stmt = $connection->prepare($addMemberQuery);
                            $stmt->bind_param("ii", $org_id, $user_id);
                            
                            if ($stmt->execute()) {
                                // Update user's organization in accountstbl for backward compatibility
                                $updateUserQuery = "UPDATE accountstbl SET organization = ? WHERE account_id = ?";
                                $userStmt = $connection->prepare($updateUserQuery);
                                $userStmt->bind_param("si", $new_org_name, $user_id);
                                $userStmt->execute();
                                $userStmt->close();
                                
                                $_SESSION['organization'] = $new_org_name;
                                $_SESSION['flash_message'] = [
                                    'type' => 'success',
                                    'message' => "Successfully created organization: " . htmlspecialchars($new_org_name) . " in " . htmlspecialchars($barangay) . ", " . htmlspecialchars($city_municipality)
                                ];
                                
                                // Log activity
                                $activity_details = "User created organization: " . $new_org_name . " in " . $barangay . ", " . $city_municipality;
                                logActivity($connection, $user_id, 'organization_create', $activity_details);
                                
                            } else {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "Failed to add you as member. Please try again."
                                ];
                            }
                            $stmt->close();
                        } else {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "Failed to create organization. Please try again."
                            ];
                            $stmt->close();
                        }
                    }
                }
                break;
                
            case 'edit_organization':
                if (!empty($current_user_organization)) {
                    // Check if user is the creator
                    $roleQuery = "SELECT om.role, o.org_id FROM organizations o 
                                 JOIN organization_members om ON o.org_id = om.org_id 
                                 WHERE o.name = ? AND om.account_id = ?";
                    $stmt = $connection->prepare($roleQuery);
                    $stmt->bind_param("si", $current_user_organization, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $roleData = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($roleData && $roleData['role'] === 'creator') {
                        $new_name = trim($_POST['edit_organization_name']);
                        $new_description = trim($_POST['edit_organization_description']);
                        $new_capacity = intval($_POST['edit_capacity_limit']);
                        $new_privacy = trim($_POST['edit_privacy_setting']);
                        
                        // Handle location inputs
                        if (!empty($_POST['edit_manual_city']) && !empty($_POST['edit_manual_barangay'])) {
                            $city_municipality = trim($_POST['edit_manual_city']);
                            $barangay = trim($_POST['edit_manual_barangay']);
                        } else {
                            $city_municipality = trim($_POST['edit_city_municipality']);
                            $barangay = trim($_POST['edit_barangay']);
                        }
                        
                        // Validation
                        $errors = [];
                        
                        if (empty($new_name)) {
                            $errors[] = "Organization name is required";
                        }
                        
                        if (empty($city_municipality)) {
                            $errors[] = "City/Municipality is required";
                        }
                        
                        if (empty($barangay)) {
                            $errors[] = "Barangay is required";
                        }
                        
                        if ($new_capacity < 10 || $new_capacity > 50) {
                            $errors[] = "Capacity limit must be between 10 and 50 members";
                        }
                        
                        if (!in_array($new_privacy, ['public', 'private'])) {
                            $errors[] = "Invalid privacy setting";
                        }
                        
                        // Check if new name already exists (excluding current organization)
                        if ($new_name !== $current_user_organization) {
                            $checkQuery = "SELECT COUNT(*) as count FROM organizations WHERE name = ?";
                            $stmt = $connection->prepare($checkQuery);
                            $stmt->bind_param("s", $new_name);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $exists = $result->fetch_assoc()['count'] > 0;
                            $stmt->close();
                            
                            if ($exists) {
                                $errors[] = "Organization name already exists";
                            }
                        }
                        
                        // Check if new capacity is less than current member count
                        $memberCountQuery = "SELECT COUNT(*) as count FROM organization_members WHERE org_id = ?";
                        $stmt = $connection->prepare($memberCountQuery);
                        $stmt->bind_param("i", $roleData['org_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $currentMembers = $result->fetch_assoc()['count'];
                        $stmt->close();
                        
                        if ($new_capacity < $currentMembers) {
                            $errors[] = "Capacity cannot be less than current member count ({$currentMembers})";
                        }
                        
                        if (!empty($errors)) {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => implode('. ', $errors)
                            ];
                        } else {
                            // Update organization
                            $updateQuery = "UPDATE organizations SET name = ?, description = ?, barangay = ?, city_municipality = ?, capacity_limit = ?, privacy_setting = ? WHERE org_id = ?";
                            $stmt = $connection->prepare($updateQuery);
                            $stmt->bind_param("ssssisi", $new_name, $new_description, $barangay, $city_municipality, $new_capacity, $new_privacy, $roleData['org_id']);
                            
                            if ($stmt->execute()) {
                                // Update accountstbl for all members if name changed
                                if ($new_name !== $current_user_organization) {
                                    $updateMembersQuery = "UPDATE accountstbl a 
                                                          JOIN organization_members om ON a.account_id = om.account_id 
                                                          SET a.organization = ? 
                                                          WHERE om.org_id = ?";
                                    $memberStmt = $connection->prepare($updateMembersQuery);
                                    $memberStmt->bind_param("si", $new_name, $roleData['org_id']);
                                    $memberStmt->execute();
                                    $memberStmt->close();
                                    
                                    $_SESSION['organization'] = $new_name;
                                }
                                
                                $_SESSION['flash_message'] = [
                                    'type' => 'success',
                                    'message' => "Organization updated successfully!"
                                ];
                                
                                // Log activity
                                $activity_details = "User updated organization: " . $new_name;
                                logActivity($connection, $user_id, 'organization_edit', $activity_details);
                            } else {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "Failed to update organization. Please try again."
                                ];
                            }
                            $stmt->close();
                        }
                    } else {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Only organization creators can edit organization details."
                        ];
                    }
                }
                break;
                
            case 'transfer_leadership':
                if (!empty($current_user_organization)) {
                    // Check if user is the creator
                    $roleQuery = "SELECT om.role, o.org_id FROM organizations o 
                                 JOIN organization_members om ON o.org_id = om.org_id 
                                 WHERE o.name = ? AND om.account_id = ?";
                    $stmt = $connection->prepare($roleQuery);
                    $stmt->bind_param("si", $current_user_organization, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $roleData = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($roleData && $roleData['role'] === 'creator') {
                        $new_leader_id = intval($_POST['new_leader_id']);
                        
                        // Verify the new leader is a member of the organization
                        $memberQuery = "SELECT om.account_id, a.fullname FROM organization_members om
                                       JOIN accountstbl a ON om.account_id = a.account_id
                                       WHERE om.org_id = ? AND om.account_id = ? AND om.role != 'creator'";
                        $stmt = $connection->prepare($memberQuery);
                        $stmt->bind_param("ii", $roleData['org_id'], $new_leader_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $newLeader = $result->fetch_assoc();
                        $stmt->close();
                        
                        if ($newLeader) {
                            // Begin transaction for leadership transfer
                            $connection->begin_transaction();
                            
                            try {
                                // Update current creator to member
                                $updateOldLeaderQuery = "UPDATE organization_members SET role = 'member' WHERE org_id = ? AND account_id = ?";
                                $stmt1 = $connection->prepare($updateOldLeaderQuery);
                                $stmt1->bind_param("ii", $roleData['org_id'], $user_id);
                                $stmt1->execute();
                                $stmt1->close();
                                
                                // Update new leader to creator
                                $updateNewLeaderQuery = "UPDATE organization_members SET role = 'creator' WHERE org_id = ? AND account_id = ?";
                                $stmt2 = $connection->prepare($updateNewLeaderQuery);
                                $stmt2->bind_param("ii", $roleData['org_id'], $new_leader_id);
                                $stmt2->execute();
                                $stmt2->close();
                                
                                $connection->commit();
                                
                                $_SESSION['flash_message'] = [
                                    'type' => 'success',
                                    'message' => "Leadership successfully transferred to " . htmlspecialchars($newLeader['fullname']) . ". You are now a regular member."
                                ];
                                
                                // Log activity
                                $activity_details = "Leadership transferred to " . $newLeader['fullname'] . " in organization: " . $current_user_organization;
                                logActivity($connection, $user_id, 'leadership_transfer', $activity_details);
                                
                            } catch (Exception $e) {
                                $connection->rollback();
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "Failed to transfer leadership. Please try again."
                                ];
                            }
                        } else {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "Selected user is not a valid member of your organization."
                            ];
                        }
                    } else {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Only organization creators can transfer leadership."
                        ];
                    }
                }
                break;
                
            case 'send_invite':
                if (!empty($current_user_organization) && $userOrgRole === 'creator') {
                    $invite_email = trim($_POST['invite_email']);
                    
                    if (empty($invite_email) || !filter_var($invite_email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Please enter a valid email address."
                        ];
                    } else {
                        // Check if user exists
                        $userQuery = "SELECT account_id, organization FROM accountstbl WHERE email = ?";
                        $stmt = $connection->prepare($userQuery);
                        $stmt->bind_param("s", $invite_email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $inviteUser = $result->fetch_assoc();
                        $stmt->close();
                        
                        if (!$inviteUser) {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "No user found with that email address."
                            ];
                        } else if (!empty($inviteUser['organization'])) {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "User is already part of an organization."
                            ];
                        } else {
                            // Get organization ID
                            $orgQuery = "SELECT org_id FROM organizations WHERE name = ?";
                            $stmt = $connection->prepare($orgQuery);
                            $stmt->bind_param("s", $current_user_organization);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $orgData = $result->fetch_assoc();
                            $stmt->close();
                            
                            if ($orgData) {
                                // Check if invite already exists
                                $checkInviteQuery = "SELECT id FROM organization_invites WHERE org_id = ? AND invited_user_id = ? AND status = 'pending'";
                                $stmt = $connection->prepare($checkInviteQuery);
                                $stmt->bind_param("ii", $orgData['org_id'], $inviteUser['account_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $existingInvite = $result->fetch_assoc();
                                $stmt->close();
                                
                                if ($existingInvite) {
                                    $_SESSION['flash_message'] = [
                                        'type' => 'error',
                                        'message' => "Invitation already sent to this user."
                                    ];
                                } else {
                                    // Create invite
                                    $insertInviteQuery = "INSERT INTO organization_invites (org_id, invited_user_id, invited_by_user_id, invited_at) VALUES (?, ?, ?, NOW())";
                                    $stmt = $connection->prepare($insertInviteQuery);
                                    $stmt->bind_param("iii", $orgData['org_id'], $inviteUser['account_id'], $user_id);
                                    
                                    if ($stmt->execute()) {
                                        $_SESSION['flash_message'] = [
                                            'type' => 'success',
                                            'message' => "Invitation sent successfully!"
                                        ];
                                        
                                        // Log activity
                                        $activity_details = "User sent organization invite to: " . $invite_email;
                                        logActivity($connection, $user_id, 'organization_invite', $activity_details);
                                    } else {
                                        $_SESSION['flash_message'] = [
                                            'type' => 'error',
                                            'message' => "Failed to send invitation. Please try again."
                                        ];
                                    }
                                    $stmt->close();
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'respond_invite':
                $invite_id = intval($_POST['invite_id']);
                $response = trim($_POST['response']);
                
                if (in_array($response, ['accept', 'decline'])) {
                    // Verify invite belongs to current user
                    $inviteQuery = "SELECT oi.*, o.name as org_name, o.capacity_limit, 
                                          COUNT(om.account_id) as current_members
                                   FROM organization_invites oi
                                   JOIN organizations o ON oi.org_id = o.org_id
                                   LEFT JOIN organization_members om ON o.org_id = om.org_id
                                   WHERE oi.id = ? AND oi.invited_user_id = ? AND oi.status = 'pending'
                                   GROUP BY oi.id, o.org_id";
                    $stmt = $connection->prepare($inviteQuery);
                    $stmt->bind_param("ii", $invite_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $invite = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($invite) {
                        if ($response === 'accept') {
                            // Check if user is already in an organization
                            if (!empty($current_user_organization)) {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "You must leave your current organization before joining a new one."
                                ];
                            } else if ($invite['current_members'] >= $invite['capacity_limit']) {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "This organization has reached its member capacity."
                                ];
                            } else {
                                // Join organization
                                $joinQuery = "INSERT INTO organization_members (org_id, account_id, role, joined_at) VALUES (?, ?, 'member', NOW())";
                                $stmt = $connection->prepare($joinQuery);
                                $stmt->bind_param("ii", $invite['org_id'], $user_id);
                                
                                if ($stmt->execute()) {
                                    // Update user's organization in accountstbl
                                    $updateUserQuery = "UPDATE accountstbl SET organization = ? WHERE account_id = ?";
                                    $userStmt = $connection->prepare($updateUserQuery);
                                    $userStmt->bind_param("si", $invite['org_name'], $user_id);
                                    $userStmt->execute();
                                    $userStmt->close();
                                    
                                    // Check if this is the user's first time joining ANY organization and award rewards
                                    $firstTimeJoinQuery = "SELECT COUNT(*) as previous_joins FROM organization_members WHERE account_id = ? AND org_id != ?";
                                    $firstTimeStmt = $connection->prepare($firstTimeJoinQuery);
                                    $firstTimeStmt->bind_param("ii", $user_id, $invite['org_id']);
                                    $firstTimeStmt->execute();
                                    $firstTimeResult = $firstTimeStmt->get_result();
                                    $isFirstTimeJoin = $firstTimeResult->fetch_assoc()['previous_joins'] == 0;
                                    $firstTimeStmt->close();
                                    
                                    // Update invite status
                                    $updateInviteQuery = "UPDATE organization_invites SET status = 'accepted', responded_at = NOW() WHERE id = ?";
                                    $inviteStmt = $connection->prepare($updateInviteQuery);
                                    $inviteStmt->bind_param("i", $invite_id);
                                    $inviteStmt->execute();
                                    $inviteStmt->close();
                                    
                                    // Award first-time organization join rewards
                                    if ($isFirstTimeJoin) {
                                        // Award 100 eco points for first-time organization join
                                        initializeEcoPointsSystem();
                                        $pointsAwarded = EcoPointsSystem::awardOrganizationJoinPoints($user_id, $invite['org_id'], 100);
                                        
                                        if ($pointsAwarded && $pointsAwarded['success']) {
                                            // Award Kabalikat Badge for first-time organization join
                                            $badgeAwarded = BadgeSystem::awardBadgeToUser($user_id, 'Kabalikat Badge');
                                            
                                            if ($badgeAwarded) {
                                                // Set session for index.php-style badge notification
                                                $_SESSION['new_badge_awarded'] = [
                                                    'badge_awarded' => true,
                                                    'badge_name' => 'Kabalikat Badge',
                                                    'eco_points_earned' => 100,
                                                    'description' => 'Awarded for joining your first organization and becoming part of the ManGrow community!'
                                                ];
                                            }
                                            
                                            $_SESSION['flash_message'] = [
                                                'type' => 'success',
                                                'message' => "🎉 Welcome to your first organization! You've earned 100 eco points and the Kabalikat Badge! Successfully joined " . $invite['org_name'] . "!"
                                            ];
                                        } else {
                                            // Points failed but still try badge
                                            $badgeAwarded = BadgeSystem::awardBadgeToUser($user_id, 'Kabalikat Badge');
                                            
                                            if ($badgeAwarded) {
                                                $_SESSION['new_badge_awarded'] = [
                                                    'badge_awarded' => true,
                                                    'badge_name' => 'Kabalikat Badge',
                                                    'eco_points_earned' => 0,
                                                    'description' => 'Awarded for joining your first organization and becoming part of the ManGrow community!'
                                                ];
                                            }
                                            
                                            $_SESSION['flash_message'] = [
                                                'type' => 'warning',
                                                'message' => "🎉 Welcome to your first organization! You've earned the Kabalikat Badge! (Eco points couldn't be awarded - daily limit reached) Successfully joined " . $invite['org_name'] . "!"
                                            ];
                                        }
                                    } else {
                                        $_SESSION['flash_message'] = [
                                            'type' => 'success',
                                            'message' => "Successfully joined " . $invite['org_name'] . "!"
                                        ];
                                    }
                                    
                                    // Update session
                                    $_SESSION['organization'] = $invite['org_name'];
                                    
                                    // Log activity
                                    $activity_details = "User accepted organization invite: " . $invite['org_name'];
                                    logActivity($connection, $user_id, 'organization_join', $activity_details);
                                } else {
                                    $_SESSION['flash_message'] = [
                                        'type' => 'error',
                                        'message' => "Failed to join organization. Please try again."
                                    ];
                                }
                                $stmt->close();
                            }
                        } else {
                            // Decline invite
                            $updateInviteQuery = "UPDATE organization_invites SET status = 'declined', responded_at = NOW() WHERE id = ?";
                            $stmt = $connection->prepare($updateInviteQuery);
                            $stmt->bind_param("i", $invite_id);
                            
                            if ($stmt->execute()) {
                                $_SESSION['flash_message'] = [
                                    'type' => 'success',
                                    'message' => "Invitation declined."
                                ];
                            } else {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "Failed to decline invitation."
                                ];
                            }
                            $stmt->close();
                        }
                    }
                }
                break;
                
            case 'request_join':
                $org_name = trim($_POST['organization_name']);
                
                if (empty($current_user_organization) && !empty($org_name)) {
                    
                    // Get organization details
                    $orgQuery = "SELECT org_id, privacy_setting, capacity_limit FROM organizations WHERE name = ?";
                    $stmt = $connection->prepare($orgQuery);
                    $stmt->bind_param("s", $org_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $orgData = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($orgData) {
                        if ($orgData['privacy_setting'] === 'private') {
                            // Check if request already exists
                            $checkRequestQuery = "SELECT id FROM join_requests WHERE org_id = ? AND user_id = ? AND status = 'pending'";
                            $stmt = $connection->prepare($checkRequestQuery);
                            $stmt->bind_param("ii", $orgData['org_id'], $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $existingRequest = $result->fetch_assoc();
                            $stmt->close();
                            
                            if ($existingRequest) {
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "You have already requested to join this organization."
                                ];
                            } else {
                                // Create join request in join_requests table
                                $insertRequestQuery = "INSERT INTO join_requests (org_id, user_id, status, requested_at) VALUES (?, ?, 'pending', NOW())";
                                $stmt = $connection->prepare($insertRequestQuery);
                                $stmt->bind_param("ii", $orgData['org_id'], $user_id);
                                
                                if ($stmt->execute()) {
                                    $_SESSION['flash_message'] = [
                                        'type' => 'success',
                                        'message' => "Join request sent! The organization creator will review your request."
                                    ];
                                    
                                    // Log activity
                                    $activity_details = "User requested to join private organization: " . $org_name;
                                    logActivity($connection, $user_id, 'organization_join_request', $activity_details);
                                } else {
                                    $_SESSION['flash_message'] = [
                                        'type' => 'error',
                                        'message' => "Failed to send join request. Please try again."
                                    ];
                                }
                                $stmt->close();
                            }
                        } else {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "This organization is public. You can join directly."
                            ];
                        }
                    } else {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Organization not found."
                        ];
                    }
                }
                break;
                
            case 'approve_join_request':
                if (!empty($current_user_organization) && $userOrgRole === 'creator') {
                    $request_id = intval($_POST['request_id']);
                    
                    // Get request details with simple query
                    $requestQuery = "SELECT jr.user_id, jr.org_id, o.name as org_name, o.capacity_limit, a.fullname
                                    FROM join_requests jr
                                    JOIN organizations o ON jr.org_id = o.org_id
                                    JOIN accountstbl a ON jr.user_id = a.account_id
                                    WHERE jr.id = ? AND o.name = ? AND jr.status = 'pending'";
                    $stmt = $connection->prepare($requestQuery);
                    $stmt->bind_param("is", $request_id, $current_user_organization);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $request = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($request) {
                        // Check current member count
                        $countQuery = "SELECT COUNT(*) as member_count FROM organization_members WHERE org_id = ?";
                        $stmt = $connection->prepare($countQuery);
                        $stmt->bind_param("i", $request['org_id']);
                        $stmt->execute();
                        $countResult = $stmt->get_result();
                        $count = $countResult->fetch_assoc();
                        $stmt->close();
                        
                        if ($count['member_count'] >= $request['capacity_limit']) {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "Organization has reached its member capacity."
                            ];
                        } else {
                            // Begin transaction for approval process
                            $connection->begin_transaction();
                            
                            try {
                                // 1. Insert user to organization_members table
                                $insertMemberQuery = "INSERT INTO organization_members (org_id, account_id, role, joined_at) VALUES (?, ?, 'member', NOW())";
                                $stmt = $connection->prepare($insertMemberQuery);
                                $stmt->bind_param("ii", $request['org_id'], $request['user_id']);
                                $stmt->execute();
                                $stmt->close();
                                
                                // 2. Update user's organization in accountstbl
                                $updateUserQuery = "UPDATE accountstbl SET organization = ? WHERE account_id = ?";
                                $stmt = $connection->prepare($updateUserQuery);
                                $stmt->bind_param("si", $request['org_name'], $request['user_id']);
                                $stmt->execute();
                                $stmt->close();
                                
                                // 2.5. Check if this is the user's first time joining ANY organization and award rewards
                                $firstTimeJoinQuery = "SELECT COUNT(*) as previous_joins FROM organization_members WHERE account_id = ? AND org_id != ?";
                                $firstTimeStmt = $connection->prepare($firstTimeJoinQuery);
                                $firstTimeStmt->bind_param("ii", $request['user_id'], $request['org_id']);
                                $firstTimeStmt->execute();
                                $firstTimeResult = $firstTimeStmt->get_result();
                                $isFirstTimeJoin = $firstTimeResult->fetch_assoc()['previous_joins'] == 0;
                                $firstTimeStmt->close();
                                
                                if ($isFirstTimeJoin) {
                                    // Award 100 eco points for first-time organization join
                                    initializeEcoPointsSystem();
                                    $pointsAwarded = EcoPointsSystem::awardOrganizationJoinPoints($request['user_id'], $request['org_id'], 100);
                                    
                                    // Award Kabalikat Badge for first-time organization join
                                    $badgeAwarded = BadgeSystem::awardBadgeToUser($request['user_id'], 'Kabalikat Badge');
                                    
                                    if ($badgeAwarded) {
                                        // Set session for badge notification (will be shown to user on next login/page load)
                                        // Note: We can't set session for the joining user since they're not the current session user
                                        // The notification will be handled through the database or when they next visit
                                    }
                                }
                                
                                // 3. Delete the approved request from join_requests table
                                $deleteRequestQuery = "DELETE FROM join_requests WHERE id = ?";
                                $stmt = $connection->prepare($deleteRequestQuery);
                                $stmt->bind_param("i", $request_id);
                                $stmt->execute();
                                $stmt->close();
                                
                                // 4. Log the approval activity
                                $activity_details = "Approved join request from: " . $request['fullname'] . " to organization: " . $request['org_name'];
                                logActivity($connection, $user_id, 'organization_approve_request', $activity_details);
                                
                                $connection->commit();
                                
                                $_SESSION['flash_message'] = [
                                    'type' => 'success',
                                    'message' => $request['fullname'] . " has been approved and added to the organization!"
                                ];
                                
                            } catch (Exception $e) {
                                $connection->rollback();
                                $_SESSION['flash_message'] = [
                                    'type' => 'error',
                                    'message' => "Failed to approve join request. Please try again."
                                ];
                            }
                        }
                    } else {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Invalid join request."
                        ];
                    }
                }
                break;
                
            case 'reject_join_request':
                if (!empty($current_user_organization) && $userOrgRole === 'creator') {
                    $request_id = intval($_POST['request_id']);
                    
                    // Get request details with simple query
                    $requestQuery = "SELECT jr.user_id, jr.org_id, a.fullname
                                    FROM join_requests jr
                                    JOIN organizations o ON jr.org_id = o.org_id
                                    JOIN accountstbl a ON jr.user_id = a.account_id
                                    WHERE jr.id = ? AND o.name = ? AND jr.status = 'pending'";
                    $stmt = $connection->prepare($requestQuery);
                    $stmt->bind_param("is", $request_id, $current_user_organization);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $request = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($request) {
                        // Update status to 'declined'
                        $updateRequestQuery = "UPDATE join_requests SET status = 'declined', responded_at = NOW(), responded_by = ? WHERE id = ?";
                        $stmt = $connection->prepare($updateRequestQuery);
                        $stmt->bind_param("ii", $user_id, $request_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['flash_message'] = [
                                'type' => 'success',
                                'message' => "Join request from " . $request['fullname'] . " has been declined."
                            ];
                            
                            // Log the rejection activity
                            $activity_details = "Declined join request from: " . $request['fullname'];
                            logActivity($connection, $user_id, 'organization_reject_request', $activity_details);
                        } else {
                            $_SESSION['flash_message'] = [
                                'type' => 'error',
                                'message' => "Failed to decline join request."
                            ];
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Invalid join request or request not found."
                        ];
                    }
                } else {
                    $_SESSION['flash_message'] = [
                        'type' => 'error',
                        'message' => "Access denied. Only organization creators can manage join requests."
                    ];
                }
                break;

            case 'acknowledge_declined_request':
                $request_id = intval($_POST['request_id']);
                
                // Verify this declined request belongs to the current user
                $checkQuery = "SELECT jr.id, o.name as org_name 
                              FROM join_requests jr
                              JOIN organizations o ON jr.org_id = o.org_id
                              WHERE jr.id = ? AND jr.user_id = ? AND jr.status = 'declined'";
                $stmt = $connection->prepare($checkQuery);
                $stmt->bind_param("ii", $request_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                if ($request) {
                    // Delete the declined request from join_requests table
                    $deleteQuery = "DELETE FROM join_requests WHERE id = ? AND user_id = ? AND status = 'declined'";
                    $stmt = $connection->prepare($deleteQuery);
                    $stmt->bind_param("ii", $request_id, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['flash_message'] = [
                            'type' => 'success',
                            'message' => "Declined request notification cleared."
                        ];
                        
                        // Log the acknowledgment
                        $activity_details = "Acknowledged declined request for organization: " . $request['org_name'];
                        logActivity($connection, $user_id, 'acknowledge_declined_request', $activity_details);
                    } else {
                        $_SESSION['flash_message'] = [
                            'type' => 'error',
                            'message' => "Failed to clear declined request notification."
                        ];
                    }
                    $stmt->close();
                } else {
                    $_SESSION['flash_message'] = [
                        'type' => 'error',
                        'message' => "Invalid declined request."
                    ];
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: organizations.php");
        exit();
    }
}

// Get flash message
$flashMessage = '';
$flashType = '';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message']['message'];
    $flashType = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Fetch all organizations with member count and total points
// Get location filters
$location_filter = '';
$location_params = [];
$param_types = '';

if (!empty($_GET['city_municipality'])) {
    $location_filter .= " AND o.city_municipality = ?";
    $location_params[] = $_GET['city_municipality'];
    $param_types .= 's';
}

if (!empty($_GET['barangay'])) {
    $location_filter .= " AND o.barangay = ?";
    $location_params[] = $_GET['barangay'];
    $param_types .= 's';
}

// Get top 3 organization recommendations (based on member count, public privacy, and eco points)
$recommendationsQuery = "SELECT o.org_id, o.name as organization, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting,
                              COUNT(DISTINCT om.account_id) as member_count, 
                              COALESCE(SUM(a.eco_points), 0) as total_points,
                              COALESCE(AVG(a.eco_points), 0) as avg_points,
                              COALESCE(MAX(a.eco_points), 0) as top_member_points
                       FROM organizations o
                       LEFT JOIN organization_members om ON o.org_id = om.org_id
                       LEFT JOIN accountstbl a ON om.account_id = a.account_id
                       GROUP BY o.org_id, o.name, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting
                       ORDER BY o.privacy_setting = 'public' DESC, member_count DESC, total_points DESC
                       LIMIT 3";

$stmt = $connection->prepare($recommendationsQuery);
$stmt->execute();
$recommendationsResult = $stmt->get_result();
$topRecommendations = [];
while ($row = $recommendationsResult->fetch_assoc()) {
    $topRecommendations[] = $row;
}
$stmt->close();

// Get location-based organizations (excluding the top 3 recommendations)
$excludeOrgIds = '';
$excludeParams = [];
if (!empty($topRecommendations)) {
    $excludeOrgIds = " AND o.org_id NOT IN (" . str_repeat('?,', count($topRecommendations) - 1) . "?)";
    foreach ($topRecommendations as $rec) {
        $excludeParams[] = $rec['org_id'];
    }
}

$locationBasedQuery = "SELECT o.org_id, o.name as organization, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting,
                              COUNT(DISTINCT om.account_id) as member_count, 
                              COALESCE(SUM(a.eco_points), 0) as total_points,
                              COALESCE(AVG(a.eco_points), 0) as avg_points,
                              COALESCE(MAX(a.eco_points), 0) as top_member_points,
                              CASE 
                                  WHEN o.city_municipality = ? AND o.barangay = ? THEN 1
                                  WHEN o.city_municipality = ? THEN 2
                                  ELSE 3
                              END as location_priority
                       FROM organizations o
                       LEFT JOIN organization_members om ON o.org_id = om.org_id
                       LEFT JOIN accountstbl a ON om.account_id = a.account_id
                       WHERE 1=1 $location_filter $excludeOrgIds
                       GROUP BY o.org_id, o.name, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting
                       ORDER BY location_priority ASC, total_points DESC";

$params = [$userCity, $userBarangay, $userCity];
$param_types_location = 'sss';

if (!empty($location_params)) {
    $params = array_merge($params, $location_params);
    $param_types_location .= $param_types;
}

if (!empty($excludeParams)) {
    $params = array_merge($params, $excludeParams);
    $param_types_location .= str_repeat('i', count($excludeParams));
}

$stmt = $connection->prepare($locationBasedQuery);
if (!empty($params)) {
    $stmt->bind_param($param_types_location, ...$params);
}
$stmt->execute();
$organizationsResult = $stmt->get_result();

// Fetch current user's organization details if they have one
$userOrgDetails = null;
$userOrgBadgeCount = 0;
$userOrgEventCount = 0;
if (!empty($current_user_organization)) {
    $userOrgQuery = "SELECT o.org_id, o.name as organization, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting,
                            COUNT(DISTINCT om.account_id) as member_count, 
                            COALESCE(SUM(a.eco_points), 0) as total_points,
                            COALESCE(AVG(a.eco_points), 0) as avg_points
                     FROM organizations o
                     LEFT JOIN organization_members om ON o.org_id = om.org_id
                     LEFT JOIN accountstbl a ON om.account_id = a.account_id
                     WHERE o.name = ?
                     GROUP BY o.org_id, o.name, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting";
    $stmt = $connection->prepare($userOrgQuery);
    $stmt->bind_param("s", $current_user_organization);
    $stmt->execute();
    $result = $stmt->get_result();
    $userOrgDetails = $result->fetch_assoc();
    $stmt->close();
    
    // Get badges count for user's organization
    if ($userOrgDetails) {
        // Get all badges from organization members
        $badgeCountQuery = "SELECT a.badges
                           FROM accountstbl a
                           JOIN organization_members om ON a.account_id = om.account_id
                           WHERE om.org_id = ? AND a.badges IS NOT NULL AND a.badges != ''";
        $stmt = $connection->prepare($badgeCountQuery);
        $stmt->bind_param("i", $userOrgDetails['org_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $unique_badges = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['badges'])) {
                $badges = explode(',', $row['badges']);
                foreach ($badges as $badge) {
                    $badge = trim($badge);
                    if (!empty($badge)) {
                        $unique_badges[$badge] = true;
                    }
                }
            }
        }
        $userOrgBadgeCount = count($unique_badges);
        $stmt->close();
        
        // Get events count for user's organization
        $eventCountQuery = "SELECT COUNT(DISTINCT e.event_id) as event_count
                           FROM eventstbl e
                           JOIN accountstbl a ON e.author = a.account_id
                           JOIN organization_members om ON a.account_id = om.account_id
                           WHERE om.org_id = ? AND e.event_status IN ('approved', 'completed')";
        $stmt = $connection->prepare($eventCountQuery);
        $stmt->bind_param("i", $userOrgDetails['org_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $eventData = $result->fetch_assoc();
        $userOrgEventCount = $eventData['event_count'];
        $stmt->close();
    }
}

// Fetch organization members for current user's organization
$orgMembers = [];
if (!empty($current_user_organization)) {
    $membersQuery = "SELECT a.account_id, a.fullname, a.eco_points, a.profile_thumbnail, a.barangay, a.city_municipality, om.role
                     FROM organizations o
                     JOIN organization_members om ON o.org_id = om.org_id
                     JOIN accountstbl a ON om.account_id = a.account_id
                     WHERE o.name = ?
                     ORDER BY 
                         CASE om.role 
                             WHEN 'creator' THEN 1 
                             WHEN 'admin' THEN 2 
                             WHEN 'member' THEN 3 
                             ELSE 4 
                         END,
                         a.eco_points DESC";
    $stmt = $connection->prepare($membersQuery);
    $stmt->bind_param("s", $current_user_organization);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orgMembers[] = $row;
    }
    $stmt->close();
}

// Fetch pending invites for current user (actual invitations from creators, not join requests)
$pendingInvites = [];
$inviteQuery = "SELECT oi.id, oi.invited_at, o.name as org_name, o.description, 
                       a.fullname as invited_by, a.email as invited_by_email
                FROM organization_invites oi
                JOIN organizations o ON oi.org_id = o.org_id
                JOIN accountstbl a ON oi.invited_by_user_id = a.account_id
                WHERE oi.invited_user_id = ? AND oi.status = 'pending'
                ORDER BY oi.invited_at DESC";
$stmt = $connection->prepare($inviteQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pendingInvites[] = $row;
}
$stmt->close();

// Fetch sent invites for organization creators (actual invitations sent to users)
$sentInvites = [];
if (!empty($current_user_organization) && $userOrgRole === 'creator') {
    $sentInvitesQuery = "SELECT oi.id, oi.invited_at, oi.status, oi.responded_at,
                                a.fullname, a.email, a.account_id
                         FROM organization_invites oi
                         JOIN organizations o ON oi.org_id = o.org_id
                         JOIN accountstbl a ON oi.invited_user_id = a.account_id
                         WHERE o.name = ? AND oi.invited_by_user_id = ?
                         ORDER BY oi.invited_at DESC";
    $stmt = $connection->prepare($sentInvitesQuery);
    $stmt->bind_param("si", $current_user_organization, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sentInvites[] = $row;
    }
    $stmt->close();
}

// Fetch declined join requests for current user
$declinedRequests = [];
$declinedQuery = "SELECT jr.id, jr.requested_at, jr.responded_at, o.name as org_name, o.description
                 FROM join_requests jr
                 JOIN organizations o ON jr.org_id = o.org_id
                 WHERE jr.user_id = ? AND jr.status = 'declined'
                 ORDER BY jr.responded_at DESC";
$stmt = $connection->prepare($declinedQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $declinedRequests[] = $row;
}
$stmt->close();


// Fetch join requests for organization creators (users requesting to join private organizations)
$joinRequests = [];
if (!empty($current_user_organization) && $userOrgRole === 'creator') {
    $joinRequestsQuery = "SELECT jr.id, jr.requested_at, jr.status,
                                 a.fullname, a.email, a.account_id, a.eco_points, a.barangay, a.city_municipality
                          FROM join_requests jr
                          JOIN organizations o ON jr.org_id = o.org_id
                          JOIN accountstbl a ON jr.user_id = a.account_id
                          WHERE o.name = ? AND jr.status = 'pending'
                          ORDER BY jr.requested_at DESC";
    $stmt = $connection->prepare($joinRequestsQuery);
    $stmt->bind_param("s", $current_user_organization);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $joinRequests[] = $row;
    }
    $stmt->close();
}

// Function to log user activities
function logActivity($connection, $user_id, $activity_type, $details) {
    $logQuery = "INSERT INTO user_activity_log (user_id, activity_type, details, created_at) 
                 VALUES (?, ?, ?, NOW())";
    $stmt = $connection->prepare($logQuery);
    $stmt->bind_param("iss", $user_id, $activity_type, $details);
    $stmt->execute();
    $stmt->close();
}

// Create activity log table if it doesn't exist
$createActivityTable = "CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE
)";
$connection->query($createActivityTable);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Organizations - Mangrow</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type ="text/javascript" src ="app.js" defer></script>
    <style>
        .organizations-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .page-header h1 {
            color: var(--base-clr);
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .page-header p {
            color: var(--placeholder-text-clr);
            font-size: 1.1rem;
        }
        
        .organization-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .filter-section {
            background: var(--accent-clr);
            border: 2px solid rgba(62, 123, 39, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filter-section h3 {
            color: var(--base-clr);
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: var(--base-clr);
            font-size: 0.9rem;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid rgba(62, 123, 39, 0.3);
            border-radius: 6px;
            background: white;
            color: var(--base-clr);
            min-width: 150px;
        }
        
        .org-location {
            color: var(--text-clr);
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .org-description {
            color: var(--text-clr);
            font-size: 0.9rem;
            margin-left:15px;
            margin-bottom: 15px;
            line-height: 1.4;
            opacity: 0.8;
        }
        
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .role-badge.creator {
            background: #f39c12;
            color: white;
        }
        
        .role-badge.admin {
            background: var(--placeholder-text-clr);
            color: white;
        }
        
        .filter-status {
            display: flex;
            align-items: center;
            margin-left: auto;
        }
        
        .loading-text {
            color: var(--placeholder-text-clr);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .no-results-message {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            grid-column: 1 / -1;
        }
        
        .bx-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .tab-button {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: var(--placeholder-text-clr);
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            color: var(--base-clr);
            border-bottom-color: var(--placeholder-text-clr);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .current-org-section {
            background: var(--placeholder-text-clr);
            color: azure;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .current-org-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap:5px;
        }
        
        .org-title-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .privacy-badge {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .privacy-badge.public {
            background: rgba(34, 197, 94, 0.2);
            color: #16a34a;
        }
        
        .privacy-badge.private {
            background: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .org-location, .org-description, .org-capacity {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .org-description {
            font-style: italic;
            opacity: 0.9;
        }
        
        .org-capacity {
            font-weight: 500;
        }
        
        .org-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        
        /* Invite Management Styles */
        .invite-section, .pending-invites-section {
            background: rgba(239, 227, 194, 0.1);
            padding: 25px;
            border-radius: 12px;
            margin-top: 25px;
        }
        
        .invite-form {
            margin: 15px 0;
        }
        
        .invite-form .form-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .invite-form input[type="email"] {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .invites-list {
            margin-top: 15px;
        }
        
        .invite-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .invite-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .invite-info small{
            font-size: 14px;
            color:var(--text-clr);
        }

        .invite-email, .invite-info strong{
            color: var(--text-clr);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge.pending {
            background: rgba(251, 191, 36, 0.2);
            color: #d97706;
        }
        
        .status-badge.accepted {
            background: rgba(34, 197, 94, 0.2);
            color: #16a34a;
        }
        
        .status-badge.declined {
            background: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .invites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .invitation-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .invitation-header h4 {
            margin: 0 0 5px 0;
            color: var(--primary-clr);
        }
        
        .invitation-description {
            margin: 10px 0;
            color: #666;
            font-style: italic;
        }
        
        .invitation-date {
            margin: 10px 0;
            opacity: 0.7;
        }
        
        .invitation-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* Join Requests Styles */
        .join-requests-section {
            background: rgba(239, 227, 194, 0.1);
            padding: 25px;
            border-radius: 12px;
            margin-top: 25px;
        }
        
        .join-requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        /* Declined Requests Styles */
        .declined-requests-section {
            background: rgba(239, 68, 68, 0.1);
            padding: 25px;
            border-radius: 12px;
            margin-top: 25px;
        }

        .declined-requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .declined-request-card {
            background: white;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(239, 68, 68, 0.1);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .request-dates {
            margin: 10px 0;
            opacity: 0.7;
        }

        .request-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
        }
        
        .join-request-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .user-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-clr);
        }
        
        .user-email {
            color: #666;
            font-size: 14px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-top: 8px;
        }
        
        .user-details small {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
        }
        
        .request-date {
            text-align: right;
            opacity: 0.7;
        }
        
        .request-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .org-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(239, 227, 194, 0.1);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .member-card {
            background: var(--accent-clr);
            color: var(--base-clr);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            transition: transform 0.3s ease;
        }
        
        .member-card:hover {
            transform: translateY(-5px);
        }
        
        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: var(--secondarybase-clr);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .member-avatar i {
            font-size: 1.5rem;
            color: var(--placeholder-text-clr);
        }
        
        .organizations-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .recommendations-section,
        .location-based-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--base-clr);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .recommendations-grid,
        .location-based-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .recommendation-card {
            border: 3px solid var(--primary-clr) !important;
            background: linear-gradient(135deg, var(--accent-clr), rgba(62, 123, 39, 0.05));
        }
        
        .recommendation-card:hover {
            border-color: var(--secondary-clr) !important;
            box-shadow: 0 12px 30px rgba(62, 123, 39, 0.2);
        }
        
        .location-card {
            border: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .location-card:hover {
            border-color: var(--placeholder-text-clr);
        }
        
        .organization-card {
            background: var(--accent-clr);
            border: 2px solid rgba(62, 123, 39, 0.2);
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .organization-card:hover {
            border-color: var(--placeholder-text-clr);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(18, 53, 36, 0.1);
        }
        
        .org-rank {
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--placeholder-text-clr);
            color: azure;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .organization-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--base-clr);
            margin-bottom: 15px;
        }
        
        .org-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .org-header .organization-name {
            margin-bottom: 0;
            flex: 1;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .org-card-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        @media(max-width:800px){
            .org-card-stats{
                display:flex;
                flex-direction:row !important;
            }
            .organization-card{
                box-sizing:border-box;
                min-width:250px;
            }
        }

        .org-stat {
            text-align: center;
        }
        
        .org-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--placeholder-text-clr);
        }
        
        .org-stat-label {
            font-size: 0.8rem;
            color: var(--placeholder-text-clr);
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-buttons button, .action-buttons .btn, .action-buttons form {
            flex: 1;
        }
        
        .action-buttons button{
            display: flex;
            align-items: center;
            gap:10px;
        }

        .action-buttons .btn i{
            font-size: 1rem;
            margin:0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #27ae60;
            color: white;
        }
        
        .btn-primary:hover {
            background: #219a52;
        }
        
        .btn-outline {
            background: transparent;
            color: #27ae60;
            border: 2px solid #27ae60;
        }
        
        .btn-outline:hover {
            background: #27ae60;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .create-org-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .checkbox-group{
            display:flex;
            align-items: center;
            justify-content: start;
            gap: 10px;
        }

        .checkbox-group input{
            max-width: 20px;
            max-height: 20px;
        }

        .checkbox-group input label{
            margin:0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #27ae60;
        }
        
        .flash-message {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .no-org-message {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 12px;
            color: #666;
        }
        
        .no-org-message i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .organizations-container {
                margin: 10px;
                padding: 15px;
            }
            
            .organization-tabs {
                flex-direction: column;
                gap: 5px;
            }
            
            .organizations-grid {
                grid-template-columns: 1fr;
            }
            
            .members-grid {
                grid-template-columns: 1fr;
            }
            
            .org-card-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        /* Organization Form Styles */
        .form-section {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--accent-clr);
        }
        
        .form-section h4 {
            margin: 0 0 8px 0;
            color: var(--base-clr);
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-desc {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--base-clr);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-clr);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .form-help {
            display: block;
            margin-top: 5px;
            font-size: 0.8em;
            color: #666;
        }
        
        .manual-input-checkbox {
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .manual-input-checkbox input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .manual-input-checkbox label {
            margin: 0;
            font-size: 0.9em;
            color: #666;
            cursor: pointer;
        }
        
        .manual-inputs {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .form-section {
                padding: 15px;
            }
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border: none;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: var(--primary-clr);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-form {
            padding: 25px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 2% auto;
                max-height: 96vh;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }

        #toggle-btn.rotate{
            min-height:40px;
            max-height:54px;
        }
    </style>
</head>
<body>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <ul class="nav-list">
                <li>
                    <i class="bx bx-home"></i>
                    <a href="index.php">Home</a>
                </li>
                <li>
                    <i class="bx bx-bulb"></i>
                    <a href="initiatives.php">Initiatives</a>
                </li>
                <li>
                    <i class="bx bx-calendar-event"></i>
                    <a href="events.php">Events</a>
                </li>
                <li>
                    <i class="bx bx-trophy"></i>
                    <a href="leaderboards.php">Leaderboards</a>
                </li>
                <?php if (isset($_SESSION["name"])): ?>
                <li>
                    <i class="bx bx-group"></i>
                    <a href="organizations.php" class="active">Organizations</a>
                </li>
                <?php endif; ?>
            </ul>
            <?php 
            if (isset($_SESSION["name"])) {
                // Show profile icon when logged in
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            } else {
                // Show login link when not logged in
                echo '<a href="login.php" class="login-link">Login</a>';
            }
            ?>
            </nav>
        </header>
    <aside id="sidebar" class="close">  
        <ul>
            <li>
                <span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span>
                <button onclick= "SidebarToggle()"id="toggle-btn" class="rotate">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
                </button>
            </li>
            <hr>
            <li>
                <a href="profile.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="mangrovemappage.php" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-690v-100q0-42 29-71t71-29h100v100q0 42-29 71t-71 29H440ZM220-450q-58 0-99-41t-41-99v-140h140q58 0 99 41t41 99v140H220ZM640-90q-39 0-74.5-12T501-135l-33 33q-11 11-28 11t-28-11q-11-11-11-28t11-28l33-33q-21-29-33-64.5T400-330q0-100 70-170.5T640-571h241v241q0 100-70.5 170T640-90Zm0-80q67 0 113-47t46-113v-160H640q-66 0-113 46.5T480-330q0 23 5.5 43.5T502-248l110-110q11-11 28-11t28 11q11 11 11 28t-11 28L558-192q18 11 38.5 16.5T640-170Zm1-161Z"/></svg>
                    <span>Explore Map</span>
                </a>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li><a href="reportspage.php" tabindex="-1">My Reports</a></li>
                    <li><a href="myevents.php" tabindex="-1">My Events</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <a href="about.php" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-29t36.5-11q19 0 35 11t16 29q0 17-12 29.5T484-540q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>
                    <span>About</span>
                </a>
            </li>
            <?php
                if(isset($_SESSION['accessrole']) && ($_SESSION['accessrole'] == "Barangay Official" || $_SESSION['accessrole'] == "Administrator" || $_SESSION['accessrole'] == "Representative")) {
                    ?>
                        <li class="admin-link">
                            <a href="adminpage.php" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M680-280q25 0 42.5-17.5T740-340q0-25-17.5-42.5T680-400q-25 0-42.5 17.5T620-340q0 25 17.5 42.5T680-280Zm0 120q31 0 57-14.5t42-38.5q-22-13-47-20t-52-7q-27 0-52 7t-47 20q16 24 42 38.5t57 14.5ZM480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v227q-19-8-39-14.5t-41-9.5v-147l-240-90-240 90v188q0 47 12.5 94t35 89.5Q310-290 342-254t71 60q11 32 29 61t41 52q-1 0-1.5.5t-1.5.5Zm200 0q-83 0-141.5-58.5T480-280q0-83 58.5-141.5T680-480q83 0 141.5 58.5T880-280q0 83-58.5 141.5T680-80ZM480-494Z"/></svg>
                                <span>Administrator Lobby</span>
                            </a>
                        </li>
                    <?php
                }
            ?>
    </aside>
    <main>
        <!-- Profile Details Popup (positioned relative to header) -->
        <div class="profile-details close" id="profile-details">
            <div class="details-box">
                <?php
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                        echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="big-profile-icon">';
                    } else {
                        echo '<div class="big-default-profile-icon"><i class="fas fa-user"></i></div>';
                    }
                ?>
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <?php if(isset($_SESSION["organization"])){ 
                    if(!empty($_SESSION["organization"]) || ($_SESSION["organization"] == "N/A")) {?>
                    <p><?= $_SESSION["organization"] ?></p>
                <?php 
                    }
                } ?>
                <p>Barangay <?= isset($_SESSION["barangay"]) ? $_SESSION["barangay"] : "" ?>, <?= isset($_SESSION["city_municipality"]) ? $_SESSION["city_municipality"] : "" ?></p> 
                <div class="profile-link-container">
                    <a href="profileform.php" class="profile-link">Edit Profile <i class="fa fa-angle-double-right"></i></a>
                </div>
            </div>
            <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        
        <?php if(!empty($_SESSION['response'])): ?>
        <div class="flash-container">
            <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
                <?= $_SESSION['response']['msg'] ?>
            </div>
        </div>
        <?php 
        unset($_SESSION['response']); 
        endif; 
        ?>

        <!-- Eco Points Notification for Resolved Reports -->
        <?php
        if (isset($_SESSION['user_id'])) {
            require_once 'eco_points_notification.php';
            $ecoPointsNotification = getUnnotifiedResolvedReports($_SESSION['user_id']);
            if ($ecoPointsNotification) {
                echo generateEcoPointsNotificationCSS();
                echo generateEcoPointsNotificationHTML($ecoPointsNotification);
            }
        }
        ?>
        <div class="organizations-container">
            <?php if (!empty($flashMessage)): ?>
                <div class="flash-message flash-<?= $flashType ?>">
                    <?= htmlspecialchars($flashMessage) ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1><i class='bx bx-group'></i> Organizations</h1>
                <p>Join forces with other environmental advocates and earn eco points together!</p>
            </div>

            <div class="organization-tabs">
                <button class="tab-button active" onclick="switchTab('my-organization')">My Organization</button>
                <button class="tab-button" onclick="switchTab('browse-organizations')">Browse Organizations</button>
                <button class="tab-button" onclick="switchTab('create-organization')">Create Organization</button>
            </div>

            <!-- My Organization Tab -->
            <div id="my-organization" class="tab-content active">
                <?php if (!empty($current_user_organization) && $userOrgDetails): ?>
                    <div class="current-org-section">
                        <div class="current-org-header">
                            <div>
                                <div class="org-title-row">
                                    <h2><i class='bx bx-group'></i> <?= htmlspecialchars($current_user_organization) ?></h2>
                                    <?php if (isset($userOrgDetails['privacy_setting'])): ?>
                                        <span class="privacy-badge <?= $userOrgDetails['privacy_setting'] ?>">
                                            <i class='bx bx-<?= $userOrgDetails['privacy_setting'] === 'private' ? 'lock' : 'globe' ?>'></i>
                                            <?= ucfirst($userOrgDetails['privacy_setting']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($userOrgDetails['barangay']) && !empty($userOrgDetails['city_municipality'])): ?>
                                    <div class="org-location">
                                        <i class='bx bx-map'></i> 
                                        <?= htmlspecialchars($userOrgDetails['barangay']) ?>, <?= htmlspecialchars($userOrgDetails['city_municipality']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($userOrgDetails['description'])): ?>
                                    <div class="org-description">
                                        <?= htmlspecialchars($userOrgDetails['description']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($userOrgDetails['capacity_limit'])): ?>
                                    <div class="org-capacity">
                                        <i class='bx bx-users'></i> 
                                        Group Limit: <?= number_format($userOrgDetails['member_count']) ?>/<?= $userOrgDetails['capacity_limit'] ?> members
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="org-actions">
                                <a href="organization_profile.php?org_id=<?= $userOrgDetails['org_id'] ?>" class="btn btn-primary">
                                    <i class='bx bx-show'></i> View Full Profile
                                </a>
                                <?php if ($userOrgRole === 'creator'): ?>
                                    <button type="button" class="btn btn-outline" onclick="showEditOrgModal()">
                                        <i class='bx bx-edit'></i> Edit Organization
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="showTransferLeadershipModal()">
                                        <i class='bx bx-transfer'></i> Transfer Leadership
                                    </button>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="leave_organization">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to leave this organization?')">
                                        <i class='bx bx-exit'></i> Leave Organization
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="org-stats">
                            <div class="stat-card">
                                <div class="stat-number"><?= number_format($userOrgDetails['member_count']) ?>/<?= $userOrgDetails['capacity_limit'] ?></div>
                                <div class="stat-label">Members</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= number_format($userOrgDetails['total_points']) ?></div>
                                <div class="stat-label">Total Eco Points</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= number_format($userOrgDetails['avg_points']) ?></div>
                                <div class="stat-label">Average Points</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= $userOrgBadgeCount ?></div>
                                <div class="stat-label">Unique Badges</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= $userOrgEventCount ?></div>
                                <div class="stat-label">Events Organized</div>
                            </div>
                        </div>
                        
                        <h3><i class='bx bx-users'></i> Organization Members</h3>
                        <div class="members-grid">
                            <?php foreach ($orgMembers as $member): ?>
                                <div class="member-card">
                                    <div class="member-avatar">
                                        <?php if ($member['profile_thumbnail']): ?>
                                            <img src="<?= htmlspecialchars($member['profile_thumbnail']) ?>" alt="<?= htmlspecialchars($member['fullname']) ?>">
                                        <?php else: ?>
                                            <i class='bx bx-user'></i>
                                        <?php endif; ?>
                                    </div>
                                    <h4>
                                        <?= htmlspecialchars($member['fullname']) ?>
                                        <?php if ($member['role'] === 'creator'): ?>
                                            <span class="role-badge creator"><i class='bx bx-crown'></i> Creator</span>
                                        <?php elseif ($member['role'] === 'admin'): ?>
                                            <span class="role-badge admin"><i class='bx bx-shield'></i> Admin</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p><strong><?= number_format($member['eco_points']) ?></strong> eco points</p>
                                    <p><small><?= htmlspecialchars($member['barangay']) ?>, <?= htmlspecialchars($member['city_municipality']) ?></small></p>
                                    <a href="user_profile.php?user_id=<?= $member['account_id'] ?>" class="btn btn-outline">
                                        <i class='bx bx-user'></i> View Profile
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Invite Management Section for Creators -->
                        <?php if ($userOrgRole === 'creator' && isset($userOrgDetails['privacy_setting']) && $userOrgDetails['privacy_setting'] === 'private'): ?>
                            <div class="invite-section">
                                <h3><i class='bx bx-mail-send'></i> Invite Members</h3>
                                <p>Since your organization is private, you can invite users by email address.</p>
                                
                                <form method="POST" class="invite-form">
                                    <input type="hidden" name="action" value="send_invite">
                                    <div class="form-row">
                                        <input type="email" name="invite_email" placeholder="Enter user's email address" required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class='bx bx-send'></i> Send Invite
                                        </button>
                                    </div>
                                </form>
                                
                                <?php if (!empty($sentInvites)): ?>
                                    <h4><i class='bx bx-list-ul'></i> Sent Invitations</h4>
                                    <div class="invites-list">
                                        <?php foreach ($sentInvites as $invite): ?>
                                            <div class="invite-item">
                                                <div class="invite-info">
                                                    <strong><?= htmlspecialchars($invite['fullname']) ?></strong>
                                                    <span class="invite-email"><?= htmlspecialchars($invite['email']) ?></span>
                                                    <small>Sent: <?= date('M j, Y g:i A', strtotime($invite['invited_at'])) ?></small>
                                                </div>
                                                <div class="invite-status">
                                                    <?php if ($invite['status'] === 'pending'): ?>
                                                        <span class="status-badge pending">
                                                            <i class='bx bx-time'></i> Pending
                                                        </span>
                                                    <?php elseif ($invite['status'] === 'accepted'): ?>
                                                        <span class="status-badge accepted">
                                                            <i class='bx bx-check'></i> Accepted
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge declined">
                                                            <i class='bx bx-x'></i> Declined
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Join Requests Approval Section for Creators -->
                        <?php if ($userOrgRole === 'creator' && isset($userOrgDetails['privacy_setting']) && $userOrgDetails['privacy_setting'] === 'private' && !empty($joinRequests)): ?>
                            <div class="join-requests-section">
                                <h3><i class='bx bx-user-check'></i> Join Requests</h3>
                                <p>Users requesting to join your private organization:</p>
                                
                                <div class="join-requests-grid">
                                    <?php foreach ($joinRequests as $request): ?>
                                        <div class="join-request-card">
                                            <div class="request-header">
                                                <div class="user-info">
                                                    <h4><?= htmlspecialchars($request['fullname']) ?></h4>
                                                    <span class="user-email"><?= htmlspecialchars($request['email']) ?></span>
                                                    <div class="user-details">
                                                        <small><i class='bx bx-map'></i> <?= htmlspecialchars($request['barangay']) ?>, <?= htmlspecialchars($request['city_municipality']) ?></small>
                                                        <small><i class='bx bx-leaf'></i> <?= number_format($request['eco_points']) ?> eco points</small>
                                                    </div>
                                                </div>
                                                <div class="request-date">
                                                    <small><i class='bx bx-time'></i> Requested: <?= date('M j, Y g:i A', strtotime($request['requested_at'])) ?></small>
                                                </div>
                                            </div>
                                            <div class="request-actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approve_join_request">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve this join request?')">
                                                        <i class='bx bx-check'></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="reject_join_request">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this join request?')">
                                                        <i class='bx bx-x'></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Pending Invites Section -->
                    <?php if (!empty($pendingInvites)): ?>
                        <div class="pending-invites-section">
                            <h3><i class='bx bx-mail-send'></i> Organization Invitations</h3>
                            <p>You have received invitations to join organizations:</p>
                            
                            <div class="invites-grid">
                                <?php foreach ($pendingInvites as $invite): ?>
                                    <div class="invitation-card">
                                        <div class="invitation-header">
                                            <h4><?= htmlspecialchars($invite['org_name']) ?></h4>
                                            <small>Invited by: <?= htmlspecialchars($invite['invited_by']) ?></small>
                                        </div>
                                        <?php if (!empty($invite['description'])): ?>
                                            <p class="invitation-description">
                                                <?= htmlspecialchars($invite['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="invitation-date">
                                            <small><i class='bx bx-time'></i> Invited: <?= date('M j, Y g:i A', strtotime($invite['invited_at'])) ?></small>
                                        </div>
                                        <div class="invitation-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="respond_invite">
                                                <input type="hidden" name="invite_id" value="<?= $invite['id'] ?>">
                                                <input type="hidden" name="response" value="accept">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class='bx bx-check'></i> Accept
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="respond_invite">
                                                <input type="hidden" name="invite_id" value="<?= $invite['id'] ?>">
                                                <input type="hidden" name="response" value="decline">
                                                <button type="submit" class="btn btn-outline" onclick="return confirm('Are you sure you want to decline this invitation?')">
                                                    <i class='bx bx-x'></i> Decline
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($declinedRequests)): ?>
                        <div class="declined-requests-section">
                            <h3><i class='bx bx-x-circle'></i> Declined Join Requests</h3>
                            <p>Your requests to join these organizations were declined:</p>
                            
                            <div class="declined-requests-grid">
                                <?php foreach ($declinedRequests as $request): ?>
                                    <div class="declined-request-card">
                                        <div class="request-header">
                                            <h4><?= htmlspecialchars($request['org_name']) ?></h4>
                                            <span class="status-badge declined">Declined</span>
                                        </div>
                                        
                                        <?php if (!empty($request['description'])): ?>
                                            <p class="request-description"><?= htmlspecialchars($request['description']) ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="request-dates">
                                            <small>Requested: <?= date('M j, Y g:i A', strtotime($request['requested_at'])) ?></small><br>
                                            <small>Responded: <?= date('M j, Y g:i A', strtotime($request['responded_at'])) ?></small>
                                        </div>
                                        
                                        <form method="POST" class="request-actions">
                                            <input type="hidden" name="action" value="acknowledge_declined_request">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <button type="submit" class="btn btn-outline">
                                                <i class='bx bx-check'></i> OK
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="no-org-message">
                        <i class='bx bx-group'></i>
                        <h3>You're not part of any organization yet</h3>
                        <p>Join an existing organization or create your own to start earning eco points together!</p>
                        <div class="action-buttons" style="justify-content: center; margin-top: 20px;">
                            <button class="btn btn-primary" onclick="switchTab('browse-organizations')">
                                <i class='bx bx-search'></i> Browse Organizations
                            </button>
                            <button class="btn btn-outline" onclick="switchTab('create-organization')">
                                <i class='bx bx-plus'></i> Create Organization
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Browse Organizations Tab -->
            <div id="browse-organizations" class="tab-content">
                <!-- Location Filter Section -->
                <div class="filter-section">
                    <h3><i class='bx bx-filter'></i> Filter Organizations</h3>
                    <div class="filter-form">
                        <div class="filter-group">
                            <label for="filter_city_municipality">City/Municipality:</label>
                            <select id="filter_city_municipality" name="city_municipality" onchange="updateFilterBarangayDropdown(); filterOrganizations();">
                                <option value="">All Cities/Municipalities</option>
                                <?php
                                $cityQuery = "SELECT DISTINCT city_municipality FROM organizations ORDER BY city_municipality";
                                $cityResult = $connection->query($cityQuery);
                                while ($city = $cityResult->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($city['city_municipality']) . "'>" . htmlspecialchars($city['city_municipality']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_barangay">Barangay:</label>
                            <select id="filter_barangay" name="barangay" onchange="filterOrganizations();">
                                <option value="">All Barangays</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_privacy">Privacy Setting:</label>
                            <select id="filter_privacy" name="privacy_setting" onchange="filterOrganizations();">
                                <option value="">All Organizations</option>
                                <option value="public">Public Organizations</option>
                                <option value="private">Private Organizations</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="button" class="btn btn-outline" onclick="clearFilters()">
                                <i class='bx bx-refresh'></i> Clear Filters
                            </button>
                        </div>
                        
                        <div class="filter-status" id="filter-status">
                            <span class="loading-text" style="display: none;">
                                <i class='bx bx-loader-alt bx-spin'></i> Loading...
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="organizations-grid">
                    <!-- Organizations will be loaded here via JavaScript -->
                    <div class="loading-text">
                        <i class='bx bx-loader-alt bx-spin'></i> Loading organizations...
                    </div>
                </div>
            </div>

            <!-- Create Organization Tab -->
            <div id="create-organization" class="tab-content">
                <div class="create-org-form">
                    <h3><i class='bx bx-plus'></i> Create New Organization</h3>
                    <p>Start your own environmental organization and invite others to join your cause!</p>
                    
                    <form method="POST" id="create-org-form">
                        <input type="hidden" name="action" value="create_organization">
                        
                        <div class="form-group">
                            <label for="new_organization_name">Organization Name *</label>
                            <input type="text" id="new_organization_name" name="new_organization_name" 
                                   placeholder="Enter organization name" required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_organization_description">Description (Optional)</label>
                            <textarea id="new_organization_description" name="new_organization_description" 
                                      placeholder="Describe your organization's mission and goals" rows="4"></textarea>
                        </div>
                        
                        <!-- Location Section -->
                        <div class="form-section">
                            <h4><i class='bx bx-map'></i> Organization Location</h4>
                            <p class="section-desc">Specify the location this organization will represent</p>
                            
                            <div class="form-row">
                                <div class="form-group" id="city-dropdown">
                                    <label for="city-select">City/Municipality *</label>
                                    <select name="city_municipality" id="city-select" onchange="updateBarangayDropdownOrg()" required>
                                        <option value="">Select City/Municipality</option>
                                        <?php
                                        $cities = getcitymunicipality();
                                        foreach ($cities as $city) {
                                            echo '<option value="' . htmlspecialchars($city['city']) . '">' . htmlspecialchars($city['city']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="barangay-dropdown">
                                    <label for="barangay-select">Barangay *</label>
                                    <select name="barangay" id="barangay-select" required>
                                        <option value="">Select Barangay</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="manual-input-checkbox">
                                <input type="checkbox" id="manual-location-org" onchange="toggleManualInputOrg()">
                                <label for="manual-location-org">My organization's location is not listed above</label>
                            </div>
                            
                            <div class="manual-inputs" id="manual-inputs-org" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="manual-city-org">City/Municipality *</label>
                                        <input type="text" name="manual_city" id="manual-city-org" 
                                               placeholder="Enter City/Municipality">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="manual-barangay-org">Barangay *</label>
                                        <input type="text" name="manual_barangay" id="manual-barangay-org" 
                                               placeholder="Enter Barangay">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Capacity Section -->
                        <div class="form-section">
                            <h4><i class='bx bx-group'></i> Organization Capacity</h4>
                            <p class="section-desc">Set the maximum number of members for your organization</p>
                            
                            <div class="form-group">
                                <label for="capacity_limit">Member Capacity Limit *</label>
                                <input type="number" id="capacity_limit" name="capacity_limit" 
                                       min="10" max="50" value="25" required>
                                <small class="form-help">Minimum: 10 members | Maximum: 50 members</small>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-plus'></i> Create Organization
                            </button>
                        </div>
                    </form>
                </div>
                
                <div style="background: #e8f5e8; padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h4><i class='bx bx-info-circle'></i> Creating an Organization</h4>
                    <ul style="margin: 10px 0; padding-left: 20px; color: #666;">
                        <li>Choose a unique and meaningful name for your organization</li>
                        <li>Specify the location your organization will represent (city/municipality and barangay)</li>
                        <li>Set a member capacity limit between 10-50 members</li>
                        <li>You'll automatically become the creator and first member of the organization</li>
                        <li>Other users can join your organization from the Browse Organizations tab</li>
                        <li>Your organization will appear on leaderboards based on total eco points earned by all members</li>
                        <li>Organization location helps in local environmental initiatives and events</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Edit Organization Modal -->
        <div id="editOrgModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class='bx bx-edit'></i> Edit Organization</h3>
                    <span class="close" onclick="hideEditOrgModal()">&times;</span>
                </div>
                <form method="POST" class="modal-form">
                    <input type="hidden" name="action" value="edit_organization">
                    
                    <div class="form-section">
                        <h4><i class='bx bx-info-circle'></i> Organization Information</h4>
                        
                        <div class="form-group">
                            <label for="edit_organization_name">Organization Name</label>
                            <input type="text" id="edit_organization_name" name="edit_organization_name" required maxlength="100" 
                                   placeholder="Enter organization name">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_organization_description">Description (Optional)</label>
                            <textarea id="edit_organization_description" name="edit_organization_description" 
                                      rows="3" placeholder="Describe your organization's mission and goals"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_capacity_limit">Member Capacity (10-50)</label>
                                <input type="number" id="edit_capacity_limit" name="edit_capacity_limit" 
                                       min="10" max="50" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_privacy_setting">Privacy Setting</label>
                                <select id="edit_privacy_setting" name="edit_privacy_setting" required>
                                    <option value="public">Public - Anyone can join</option>
                                    <option value="private">Private - Invite only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class='bx bx-map'></i> Organization Location</h4>
                        
                        <div class="edit-dropdown-location">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_city_municipality">City/Municipality</label>
                                    <select id="edit_city_municipality" name="edit_city_municipality" onchange="updateEditBarangayDropdown()">
                                        <option value="">Select City/Municipality</option>
                                        <?php
                                        $cities = getcitymunicipality();
                                        foreach ($cities as $city) {
                                            $selected = (isset($userOrgDetails['city_municipality']) && $userOrgDetails['city_municipality'] == $city['city']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($city['city']) . '" ' . $selected . '>' . htmlspecialchars($city['city']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_barangay">Barangay</label>
                                    <select id="edit_barangay" name="edit_barangay">
                                        <option value="">Select Barangay</option>
                                        <?php
                                        if (isset($userOrgDetails['city_municipality']) && !empty($userOrgDetails['city_municipality'])) {
                                            // Load barangays for the current city
                                            $currentBarangays = json_decode(getBarangays($userOrgDetails['city_municipality']), true);
                                            if (is_array($currentBarangays)) {
                                                foreach ($currentBarangays as $barangay) {
                                                    $selected = (isset($userOrgDetails['barangay']) && $userOrgDetails['barangay'] == $barangay['barangay']) ? 'selected' : '';
                                                    echo '<option value="' . htmlspecialchars($barangay['barangay']) . '" ' . $selected . '>' . htmlspecialchars($barangay['barangay']) . '</option>';
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="edit-manual-location-org" onchange="toggleEditManualLocation()">
                            <label for="edit-manual-location-org">My organization's location is not listed above</label>
                        </div>
                        
                        <div class="edit-manual-location" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_manual_city">City/Municipality</label>
                                    <input type="text" id="edit_manual_city" name="edit_manual_city" 
                                           placeholder="Enter city/municipality">
                                </div>
                                <div class="form-group">
                                    <label for="edit_manual_barangay">Barangay</label>
                                    <input type="text" id="edit_manual_barangay" name="edit_manual_barangay" 
                                           placeholder="Enter barangay">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="hideEditOrgModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-save'></i> Update Organization
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transfer Leadership Modal -->
        <div id="transferLeadershipModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class='bx bx-transfer'></i> Transfer Leadership</h3>
                    <span class="close" onclick="hideTransferLeadershipModal()">&times;</span>
                </div>
                <form method="POST" class="modal-form">
                    <input type="hidden" name="action" value="transfer_leadership">
                    
                    <div class="form-section">
                        <h4><i class='bx bx-info-circle'></i> Transfer Organization Leadership</h4>
                        <p>Choose a member to transfer leadership to. Once transferred, you will become a regular member and the selected user will become the new organization creator.</p>
                        
                        <div class="form-group">
                            <label for="new_leader_id">Select New Leader *</label>
                            <select id="new_leader_id" name="new_leader_id" required>
                                <option value="">Choose a member...</option>
                                <?php if (!empty($orgMembers)): ?>
                                    <?php foreach ($orgMembers as $member): ?>
                                        <?php if ($member['account_id'] != $user_id && $member['role'] !== 'creator'): ?>
                                            <option value="<?= $member['account_id'] ?>">
                                                <?= htmlspecialchars($member['fullname']) ?> 
                                                (<?= number_format($member['eco_points']) ?> eco points)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="warning-box" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <h5 style="color: #856404; margin: 0 0 10px 0;"><i class='bx bx-warning'></i> Important Notice</h5>
                            <ul style="margin: 0; padding-left: 20px; color: #856404;">
                                <li>This action cannot be undone</li>
                                <li>You will become a regular member</li>
                                <li>The new leader will have full control over the organization</li>
                                <li>Only transfer leadership to trusted members</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="hideTransferLeadershipModal()">Cancel</button>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to transfer leadership? This action cannot be undone.')">
                            <i class='bx bx-transfer'></i> Transfer Leadership
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer>
                <div id="right-footer">
                    <h3>Follow us on</h3>
                    <div id="social-media-footer">
                        <ul>
                            <li>
                                <a href="#">
                                    <i class="fab fa-facebook"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <p>This website is developed by ManGrow. All Rights Reserved.</p>
                </div>
    </footer>

    <script>
        // Profile popup toggle functionality
        function toggleProfilePopup(event) {
            event.stopPropagation();
            const profileDetails = document.getElementById('profile-details');
            if (profileDetails) {
                profileDetails.classList.toggle('close');
            }
        }

        // Close profile details when clicking outside
        document.addEventListener('click', function(event) {
            const profileDetails = document.getElementById('profile-details');
            const userbox = document.querySelector('.userbox');
            
            if (profileDetails && userbox && 
                !profileDetails.contains(event.target) && 
                !userbox.contains(event.target)) {
                profileDetails.classList.add('close');
            }
        });

        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Search functionality
        const searchInput = document.querySelector('.searchbar input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const orgCards = document.querySelectorAll('.organization-card');
                
                orgCards.forEach(card => {
                    const orgName = card.querySelector('.organization-name').textContent.toLowerCase();
                    if (orgName.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
        
        // Organization location dropdown functions
        function updateBarangayDropdownOrg() {
            const citySelect = document.getElementById('city-select');
            const barangaySelect = document.getElementById('barangay-select');
            const selectedCity = citySelect.value;

            // Clear previous barangay options
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

            if (selectedCity) {
                // Fetch barangays for the selected city
                fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error fetching barangays:', data.error);
                            return;
                        }
                        
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.barangay;
                            option.textContent = item.barangay;
                            barangaySelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        }

        // Edit modal barangay dropdown function
        function updateEditBarangayDropdown() {
            const citySelect = document.getElementById('edit_city_municipality');
            const barangaySelect = document.getElementById('edit_barangay');
            const selectedCity = citySelect.value;

            // Clear previous barangay options
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

            if (selectedCity) {
                // Fetch barangays for the selected city
                fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error fetching barangays:', data.error);
                            return;
                        }
                        
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.barangay;
                            option.textContent = item.barangay;
                            barangaySelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        }

        // Filter barangay dropdown function
        function updateFilterBarangayDropdown() {
            const citySelect = document.getElementById('filter_city_municipality');
            const barangaySelect = document.getElementById('filter_barangay');
            const selectedCity = citySelect.value;

            // Clear previous barangay options but keep the current selection if applicable
            const currentBarangay = barangaySelect.value;
            barangaySelect.innerHTML = '<option value="">All Barangays</option>';

            if (selectedCity) {
                // Fetch barangays for the selected city from organizations
                fetch(`organizations_ajax.php?action=get_barangays&city_municipality=${encodeURIComponent(selectedCity)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error fetching barangays:', data.error);
                            return;
                        }
                        
                        data.barangays.forEach(barangay => {
                            const option = document.createElement('option');
                            option.value = barangay;
                            option.textContent = barangay;
                            
                            // Restore selection if it exists in the new list
                            if (barangay === currentBarangay) {
                                option.selected = true;
                            }
                            
                            barangaySelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        }

        // New function to filter organizations asynchronously
        function filterOrganizations() {
            const citySelect = document.getElementById('filter_city_municipality');
            const barangaySelect = document.getElementById('filter_barangay');
            const privacySelect = document.getElementById('filter_privacy');
            const organizationsGrid = document.querySelector('.organizations-grid');
            const loadingIndicator = document.querySelector('.loading-text');
            
            // Show loading indicator
            if (loadingIndicator) {
                loadingIndicator.style.display = 'flex';
            }
            
            // Load recommendations first (only on initial load)
            if (!organizationsGrid.dataset.loaded) {
                loadRecommendations().then(() => {
                    loadOrganizations();
                    organizationsGrid.dataset.loaded = 'true';
                });
            } else {
                loadOrganizations();
            }
            
            function loadRecommendations() {
                return fetch('organizations_ajax.php?action=get_recommendations')
                    .then(response => response.json())
                    .then(data => {
                        if (data.recommendations) {
                            displayRecommendations(data.recommendations);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading recommendations:', error);
                    });
            }
            
            function loadOrganizations() {
                const params = new URLSearchParams();
                params.append('action', 'get_organizations');
                
                if (citySelect.value) {
                    params.append('city_municipality', citySelect.value);
                }
                
                if (barangaySelect.value) {
                    params.append('barangay', barangaySelect.value);
                }
                
                if (privacySelect.value) {
                    params.append('privacy_setting', privacySelect.value);
                }
                
                fetch(`organizations_ajax.php?${params.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error fetching organizations:', data.error);
                            return;
                        }
                        
                        // Update organizations grid
                        displayOrganizations(data.organizations);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    })
                    .finally(() => {
                        // Hide loading indicator
                        if (loadingIndicator) {
                            loadingIndicator.style.display = 'none';
                        }
                    });
            }
        }
        
        function displayRecommendations(recommendations) {
            const organizationsGrid = document.querySelector('.organizations-grid');
            
            if (recommendations.length === 0) return;
            
            let html = `
                <div class="recommendations-section">
                    <h3 class="section-title">
                        <i class='bx bx-trophy'></i> 
                        Top Recommendations
                    </h3>
                    <div class="recommendations-grid">
            `;
            
            recommendations.forEach((org, index) => {
                html += generateOrganizationCard(org, index + 1, true);
            });
            
            html += `
                    </div>
                </div>
            `;
            
            organizationsGrid.innerHTML = html;
        }
        
        function displayOrganizations(organizations) {
            const organizationsGrid = document.querySelector('.organizations-grid');
            
            // Get existing recommendations HTML if any
            let existingRecommendations = '';
            const recommendationsSection = organizationsGrid.querySelector('.recommendations-section');
            if (recommendationsSection) {
                existingRecommendations = recommendationsSection.outerHTML;
            }
            
            if (organizations.length === 0) {
                organizationsGrid.innerHTML = existingRecommendations + `
                    <div class="location-based-section">
                        <div class="no-results-message">
                            <i class='bx bx-search'></i>
                            <h3>No organizations found</h3>
                            <p>Try adjusting your filter criteria to see more results.</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            let html = existingRecommendations + `
                <div class="location-based-section">
                    <h3 class="section-title">
                        <i class='bx bx-map-pin'></i> 
                        Browse Organizations
                    </h3>
                    <div class="location-based-grid">
            `;
            
            organizations.forEach(org => {
                html += generateOrganizationCard(org, null, false);
            });
            
            html += `
                    </div>
                </div>
            `;
            
            organizationsGrid.innerHTML = html;
        }

        // Function to update the organizations grid with new data (backward compatibility)
        function updateOrganizationsGrid(organizations) {
            // This function is kept for backward compatibility
            displayOrganizations(organizations);
        }
        
        // Helper function to generate organization card HTML
        function generateOrganizationCard(org, rank = null, isRecommendation = false) {
            const joinButtonHtml = org.is_current_user_org 
                ? `<button class="btn btn-outline" disabled>
                    <i class='bx bx-check'></i> Current Organization
                   </button>`
                : org.is_full 
                    ? `<button class="btn btn-outline" disabled>
                        <i class='bx bx-lock'></i> Organization Full
                       </button>`
                    : org.privacy_setting === 'private'
                        ? `<form method="POST" style="flex: 1;">
                            <input type="hidden" name="action" value="request_join">
                            <input type="hidden" name="organization_name" value="${escapeHtml(org.organization)}">
                            <button type="submit" class="btn btn-secondary">
                                <i class='bx bx-mail-send'></i> Request to Join
                            </button>
                           </form>`
                        : `<form method="POST" style="flex: 1;">
                            <input type="hidden" name="action" value="join_organization">
                            <input type="hidden" name="organization_name" value="${escapeHtml(org.organization)}">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-plus'></i> Join Organization
                            </button>
                           </form>`;
            
            const rankBadge = isRecommendation && rank ? `<div class="org-rank">#${rank}</div>` : '';
            
            return `
                <div class="organization-card ${isRecommendation ? 'recommendation-card' : 'location-card'}">
                    ${rankBadge}
                    <div class="org-header">
                        <div class="organization-name">
                            <i class='bx bx-group'></i> ${escapeHtml(org.organization)}
                        </div>
                        <span class="privacy-badge ${org.privacy_setting}">
                            <i class='bx bx-${org.privacy_setting === 'private' ? 'lock' : 'globe'}'></i>
                            ${org.privacy_setting === 'private' ? 'Private' : 'Public'}
                        </span>
                    </div>
                    
                    <div class="org-location">
                        <i class='bx bx-map'></i> 
                        ${escapeHtml(org.barangay)}, ${escapeHtml(org.city_municipality)}
                    </div>
                    
                    ${org.description ? `<div class="org-description">${escapeHtml(org.description)}</div>` : ''}
                    
                    <div class="org-card-stats">
                        <div class="org-stat">
                            <div class="org-stat-number">${formatNumber(org.member_count)}/${org.capacity_limit}</div>
                            <div class="org-stat-label">Members</div>
                        </div>
                        <div class="org-stat">
                            <div class="org-stat-number">${formatNumber(org.total_points)}</div>
                            <div class="org-stat-label">Total Points</div>
                        </div>
                        <div class="org-stat">
                            <div class="org-stat-number">${formatNumber(Math.round(org.avg_points))}</div>
                            <div class="org-stat-label">Avg Points</div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="organization_profile.php?org_id=${org.org_id}" class="btn btn-outline">
                            <i class='bx bx-show'></i> View Profile
                        </a>
                        ${joinButtonHtml}
                    </div>
                </div>
            `;
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Helper function to format numbers
        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        // Function to clear all filters
        function clearFilters() {
            const citySelect = document.getElementById('filter_city_municipality');
            const barangaySelect = document.getElementById('filter_barangay');
            const privacySelect = document.getElementById('filter_privacy');
            
            citySelect.selectedIndex = 0;
            barangaySelect.innerHTML = '<option value="">All Barangays</option>';
            barangaySelect.selectedIndex = 0;
            privacySelect.selectedIndex = 0;
            
            // Load all organizations
            filterOrganizations();
        }

        // Load organizations on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Load all organizations initially
            filterOrganizations();
        });

        function toggleManualInputOrg() {
            const checkbox = document.getElementById('manual-location-org');
            const manualInputs = document.getElementById('manual-inputs-org');
            const cityDropdown = document.getElementById('city-dropdown');
            const barangayDropdown = document.getElementById('barangay-dropdown');
            const citySelect = document.getElementById('city-select');
            const barangaySelect = document.getElementById('barangay-select');
            const manualCity = document.getElementById('manual-city-org');
            const manualBarangay = document.getElementById('manual-barangay-org');

            if (checkbox.checked) {
                // Show manual inputs
                manualInputs.style.display = 'block';
                cityDropdown.style.display = 'none';
                barangayDropdown.style.display = 'none';
                
                // Remove required from dropdowns
                citySelect.removeAttribute('required');
                barangaySelect.removeAttribute('required');
                
                // Add required to manual inputs
                manualCity.setAttribute('required', 'required');
                manualBarangay.setAttribute('required', 'required');
            } else {
                // Show dropdowns
                manualInputs.style.display = 'none';
                cityDropdown.style.display = 'block';
                barangayDropdown.style.display = 'block';
                
                // Add required to dropdowns
                citySelect.setAttribute('required', 'required');
                barangaySelect.setAttribute('required', 'required');
                
                // Remove required from manual inputs
                manualCity.removeAttribute('required');
                manualBarangay.removeAttribute('required');
                
                // Clear manual input values
                manualCity.value = '';
                manualBarangay.value = '';
            }
        }
        
        // Edit Organization Modal Functions
        function showEditOrgModal() {
            document.getElementById('editOrgModal').style.display = 'block';
        }
        
        function hideEditOrgModal() {
            document.getElementById('editOrgModal').style.display = 'none';
        }
        
        function loadCitiesForEdit(selectedCity = '', selectedBarangay = '') {
            fetch('organizations_ajax.php?action=load_cities')
                .then(response => response.json())
                .then(data => {
                    const citySelect = document.getElementById('edit_city_municipality');
                    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
                    
                    data.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;
                        // Only select if we have a valid selectedCity
                        if (selectedCity && selectedCity.trim() !== '' && city === selectedCity) {
                            option.selected = true;
                        }
                        citySelect.appendChild(option);
                    });
                    
                    // If a city is selected, load its barangays
                    if (selectedCity && selectedCity.trim() !== '') {
                        loadBarangaysForEdit(selectedCity, selectedBarangay);
                    }
                })
                .catch(error => console.error('Error loading cities:', error));
        }
        
        function loadBarangaysForEdit(selectedCity = '', selectedBarangay = '') {
            const citySelect = document.getElementById('edit_city_municipality');
            const barangaySelect = document.getElementById('edit_barangay');
            
            // Use the provided selectedCity or get it from the dropdown
            const cityToUse = selectedCity || citySelect.value;
            
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            
            if (cityToUse && cityToUse.trim() !== '') {
                fetch(`organizations_ajax.php?action=load_barangays&city=${encodeURIComponent(cityToUse)}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(barangay => {
                            const option = document.createElement('option');
                            option.value = barangay;
                            option.textContent = barangay;
                            // Only select if we have a valid selectedBarangay
                            if (selectedBarangay && selectedBarangay.trim() !== '' && barangay === selectedBarangay) {
                                option.selected = true;
                            }
                            barangaySelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading barangays:', error));
            }
        }
        
        function toggleEditManualLocation() {
            const checkbox = document.getElementById('edit-manual-location-org');
            const dropdownSection = document.querySelector('.edit-dropdown-location');
            const manualSection = document.querySelector('.edit-manual-location');
            
            if (checkbox.checked) {
                dropdownSection.style.display = 'none';
                manualSection.style.display = 'block';
            } else {
                dropdownSection.style.display = 'block';
                manualSection.style.display = 'none';
                
                // Clear manual input values
                document.getElementById('edit_manual_city').value = '';
                document.getElementById('edit_manual_barangay').value = '';
            }
        }
        
        // Edit Organization Modal Functions
        function showEditOrgModal() {
            <?php if (!empty($current_user_organization) && $userOrgDetails): ?>
                // Populate modal with current organization data
                document.getElementById('edit_organization_name').value = '<?= addslashes($current_user_organization) ?>';
                document.getElementById('edit_organization_description').value = '<?= addslashes($userOrgDetails['description'] ?? '') ?>';
                document.getElementById('edit_capacity_limit').value = '<?= $userOrgDetails['capacity_limit'] ?>';
                document.getElementById('edit_privacy_setting').value = '<?= $userOrgDetails['privacy_setting'] ?? 'public' ?>';
                
                // Store current location values
                const currentCity = '<?= addslashes($userOrgDetails['city_municipality'] ?? '') ?>';
                const currentBarangay = '<?= addslashes($userOrgDetails['barangay'] ?? '') ?>';
                
                // Load cities and set current values
                loadCitiesForEdit(currentCity, currentBarangay);
            <?php endif; ?>
                
            document.getElementById('editOrgModal').style.display = 'block';
        }
        
        function hideEditOrgModal() {
            document.getElementById('editOrgModal').style.display = 'none';
        }
        
        function loadCitiesForEdit(selectedCity = '', selectedBarangay = '') {
            const citySelect = document.getElementById('edit_city_municipality');
            
            fetch('organizations_ajax.php?action=get_cities')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching cities:', data.error);
                        return;
                    }
                    
                    // Clear existing options except the first one
                    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
                    
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.city_municipality;
                        option.textContent = item.city_municipality;
                        citySelect.appendChild(option);
                    });
                    
                    // Set the current city if provided
                    if (selectedCity) {
                        citySelect.value = selectedCity;
                        // Load barangays for the selected city
                        loadBarangaysForEdit(selectedBarangay);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function loadBarangaysForEdit(selectedBarangay = '') {
            const citySelect = document.getElementById('edit_city_municipality');
            const barangaySelect = document.getElementById('edit_barangay');
            const selectedCity = citySelect.value;
            
            // Clear barangay options
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            
            if (!selectedCity) return;
            
            fetch(`organizations_ajax.php?action=get_barangays&city=${encodeURIComponent(selectedCity)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching barangays:', data.error);
                        return;
                    }
                    
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.barangay;
                        option.textContent = item.barangay;
                        barangaySelect.appendChild(option);
                    });
                    
                    // Set the current barangay if provided
                    if (selectedBarangay) {
                        barangaySelect.value = selectedBarangay;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Transfer Leadership Modal Functions
        function showTransferLeadershipModal() {
            document.getElementById('transferLeadershipModal').style.display = 'block';
        }
        
        function hideTransferLeadershipModal() {
            document.getElementById('transferLeadershipModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editOrgModal');
            const transferModal = document.getElementById('transferLeadershipModal');
            if (event.target === editModal) {
                hideEditOrgModal();
            }
            if (event.target === transferModal) {
                hideTransferLeadershipModal();
            }
        }
    </script>

</body>
</html>