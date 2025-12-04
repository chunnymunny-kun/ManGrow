<?php
function loginme($email, $password) {
    require 'database.php';
    session_start();

    try {
        // Prepared statement to prevent SQL injection
        $stmt = $connection->prepare("SELECT * FROM accountstbl WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Password Verification with hashed passwords
            if (password_verify($password, $row["password"])) {
                // Check if there's a new badge awarded before setting session data
                $badgeAwarded = isset($_SESSION['new_badge_awarded']) ? $_SESSION['new_badge_awarded'] : null;
                
                $_SESSION["login"] = true;
                $_SESSION["user_id"] = $row["account_id"];
                $_SESSION["name"] = $row["fullname"];
                $_SESSION["email"] = $row["email"];
                $_SESSION["personal_email"] = $row["personal_email"];
                $_SESSION["accessrole"] = $row["accessrole"];
                $_SESSION["profile_image"] = $row["profile_thumbnail"];
                $_SESSION["city_municipality"] = $row["city_municipality"];
                $_SESSION["barangay"] = $row["barangay"];
                $_SESSION["organization"] = $row["organization"];
                $_SESSION["bio"] = $row["bio"];
                $_SESSION["eco_points"] = $row["eco_points"];
                $_SESSION["total_eco_points"] = $row["total_eco_points"];
                
                // Preserve badge notification if it exists
                if ($badgeAwarded) {
                    $_SESSION['new_badge_awarded'] = $badgeAwarded;
                }
                
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => 'Login successful! Welcome back.'
                ];
                
                // Redirect to the appropriate page after login
                if (isset($_SESSION['redirect_url'])) {
                    header("Location: " . $_SESSION['redirect_url']);
                    exit();
                }
                // Default redirect if no specific URL is set
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['response'] = [
                    'status' => 'error',
                    'msg' => 'Incorrect credentials! Please try again.'
                ];
                header("Location: login.php");
                exit();
            }
        } else {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'Incorrect credentials! Please try again.'
            ];
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Login error: ' . $e->getMessage()
        ];
        header("Location: login.php");
        exit();
    }
}
?>