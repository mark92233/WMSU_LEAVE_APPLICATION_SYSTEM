<?php

// --- CRITICAL FIX 1: session_start() MUST be the very first line ---
session_start();

// --- CRITICAL FIX 2: Apply anti-caching headers immediately after session start ---
// This prevents the browser from loading the cached page content after logout.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// --- CRITICAL LOGOUT FIX START ---
// The session is now active and headers are set, so destruction is effective immediately.
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    
    // Unset all session variables
    $_SESSION = array(); 
    
    // Destroy the session itself
    session_destroy(); 
    
    // Redirect to the login page (index.php) after logout
    header("Location: index.php");
    exit();
}
// --- CRITICAL LOGOUT FIX END ---


// --- 3. CORE SECURITY CHECK (Must happen before any business logic) ---
// If the session doesn't contain the necessary user data, redirect them.
if (!isset($_SESSION['employeeID'], $_SESSION['Role'])) {
    header("Location: index.php");
    exit();
}


// --- 1. SETUP AND INCLUDES ---
require_once "dbRelated/db_connect.php";
require_once "dbRelated/operation.php"; 

// --- Core Utility Functions (Unmodified) ---
function calculateEndDate($start, $days) {
    if (!$start || $days <= 0) { return ""; }
    $date = new DateTime($start);
    $daysRemaining = $days; 

    while ($date->format('N') >= 6) { 
        $date->modify('+1 day');
    }

    while ($daysRemaining > 1) { 
        $date->modify('+1 day');
        $dayOfWeek = $date->format('N'); 
        if ($dayOfWeek < 6) { 
            $daysRemaining--;
        }
    }
    return $date->format('Y-m-d');
}

function clean($v) { 
    return htmlspecialchars(trim($v)); 
}


// --- 2. Initialize Managers ---
$employeeManager = new EmployeeManager();
$creditManager = new LeaveCreditManager();
$applicationManager = new LeaveApplicationManager();
$notifyObj = new NotificationManager(); 


// --- AJAX Handler for Marking Notifications as Read ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'mark_read') {
    header('Content-Type: application/json'); // Tell browser this is data, not HTML
    
    if (isset($_SESSION['employeeID'])) {
        $success = $notifyObj->markAsRead($_SESSION['employeeID']);
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No session']);
    }
    
    exit(); // CRITICAL: Stop script here so no HTML is sent!
}


// --- 3. User Identity Variables ---
$employeeID = $_SESSION['employeeID'];
$Role = $_SESSION['Role']; 

// --- Fetch User-Specific Data ---
$userNotifs = $notifyObj->getUserNotifications($employeeID);
$userNotifCount = count($userNotifs);
$credit = $creditManager->getCreditDetails($employeeID); 
$account = $employeeManager->getEmployee($employeeID);


// --- 4. Feedback handling — Post/Redirect/Get ---
$actionMessage = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'submitted': $actionMessage = "Leave application submitted successfully."; break;
        case 'upload_fail': $actionMessage = "Application failed: Could not save file."; break;
    }
}

$inputs = [];
$error = [];
$displayAttachment = 'none';

// -------------------- 7. Section Routing and Logic Handling --------------------
// Default section is 'apply_leave' for employees
$section = $_GET['section'] ?? "apply_leave";

// --- Handle Apply Leave ---
$inputs = [
    'type' => 0, 'start' => '', 'days' => 0,
    'end' => '', 'reason' => '', 'uploadPath' => null
];
$sickLeaveID = 2; // Assuming 2 is the numeric LeaveTypeID for Sick Leave

