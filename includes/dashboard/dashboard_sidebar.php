<?php
/**
 * Shared Dashboard Sidebar Component
 * Provides consistent sidebar navigation across all dashboards
 */

// Include permission system and database connection
require_once __DIR__ . '/dashboard_permissions.php';
require_once __DIR__ . '/../../config/database.php';

// Get user information
$userInfo = getUserInfo();
$userRole = getUserRole();
$navigation = getDashboardNavigation();

if (!$userInfo || !$userRole) {
    die('User information not found');
}

$username = $userInfo['fname'] . ' ' . $userInfo['lname'];
$roleName = $userRole['role_name'];
?>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarLabel">Menu</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-3">
        <!-- Mobile Profile Section -->
        <div class="text-center mb-4">
            <?php 
            // Use the same profile picture logic
            $mobileImagePath = ($profileData && $profileData['profile_picture'] && file_exists($profileData['profile_picture'])) ? $profileData['profile_picture'] : $defaultImage;
            ?>
            <div class="position-relative d-inline-block mb-3">
                <img id="mobileSidebarProfileImg" src="<?= $mobileImagePath ?>" alt="Profile Picture" class="rounded-circle" width="60" height="60" style="object-fit: cover; border: 2px solid #fff;">
                <button id="mobileEditProfileBtn" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 rounded-circle" style="width: 24px; height: 24px; padding: 0; font-size: 10px;" title="Edit Profile" type="button" onclick="showTab('profile', this); closeOffcanvas(); return false;">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <div class="fw-semibold text-white"><?= htmlspecialchars($username) ?></div>
            <small class="text-light opacity-75"><?= htmlspecialchars($roleName) ?></small>
        </div>
        
        <nav class="nav flex-column">
            <?php foreach ($navigation as $key => $item): ?>
                <a class="nav-link <?= $key === 'overview' ? 'active' : '' ?>" 
                   href="<?= $item['href'] ?>" 
                   onclick="showTab('<?= $key ?>', this); closeOffcanvas(); return false;">
                    <span class="bi <?= $item['icon'] ?> me-2"></span> <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>
            
            <!-- Divider -->
            <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
            
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <span class="bi bi-box-arrow-right me-2"></span> Logout
            </a>
        </nav>
    </div>
</div>

