<?php
// Use the application manager to fetch only pending requests
$leaveList = $applicationManager->getApplicationsByStatus('Pending');
?>
<table>
<thead>
    <tr>
        <th>App. ID</th>
        <th>EmployeeID</th>
        <th>Full Name</th>
        <th>Leave Type</th>
        <th>Date Applied</th>
        <th>Attachment</th>
    </tr>
</thead>
<tbody>
    <?php if (!empty($leaveList)): ?>
        <?php foreach ($leaveList as $leave): ?>
            <tr style="cursor:pointer;" onclick="document.getElementById('form-<?= $leave['LeaveID'] ?>').submit();">
                <form id="form-<?= $leave['LeaveID'] ?>" action="adminhome.php?section=evaluate" method="POST" style="display:none;">
                    <input type="hidden" name="leave_id" value="<?= htmlspecialchars($leave['LeaveID']) ?>">
                </form>
                <td><?= htmlspecialchars($leave['LeaveID']) ?></td>
                <td><?= htmlspecialchars($leave['EmployeeID']) ?></td>
                <td>
                    <?= htmlspecialchars($leave['FirstName'] ?? 'N/A') ?>
                    <?= htmlspecialchars($leave['LastName'] ?? 'N/A') ?>
                </td>
                <td><?= htmlspecialchars($leave['LeaveType'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($leave['DateApplied'] ?? 'N/A') ?></td>
                <td>
                    <?php if (!empty($leave['Attachment'])): ?>
                        <a href="<?= htmlspecialchars($leave['Attachment']) ?>" target="_blank">View</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="6">No pending leave applications found.</td></tr>
    <?php endif; ?>
</tbody>
</table>