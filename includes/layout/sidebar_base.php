<?php
/**
 * Unified Sidebar Base Template
 * Provides consistent sidebar UI across all roles.
 *
 * Expected variables (set before including this file):
 * - $username: User's display name
 * - $roleName: User's role name (optional)
 * - $profileImage: Profile picture path (optional, defaults to icon)
 * - $dept_name: Department name (optional)
 *
 * Menu items are loaded from includes/sidebar/menus/{role}.php
 *
 * Permission-aware visibility:
 * - Menu items may define a `permissions` array of permission keys.
 * - If defined, the item is only shown when the user has at least one of those
 *   permissions (based on `permissions` and `user_permissions` tables).
 * - Sections with no visible items are automatically hidden.
 */

// Load permission visibility helpers (real-time view of role + user overrides)
require_once __DIR__ . '/../utils/permission_visibility.php';

// Get user role from session
$userRole = $_SESSION['role'] ?? 'User';

// Normalize role name for file lookup
$roleFileMap = [
    'Admin' => 'admin',
    'Admin support' => 'admin_support',
    'Moderator' => 'moderator',
    'User' => 'user',
    'Instructor' => 'user'
];

$roleKey = $roleFileMap[$userRole] ?? 'user';

// Load raw menu configuration for this role
$menuConfigPath = __DIR__ . '/../sidebar/menus/' . $roleKey . '.php';
$menuSections = [];

if (file_exists($menuConfigPath)) {
    $menuSections = include $menuConfigPath;
} else {
    // Fallback to empty menu if config file doesn't exist
    $menuSections = [];
}

// Filter menu sections/items based on permissions
$currentUserId = $_SESSION['acc_id'] ?? null;
if ($currentUserId) {
    $filteredSections = [];

    foreach ($menuSections as $section) {
        if (empty($section['items']) || !is_array($section['items'])) {
            continue;
        }

        $visibleItems = [];

        foreach ($section['items'] as $item) {
            // If no explicit permissions are configured, the item is always visible
            if (empty($item['permissions']) || !is_array($item['permissions'])) {
                $visibleItems[] = $item;
                continue;
            }

            // Show item when the user has at least one of the required permissions
            $hasAnyPermission = false;
            foreach ($item['permissions'] as $permKey) {
                if (hasVisiblePermission($permKey, $currentUserId)) {
                    $hasAnyPermission = true;
                    break;
                }
            }

            if ($hasAnyPermission) {
                $visibleItems[] = $item;
            }
        }

        // Only keep sections that still have visible items
        if (!empty($visibleItems)) {
            $section['items'] = $visibleItems;
            $filteredSections[] = $section;
        }
    }

    $menuSections = $filteredSections;
}

// Set default values if not provided
$username = $username ?? 'User';
$roleName = $roleName ?? $userRole;
$profileImage = $profileImage ?? null;
$dept_name = $dept_name ?? '';

// Fix profile image path - if it's a relative path, convert to absolute URL for web access
if (!empty($profileImage) && !str_starts_with($profileImage, 'data:') && !str_starts_with($profileImage, 'http')) {
    // It's a relative path, need to convert to absolute URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    
    // Calculate base path - find 'views' in path and get everything before it
    $pathParts = explode('/', trim($scriptPath, '/'));
    $viewsIndex = array_search('views', $pathParts);
    
    if ($viewsIndex !== false) {
        // Get all parts before 'views'
        $rootParts = array_slice($pathParts, 0, $viewsIndex);
        $basePath = empty($rootParts) ? '/' : '/' . implode('/', $rootParts) . '/';
    } else {
        // Fallback: go up 3 levels from current script
        $basePath = dirname(dirname(dirname($scriptPath)));
        if ($basePath === '/' || $basePath === '.' || empty($basePath)) {
            $basePath = '/';
        } else {
            $basePath = rtrim($basePath, '/') . '/';
        }
    }
    
    // Normalize the profile image path
    // Remove ../ and normalize
    $cleanProfilePath = $profileImage;
    
    // Count how many ../ are in the path
    $upLevels = substr_count($cleanProfilePath, '../');
    if ($upLevels > 0) {
        // Remove ../ from path
        $cleanProfilePath = str_replace('../', '', $cleanProfilePath);
        $cleanProfilePath = ltrim($cleanProfilePath, '/');
    } else {
        // Already a clean path, just remove leading /
        $cleanProfilePath = ltrim($cleanProfilePath, '/');
    }
    
    // Ensure path starts with 'public/' if it's an assets path
    if (str_starts_with($cleanProfilePath, 'assets/')) {
        $cleanProfilePath = 'public/' . $cleanProfilePath;
    }
    
    // Build absolute URL
    $profileImage = $protocol . '://' . $host . $basePath . $cleanProfilePath;
}

