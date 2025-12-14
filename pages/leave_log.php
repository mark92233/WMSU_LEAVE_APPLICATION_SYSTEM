<?php
    $myLog = $applicationManager->getEmployeeApplications($employeeID);
?>
<table>
    <thead>
        <tr>
            <th>App. ID</th>
            <th>Leave Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Date Applied</th>
            <th>Attachment</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($myLog)): ?>
            <?php foreach ($myLog as $leave) :?>
                <tr style="cursor:pointer;" onclick="document.getElementById('view-form-<?= $leave['LeaveID'] ?>').submit();">
                    <form id="view-form-<?= $leave['LeaveID'] ?>" action="<?= $Role !== 'Admin' ? 'home.php' : 'adminhome.php' ?>?section=view_log" method="POST" style="display:none;">
                        <input type="hidden" name="leave_id" value="<?= htmlspecialchars($leave['LeaveID']) ?>">
                    </form>
                    <td><?= htmlspecialchars($leave['LeaveID']) ?></td>
                    <td><?= htmlspecialchars($leave['LeaveType'] ?? 'N/A') ?></td> 
                    <td><?= htmlspecialchars($leave['StartDate']) ?></td>
                    <td><?= htmlspecialchars($leave['EndDate']) ?></td> 
                    <td><?= htmlspecialchars($leave['DateApplied']) ?></td>
                    <td>
                        <?php if (!empty($leave['Attachment'])): ?>
                            <a href="<?= htmlspecialchars($leave['Attachment']) ?>" target="_blank">View</a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($leave['Status']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8">No leave logs found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>