<?php
/**
 * Authentication Check Script
 * 
 * This script checks if a user is logged in and has a valid session.
 * It also verifies the user's role and provides the current user's details.
 */

// Start or resume the session
session_start();

// Redirect to login if no user_id in session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once 'db_connection.php';

try {
    // Get current user's details from database
    $stmt = $conn->prepare("
        SELECT u.*, 
               e.employee_id, e.field_id, e.role, e.current_status,
               f.field_name,
               c.customer_id
        FROM Users u
        LEFT JOIN Employees e ON u.user_id = e.user_id
        LEFT JOIN BrickField f ON e.field_id = f.field_id
        LEFT JOIN Customers c ON u.user_id = c.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user not found in database, destroy session and redirect
    if (!$current_user) {
        session_destroy();
        header("Location: login.php?error=invalid_session");
        exit();
    }

    // Check if user account is active
    if (!$current_user['is_active']) {
        session_destroy();
        header("Location: login.php?error=account_inactive");
        exit();
    }

    // Check if employee status is active (for employees)
    if (in_array($current_user['user_type'], ['manager', 'supervisor', 'worker']) && 
        $current_user['current_status'] != 'active') {
        session_destroy();
        header("Location: login.php?error=account_suspended");
        exit();
    }

    // Make user data available globally
    $GLOBALS['current_user'] = $current_user;

    // Role-based access control can be implemented here if needed
    // Example: 
    // if ($current_user['user_type'] !== 'admin' && basename($_SERVER['PHP_SELF']) === 'admin_page.php') {
    //     header("Location: unauthorized.php");
    //     exit();
    // }

} catch (PDOException $e) {
    // Log the error (in a real application, you'd log to a file or system)
    error_log("Database error in auth_check.php: " . $e->getMessage());
    
    // Destroy session and redirect to login with error
    session_destroy();
    header("Location: login.php?error=system_error");
    exit();
}

/**
 * Function to check if current user has specific role
 * 
 * @param string|array $roles Role or array of roles to check against
 * @return bool True if user has one of the specified roles
 */
function has_role($roles) {
    if (!isset($GLOBALS['current_user'])) return false;
    
    if (is_array($roles)) {
        return in_array($GLOBALS['current_user']['user_type'], $roles);
    }
    
    return $GLOBALS['current_user']['user_type'] === $roles;
}

/**
 * Function to check if current user has specific permission
 * (You would expand this based on your permission system)
 * 
 * @param string $permission Permission to check
 * @return bool True if user has permission
 */
function has_permission($permission) {
    // In a real application, you'd check against a permissions table
    // This is a simplified version
    
    if (!isset($GLOBALS['current_user'])) return false;
    
    // Admin has all permissions
    if ($GLOBALS['current_user']['user_type'] === 'admin') return true;
    
    // Example permission check for supervisors
    if ($GLOBALS['current_user']['user_type'] === 'supervisor') {
        $supervisor_permissions = [
            'view_production',
            'manage_workers',
            'quality_control'
        ];
        return in_array($permission, $supervisor_permissions);
    }
    
    // Add other role permissions as needed
    
    return false;
}

/**
 * Function to require a specific role
 * 
 * @param string|array $roles Required role(s)
 * @param string $redirect_url URL to redirect if role check fails
 */
function require_role($roles, $redirect_url = 'unauthorized.php') {
    if (!has_role($roles)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Function to require a specific permission
 * 
 * @param string $permission Required permission
 * @param string $redirect_url URL to redirect if permission check fails
 */
function require_permission($permission, $redirect_url = 'unauthorized.php') {
    if (!has_permission($permission)) {
        header("Location: $redirect_url");
        exit();
    }
}
?>