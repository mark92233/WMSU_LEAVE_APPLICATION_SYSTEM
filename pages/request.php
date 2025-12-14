<?php 
// NOTE: Assuming manager objects are available from the parent controller (adminhome.php).

// --- 1. HANDLE CSV IMPORT SUBMISSION (Revised Logic) ---
$csvSuccess = "";
$csvError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_import') {
    
    if (empty($_FILES['csv_file']['name'])) {
        $csvError = "Please select a CSV file to upload.";
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $csvError = "File upload failed with error code: " . $_FILES['csv_file']['error'];
    } elseif (pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION) !== 'csv') {
        $csvError = "Invalid file type. Only CSV files are allowed.";
    } else {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        
        // CRITICAL FIX: Call the manager function instead of inline logic
        $results = $reqObj->bulkImportRegistrations($fileTmpPath);
        
        if (isset($results['error'])) {
            $csvError = $results['error'];
        } else {
            $importedCount = $results['importedCount'];
            $skippedCount = $results['skippedCount'];
            $csvSuccess = "Bulk Import Complete: <strong>{$importedCount}</strong> new employees added (Inactive). <strong>{$skippedCount}</strong> rows skipped (Invalid data or already exists).";
        }
    }
}

// --- 2. HANDLE ACTIVATION & PROMOTION SUBMISSIONS (Unmodified) ---
$promoSuccess = "";
$promoError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Handle Activation
    if (isset($_POST['activate_id'])) {
        $activateID = $_POST['activate_id'];
        $userInfo = $reqObj->checkIdExists($activateID);
        
        if ($reqObj->activate($activateID)) {
             if (!empty($userInfo['Email'])) {
                 $emailSender->sendAccountConfirmation($userInfo['Email'], $activateID);
             }
             header("Location: adminhome.php?section=request&msg=activated");
             exit;
        } else {
             $promoError = "Activation failed. The record may not be in 'Pending' status.";
        }
    }

    // B. Handle Promotion / Update
    if (isset($_POST['action']) && $_POST['action'] === 'promote_employee') {
        $targetID   = $_POST['target_id'];
        $newPos     = $_POST['position'];
        $newDept    = $_POST['department'];
        $newType    = $_POST['type'];

        $targetEmployee = $employeeManager->getEmployee($targetID);
        
        if ($targetEmployee) {
            $oldPosName = $targetEmployee['PositionName']; 
            $empEmail = $targetEmployee['Email'];
            $empName = $targetEmployee['FirstName'];

            if ($employeeManager->updateEmploymentDetails($_SESSION['employeeID'], $targetID, $newPos, $newDept, $newType)) {
                
                $updatedEmployee = $employeeManager->getEmployee($targetID);
                $newPosName = $updatedEmployee['PositionName'] ?? 'Updated Position';

                $emailSender->sendPromotionNotification($empEmail, $empName, $newPosName, $oldPosName);

                $promoSuccess = "Employee record updated successfully. Leave credits have been recalculated and notification sent.";
            } else {
                $promoError = "Failed to update record. Please check permissions or database connection.";
            }
        } else {
             $promoError = "Employee not found.";
        }
    }
}

// Fetch Data for Dropdowns and Initial List Display
$departments = $depObj->getAllDepartments();
$positions   = $posObj->getAllPositions();

// Initial load variables
$initialSearch = '';
$initialSelectedDept = ''; 
$allEmployees = $employeeManager->getAllEmployeesDetails($initialSearch); 

?>

<?php if ($Role !== 'Admin'): ?> 
    <div class="alert"> 
        <h2>Access Denied</h2>
        <p>You do not have permission to access this section.</p>
    </div> 
<?php else: ?> 

