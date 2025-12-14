<?php
ob_start(); // Start output buffering
session_start();

// --- SECURITY: HANDLE LOGOUT ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    
    // 1. Unset all session variables (The data)
    $_SESSION = array();

    // 2. Expire the Session Cookie (The browser key)
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 3. Destroy the Session file on server
    session_destroy();

    // 4. Refresh to clean URL
    header("Location: index.php");
    exit();
}


// --- REQUIRED FILES ---
require_once "dbRelated/db_connect.php";
require_once "dbRelated/operation.php"; // Contains PasswordResetManager, etc.
require_once "dbRelated/EmailSender.php";

// --- Initialize Managers ---
// Assuming all necessary classes (LeaveCreditManager, EmployeeManager, etc.) are available
$creditManager = new LeaveCreditManager();
$employeeManager = new EmployeeManager();
$applicationManager = new LeaveApplicationManager(); 
$reg = new AdminRegistration(); 
$acc = new AccountSetup(); 
$emailSender = new EmailSender(); 
$pwdReset = new PasswordResetManager(); 

// --- Initialize Variables ---
$employeeID = trim($_POST['id'] ?? $_SESSION['tempEmployeeID'] ?? ""); 
$email = trim($_POST['email'] ?? "");

// Login/Registration State Variables
$idError = "";
$emailError = "";
$passError = "";
$uname = "";
$accountData = null; 
$checkDisplay = "flex"; 
$emailDisplay = "none";
$loginDisplay = "none";
$forgotDisplay = "none"; 

// Contact Form State Variables
$contactEmail = trim($_POST['contact_email'] ?? "");
$contactMsg = trim($_POST['message'] ?? "");
$contactError = "";
$contactSuccess = "";

// Forgot Password State Variables (OTP-SPECIFIC)
$forgotEmail = '';
$forgotOTP = trim($_POST['otp_code'] ?? ''); // Input for OTP code
$forgotError = "";
$forgotSuccess = "";
$otpStage = false; // Flag: Is the user past the email input stage?

// Flags to trigger modal auto-open via JS
$shouldOpenLoginModal = false;
$shouldOpenContactModal = false;

// --- OTP SESSION CHECK ---
// If an email is stored in the session, we are in the OTP entry stage (State 2)
if (isset($_SESSION['otp_reset_email'])) {
    $forgotEmail = $_SESSION['otp_reset_email'];
    $otpStage = true;
}