<!-- Desktop Sidebar -->
<div class="sidebar d-none d-lg-flex flex-column">
    <div class="text-center mb-4">
        <?php 
        // Get profile picture from database
        $stmt = $conn->prepare("SELECT profile_picture FROM account WHERE acc_id = ?");
        $stmt->bind_param("i", $userInfo['acc_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();
        
        $defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+';
        $imagePath = ($profileData && $profileData['profile_picture'] && file_exists($profileData['profile_picture'])) ? $profileData['profile_picture'] : $defaultImage;
        ?>
        <div class="position-relative d-inline-block mb-3">
            <img id="sidebarProfileImg" src="<?= $imagePath ?>" alt="Profile Picture" class="rounded-circle" width="80" height="80" style="object-fit: cover; border: 3px solid #fff;">
            <button id="editProfileBtn" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 rounded-circle" style="width: 28px; height: 28px; padding: 0; font-size: 12px;" title="Edit Profile" type="button" onclick="showTab('profile', this)">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
        <div class="fw-semibold mt-2"><?= htmlspecialchars($username) ?></div>
        <small class="text-light opacity-75"><?= htmlspecialchars($roleName) ?></small>
    </div>
    <nav class="nav flex-column px-3">
        <?php foreach ($navigation as $key => $item): ?>
            <a class="nav-link <?= $key === 'overview' ? 'active' : '' ?>" 
               href="<?= $item['href'] ?>" 
               onclick="showTab('<?= $key ?>', this)">
                <span class="bi <?= $item['icon'] ?> me-2"></span> <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
        
        <!-- Divider -->
        <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
        
        <a class="nav-link" href="#logoutModal" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <span class="bi bi-box-arrow-right me-2"></span> Logout
        </a>
    </nav>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-maroon text-white">
                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Are you sure you want to logout?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <a href="../index.php" class="btn btn-maroon rounded-pill">Logout</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Ensure logout modal is above backdrop (1100) and other modals */
    #logoutModal {
        z-index: 1105 !important;
    }
    
    #logoutModal.modal {
        z-index: 1105 !important;
    }
    
    #logoutModal .modal-dialog {
        z-index: 1106 !important;
    }
    
    /* Logout Modal Styling - Clean card design with proper spacing */
    #logoutModal .modal-dialog {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: calc(100% - 1rem) !important;
        margin: 0.5rem auto !important;
    }
    
    #logoutModal .modal-content {
        border: none !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
        max-width: 450px !important;
        width: 100% !important;
        overflow: hidden !important;
        position: relative !important;
        z-index: 1107 !important;
    }
    
    #logoutModal .modal-header {
        background: #800000 !important;
        background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
        color: #ffffff !important;
        padding: 1.25rem 1.5rem !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 16px 16px 0 0 !important;
    }
    
    #logoutModal .modal-title {
        font-weight: 600 !important;
        font-size: 1.1rem !important;
        margin: 0 !important;
        color: #ffffff !important;
    }
    
    #logoutModal .btn-close-white {
        filter: brightness(0) invert(1) !important;
        opacity: 0.9 !important;
    }
    
    #logoutModal .btn-close-white:hover {
        opacity: 1 !important;
    }
    
    #logoutModal .modal-body {
        padding: 1.75rem 1.5rem !important;
        color: #212529 !important;
        font-size: 1rem !important;
        line-height: 1.5 !important;
        background: #ffffff !important;
    }
    
    #logoutModal .modal-footer {
        padding: 1.25rem 1.5rem !important;
        border-top: 1px solid #dee2e6 !important;
        background: #f8f9fa !important;
        gap: 0.75rem !important;
        display: flex !important;
        justify-content: flex-end !important;
    }
    
    #logoutModal .modal-footer .btn {
        padding: 0.625rem 1.25rem !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        font-size: 0.95rem !important;
        min-width: 100px !important;
        height: 42px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.2s ease !important;
    }
    
    #logoutModal .modal-footer .btn-secondary {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        color: #ffffff !important;
    }
    
    #logoutModal .modal-footer .btn-secondary:hover {
        background-color: #5a6268 !important;
        border-color: #545b62 !important;
        color: #ffffff !important;
    }
    
    #logoutModal .modal-footer .btn-maroon {
        background: #800000 !important;
        background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
        border: none !important;
        color: #ffffff !important;
        text-decoration: none !important;
    }
    
    #logoutModal .modal-footer .btn-maroon:hover {
        background: #660000 !important;
        background: linear-gradient(135deg, #660000 0%, #800000 100%) !important;
        color: #ffffff !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3) !important;
    }
    
    /* Mobile: Touch-friendly logout buttons */
    @media (max-width: 991.98px) {
        #logoutModal .modal-footer .btn {
            min-height: 44px !important; /* Touch-friendly minimum height */
            padding: 0.75rem 1.5rem !important; /* Larger touch target */
            font-size: 1rem !important; /* Slightly larger text for readability */
            min-width: 120px !important; /* Wider buttons for easier tapping */
        }
        
        #logoutModal .modal-footer {
            flex-direction: column-reverse !important; /* Stack buttons vertically on mobile */
            gap: 0.75rem !important;
        }
        
        #logoutModal .modal-footer .btn {
            width: 100% !important; /* Full-width buttons on mobile */
        }
        
        /* Ensure logout link in sidebar is touch-friendly */
        .sidebar-offcanvas .nav-link[data-bs-target="#logoutModal"],
        .sidebar .nav-link[data-bs-target="#logoutModal"],
        .nav-link[data-bs-target="#logoutModal"] {
            min-height: 44px !important;
            padding: 0.75rem 1rem !important;
            display: flex !important;
            align-items: center !important;
        }
        
        #logoutModal .modal-dialog {
            margin: 1rem !important;
            max-width: calc(100% - 2rem) !important;
            width: calc(100% - 2rem) !important;
        }
        
        #logoutModal.modal.show {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 1rem !important;
        }
    }
    
    @media (max-width: 480px) {
        #logoutModal .modal-content {
            width: calc(100% - 2rem) !important;
            max-width: calc(100% - 2rem) !important;
            margin: 0 1rem !important;
        }
        
        #logoutModal .modal-header,
        #logoutModal .modal-body,
        #logoutModal .modal-footer {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
    }
