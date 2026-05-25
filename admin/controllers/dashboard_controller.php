<?php
/**
 * Admin Dashboard Controller
 * Handles data preparation and business logic for the admin dashboard
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/dashboard/dashboard_permissions.php';

class AdminDashboardController {
    private $conn;
    private $userInfo;
    private $userRole;
    
    public function __construct() {
        $this->conn = $GLOBALS['conn'];
        $this->userInfo = getUserInfo();
        $this->userRole = getUserRole();
        
        if (!$this->userInfo || !$this->userRole) {
            die('User information not found');
        }
    }
    
    /**
     * Get user profile data including profile picture
     */
    public function getUserProfileData() {
        $stmt = $this->conn->prepare("SELECT profile_picture FROM account WHERE acc_id = ?");
        $stmt->bind_param("i", $this->userInfo['acc_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();
        
        $defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiByeD0iNjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iNjAiIGN5PSI0NSIgcj0iMjAiIGZpbGw9IiM5Y2E5YWEiLz4KPHBhdGggZD0iTTMwIDkwQzMwIDc1LjY0MDYgNDIuNjQwNiA2MyA1NyA2M0g2M0M3Ny4zNTk0IDYzIDkwIDc1LjY0MDYgOTAgOTBWMTAwSDMwVjkwWiIgZmlsbD0iIzljYTlhYSIvPgo8L3N2Zz4K';
        
        // Fix path for admin dashboard
        $imagePath = $defaultImage;
        if ($profileData && $profileData['profile_picture']) {
            $dbPath = $profileData['profile_picture'];
            
            // Check if it's a data URI (default image)
            if (strpos($dbPath, 'data:image') === 0) {
                $imagePath = $dbPath;
            } else {
                // Build possible file system paths to check
                $baseDir = __DIR__ . '/../../';
                $cleanPath = ltrim($dbPath, '/');
                
                $possibleFilePaths = [
                    $baseDir . 'public/assets/uploads/profile_pictures/' . basename($cleanPath),
                    $baseDir . 'assets/uploads/profile_pictures/' . basename($cleanPath),
                    $baseDir . 'public/' . $cleanPath,
                    $baseDir . $cleanPath
                ];
                
                $possibleWebPaths = [
                    '../../public/assets/uploads/profile_pictures/' . basename($cleanPath),
                    '../../assets/uploads/profile_pictures/' . basename($cleanPath),
                    '../../public/' . $cleanPath,
                    '../../' . $cleanPath
                ];
                
                // Check which file actually exists
                $foundPath = null;
                foreach ($possibleFilePaths as $index => $filePath) {
                    if (file_exists($filePath) && is_file($filePath)) {
                        $foundPath = $possibleWebPaths[$index];
                        break;
                    }
                }
                
                if ($foundPath) {
                    $imagePath = $foundPath . '?t=' . time();
                } else {
                    // File doesn't exist, use default image
                    $imagePath = $defaultImage;
                }
            }
        }
        
        return [
            'profile_picture' => $imagePath,
            'has_custom_picture' => $profileData && $profileData['profile_picture']
        ];
    }
    
    /**
     * Get user information for display
     */
    public function getUserDisplayData() {
        return [
            'username' => $this->userInfo['fname'] . ' ' . $this->userInfo['lname'],
            'role_name' => $this->userRole['role_name'],
            'dept_name' => $this->userInfo['dept_name'] ?? 'All Departments',
            'acc_id' => $this->userInfo['acc_id'],
            'dept_id' => $this->userInfo['dept_id'] ?? 0,
            // Add individual fields for profile form
            'fname' => $this->userInfo['fname'],
            'lname' => $this->userInfo['lname'],
            'minitial' => $this->userInfo['minitial'] ?? '',
            'acc_user' => $this->userInfo['acc_user'],
            'acc_email' => $this->userInfo['acc_email'],
            'acc_status' => $this->userInfo['acc_status']
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        // This would typically call the existing get_dashboard_data.php logic
        // For now, return empty array - the existing AJAX call will handle this
        return [];
    }
    
    /**
     * Get accounts data for the users tab
     */
    public function getAccountsData($dept_id = null) {
        // This would typically call the existing get_accounts.php logic
        // For now, return empty array - the existing AJAX call will handle this
        return [];
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($permission) {
        return hasPermission($permission);
    }
    
    /**
     * Get user info for JavaScript
     */
    public function getJavaScriptUserData() {
        return [
            'acc_id' => $this->userInfo['acc_id'],
            'dept_id' => $this->userInfo['dept_id'] ?? 0
        ];
    }
    
    /**
     * Get complete user profile data for editing
     */
    public function getCompleteUserProfileData() {
        $stmt = $this->conn->prepare("
            SELECT a.acc_id, a.fname, a.lname, a.minitial, a.acc_user, a.acc_email, 
                   a.acc_status, a.profile_picture, d.dept_name, d.dept_id
            FROM account a 
            LEFT JOIN department d ON a.dept_id = d.dept_id
            WHERE a.acc_id = ?
        ");
        $stmt->bind_param("i", $this->userInfo['acc_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();
        
        return $profileData;
    }
}

// Create global instance
$dashboardController = new AdminDashboardController();
$profileData = $dashboardController->getUserProfileData();
$userDisplayData = $dashboardController->getUserDisplayData();
$jsUserData = $dashboardController->getJavaScriptUserData();
?>