// --- MAIN POST HANDLER ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $action = $_POST["action"] ?? "";

    /* -----------------------------------------
        CHECK ID (Step 1)
    ----------------------------------------- */
    if ($action === "check") {
        $shouldOpenLoginModal = true;
        if (empty($employeeID)) {
            $idError = "Please enter your Faculty ID.";
        } else {
            $existingData = $reg->checkIdExists($employeeID); 

            if (!$existingData) {
                $_SESSION['tempEmployeeID'] = $employeeID; 
                $checkDisplay = "none";
                $emailDisplay = "flex";
            } else {
                $status = $existingData["status"]; 
                if ($status === "Active") {
                    $_SESSION['tempEmployeeID'] = $employeeID; 
                    $accountData = $acc->getAccountByEmployeeID($employeeID);
                    
                    if (!$accountData) {
                        $_SESSION['employeeID'] = $employeeID;
                         $idError = "Your account is active but credentials are not set. <a href='accountSetup/register.php' class='underline font-bold'>Please complete registration.</a>";
                         $checkDisplay = "flex"; 
                    } else {
                         $uname = $accountData['Username'] ?? ""; 
                         $checkDisplay = "none";
                         $loginDisplay = "flex";
                    }
                } elseif ($status === "Pending") {
                    $idError = "Your account request is still pending admin approval.";
                } elseif ($status === "Inactive") {
                    $idError = "This account is deactivated. Please contact the administrator.";
                }
            }
        }
    }

    /* -----------------------------------------
        REGISTER PENDING ACCOUNT (Step 2)
    ----------------------------------------- */
    elseif ($action === "notify") {
        $shouldOpenLoginModal = true;
        $checkDisplay = "none";
        $emailDisplay = "flex";

        $email = trim($_POST["email"] ?? "");

        if (empty($email)) {
            $emailError = "Please input an email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailError = "Invalid email format.";
        } else {
            $employeeID = trim($_POST["id"] ?? $_SESSION['tempEmployeeID'] ?? "");
            if ($reg->registerPending($employeeID, $email)) {
                $emailSender->sendRegistrationRequest('wmsu.leave.system@gmail.com', $employeeID, $email);
                $idError = "Your request has been sent to admin. Please wait for activation.";
                $checkDisplay = "flex";
                $emailDisplay = "none";
                unset($_SESSION['tempEmployeeID']);
            } else {
                $emailError = "Something went wrong. The ID might already be registered.";
            }
        }
    }

    /* -----------------------------------------
        LOGIN FOR ACTIVE USERS (Step 3)
    ----------------------------------------- */
    elseif ($action === "login") {
        $shouldOpenLoginModal = true;
        $employeeID = trim($_POST["id"] ?? $_SESSION['tempEmployeeID'] ?? "");
        $passInput = trim($_POST["pass"] ?? "");

        $loginDisplay = "flex"; 
        $checkDisplay = "none";
        
        if (empty($employeeID) || empty($passInput)) {
            $passError = "Please provide both ID and password.";
        } else {
            $accountData = $acc->getAccountByEmployeeID($employeeID);
            if (!$accountData) {
                $passError = "Invalid ID or account not set up.";
            } elseif ($accountData['Password'] !== null && !password_verify($passInput, $accountData['Password'])) {
                $passError = "Incorrect password.";
            } else {
                session_regenerate_id(true);
                $_SESSION['employeeID'] = $accountData['EmployeeID'];
                $_SESSION['email'] = $accountData['Email'];
                $_SESSION['username'] = $accountData['Username'];
                $_SESSION['Role'] = $accountData["Role"];
                unset($_SESSION['tempEmployeeID']);
                $creditManager->updateAccruedCredits($accountData['EmployeeID'], $employeeManager, $applicationManager);

                if ($accountData["Role"] === "Admin") {
                    header("Location: adminhome.php");
                } else {
                    header("Location: home.php");
                }
                exit;
            }
        }
        if ($loginDisplay === "flex" && $accountData) {
            $uname = $accountData['Username'] ?? "";
        }
    }

    /* -----------------------------------------
        SWITCH TO FORGOT PASSWORD VIEW
    ----------------------------------------- */
    elseif ($action === "show_forgot") {
        // Clear old session data if we switch back to forgot password
        if (isset($_SESSION['otp_reset_email'])) {
            unset($_SESSION['otp_reset_email']);
            $otpStage = false;
        }
        $shouldOpenLoginModal = true;
        $checkDisplay = "none";
        $loginDisplay = "none";
        $forgotDisplay = "flex"; 
    }

    /* -----------------------------------------------------
        PROCESS FORGOT PASSWORD REQUEST (OTP STAGE 1)
        User submits email.
    ----------------------------------------------------- */
    elseif ($action === "forgot_password_email") { 
        $shouldOpenLoginModal = true;
        $checkDisplay = "none";
        $loginDisplay = "none";
        $forgotDisplay = "flex";

        $forgotEmail = trim($_POST["forgot_email"] ?? "");

        if (empty($forgotEmail) || !filter_var($forgotEmail, FILTER_VALIDATE_EMAIL)) {
            $forgotError = "The email provided is not a valid email address format.";
        } else {
            // 1. Initiate Reset (Generates OTP and stores it in DB)
            $resetData = $pwdReset->initiateReset($forgotEmail); 

            if ($resetData) {
                // 2. Send the OTP email
                if ($emailSender->sendOTP($forgotEmail, $resetData['otp'])) { 
                    
                    // Success: Store the email in session and move to OTP entry stage (State 2)
                    $_SESSION['otp_reset_email'] = $forgotEmail;
                    $otpStage = true; // Sets state flag
                    
                    // Set the success message for display
                    $forgotSuccess = "A One-Time Password (OTP) has been sent to <strong>" . htmlspecialchars($forgotEmail) . "</strong>. Please check your spam folder.";
                    
                    $forgotError = ""; 
                    $forgotDisplay = "flex"; 
                    $shouldOpenLoginModal = true;
                    
                } else {
                    $forgotError = "System error: Code generated, but email failed to send. Please contact HR support.";
                }
            } else {
                // Fails if email not found or account not active
                $forgotError = "We could not find an active account for that email address.";
            }
        }
    }

    /* -----------------------------------------------------
        PROCESS RESET PASSWORD (OTP STAGE 2)
        User submits OTP and new passwords.
    ----------------------------------------------------- */
    elseif ($action === "reset_password_otp" && $otpStage) {
        $shouldOpenLoginModal = true;
        $checkDisplay = "none";
        $loginDisplay = "none";
        $forgotDisplay = "flex";
        
        $pass1 = trim($_POST['pass1'] ?? '');
        $pass2 = trim($_POST['pass2'] ?? '');

        if (empty($forgotOTP) || empty($pass1) || empty($pass2)) {
            $forgotError = "All fields (OTP and passwords) are required.";
        } elseif ($pass1 !== $pass2) {
            $forgotError = "New passwords do not match.";
        } elseif (strlen($pass1) < 6) {
            $forgotError = "Password must be at least 6 characters long.";
        } else {
            // 1. Attempt to update the password using the OTP
            if ($pwdReset->updatePassword($forgotOTP, $pass1)) {
                
                // Success: Clean up session and display final success message
                unset($_SESSION['otp_reset_email']);
                $otpStage = false; // MUST be false to show the final "Go to Login" button
                $forgotSuccess = "Password successfully reset! You can now log in with your new password.";

            } else {
                $forgotError = "Invalid OTP or code has expired (10 minutes). Please check the code or request a new one.";
            }
        }
    }


    /* -----------------------------------------
        CONTACT HR SUPPORT
    ----------------------------------------- */
    elseif ($action === "contact_hr") {
        $shouldOpenContactModal = true;
        if (empty($contactEmail) || empty($contactMsg)) {
            $contactError = "Please provide your Email and a message.";
        } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $contactError = "Please provide a valid email address.";
        } else {
            if ($emailSender->sendContactInquiry($contactEmail, $contactMsg)) {
                $contactSuccess = "Your inquiry has been sent successfully.";
                $contactEmail = ""; $contactMsg = "";
            } else {
                $contactError = "Failed to send email.";
            }
        }
    }
}

