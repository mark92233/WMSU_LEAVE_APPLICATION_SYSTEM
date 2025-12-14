<?php
// 1. OUTPUT BUFFERING START (Fixes header errors)
ob_start(); 

require_once "../dbRelated/db_connect.php";
require_once "../dbRelated/operation.php";
session_start();

// --- Security Check & Employee ID Acquisition ---
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../index.php"); 
    exit();
}
$employeeID = $_SESSION['employeeID']; 

// --- Initialize Managers ---
$employeeManager = new EmployeeManager();
$adminRegManager = new AdminRegistration();
$creditManager   = new LeaveCreditManager();
$accountSetupManager = new AccountSetup(); 
$depObj          = new DepartmentManager();
$posObj          = new PositionManager();

// --- State Variables ---
$isEmployeeRegistered = $employeeManager->getEmployee($employeeID);
$isAccountCreated = $isEmployeeRegistered ? $accountSetupManager->getAccountByEmployeeID($employeeID) : false;
// Fetch Data for Dropdowns
$departments = $depObj->getAllDepartments();
$positions   = $posObj->getAllPositions();

// 2. REDIRECT LOGIC MOVED HERE (Before HTML)
if ($isEmployeeRegistered && $isAccountCreated) {
    header("Location: ../index.php");
    exit();
}

// --- Initial Input & Error Initialization ---
$inputs = [
    "FirstName" => "", "LastName" => "", "MiddleName" => "", "dob" => "",
    "sex" => "", "ContactNumber" => "", "email" => "", "DepartmentID" => "",
    "PositionID" => "", "DateHired" => "", "isTeaching" => "", "profilePic" => "",
    "Username" => "", "Password" => "", "ConfirmPassword" => "" 
];
$error = array_fill_keys(array_keys($inputs), "");
$message = "";

$btnSubmit = "flex"; 
$btnLog = "none";     

// --- Universal Sanitation Function ---
function sanitizeInput($data, $type = "text") {
    $data = trim($data);
    $data = stripslashes($data);
    switch ($type) {
        case "email": 
            $data = filter_var($data, FILTER_SANITIZE_EMAIL); 
            break;
        case "text": 
            $data = preg_replace("/[^a-zA-Z0-9\s\.,\-@]/", "", $data); 
            break;
        case "date": 
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $data)) $data = ""; 
            break;
        case "select": 
            $data = preg_replace("/[^a-zA-Z0-9_\-]/", "", $data); 
            break;
        case "number": 
            $data = preg_replace("/[^0-9]/", "", $data); 
            break;
        case "password": 
            $data = preg_replace("/[^a-zA-Z0-9!@#$%^&*()_+=\-]/", "", $data); 
            break;
    }
    return $data;
}

function getRoleFromPosition($positionID) {
    return match((int)$positionID) {
        1 => "Faculty",
        2 => "Department Head",
        3 => "Dean",
        4 => "Admin",
        default => "User"
    };
}

// =====================================================================
// LOGIC BLOCK 1: ACCOUNT CREDENTIAL SETUP
// =====================================================================
if ($isEmployeeRegistered && !$isAccountCreated && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'account_setup') {
    
    $employeeData = $isEmployeeRegistered;
    $positionID = (int)$employeeData['PositionID'];

    $inputs["Username"] = sanitizeInput($_POST["Username"] ?? "", "text");
    $inputs["Password"] = sanitizeInput($_POST["Password"] ?? "", "password");
    $inputs["ConfirmPassword"] = sanitizeInput($_POST["ConfirmPassword"] ?? "", "password");
    $log = $_POST["log"] ?? ""; 

    if (empty($inputs["Username"])) $error["Username"] = "Please enter a username.";
    if (empty($inputs["Password"])) {
        $error["Password"] = "Please enter a password.";
    } elseif (strlen($inputs["Password"]) < 6) {
        $error["Password"] = "Password must be at least 6 characters.";
    }
    if ($inputs["Password"] !== $inputs["ConfirmPassword"]) $error["ConfirmPassword"] = "Passwords do not match.";

    $success = false; 

    if (!array_filter($error)) {
        $role = getRoleFromPosition($positionID);
        $success = $accountSetupManager->createAccount(
            $employeeID, 
            $inputs["Username"], 
            $inputs["Password"], 
            $role
        );

        if ($success) {
            $message = "✅ Account successfully created! Proceed to login.";
            $btnSubmit = "none";
            $btnLog = "flex";
            $inputs["Username"] = $inputs["Password"] = $inputs["ConfirmPassword"] = ""; 
        } else {
            $message = "❌ Failed to create account. Username may already exist.";
        }
    }

    if ($log === "log" && $success) { 
        header("Location: ../index.php"); 
        exit;
    }
}