if ($section === "apply_leave" && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize user input
    $inputs = [
        'type' => (int)($_POST["type"] ?? 0), 
        'start' => clean($_POST["start"] ?? ""),
        'days' => (int)($_POST["days"] ?? 0),
        'reason' => clean($_POST["reason"] ?? ""),
        'uploadPath'=> null
    ];

    if ($inputs["start"] && $inputs["days"] > 0) {
        $inputs["end"] = calculateEndDate($inputs["start"], $inputs["days"]);
    }

    // Validate inputs
    if ($inputs["type"] <= 0) $error["type"] = "Select leave type.";
    if (!$inputs["start"]) $error["start"] = "Start date required.";
    if ($inputs["days"] <= 0) $error["days"] = "Invalid days.";
    if (!$inputs["reason"]) $error["reason"] = "Enter reason.";

    // File upload validation (do not move the file yet)
    $tempFile = null;
    $targetFile = null;

    if ($inputs["type"] === $sickLeaveID && !empty($_FILES["upload"]["name"])) {
        $file = $_FILES["upload"];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        
        if (!in_array($file["type"], $allowed)) {
            $error["upload"] = "Invalid file format.";
        } elseif ($file["size"] > 5000000) {
            $error["upload"] = "Max file size is 5MB.";
        } else {
            // File is valid — prepare destination path, move later after checks
            $tempFile = $file["tmp_name"];
            $dir = "uploadedFiles";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $targetFile = "$dir/" . time() . "_" . preg_replace("/[^a-zA-Z0-9\.\-_]/", "", $file["name"]);
        }
    }

    // Insert Application logic (Only if no errors)
    if (empty($error)) {
        
        // Move uploaded file to permanent location
        $fileMoveSuccess = true;
        if ($tempFile && $targetFile) {
            if (!move_uploaded_file($tempFile, $targetFile)) {
                $fileMoveSuccess = false;
                $error['upload'] = "Failed to save file on server.";
            } else {
                $inputs['uploadPath'] = $targetFile;
            }
        }

        if ($fileMoveSuccess) {
            $success = $applicationManager->submitApplication([
                'EmployeeID' => $employeeID,
                'LeaveType' => $inputs["type"], // Numeric LeaveTypeID
                'StartDate' => $inputs["start"],
                'NumberOfDays' => $inputs["days"],
                'EndDate' => $inputs["end"],
                'Reason' => $inputs["reason"],
                'Attachment' => $inputs["uploadPath"],
            ]);

            if ($success) {
                // Redirect after successful submit
                header("Location: home.php?section=apply_leave&msg=submitted");
                exit();
            } else {
                $actionMessage = "Database submission failed.";
            }
        }
    }
}
    
// Handle display of attachment field for form repopulation/error display
if (($inputs['type'] ?? 0) == $sickLeaveID) {
    $displayAttachment = 'flex';
} else {
    $displayAttachment = 'none';
}


// -------------------- 8. Data prefetching for views --------------------
$myLog = [];
$details = null;

if ($section === 'leave_log') {
    // Used by Employee to see their history
    $myLog = $applicationManager->getEmployeeApplications($employeeID);
}