// Determine profile display - for web URLs, don't use file_exists
$hasProfileImage = !empty($profileImage) && (
    str_starts_with($profileImage, 'data:') || 
    str_starts_with($profileImage, 'http') || 
    file_exists($profileImage)
);
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
            <?php if ($hasProfileImage): ?>
                <div class="position-relative d-inline-block mb-3" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } if(typeof closeOffcanvas === 'function') { closeOffcanvas(); } return false;" title="Click to edit profile">
                    <img id="mobileAdminProfileImg" src="<?= htmlspecialchars($profileImage) ?>" alt="Profile Picture" class="rounded-circle" width="60" height="60" style="object-fit: cover; border: 2px solid #fff; transition: transform 0.2s ease;">
                    <button id="mobileAdminEditProfileBtn" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 rounded-circle" style="width: 24px; height: 24px; padding: 0; font-size: 10px; pointer-events: none;" title="Edit Profile" type="button">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            <?php else: ?>
                <span class="bi bi-person-circle" style="font-size:3rem; color:#fff; cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } if(typeof closeOffcanvas === 'function') { closeOffcanvas(); } return false;" title="Click to edit profile"></span>
            <?php endif; ?>
            <div class="fw-semibold text-white" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } if(typeof closeOffcanvas === 'function') { closeOffcanvas(); } return false;" title="Click to edit profile"><?= htmlspecialchars($username) ?></div>
            <?php if (!empty($roleName)): ?>
                <small class="text-light opacity-75"><?= htmlspecialchars($roleName) ?></small>
            <?php endif; ?>
        </div>
        
        <nav class="nav flex-column">
            <?php foreach ($menuSections as $section): ?>
                <?php if (!empty($section['items'])): ?>
                    <div class="nav-section mb-3">
                        <?php if (!empty($section['label'])): ?>
                            <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block"><?= htmlspecialchars($section['label']) ?></small>
                        <?php endif; ?>
                        <?php foreach ($section['items'] as $item): ?>
                            <?php
                            $href = $item['route'] ?? $item['href'] ?? '#';
                            $onclick = $item['onclick'] ?? '';
                            $icon = $item['icon'] ?? '';
                            $label = $item['label'] ?? '';
                            $active = $item['active'] ?? false;
                            $dataAttrs = '';
                            if (isset($item['data'])) {
                                foreach ($item['data'] as $key => $value) {
                                    $dataAttrs .= ' data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                                }
                            }
                            ?>
                            <a class="nav-link <?= $active ? 'active' : '' ?>" 
                               href="<?= htmlspecialchars($href) ?>" 
                               <?= !empty($onclick) ? 'onclick="' . htmlspecialchars($onclick) . '"' : '' ?>
                               <?= $dataAttrs ?>>
                                <?php if (!empty($icon)): ?>
                                    <span class="<?= htmlspecialchars($icon) ?> me-2"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Divider -->
            <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
            
            <!-- Logout Section -->
            <div class="nav-section">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <span class="bi bi-box-arrow-right me-2"></span> Logout
                </a>
            </div>
        </nav>
    </div>
</div>

<!-- Desktop Sidebar -->
<div class="sidebar d-none d-lg-flex flex-column">
    <div class="text-center mb-4">
        <?php if ($hasProfileImage): ?>
            <div class="position-relative d-inline-block mb-3" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } return false;" title="Click to edit profile">
                <img id="adminProfileImg" src="<?= htmlspecialchars($profileImage) ?>" alt="Profile Picture" class="rounded-circle" width="80" height="80" style="object-fit: cover; border: 3px solid #fff; transition: transform 0.2s ease;">
                <button id="adminEditProfileBtn" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 rounded-circle" style="width: 28px; height: 28px; padding: 0; font-size: 12px; pointer-events: none;" title="Edit Profile" type="button">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
        <?php else: ?>
            <span class="bi bi-person-circle" style="font-size:3rem; color:#fff; cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } return false;" title="Click to edit profile"></span>
        <?php endif; ?>
        <div class="fw-semibold mt-2" style="cursor: pointer;" onclick="if(typeof showTab === 'function') { showTab('profile', this); } return false;" title="Click to edit profile"><?= htmlspecialchars($username) ?></div>
        <?php if (!empty($roleName)): ?>
            <small class="text-light opacity-75"><?= htmlspecialchars($roleName) ?></small>
        <?php endif; ?>
        <?php if (!empty($dept_name)): ?>
            <small class="text-light opacity-75 d-block"><?= htmlspecialchars($dept_name) ?></small>
        <?php endif; ?>
    </div>
    <nav class="nav flex-column px-3">
        <?php foreach ($menuSections as $section): ?>
            <?php if (!empty($section['items'])): ?>
                <div class="nav-section mb-3">
                    <?php if (!empty($section['label'])): ?>
                        <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block"><?= htmlspecialchars($section['label']) ?></small>
                    <?php endif; ?>
                    <?php foreach ($section['items'] as $item): ?>
                        <?php
                        $href = $item['route'] ?? $item['href'] ?? '#';
                        $onclick = $item['onclick'] ?? '';
                        $icon = $item['icon'] ?? '';
                        $label = $item['label'] ?? '';
                        $active = $item['active'] ?? false;
                        $dataAttrs = '';
                        if (isset($item['data'])) {
                            foreach ($item['data'] as $key => $value) {
                                $dataAttrs .= ' data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                            }
                        }
                        ?>
                        <a class="nav-link <?= $active ? 'active' : '' ?>" 
                           href="<?= htmlspecialchars($href) ?>" 
                           <?= !empty($onclick) ? 'onclick="' . htmlspecialchars($onclick) . '"' : '' ?>
                           <?= $dataAttrs ?>>
                            <?php if (!empty($icon)): ?>
                                <span class="<?= htmlspecialchars($icon) ?> me-2"></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Divider -->
        <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.2);">
        
        <!-- Account Section -->
        <div class="nav-section">
            <?php if (in_array($roleKey, ['admin', 'moderator', 'user'])): ?>
                <small class="text-light opacity-50 text-uppercase fw-bold px-3 mb-2 d-block">Account</small>
            <?php endif; ?>
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <span class="bi bi-box-arrow-right me-2"></span> Logout
            </a>
        </div>
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <?php
        // Determine logout route based on role
        $logoutRoute = '../auth/logout.php';
        if ($userRole === 'User' || $userRole === 'Instructor') {
            // For instructors, use the instructor logout handler which properly redirects
            $logoutRoute = '../../instructor/auth/logout.php';
        }
        ?>
        <a href="<?= $logoutRoute ?>" class="btn btn-maroon">Logout</a>
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
        width: 420px !important;
        max-width: 420px !important;
        overflow: hidden !important;
        position: relative !important;
        z-index: 1107 !important;
    }
    
    @media (max-width: 480px) {
        #logoutModal .modal-content {
            width: calc(100% - 2rem) !important;
            max-width: calc(100% - 2rem) !important;
            margin: 0 1rem !important;
        }
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
        text-align: center !important;
    }
    
    #logoutModal .modal-footer {
        padding: 1.25rem 1.5rem !important;
        border-top: 1px solid #dee2e6 !important;
        background: #ffffff !important;
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
        .sidebar .nav-link[data-bs-target="#logoutModal"] {
            min-height: 44px !important;
            padding: 0.75rem 1rem !important;
            display: flex !important;
            align-items: center !important;
        }
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
    
    /* Match Admin Sidebar Transitions - Instant hover, smooth active state */
    .sidebar .nav-link {
        position: relative;
        overflow: hidden;
        cursor: pointer;
        will-change: background-color, transform;
        /* No transition on default state for instant hover */
        transition: none;
    }
    
    .sidebar .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 3px;
        height: 100%;
        background: rgba(255, 255, 255, 0.5);
        transform: scaleY(0);
        transition: none;
    }
    
    /* Instant hover effect - ZERO delay, immediate response */
    .sidebar .nav-link:hover:not(.active) {
        background: rgba(255, 255, 255, 0.1) !important;
        color: var(--evsu-white) !important;
        transform: translateX(2px) !important;
        /* NO transition - instant hover response */
        transition: none !important;
    }
    
    /* Active state - PERSISTS, smooth transition when becoming active */
    .sidebar .nav-link.active {
        background: rgba(255, 255, 255, 0.25) !important;
        color: var(--evsu-white) !important;
        transform: translateX(4px) !important;
        border-left: 3px solid #fff !important;
        font-weight: 600 !important;
        transition: background-color 0.1s ease, transform 0.1s ease, border-left 0.1s ease !important;
    }
    
    /* Active link hover - maintains active styling */
    .sidebar .nav-link.active:hover {
        background: rgba(255, 255, 255, 0.25) !important;
        transform: translateX(4px) !important;
        border-left: 3px solid #fff !important;
        /* NO transition on hover - instant */
        transition: none !important;
    }
    
    /* Before pseudo-element for active state - instant */
    .sidebar .nav-link.active::before {
        transform: scaleY(1);
        opacity: 1;
    }
    
    /* Ensure active state overrides any other states */
    .sidebar .nav-link.active,
    .sidebar .nav-link.active * {
        color: var(--evsu-white) !important;
    }
    
    /* Mobile sidebar - match admin style */
    .sidebar-offcanvas .nav-link {
        position: relative;
        overflow: hidden;
        transition: none; /* No transition for instant hover */
    }
    
    .sidebar-offcanvas .nav-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: none; /* Remove animation delay */
    }
    
    .sidebar-offcanvas .nav-link:hover:not(.active)::before {
        left: 100%;
        transition: none; /* Instant */
    }
    
    /* Mobile sidebar - Instant hover effect */
    .sidebar-offcanvas .nav-link:hover:not(.active) {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
        transform: translateX(3px) !important;
        box-shadow: var(--shadow-sm);
        /* NO transition - instant hover */
        transition: none !important;
    }
    
    /* Mobile sidebar - Active state PERSISTS */
    .sidebar-offcanvas .nav-link.active {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #fff !important;
        transform: translateX(5px) !important;
        box-shadow: var(--shadow-sm);
        border-left: 3px solid #fff !important;
        font-weight: 600 !important;
        transition: background-color 0.08s ease, transform 0.08s ease, border-left 0.08s ease !important;
    }
    
    /* Active link hover - maintains active styling */
    .sidebar-offcanvas .nav-link.active:hover {
        background: rgba(255, 255, 255, 0.25) !important;
        transform: translateX(5px) !important;
        border-left: 3px solid #fff !important;
        transition: none !important;
    }
    
    /* Profile section clickable styling */
    .sidebar .text-center.mb-4 > div[onclick],
    .sidebar-offcanvas .text-center.mb-4 > div[onclick] {
        transition: all 0.2s ease;
    }
    
    .sidebar .text-center.mb-4 > div[onclick]:hover,
    .sidebar-offcanvas .text-center.mb-4 > div[onclick]:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    
    .sidebar .text-center.mb-4 > div[onclick]:hover img,
    .sidebar-offcanvas .text-center.mb-4 > div[onclick]:hover img {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }
    
    .sidebar .text-center.mb-4 .fw-semibold[onclick],
    .sidebar-offcanvas .text-center.mb-4 .fw-semibold[onclick] {
        transition: all 0.2s ease;
    }
    
    .sidebar .text-center.mb-4 .fw-semibold[onclick]:hover,
    .sidebar-offcanvas .text-center.mb-4 .fw-semibold[onclick]:hover {
        color: #fff !important;
        text-decoration: underline;
    }
    
    .sidebar .text-center.mb-4 .bi-person-circle[onclick],
    .sidebar-offcanvas .text-center.mb-4 .bi-person-circle[onclick] {
        transition: all 0.2s ease;
    }
    
    .sidebar .text-center.mb-4 .bi-person-circle[onclick]:hover,
    .sidebar-offcanvas .text-center.mb-4 .bi-person-circle[onclick]:hover {
        opacity: 0.8;
        transform: scale(1.05);
    }