// =====================================================================
// LOGIC BLOCK 2: EMPLOYEE REGISTRATION SUBMISSION
// =====================================================================
if (!$isEmployeeRegistered && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'registration') {
    
    $inputs["FirstName"] = sanitizeInput($_POST["FirstName"] ?? "", "text");
    $inputs["LastName"] = sanitizeInput($_POST["LastName"] ?? "", "text");
    $inputs["MiddleName"] = sanitizeInput($_POST["MiddleName"] ?? "", "text");
    $inputs["dob"] = sanitizeInput($_POST["dob"] ?? "", "date");
    $inputs["sex"] = sanitizeInput($_POST["sex"] ?? "", "select");
    $inputs["ContactNumber"] = sanitizeInput($_POST["ContactNumber"] ?? "", "text");
    $inputs["email"] = sanitizeInput($_POST["email"] ?? "", "email");
    
    // FIX 2: Sanitize to number, but rely on intval() below for final type.
    $inputs["DepartmentID"] = sanitizeInput($_POST["department"] ?? "", "number"); 
    $inputs["PositionID"] = sanitizeInput($_POST["position"] ?? "", "number");
    
    $inputs["DateHired"] = sanitizeInput($_POST["DateHired"] ?? "", "date");
    $inputs["isTeaching"] = sanitizeInput($_POST["isTeaching"] ?? "", "select");

    // --- Validation ---
    if (empty($inputs["FirstName"])) $error["FirstName"] = "Please enter your first name.";
    if (empty($inputs["LastName"])) $error["LastName"] = "Please enter your last name.";
    if (empty($inputs["PositionID"]) || intval($inputs["PositionID"]) === 0) { // Check Position
        $error["PositionID"] = "Please select a position.";
    }
    
    // CRITICAL FIX 1: Validate DepartmentID to prevent FK failure on ID=0
    if (empty($inputs["DepartmentID"]) || intval($inputs["DepartmentID"]) === 0) {
        $error["DepartmentID"] = "Please select a college/department.";
    }
    
    // Set default MiddleName if left blank, prevents issues with non-nullable columns if applicable
    if (empty($inputs["MiddleName"])) $inputs["MiddleName"] = "N/A"; 

    $uploadFile = $_FILES["upload"] ?? null;
    $inputs["profilePic"] = null; 

    if ($uploadFile && !empty($uploadFile["name"]) && $uploadFile["error"] === UPLOAD_ERR_OK) {
        $fileTmp  = $uploadFile["tmp_name"];
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($fileInfo, $fileTmp);
        finfo_close($fileInfo);
        
        if (!in_array($fileType, $allowedTypes)) {
             $error["upload"] = "Only JPG or PNG files are allowed.";
        } elseif ($uploadFile["size"] > 5 * 1024 * 1024) {
            $error["upload"] = "File size must not exceed 5 MB.";
        } else {
            $fileName = preg_replace("/[^a-zA-Z0-9_\-\.]/", "", basename($uploadFile["name"]));
            $fileName = time() . "_" . $fileName;
            $targetDir = "../uploadedFiles/profile"; 
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $targetFile = $targetDir . "/" . $fileName;

            if (move_uploaded_file($uploadFile["tmp_name"], $targetFile)) {
                $inputs["profilePic"] = "uploadedFiles/profile/" . $fileName;
            } else {
                $error["upload"] = "Error uploading file.";
            }
        }
    }

    if (!array_filter($error)) {
        $employeeData = [
            'EmployeeID' => $employeeID,
            'FirstName' => $inputs["FirstName"],
            'LastName' => $inputs["LastName"],
            'MiddleName' => $inputs["MiddleName"],
            'DOB' => $inputs["dob"],
            'Sex' => $inputs["sex"],
            'ContactNumber' => $inputs["ContactNumber"],
            'Email' => $inputs["email"],
            // FIX 2: Ensure final values are integers before sending to DB
            'DepartmentID' => intval($inputs["DepartmentID"]), 
            'PositionID' => intval($inputs["PositionID"]),
            'DateHired' => $inputs["DateHired"],
            'isTeaching' => $inputs["isTeaching"] === 'true' ? 1 : 0, 
            'profilePic' => $inputs["profilePic"],
        ];
        
        // This is line 206, which calls the function that fails on line 31
        if ($employeeManager->registerEmployee($employeeData)) { 
            $adminRegManager->activate($employeeID); 
            $creditManager->updateAccruedCredits($employeeID, $employeeManager, null); 
            header("Location: register.php"); 
            exit;
        } else {
             $message = "Error adding employee record to database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faculty Registration | Leave System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php if (!empty($message)): ?>
    <div class="message">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="login-card">
    
    <div class="brand-logo-container">
        <img src="../assets/image/logo.png" alt="WMSU Logo" class="brand-logo-img">
    </div>

    <div class="scroll-content">

        <?php if (!$isEmployeeRegistered): // STATE 1: Show Registration Form ?>
            
            <div class="header">
                <h2>Faculty Registration</h2>
                <p>Step 1 of 2: Personal Information <br> (ID: <strong><?= htmlspecialchars($employeeID) ?></strong>)</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="registration">

                <div class="input-group">
                    <label>First Name</label>
                    <input type="text" name="FirstName" value="<?= htmlspecialchars($inputs['FirstName'] ?? '') ?>">
                    <div class="error"><?= htmlspecialchars($error['FirstName'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Last Name</label>
                    <input type="text" name="LastName" value="<?= htmlspecialchars($inputs['LastName'] ?? '') ?>">
                    <div class="error"><?= htmlspecialchars($error['LastName'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Middle Name</label>
                    <input type="text" name="MiddleName" value="<?= htmlspecialchars($inputs['MiddleName'] ?? '') ?>">
                </div>

                <div class="input-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" value="<?= htmlspecialchars($inputs['dob'] ?? '') ?>">
                    <div class="error"><?= htmlspecialchars($error['dob'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Sex</label>
                    <select name="sex">
                        <option value="">--Select--</option>
                        <option value="Male" <?= ($inputs['sex'] ?? '')=='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= ($inputs['sex'] ?? '')=='Female'?'selected':'' ?>>Female</option>
                    </select>
                    <div class="error"><?= htmlspecialchars($error['sex'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Contact Number</label>
                    <input type="text" name="ContactNumber" value="<?= htmlspecialchars($inputs['ContactNumber'] ?? '') ?>" placeholder="09XXXXXXXXX">
                    <div class="error"><?= htmlspecialchars($error['ContactNumber'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($inputs['email'] ?? '') ?>">
                    <div class="error"><?= htmlspecialchars($error['email'] ?? '') ?></div>
                </div>
               <div class="input-group">
                    <label>College</label>
                    <select name="department">
                        <option value="">--Select--</option>
                        <?php if (!empty($departments)): ?>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d['DepartmentID']) ?>" 
                                    <?= ($inputs['DepartmentID'] ?? '') == $d['DepartmentID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['DepartmentName']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="error"><?= htmlspecialchars($error['DepartmentID'] ?? '') ?></div>
                </div>

                <div class="input-group">
    <label>Position</label>
    <select name="position">
        <option value="">--Select--</option>
        <?php if (!empty($positions)): ?>
            <?php foreach($positions as $p): ?>
                
                <?php 
                    // This is the statement you requested
                    if ($p['PositionName'] === 'Admin') continue; 
                ?>

                <option value="<?= htmlspecialchars($p['PositionID']) ?>" 
                    <?= ($inputs['PositionID'] ?? '') == $p['PositionID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['PositionName']) ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
    <div class="error"><?= htmlspecialchars($error['PositionID'] ?? '') ?></div>
</div>
                
                <div class="input-group">
                    <label>Date Hired</label>
                    <input type="date" name="DateHired" value="<?= htmlspecialchars($inputs['DateHired'] ?? '') ?>">
                    <div class="error"><?= htmlspecialchars($error['DateHired'] ?? '') ?></div>
                </div>
                
                <div class="input-group">
                    <label>Teaching Personnel</label>
                    <select name="isTeaching">
                        <option value="">--Select--</option>
                        <option value="true" <?= ($inputs['isTeaching'] ?? '')=='true'?'selected':'' ?>>Teaching</option>
                        <option value="false" <?= ($inputs['isTeaching'] ?? '')=='false'?'selected':'' ?>>Non-Teaching</option>
                    </select>
                    <div class="error"><?= htmlspecialchars($error['isTeaching'] ?? '') ?></div>
                </div>
                
                <div class="input-group">
                    <label>Profile Picture</label>
                    <input type="file" name="upload" id="upload" accept="image/*">
                    <div class="error"><?= htmlspecialchars($error['upload'] ?? "") ?></div>
                </div>

                <button type="submit">Submit & Continue <i class="fas fa-arrow-right"></i></button>
            </form>

        <?php elseif ($isEmployeeRegistered && !$isAccountCreated): // STATE 2: Show Account Setup Form ?>

            <div class="header">
                <h2>Set Credentials</h2>
                <p>Step 2 of 2: Create your login <br> (ID: <strong><?= htmlspecialchars($employeeID) ?></strong>)</p>
            </div>

            <form method="POST">
                <input type="hidden" name="form_type" value="account_setup">

                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="Username" value="<?= htmlspecialchars($inputs['Username'] ?? '') ?>" placeholder="Create a unique username">
                    <div class="error"><?= htmlspecialchars($error['Username'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="Password" placeholder="At least 6 characters">
                    <div class="error"><?= htmlspecialchars($error['Password'] ?? '') ?></div>
                </div>

                <div class="input-group">
                    <label>Confirm Password</label>
                    <input type="password" name="ConfirmPassword" placeholder="Re-type password">
                    <div class="error"><?= htmlspecialchars($error['ConfirmPassword'] ?? '') ?></div>
                </div>

                <button type="submit" name="log" value="sub" style="display:<?= $btnSubmit ?>;">Create Account</button>
                
                <?php if ($btnLog === 'flex'): ?>
                       <button type="submit" name="log" value="log" style="display:<?= $btnLog ?>;">Login to Dashboard</button>
                <?php endif; ?>

            </form>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
<?php
ob_end_flush(); // End buffering
?>