if ($section === 'view_log') {
    // Fetch single leave details using the comprehensive view log function
    $leaveID = $_POST['leave_id'] ?? $_GET['leave_id'] ?? null;
    if ($leaveID) {
        // Using the robust function that includes approval log
        $details = $applicationManager->getOneApplicationWithApproval($leaveID); 
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $section))); ?> | Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
        /* =========================================
        1. VARIABLES & RESET (MODERN SAAS RED)
        ========================================= */
        :root {
            /* --- Palette: Elegant Red SaaS --- */
            --primary-color: #b91c1c;       /* Deep Cardinal Red */
            --primary-hover: #991b1b;       /* Darker Red */
            --secondary-color: #450a0a;     /* Deep Merlot (Sidebar) */
            --accent-bg: #fef2f2;           /* Pale Red Background */
            
            /* --- Neutrals --- */
            --bg-body: #f3f4f6;             /* Light Cool Gray */
            --surface-white: #ffffff;
            --text-main: #1f2937;           /* Near Black */
            --text-muted: #6b7280;          /* Gray */
            --border-color: #e5e7eb;        /* Light Gray Border */

            /* --- Dimensions & Effects --- */
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 70px;
            --header-height: 70px;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", "Poppins", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden; /* Prevent horizontal scroll on body */
        }

        /* =========================================
        2. LAYOUT: SIDEBAR (Desktop Defaults)
        ========================================= */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            padding: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.05);
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 80px; 
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            padding: 10px;
            gap: 8px;
            overflow-y: auto;
        }

        .sidebar button {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            padding: 14px 20px;
            text-align: left;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .sidebar button i {
            min-width: 25px;
            margin-right: 10px;
            text-align: center;
        }

        .sidebar button:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            padding-left: 25px;
        }

        .sidebar button.active {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Desktop Collapsed Logic */
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed #sidebarTitle, .sidebar.collapsed span { display: none; }
        .sidebar.collapsed .sidebar-header { justify-content: center; padding: 20px 0; }
        .sidebar.collapsed button { justify-content: center; padding: 14px 0; }
        .sidebar.collapsed button i { margin-right: 0; }
        .sidebar.collapsed button:hover { padding-left: 0; background: rgba(255,255,255,0.2); }

        /* =========================================
        MOBILE TRANSFORMATION (FAB MODE)
        ========================================= */
        @media (max-width: 768px) {
            .sidebar {
                width: auto !important;
                height: auto !important;
                background: transparent !important;
                box-shadow: none !important;
                border-right: none !important;
                position: fixed !important;
                left: auto !important;
                top: auto !important;
                bottom: 25px !important;
                right: 25px !important;
                display: flex !important;
                flex-direction: column-reverse !important; 
                align-items: flex-end !important;
                z-index: 9999 !important;
            }

            .sidebar.collapsed { width: auto !important; }
            #sidebarTitle { display: none !important; }
            .sidebar-header { padding: 0 !important; min-height: auto !important; background: transparent !important; display: block !important; }

            .toggle-btn {
                width: 60px !important;
                height: 60px !important;
                border-radius: 50% !important;
                background: var(--primary-color) !important;
                color: white !important;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4) !important; 
                font-size: 24px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 0 !important;
                margin: 0 !important;
                cursor: pointer;
                transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            .sidebar.open .toggle-btn {
                background: #ef4444 !important;
                transform: rotate(90deg);
            }

            .sidebar-menu {
                padding: 0 !important;
                margin-bottom: 15px !important;
                gap: 12px;
                display: flex !important;
                flex-direction: column !important;
                opacity: 0;
                visibility: hidden;
                transform: translateY(20px);
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            .sidebar.open .sidebar-menu { opacity: 1; visibility: visible; transform: translateY(0); }

            .sidebar button {
                width: auto !important;
                height: 48px !important;
                background: white !important;
                color: #333 !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
                border-radius: 25px !important;
                padding: 0 20px !important;
                justify-content: flex-end !important;
                border: 1px solid #eee !important;
                display: flex !important;
                align-items: center !important;
            }
            
            .sidebar button span { display: inline-block !important; margin-left: 10px; font-size: 0.85rem; white-space: nowrap; }
            .sidebar button i { margin-right: 0 !important; color: var(--primary-color); }
            .sidebar button.active { background: var(--primary-color) !important; }
            .sidebar button.active span, .sidebar button.active i { color: white !important; }

            #contentWrapper { margin-left: 0 !important; }
        }

        /* FAB Overlay */
        .fab-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .sidebar.open ~ .fab-overlay, body.fab-open .fab-overlay {
                display: block;
                opacity: 1;
                pointer-events: auto;
            }
        }

        /* =========================================
        3. LAYOUT: HEADER & CONTENT
        ========================================= */
        #contentWrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed ~ #contentWrapper { margin-left: var(--sidebar-width-collapsed); }

        #mainHeader {
            height: var(--header-height);
            background: var(--surface-white);
            padding: 0 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        #mainHeader h1 {
            font-size: 1.25rem;
            color: var(--text-main);
            font-weight: 700;
        }

        .top-bar { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .top-bar-right { display: flex; align-items: center; gap: 20px; }

        .icon-button-wrapper button {
            background: transparent;
            border: none;
            font-size: 1.1rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
        }
        .icon-button-wrapper button:hover { background: var(--bg-body); color: var(--primary-color); }
        .logout-icon-btn:hover { color: #dc2626 !important; background: #fef2f2 !important; }

        .profile-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            border: 2px solid var(--primary-color);
            padding: 2px;
        }

        .main-content {
            padding: 40px;
            max-width: 1600px;
            width: 100%;
            margin: 0 auto;
            animation: fadeUp 0.5s ease-out;
        }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* =========================================
        4. DASHBOARD & CHARTS
        ========================================= */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .card {
            background: var(--surface-white);
            border-radius: var(--border-radius);
            padding: 25px 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary-color); }
        .card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--primary-color); opacity: 0; transition: opacity 0.3s; }
        .card:hover::before { opacity: 1; }

        .card h3 { color: var(--text-muted); margin-bottom: 10px; font-size: 0.85rem; text-transform: uppercase; font-weight: 600; }
        .card p { font-size: 2.2rem; font-weight: 800; color: var(--text-main); }

        #chart-section { width: 100%; max-width: 1200px; margin: 40px 0; display: flex; flex-direction: column; gap: 30px; }
        .chart-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; }
        .chart-card { background: var(--surface-white); border-radius: var(--border-radius); box-shadow: var(--shadow-md); padding: 25px; border: 1px solid var(--border-color); }
        .chart-card h3 { text-align: left; color: var(--text-main); font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; }
        canvas { width: 100% !important; height: auto !important; max-height: 350px; }

        /* Clock */
        #flip-clock { display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 30px; }
        #date-text { text-align: center; color: var(--text-muted); margin-bottom: 15px; font-weight: 500; }
        .digit { background: var(--text-main); color: #fff; padding: 12px 14px; font-size: 1.8rem; font-weight: 700; border-radius: 6px; min-width: 50px; text-align: center; box-shadow: var(--shadow-md); }
        .colon { font-size: 1.8rem; color: var(--primary-color); font-weight: 900; }
        .flip { animation: flipAnim 0.6s cubic-bezier(0.4, 2.5, 0.55, 1); }
        @keyframes flipAnim { 0% { transform: rotateX(0deg); } 100% { transform: rotateX(360deg); } }

        /* =========================================
        5. TABLES (Desktop Style)
        ========================================= */
        table {
            width: 100%;
            border-collapse: separate; 
            border-spacing: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-top: 25px;
            background: var(--surface-white);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        th { background: #f9fafb; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; padding: 16px 24px; border-bottom: 1px solid var(--border-color); text-align: left; }
        td { padding: 16px 24px; border-bottom: 1px solid var(--border-color); color: var(--text-main); font-size: 0.9rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: var(--accent-bg); }

        .status { display: inline-block; padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status.pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .status.inactive, .status.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
        .status.active, .status.approved { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }

        /* =========================================
        6. FORMS & INPUTS
        ========================================= */
        .apply-card, .account-info, #evaluate-section, .leave-credit-details {
            background: var(--surface-white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow-md);
            max-width: 800px;
            margin: 0 auto 30px;
            border: 1px solid var(--border-color);
        }

        label { font-weight: 600; color: var(--text-main); font-size: 0.9rem; margin-bottom: 8px; display: block; }
        input, select, textarea {
            width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px;
            font-size: 0.95rem; background-color: #f9fafb; transition: all 0.2s; margin-bottom: 20px; color: var(--text-main);
        }
        input:focus, select:focus, textarea:focus { background: #fff; border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 4px rgba(185, 28, 28, 0.1); }

        button {
            background: var(--primary-color); color: white; border: none; border-radius: 8px;
            padding: 12px 24px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(185, 28, 28, 0.3);
        }
        button:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(185, 28, 28, 0.4); }

        .deactivate-btn { background: #15803d; }
        .deactivate-btn:hover { background: #166534; }
        
        .leave-credit-details h3 { color: var(--primary-color); font-size: 1.4rem; margin-bottom: 20px; }
        .leave-credit-details { position: relative; overflow: hidden; }
        .leave-credit-details::after { content: ""; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: var(--primary-color); opacity: 0.05; border-radius: 50%; }

        .notice { background: #eff6ff; border-left: 4px solid #2563eb; color: #1e40af; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 0.95rem; }
        .alert { background: #fef2f2; border-left: 4px solid #dc2626; color: #991b1b; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        #attachmentDiv { background: #f0f9ff; border: 1px dashed #0ea5e9; padding: 20px; border-radius: 8px; text-align: center; color: #0284c7; margin-bottom: 20px; }
    /* =========================================
        7. GLOBAL RESPONSIVENESS (MOBILE TWEAKS)
        ========================================= */
        @media (max-width: 768px) {
            
            /* --- SIDEBAR CONTAINER FIX --- */
            .sidebar {
                width: auto !important;
                height: auto !important;
                background: transparent !important;
                box-shadow: none !important;
                border-right: none !important;
                position: fixed !important;
                left: auto !important;
                top: auto !important;
                bottom: 25px !important;
                right: 25px !important;
                display: flex !important;
                flex-direction: column-reverse !important; 
                align-items: flex-end !important;
                z-index: 9999 !important;
                
                /* Allow clicks to pass through empty FAB area; enable only buttons below */
                pointer-events: none !important; 
            }

            /* Re-enable clicks only on the actual buttons */
            .toggle-btn, .sidebar-menu button {
                pointer-events: auto !important;
            }

            /* --- CONTENT BUFFER --- */
            .main-content {
                padding: 15px !important;
                /* Adds space at bottom so you can scroll content ABOVE the FAB */
                padding-bottom: 100px !important; 
            }

            /* Shrink Header */
            #mainHeader {
                padding: 0 15px !important;
                height: 60px;
            }
            #mainHeader h1 { font-size: 1rem; }
            .profile-avatar { width: 32px; height: 32px; }

            /* Shrink Forms & Cards */
            .apply-card, .account-info, #evaluate-section, .leave-credit-details, .card, .chart-card {
                padding: 20px !important;
                border-radius: 8px;
            }

            /* Stack Profile Info */
            #evaluate-section .profile-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 15px;
            }
            #evaluate-section strong { display: block; width: 100%; margin-bottom: 2px; }
            #evaluate-section .info { width: 100%; text-align: center; }

            /* Grid Stacking */
            .dashboard-cards { grid-template-columns: 1fr; }
            .chart-container { grid-template-columns: 1fr; }
            .row-split { flex-direction: column; gap: 0; }

            /* Font Sizes */
            .card p { font-size: 1.8rem; }
            .digit { font-size: 1.4rem; padding: 8px 10px; min-width: 40px; }
            
            /* Adjust Chart Size */
            canvas { max-height: 250px !important; }

            /* --- REST OF FAB STYLING --- */
            .sidebar.collapsed { width: auto !important; }
            #sidebarTitle { display: none !important; }
            .sidebar-header { padding: 0 !important; min-height: auto !important; background: transparent !important; display: block !important; }

            .toggle-btn {
                width: 60px !important;
                height: 60px !important;
                border-radius: 50% !important;
                background: var(--primary-color) !important;
                color: white !important;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4) !important; 
                font-size: 24px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 0 !important;
                margin: 0 !important;
                cursor: pointer;
                transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            .sidebar.open .toggle-btn {
                background: #ef4444 !important;
                transform: rotate(90deg);
            }

            .sidebar-menu {
                padding: 0 !important;
                margin-bottom: 15px !important;
                gap: 12px;
                display: flex !important;
                flex-direction: column !important;
                opacity: 0;
                visibility: hidden;
                transform: translateY(20px);
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            .sidebar.open .sidebar-menu { opacity: 1; visibility: visible; transform: translateY(0); }

            .sidebar button {
                width: auto !important;
                height: 48px !important;
                background: white !important;
                color: #333 !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
                border-radius: 25px !important;
                padding: 0 20px !important;
                justify-content: flex-end !important;
                border: 1px solid #eee !important;
                display: flex !important;
                align-items: center !important;
            }
            
            .sidebar button span { display: inline-block !important; margin-left: 10px; font-size: 0.85rem; white-space: nowrap; }
            .sidebar button i { margin-right: 0 !important; color: var(--primary-color); }
            .sidebar button.active { background: var(--primary-color) !important; }
            .sidebar button.active span, .sidebar button.active i { color: white !important; }

            #contentWrapper { margin-left: 0 !important; }

            /* --- TABLE CARD VIEW --- */
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { 
                background: #fff; border: 1px solid var(--border-color); border-radius: 12px;
                margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 15px;
            }
            td { 
                border: none; border-bottom: 1px solid #f3f4f6; position: relative;
                padding-left: 50% !important; padding-top: 10px !important; padding-bottom: 10px !important;
                text-align: right; display: flex; align-items: center; justify-content: flex-end; min-height: 40px;
            }
            td:last-child { border-bottom: none; justify-content: center; padding-left: 0 !important; margin-top: 10px; }
            
            /* Labels */
            td::before { 
                position: absolute; top: 50%; left: 15px; transform: translateY(-50%);
                width: 45%; padding-right: 10px; white-space: nowrap; text-align: left;
                font-weight: 700; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase;
            }
            td:nth-of-type(1)::before { content: "Employee"; }
            td:nth-of-type(2)::before { content: "Leave Type"; }
            td:nth-of-type(3)::before { content: "Start Date"; }
            td:nth-of-type(4)::before { content: "End Date"; }
            td:nth-of-type(5)::before { content: "Status"; }
            td:last-child::before { content: ""; display: none; }
            
        }
        /* =========================================
        EVALUATE SECTION
        ========================================= */
        #evaluate-section {
            width: 100%;
            max-width: 900px;
            margin: 40px auto;
            background: var(--surface-white);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 40px 60px;
            font-family: "Poppins", sans-serif;
            box-shadow: var(--shadow-md);
        }

        #evaluate-section h1 {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }

        #evaluate-section h2 {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            margin-top: 10px;
            margin-bottom: 30px;
            color: var(--text-muted);
        }

        #evaluate-section .profile-section {
            display: flex;
            align-items: flex-start;
            gap: 40px;
            margin-bottom: 30px;
        }

        #evaluate-section img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--surface-white); 
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        #evaluate-section .info { flex: 1; }
        
        #evaluate-section p {
            font-size: 16px;
            margin: 8px 0;
            line-height: 1.6;
            color: var(--text-main);
        }

        #evaluate-section strong {
            display: inline-block;
            width: 160px;
            font-weight: 600;
            color: var(--primary-color);
        }

        #evaluate-section .section-title {
            text-align: center;
            font-weight: 700;
            margin-top: 35px;
            margin-bottom: 20px;
            font-size: 18px;
            color: var(--secondary-color);
            border-bottom: 2px solid var(--accent-bg);
            padding-bottom: 10px;
            display: inline-block;
            min-width: 200px;
        }

        #evaluate-section form {
            margin-top: 25px;
            border-top: 1px solid var(--border-color);
            padding-top: 25px;
        }

        #evaluate-section label {
            font-weight: 600;
            display: inline-block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        #evaluate-section select,
        #evaluate-section textarea {
            width: 100%;
            font-size: 15px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            resize: none;
            margin-top: 4px;
            background-color: #f9fafb;
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        #evaluate-section select:focus,
        #evaluate-section textarea:focus {
            background-color: #fff;
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(185, 28, 28, 0.1);
        }

        #evaluate-section button {
            margin-top: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            box-shadow: 0 2px 4px rgba(185, 28, 28, 0.2);
        }

        #evaluate-section button:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(185, 28, 28, 0.3);
        }

    </style>
    <style>
        /* =========================================
    NOTIFICATION SYSTEM CSS
    ========================================= */
    .icon-button-wrapper {
        position: relative;
        display: inline-block;
    }

    .icon-button-wrapper button {
        background: transparent;
        border: none;
        font-size: 1.2rem;
        color: var(--text-muted);
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        position: relative;
        transition: background 0.2s;
    }

    .icon-button-wrapper button:hover {
        background: var(--bg-body);
        color: var(--primary-color);
    }

    /* The Red Count Badge */
    .notif-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #ef4444;
        color: white;
        font-size: 0.7rem;
        font-weight: bold;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        animation: bounceIn 0.3s ease-out;
    }

    /* The Dropdown Container */
    .notif-dropdown {
        display: none;
        position: absolute;
        top: 120%;
        right: -10px;
        width: 360px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
        border: 1px solid var(--border-color);
        z-index: 2000;
        overflow: hidden;
        animation: dropFade 0.2s ease-out;
    }

    .notif-dropdown.show { display: block; }

    /* Dropdown Header */
    .notif-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .notif-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }

    /* Dropdown List Area */
    .notif-list {
        max-height: 400px;
        overflow-y: auto;
    }

    /* Individual Item */
    .notif-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 20px;
        border-bottom: 1px solid #f9fafb;
        text-decoration: none;
        color: inherit;
        transition: background 0.2s;
        position: relative;
    }

    .notif-item:hover { background: #f9fafb; }
    .notif-item.unread { background: #fef2f2; } /* Red tint for pending items */
    .notif-item.unread::before {
        content: '';
        position: absolute;
        left: 8px; top: 50%; transform: translateY(-50%);
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--primary-color);
    }

    /* Icon/Image Box */
    .notif-icon-box {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        margin-right: 15px;
        position: relative;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .notif-icon-box img { width: 100%; height: 100%; object-fit: cover; }

    /* Small Icon Overlay (e.g., Document or User icon) */
    .notif-type-icon {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        color: white;
        border: 2px solid white;
    }

    .notif-content { flex: 1; }
    .notif-content h4 { font-size: 0.9rem; font-weight: 600; margin: 0 0 2px 0; line-height: 1.2; }
    .notif-content p { font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.3; }
    .notif-time { font-size: 0.75rem; color: #9ca3af; margin-top: 4px; display: block; font-weight: 500; }

    @media (max-width: 480px) {
        .notif-dropdown {
            position: fixed;
            top: 70px; 
            left: 10px;
            right: 10px;
            width: auto;
            max-width: none;
        }
    }

    @keyframes dropFade { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes bounceIn { 0% { transform: scale(0); } 60% { transform: scale(1.2); } 100% { transform: scale(1); } }
    </style>
    <style>
        /* LOGOUT MODAL STYLES */
    #logoutModal {
        display: none; /* Hidden by default */
        position: fixed !important; /* Force fixed positioning */
        z-index: 99999 !important; /* Sit on top of EVERYTHING */
        left: 0;
        top: 0;
        width: 100vw; /* Full Viewport Width */
        height: 100vh; /* Full Viewport Height */
        background-color: rgba(0, 0, 0, 0.6); /* Dark dim */
        backdrop-filter: blur(4px); /* Modern blur effect */
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    /* When JS sets display: flex, this centers it */
    #logoutModal[style*="display: flex"] {
        display: flex !important;
    }

    .logout-card {
        background: white;
        width: 90%;
        max-width: 400px;
        border-radius: 16px;
        padding: 40px 30px;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        position: relative;
        animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .logout-icon-container {
        width: 80px;
        height: 80px;
        background-color: #fee2e2;
        color: #dc2626;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px auto;
        font-size: 2rem;
        border: 4px solid #fff;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .logout-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1f2937;
        margin-bottom: 10px;
    }

    .logout-desc {
        color: #6b7280;
        font-size: 0.95rem;
        margin-bottom: 30px;
        line-height: 1.5;
    }

    .logout-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .btn-cancel {
        background: #f3f4f6;
        color: #4b5563;
        border: 1px solid #e5e7eb;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        flex: 1;
    }
    .btn-cancel:hover { background: #e5e7eb; }

    .btn-signout {
        background: #dc2626;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        flex: 1;
        box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);
    }
    .btn-signout:hover { background: #b91c1c; transform: translateY(-1px); }

    @keyframes popIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
</style>

</head>
<body>
    <div id="fabOverlay" class="fab-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="mainSidebar">
    
    <div class="sidebar-header">
        <h3 id="sidebarTitle">User</h3>
        <button type="button" id="sidebarToggle" class="toggle-btn">
            <i class="fas fa-bars" id="toggleIcon"></i>
        </button>
    </div>

    <form method="get" action="" class="sidebar-menu">
        <button type="submit" name="section" value="apply_leave" class="<?= $section === 'apply_leave' ? 'active' : '' ?>">
            <i class="fas fa-paper-plane"></i> <span>Apply Leave</span>
        </button>
        <button type="submit" name="section" value="leave_log" class="<?= $section === 'leave_log' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> <span>Leave Logs</span>
        </button>
        <button type="submit" name="section" value="account" class="<?= $section === 'account' ? 'active' : '' ?>">
            <i class="fas fa-user-cog"></i> <span>Account</span>
        </button>
    </form>
</div>
<script>
    // --- Sidebar and Toggle Logic (Unmodified) ---
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = toggleBtn.querySelector('i'); 
    const overlay = document.getElementById('fabOverlay'); 

    if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
    }

    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault(); 
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            sidebar.classList.toggle('open');
            const isOpen = sidebar.classList.contains('open');
            
            if (isOpen) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times');
                
                if (overlay) {
                    overlay.style.display = 'block';
                    setTimeout(() => overlay.style.opacity = '1', 10);
                }
            } else {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
                
                if (overlay) {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            }
        } else {
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
            }
        }
    });

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            toggleIcon.classList.remove('fa-times');
            toggleIcon.classList.add('fa-bars');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        });
    }