</style>

<style>
    /* Ensure logout modal works properly in mobile mode */
    #logoutModal {
        z-index: 1105 !important;
    }
    
    #logoutModal.modal {
        z-index: 1105 !important;
    }
    
    #logoutModal.modal.show {
        z-index: 1105 !important;
    }
    
    #logoutModal .modal-dialog {
        z-index: 1106 !important;
        position: relative !important;
        pointer-events: auto !important;
    }
    
    #logoutModal .modal-content {
        position: relative !important;
        z-index: 1107 !important;
        pointer-events: auto !important;
    }
    
    /* Mobile-specific adjustments */
    @media (max-width: 991.98px) {
        #logoutModal {
            z-index: 1105 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        
        #logoutModal .modal-dialog {
            margin: 1rem !important;
            max-width: calc(100% - 2rem) !important;
            width: calc(100% - 2rem) !important;
            min-height: auto !important;
        }
        
        #logoutModal .modal-content {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
        }
        
        /* Ensure modal is properly centered and visible on mobile */
        #logoutModal.modal.show {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 1rem !important;
        }
        
        /* Ensure backdrop doesn't interfere */
        #logoutModal.show ~ .modal-backdrop,
        body.modal-open .modal-backdrop {
            z-index: 1100 !important;
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
// Ensure logout modal works in mobile mode by handling offcanvas closure
document.addEventListener('DOMContentLoaded', function() {
    // Handle logout link clicks - close offcanvas before showing modal
    document.querySelectorAll('[data-bs-target="#logoutModal"]').forEach(link => {
        link.addEventListener('click', function(e) {
            // Close mobile offcanvas if open
            const offcanvasEl = document.getElementById('sidebarOffcanvas');
            if (offcanvasEl && typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                if (bsOffcanvas && bsOffcanvas._isShown) {
                    // Close offcanvas first, then show modal after a short delay
                    bsOffcanvas.hide();
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Wait for offcanvas to close, then show modal
                    const showModalAfterOffcanvas = function() {
                        offcanvasEl.removeEventListener('hidden.bs.offcanvas', showModalAfterOffcanvas);
                        
                        // Small delay to ensure offcanvas is fully closed
                        setTimeout(() => {
                            // Show logout modal using ModalManager if available
                            const logoutModalEl = document.getElementById('logoutModal');
                            if (logoutModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                // Remove any existing modal instance to avoid conflicts
                                const existingInstance = bootstrap.Modal.getInstance(logoutModalEl);
                                if (existingInstance) {
                                    try {
                                        existingInstance.dispose();
                                    } catch (e) {
                                        console.warn('Error disposing existing modal instance:', e);
                                    }
                                }
                                
                                // Create new modal instance
                                const logoutModal = new bootstrap.Modal(logoutModalEl, {
                                    backdrop: true,
                                    keyboard: true,
                                    focus: true
                                });
                                
                                // Ensure proper z-index before showing
                                logoutModalEl.style.zIndex = '1105';
                                if (logoutModalEl.querySelector('.modal-dialog')) {
                                    logoutModalEl.querySelector('.modal-dialog').style.zIndex = '1106';
                                }
                                if (logoutModalEl.querySelector('.modal-content')) {
                                    logoutModalEl.querySelector('.modal-content').style.zIndex = '1107';
                                }
                                
                                // Show modal
                                logoutModal.show();
                                
                                // Ensure buttons are clickable
                                const buttons = logoutModalEl.querySelectorAll('.btn, a.btn');
                                buttons.forEach(btn => {
                                    btn.style.pointerEvents = 'auto';
                                    btn.style.cursor = 'pointer';
                                    btn.style.zIndex = '1108';
                                });
                            }
                        }, 150); // Small delay to ensure offcanvas is fully closed
                    };
                    
                    offcanvasEl.addEventListener('hidden.bs.offcanvas', showModalAfterOffcanvas, { once: true });
                    
                    return false;
                }
            }
            // If offcanvas is not open, let Bootstrap handle the modal normally
            // But still ensure proper z-index and button clickability
            const logoutModalEl = document.getElementById('logoutModal');
            if (logoutModalEl) {
                // Ensure proper z-index
                logoutModalEl.style.zIndex = '1105';
                if (logoutModalEl.querySelector('.modal-dialog')) {
                    logoutModalEl.querySelector('.modal-dialog').style.zIndex = '1106';
                }
                if (logoutModalEl.querySelector('.modal-content')) {
                    logoutModalEl.querySelector('.modal-content').style.zIndex = '1107';
                }
            }
        });
    });
    
    // Ensure logout modal buttons are always clickable when modal is shown
    const logoutModalEl = document.getElementById('logoutModal');
    if (logoutModalEl) {
        logoutModalEl.addEventListener('shown.bs.modal', function() {
            // Ensure all buttons are clickable
            const buttons = logoutModalEl.querySelectorAll('.btn, a.btn, button');
            buttons.forEach(btn => {
                btn.style.pointerEvents = 'auto';
                btn.style.cursor = 'pointer';
                btn.style.zIndex = '1108';
                btn.style.position = 'relative';
            });
            
            // Ensure modal content is clickable
            const modalContent = logoutModalEl.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.pointerEvents = 'auto';
                modalContent.style.zIndex = '1107';
            }
            
            // Ensure backdrop doesn't block clicks
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = '1100';
            }
        });
    }
});
</script>