<style>
    /* ... (CSS styles are kept as they were, assumed to be functional) ... */
    :root {
        --primary-red: #b91c1c;
        --primary-hover: #991b1b;
        --border-color: #e5e7eb;
        --bg-input: #f9fafb;
        --text-main: #1f2937;
    }
    .master-header { display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 20px 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin-bottom: 25px; gap: 20px; flex-wrap: wrap; }
    .header-title h3 { margin: 0; color: var(--text-main); font-size: 1.1rem; font-weight: 700; white-space: nowrap; }
    .search-form { flex: 1; display: flex; justify-content: center; min-width: 300px; }
    .search-group { display: flex; width: 100%; max-width: 450px; position: relative; }
    .search-group input { width: 100%; padding: 12px 16px; font-size: 0.95rem; border: 1px solid var(--border-color); background-color: var(--bg-input); color: var(--text-main); border-radius: 8px; margin-bottom: 0 !important; transition: all 0.2s ease; }
    .search-group input:focus { background-color: #fff; border-color: var(--primary-red); outline: none; box-shadow: inset 0 0 0 1px var(--primary-red); z-index: 2; }
    .filter-group { display: flex; align-items: center; gap: 10px; }
    .filter-group label { margin-bottom: 0 !important; white-space: nowrap; }
    .filter-group select { margin-bottom: 0 !important; padding: 10px 30px 10px 12px; min-width: 180px; background-color: #fff; cursor: pointer; border-radius: 8px; }
    .btn-icon { background: white; border: 1px solid #ddd; cursor: pointer; padding: 6px 10px; border-radius: 6px; transition: all 0.2s; color: #6b7280; margin-left: 5px; }
    .btn-icon:hover { background: #f3f4f6; color: #1f2937; border-color: #bbb; }
    .btn-edit { color: var(--primary-red); border-color: #fecaca; background: #fef2f2; }
    .btn-edit:hover { background: var(--primary-red); color: white; border-color: var(--primary-red); }
    /* NEW STYLES for Import Button */
    .btn-import { background-color: #10b981; color: white; border: 1px solid #10b981; padding: 10px 15px; font-weight: 600; cursor: pointer; border-radius: 8px; margin-bottom: 0 !important; transition: background 0.2s ease; }
    .btn-import:hover { background-color: #059669; }
    /* NEW STYLES for Modals */
    .modal-overlay { z-index: 1000; }
    .form-group { margin-bottom: 15px; text-align: left; }
    #employeeTableBody td.loading { text-align: center; padding: 50px; font-style: italic; color: #6b7280; }
</style>

    <?php if ($promoSuccess): ?>
        <div style="background:#ecfdf5; color:#047857; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #d1fae5; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle"></i> <?= $promoSuccess ?>
        </div>
    <?php endif; ?>
    <?php if ($promoError): ?>
        <div style="background:#fef2f2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle"></i> <?= $promoError ?>
        </div>
    <?php endif; ?>
    <?php if ($csvSuccess): ?>
        <div style="background:#e0f7fa; color:#006064; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #b2ebf2; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-file-import"></i> <?= $csvSuccess ?>
        </div>
    <?php endif; ?>
    <?php if ($csvError): ?>
        <div style="background:#fef2f2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-triangle"></i> <?= $csvError ?>
        </div>
    <?php endif; ?>

    <h3>Pending Account Requests</h3>
    <table> 
        <thead> 
            <tr> 
                <th>Employee ID</th> <th>Email</th> <th>Status</th> <th>Action</th>
            </tr> 
        </thead> 
        <tbody> 
            <?php 
                $requests = $reqObj->getPendingAccounts();
                if (!empty($requests)) { 
                    foreach ($requests as $r) { 
                        $eid = htmlspecialchars($r['EmployeeID'] ?? ''); 
                        $email = htmlspecialchars($r['Email'] ?? '');
                        $status = htmlspecialchars($r['Status'] ?? ''); 
                        echo "<tr> 
                                <td>{$eid}</td> <td>{$email}</td> <td>{$status}</td> 
                                <td> 
                                    <form method='POST' style='margin:0'> 
                                        <input type='hidden' name='activate_id' value='{$eid}'> 
                                        <button type='submit' class='btn-confirm' style='background-color: #166534; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;'>
                                            Confirm
                                        </button> 
                                    </form> 
                                </td> 
                              </tr>"; 
                    } 
                } else { 
                    echo "<tr><td colspan='4' style='text-align:center; color:#888;'>No pending requests.</td></tr>"; 
                }
            ?>
        </tbody> 
    </table> 

    <hr style="margin: 30px 0; border: 1px solid #ddd;">

    <div class="master-header">
        <div class="header-title">
            <h3>Employee Master List</h3>
        </div>

        <div class="search-form">
            <div class="search-group">
                <input type="text" id="searchQuery" placeholder="Search name or ID..." value="">
            </div>
        </div>

        <div class="filter-group">
            <label for="deptFilter">Filter by:</label>
            <select id="deptFilter">
                <option value="">-- All Colleges --</option>
                <?php 
                    if(!empty($departments)){
                        foreach($departments as $d){
                            $dID = htmlspecialchars($d['DepartmentID']);
                            $dName = htmlspecialchars($d['DepartmentName']);
                            $isSelected = ($initialSelectedDept == $dID) ? 'selected' : '';
                            echo "<option value='{$dID}' {$isSelected}>{$dName}</option>";
                        }
                    }
                ?>
            </select>
            
            <button type="button" class="btn-import" onclick="document.getElementById('importModal').style.display='flex'">
                <i class="fas fa-upload" style="margin-right: 5px;"></i> Import
            </button>
        </div>
    </div>

    <table> 
        <thead> 
            <tr> 
                <th>ID</th> <th>Name</th> <th>College</th> <th>Position</th> <th>Actions</th>
            </tr> 
        </thead> 
        <tbody id="employeeTableBody"> 
            <?php 
                // Initial Data Rendering Logic
                $hasRecords = false;
                if (!empty($allEmployees)) { 
                    foreach ($allEmployees as $emp) { 
                        $hasRecords = true;
                        $e_id = htmlspecialchars($emp['EmployeeID'] ?? ''); 
                        $e_first = htmlspecialchars($emp['FirstName'] ?? '');
                        $e_last = htmlspecialchars($emp['LastName'] ?? '');
                        $e_full = $e_last . ', ' . $e_first;
                        $e_dept = htmlspecialchars($emp['DepartmentName'] ?? 'N/A');
                        $e_pos = htmlspecialchars($emp['PositionName'] ?? 'N/A');
                        
                        $jsData = json_encode([
                            'id'        => $emp['EmployeeID'],
                            'full_name' => $emp['FirstName'] . ' ' . $emp['LastName'],
                            'first'     => $emp['FirstName'],
                            'last'      => $emp['LastName'],
                            'dob'       => $emp['DOB'],
                            'sex'       => $emp['Sex'],
                            'contact'   => $emp['ContactNumber'],
                            'email'     => $emp['Email'],
                            'dept_name' => $emp['DepartmentName'],
                            'pos_name'  => $emp['PositionName'],
                            'hired'     => $emp['DateHired'],
                            'teaching'  => $emp['isTeaching'],
                            'pic'       => $emp['profilePic'],
                            'DepartmentID' => $emp['DepartmentID'], 
                            'PositionID' => $emp['PositionID']
                        ]);
                        $safeViewData = htmlspecialchars($jsData, ENT_QUOTES, 'UTF-8');

                        $jsEditData = json_encode([
                            'id'      => $emp['EmployeeID'],
                            'pos'     => $emp['PositionID'],
                            'dept_id' => $emp['DepartmentID'],
                            'type'    => $emp['isTeaching']
                        ]);
                        $safeEditData = htmlspecialchars($jsEditData, ENT_QUOTES, 'UTF-8');

                        echo "<tr class='emp-row' onclick='openModal($safeViewData)'> 
                                <td>{$e_id}</td> 
                                <td>{$e_full}</td> 
                                <td>{$e_dept}</td> 
                                <td>{$e_pos}</td> 
                                <td onclick='event.stopPropagation()'>
                                    <button class='btn-icon btn-edit' onclick='openPromoteModal($safeEditData)' title='Promote / Edit'>
                                        <i class='fas fa-edit'></i>
                                    </button>
                                </td>
                              </tr>"; 
                    } 
                } 
                
                if (!$hasRecords) { 
                    echo "<tr><td colspan='5' style='text-align:center; padding: 30px; color: #6b7280;'>No employees found.</td></tr>"; 
                }
            ?>
        </tbody> 
    </table>

    <div id="employeeModal" class="modal-overlay" onclick="closeModal(event)">
        <div class="profile-card">
            <button class="close-btn" onclick="document.getElementById('employeeModal').style.display='none'">&times;</button>
            <div class="card-header">
                <div class="avatar-container"><img id="m_pic" src="assets/image/default-avatar.png" class="profile-img" alt="Profile"></div>
                <h2 id="m_fullname">Full Name</h2>
                <p id="m_job_title">Position</p>
            </div>
            <div class="card-body" style="overflow-y: auto; max-height: 400px;">
                <div class="section-title">Employment Information</div>
                <div class="row-split">
                    <div class="info-group"><span class="info-label">Employee ID</span><span class="info-value" id="m_id">--</span></div>
                    <div class="info-group"><span class="info-label">Date Hired</span><span class="info-value" id="m_hired">--</span></div>
                </div>
                <div class="info-group"><span class="info-label">College</span><span class="info-value" id="m_dept">--</span></div>
                <div class="info-group"><span class="info-label">Teaching Status</span><span class="info-value" id="m_teaching">--</span></div>
                <div class="section-title">Personal Details</div>
                <div class="info-group"><span class="info-label">Email Address</span><span class="info-value" id="m_email">--</span></div>
                <div class="info-group"><span class="info-label">Contact Number</span><span class="info-value" id="m_contact">--</span></div>
                <div class="row-split">
                    <div class="info-group"><span class="info-label">Date of Birth</span><span class="info-value" id="m_dob">--</span></div>
                    <div class="info-group"><span class="info-label">Sex</span><span class="info-value" id="m_sex">--</span></div>
                </div>
            </div>
        </div>
    </div>

    <div id="promoteModal" class="modal-overlay" style="display: none;" onclick="closePromoteModal(event)">
        <div class="profile-card" style="max-width: 500px;">
            <button class="close-btn" onclick="document.getElementById('promoteModal').style.display='none'">&times;</button>
            
            <div style="padding: 20px 30px 10px; border-bottom: 1px solid #eee;">
                <h2 style="margin:0; color:var(--primary-red); font-size:1.5rem;">Update Employment</h2>
                <p style="margin:5px 0 0; color:#666; font-size:0.9rem;">Promote or transfer employee.</p>
            </div>

            <div style="padding: 30px;">
                <form method="POST">
                    <input type="hidden" name="action" value="promote_employee">
                    <input type="hidden" name="target_id" id="edit_id">

                    <div class="form-group">
                        <label class="form-label">Position / Rank</label>
                        <select name="position" id="edit_pos" class="form-input" required>
                            <?php foreach($positions as $p): ?>
                                <option value="<?= htmlspecialchars($p['PositionID']) ?>"><?= htmlspecialchars($p['PositionName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">College</label>
                        <select name="department" id="edit_dept" class="form-input" required>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= $d['DepartmentID'] ?>"><?= $d['DepartmentName'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Employee Type</label>
                        <select name="type" id="edit_type" class="form-input">
                            <option value="1">Teaching Faculty</option>
                            <option value="0">Non-Teaching Staff</option>
                        </select>
                    </div>

                    <div style="text-align:right; margin-top:20px;">
                        <button type="button" onclick="document.getElementById('promoteModal').style.display='none'" style="padding:10px 20px; background:#f3f4f6; border:none; border-radius:6px; cursor:pointer; margin-right:10px; font-weight:600; color:#555;">Cancel</button>
                        <button type="submit" style="padding:10px 20px; background:var(--primary-red); color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="importModal" class="modal-overlay" style="display: none;" onclick="if(event.target.id === 'importModal') this.style.display='none'">
        <div class="profile-card" style="max-width: 450px;">
            <button class="close-btn" onclick="document.getElementById('importModal').style.display='none'">&times;</button>
            
            <div style="padding: 20px 30px 10px; border-bottom: 1px solid #eee;">
                <h2 style="margin:0; color:var(--primary-red); font-size:1.5rem;">Bulk Employee Import</h2>
                <p style="margin:5px 0 0; color:#666; font-size:0.9rem;">Upload a CSV file containing Employee IDs and Emails.</p>
            </div>

            <div style="padding: 30px;">
                <form method="POST" enctype="multipart/form-data"> 
                    <input type="hidden" name="action" value="bulk_import">
                    
                    <div class="form-group">
                        <label class="form-label">CSV File (EmployeeID, Email)</label>
                        <input type="file" name="csv_file" id="csv_file" class="form-input" accept=".csv" required>
                    </div>
                    
                    <p style="font-size:0.85rem; color:#6b7280; margin-top:-10px;">
                        <i class="fas fa-info-circle"></i> File must contain two columns (EmployeeID, Email). New accounts will be set to 'Inactive'.
                    </p>

                    <div style="text-align:right; margin-top:20px;">
                        <button type="button" onclick="document.getElementById('importModal').style.display='none'" style="padding:10px 20px; background:#f3f4f6; border:none; border-radius:6px; cursor:pointer; margin-right:10px; font-weight:600; color:#555;">Cancel</button>
                        <button type="submit" class="btn-import">
                            <i class="fas fa-file-import"></i> Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
        // --- Modal Logic (openModal, closeModal, openPromoteModal, closePromoteModal) ---
        function openModal(data) {
            document.getElementById('m_id').innerText = data.id;
            document.getElementById('m_fullname').innerText = data.full_name;
            document.getElementById('m_job_title').innerText = data.pos_name;
            document.getElementById('m_dept').innerText = data.dept_name;
            document.getElementById('m_email').innerText = data.email;
            document.getElementById('m_contact').innerText = data.contact;
            document.getElementById('m_dob').innerText = data.dob;
            document.getElementById('m_sex').innerText = data.sex;
            document.getElementById('m_hired').innerText = data.hired;
            document.getElementById('m_teaching').innerText = (data.teaching == '1') ? 'Teaching Faculty' : 'Non-Teaching Staff';

            var imgElement = document.getElementById('m_pic');
            if (data.pic && data.pic.trim() !== "" && data.pic !== "N/A") { 
                imgElement.src = data.pic.startsWith('assets') ? data.pic : "assets/" + data.pic;
            } else {
                imgElement.src = "assets/image/default-avatar.png"; 
            }
            document.getElementById('employeeModal').style.display = 'flex';
        }

        function closeModal(event) {
            if (event.target.id === 'employeeModal') {
                document.getElementById('employeeModal').style.display = 'none';
            }
        }

        function openPromoteModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_pos').value = data.pos; 
            document.getElementById('edit_dept').value = data.dept_id;
            document.getElementById('edit_type').value = data.type;
            document.getElementById('promoteModal').style.display = 'flex';
        }

        function closePromoteModal(event) {
            if (event.target.id === 'promoteModal') {
                document.getElementById('promoteModal').style.display = 'none';
            }
        }

        // --- REAL-TIME SEARCH IMPLEMENTATION ---

        const searchInput = document.getElementById('searchQuery');
        const deptFilter = document.getElementById('deptFilter');
        const tableBody = document.getElementById('employeeTableBody');
        let searchTimeout;

        function fetchEmployeeList() {
            clearTimeout(searchTimeout);
            
            const query = searchInput.value;
            const deptId = deptFilter.value;

            if (query.length < 2 && query.length > 0) {
                searchTimeout = setTimeout(fetchEmployeeList, 300);
                return; 
            }

            tableBody.innerHTML = '<tr><td colspan="5" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading results...</td></tr>';
            
            const params = new URLSearchParams({
                action: 'search_employees',
                search_query: query,
                dept_filter: deptId
            });

            fetch('adminhome.php?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                renderTable(data);
            })
            .catch(error => {
                console.error('AJAX Fetch Error:', error);
                tableBody.innerHTML = '<tr><td colspan="5" class="loading" style="color:red;">Error fetching data. Please refresh or check connection.</td></tr>';
            });
        }
        
        function renderTable(employees) {
            let html = '';
            
            if (employees.length === 0) {
                html = "<tr><td colspan='5' style='text-align:center; padding: 30px; color: #6b7280;'>No employees found matching the criteria.</td></tr>";
            } else {
                employees.forEach(emp => {
                    const fullName = `${emp.LastName || ''}, ${emp.FirstName || ''}`;
                    const viewData = JSON.stringify(emp).replace(/'/g, "\\'"); 

                    html += `<tr class='emp-row' onclick='openModal(JSON.parse("${viewData}"))'>
                                <td>${emp.EmployeeID}</td>
                                <td>${fullName}</td>
                                <td>${emp.DepartmentName || 'N/A'}</td>
                                <td>${emp.PositionName || 'N/A'}</td>
                                <td onclick='event.stopPropagation()'>
                                    <button class='btn-icon btn-edit' onclick='openPromoteModal(JSON.parse("${viewData}"))' title='Promote / Edit'>
                                        <i class='fas fa-edit'></i>
                                    </button>
                                </td>
                            </tr>`;
                });
            }
            
            tableBody.innerHTML = html;
        }

        // Event listener for search bar input
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(fetchEmployeeList, 300);
        });

        // Event listener for department filter change
        deptFilter.addEventListener('change', fetchEmployeeList);
    </script>
<?php endif; ?>