</script>

<div id="contentWrapper">
    <header id="mainHeader">
        <div class="top-bar">
            <div class="top-bar-left">
                <h1 id="pageTitle"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $section))) ?></h1> 
            </div>
            <div class="top-bar-right">
                <div class="icon-button-wrapper">
    <button onclick="toggleNotifications()" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($userNotifCount > 0): ?>
            <span class="notif-badge"><?= $userNotifCount ?></span>
        <?php endif; ?>
    </button>

    <div id="notificationDropdown" class="notif-dropdown">
        <div class="notif-header">
            <h3>Your Notifications</h3>
        </div>
        <div class="notif-list">
            <?php if (!empty($userNotifs)): ?>
                <?php foreach ($userNotifs as $notif): ?>
                    <a href="home.php?section=leave_log" class="notif-item">
                        <div class="notif-icon-box" style="display:flex; align-items:center; justify-content:center; background:transparent; box-shadow:none; border:none;">
                            <div style="width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center;" class="<?= $notif['bg_color'] ?> <?= $notif['color'] ?>">
                                <i class="fas <?= $notif['icon'] ?>"></i>
                            </div>
                        </div>

                        <div class="notif-content">
                            <h4 class="<?= $notif['color'] ?>"><?= htmlspecialchars($notif['title']) ?></h4>
                            <p><?= htmlspecialchars($notif['message']) ?></p>
                            <p style="font-size:0.75rem; color:#666; margin-top:2px; font-style:italic;">
                                <?= htmlspecialchars($notif['sub_text']) ?>
                            </p>
                            <span class="notif-time"><?= $notif['time_ago'] ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:30px; text-align:center; color:#9ca3af;">
                    <i class="far fa-folder-open" style="font-size:2rem; margin-bottom:10px;"></i>
                    <p>No recent activity</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
                <div class="icon-button-wrapper">
                    <button id="logoutIconBtn" class="logout-icon-btn" title="Sign Out"><i class="fas fa-sign-out-alt"></i></button>
                </div>
                <div class="profile-avatar" style="background: #ccc;">
                    <img src="<?= htmlspecialchars($account['profilePic'] ?? 'assets/image/default-avatar.png') ?>" alt="Profile Picture" 
                            style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                </div>
            </div>
        </div>
    </header>
    <script>
