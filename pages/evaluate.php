<?php
$details = null;
$leaveID = $_POST['leave_id'] ?? $_GET['leave_id'] ?? null;

if ($leaveID) {
    // Note: Ensure $applicationManager->getOneApplication($leaveID) joins employee data
    $details = $applicationManager->getOneApplication($leaveID); 
}

if ($details === null) {
    echo "<h1>Error: Application not found or no ID provided.</h1>";
} else {
?>

<div id="evaluate-section">
    <h1>LEAVE APPLICATION</h1>
    <h2>Personal Profile</h2>

    <div class="profile-section">
        <img src="<?= htmlspecialchars($details['profilePic'] ?? 'assets/image/default-avatar.png') ?>" alt="Profile Picture">
        <div class="info">
            <p><strong>Full Name:</strong> 
                <span>
                    <?= htmlspecialchars($details['FirstName']) ?> 
                    <?php
                    // FIX: Simplified and corrected MiddleName check using ternary with parentheses
                    $middle = htmlspecialchars($details['MiddleName'] ?? '');
                    echo (
                        !empty($middle) && $middle !== "N/A" 
                        ? $middle . ' ' 
                        : '' 
                    );
                    ?>
                    <?= htmlspecialchars($details['LastName']) ?>
                </span>
            </p>
            <p><strong>Position:</strong><?= htmlspecialchars($details['PositionName'] ?? 'N/A') ?></p>
            <p><strong>College:</strong> <?= htmlspecialchars($details['DepartmentName'] ?? 'N/A') ?></p>
            <p><strong>Contact Number:</strong><?= htmlspecialchars($details['ContactNumber'] ?? 'N/A') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($details['Email'] ?? 'N/A') ?></p>
        </div>
    </div>
    <div class="section-title">Leave Information (Application ID: <?= htmlspecialchars($details['LeaveID']) ?>)</div>
    <p><strong>Leave Type:</strong> <?= htmlspecialchars($details['LeaveType']) ?></p>
    <p><strong>Total Days:</strong> <?= htmlspecialchars($details['NumberOfDays']) ?></p>
    <p><strong>Start Date:</strong> <?= htmlspecialchars($details['StartDate']) ?></p>
    <p><strong>End Date:</strong> <?= htmlspecialchars($details['EndDate']) ?></p>
    <p><strong>Reason:</strong> <?= htmlspecialchars($details['Reason']) ?></p>

    <p><strong>Attachment:</strong>
        <?php if (!empty($details['Attachment'])): ?>
            <a href="<?= htmlspecialchars($details['Attachment']) ?>" target="_blank">View File</a>
        <?php else: ?>
            None
        <?php endif; ?>
    </p>
    <?php if ($details['Status'] === 'Pending'): // Only allow decision if pending ?>
        <form action="adminhome.php" method="POST">
            <input type="hidden" name="leave_id" value="<?= htmlspecialchars($details['LeaveID']) ?>">
            <label for="decision">Decision:</label>
            <select name="decision" id="decision" required>
                <option value="">-- Select --</option>
                <option value="Approved">Approve</option>
                <option value="Rejected">Reject</option>
            </select>
            <br><br>
            <label for="remarks">Remarks:</label><br>
            <textarea name="remarks" id="remarks" rows="4" cols="60" placeholder="Enter remarks (optional)"></textarea>
            <br><br>
            <button type="submit">Submit Decision</button>
        </form>
    <?php else: ?>
        <div style="text-align:center; margin-top:20px; padding:10px; background:#f0f0f0; border:1px solid #ccc; border-radius:4px;">
            <strong>Application Status: <?= htmlspecialchars($details['Status']) ?></strong> (Cannot be modified)
        </div>
    <?php endif; ?>

</div>
<?php } ?>