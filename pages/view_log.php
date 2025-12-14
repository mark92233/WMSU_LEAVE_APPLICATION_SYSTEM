<?php
$details = null;
$leaveID = $_POST['leave_id'] ?? $_GET['leave_id'] ?? null;

if ($leaveID) {
    // This function MUST be updated in operation.php to include the leavetype JOIN
    $details = $applicationManager->getOneApplicationWithApproval($leaveID); 
}

if ($details === null) {
    echo "<h1>Error: Leave Log not found or no ID provided.</h1>";
} else {
?>

<div id="evaluate-section">
    <h1>LEAVE APPLICATION LOG</h1>
    <h2>Personal Profile</h2>

    <div class="profile-section">
        <img src="<?= htmlspecialchars($details['profilePic'] ?? 'assets/image/default-avatar.png') ?>" alt="Profile Picture">
        <div class="info">
            <p><strong>Employee ID:</strong> <?= htmlspecialchars($details['EmployeeID'] ?? 'N/A') ?></p>
            <p><strong>Full Name:</strong> 
                <span>
                    <?= htmlspecialchars($details['FirstName'] ?? '') ?> 
                    <?php
                    // FIX: Robust middle name check
                    $middle = htmlspecialchars($details['MiddleName'] ?? '');
                    echo (
                        !empty($middle) && $middle !== "N/A" 
                        ? $middle . ' ' 
                        : '' 
                    );
                    ?>
                    <?= htmlspecialchars($details['LastName'] ?? '') ?>
                </span>
            </p>
            <p><strong>Position:</strong> <?= htmlspecialchars($details['PositionName'] ?? 'N/A') ?></p>
            <p><strong>College:</strong> <?= htmlspecialchars($details['DepartmentName'] ?? 'N/A') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($details['Email'] ?? 'N/A') ?></p>
        </div>
    </div>

    <div class="section-title">Leave Details (Application ID: <?= htmlspecialchars($details['LeaveID'] ?? 'N/A') ?>)</div>
    <p><strong>Leave Type:</strong> <?= htmlspecialchars($details['LeaveType'] ?? 'N/A') ?></p>
    <p><strong>Total Days:</strong> <?= htmlspecialchars($details['NumberOfDays'] ?? 'N/A') ?></p>
    <p><strong>Start Date:</strong> <?= htmlspecialchars($details['StartDate'] ?? 'N/A') ?></p>
    <p><strong>End Date:</strong> <?= htmlspecialchars($details['EndDate'] ?? 'N/A') ?></p>
    <p><strong>Reason:</strong> <?= htmlspecialchars($details['Reason'] ?? 'N/A') ?></p>

    <p><strong>Attachment:</strong>
        <?php if (!empty($details['Attachment'])): ?>
            <a href="<?= htmlspecialchars($details['Attachment']) ?>" target="_blank">View File</a>
        <?php else: ?>
            None
        <?php endif; ?>
    </p>
    
    <div class="section-title" style="margin-top:30px; border-top: 1px solid #ddd; padding-top: 20px;">
        Approval Decision
    </div>
    <p><strong>Final Status:</strong> <span style="font-weight:bold; color:<?= $details['Status'] === 'Approved' ? 'green' : ($details['Status'] === 'Rejected' ? 'red' : 'orange') ?>;"><?= htmlspecialchars($details['Status'] ?? 'Pending') ?></span></p>
    <p><strong>Decision Date:</strong> <?= htmlspecialchars($details['DecidedDate'] ?? 'N/A') ?></p>
    <p><strong>Approver ID:</strong> <?= htmlspecialchars($details['EmployeeApproverID'] ?? 'N/A') ?></p>
    <p><strong>Approver Remarks:</strong> <span style="font-style:italic;">
        <?= htmlspecialchars($details['ApproverRemarks'] ?? 'No remarks provided.') ?>
    </span></p>
    <button type="button" class="pdf-btn" onclick="window.open('pages/print.php?leave_id=<?= htmlspecialchars($details['LeaveID'] ?? '') ?>', '_blank')">
    Download as PDF
</button>
    
    </div>
<?php } ?>