// --- Toggle Notifications ---
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        const badge = document.querySelector('.notif-badge');

        dropdown.classList.toggle('show');

        // If opening the dropdown...
        if (dropdown.classList.contains('show')) {
            
            // 1. Hide Badge Immediately (Visual Feedback)
            if (badge) {
                badge.style.display = 'none';
            }

            // 2. Send Signal to THIS SAME PAGE
            fetch('adminhome.php?ajax_action=mark_read') // <--- Changed URL
                .then(response => response.json())
                .then(data => {
                    console.log("Read status updated:", data.success);
                })
                .catch(error => console.error('Error:', error));
        }
    }
    </script>

   <div class="main-content" id="mainContent">
        <?php if (!empty($actionMessage)): ?>
            <div class="notice"><?= htmlspecialchars($actionMessage) ?></div>
        <?php endif; ?>

        <?php 
        // --- 9. Core Routing ---
        $pageFile = "pages/{$section}.php";
        
        if (file_exists($pageFile)) {
            require_once $pageFile;
        } else {
            echo "<h1>Section Not Found: " . htmlspecialchars($section) . "</h1>";
        }
        ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="logoutModal">
    <div class="logout-card">
        <div class="logout-icon-container">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3 class="logout-title">Signing Out</h3>
        <p class="logout-desc">Are you sure you want to end your session?</p>
        
        <div class="logout-actions">
            <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <button class="btn-signout" onclick="confirmLogout()">Sign Out</button>
        </div>
    </div>
</div>

<script>
    // --- LOGOUT MODAL LOGIC ---
    document.getElementById("logoutIconBtn").addEventListener("click", function(e) {
        e.preventDefault();
        document.getElementById('logoutModal').style.display = 'flex'; // Shows overlay
    });

    function closeLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
    }

    function confirmLogout() {
        // Targets the logout logic at the very top of the home.php file
        window.location.href = "home.php?action=logout"; 
    }

    // Close on Outside Click
    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLogoutModal();
        }
    });
</script>
</body>
</html>