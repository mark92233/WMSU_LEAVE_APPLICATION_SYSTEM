<?php
// Handle user password change requests via POST
$pwdError = "";
$pwdSuccess = "";
$acc = new AccountSetup();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    // Retrieve current account credentials for verification
    $credentials = $acc->getAccountByEmployeeID($employeeID);

    if (!$credentials) {
        $pwdError = "Account not found.";
    } 
    // Verify provided current password against stored hash
    elseif (!password_verify($currentPwd, $credentials['Password'])) {
        $pwdError = "The current password you entered is incorrect.";
    } 
    // Ensure new password matches confirmation
    elseif ($newPwd !== $confirmPwd) {
        $pwdError = "New passwords do not match.";
    } 
    // Enforce minimum password length requirements
    elseif (strlen($newPwd) < 6) {
        $pwdError = "New password must be at least 6 characters long.";
    } else {
        // Update password in database and set status messages
        if ($acc->changePassword($employeeID, $newPwd)) {
            $pwdSuccess = "Password updated successfully!";
        } else {
            $pwdError = "System error: Unable to update password at this time.";
        }
    }
}
?>
<style>
    /* =========================================
       PROFESSIONAL PROFILE STYLES (Red SaaS)
       ========================================= */
    :root {
        --primary-red: #b91c1c;       /* Cardinal Red */
        --primary-dark: #7f1d1d;      
        --bg-surface: #ffffff;
        --bg-body: #f3f4f6;
        --border-light: #e5e7eb;
        --text-main: #1f2937;
        --text-muted: #6b7280;
        --success-bg: #ecfdf5;
        --success-text: #047857;
        --danger-bg: #fef2f2;
        --danger-text: #991b1b;
    }

    /* Layout Container */
    .profile-container {
        display: grid;
        grid-template-columns: 300px 1fr; /* Fixed Sidebar + Fluid Content */
        gap: 25px;
        max-width: 1100px;
        margin: 40px auto;
        font-family: "Inter", system-ui, sans-serif;
    }

    /* --- LEFT SIDEBAR (Identity) --- */
    .profile-sidebar {
        background: var(--bg-surface);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        border: 1px solid var(--border-light);
        text-align: center;
        padding: 40px 20px;
        height: fit-content;
    }

    .avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto 20px;
    }

    .avatar-wrapper img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--bg-surface);
        box-shadow: 0 0 0 2px var(--primary-red);
    }

    .profile-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 5px;
    }

    .profile-role {
        font-size: 0.95rem;
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    .status-badge {
        display: inline-block;
        background: var(--success-bg);
        color: var(--success-text);
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid #d1fae5;
        margin-bottom: 25px;
    }

    .sidebar-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .btn-action {
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .btn-primary {
        background: var(--primary-red);
        color: white;
    }
    .btn-primary:hover { background: var(--primary-dark); }

    .btn-outline {
        background: transparent;
        border: 1px solid var(--border-light);
        color: var(--text-main);
    }
    .btn-outline:hover { background: var(--bg-body); }


    /* --- RIGHT CONTENT (Details) --- */
    .profile-content {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .detail-card {
        background: var(--bg-surface);
        border-radius: 12px;
        box-shadow: 0 2px 4px -1px rgba(0,0,0,0.05);
        border: 1px solid var(--border-light);
        padding: 25px 30px;
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .card-title i { color: var(--primary-red); }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .info-item {
        margin-bottom: 5px;
    }

    .label {
        display: block;
        font-size: 0.8rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .value {
        font-size: 1rem;
        color: var(--text-main);
        font-weight: 500;
    }

    /* --- MODAL STYLES (To match Login Modal) --- */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    .modal-overlay.active {
        opacity: 1;
        pointer-events: all;
    }

    .modal-card {
        background: var(--bg-surface);
        width: 100%;
        max-width: 400px;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: translateY(20px);
        transition: transform 0.3s ease;
        position: relative;
    }

    .modal-overlay.active .modal-card {
        transform: translateY(0);
    }

    .modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        background: transparent;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 1.2rem;
    }

    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }

    .form-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .form-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-light);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s;
        background: var(--bg-body);
    }

    .form-input:focus {
        border-color: var(--primary-red);
        outline: none;
        background: var(--bg-surface);
    }

    /* Alerts */
    .alert {
        padding: 10px;
        border-radius: 8px;
        font-size: 0.9rem;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .alert-error { background: var(--danger-bg); color: var(--danger-text); }
    .alert-success { background: var(--success-bg); color: var(--success-text); }

    /* --- MOBILE RESPONSIVENESS --- */
    @media (max-width: 768px) {
        .profile-container {
            grid-template-columns: 1fr;
        }
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<!-- Font Awesome (If not already included in your header) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<?php 
// 1. Fetch Data
// Ensure $employeeManager is accessible
$account = $employeeManager->getEmployee($employeeID);
// Format Date Helper
$formatDate = function($date) {
    return ($date && $date != '0000-00-00') ? date("F j, Y", strtotime($date)) : "N/A";
};
?>

<?php if ($account): ?>
    <div class="profile-container">
        
        <!-- SIDEBAR: Identity & Actions -->
        <aside class="profile-sidebar">
            <div class="avatar-wrapper">
                <img src="<?= htmlspecialchars($account['profilePic'] ?? 'assets/image/default-avatar.png') ?>" alt="Profile">
            </div>
            
            <h1 class="profile-name">
                <?= htmlspecialchars($account['FirstName'] . ' ' . ($account['MiddleName'] == 'N/A' ? '' : $account['MiddleName'] . ' ') . $account['LastName']) ?>
            </h1>
            <p class="profile-role"><?= htmlspecialchars($account['PositionName'] ?? 'N/A') ?></p>
            
            <div class="status-badge">Active Status</div>

            <!-- Messages -->
            <?php if ($pwdError): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $pwdError ?></div>
            <?php endif; ?>
            <?php if ($pwdSuccess): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $pwdSuccess ?></div>
            <?php endif; ?>

            <div class="sidebar-actions">
                <!-- TRIGGER BUTTON -->
                <button class="btn-action btn-primary" onclick="openModal()">
                    <i class="fas fa-key" style="margin-right: 8px;"></i> Change Password
                </button>
            </div>
        </aside>

        <!-- MAIN CONTENT: Details -->
        <main class="profile-content">
            
            <!-- Card 1: Employment Information -->
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-briefcase"></i> Employment Details
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Employee ID</span>
                        <div class="value"><?= htmlspecialchars($account['EmployeeID']) ?></div>
                    </div>
                    <div class="info-item">
                        <span class="label">Date Hired</span>
                        <div class="value"><?= $formatDate($account['DateHired']) ?></div>
                    </div>
                    <div class="info-item">
                        <span class="label">College</span>
                        <div class="value">
                        <?php 
                        // Check if Admin first
                        if (($account['PositionName'] ?? '') === 'Admin') {
                            echo 'N/A';
                        } else {
                            // Original logic for non-admins
                            echo htmlspecialchars($account['DepartmentName'] ?? 'N/A');
                        }
                        ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="label">Teaching Status</span>
                        <div class="value">
                                <?php 
                                // Check if Admin first
                                if (($account['PositionName'] ?? '') === 'Admin') {
                                    echo 'Administrator'; 
                                } else {
                                    // Original logic for non-admins
                                    echo ($account['isTeaching'] ?? 0) 
                                        ? '<span style="color:var(--primary-red); font-weight:600;">Teaching Faculty</span>' 
                                        : 'Non-Teaching Staff'; 
                                }
                                ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Personal Information -->
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-user-circle"></i> Personal Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Full Name</span>
                        <div class="value">
                            <?= htmlspecialchars($account['FirstName'] . ' ' . ($account['MiddleName'] == 'N/A' ? '' : $account['MiddleName'] . ' ') . $account['LastName']) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="label">Date of Birth</span>
                        <div class="value"><?= $formatDate($account['DOB']) ?></div>
                    </div>
                    <div class="info-item">
                        <span class="label">Gender</span>
                        <div class="value"><?= htmlspecialchars($account['Sex']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Contact Info -->
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-address-book"></i> Contact Details
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Email Address</span>
                        <div class="value"><?= htmlspecialchars($account['Email']) ?></div>
                    </div>
                    <div class="info-item">
                        <span class="label">Mobile Number</span>
                        <div class="value"><?= htmlspecialchars($account['ContactNumber']) ?></div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div class="modal-overlay" id="pwdModal">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            
            <div style="text-align:center; margin-bottom:20px;">
                <div style="width:50px; height:50px; background:var(--danger-bg); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:var(--primary-red); font-size:1.2rem; margin-bottom:10px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 style="margin:0; color:var(--text-main); font-size:1.25rem;">Change Password</h3>
                <p style="margin:5px 0 0; color:var(--text-muted); font-size:0.9rem;">Secure your account</p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-action btn-primary" style="margin-top:10px;">
                    Update Password
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <div style="text-align: center; padding: 50px; color: #666;">
        <h2>User not found</h2>
        <p>No account information available.</p>
    </div>
<?php endif; ?>

<script>
    function openModal() {
        document.getElementById('pwdModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('pwdModal').classList.remove('active');
    }

    // Close modal on outside click
    document.getElementById('pwdModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>