// FIX: If refreshing on the login screen, re-fetch username
if ($loginDisplay === "flex" && !empty($_SESSION['tempEmployeeID'])) {
    if (!$accountData) {
        $accountData = $acc->getAccountByEmployeeID($_SESSION['tempEmployeeID']);
    }
    $uname = $accountData['Username'] ?? "User";
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU Faculty Leave Application System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        wmsu: {
                            main: '#800000', dark: '#4a0000', light: '#a61b1b', 
                            surface: '#fff5f5', text: '#2d0a0a' 
                        }
                    },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.8s ease-out forwards', 'bounce-slow': 'bounce 3s infinite', 'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeInUp: { '0%': { opacity: '0', transform: 'translateY(20px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } }
                    }
                }
            }
        }
    </script>
    <style>
        .hero-bg {
            background-image: linear-gradient(to bottom, rgba(74, 0, 0, 0.85), rgba(128, 0, 0, 0.8), rgba(128, 0, 0, 0.95)), url('assets/image/wmsuentrance.jpeg');
            background-size: cover; background-position: center; background-attachment: fixed;
        }
        .glass-nav { background: rgba(128, 0, 0, 0.95); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .glass-nav.scrolled { background: rgba(128, 0, 0, 0.85); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); }
        .text-glow { text-shadow: 0 0 20px rgba(255,255,255,0.3); }
        .no-scroll { overflow: hidden; }
        .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s ease-out; }
        .reveal.active { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body class="font-sans text-gray-800 antialiased bg-gray-50 selection:bg-wmsu-main selection:text-white">

    <nav id="navbar" class="fixed w-full top-0 z-50 transition-all duration-300 glass-nav border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center space-x-3 group cursor-pointer">
                    <div class="relative">
                        <div class="absolute inset-0 bg-red-400 rounded-full blur opacity-20 group-hover:opacity-40 transition-opacity duration-300"></div>
                        <img src="assets/image/logo.png" alt="WMSU Logo" class="w-10 h-10 rounded-full border-10 border-transparent group-hover:border-red-200 transition-all shadow-lg object-cover flex-shrink-0">
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-lg leading-none tracking-wide text-white group-hover:text-red-100 transition-colors">Western Mindanao State Universiy</span>
                        <span class="text-[10px] text-red-200 uppercase tracking-[0.2em] font-medium group-hover:text-white transition-colors">Leave Application System</span>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-center space-x-8">
                        <a href="#home" class="text-sm font-medium text-white hover:text-red-200 transition-colors relative after:content-[''] after:absolute after:w-full after:scale-x-0 after:h-0.5 after:bottom-0 after:left-0 after:bg-red-200 after:origin-bottom-right after:transition-transform after:duration-300 hover:after:scale-x-100 hover:after:origin-bottom-left py-1">Home</a>
                        <a href="#about" class="text-sm font-medium text-white/90 hover:text-white transition-colors py-1">About</a>
                        <a href="#academics" class="text-sm font-medium text-white/90 hover:text-white transition-colors py-1">Policy</a>
                        <a href="#contact" class="text-sm font-medium text-white/90 hover:text-white transition-colors py-1">Contact</a>
                        <button onclick="toggleLoginModal()" class="ml-4 bg-white text-wmsu-main px-6 py-2.5 rounded-full text-sm font-bold shadow-lg shadow-black/20 hover:bg-red-50 hover:shadow-xl hover:shadow-red-900/20 transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2 group">
                            <i data-lucide="log-in" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Login
                        </button>
                    </div>
                </div>
                <div class="-mr-2 flex md:hidden">
                    <button type="button" onclick="toggleMobileMenu()" class="inline-flex items-center justify-center p-2 rounded-md text-white hover:bg-white/10 focus:outline-none transition-colors">
                        <span class="sr-only">Open main menu</span>
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-wmsu-dark border-t border-white/10 backdrop-blur-xl absolute w-full">
            <div class="px-4 pt-2 pb-6 space-y-2">
                <a href="#home" class="block px-3 py-3 rounded-md text-base font-medium text-white hover:bg-white/10 border-l-4 border-transparent hover:border-white transition-all">Home</a>
                <a href="#about" class="block px-3 py-3 rounded-md text-base font-medium text-red-100 hover:bg-white/10 border-l-4 border-transparent hover:border-white transition-all">About</a>
                <a href="#academics" class="block px-3 py-3 rounded-md text-base font-medium text-red-100 hover:bg-white/10 border-l-4 border-transparent hover:border-white transition-all">Policy</a>
                <a href="#contact" class="block px-3 py-3 rounded-md text-base font-medium text-red-100 hover:bg-white/10 border-l-4 border-transparent hover:border-white transition-all">Contact</a>
                <button onclick="toggleLoginModal()" class="w-full mt-4 px-3 py-3 rounded-md text-base font-bold bg-white text-wmsu-main text-center shadow-md active:scale-95 transition-transform">Faculty Login</button>
            </div>
        </div>
    </nav>

    <section id="home" class="hero-bg relative h-screen max-h-[850px] min-h-[600px] flex items-center justify-center text-center px-4 overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-red-600 rounded-full mix-blend-overlay filter blur-3xl opacity-20 animate-pulse-slow"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-red-900 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse"></div>
        <div class="relative z-10 max-w-5xl mx-auto space-y-8 pt-10">
            <div class="animate-fade-in-up" style="animation-delay: 0.1s;">
                <div class="inline-flex items-center gap-2 px-6 py-2 bg-black/20 backdrop-blur-md border border-white/10 rounded-full text-red-50 text-xs font-bold tracking-widest uppercase shadow-sm hover:bg-black/30 transition-colors cursor-default">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span> System Operational
                </div>
            </div>
            <h1 class="text-4xl md:text-7xl lg:text-8xl font-extrabold text-white leading-tight tracking-tight text-glow animate-fade-in-up" style="animation-delay: 0.3s;">Leave Management<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-red-100 to-red-300">Simplified.</span></h1>
            <p class="text-lg md:text-xl text-red-100/90 max-w-2xl mx-auto font-light leading-relaxed animate-fade-in-up" style="animation-delay: 0.5s;">The official digital ecosystem for WMSU Faculty. Securely manage service credits, file applications, and track approvals in real-time.</p>
            <div class="flex flex-col sm:flex-row gap-5 justify-center mt-8 animate-fade-in-up" style="animation-delay: 0.7s;">
                <button onclick="toggleLoginModal()" class="px-8 py-4 bg-white text-wmsu-main font-bold rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] hover:bg-gray-50 hover:scale-105 hover:shadow-[0_20px_50px_rgba(0,0,0,0.5)] transition-all duration-300 flex items-center justify-center gap-2 min-w-[200px] group"><span>Access Portal</span> <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i></button>
                <a href="#features" class="px-8 py-4 bg-white/5 backdrop-blur-sm border border-white/20 text-white font-semibold rounded-xl hover:bg-white/10 hover:border-white/40 transition-all duration-300 min-w-[200px]">Learn More</a>
            </div>
        </div>
        <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 text-white/50 animate-bounce-slow hidden md:block"><i data-lucide="chevron-down" class="w-8 h-8"></i></div>
        <div class="absolute bottom-0 w-full overflow-hidden leading-[0] z-20">
            <svg class="relative block w-[calc(100%+1.3px)] h-[60px] md:h-[100px]" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="fill-gray-50"></path></svg>
        </div>
    </section>
    <div class="bg-gray-50 py-16 -mt-2 relative z-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12 border border-gray-100 transform -translate-y-20 reveal">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center divide-y md:divide-y-0 md:divide-x divide-gray-100">
                    <div class="p-4">
                        <div class="text-wmsu-main font-bold text-5xl mb-2 flex justify-center items-center gap-1">
                            <span class="counter" data-target="24">0</span><span class="text-3xl">/</span><span class="counter" data-target="7">0</span>
                        </div>
                        <div class="text-gray-400 font-bold uppercase tracking-widest text-xs">Uptime Availability</div>
                    </div>
                    <div class="p-4">
                        <div class="text-wmsu-main font-bold text-5xl mb-2 flex justify-center items-center">
                            <span class="counter" data-target="100">0</span><span class="text-4xl">%</span>
                        </div>
                        <div class="text-gray-400 font-bold uppercase tracking-widest text-xs">Paperless Workflow</div>
                    </div>
                    <div class="p-4">
                        <div class="text-wmsu-main font-bold text-5xl mb-2 flex justify-center items-center">
                            <span class="text-2xl mr-1">&lt;</span><span class="counter" data-target="1">0</span><span class="text-2xl ml-1">min</span>
                        </div>
                        <div class="text-gray-400 font-bold uppercase tracking-widest text-xs">Processing Time</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <section id="features" class="py-10 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-20 reveal">
                <h2 class="text-wmsu-main font-bold tracking-widest uppercase text-xs mb-3 bg-red-100 inline-block px-3 py-1 rounded-full">Modern Solutions</h2>
                <h3 class="text-3xl md:text-5xl font-bold text-gray-900 mb-6 mt-4">Why Upgrade?</h3>
                <p class="text-gray-600 max-w-2xl mx-auto text-lg">Replace traditional paper forms with a secure, fast, and transparent digital ecosystem exclusively for the university faculty.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12">
                <div class="group bg-white p-8 rounded-3xl shadow-sm hover:shadow-2xl hover:shadow-red-900/10 transition-all duration-500 border border-gray-100 reveal hover:-translate-y-2 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-red-50 rounded-full blur-2xl group-hover:bg-red-100 transition-colors"></div>
                    
                    <div class="w-16 h-16 bg-gradient-to-br from-red-50 to-red-100 rounded-2xl flex items-center justify-center mb-8 group-hover:from-wmsu-main group-hover:to-red-700 transition-all duration-500 shadow-inner">
                        <i data-lucide="calendar-check" class="w-8 h-8 text-wmsu-main group-hover:text-white transition-colors duration-500"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-wmsu-main transition-colors">Instant Applications</h4>
                    <p class="text-gray-500 leading-relaxed text-sm lg:text-base">
                        Submit sick, vacation, or emergency leave requests in seconds using our intuitive interactive calendar interface.
                    </p>
                </div>

                <div class="group bg-wmsu-main p-8 rounded-3xl shadow-2xl transition-all duration-500 text-white transform md:scale-105 border border-wmsu-dark relative overflow-hidden reveal">
                    <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-white opacity-5 rounded-full blur-3xl"></div>
                    <div class="absolute top-10 left-10 w-20 h-20 bg-red-400 opacity-10 rounded-full blur-xl animate-pulse"></div>

                    <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center mb-8 backdrop-blur-md border border-white/10">
                        <i data-lucide="pie-chart" class="w-8 h-8 text-white"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-white mb-4">Credit Analytics</h4>
                    <p class="text-red-100 leading-relaxed text-sm lg:text-base">
                        View remaining service credits and leave balances in real-time. Eliminate guesswork with automated computations.
                    </p>
                    <div class="mt-8 pt-8 border-t border-white/10">
                        <button class="text-sm font-bold uppercase tracking-wider hover:text-red-200 transition-colors flex items-center gap-2">
                            View Dashboard <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <div class="group bg-white p-8 rounded-3xl shadow-sm hover:shadow-2xl hover:shadow-red-900/10 transition-all duration-500 border border-gray-100 reveal hover:-translate-y-2 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-red-50 rounded-full blur-2xl group-hover:bg-red-100 transition-colors"></div>

                    <div class="w-16 h-16 bg-gradient-to-br from-red-50 to-red-100 rounded-2xl flex items-center justify-center mb-8 group-hover:from-wmsu-main group-hover:to-red-700 transition-all duration-500 shadow-inner">
                        <i data-lucide="bell" class="w-8 h-8 text-wmsu-main group-hover:text-white transition-colors duration-500"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-wmsu-main transition-colors">Smart Notifications</h4>
                    <p class="text-gray-500 leading-relaxed text-sm lg:text-base">
                        Stay updated with instant email alerts whenever your Department Head or HR takes action on your request.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id="academics" class="py-24 bg-white relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-red-50 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-96 h-96 bg-red-50 rounded-full blur-3xl opacity-50"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-col lg:flex-row items-center gap-16">
                <div class="lg:w-1/2 reveal">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl border-8 border-gray-50 group">
                        <img src="assets/image/this.jpg" alt="Faculty Meeting" class="w-full h-full object-cover transform transition-transform duration-700 group-hover:scale-110">
                        <div class="absolute inset-0 bg-gradient-to-t from-wmsu-main/90 via-transparent to-transparent opacity-90"></div>
                        <div class="absolute bottom-8 left-8 text-white z-20">
                            <p class="text-xs font-bold uppercase tracking-widest mb-2 text-red-200">WMSU Administration</p>
                            <p class="font-bold text-2xl">Faculty Development & Welfare</p>
                        </div>
                    </div>
                </div>
                <div class="lg:w-1/2 space-y-8 reveal" style="transition-delay: 0.2s;">
                    <div>
                        <h2 class="text-4xl font-bold text-gray-900 mb-4">Aligned with Policy</h2>
                        <div class="h-1.5 w-20 bg-wmsu-main rounded-full mb-6"></div>
                        <p class="text-gray-600 text-lg leading-relaxed">
                            The Leave Application System is meticulously configured to strictly adhere to the <span class="font-semibold text-wmsu-main">Civil Service Commission (CSC)</span> and WMSU Faculty Manual guidelines.
                        </p>
                    </div>
                    
                    <ul class="space-y-6">
                        <li class="flex items-start group p-4 rounded-xl hover:bg-red-50 transition-colors cursor-default">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center group-hover:bg-wmsu-main transition-colors duration-300">
                                <i data-lucide="check" class="w-5 h-5 text-wmsu-main group-hover:text-white transition-colors"></i>
                            </div>
                            <div class="ml-4">
                                <h5 class="text-gray-900 font-bold text-lg">Smart Calendar Exclusion</h5>
                                <p class="text-gray-500 text-sm mt-1">Automatic exclusion of weekends and public holidays from leave counts.</p>
                            </div>
                        </li>
                        <li class="flex items-start group p-4 rounded-xl hover:bg-red-50 transition-colors cursor-default">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center group-hover:bg-wmsu-main transition-colors duration-300">
                                <i data-lucide="calculator" class="w-5 h-5 text-wmsu-main group-hover:text-white transition-colors"></i>
                            </div>
                            <div class="ml-4">
                                <h5 class="text-gray-900 font-bold text-lg">Monetization Engine</h5>
                                <p class="text-gray-500 text-sm mt-1">Built-in calculator for Service Credit monetization requests.</p>
                            </div>
                        </li>
                        <li class="flex items-start group p-4 rounded-xl hover:bg-red-50 transition-colors cursor-default">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center group-hover:bg-wmsu-main transition-colors duration-300">
                                <i data-lucide="send" class="w-5 h-5 text-wmsu-main group-hover:text-white transition-colors"></i>
                            </div>
                            <div class="ml-4">
                                <h5 class="text-gray-900 font-bold text-lg">Direct Submission</h5>
                                <p class="text-gray-500 text-sm mt-1">Linear application: Employee &rarr; Admin for immediate processing.</p>
                            </div>
                        </li>
                    </ul>

                    <div class="pt-6 border-t border-gray-100">
                        <a href="https://wmsu.edu.ph/" target="_blank" class="inline-flex items-center text-wmsu-main font-bold hover:text-red-700 transition-colors group">
                            Visit Official Portal 
                            <i data-lucide="external-link" class="ml-2 w-4 h-4 transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 transition-transform"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="admissions" class="py-24 bg-wmsu-main text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-wmsu-dark to-wmsu-main opacity-90"></div>
        
        <div class="max-w-4xl mx-auto text-center px-4 relative z-10 reveal">
            <h2 class="text-3xl md:text-5xl font-bold mb-6">Ready to manage your schedule?</h2>
            <p class="text-red-100 text-lg md:text-xl mb-10 max-w-2xl mx-auto font-light">
                Access the system using your WMSU Faculty ID. New faculty members should contact the HR office for account activation.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-5">
                <button onclick="toggleLoginModal()" class="bg-white text-wmsu-main px-10 py-4 rounded-xl font-bold hover:bg-red-50 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center gap-2">
                    <i data-lucide="lock" class="w-4 h-4"></i> Secure Login
                </button>
                <button onclick="toggleContactModal()" class="border-2 border-red-300/30 text-white px-10 py-4 rounded-xl font-bold hover:bg-white/10 transition-colors backdrop-blur-sm">
                    Contact HR Support
                </button>
            </div>
            <p class="mt-8 text-xs text-red-300 uppercase tracking-widest opacity-80">Secure Connection • SSL Encrypted</p>
        </div>
    </section>

    <section id="contact" class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 reveal">
                <h2 class="text-3xl font-bold text-gray-900">Get in Touch</h2>
                <p class="mt-4 text-gray-500">Having trouble logging in? Our support team is here to help.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300 reveal group">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-main transition-colors duration-300">
                        <i data-lucide="map-pin" class="w-8 h-8 text-wmsu-main group-hover:text-white transition-colors"></i>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">HR Office</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Administration Building<br>WMSU Campus, Zamboanga City</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300 reveal group" style="transition-delay: 0.1s;">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-main transition-colors duration-300">
                        <i data-lucide="phone" class="w-8 h-8 text-wmsu-main group-hover:text-white transition-colors"></i>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">Phone</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">(062) 991-1771<br>Loc. 1024</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300 reveal group" style="transition-delay: 0.2s;">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-main transition-colors duration-300">
                        <i data-lucide="mail" class="w-8 h-8 text-wmsu-main group-hover:text-white transition-colors"></i>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">Email Support</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">hr@wmsu.edu.ph<br>sysadmin@wmsu.edu.ph</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-[#1a0505] text-white py-16 border-t border-red-900/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                <div class="text-center md:text-left space-y-4">
                    <div class="flex items-center justify-center md:justify-start space-x-3">
                         <img src="assets/image/logo.png" alt="WMSU Logo" class="w-10 h-10 rounded-lg shadow-lg">
                         <div>
                             <h4 class="text-xl font-bold text-white tracking-wide">WMSU</h4>
                             <p class="text-xs text-red-400 uppercase tracking-widest">Faculty Leave System</p>
                         </div>
                    </div>
                    <p class="text-gray-500 text-sm max-w-md">
                        Empowering academic excellence through efficient digital governance. <br>Western Mindanao State University © 2025. All rights reserved.
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="p-3 bg-white/5 rounded-full text-gray-400 hover:text-white hover:bg-wmsu-main hover:scale-110 transition-all duration-300"><i data-lucide="facebook" class="w-5 h-5"></i></a>
                    <a href="#" class="p-3 bg-white/5 rounded-full text-gray-400 hover:text-white hover:bg-wmsu-main hover:scale-110 transition-all duration-300"><i data-lucide="twitter" class="w-5 h-5"></i></a>
                    <a href="#" class="p-3 bg-white/5 rounded-full text-gray-400 hover:text-white hover:bg-wmsu-main hover:scale-110 transition-all duration-300"><i data-lucide="globe" class="w-5 h-5"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <div id="login-modal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity opacity-0" id="modal-backdrop"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 relative transform scale-95 opacity-0 transition-all duration-300" id="modal-content">
                <button onclick="toggleLoginModal()" class="absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>

                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4 text-wmsu-main">
                        <i data-lucide="user" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">Welcome Back</h3>
                    <p class="text-gray-500 text-sm mt-2">Please enter your faculty credentials.</p>
                </div>

                <form method="post" style="display: <?php echo $checkDisplay; ?>; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Faculty ID</label>
                        <input type="text" name="id" value="<?php echo htmlspecialchars($employeeID); ?>" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="e.g., 2025-0001" autofocus>
                    </div>

                    <?php if ($idError): ?>
                        <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-start gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i> 
                            <span><?php echo $idError; ?></span>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="action" value="check" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold shadow-lg shadow-red-900/20 hover:bg-red-900 hover:shadow-xl transition-all transform active:scale-95 flex justify-center items-center gap-2">
                        Next <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </form>

                <form method="post" style="display: <?php echo $emailDisplay; ?>; flex-direction: column; gap: 1rem;">
                    <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-sm mb-2 flex gap-3">
                        <i data-lucide="info" class="w-5 h-5 shrink-0"></i>
                        <p>Your ID is not registered yet. Please provide your email to request access from the admin.</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="name@wmsu.edu.ph">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($employeeID); ?>">
                    </div>

                    <?php if ($emailError): ?>
                        <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i> <?php echo $emailError; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="action" value="notify" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold shadow-lg shadow-red-900/20 hover:bg-red-900 hover:shadow-xl transition-all transform active:scale-95">
                        Send Request
                    </button>
                </form>

                <form method="post" style="display: <?php echo $loginDisplay; ?>; flex-direction: column; gap: 1rem;">
                    <div class="text-center mb-2">
                        <span class="bg-red-100 text-wmsu-main px-4 py-1 rounded-full text-xs font-bold border border-red-200">
                            Hello, <?php echo htmlspecialchars($uname); ?>
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Password</label>
                        <input type="password" name="pass" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="••••••••">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($employeeID); ?>">
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center text-gray-600 cursor-pointer">
                            <input type="checkbox" class="rounded text-wmsu-main focus:ring-red-500 mr-2 border-gray-300"> Remember me
                        </label>
                        <button 
                            type="submit" 
                            name="action" 
                            value="show_forgot" 
                            class="text-wmsu-main font-semibold hover:underline bg-transparent border-none p-0 cursor-pointer"
                        >
                            Forgot password?
                        </button>
                    </div>

                    <?php if ($passError): ?>
                        <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i> <?php echo $passError; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="action" value="login" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold shadow-lg shadow-red-900/20 hover:bg-red-900 hover:shadow-xl transition-all transform active:scale-95">
                        Sign In
                    </button>
                </form>

                <form method="post" style="display: <?php echo $forgotDisplay; ?>; flex-direction: column; gap: 1rem;">
                    <div class="text-center mb-2">
                        <span class="bg-red-100 text-wmsu-main px-4 py-1 rounded-full text-xs font-bold border border-red-200">
                            Password Recovery
                        </span>
                    </div>
                    
                    <?php if (!empty($forgotError) && $forgotDisplay === "flex"): ?>
                        <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-start gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i> 
                            <span><?php echo $forgotError; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($forgotSuccess) && $forgotDisplay === "flex"): ?>
                        <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm flex items-start gap-2 mb-2">
                            <i data-lucide="check-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                            <span><?php echo $forgotSuccess; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($otpStage === false && !empty($forgotSuccess)): ?>
                        <button type="submit" name="action" value="check" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold hover:bg-red-900 transition-all">
                            Go to Login
                        </button>
                    
                    <?php elseif ($otpStage === true): ?>
                        <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-sm mb-2 flex gap-3">
                            <i data-lucide="key-round" class="w-5 h-5 shrink-0"></i>
                            <p>We sent a 6-digit code to **<?php echo htmlspecialchars($forgotEmail); ?>**. Enter it below with your new password.</p>
                        </div>

                        <input type="hidden" name="forgot_email" value="<?php echo htmlspecialchars($forgotEmail); ?>">

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">One-Time Password (OTP)</label>
                            <input type="text" name="otp_code" maxlength="6" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="6-digit code" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">New Password</label>
                            <input type="password" name="pass1" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="••••••••" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Confirm New Password</label>
                            <input type="password" name="pass2" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="••••••••" required>
                        </div>

                        <button type="submit" name="action" value="reset_password_otp" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold shadow-lg shadow-red-900/20 hover:bg-red-900 hover:shadow-xl transition-all transform active:scale-95">
                            Reset Password
                        </button>
                        <button type="submit" name="action" value="show_forgot" class="w-full bg-gray-100 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-200 transition-all">
                            Request New OTP
                        </button>

                    <?php else: ?>
                        <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-sm mb-2 flex gap-3">
                            <i data-lucide="mail" class="w-5 h-5 shrink-0"></i>
                            <p>Enter your registered email address to receive a **One-Time Password (OTP)**.</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Email Address</label>
                            <input type="email" name="forgot_email" value="<?php echo htmlspecialchars($forgotEmail); ?>" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="name@wmsu.edu.ph" required>
                        </div>

                        <button type="submit" name="action" value="forgot_password_email" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold shadow-lg shadow-red-900/20 hover:bg-red-900 hover:shadow-xl transition-all transform active:scale-95">
                            Send OTP
                        </button>
                        <button type="submit" name="action" value="check" class="w-full bg-gray-100 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-200 transition-all">
                            Cancel & Back
                        </button>
                    <?php endif; ?>
                </form>
                
                <p class="mt-6 text-center text-xs text-gray-400">
                    Protected by reCAPTCHA and Subject to WMSU Privacy Policy.
                </p>
            </div>
        </div>
    </div>

    <div id="contact-modal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity opacity-0" id="contact-backdrop"></div>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 relative transform scale-95 opacity-0 transition-all duration-300" id="contact-content">
                <button onclick="toggleContactModal()" class="absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>

                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4 text-wmsu-main">
                        <i data-lucide="message-circle" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">Contact HR</h3>
                    <p class="text-gray-500 text-sm mt-2">We are here to help with your leave inquiries.</p>
                </div>

                <form method="post" style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php if ($contactSuccess): ?>
                        <div class="bg-green-50 text-green-700 p-4 rounded-lg text-sm flex items-start gap-2 mb-2">
                            <i data-lucide="check-circle" class="w-5 h-5 mt-0.5 shrink-0"></i> 
                            <span><?php echo $contactSuccess; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$contactSuccess): ?>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Email Address</label>
                            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contactEmail); ?>" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all" placeholder="name@wmsu.edu.ph" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Your Message</label>
                            <textarea name="message" rows="4" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-wmsu-main focus:ring-2 focus:ring-red-100 outline-none transition-all resize-none" placeholder="How can we assist you?" required><?php echo htmlspecialchars($contactMsg); ?></textarea>
                        </div>

                        <?php if ($contactError): ?>
                            <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-start gap-2">
                                <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i> 
                                <span><?php echo $contactError; ?></span>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="action" value="contact_hr" class="w-full bg-wmsu-main text-white py-3 rounded-xl font-bold shadow-lg shadow-red-900/20 hover:bg-red-900 hover:shadow-xl transition-all transform active:scale-95 flex justify-center items-center gap-2">
                            Send Message <i data-lucide="send" class="w-4 h-4"></i>
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="toggleContactModal()" class="w-full bg-gray-100 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-200 transition-all">
                            Close
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // 1. Mobile Menu Logic
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }

        // 2. Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 20) {
                nav.classList.add('scrolled', 'py-0');
            } else {
                nav.classList.remove('scrolled', 'py-0');
            }
        });

        // 3. Scroll Reveal Animation
        const revealElements = document.querySelectorAll('.reveal');
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, { threshold: 0.1 });

        revealElements.forEach(el => revealObserver.observe(el));

        // 4. Number Counter Animation
        const counters = document.querySelectorAll('.counter');
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const target = +counter.getAttribute('data-target');
                    const duration = 2000;
                    const increment = target / (duration / 16); 
                    
                    let current = 0;
                    const updateCounter = () => {
                        current += increment;
                        if (current < target) {
                            counter.innerText = Math.ceil(current);
                            requestAnimationFrame(updateCounter);
                        } else {
                            counter.innerText = target;
                        }
                    };
                    updateCounter();
                    observer.unobserve(counter);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => counterObserver.observe(counter));

        // 5. Login Modal Logic
        const loginModal = document.getElementById('login-modal');
        const loginBackdrop = document.getElementById('modal-backdrop');
        const loginContent = document.getElementById('modal-content');
        const body = document.body;

        function toggleLoginModal() {
            const isHidden = loginModal.classList.contains('hidden');
            
            if (isHidden) {
                if(!document.getElementById('contact-modal').classList.contains('hidden')) {
                     toggleContactModal();
                }

                loginModal.classList.remove('hidden');
                body.classList.add('no-scroll');
                setTimeout(() => {
                    loginBackdrop.classList.remove('opacity-0');
                    loginContent.classList.remove('scale-95', 'opacity-0');
                    loginContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            } else {
                loginBackdrop.classList.add('opacity-0');
                loginContent.classList.remove('scale-100', 'opacity-100');
                loginContent.classList.add('scale-95', 'opacity-0');
                
                setTimeout(() => {
                    loginModal.classList.add('hidden');
                    body.classList.remove('no-scroll');
                }, 300);
            }
        }

        // 6. Contact Modal Logic
        const contactModal = document.getElementById('contact-modal');
        const contactBackdrop = document.getElementById('contact-backdrop');
        const contactContent = document.getElementById('contact-content');

        function toggleContactModal() {
            const isHidden = contactModal.classList.contains('hidden');
            
            if (isHidden) {
                if(!document.getElementById('login-modal').classList.contains('hidden')) {
                     toggleLoginModal();
                }

                contactModal.classList.remove('hidden');
                body.classList.add('no-scroll');
                setTimeout(() => {
                    contactBackdrop.classList.remove('opacity-0');
                    contactContent.classList.remove('scale-95', 'opacity-0');
                    contactContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            } else {
                contactBackdrop.classList.add('opacity-0');
                contactContent.classList.remove('scale-100', 'opacity-100');
                contactContent.classList.add('scale-95', 'opacity-0');
                
                setTimeout(() => {
                    contactModal.classList.add('hidden');
                    body.classList.remove('no-scroll');
                }, 300);
            }
        }

        // Close modals when clicking outside
        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal || e.target === loginBackdrop) {
                toggleLoginModal();
            }
        });

        contactModal.addEventListener('click', (e) => {
            if (e.target === contactModal || e.target === contactBackdrop) {
                toggleContactModal();
            }
        });

        // 7. PHP Auto-Open Logic
        <?php if ($shouldOpenLoginModal): ?>
            document.addEventListener("DOMContentLoaded", function() {
                toggleLoginModal();
            });
        <?php endif; ?>

        <?php if ($shouldOpenContactModal): ?>
            document.addEventListener("DOMContentLoaded", function() {
                toggleContactModal();
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php ob_end_flush(); ?>