</style>

<script>
// Global tab switching functions
// Only define if not already defined (to prevent conflicts)
if (typeof window.showTab === 'undefined') {
    window.showTab = function(tabId, element) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Show selected tab
        const targetTab = document.getElementById(tabId);
        if (targetTab) {
            targetTab.style.display = 'block';
        }
        
        // CRITICAL: Remove active class from ALL navigation links FIRST
        const allNavLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link');
        allNavLinks.forEach(link => {
            link.classList.remove('active');
        });
        
        // Add active class only to the clicked element
        if (element && element.classList.contains('nav-link')) {
            element.classList.add('active');
            
            // Also activate corresponding link in other sidebar
            // Extract tabId from onclick for precise matching (avoids matching multiple links)
            const extractTabIdFromOnclick = (onclickStr) => {
                if (!onclickStr) return null;
                // Match pattern: showTab('tabId', ...) or showTab("tabId", ...)
                const match = onclickStr.match(/showTab\s*\(\s*['"]([^'"]+)['"]/);
                return match ? match[1] : null;
            };
            
            const clickedTabId = extractTabIdFromOnclick(element.getAttribute('onclick')) || tabId;
            
            const correspondingLink = Array.from(allNavLinks).find(link => {
                if (link === element) return false;
                
                // Match by extracted tabId from onclick (most precise - avoids false matches)
                const linkTabId = extractTabIdFromOnclick(link.getAttribute('onclick'));
                if (linkTabId && clickedTabId && linkTabId === clickedTabId) {
                    return true;
                }
                
                // Fallback: match by exact onclick content
                const linkOnclick = (link.getAttribute('onclick') || '').trim();
                const elementOnclick = (element.getAttribute('onclick') || '').trim();
                if (linkOnclick && elementOnclick && linkOnclick === elementOnclick && linkOnclick !== '') {
                    return true;
                }
                
                // Fallback: match by href if both have the same href (and it's not just '#')
                const linkHref = (link.getAttribute('href') || '').trim();
                const elementHref = (element.getAttribute('href') || '').trim();
                if (linkHref && elementHref && linkHref === elementHref && linkHref !== '#' && linkHref !== '') {
                    return true;
                }
                
                return false;
            });
            
            if (correspondingLink) {
                correspondingLink.classList.add('active');
            }
        }
    };
}

window.closeOffcanvas = function() {
    const offcanvasEl = document.getElementById('sidebarOffcanvas');
    if (offcanvasEl && window.bootstrap && bootstrap.Offcanvas) {
        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (bsOffcanvas) {
            bsOffcanvas.hide();
        }
    }
};

// Initialize default tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const defaultTab = document.getElementById('overview');
    if (defaultTab) {
        defaultTab.style.display = 'block';
    }
    
    // Handle URL parameters for tab switching
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        const targetLink = document.querySelector(`.nav-link[onclick*="${tab}"]`);
        if (targetLink) {
            showTab(tab, targetLink);
        }
    }
});

// Profile picture update functions
window.updateSidebarProfilePicture = function(imagePath) {
    // Update desktop sidebar profile picture
    const desktopImg = document.getElementById('sidebarProfileImg');
    if (desktopImg) {
        desktopImg.src = imagePath + '?t=' + new Date().getTime();
    }
    
    // Update mobile sidebar profile picture
    const mobileImg = document.getElementById('mobileSidebarProfileImg');
    if (mobileImg) {
        mobileImg.src = imagePath + '?t=' + new Date().getTime();
    }
};

window.updateSidebarProfilePictureToDefault = function() {
    const defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iMzUiIHI9IjE1IiBmaWxsPSIjOEE4QTg4Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMyLjY0MDYgNTMgNDcgNTNINjNDNzcuMzU5NCA1MyA5MCA2NS42NDA2IDkwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM4QThBODgiLz4KPC9zdmc+';
    
    // Update desktop sidebar profile picture
    const desktopImg = document.getElementById('sidebarProfileImg');
    if (desktopImg) {
        desktopImg.src = defaultImage;
    }
    
    // Update mobile sidebar profile picture
    const mobileImg = document.getElementById('mobileSidebarProfileImg');
    if (mobileImg) {
        mobileImg.src = defaultImage;
    }
};
</script>
