<?php
/**
 * Shared Dashboard Header Component
 * Provides consistent header across all dashboards
 */

// Include permission system and database connection
require_once __DIR__ . '/dashboard_permissions.php';
require_once __DIR__ . '/../../config/database.php';

// Auto-sync SY & semester based on current date (runs on every page load)
require_once __DIR__ . '/../../shared/utils/academic_period_sync.php';
try {
    syncActiveAcademicPeriod($conn, isset($_SESSION['acc_id']) ? (int)$_SESSION['acc_id'] : null, true);
} catch (Exception $e) {
    // Silently fail - don't break page load
    error_log('Academic period sync error: ' . $e->getMessage());
}

// Get user information
$userInfo = getUserInfo();
$dashboardTitle = getDashboardTitle();
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$canManageSySemester = ($userRole === 'Admin' || $userRole === 'Admin support');

if (!$userInfo) {
    die('User information not found');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EVSU-OCC Scheduling System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    
    <style>
        /* Force override all styles for proper maroon background and white text */
        body {
            font-family: 'Poppins', Arial, sans-serif !important;
            background: #f8f9fa !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Navbar - Force maroon background */
        .navbar, .navbar-dark, .navbar-expand-lg {
            background: #800000 !important;
            background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
            min-height: 70px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
        }
        
        /* Mobile navbar - more compact */
        @media (max-width: 991.98px) {
            .navbar, .navbar-dark, .navbar-expand-lg {
                min-height: 50px !important; /* Reduced from 60px */
                padding: 0.15rem 0 !important; /* Reduced from 0.25rem */
            }
        }
        
        .navbar-brand, .navbar-brand * {
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 1.1rem !important;
        }
        
        .navbar-nav .nav-link, .navbar-nav .nav-link * {
            color: #ffffff !important;
            font-weight: 500 !important;
        }
        
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link:hover * {
            color: #f5f5dc !important;
        }
        
        /* Sidebar - Force maroon background and white text */
        .sidebar {
            background: #800000 !important;
            background: linear-gradient(180deg, #800000 0%, #660000 100%) !important;
            color: #ffffff !important;
            min-height: 100vh !important;
            position: fixed !important;
            top: 70px !important;
            left: 0 !important;
            width: 280px !important;
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            z-index: 1090 !important;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1) !important;
            border-right: 1px solid rgba(255, 255, 255, 0.1) !important;
            overflow-y: auto !important;
            max-height: calc(100vh - 70px) !important;
        }
        
        /* Mobile sidebar - adjust top position for smaller navbar */
        @media (max-width: 991.98px) {
            .sidebar {
                top: 50px !important; /* Reduced from 60px */
                max-height: calc(100vh - 50px) !important; /* Reduced from 60px */
            }
        }
        
        .sidebar *, .sidebar h1, .sidebar h2, .sidebar h3, .sidebar h4, .sidebar h5, .sidebar h6, 
        .sidebar p, .sidebar span, .sidebar div, .sidebar a, .sidebar button, .sidebar small {
            color: #ffffff !important;
        }
        
        .sidebar .nav-link, .sidebar .nav-link * {
            color: #ffffff !important;
            font-weight: 500 !important;
            padding: 0.4rem 0.8rem !important;
            margin: 0.05rem 0.4rem !important;
            border-radius: 6px !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            font-size: 0.9rem !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        
        .sidebar .nav-link .bi {
            margin-right: 0.5rem !important;
            font-size: 1rem !important;
            width: 16px !important;
            text-align: center !important;
            flex-shrink: 0 !important;
        }
        
        /* Reduce spacing for section headers and dividers */
        .sidebar .nav-section {
            margin-bottom: 0.5rem !important;
        }
        
        .sidebar .nav-section small {
            margin-bottom: 0.25rem !important;
            padding: 0.25rem 0.8rem !important;
        }
        
        .sidebar hr {
            margin: 0.5rem 0 !important;
        }
        
        /* Base nav-link - no transition for instant hover */
        .sidebar .nav-link {
            transition: none !important;
        }
        
        /* Instant hover effect - ZERO delay */
        .sidebar .nav-link:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            transform: translateX(2px) !important;
            /* NO transition - instant hover */
            transition: none !important;
        }
        
        /* Active state - PERSISTS when clicked */
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            transform: translateX(4px) !important;
            border-left: 3px solid #fff !important;
            font-weight: 600 !important;
            transition: background-color 0.08s ease, transform 0.08s ease, border-left 0.08s ease !important;
        }
        
        /* Active link hover - maintains active styling */
        .sidebar .nav-link.active:hover {
            background: rgba(255, 255, 255, 0.25) !important;
            transform: translateX(4px) !important;
            border-left: 3px solid #fff !important;
            transition: none !important;
        }
        
        .sidebar .nav-link:hover *, 
        .sidebar .nav-link.active * {
            color: #ffffff !important;
        }
        
        /* Sidebar offcanvas for mobile */
        .sidebar-offcanvas, 
        .offcanvas-start,
        #sidebarOffcanvas {
            background: #800000 !important;
            background: linear-gradient(180deg, #800000 0%, #660000 100%) !important;
            color: #ffffff !important;
            width: 280px !important;
            max-width: 85vw !important;
            max-height: 100vh !important;
            overflow-y: auto !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            z-index: 1045 !important;
            visibility: hidden !important;
            transform: translateX(-100%) !important;
            transition: transform 0.3s ease-in-out, visibility 0.3s ease-in-out !important;
        }
        
        /* Offcanvas when shown */
        .sidebar-offcanvas.show,
        .offcanvas-start.show,
        #sidebarOffcanvas.show,
        .sidebar-offcanvas.showing,
        .offcanvas-start.showing,
        #sidebarOffcanvas.showing {
            visibility: visible !important;
            transform: translateX(0) !important;
        }
        
        /* Ensure offcanvas is visible when Bootstrap adds 'show' class */
        .offcanvas.show {
            visibility: visible !important;
            transform: translateX(0) !important;
        }
        
        /* Offcanvas backdrop */
        .offcanvas-backdrop {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 1040 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        
        /* Ensure backdrop is hidden when offcanvas is not showing */
        body:not(.offcanvas-open) .offcanvas-backdrop {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        /* Clean up any orphaned backdrops */
        .offcanvas-backdrop:not(.show) {
            display: none !important;
        }
        
        .sidebar-offcanvas *, .sidebar-offcanvas h1, .sidebar-offcanvas h2, .sidebar-offcanvas h3, 
        .sidebar-offcanvas h4, .sidebar-offcanvas h5, .sidebar-offcanvas h6, .sidebar-offcanvas p, 
        .sidebar-offcanvas span, .sidebar-offcanvas div, .sidebar-offcanvas a, .sidebar-offcanvas button {
            color: #ffffff !important;
        }
        
        .sidebar-offcanvas .nav-link, .sidebar-offcanvas .nav-link * {
            color: #ffffff !important;
            font-weight: 500 !important;
            padding: 0.4rem 0.8rem !important;
            margin: 0.05rem 0.4rem !important;
            border-radius: 6px !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
        }
        
        .sidebar-offcanvas .nav-link .bi {
            margin-right: 0.5rem !important;
            font-size: 1rem !important;
            width: 16px !important;
            text-align: center !important;
            flex-shrink: 0 !important;
        }
        
        .sidebar-offcanvas .nav-link:hover, .sidebar-offcanvas .nav-link.active {
            background: rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
        }
        
        .sidebar-offcanvas .nav-link:hover *, .sidebar-offcanvas .nav-link.active * {
            color: #ffffff !important;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 280px !important;
            padding-top: 90px !important;
            padding-bottom: 80px !important;
            min-height: calc(100vh - 170px) !important;
            background: #f8f9fa !important;
        }
        
        /* Dashboard cards */
        .dashboard-card {
            background: #ffffff !important;
            border-radius: 16px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
            margin-bottom: 2rem !important;
            padding: 2rem !important;
            min-height: 180px !important;
            border: 1px solid #dee2e6 !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        .dashboard-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 4px !important;
            background: linear-gradient(90deg, #800000, #f5f5dc) !important;
        }
        
        .dashboard-card h5 {
            color: #800000 !important;
            font-weight: 700 !important;
        }
        
        /* Buttons */
        .btn-maroon {
            background: #800000 !important;
            background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        
        .btn-maroon:hover, .btn-maroon:focus {
            background: #660000 !important;
            background: linear-gradient(135deg, #660000 0%, #800000 100%) !important;
            color: #ffffff !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
        }
        
        /* Footer */
        .footer {
            background: #800000 !important;
            color: #ffffff !important;
            text-align: center !important;
            width: 100% !important;
            position: fixed !important;
            left: 0 !important;
            bottom: 0 !important;
            font-size: 0.9rem !important;
            padding: 0.5rem 0 !important;
            z-index: 1080 !important;
        }
        
        /* Mobile footer - more compact */
        @media (max-width: 991.98px) {
            .footer {
                font-size: 0.75rem !important;
                padding: 0.4rem 0.5rem !important;
                line-height: 1.3 !important;
            }
            
            .footer .container {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            .footer p {
                margin-bottom: 0 !important;
                font-size: 0.75rem !important;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 575.98px) {
            .footer {
                font-size: 0.7rem !important;
                padding: 0.35rem 0.5rem !important;
            }
            
            .footer p {
                font-size: 0.7rem !important;
            }
        }
        
        /* Utility classes */
        .bg-maroon {
            background-color: #800000 !important;
        }
        
        .text-maroon {
            color: #800000 !important;
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .sidebar {
                width: 260px !important;
            }
            .main-content {
                margin-left: 260px !important;
            }
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding-top: 65px !important; /* Reduced for more compact mobile navbar (was 75px) */
                padding-bottom: 50px !important; /* Reduced for more compact footer */
                padding-left: 0.5rem !important; /* Reduced horizontal padding */
                padding-right: 0.5rem !important; /* Reduced horizontal padding */
                min-height: calc(100vh - 180px) !important;
            }
            
            /* Reduce container-fluid padding on mobile */
            .main-content .container-fluid {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            /* Reduce Bootstrap column padding on mobile */
            .main-content .row {
                margin-left: -0.375rem !important;
                margin-right: -0.375rem !important;
            }
            
            .main-content .row > [class*="col-"] {
                padding-left: 0.375rem !important;
                padding-right: 0.375rem !important;
            }
            
            .dashboard-card {
                padding: 0.875rem !important; /* Reduced from 1rem */
                margin-bottom: 1.5rem !important;
                min-height: unset !important;
            }
        }
        
        @media (max-width: 767.98px) {
            /* Further reduce padding on smaller mobile devices */
            .main-content {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            .main-content .container-fluid {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            .main-content .row {
                margin-left: -0.25rem !important;
                margin-right: -0.25rem !important;
            }
            
            .main-content .row > [class*="col-"] {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }
            
            .dashboard-card {
                padding: 0.75rem !important; /* Further reduced for small screens */
            }
        }
        
        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(0);
            }
        }
        
        /* Card hover effects */
        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        
        /* SY & Semester Navbar Styles */
        .navbar-sy-semester {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .active-sy-display {
            color: #ffffff !important;
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
            line-height: 1.5;
        }
        
        /* Ensure navbar brand logo aligns with School Year text */
        .navbar-brand {
            display: flex !important;
            align-items: center !important;
            height: 100% !important;
        }
        
        .navbar-brand img {
            vertical-align: middle !important;
            object-fit: contain !important;
        }
        
        /* Desktop: Ensure logo and School Year are on same baseline */
        @media (min-width: 992px) {
            .navbar-brand {
                align-items: center !important;
            }
            
            .navbar-sy-semester {
                align-items: center !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
            }
            
            .navbar-brand img {
                vertical-align: middle !important;
            }
        }
        
        .btn-sy-semester {
            background: rgba(255, 255, 255, 0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            color: #ffffff !important;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-sy-semester:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.3) !important;
            border-color: rgba(255, 255, 255, 0.5) !important;
            color: #ffffff !important;
        }
        
        .btn-sy-semester:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        @media (max-width: 991.98px) {
            /* Mobile Menu Toggle Button - Ensure it's visible and clickable */
            .navbar-toggler {
                display: block !important;
                position: relative !important;
                z-index: 1055 !important;
                pointer-events: auto !important;
                cursor: pointer !important;
                border: 1px solid rgba(255, 255, 255, 0.3) !important;
                padding: 0.2rem 0.4rem !important; /* Reduced from 0.35rem 0.5rem */
                margin-right: 0.375rem !important; /* Reduced from 0.5rem */
                flex-shrink: 0 !important;
                order: 1 !important;
            }
            
            .navbar-toggler:focus {
                box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25) !important;
            }
            
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
            }
            
            /* Adjust navbar to accommodate mobile SY section - Two row layout */
            .navbar {
                min-height: 60px !important;
                height: auto !important;
                padding: 0.25rem 0 !important;
            }
            
            /* Ensure proper spacing in navbar container - Two rows using flexbox */
            .navbar .container-fluid {
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 0.4rem 0.5rem !important;
                padding: 0.4rem 0.5rem !important;
            }
            
            /* First row wrapper: Group logo, hamburger, and notifications */
            .navbar .container-fluid > .navbar-brand,
            .navbar .container-fluid > .navbar-toggler,
            .navbar .container-fluid > .d-flex.align-items-center {
                flex-shrink: 0 !important;
            }
            
            /* First row: Logo */
            .navbar .container-fluid > .navbar-brand {
                order: 1 !important;
                margin-right: 0.375rem !important; /* Reduced from 0.5rem */
            }
            
            /* First row: Hamburger */
            .navbar .container-fluid > .navbar-toggler {
                order: 2 !important;
            }
            
            /* First row: Notifications */
            .navbar .container-fluid > .d-flex.align-items-center {
                order: 3 !important;
                margin-left: auto !important;
            }
            
            /* Force first row to wrap before SY section */
            .navbar .container-fluid > .navbar-brand,
            .navbar .container-fluid > .navbar-toggler,
            .navbar .container-fluid > .d-flex.align-items-center {
                flex-basis: auto !important;
            }
            
            /* Mobile: School Year section - Try to fit on same row, wrap if needed */
            .navbar .container-fluid > .navbar-sy-semester.d-lg-none {
                order: 2 !important;
                position: relative !important; /* Override absolute positioning */
                left: auto !important;
                transform: none !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
                max-width: calc(100% - 200px) !important; /* Leave space for logo and buttons */
                margin-left: 0.375rem !important; /* Reduced from 0.5rem */
                margin-right: 0.375rem !important; /* Reduced from 0.5rem */
                margin-top: 0 !important;
            }
            
            /* If screen is too small, wrap School Year to new row and center it */
            @media (max-width: 576px) {
                .navbar .container-fluid > .navbar-sy-semester.d-lg-none {
                    order: 4 !important;
                    flex-basis: 100% !important;
                    max-width: 100% !important;
                    margin-left: 0 !important;
                    margin-right: 0 !important;
                    margin-top: 0.25rem !important;
                    justify-content: center !important;
                    text-align: center !important;
                }
            }
            
            /* Make navbar brand smaller on mobile to save space */
            .navbar-brand {
                display: flex !important;
                align-items: center !important;
                height: auto !important;
                flex-shrink: 0 !important;
            }
            
            .navbar-brand img {
                width: 26px !important; /* Reduced from 28px */
                height: 26px !important; /* Reduced from 28px */
                margin-right: 0.375rem !important; /* Reduced from 0.5rem */
                vertical-align: middle !important;
                object-fit: contain !important;
            }
            
            .navbar-brand .fw-bold {
                font-size: 0.75rem !important;
                display: none !important; /* Hide text on very small screens */
            }
            
            /* Mobile: Ensure logo and School Year are horizontally aligned */
            .navbar .container-fluid {
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                min-height: 50px !important; /* Reduced from 60px */
            }
            
            /* First row: Logo, menu button, and notification */
            .navbar-brand,
            .navbar-toggler,
            .navbar .d-flex.align-items-center {
                order: 1 !important;
                flex-shrink: 0 !important;
            }
            
            /* Mobile: Make logo and School Year align horizontally on same row if space allows */
            .navbar-sy-semester.d-lg-none {
                order: 2 !important;
                display: flex !important;
                align-items: center !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
                margin-left: 0.375rem !important; /* Reduced from 0.5rem */
                margin-right: 0.375rem !important; /* Reduced from 0.5rem */
                height: 100% !important;
            }
            
            /* Ensure logo and School Year text are vertically centered together */
            .navbar-brand,
            .navbar-sy-semester.d-lg-none {
                align-items: center !important;
                display: flex !important;
            }
            
            /* Make sure logo and School Year text have same vertical alignment */
            .navbar-brand img,
            .navbar-sy-semester.d-lg-none .active-sy-display {
                vertical-align: middle !important;
            }
            
            /* Make navbar toggler smaller */
            .navbar-toggler {
                padding: 0.2rem 0.4rem !important; /* Reduced from 0.25rem 0.5rem */
                font-size: 0.85rem !important; /* Reduced from 0.9rem */
                margin-right: 0.375rem !important; /* Reduced spacing */
            }
            
            /* Make notification bell smaller */
            .navbar .d-flex.align-items-center .btn {
                padding: 0.2rem 0.4rem !important; /* Reduced from 0.25rem 0.5rem */
                font-size: 0.85rem !important; /* Reduced from 0.9rem */
            }
            
            .navbar .d-flex.align-items-center .btn i {
                font-size: 0.9rem !important;
            }
            
            /* Make SY & Semester section more compact on mobile */
            .navbar-sy-semester.d-lg-none {
                font-size: 0.8rem !important;
            }
            
            .navbar-sy-semester.d-lg-none .active-sy-display {
                font-size: 0.75rem !important; /* Reduced from 0.8rem */
                padding: 0.25rem 0.4rem !important; /* Reduced from 0.3rem 0.5rem */
                line-height: 1.3 !important; /* Reduced from 1.4 */
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
            }
            
            .navbar-sy-semester.d-lg-none .btn-sy-semester {
                padding: 0.2rem 0.4rem !important; /* Reduced from 0.25rem 0.5rem */
                font-size: 0.7rem !important; /* Reduced from 0.75rem */
                flex-shrink: 0 !important;
            }
            
            .navbar-sy-semester.d-lg-none .btn-sy-semester i {
                font-size: 0.75rem !important;
                margin-right: 0.25rem !important;
            }
            
            /* Ensure School Year section content is centered on mobile */
            @media (max-width: 991.98px) {
                .navbar-sy-semester.d-lg-none {
                    justify-content: center !important;
                }
                
                .navbar-sy-semester.d-lg-none .active-sy-display {
                    text-align: center !important;
                }
            }
            
            /* Mobile: Style SY & Semester section - Center on mobile */
            .navbar-sy-semester.d-lg-none {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 0.375rem !important; /* Reduced from 0.5rem */
                padding: 0.15rem 0 !important; /* Reduced from 0.25rem */
                position: relative !important; /* Override absolute positioning */
                left: auto !important;
                transform: none !important;
                width: 100% !important;
                text-align: center !important;
            }
            
            /* When School Year wraps to new row, ensure it's centered */
            @media (max-width: 991.98px) {
                .navbar .container-fluid > .navbar-sy-semester.d-lg-none {
                    justify-content: center !important;
                    text-align: center !important;
                }
            }
            
            .navbar-sy-semester .active-sy-display {
                font-size: 0.8rem !important;
                font-weight: 600 !important;
                color: #ffffff !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
                max-width: none !important;
                margin-right: 0.5rem !important;
            }
            
            .navbar-sy-semester .btn-sy-semester {
                font-size: 0.7rem !important;
                padding: 0.35rem 0.6rem !important;
                white-space: nowrap !important;
                min-height: 32px !important;
                flex-shrink: 0 !important;
                line-height: 1.2 !important;
            }
            
            /* Notification section - First row */
            .navbar .d-flex.align-items-center {
                flex-shrink: 0 !important;
                gap: 0.25rem !important;
                order: 3 !important;
                margin-left: auto !important;
            }
            
            /* Notification button */
            #notificationBtn {
                padding: 0.35rem !important;
                font-size: 1.1rem !important;
                min-width: 36px !important;
                min-height: 36px !important;
            }
            
            /* Hide dashboard title on mobile */
            .navbar-text {
                display: none !important;
            }
            
            /* Very small screens - Stack SY section vertically if needed */
            @media (max-width: 400px) {
                .navbar-brand .fw-bold {
                    display: none !important;
                }
                
                .navbar-sy-semester {
                    flex-wrap: wrap !important;
                    gap: 0.4rem !important;
                }
                
                .navbar-sy-semester .active-sy-display {
                    font-size: 0.75rem !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    margin-right: 0 !important;
                }
                
                .navbar-sy-semester .btn-sy-semester {
                    font-size: 0.65rem !important;
                    padding: 0.3rem 0.5rem !important;
                    width: 100% !important;
                    justify-content: center !important;
                }
            }
        }
        
        /* Modal Backdrop - Covers whole system (navbar, sidebar, content) */
        .modal-backdrop {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            min-width: 100% !important;
            min-height: 100% !important;
            background-color: rgba(0, 0, 0, 0.45) !important;
            z-index: 1100 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .modal-backdrop.show {
            opacity: 1 !important;
            z-index: 1100 !important;
        }
        
        .modal-backdrop.fade {
            z-index: 1100 !important;
        }
        
        body.modal-open .modal-backdrop {
            z-index: 1100 !important;
        }
        
        /* Modals above backdrop */
        .modal {
            z-index: 1105 !important;
        }
        
        .modal.show {
            z-index: 1105 !important;
        }
        
        .modal-dialog {
            z-index: 1106 !important;
        }
        
        .modal-content {
            position: relative !important;
            z-index: 1107 !important;
        }
        
        /* Modal - Ensure it's above backdrop */
        #setSySemesterModal {
            z-index: 1105 !important;
        }
        
        #setSySemesterModal.modal {
            z-index: 1105 !important;
        }
        
        /* Logout modal above backdrop */
        #logoutModal {
            z-index: 1105 !important;
        }
        
        #logoutModal.modal {
            z-index: 1105 !important;
        }
        
        #logoutModal .modal-dialog {
            z-index: 1106 !important;
        }
        
        /* Modal Content - Improved contrast and readability */
        #setSySemesterModal .modal-content {
            border: none !important;
            border-radius: 16px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
            overflow: hidden !important;
        }
        
        #setSySemesterModal .modal-header {
            background: linear-gradient(135deg, #8B0000 0%, #6B0000 100%) !important; /* EVSU Maroon */
            background-color: #8B0000 !important; /* EVSU Maroon */
            color: #ffffff !important;
            padding: 0.75rem 1rem !important;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
        }
        
        #setSySemesterModal .modal-header.bg-primary,
        #setSySemesterModal .modal-header.bg-maroon {
            background: linear-gradient(135deg, #8B0000 0%, #6B0000 100%) !important; /* EVSU Maroon */
            background-color: #8B0000 !important; /* EVSU Maroon */
        }
        
        #setSySemesterModal .modal-body {
            padding: 1rem !important;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
        #setSySemesterModal .modal-footer {
            padding: 0.75rem 1rem !important;
        }
        
        @media (max-width: 768px) {
            #setSySemesterModal .modal-header {
                padding: 0.625rem 0.875rem !important;
            }
            
            #setSySemesterModal .modal-body {
                padding: 0.875rem !important;
                max-height: calc(100vh - 150px);
            }
            
            #setSySemesterModal .modal-footer {
                padding: 0.625rem 0.875rem !important;
            }
        }
        
        @media (max-width: 576px) {
            #setSySemesterModal .modal-dialog {
                margin: 0.25rem !important;
                max-width: calc(100vw - 0.5rem) !important;
            }
            
            #setSySemesterModal .modal-header {
                padding: 0.5rem 0.75rem !important;
            }
            
            #setSySemesterModal .modal-body {
                padding: 0.75rem !important;
                max-height: calc(100vh - 120px);
            }
            
            #setSySemesterModal .modal-footer {
                padding: 0.5rem 0.75rem !important;
            }
        }
        
        #setSySemesterModal .modal-title {
            color: #ffffff !important;
            font-weight: 600 !important;
            font-size: 1.1rem !important;
        }
        
        #setSySemesterModal .btn-close-white {
            filter: brightness(0) invert(1) !important;
            opacity: 0.9 !important;
        }
        
        #setSySemesterModal .btn-close-white:hover {
            opacity: 1 !important;
        }
        
        /* Modal Body - Better spacing and contrast */
        #setSySemesterModal .modal-body {
            padding: 1.75rem 1.5rem !important;
            background: #ffffff !important;
            color: #212529 !important;
        }
        
        #setSySemesterModal .form-label {
            color: #212529 !important;
            font-weight: 600 !important;
            margin-bottom: 0.75rem !important;
            font-size: 0.95rem !important;
        }
        
        #setSySemesterModal .form-text {
            color: #6c757d !important;
            font-size: 0.875rem !important;
            margin-top: 0.5rem !important;
        }
        
        /* School Year Input - Fixed alignment and height */
        #setSySemesterModal .input-group {
            display: flex !important;
            align-items: stretch !important;
        }
        
        #setSySemesterModal .input-group .form-control {
            height: 42px !important;
            border: 1px solid #ced4da !important;
            border-radius: 8px 0 0 8px !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 1rem !important;
            color: #212529 !important;
            background-color: #ffffff !important;
        }
        
        #setSySemesterModal .input-group .form-control:focus {
            border-color: #800000 !important;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25) !important;
            outline: none !important;
        }
        
        #setSySemesterModal .input-group .form-control[readonly] {
            background-color: #f8f9fa !important;
            color: #495057 !important;
            cursor: not-allowed !important;
        }
        
        #setSySemesterModal .input-group .btn {
            height: 42px !important;
            border: 1px solid #ced4da !important;
            border-left: none !important;
            padding: 0.375rem 0.75rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background-color: #f8f9fa !important;
            color: #495057 !important;
            transition: all 0.2s ease !important;
        }
        
        #setSySemesterModal .input-group .btn:last-child {
            border-radius: 0 8px 8px 0 !important;
        }
        
        #setSySemesterModal .input-group .btn:hover {
            background-color: #e9ecef !important;
            color: #212529 !important;
        }
        
        #setSySemesterModal .input-group .btn:active {
            background-color: #dee2e6 !important;
        }
        
        /* Year separator */
        #setSySemesterModal .col-2.text-center {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 42px !important;
        }
        
        #setSySemesterModal .col-2.text-center span {
            color: #495057 !important;
            font-size: 1.25rem !important;
            font-weight: 600 !important;
        }
        
        /* Semester Dropdown */
        #setSySemesterModal #semesterValue {
            height: 42px !important;
            border: 1px solid #ced4da !important;
            border-radius: 8px !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 1rem !important;
            color: #212529 !important;
            background-color: #ffffff !important;
        }
        
        #setSySemesterModal #semesterValue:focus {
            border-color: #800000 !important;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25) !important;
            outline: none !important;
        }
        
        /* Alert/Preview Box */
        #setSySemesterModal .alert {
            border-radius: 8px !important;
            padding: 0.875rem 1rem !important;
            border: 1px solid !important;
            margin-bottom: 0 !important;
        }
        
        #setSySemesterModal .alert-info {
            background-color: #d1ecf1 !important;
            border-color: #bee5eb !important;
            color: #0c5460 !important;
        }
        
        #setSySemesterModal .alert-info strong {
            color: #0c5460 !important;
        }
        
        #setSySemesterModal .alert-info span {
            color: #0c5460 !important;
        }
        
        /* Modal Footer */
        #setSySemesterModal .modal-footer {
            padding: 1.25rem 1.5rem !important;
            background: #f8f9fa !important;
            border-top: 1px solid #dee2e6 !important;
            gap: 0.75rem !important;
        }
        
        #setSySemesterModal .modal-footer .btn {
            padding: 0.625rem 1.25rem !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
            transition: all 0.2s ease !important;
        }
        
        #setSySemesterModal .modal-footer .btn-secondary {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #ffffff !important;
        }
        
        #setSySemesterModal .modal-footer .btn-secondary:hover {
            background-color: #5a6268 !important;
            border-color: #545b62 !important;
            color: #ffffff !important;
        }
        
        #setSySemesterModal .modal-footer .btn-maroon {
            background: #800000 !important;
            background: linear-gradient(135deg, #800000 0%, #660000 100%) !important;
            border: none !important;
            color: #ffffff !important;
        }
        
        #setSySemesterModal .modal-footer .btn-maroon:hover {
            background: #660000 !important;
            background: linear-gradient(135deg, #660000 0%, #800000 100%) !important;
            color: #ffffff !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3) !important;
        }
        
        /* Alert messages in modal */
        #setSySemesterModal .alert {
            border-radius: 8px !important;
            padding: 0.875rem 1rem !important;
        }
        
        #setSySemesterModal .alert-danger {
            background-color: #f8d7da !important;
            border-color: #f5c6cb !important;
            color: #721c24 !important;
        }
        
        #setSySemesterModal .alert-success {
            background-color: #d4edda !important;
            border-color: #c3e6cb !important;
            color: #155724 !important;
        }
        
        /* Notification Icon Styles */
        #notificationBtn {
            transition: all 0.3s ease;
            overflow: visible !important;
            position: relative;
        }
        
        #notificationBtn:hover {
            transform: scale(1.1);
            opacity: 0.9;
        }
        
        /* Ensure notification button container allows badge overflow */
        .position-relative:has(#notificationBtn) {
            overflow: visible !important;
        }
        
        /* Let dropdown menus escape the navbar (avoid clipping) */
        .navbar.fixed-top > .container-fluid {
            overflow: visible !important;
        }
        
        #notificationDropdown {
            margin-top: 0.5rem;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        #notificationDropdown .dropdown-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        #notificationDropdown .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(2px);
        }
        
        #notificationDropdown .dropdown-item[style*="pointer"] {
            cursor: pointer;
        }
        
        #notificationDropdown .dropdown-item[style*="pointer"]:hover {
            background-color: #e7f3ff;
        }
        
        #notificationDropdown .dropdown-item.unread {
            background-color: #e7f3ff;
            font-weight: 500;
        }
        
        #notificationDropdown .dropdown-item.unread:hover {
            background-color: #d0e7ff;
        }
        
        #notificationDropdown .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        #notificationDropdown .notification-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        
        #notificationDropdown .notification-message {
            font-size: 0.875rem;
            color: #495057;
            margin-bottom: 0;
        }
        
        /* Notification panel improvements */
        #notificationDropdown {
            width: 420px;
            max-width: calc(100vw - 24px);
            border-radius: 14px;
            box-shadow: 0 14px 36px rgba(0,0,0,0.18);
            padding: 0 0 10px 0;
            border: 1px solid #e5e7eb;
            background: #fff;
            z-index: 1050;
            margin-top: 0.5rem !important;
        }
        
        /* Mobile: Make notification dropdown responsive and fully visible */
        @media (max-width: 991.98px) {
            #notificationDropdown {
                width: calc(100vw - 2rem) !important;
                max-width: calc(100vw - 2rem) !important;
                min-width: calc(100vw - 2rem) !important;
                margin-left: 1rem !important;
                margin-right: 1rem !important;
                max-height: calc(100vh - 120px) !important;
                border-radius: 12px !important;
                position: fixed !important;
                left: 0 !important;
                right: 0 !important;
                top: auto !important;
            }
            
            /* Ensure dropdown is positioned correctly on mobile */
            .dropdown-menu-end {
                right: 0 !important;
                left: 0 !important;
                margin: 0.5rem 1rem !important;
            }
            
            #notificationDropdown .dropdown-item {
                padding: 1rem !important;
                font-size: 0.9rem !important;
            }
            
            #notificationDropdown .notification-title {
                font-size: 0.95rem !important;
                font-weight: 600 !important;
            }
            
            #notificationDropdown .notification-message {
                font-size: 0.85rem !important;
                line-height: 1.4 !important;
            }
            
            #notificationDropdown .notification-time {
                font-size: 0.8rem !important;
                margin-top: 0.25rem !important;
            }
        }
        
        @media (max-width: 576px) {
            #notificationDropdown {
                width: calc(100vw - 1rem) !important;
                max-width: calc(100vw - 1rem) !important;
                min-width: calc(100vw - 1rem) !important;
                margin-left: 0.5rem !important;
                margin-right: 0.5rem !important;
                max-height: calc(100vh - 100px) !important;
                border-radius: 10px !important;
            }
            
            .dropdown-menu-end {
                margin: 0.5rem !important;
            }
            
            #notificationDropdown .dropdown-header {
                padding: 12px 14px !important;
            }
            
            #notificationDropdown .dropdown-item {
                padding: 0.9rem !important;
                font-size: 0.875rem !important;
            }
        }
        #notificationDropdown .dropdown-header {
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
            padding: 14px 16px;
            z-index: 2;
            border-bottom: 1px solid #e5e7eb;
        }
        #notificationDropdown .dropdown-header .fw-bold {
            color: #800000;
            font-size: 1rem;
        }
        #notificationDropdown .dropdown-header button {
            color: #6c757d !important;
        }
        #notificationList {
            padding: 10px 10px 6px 10px;
            list-style: none;
            margin: 0;
        }
        
        #notificationList:empty {
            padding: 0;
        }
        #notificationDropdown .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 12px 10px 12px;
            margin-bottom: 10px;
            background: #ffffff;
            border: 1px solid #eef2f7;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        #notificationDropdown .notification-item:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        #notificationDropdown .notification-item.unread {
            background: #f0f7ff;
            border-color: #cfe5ff;
            box-shadow: 0 4px 14px rgba(13,110,253,0.10);
        }
        #notificationDropdown .notification-item.unread:hover {
            background: #e0f0ff;
            border-color: #b8daff;
            box-shadow: 0 6px 18px rgba(13,110,253,0.15);
        }
        #notificationDropdown .notification-item:last-child {
            margin-bottom: 4px;
        }
        #notificationDropdown .notification-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        #notificationDropdown .notification-content {
            flex: 1;
            min-width: 0;
        }
        #notificationDropdown .notification-meta {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 6px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        #notificationDropdown .notification-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 2px;
        }
        #notificationDropdown .notification-message {
            color: #374151;
        }
        #notificationDropdown .notification-link {
            color: #0d6efd;
            text-decoration: none;
        }
        #notificationDropdown .notification-link:hover {
            text-decoration: underline;
        }
        
        /* Improve notification badge visibility/position - Show count */
        #notificationBadge {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 18px;
            text-align: center;
            z-index: 1052;
            right: -2px;
            top: -2px;
            transform: translate(50%, -50%);
            pointer-events: none;
            box-shadow: 0 0 0 2px #fff;
            border: none;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            overflow: visible;
        }
        
        /* For numbers 99+ show "99+" */
        #notificationBadge:has-text("99+") {
            padding: 0 4px;
        }
        
        /* Ensure notification button container allows overflow */
        #notificationBtn {
            overflow: visible !important;
        }
        
        /* Ensure parent container allows overflow */
        .position-relative:has(#notificationBadge) {
            overflow: visible !important;
        }
        
        /* Mobile: Adjust notification badge size */
        @media (max-width: 991.98px) {
            #notificationBadge {
                min-width: 16px !important;
                height: 16px !important;
                padding: 0 4px !important;
                font-size: 0.65rem !important;
                line-height: 16px !important;
                right: -2px !important;
                top: -2px !important;
                transform: translate(50%, -50%) !important;
                box-shadow: 0 0 0 2px #fff !important;
                border-radius: 8px !important;
            }
        }
        
        @media (max-width: 576px) {
            #notificationBadge {
                min-width: 14px !important;
                height: 14px !important;
                padding: 0 3px !important;
                font-size: 0.6rem !important;
                line-height: 14px !important;
                right: -1px !important;
                top: -1px !important;
                transform: translate(50%, -50%) !important;
                box-shadow: 0 0 0 1.5px #fff !important;
                border-radius: 7px !important;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top shadow-sm">
        <div class="container-fluid position-relative">
            <a class="navbar-brand d-flex align-items-center" href="../../views/admin/dashboard.php">
                <img src="../../assets/img/evsu-logo.png" alt="EVSU Logo" width="30" height="30" class="me-2">
                <span class="fw-bold d-none d-lg-inline">EVSU Scheduling System</span>
            </a>
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Centered SY & Semester Section (Desktop) -->
            <div class="navbar-sy-semester d-none d-lg-flex">
                <span class="active-sy-display" id="activeSyDisplay">No SY & Semester set</span>
                <?php if ($canManageSySemester): ?>
                <button type="button" class="btn btn-sy-semester" id="btnSetSySemester" data-bs-toggle="modal" data-bs-target="#setSySemesterModal">
                    <i class="bi bi-calendar3 me-1"></i> Set SY & Semester
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Mobile SY & Semester Section -->
            <div class="navbar-sy-semester d-lg-none" style="margin-left: 0.375rem !important;">
                <span class="active-sy-display" id="activeSyDisplayMobile">No SY & Semester set</span>
                <?php if ($canManageSySemester): ?>
                <button type="button" class="btn btn-sy-semester" id="btnSetSySemesterMobile" data-bs-toggle="modal" data-bs-target="#setSySemesterModal">
                    <i class="bi bi-calendar3 me-1"></i> Set SY & Semester
                </button>
                <?php endif; ?>
            </div>
            
            <div class="d-flex align-items-center ms-auto" style="gap: 0.5rem;">
                <!-- Notification Icon -->
                <div class="dropdown position-relative">
                    <button type="button" class="btn btn-link text-white position-relative" id="notificationBtn" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false" style="text-decoration: none; padding: 0.2rem 0.4rem !important;">
                        <i class="bi bi-bell fs-5"></i>
                        <span class="position-absolute badge rounded-pill bg-danger" id="notificationBadge" style="display: none;"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" id="notificationDropdown" style="max-height: 500px; overflow-y: auto;">
                        <li class="dropdown-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Notifications</span>
                                <button class="btn btn-sm btn-link text-muted p-0" id="markAllReadBtn" style="font-size: 0.75rem; text-decoration: none;">Mark all as read</button>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <div id="notificationList">
                                <div class="text-center text-muted p-4">
                                    <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                                    <small>No notifications</small>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                <span class="navbar-text fw-semibold text-white d-none d-lg-block"><?= htmlspecialchars($dashboardTitle) ?></span>
            </div>
        </div>
    </nav>
    
    <!-- Notification System Script (Bootstrap JS loads once later via footer / page scripts — avoids duplicate handlers) -->
    <script>
        (function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationBadge = document.getElementById('notificationBadge');
            const notificationList = document.getElementById('notificationList');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            
            // Track current unread count
            let currentUnreadCount = 0;
            
            // Initialize notification system
            function initNotifications() {
                loadNotifications();
                // Refresh notifications every 30 seconds
                setInterval(loadNotifications, 30000);
            }
            
            // Load notifications
            function loadNotifications() {
                fetch('../../shared/notifications/get_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            if (data.data.length === 0) {
                                notificationList.innerHTML = `
                                    <div class="text-center text-muted p-4">
                                        <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                                        <small>No notifications</small>
                                    </div>
                                `;
                                currentUnreadCount = 0;
                                updateBadge(0);
                            } else {
                                renderNotifications(data.data);
                                // Use unread_count from API response if available, otherwise calculate from array
                                const unreadCount = (typeof data.unread_count !== 'undefined') ? data.unread_count : data.data.filter(n => !n.read).length;
                                
                                // Check if count increased (new notification)
                                if (unreadCount > currentUnreadCount) {
                                    // New notification(s) added - count increased
                                    currentUnreadCount = unreadCount;
                                    updateBadge(currentUnreadCount);
                                } else {
                                    // Update count (might have decreased or stayed same)
                                    currentUnreadCount = unreadCount;
                                    updateBadge(currentUnreadCount);
                                }
                            }
                        } else {
                            notificationList.innerHTML = `
                                <div class="text-center text-muted p-4">
                                    <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                                    <small>No notifications</small>
                                </div>
                            `;
                            currentUnreadCount = 0;
                            updateBadge(0);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notifications:', error);
                        notificationList.innerHTML = `
                            <div class="text-center text-muted p-4">
                                <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                                <small>Error loading notifications</small>
                            </div>
                        `;
                        currentUnreadCount = 0;
                        updateBadge(0);
                    });
            }
            
            // Render notifications
            function renderNotifications(notifications) {
                notificationList.innerHTML = '';
                notifications.forEach(notification => {
                    const item = document.createElement('div');
                    const typeIcon = {
                        'success': 'bi-check-circle-fill',
                        'warning': 'bi-exclamation-triangle-fill',
                        'danger': 'bi-x-circle-fill',
                        'info': 'bi-info-circle-fill'
                    }[notification.type] || 'bi-info-circle-fill';
                    
                    const iconColor = {
                        'success': '#28a745',
                        'warning': '#ffc107',
                        'danger': '#dc3545',
                        'info': '#0d6efd'
                    }[notification.type] || '#0d6efd';
                    
                    item.className = `notification-item ${notification.read ? '' : 'unread'}`;
                    item.style.cursor = notification.target_tab ? 'pointer' : 'default';
                    item.dataset.notificationId = notification.id;
                    const clickableIndicator = notification.target_tab ? '<i class="bi bi-arrow-right text-muted ms-auto" style="font-size: 0.9rem;"></i>' : '';
                    item.innerHTML = `
                        <div class="notification-icon" style="background: ${iconColor}15; color: ${iconColor};">
                            <i class="bi ${typeIcon}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="notification-title">${escapeHtml(notification.title)}</div>
                                ${clickableIndicator}
                            </div>
                            <div class="notification-message">${escapeHtml(notification.message)}</div>
                            <div class="notification-meta">
                                <small>${formatTime(notification.created_at)}</small>
                                ${notification.actor ? `<small class="text-muted ms-2">• ${escapeHtml(notification.actor)}</small>` : ''}
                            </div>
                        </div>
                    `;
                    // Store notification data for click handler
                    item.dataset.notificationId = notification.id;
                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleNotificationClick(notification);
                    });
                    notificationList.appendChild(item);
                });
            }
            
            // Handle notification click - navigate to appropriate tab/page/modal
            function handleNotificationClick(notification) {
                // Only mark as read if it's currently unread
                if (!notification.read) {
                    // Decrease count immediately for better UX
                    if (currentUnreadCount > 0) {
                        currentUnreadCount--;
                        updateBadge(currentUnreadCount);
                    }
                    // Mark as read via API
                    markAsRead(notification.id);
                }
                
                // Close dropdown
                const dropdown = bootstrap.Dropdown.getInstance(notificationBtn);
                if (dropdown) {
                    dropdown.hide();
                }
                
                // Extract details from notification (includes acc_id, room_id, room_request_id, etc.)
                const details = notification.details || {};
                const action = notification.action || '';
                
                // Navigate to target tab if specified
                if (notification.target_tab) {
                    navigateToTab(notification.target_tab, notification, details);
                } else if (notification.target_url) {
                    // Navigate to URL if specified
                    window.location.href = notification.target_url;
                }
            }
            
            // Navigate to a specific tab and optionally open modals
            function navigateToTab(tabId, notification = null, details = {}) {
                const currentPath = window.location.pathname;

                // Special handling for Admin Support, which uses ?tab=overview|users|roles on index.php
                if (currentPath.includes('/views/admin_support/')) {
                    const adminSupportTabs = ['overview', 'users', 'roles'];
                    if (adminSupportTabs.includes(tabId)) {
                        const url = `/views/admin_support/index.php?tab=${encodeURIComponent(tabId)}`;
                        window.location.href = url;
                        return;
                    }
                }

                // Check if we're on dashboards that use in-page tabs (Admin, Moderator, Instructor, legacy Admin Support)
                const isAdminDashboard = currentPath.includes('/views/admin/dashboard.php') || 
                                        currentPath.includes('/views/moderator/dashboard.php') ||
                                        currentPath.includes('/views/instructor/dashboard.php') ||
                                        currentPath.includes('/views/admin_support/dashboard.php') || 
                                        currentPath.includes('/views/admin_support/index.php');
                
                if (isAdminDashboard) {
                    // Use showTab function if available
                    if (typeof showTab === 'function') {
                        // Find the sidebar link that corresponds to this tab
                        const sidebarLink = document.querySelector(`a[href="#${tabId}"], a[onclick*="${tabId}"]`);
                        if (sidebarLink) {
                            showTab(tabId, sidebarLink);
                        } else {
                            // Try to find tab by ID and show it
                            const tabElement = document.getElementById(tabId);
                            if (tabElement) {
                                showTab(tabId, null);
                            } else {
                                console.warn('Tab not found:', tabId);
                            }
                        }
                    } else {
                        // Fallback: try to show tab directly
                        const tabElement = document.getElementById(tabId);
                        if (tabElement) {
                            // Hide all tabs
                            document.querySelectorAll('.tab-content').forEach(tab => {
                                tab.style.display = 'none';
                            });
                            // Show target tab
                            tabElement.style.display = 'block';
                            
                            // Update active sidebar link
                            document.querySelectorAll('.sidebar .nav-link, .sidebar-offcanvas .nav-link').forEach(link => {
                                link.classList.remove('active');
                            });
                            const sidebarLink = document.querySelector(`a[href="#${tabId}"], a[onclick*="${tabId}"]`);
                            if (sidebarLink) {
                                sidebarLink.classList.add('active');
                            }
                        }
                    }
                    
                    // Handle nested tabs within room management
                    if (tabId === 'room_requests') {
                        // Wait a bit for the tab to be shown, then activate the room-requests sub-tab
                        setTimeout(() => {
                            const roomRequestsSubTab = document.getElementById('room-requests-tab');
                            if (roomRequestsSubTab) {
                                // Use Bootstrap's tab API to switch
                                const tab = new bootstrap.Tab(roomRequestsSubTab);
                                tab.show();
                            }
                            // Try to open/filter specific room request if details available
                            if (notification && details) {
                                const requestId = details.room_request_id || details.req_id || details.request_id;
                                if (requestId) {
                                    setTimeout(() => openRoomRequestModal(requestId, details), 500);
                                } else if (details.room_id) {
                                    // Filter by room if request ID not available
                                    setTimeout(() => openRoomRequestModal(null, details), 500);
                                }
                            }
                        }, 300);
                    } else if (tabId === 'room_requests_rooms') {
                        // Navigate to room_requests main tab first, then activate the rooms sub-tab
                        const mainTabId = 'room_requests';
                        const sidebarLink = document.querySelector(`a[href="#${mainTabId}"], a[onclick*="${mainTabId}"]`);
                        if (sidebarLink && typeof showTab === 'function') {
                            showTab(mainTabId, sidebarLink);
                        }
                        // Wait a bit for the tab to be shown, then activate the rooms sub-tab
                        setTimeout(() => {
                            const roomsSubTab = document.getElementById('rooms-tab');
                            if (roomsSubTab) {
                                // Use Bootstrap's tab API to switch
                                const tab = new bootstrap.Tab(roomsSubTab);
                                tab.show();
                            }
                        }, 300);
                    } else if (tabId === 'roles' || tabId === 'manageRoles' || tabId === 'userManagement') {
                        // For user-related notifications, try to open user details modal
                        if (notification && details.acc_id) {
                            setTimeout(() => openUserDetailsModal(details.acc_id, notification), 500);
                        } else if (notification && notification.actor) {
                            // Try to find user by name if acc_id not available
                            setTimeout(() => findAndOpenUserModal(notification.actor, notification), 500);
                        }
                    } else if (tabId === 'schedule' || tabId === 'schedule_management') {
                        // For schedule-related notifications
                        if (notification && details.schedule_id) {
                            setTimeout(() => openScheduleModal(details.schedule_id), 500);
                        }
                    } else if (tabId === 'course_management') {
                        // For course/program-related notifications
                        if (notification && details.program_id) {
                            setTimeout(() => openProgramModal(details.program_id), 500);
                        }
                    }
                } else {
                    // Not on dashboard, redirect to dashboard with tab
                    window.location.href = `dashboard.php#${tabId}`;
                }
            }
            
            // Helper function to open user details modal
            function openUserDetailsModal(userId, notification) {
                // Wait for tab to be fully loaded
                setTimeout(() => {
                    // Check if viewUserDetails function exists (from admin_dashboard.js)
                    if (typeof viewUserDetails === 'function') {
                        viewUserDetails(userId);
                    } else if (typeof editAccount === 'function') {
                        // Try editAccount function if available
                        editAccount(userId);
                    } else {
                        // Try to find and trigger edit account modal directly
                        const editModal = document.getElementById('editAccountModal');
                        if (editModal && typeof bootstrap !== 'undefined') {
                            // Try to load user data - this depends on the user management component
                            // For now, just scroll to the user in the table if it exists
                            scrollToUserInTable(userId);
                        }
                    }
                }, 500);
            }
            
            // Helper function to scroll to user in accounts table
            function scrollToUserInTable(userId) {
                // Try to find the user row in DataTable if it exists
                if (typeof window.accountsTable !== 'undefined' && window.accountsTable) {
                    window.accountsTable.search('').draw(); // Clear any filters
                    // Search for the user ID
                    window.accountsTable.column(0).search(userId).draw();
                    // Scroll to the table
                    const tableContainer = document.querySelector('#accountsTable_wrapper');
                    if (tableContainer) {
                        tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
            
            // Helper function to find user by name and open modal
            function findAndOpenUserModal(userName, notification) {
                // This is a fallback - try to find user in the accounts list
                if (typeof __ACCOUNTS_CACHE__ !== 'undefined' && Array.isArray(__ACCOUNTS_CACHE__)) {
                    const nameParts = userName.trim().split(' ');
                    const user = __ACCOUNTS_CACHE__.find(u => {
                        const fullName = `${u.fname || ''} ${u.lname || ''}`.trim();
                        return fullName === userName || 
                               (nameParts.length >= 2 && u.fname === nameParts[0] && u.lname === nameParts[1]);
                    });
                    if (user && user.acc_id) {
                        openUserDetailsModal(user.acc_id, notification);
                    }
                }
            }
            
            // Helper function to open/filter room request
            function openRoomRequestModal(requestId, details) {
                // Wait for tab to be fully loaded
                setTimeout(() => {
                    // Try to filter the room requests table to show this specific request
                    if (typeof window.roomRequestsTable !== 'undefined' && window.roomRequestsTable) {
                        // Clear existing filters
                        window.roomRequestsTable.search('').draw();
                        // Search for the request ID
                        window.roomRequestsTable.column(0).search(requestId).draw();
                        // Scroll to the table
                        const tableContainer = document.querySelector('#roomRequestsTable_wrapper');
                        if (tableContainer) {
                            tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } else if (typeof loadRoomRequests === 'function') {
                        // If table not initialized, try to load it
                        loadRoomRequests();
                        setTimeout(() => {
                            if (typeof window.roomRequestsTable !== 'undefined' && window.roomRequestsTable) {
                                window.roomRequestsTable.column(0).search(requestId).draw();
                            }
                        }, 1000);
                    }
                    
                    // Also try to filter by room if room_id is available
                    if (details && details.room_id) {
                        const roomFilter = document.getElementById('roomRequestRoomFilter');
                        if (roomFilter) {
                            roomFilter.value = details.room_id;
                            // Trigger filter change event
                            const event = new Event('change', { bubbles: true });
                            roomFilter.dispatchEvent(event);
                        }
                    }
                }, 500);
            }
            
            // Helper function to open schedule modal
            function openScheduleModal(scheduleId) {
                setTimeout(() => {
                    // Try to find and highlight the schedule in the schedule table
                    if (typeof window.schedulesTable !== 'undefined' && window.schedulesTable) {
                        window.schedulesTable.column(0).search(scheduleId).draw();
                        const tableContainer = document.querySelector('#schedulesTable_wrapper');
                        if (tableContainer) {
                            tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                }, 500);
            }
            
            // Helper function to open program modal
            function openProgramModal(programId) {
                setTimeout(() => {
                    // Try to find and highlight the program in the programs table
                    if (typeof window.programsTable !== 'undefined' && window.programsTable) {
                        window.programsTable.column(0).search(programId).draw();
                        const tableContainer = document.querySelector('#programsTable_wrapper');
                        if (tableContainer) {
                            tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                }, 500);
            }
            
            // Update badge count - accepts unread count directly
            function updateBadge(unreadCount) {
                // Ensure unreadCount is a number
                const count = typeof unreadCount === 'number' ? unreadCount : 0;
                
                if (count > 0) {
                    // Show badge with count
                    notificationBadge.style.display = 'flex';
                    // Show "99+" for counts over 99
                    notificationBadge.textContent = count > 99 ? '99+' : count.toString();
                } else {
                    // Hide badge when no unread notifications
                    notificationBadge.style.display = 'none';
                    notificationBadge.textContent = '';
                }
            }
            
            // Mark notification as read
            function markAsRead(notificationId) {
                fetch('../../shared/notifications/mark_as_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the notification item visually
                        const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                        if (notificationItem) {
                            notificationItem.classList.remove('unread');
                        }
                        // Reload notifications to sync with server (optional - for consistency)
                        // loadNotifications();
                    } else {
                        // If API call failed, restore the count
                        if (currentUnreadCount >= 0) {
                            currentUnreadCount++;
                            updateBadge(currentUnreadCount);
                        }
                        console.error('Error marking notification as read:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                    // If API call failed, restore the count
                    if (currentUnreadCount >= 0) {
                        currentUnreadCount++;
                        updateBadge(currentUnreadCount);
                    }
                });
            }
            
            // Mark all as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Reset count immediately
                    currentUnreadCount = 0;
                    updateBadge(0);
                    
                    // Mark all as read via API
                    fetch('../../shared/notifications/mark_all_as_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove unread class from all notifications
                            document.querySelectorAll('.notification-item.unread').forEach(item => {
                                item.classList.remove('unread');
                            });
                            // Reload notifications to sync
                            loadNotifications();
                        } else {
                            console.error('Error marking all notifications as read:', data.message);
                            // Reload to restore correct count
                            loadNotifications();
                        }
                    })
                    .catch(error => {
                        console.error('Error marking all notifications as read:', error);
                        // Reload to restore correct count
                        loadNotifications();
                    });
                });
            }
            
            // Helper functions
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatTime(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diff = now - date;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                const days = Math.floor(diff / 86400000);
                
                if (minutes < 1) return 'Just now';
                if (minutes < 60) return `${minutes}m ago`;
                if (hours < 24) return `${hours}h ago`;
                if (days < 7) return `${days}d ago`;
                return date.toLocaleDateString();
            }
            
            // Initialize on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initNotifications);
            } else {
                initNotifications();
            }
        })();
    </script>
    
    <!-- Set SY & Semester Modal (Only visible to Admin/Admin Support) -->
    <?php if ($canManageSySemester): ?>
    <div class="modal fade" id="setSySemesterModal" tabindex="-1" aria-labelledby="setSySemesterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title" id="setSySemesterModalLabel">
                        <i class="bi bi-calendar3 me-2"></i>Set School Year & Semester
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="setSySemesterForm">
                    <div class="modal-body">
                        <div id="modalAlertContainer"></div>
                        
                        <!-- School Year Input with Up/Down Controls -->
                        <div class="mb-3">
                            <label for="syYearStart" class="form-label fw-semibold">School Year <span class="text-danger">*</span></label>
                            <div class="row g-2 align-items-center">
                                <div class="col-5">
                                    <div class="input-group">
                                        <input 
                                            type="number" 
                                            class="form-control" 
                                            id="syYearStart" 
                                            name="sy_year_start" 
                                            min="2020" 
                                            max="2100" 
                                            required
                                            style="text-align: center;"
                                        >
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-secondary" 
                                            onclick="incrementYear('syYearStart', 1)"
                                            title="Increase Year"
                                        >
                                            <i class="bi bi-chevron-up"></i>
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-secondary" 
                                            onclick="incrementYear('syYearStart', -1)"
                                            title="Decrease Year"
                                        >
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-2 text-center">
                                    <span class="fw-bold">-</span>
                                </div>
                                <div class="col-5">
                                    <div class="input-group">
                                        <input 
                                            type="number" 
                                            class="form-control" 
                                            id="syYearEnd" 
                                            name="sy_year_end" 
                                            min="2020" 
                                            max="2100" 
                                            required
                                            readonly
                                            style="text-align: center; background-color: #e9ecef;"
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Use up/down arrows to adjust the start year. End year updates automatically.
                            </div>
                        </div>
                        
                        <!-- Semester Dropdown -->
                        <div class="mb-3">
                            <label for="semesterValue" class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                            <select 
                                class="form-select" 
                                id="semesterValue" 
                                name="semester" 
                                required
                            >
                                <option value="">-- Select Semester --</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Mid-Year">Mid/Summer</option>
                            </select>
                            <div class="form-text">
                                <i class="bi bi-lightbulb me-1"></i>
                                Semester is automatically determined based on progression logic, but you can manually override if needed.
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="alert alert-info mb-0">
                            <strong>Preview:</strong> <span id="sySemesterPreview">-</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-maroon" id="submitSySemesterBtn">
                            <i class="bi bi-check-circle me-1"></i> Set Active SY & Semester
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Class Curriculum Modal -->
    <div class="modal fade" id="classCurriculumModal" tabindex="-1" aria-labelledby="classCurriculumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title" id="classCurriculumModalLabel">
                        <i class="bi bi-book me-2"></i><span id="classCurriculumModalTitle">Class Curriculum</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="classCurriculumAlertContainer"></div>
                    
                    <!-- Program Selection -->
                    <div class="mb-4">
                        <label for="classCurriculumProgram" class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="classCurriculumProgram" name="program_id" required>
                            <option value="">-- Select Program --</option>
                        </select>
                    </div>
                    
                    <!-- Year Level Curriculum Settings -->
                    <div id="yearLevelCurriculumContainer" style="display: none;">
                        <h6 class="fw-semibold mb-3">Set Curriculum for Each Year Level</h6>
                        <div id="yearLevelCurriculumRows">
                            <!-- Year level rows will be generated here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="classCurriculumBackBtn" data-bs-dismiss="modal">Back</button>
                    <button type="button" class="btn btn-maroon" id="classCurriculumSaveBtn">
                        <i class="bi bi-check-circle me-1"></i> Save Class Setting
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // SY & Semester Management Script
        (function() {
            const basePath = '../../shared/management/';
            const canManage = <?php echo $canManageSySemester ? 'true' : 'false'; ?>;
            let activeSyData = null;
            
            // Load active SY & Semester on page load (with auto-progression)
            function loadActiveSySemester() {
                fetch(basePath + 'get_current_sy_semester.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            activeSyData = data.data;
                            updateActiveDisplay(data.data);
                        } else {
                            activeSyData = null;
                            updateActiveDisplay(null);
                        }
                        checkButtonState();
                    })
                    .catch(error => {
                        console.error('Error loading active SY & Semester:', error);
                        updateActiveDisplay(null);
                    });
            }
            
            // Update the display of active SY & Semester
            function updateActiveDisplay(data) {
                let displayText = 'No SY & Semester set';
                
                if (data) {
                    // Clean and format the display text
                    let syYear = data.sy_year || '';
                    let semester = data.semester || '';
                    
                    // Clean sy_year - remove any non-printable characters and extra spaces
                    syYear = String(syYear).trim().replace(/[^\d\s\-]/g, '').replace(/\s+/g, ' ');
                    
                    // Clean semester - ensure it's a valid semester name
                    semester = String(semester).trim();
                    const validSemesters = ['1st Semester', '2nd Semester', 'Mid-Year'];
                    if (!validSemesters.includes(semester)) {
                        // Try to clean it
                        semester = semester.replace(/[^a-zA-Z0-9\s\-]/g, '').trim();
                    }
                    
                    // Display "Mid/Summer" instead of "Mid-Year" in the header
                    const displaySemester = semester === 'Mid-Year' ? 'Mid/Summer' : semester;
                    
                    // Build formatted text
                    if (syYear && semester) {
                        displayText = `${syYear} | ${displaySemester}`;
                    } else if (data.formatted) {
                        // Use formatted if available, but clean it and replace Mid-Year with Mid/Summer
                        let formatted = String(data.formatted).trim();
                        formatted = formatted.replace(/Mid-Year/g, 'Mid/Summer');
                        displayText = formatted;
                    } else if (syYear) {
                        displayText = syYear;
                    } else if (semester) {
                        displayText = displaySemester;
                    }
                }
                
                // Update both displays
                const desktopDisplay = document.getElementById('activeSyDisplay');
                const mobileDisplay = document.getElementById('activeSyDisplayMobile');
                
                if (desktopDisplay) {
                    desktopDisplay.textContent = displayText;
                }
                if (mobileDisplay) {
                    mobileDisplay.textContent = displayText;
                }
            }
            
            // Check if button should be disabled (always enabled in new system)
            function checkButtonState() {
                // Button is always enabled in new auto-progression system
                enableButtons();
            }
            
            function disableButtons(message) {
                if (!canManage) return;
                const buttons = ['btnSetSySemester', 'btnSetSySemesterMobile'];
                buttons.forEach(btnId => {
                    const btn = document.getElementById(btnId);
                    if (btn) {
                        btn.disabled = true;
                        btn.title = message || 'Maximum semesters reached for this School Year';
                    }
                });
            }
            
            function enableButtons() {
                if (!canManage) return;
                const buttons = ['btnSetSySemester', 'btnSetSySemesterMobile'];
                buttons.forEach(btnId => {
                    const btn = document.getElementById(btnId);
                    if (btn) {
                        btn.disabled = false;
                        btn.title = '';
                    }
                });
            }
            
            // Initialize year fields with current academic year
            function initializeYearFields() {
                // Get current year
                const now = new Date();
                const currentMonth = now.getMonth() + 1; // 1-12
                const currentYear = now.getFullYear();
                
                let startYear;
                if (currentMonth >= 8) {
                    // August or later: current year is start year
                    startYear = currentYear;
                } else {
                    // Before August: previous year is start year
                    startYear = currentYear - 1;
                }
                
                const endYear = startYear + 1;
                
                // Set year fields
                document.getElementById('syYearStart').value = startYear;
                document.getElementById('syYearEnd').value = endYear;
                
                // Auto-determine semester
                determineSemester();
            }
            
            // Determine semester based on current date and progression logic
            function determineSemester() {
                const now = new Date();
                const currentMonth = now.getMonth() + 1; // 1-12
                
                // Get current active semester to determine next
                if (activeSyData && activeSyData.semester) {
                    const currentSemester = activeSyData.semester;
                    
                    // Progression logic
                    if (currentSemester === '1st Semester') {
                        // Next is 2nd Semester
                        setSemester('2nd Semester');
                    } else if (currentSemester === '2nd Semester' || currentSemester === 'Mid-Year') {
                        // Next is 1st Semester of next SY
                        setSemester('1st Semester');
                    } else {
                        // Default based on current month
                        if (currentMonth >= 8 && currentMonth <= 12) {
                            setSemester('1st Semester');
                        } else if (currentMonth >= 1 && currentMonth <= 5) {
                            setSemester('2nd Semester');
                        } else {
                            setSemester('Mid-Year');
                        }
                    }
                } else {
                    // No current semester, determine from current month
                    if (currentMonth >= 8 && currentMonth <= 12) {
                        setSemester('1st Semester');
                    } else if (currentMonth >= 1 && currentMonth <= 5) {
                        setSemester('2nd Semester');
                    } else {
                        setSemester('Mid-Year');
                    }
                }
            }
            
            // Set semester value
            function setSemester(semester) {
                const semesterDropdown = document.getElementById('semesterValue');
                if (semesterDropdown) {
                    semesterDropdown.value = semester;
                    updatePreview();
                }
            }
            
            // Update preview
            function updatePreview() {
                const startYear = document.getElementById('syYearStart').value;
                const endYear = document.getElementById('syYearEnd').value;
                const semester = document.getElementById('semesterValue').value;
                
                if (startYear && endYear && semester) {
                    // Display "Mid/Summer" in preview instead of "Mid-Year"
                    const displaySemester = semester === 'Mid-Year' ? 'Mid/Summer' : semester;
                    const preview = `${startYear} - ${endYear} | ${displaySemester}`;
                    document.getElementById('sySemesterPreview').textContent = preview;
                } else {
                    document.getElementById('sySemesterPreview').textContent = '-';
                }
            }
            
            // Increment year function (global for onclick)
            window.incrementYear = function(fieldId, direction) {
                const field = document.getElementById(fieldId);
                if (!field) return;
                
                let currentValue = parseInt(field.value) || new Date().getFullYear();
                const newValue = currentValue + direction;
                
                // Validate range
                if (newValue >= 2020 && newValue <= 2100) {
                    field.value = newValue;
                    
                    // Update end year if start year changed
                    if (fieldId === 'syYearStart') {
                        document.getElementById('syYearEnd').value = newValue + 1;
                    }
                    
                    updatePreview();
                }
            };
            
            // Handle form submission (only for admins)
            if (canManage) {
                const form = document.getElementById('setSySemesterForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                const submitBtn = document.getElementById('submitSySemesterBtn');
                        const originalText = submitBtn.innerHTML;
                        
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Setting...';
                        
                        // Build sy_year from start and end years
                const startYear = document.getElementById('syYearStart').value;
                const endYear = document.getElementById('syYearEnd').value;
                const syYear = `${startYear} - ${endYear}`;
                const semester = document.getElementById('semesterValue').value;
                
                const formData = new FormData();
                formData.append('sy_year', syYear);
                formData.append('semester', semester);
                
                fetch(basePath + 'set_current_sy_semester.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showAlert('success', data.message);
                                    loadActiveSySemester();
                                    
                                    // Trigger event to refresh schedule form data (school year dropdown)
                                    const event = new CustomEvent('schoolYearUpdated');
                                    document.dispatchEvent(event);
                                    
                                    // Also call the global refresh function if available
                                    if (typeof window.refreshScheduleFormData === 'function') {
                                        window.refreshScheduleFormData();
                                    }
                                    
                                    // Close the SY & Semester modal and show curriculum modal
                                    setTimeout(() => {
                                        const syModal = bootstrap.Modal.getInstance(document.getElementById('setSySemesterModal'));
                                        syModal.hide();
                                        document.getElementById('setSySemesterForm').reset();
                                        
                                        // Show curriculum modal
                                        showClassCurriculumModal(syYear, semester);
                                    }, 1500);
                                } else {
                                    showAlert('danger', data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error setting SY & Semester:', error);
                                showAlert('danger', 'An error occurred. Please try again.');
                            })
                            .finally(() => {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            });
                    });
                }
            }
            
            // Show alert in modal
            function showAlert(type, message) {
                const container = document.getElementById('modalAlertContainer');
                container.innerHTML = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            }
            
            // Load data when modal is opened (only for admins)
            if (canManage) {
                const modal = document.getElementById('setSySemesterModal');
                if (modal) {
                    modal.addEventListener('show.bs.modal', function() {
                        document.getElementById('modalAlertContainer').innerHTML = '';
                        initializeYearFields();
                        // Focus on start year field
                        setTimeout(function() {
                            document.getElementById('syYearStart').focus();
                            document.getElementById('syYearStart').select();
                        }, 300);
                    });
                }
                
                // Add event listeners for year changes
                const syYearStart = document.getElementById('syYearStart');
                if (syYearStart) {
                    syYearStart.addEventListener('input', function() {
                        const startYear = parseInt(this.value) || new Date().getFullYear();
                        document.getElementById('syYearEnd').value = startYear + 1;
                        updatePreview();
                    });
                    
                    syYearStart.addEventListener('change', function() {
                        determineSemester();
                    });
                }
                
                // Add event listener for semester dropdown changes
                const semesterDropdown = document.getElementById('semesterValue');
                if (semesterDropdown) {
                    semesterDropdown.addEventListener('change', function() {
                        updatePreview();
                    });
                }
            }
            
            // Initialize on page load
            loadActiveSySemester();
        })();
        
        // Class Curriculum Modal Script
        (function() {
            const apiBasePath = '../../admin/management/';
            let currentSyYear = '';
            let currentSemester = '';
            let selectedProgramId = null;
            
            /**
             * Shows the Class Curriculum Modal with the given school year and semester
             */
            function showClassCurriculumModal(syYear, semester) {
                currentSyYear = syYear;
                currentSemester = semester;
                
                // Update modal title
                const titleElement = document.getElementById('classCurriculumModalTitle');
                if (titleElement) {
                    titleElement.textContent = `S.Y. ${syYear} | ${semester} Class Curriculum`;
                }
                
                // Reset form
                document.getElementById('classCurriculumProgram').value = '';
                document.getElementById('yearLevelCurriculumContainer').style.display = 'none';
                document.getElementById('yearLevelCurriculumRows').innerHTML = '';
                document.getElementById('classCurriculumAlertContainer').innerHTML = '';
                selectedProgramId = null;
                
                // Load programs
                loadProgramsForCurriculumModal();
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('classCurriculumModal'));
                modal.show();
            }
            
            /**
             * Loads programs for the curriculum modal
             */
            function loadProgramsForCurriculumModal() {
                const programSelect = document.getElementById('classCurriculumProgram');
                if (!programSelect) return;
                
                programSelect.innerHTML = '<option value="">Loading Programs...</option>';
                
                fetch(apiBasePath + 'get_schedule_form_data.php')
                    .then(response => response.json())
                    .then(data => {
                        programSelect.innerHTML = '<option value="">-- Select Program --</option>';
                        if (data.success && data.programs && data.programs.length > 0) {
                            data.programs.forEach(program => {
                                const option = document.createElement('option');
                                option.value = program.program_id;
                                option.textContent = program.program_display || 
                                    (program.program_code && program.program_name ? 
                                        `${program.program_code} - ${program.program_name}` : 
                                        program.program_name || 'Unknown');
                                programSelect.appendChild(option);
                            });
                        } else {
                            programSelect.innerHTML = '<option value="">No Programs Found</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading programs:', error);
                        programSelect.innerHTML = '<option value="">Error Loading Programs</option>';
                    });
            }
            
            /**
             * Loads curricula for the selected program and creates year level rows
             */
            function loadCurriculaForProgram(programId) {
                selectedProgramId = programId;
                
                if (!programId) {
                    document.getElementById('yearLevelCurriculumContainer').style.display = 'none';
                    return;
                }
                
                // Show loading state
                const container = document.getElementById('yearLevelCurriculumContainer');
                const rowsContainer = document.getElementById('yearLevelCurriculumRows');
                container.style.display = 'block';
                rowsContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading curricula...</div>';
                
                // Fetch curricula for this program
                Promise.all([
                    fetch(apiBasePath + 'get_curricula_by_program.php?program_id=' + programId).then(r => r.json()),
                    fetch(apiBasePath + 'get_curriculum_usage_by_year_level.php?program_id=' + programId).then(r => r.json())
                ])
                .then(([curriculaData, usageData]) => {
                    const curricula = curriculaData.success ? curriculaData.data.curricula : [];
                    const usage = usageData.success ? usageData.data.usage : {};
                    
                    // Generate year level rows
                    let html = '';
                    const yearLevels = [
                        { value: 1, label: '1st Year' },
                        { value: 2, label: '2nd Year' },
                        { value: 3, label: '3rd Year' },
                        { value: 4, label: '4th Year' }
                    ];
                    
                    yearLevels.forEach(yearLevel => {
                        const currentCurrId = usage[yearLevel.value] ? usage[yearLevel.value].curr_id : '';
                        const currentCurrName = usage[yearLevel.value] ? usage[yearLevel.value].curriculum_name : '';
                        
                        html += `
                            <div class="row align-items-center mb-3 year-level-row" data-year-level="${yearLevel.value}">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-outline-danger btn-sm" disabled style="min-width: 100px;">
                                        ${yearLevel.label}
                                    </button>
                                </div>
                                <div class="col">
                                    <select class="form-select curriculum-select" data-year-level="${yearLevel.value}" required>
                                        <option value="">-- Select Curriculum --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-danger btn-sm change-curriculum-btn" data-year-level="${yearLevel.value}">
                                        Change
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    rowsContainer.innerHTML = html;
                    
                    // Populate curriculum dropdowns
                    const curriculumSelects = rowsContainer.querySelectorAll('.curriculum-select');
                    curriculumSelects.forEach(select => {
                        const yearLevel = parseInt(select.dataset.yearLevel);
                        const currentCurrId = usage[yearLevel] ? usage[yearLevel].curr_id : '';
                        
                        curricula.forEach(curriculum => {
                            const option = document.createElement('option');
                            option.value = curriculum.curr_id;
                            option.textContent = `${curriculum.curr_name} (${curriculum.curr_yr})`;
                            if (curriculum.curr_id == currentCurrId) {
                                option.selected = true;
                            }
                            select.appendChild(option);
                        });
                    });
                    
                    // Add event listeners for Change buttons
                    const changeButtons = rowsContainer.querySelectorAll('.change-curriculum-btn');
                    changeButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            const yearLevel = parseInt(this.dataset.yearLevel);
                            const select = rowsContainer.querySelector(`.curriculum-select[data-year-level="${yearLevel}"]`);
                            if (select && select.value) {
                                saveYearLevelCurriculum(yearLevel, select.value, this);
                            } else {
                                showCurriculumAlert('warning', `Please select a curriculum for ${this.previousElementSibling.previousElementSibling.querySelector('button').textContent.trim()}.`);
                            }
                        });
                    });
                })
                .catch(error => {
                    console.error('Error loading curricula:', error);
                    rowsContainer.innerHTML = '<div class="alert alert-danger">Error loading curricula. Please try again.</div>';
                });
            }
            
            /**
             * Saves curriculum for a single year level
             */
            function saveYearLevelCurriculum(yearLevel, currId, buttonElement) {
                if (!selectedProgramId) {
                    showCurriculumAlert('danger', 'Please select a program first.');
                    return;
                }
                
                // Disable button
                const originalText = buttonElement.innerHTML;
                buttonElement.disabled = true;
                buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                // Prepare form data
                const formData = new FormData();
                formData.append('program_id', selectedProgramId);
                formData.append('year_levels[]', yearLevel);
                formData.append('curr_id', currId);
                
                fetch(apiBasePath + 'bulk_update_subject_curriculum.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const yearLevelNames = { 1: '1st Year', 2: '2nd Year', 3: '3rd Year', 4: '4th Year' };
                        showCurriculumAlert('success', `Curriculum for ${yearLevelNames[yearLevel]} updated successfully!`);
                    } else {
                        showCurriculumAlert('danger', data.message || 'Failed to update curriculum.');
                    }
                })
                .catch(error => {
                    console.error('Error saving curriculum:', error);
                    showCurriculumAlert('danger', 'An error occurred while saving. Please try again.');
                })
                .finally(() => {
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = originalText;
                });
            }
            
            /**
             * Saves curriculum settings for all year levels
             */
            function saveClassCurriculumSettings() {
                if (!selectedProgramId) {
                    showCurriculumAlert('danger', 'Please select a program first.');
                    return;
                }
                
                const curriculumSelects = document.querySelectorAll('.curriculum-select');
                const changes = [];
                
                curriculumSelects.forEach(select => {
                    const yearLevel = parseInt(select.dataset.yearLevel);
                    const currId = select.value;
                    if (currId) {
                        changes.push({ yearLevel, currId });
                    }
                });
                
                if (changes.length === 0) {
                    showCurriculumAlert('warning', 'Please select at least one curriculum.');
                    return;
                }
                
                // Disable save button
                const saveBtn = document.getElementById('classCurriculumSaveBtn');
                const originalText = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
                
                // Prepare form data
                const formData = new FormData();
                formData.append('program_id', selectedProgramId);
                changes.forEach(change => {
                    formData.append('year_levels[]', change.yearLevel);
                });
                formData.append('curr_id', changes[0].currId); // Use first curriculum for now
                
                // Save each year level's curriculum separately
                Promise.all(changes.map(change => {
                    const changeFormData = new FormData();
                    changeFormData.append('program_id', selectedProgramId);
                    changeFormData.append('year_levels[]', change.yearLevel);
                    changeFormData.append('curr_id', change.currId);
                    
                    return fetch(apiBasePath + 'bulk_update_subject_curriculum.php', {
                        method: 'POST',
                        body: changeFormData
                    }).then(r => r.json());
                }))
                .then(results => {
                    const allSuccess = results.every(r => r.success);
                    if (allSuccess) {
                        showCurriculumAlert('success', 'Curriculum settings saved successfully!');
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('classCurriculumModal'));
                            modal.hide();
                        }, 1500);
                    } else {
                        showCurriculumAlert('danger', 'Some curriculum settings could not be saved. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error saving curriculum settings:', error);
                    showCurriculumAlert('danger', 'An error occurred while saving. Please try again.');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                });
            }
            
            /**
             * Shows an alert in the curriculum modal
             */
            function showCurriculumAlert(type, message) {
                const container = document.getElementById('classCurriculumAlertContainer');
                container.innerHTML = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            }
            
            // Event listeners
            if (document.getElementById('classCurriculumModal')) {
                const programSelect = document.getElementById('classCurriculumProgram');
                if (programSelect) {
                    programSelect.addEventListener('change', function() {
                        loadCurriculaForProgram(this.value);
                    });
                }
                
                const saveBtn = document.getElementById('classCurriculumSaveBtn');
                if (saveBtn) {
                    saveBtn.addEventListener('click', saveClassCurriculumSettings);
                }
                
                // Reset modal when closed
                const modal = document.getElementById('classCurriculumModal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', function() {
                        document.getElementById('classCurriculumProgram').value = '';
                        document.getElementById('yearLevelCurriculumContainer').style.display = 'none';
                        document.getElementById('yearLevelCurriculumRows').innerHTML = '';
                        document.getElementById('classCurriculumAlertContainer').innerHTML = '';
                        selectedProgramId = null;
                    });
                }
            }
            
            // Make function globally accessible
            window.showClassCurriculumModal = showClassCurriculumModal;
        })();
    </script>
    
    <!-- Mobile Sidebar Offcanvas Initialization -->
    <script>
        // Ensure Bootstrap offcanvas is properly initialized for mobile menu
        (function() {
            'use strict';
            
            function initMobileSidebar() {
                const sidebarToggle = document.querySelector('.navbar-toggler[data-bs-target="#sidebarOffcanvas"]');
                const sidebarOffcanvas = document.getElementById('sidebarOffcanvas');
                
                if (!sidebarToggle || !sidebarOffcanvas) {
                    console.warn('Mobile sidebar elements not found');
                    return;
                }
                
                // Ensure Bootstrap is loaded
                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded. Mobile menu will not work.');
                    return;
                }
                
                // Initialize offcanvas - Bootstrap will handle toggle via data-bs-toggle attribute
                // We just need to ensure it's initialized properly
                let offcanvasInstance = bootstrap.Offcanvas.getInstance(sidebarOffcanvas);
                if (!offcanvasInstance) {
                    // Initialize with proper options
                    offcanvasInstance = new bootstrap.Offcanvas(sidebarOffcanvas, {
                        backdrop: true,
                        scroll: false,
                        keyboard: true
                    });
                }
                
                // Track opening state to prevent immediate closing
                let isOpening = false;
                let openTimeout = null;
                
                // Listen for Bootstrap offcanvas events
                sidebarOffcanvas.addEventListener('show.bs.offcanvas', function(e) {
                    isOpening = true;
                    // Clear any pending close operations
                    if (openTimeout) {
                        clearTimeout(openTimeout);
                    }
                    // Ensure offcanvas is visible when showing
                    this.style.visibility = 'visible';
                    this.style.transform = 'translateX(0)';
                    this.classList.add('show');
                    
                    // Reset opening flag after animation completes
                    openTimeout = setTimeout(function() {
                        isOpening = false;
                    }, 350);
                });
                
                sidebarOffcanvas.addEventListener('shown.bs.offcanvas', function(e) {
                    // Ensure offcanvas stays visible after shown
                    this.style.visibility = 'visible';
                    this.style.transform = 'translateX(0)';
                    this.classList.add('show');
                    isOpening = false;
                });
                
                sidebarOffcanvas.addEventListener('hide.bs.offcanvas', function(e) {
                    // Prevent hiding if we just opened
                    if (isOpening) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                });
                
                sidebarOffcanvas.addEventListener('hidden.bs.offcanvas', function(e) {
                    // Clean up after hiding
                    this.style.transform = 'translateX(-100%)';
                    this.style.visibility = 'hidden';
                    this.classList.remove('show');
                    isOpening = false;
                    
                    // Force remove any lingering backdrop
                    const backdrops = document.querySelectorAll('.offcanvas-backdrop');
                    backdrops.forEach(backdrop => {
                        backdrop.remove();
                    });
                    
                    // Remove backdrop class from body
                    document.body.classList.remove('offcanvas-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                });
                
                // Clean up backdrop on show event as well to prevent duplicates
                sidebarOffcanvas.addEventListener('show.bs.offcanvas', function(e) {
                    // Remove any existing backdrops first
                    const existingBackdrops = document.querySelectorAll('.offcanvas-backdrop');
                    existingBackdrops.forEach(backdrop => backdrop.remove());
                });
                
                // Ensure button is visible and clickable on mobile
                if (window.innerWidth <= 991.98) {
                    sidebarToggle.style.display = 'block';
                    sidebarToggle.style.pointerEvents = 'auto';
                    sidebarToggle.style.zIndex = '1050';
                }
            }
            
            // Cleanup function to remove lingering backdrops
            function cleanupBackdrops() {
                // Remove any orphaned offcanvas backdrops
                const backdrops = document.querySelectorAll('.offcanvas-backdrop');
                backdrops.forEach(backdrop => {
                    // Check if there's an active offcanvas
                    const activeOffcanvas = document.querySelector('.offcanvas.show');
                    if (!activeOffcanvas) {
                        backdrop.remove();
                    }
                });
                
                // Clean up body classes and styles
                const offcanvasOpen = document.querySelector('.offcanvas.show');
                if (!offcanvasOpen) {
                    document.body.classList.remove('offcanvas-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            }
            
            // Clean up on page load
            cleanupBackdrops();
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    initMobileSidebar();
                    cleanupBackdrops();
                });
            } else {
                initMobileSidebar();
                cleanupBackdrops();
            }
            
            // Clean up periodically to catch any missed backdrops
            setInterval(cleanupBackdrops, 1000);
            
            // Re-initialize on window resize to handle orientation changes
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    initMobileSidebar();
                    cleanupBackdrops();
                }, 250);
            });
        })();
    </script>
    
    <!-- Immediate Backdrop Cleanup Script -->
    <script>
        // Remove any lingering backdrops immediately on page load
        (function() {
            function removeLingeringBackdrops() {
                // Check if offcanvas is actually showing
                const offcanvas = document.getElementById('sidebarOffcanvas');
                const isOffcanvasShowing = offcanvas && offcanvas.classList.contains('show');
                
                if (!isOffcanvasShowing) {
                    // Remove all backdrops if offcanvas is not showing
                    const backdrops = document.querySelectorAll('.offcanvas-backdrop');
                    backdrops.forEach(backdrop => {
                        backdrop.remove();
                    });
                    
                    // Clean up body
                    document.body.classList.remove('offcanvas-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            }
            
            // Run immediately
            removeLingeringBackdrops();
            
            // Run after DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeLingeringBackdrops);
            } else {
                setTimeout(removeLingeringBackdrops, 100);
            }
            
            // Run after a short delay to catch any late-loading issues
            setTimeout(removeLingeringBackdrops, 500);
        })();
    </script>

