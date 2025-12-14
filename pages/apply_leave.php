<?php
    // Retrieve latest credit balance for the user
    $credit = $creditManager->getCreditDetails($employeeID);
    
    // Fetch available leave types for dropdown selection
    $leaveManager = new LeaveTypeManager();
    $leaveTypes = $leaveManager->getAllLeaveTypes();
    
    // Note: $sickLeaveID = 2; is inherited from the main controller logic
?>


<div class="apply-container">
    <div class="leave-credit-details">
        <h3>Your Leave Credits</h3>
        <div style="display:flex; gap:15%;">
            <p>
                <strong>Vacation Leave:</strong> 
                <?= number_format($credit['vacation'] ?? 0, 2) ?>
            </p>
            <p><strong>Sick Leave:</strong> <?= number_format($credit['sick'] ?? 0, 2) ?></p>
            <p><strong>Last Updated: </strong> <?= htmlspecialchars($credit['lastUpdated'] ?? '') ?></p>
        </div>  
    </div>
</div>
<div class="apply-card">
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="type">Leave Type:</label>
        <select name="type" id="typeSelect">
            <option value="">--Select--</option>
            
            <?php 
            foreach ($leaveTypes as $leaveType) {
                $id = htmlspecialchars($leaveType['LeaveTypeID']); 
                $name = htmlspecialchars($leaveType['TypeName']); 
                
                $selected = '';
                // Pre-select the option if it matches previous input
                if (isset($inputs['type']) && (string)$inputs['type'] === (string)$id) {
                    $selected = 'selected';
                }
                
                echo "<option value=\"$id\" $selected>$name</option>";
            }
            ?>
        </select>
        <p class="error"><?= htmlspecialchars($error['type'] ?? '') ?></p>
        
        <div class="date-row">
            <label for="start">Start Date:</label>
            <input type="date" name="start" id="startDate" value="<?= htmlspecialchars($inputs['start'] ?? '') ?>">
            <p class="error"><?= htmlspecialchars($error['start'] ?? '') ?></p>
            
            <label for="days">Number of Days:</label>
            <input type="number" name="days" id="numDays" min="1" value="<?= htmlspecialchars($inputs['days'] ?? '') ?>">
            <p class="error"><?= htmlspecialchars($error['days'] ?? '') ?></p>
            
            <label for="end">End Date:</label>
            <input type="date" name="end" id="endDate" value="<?= htmlspecialchars($inputs['end'] ?? '') ?>" readonly>
        </div>
        
        <label for="reason">Reason for leave:</label>
        <textarea name="reason"><?= htmlspecialchars($inputs['reason'] ?? '') ?></textarea>
        <p class="error"><?= htmlspecialchars($error['reason'] ?? '') ?></p>

        <div id="attachmentDiv" style="display: <?= $displayAttachment ?>;"> 
            <p>Supporting document is optional for sick leave.</p>
            <label for="upload">Upload Document:</label>
            <input type="file" name="upload" id="upload">
            <p class="error"><?= htmlspecialchars($error['upload'] ?? '') ?></p>
        </div>

        <button type="submit">Submit</button>
    </form>

    <script>
        // Store DOM references for dynamic form handling
        const startDateInput = document.getElementById('startDate');
        const numDaysInput = document.getElementById('numDays');
        const endDateInput = document.getElementById('endDate');
        const typeSelect = document.getElementById('typeSelect');
        const attachmentDiv = document.getElementById('attachmentDiv');

        // Define Sick Leave ID to match PHP logic for conditional display
        const SICK_LEAVE_ID = '2'; 

        // Calculate end date based on start date and business days duration
        function calculateEndDateJS(start_date_str, num_days) {
            if (!start_date_str || num_days <= 0) {
                return '';
            }
            
            let date = new Date(start_date_str);
            // Compensate for potential time zone offset issues in Date parsing
            date.setDate(date.getDate() + 1); 

            let daysRemaining = num_days; 
            
            // Skip initial weekend days if start date lands on Sat/Sun
            while (date.getDay() === 0 || date.getDay() === 6) { 
                date.setDate(date.getDate() + 1); 
            }
            
            // Loop to subtract only weekdays (Mon-Fri) from remaining count
            while (daysRemaining > 1) { 
                date.setDate(date.getDate() + 1); 
                
                if (date.getDay() !== 0 && date.getDay() !== 6) { 
                    daysRemaining--; 
                }
            }
            
            // Format result as YYYY-MM-DD string
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            
            return `${y}-${m}-${d}`;
        }

        // Trigger end date recalculation and update UI field
        function updateEndDate() {
            const start = startDateInput.value;
            const days = parseInt(numDaysInput.value);

            if (start && days > 0) {
                const endDate = calculateEndDateJS(start, days);
                endDateInput.value = endDate;   
            } else {
                endDateInput.value = '';
            }
        }

        // Bind calculation logic to date and duration input changes
        startDateInput.addEventListener('change', updateEndDate);
        numDaysInput.addEventListener('input', updateEndDate); 
        
        // Show/hide file upload field when leave type changes
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                attachmentDiv.style.display = this.value === SICK_LEAVE_ID ? 'flex' : 'none';
            });
        }

        // Initialize calculation on page load in case of pre-filled values
        updateEndDate();
    </script>

</div>