<?php
// Note: This file assumes 'db_connect.php' exists and defines a Database class
// with a public method connect() that returns a PDO connection object.
require_once "db_connect.php";

// =======================================================================
// CORE MANAGEMENT CLASSES (Admin, Employee, Account)
// =======================================================================

/**
 * Class for managing Employee registration and data.
 * Interacts with `employee`, `department`, and `jobposition` tables.
 */
class EmployeeManager {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }
    
    // Create: Insert a new employee record into the 'employee' table
    public function registerEmployee($data) {
        $sql = "INSERT INTO employee (
                    EmployeeID, FirstName, MiddleName, LastName, DOB, Sex, ContactNumber, 
                    Email, DepartmentID, PositionID, DateHired, isTeaching, profilePic
                ) VALUES (
                    :EmployeeID, :FirstName, :MiddleName, :LastName, :DOB, :Sex, :ContactNumber, 
                    :Email, :DepartmentID, :PositionID, :DateHired, :isTeaching, :profilePic
                )";
        $query = $this->db->connect()->prepare($sql);
        return $query->execute($data);
    }
    
    // Read: Fetch a single employee record
public function getEmployee($employeeID) {
    // We use aliases (e, d, jp) to keep the query clean
    $sql = "SELECT 
                e.*, 
                d.DepartmentName, 
                jp.PositionName 
            FROM employee e
            LEFT JOIN department d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN jobposition jp ON e.PositionID = jp.PositionID
            WHERE e.EmployeeID = :EmployeeID";

    $query = $this->db->connect()->prepare($sql);
    $query->bindParam(":EmployeeID", $employeeID);
    $query->execute();
    return $query->fetch(PDO::FETCH_ASSOC);
}

    // Read: Fetch all employee records with their Department and Position names
   public function getAllEmployeesDetails($searchQuery = "") {
    // 1. Base SQL (Always runs)
    $sql = "SELECT 
                e.*, 
                d.DepartmentName, 
                p.PositionName
            FROM 
                employee e
            LEFT JOIN 
                department d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN 
                jobposition p ON e.PositionID = p.PositionID";

    // 2. Dynamic Search (Only runs if user typed something)
    if (!empty($searchQuery)) {
        $sql .= " WHERE e.FirstName LIKE :search 
                  OR e.LastName LIKE :search 
                  OR e.EmployeeID LIKE :search";
    }

    // 3. Ordering (Always runs)
    $sql .= " ORDER BY e.LastName";

    $query = $this->db->connect()->prepare($sql);

    // 4. Binding (Only runs if user typed something)
    if (!empty($searchQuery)) {
        $searchTerm = "%" . $searchQuery . "%";
        $query->bindParam(':search', $searchTerm);
    }

    $query->execute();
    return $query->fetchAll(PDO::FETCH_ASSOC);
}
    
    // Update: Update an employee record (simplified for essential fields)
    public function updateEmployee($employeeID, $data) {
        $setClauses = [];
        foreach ($data as $key => $value) {
            if ($key !== 'EmployeeID') {
                $setClauses[] = "$key = :$key";
            }
        }
        
        $sql = "UPDATE employee SET " . implode(', ', $setClauses) . " WHERE EmployeeID = :EmployeeID";
        $query = $this->db->connect()->prepare($sql);
        $data['EmployeeID'] = $employeeID;
        return $query->execute($data);
    }
    
    // Delete: Remove an employee record
    public function deleteEmployee($employeeID) {
        $sql = "DELETE FROM employee WHERE EmployeeID = :EmployeeID";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":EmployeeID", $employeeID);
        return $query->execute();
    }
    
    // Read: Get the total count of active employees (for Dashboard)
    public function getTotalEmployeeCount() {
        $sql = "SELECT COUNT(*) FROM employee";
        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        return (int)$query->fetchColumn(); 
    }

    public function getAccrualData($employeeID) {
    $sql = "SELECT DateHired, PositionID FROM employee WHERE EmployeeID = :employeeID";
    $query = $this->db->connect()->prepare($sql);
    $query->bindParam(":employeeID", $employeeID);
    $query->execute();
    return $query->fetch(PDO::FETCH_ASSOC);
}

public function getEmployeeCountByDepartment() {
    // We join the tables using DepartmentID, but we still group by DepartmentName
    // so the result is easy to read (e.g., "CCS" => 10).
    $sql = "SELECT d.DepartmentName, COUNT(e.EmployeeID) as Total
            FROM department d
            LEFT JOIN employee e ON d.DepartmentID = e.DepartmentID
            GROUP BY d.DepartmentName";

    $query = $this->db->connect()->prepare($sql);
    $query->execute();

    // Returns an array like: ['CCS' => 5, 'Nursing' => 12]
    return $query->fetchAll(PDO::FETCH_KEY_PAIR); 
}

  public function updateEmploymentDetails($adminID, $targetEmployeeID, $newPosition, $newDept, $newType) {
        $pdo = $this->db->connect();

        // 1. Security Check
        $check = $pdo->prepare("SELECT Role FROM account WHERE EmployeeID = :adminID");
        $check->execute([':adminID' => $adminID]);
        $role = $check->fetchColumn();

        if ($role !== 'Admin' && $role !== 'HR') {
            return false; 
        }

        try {
            $pdo->beginTransaction();

            // 2. Cash Out Old Credits
            $creditManager = new LeaveCreditManager();
            $appManager = new LeaveApplicationManager();
            $creditManager->updateAccruedCredits($targetEmployeeID, $this, $appManager);

            // 3. Update Employee Record
            // FIX: Changed 'PositionName' to 'PositionID'
            // FIX: Ensure $newPosition contains the ID (Integer), not the String
            $sql = "UPDATE employee SET 
                        PositionID = :pos, 
                        DepartmentID = :dept, 
                        isTeaching = :type 
                    WHERE EmployeeID = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pos'  => $newPosition, // This must be the PositionID (e.g., 1, 2, 5)
                ':dept' => $newDept,
                ':type' => $newType,
                ':id'   => $targetEmployeeID
            ]);

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Keep debugging on until it works
            var_dump("SQL ERROR: " . $e->getMessage()); 
            die(); 

            return false;
        }
    }
}

// -----------------------------------------------------------------------

/**
 * Class for managing Admin account status.
 * Interacts with `adminregistration` table.
 */
class AdminRegistration {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Check if EmployeeID exists in the adminregistration table
    public function checkIdExists($employeeID) {
        $sql = "SELECT * FROM adminregistration WHERE EmployeeID = :employeeID";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC);
    }
    
    // Register ID with 'Pending' status in adminregistration
    public function registerPending($employeeID, $email) {
        $sql = "INSERT INTO adminregistration (EmployeeID, Email, Status) 
                VALUES (:employeeID, :email, 'Pending')";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        $query->bindParam(":email", $email);
        return $query->execute();
    }

    // Set account status to 'Active'
    public function activate($employeeID) {
        $sql = "UPDATE adminregistration SET Status = 'Active' WHERE EmployeeID = :employeeID";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        return $query->execute();
    }

    // Set account status to 'Inactive'
    public function deactivate($employeeID) {
        $sql = "UPDATE adminregistration SET Status = 'Inactive' WHERE EmployeeID = :employeeID";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        return $query->execute();
    }
    
   public function softDelete($employeeID) {
    // Now we explicitly mark them as 'Deleted'
    $sql = "UPDATE adminregistration SET Status = 'Deleted' WHERE EmployeeID = :employeeID";
    $query = $this->db->connect()->prepare($sql);
    $query->bindParam(":employeeID", $employeeID);
    return $query->execute();
}
    // Get all pending accounts
    public function getPendingAccounts() {
        $sql = "SELECT * FROM adminregistration WHERE Status = 'Pending' ORDER BY DateAdded DESC";
        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function bulkImportRegistrations(string $fileTmpPath): array {
        $pdo = $this->db->connect(); 
        $importedCount = 0;
        $skippedCount = 0;
        
        $handle = fopen($fileTmpPath, "r");
        if ($handle === FALSE) {
            // Cannot open file, this should ideally be caught in the view handler too
            return ['importedCount' => 0, 'skippedCount' => 0, 'error' => "Could not open the uploaded file."];
        }

        // Assuming the first row is a header, skip it
        fgetcsv($handle); 

        try {
            // Use INSERT IGNORE to skip existing EmployeeIDs
            $sql = "INSERT IGNORE INTO adminregistration (EmployeeID, Email, Status) 
                    VALUES (:EmployeeID, :Email, 'Inactive')";
            $stmt = $pdo->prepare($sql);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Ensure the row has at least 2 columns: EmployeeID, Email
                if (count($data) >= 2) {
                    $employeeId = trim($data[0]);
                    $email = trim($data[1]);
                    
                    // Basic validation
                    if (empty($employeeId) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $stmt->execute([
                        ':EmployeeID' => $employeeId,
                        ':Email' => $email
                    ]);
                    
                    // Check if a row was actually inserted (not ignored)
                    if ($stmt->rowCount() > 0) {
                        $importedCount++;
                    } else {
                        // Skipped because EmployeeID already exists in adminregistration or invalid data
                        $skippedCount++; 
                    }
                } else {
                    $skippedCount++;
                }
            }
            
        } catch (PDOException $e) {
            error_log("CSV Import DB Error: " . $e->getMessage());
            return ['importedCount' => 0, 'skippedCount' => 0, 'error' => "A database error occurred during import."];
        } finally {
            fclose($handle);
        }
        
        return ['importedCount' => $importedCount, 'skippedCount' => $skippedCount];
    }
}

// -----------------------------------------------------------------------

/**
 * Class for managing account login and credential verification.
 */
class AccountSetup {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAccountByEmployeeID($employeeID) {
        $sql = "SELECT 
                    a.EmployeeID, 
                    a.Email, 
                    ac.Role, 
                    e.FirstName AS Username, 
                    ac.Password
                FROM 
                    adminregistration a
                JOIN 
                    employee e ON a.EmployeeID = e.EmployeeID
                JOIN
                    account ac ON a.EmployeeID = ac.EmployeeID
                WHERE a.EmployeeID = :employeeID AND a.status = 'Active'";
        
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function createAccount($employeeID, $username, $password, $role) {
    try {
        $pdo = $this->db->connect();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO account (EmployeeID, Username, Password, Role) 
                VALUES (:eid, :user, :pass, :role)";
        
        $query = $pdo->prepare($sql);
        
        return $query->execute([
            ':eid' => $employeeID,
            ':user' => $username,
            ':pass' => $hashedPassword,
            ':role' => $role
        ]);
    } catch (Exception $e) {
        // Log the error $e->getMessage() for debugging (e.g., MySQL Duplicate Entry)
        return false;
    }
}
 public function changePassword($employeeID, $newPassword) {
        try {
            $pdo = $this->db->connect();
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE account SET Password = :pass WHERE EmployeeID = :eid";
            $stmt = $pdo->prepare($sql);
            
            return $stmt->execute([
                ':pass' => $hashedPassword,
                ':eid' => $employeeID
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}


// =======================================================================
// LEAVE MANAGEMENT CLASSES
// =======================================================================


/**
 * Class for managing employee leave credits.
 * Interacts with `leavecredits` table.
 *//**
 * Class for managing employee leave credits.
 * Interacts with `leavecredits` table.
 */
class LeaveCreditManager {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }
    
    // Read: Fetch an employee's leave credit details
       public function getCreditDetails($employeeID) {
        $sql = "SELECT * FROM leavecredits WHERE EmployeeID = :employeeID";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        
        if ($query->execute()) {
            // FIX: Use fetch() instead of fetchAll() 
            // because an employee only has ONE row of credits.
            return $query->fetch(PDO::FETCH_ASSOC); 
        } else {
            return false; 
        }
       }

    /**
     * DEDUCT CREDITS (Used when Leave is Approved)
     * Matches table structure: `sick`, `vacation`
     * CRITICAL: This does NOT update 'lastUpdated'.
     */
    public function adjustLeaveCredit($employeeID, $leaveType, $days) {
        try {
            $pdo = $this->db->connect();
            
            // 1. Map Leave Type to Column Name (Matches your DB columns)
            $column = '';
            if (stripos($leaveType, 'Sick') !== false) {
                $column = 'sick';
            } elseif (stripos($leaveType, 'Vacation') !== false) {
                $column = 'vacation';
            } else {
                return true; // Ignore types that don't have credit banks
            }

            // 2. Perform the Deduction
            // We use addition because $days is passed as a negative number from ApprovalManager
            // Example SQL: UPDATE leavecredits SET sick = sick + (-3)
            $sql = "UPDATE leavecredits SET $column = $column + :days WHERE EmployeeID = :eid";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':days' => $days,
                ':eid'  => $employeeID
            ]);

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * INCREMENTAL ACCRUAL (The "Paycheck" Method)
     * Calculates credits earned ONLY since the last update/login.
     */
    public function updateAccruedCredits($employeeID, $employeeManager, $applicationManager) {
        try {
            $pdo = $this->db->connect();

            // 1. Get Credit Record
            $sql = "SELECT * FROM leavecredits WHERE EmployeeID = :eid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':eid' => $employeeID]);
            $creditRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            $empData = $employeeManager->getAccrualData($employeeID);
            if (!$empData) return false;

            // 2. Determine Start Date for Calculation
            if (!$creditRecord) {
                $lastUpdateDate = new DateTime($empData["DateHired"]);
                // Create initial record if missing
                $initSql = "INSERT INTO leavecredits (EmployeeID, sick, vacation, lastUpdated) VALUES (:eid, 0, 0, :hired)";
                $initStmt = $pdo->prepare($initSql);
                $initStmt->execute([':eid' => $employeeID, ':hired' => $empData["DateHired"]]);
            } else {
                $lastUpdateDate = new DateTime($creditRecord['lastUpdated']);
            }

            // 3. Calculate Time Passed (Months)
            $today = new DateTime();
            $interval = $lastUpdateDate->diff($today);
            $monthsPassed = ($interval->y * 12) + $interval->m;

            if ($monthsPassed < 1) {
                return true; // Less than a month passed, no accrual yet
            }

            // 4. Define Rates based on Position
            $position = (int)$empData["PositionID"];
            switch ($position) {
                case 1: $sickRate = 5/12; $vacRate = 10/12; break; 
                case 2: $sickRate = 10/12; $vacRate = 15/12; break; 
                case 3: $sickRate = 15/12; $vacRate = 20/12; break; 
                case 4: $sickRate = 10/12; $vacRate = 15/12; break; 
                default: $sickRate = 5/12; $vacRate = 10/12; break;
            }

            // 5. Calculate New Earnings
            $earnedSick = $monthsPassed * $sickRate;
            $earnedVacation = $monthsPassed * $vacRate;

            // 6. Deposit Earnings & Update Timestamp
            $updateSql = "UPDATE leavecredits SET 
                            sick = sick + :addSick, 
                            vacation = vacation + :addVac, 
                            lastUpdated = NOW() 
                          WHERE EmployeeID = :eid";
            
            $updateStmt = $pdo->prepare($updateSql);
            return $updateStmt->execute([
                ':addSick' => $earnedSick,
                ':addVac' => $earnedVacation,
                ':eid' => $employeeID
            ]);

        } catch (Exception $e) {
            return false;
        }
    }
}

// -----------------------------------------------------------------------

/**
 * Class for managing leave applications.
 * Interacts with `leaveapplication` table.
 */
class LeaveApplicationManager {
    protected $db;

    public function __construct() {
        $this->db = new Database(); 
    }
protected function generateUniqueLeaveID() {
    $pdo = $this->db->connect();
    $unique = false;
    $leaveID = '';

    while (!$unique) {
        $leaveID = strval(mt_rand(100000, 999999));
        $sql = "SELECT LeaveID FROM leaveapplication WHERE LeaveID = :leaveID";
        $query = $pdo->prepare($sql);
        $query->bindParam(':leaveID', $leaveID);
        $query->execute();

        if (!$query->fetch()) {
            $unique = true;
        }
    }
    return $leaveID;
}
public function submitApplication($data)
    {
        // 1. Establish connection and begin transaction
        try {
            $pdo = $this->db->connect(); 
            $pdo->beginTransaction(); 

            // 2. Generate Leave ID
            $leaveID = $this->generateUniqueLeaveID();
            $data['LeaveID'] = $leaveID;

            // --- 3. Leave Application Insertion ---
            $sql_leave = "INSERT INTO leaveapplication (
                              LeaveID, EmployeeID, LeaveTypeID, StartDate, EndDate, NumberOfDays, Reason, Attachment, Status, DateApplied
                          ) VALUES (
                              :LeaveID, :EmployeeID, :LeaveType, :StartDate, :EndDate, :NumberOfDays, :Reason, :Attachment, 'Pending', CURDATE()
                          )";

            $query_leave = $pdo->prepare($sql_leave);
            
            $success_leave = $query_leave->execute([
                ':LeaveID'      => $data['LeaveID'],
                ':EmployeeID'   => $data['EmployeeID'],
                ':LeaveType'    => $data['LeaveType'], 
                ':StartDate'    => $data['StartDate'],
                ':EndDate'      => $data['EndDate'],
                ':NumberOfDays' => $data['NumberOfDays'],
                ':Reason'       => $data['Reason'],
                ':Attachment'   => $data['Attachment'] ?? null 
            ]);

            if (!$success_leave) {
                throw new \Exception("Failed to insert Leave Application.");
            }

            // --- 4. Notification Insertion ---
            $sql_notif = "INSERT INTO notification (EmployeeID, status, purpose) 
                          VALUES (:EmployeeID, :status, :purpose)";
            
            $query_notif = $pdo->prepare($sql_notif);
            
            $success_notif = $query_notif->execute([
                ':EmployeeID' => $data['EmployeeID'], 
                ':status'     => 'unread', 
                ':purpose'    => 'apply' 
            ]);
            
            if (!$success_notif) {
                 throw new \Exception("Failed to insert Notification.");
            }

            // 5. Commit transaction
            $pdo->commit();
            return true;

        } catch (PDOException $e) {
            // >>> DEBUGGING CHANGE: DISPLAY THE PDO ERROR <<<
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Display error and stop execution
            die("DATABASE ERROR: " . $e->getMessage() . " (Please copy this message for debugging)");
            return false; // This line won't be reached, but is here for completeness

        } catch (\Exception $e) {
            // >>> DEBUGGING CHANGE: DISPLAY THE GENERAL EXCEPTION ERROR <<<
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Display error and stop execution
            die("APPLICATION LOGIC ERROR: " . $e->getMessage());
            return false; 
        }
    }

    // Read: Get applications by status (e.g., for admin dashboard)
    public function getApplicationsByStatus($status = 'Pending') {
    $sql = "SELECT 
                la.*, 
                e.FirstName, e.LastName, -- <<< FIX 1: Explicitly select first and last names
                lt.TypeName AS LeaveType -- <<< FIX 2: Joins the descriptive name
            FROM 
                leaveapplication la
            JOIN 
                employee e ON la.EmployeeID = e.EmployeeID
            -- CRITICAL FIX: Join leavetype table to get the name
            JOIN 
                leavetype lt ON la.LeaveTypeID = lt.LeaveTypeID 
            WHERE 
                la.Status = :status
            ORDER BY la.DateApplied ASC";
            
    try {
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":status", $status);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Error in getApplicationsByStatus: " . $e->getMessage());
        return [];
    }
}
    
    // Read: Get all applications for a specific employee
 public function getEmployeeApplications($employeeID) {
    $sql = "SELECT 
                la.*, 
                lt.TypeName AS LeaveType -- <<< CRITICAL FIX: Joins the name
            FROM leaveapplication la 
            -- CRITICAL FIX: Join leavetype table
            JOIN leavetype lt ON la.LeaveTypeID = lt.LeaveTypeID 
            WHERE la.EmployeeID = :employeeID 
            ORDER BY la.DateApplied DESC";
            
    try {
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":employeeID", $employeeID);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Error in getEmployeeApplications: " . $e->getMessage());
        return [];
    }
}
    
    // Read: Get a single application detail
public function getOneApplication($applicationID) {
    $sql = "SELECT 
                la.*, 
                e.*, 
                d.DepartmentName, 
                p.PositionName,
                lt.TypeName AS LeaveType, -- <<< CRITICAL FIX: Joins leave type name
                la.NumberOfDays 
            FROM 
                leaveapplication la
            JOIN 
                employee e ON la.EmployeeID = e.EmployeeID 
            LEFT JOIN 
                department d ON e.DepartmentID = d.DepartmentID 
            LEFT JOIN 
                jobposition p ON e.PositionID = p.PositionID
            -- CRITICAL FIX: Add JOIN for leavetype table
            LEFT JOIN
                leavetype lt ON la.LeaveTypeID = lt.LeaveTypeID
            WHERE 
                la.LeaveID = :applicationID";
    
    try {
        // Use $this->db->connect() once per operation if transactions are not involved, 
        // though it's best to handle connection state consistently.
        $query = $this->db->connect()->prepare($sql); 
        $query->bindParam(":applicationID", $applicationID);
        $query->execute();
        
        return $query->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("DB Error in getOneApplication: " . $e->getMessage());
        return null; // Return null on failure, matching the view's check
    }
}
    // Update: Update the status of an application
    public function updateApplicationStatus($applicationID, $status) {
        $sql = "UPDATE leaveapplication SET Status = :status WHERE LeaveID = :applicationID";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":status", $status);
        $query->bindParam(":applicationID", $applicationID);
        return $query->execute();
    }

    // Read: Get counts of applications by status
    public function getStatusCounts() {
        $conn = $this->db->connect();

        $sql = "
            SELECT Status, COUNT(*) AS total 
            FROM leaveapplication
            GROUP BY Status
        ";

        $query = $conn->prepare($sql);
        $query->execute();

        $statusCounts = [
            'Pending' => 0,
            'Approved' => 0,
            'Rejected' => 0
        ];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $status = $row['Status'];
            if (isset($statusCounts[$status])) {
                $statusCounts[$status] = (int)$row['total'];
            }
        }

        return $statusCounts;
    }
    
    // Dashboard: Counts the number of employees currently on approved leave.
    public function getEmployeesOnLeaveCount() {
        $sql = "SELECT COUNT(DISTINCT EmployeeID) AS onLeaveCount
                FROM leaveapplication
                WHERE Status = 'Approved' 
                AND StartDate <= CURDATE() 
                AND EndDate >= CURDATE()";
        
        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        return (int)$query->fetchColumn(); 
    }

    // Dashboard: Calculates the total number of approved leave days used, broken down by type.
    // ACTION: Uses DATEDIFF() to calculate total days instead of SUM(NumberOfDays).
    public function getTotalLeaveDaysByType() {
        $sql = "SELECT 
                        lt.TypeName AS LeaveType, -- Fetching the name from the joined table
                        SUM(DATEDIFF(l.EndDate, l.StartDate) + 1) AS totalDays
                      FROM leaveapplication l
                      JOIN leavetype lt ON l.LeaveTypeID = lt.LeaveTypeID -- Joining to the leavetype table
                      WHERE l.Status = 'Approved'
                      GROUP BY lt.TypeName";
                
        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        $result = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['LeaveType'] . ' Leave'] = (int)$row['totalDays'];
        }

        // Ensure both types are present for consistent display
        $result['Sick Leave'] = $result['Sick Leave'] ?? 0;
        $result['Vacation Leave'] = $result['Vacation Leave'] ?? 0;
        
        return $result;
    }
    
    // Dashboard: Gets a count of leave applications submitted per month for the current year.
    public function getMonthlyLeaveApplications() {
        $sql = "SELECT 
                    MONTH(DateApplied) AS month_num,
                    COUNT(*) AS count
                FROM leaveapplication
                WHERE YEAR(DateApplied) = YEAR(CURDATE())
                GROUP BY month_num
                ORDER BY month_num";

        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        $dbData = $query->fetchAll(PDO::FETCH_ASSOC);

        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $data = array_fill(0, 12, 0); 

        foreach ($dbData as $row) {
            $data[$row['month_num'] - 1] = (int)$row['count'];
        }

        return [
            'months' => $months,
            'data' => $data,
        ];
    }
    public function getEmployeesOnLeaveByType($leaveType) {
    $sql = "SELECT 
                          COUNT(DISTINCT l.EmployeeID) 
                      FROM leaveapplication l
                      JOIN leavetype lt ON l.LeaveTypeID = lt.LeaveTypeID -- Joining to match the TypeName
                      WHERE l.Status = 'Approved' 
                        AND lt.TypeName = :leaveType  -- Matching the parameter against the TypeName
                        AND l.StartDate <= CURDATE() 
                        AND l.EndDate >= CURDATE()";
    
    $query = $this->db->connect()->prepare($sql);
    $query->bindParam(':leaveType', $leaveType);
    $query->execute();
    return (int)$query->fetchColumn(); 
}
public function getOneApplicationWithApproval($leaveID) {
    $sql = "SELECT 
                la.*, 
                e.*, 
                d.DepartmentName,      
                p.PositionName,          
                la.NumberOfDays,
                lt.TypeName AS LeaveType, -- <<< CRITICAL FIX: Joins the descriptive name
                
                -- Approval Details from the separate table
                a.EmployeeApproverID,
                a.Remarks AS ApproverRemarks,
                a.DecidedDate
            FROM 
                leaveapplication la
            JOIN 
                employee e ON la.EmployeeID = e.EmployeeID 
            LEFT JOIN       
                department d ON e.DepartmentID = d.DepartmentID 
            LEFT JOIN       
                jobposition p ON e.PositionID = p.PositionID
            -- CRITICAL FIX: Join the leavetype table to get the name
            LEFT JOIN
                leavetype lt ON la.LeaveTypeID = lt.LeaveTypeID
            LEFT JOIN               
                leaveapproval a ON la.LeaveID = a.LeaveID
            WHERE 
                la.LeaveID = :leaveID";
    
    try {
        // Use $this->db->connect() to get the PDO object
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":leaveID", $leaveID);
        $query->execute();
        
        // Return the single resulting row
        return $query->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Log the error for review
        error_log("DB Error in getOneApplicationWithApproval: " . $e->getMessage());
        // Return null on failure, which the view handles with "Error: Leave Log not found"
        return null; 
    }
}
public function getCumulativeUsedDays($employeeID) {
    // This query sums the stored NumberOfDays column for all approved leave
    $sql = "SELECT 
                LeaveType, 
                SUM(NumberOfDays) AS totalUsed 
            FROM leaveapplication
            WHERE EmployeeID = :employeeID 
            AND Status = 'Approved'
            GROUP BY LeaveType";
            
    $query = $this->db->connect()->prepare($sql);
    $query->bindParam(':employeeID', $employeeID);
    $query->execute();
    
    $results = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Ensure both keys exist, defaulting to 0
    return [
        'Sick' => (float)($results['Sick'] ?? 0),
        'Vacation' => (float)($results['Vacation'] ?? 0)
    ];
}
}

// -----------------------------------------------------------------------

/**
 * Class for managing the leave approval workflow.
 * Interacts with `leaveapproval` and uses `LeaveApplicationManager` to update status.
 */
class LeaveApprovalManager {
    protected $db;
    protected $appManager;
    protected $creditManager;

    public function __construct() {
        // Assuming Database class is available for connection
        $this->db = new Database();
        // Assuming Manager classes are available for dependency injection
        $this->appManager = new LeaveApplicationManager(); 
        $this->creditManager = new LeaveCreditManager();
    }

    protected function generateUniqueApprovalID() {
    $pdo = $this->db->connect();
    $unique = false;
    $id = '';

    while (!$unique) {
        // Generate a 10-character random string/number
        $id = substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', 10)), 0, 10);
        
        // Check if the ID exists
        $sql = "SELECT ApprovalID FROM leaveapproval WHERE ApprovalID = :id";
        $query = $pdo->prepare($sql);
        $query->bindParam(':id', $id);
        $query->execute();

        if (!$query->fetch()) {
            $unique = true;
        }
    }
    return $id;
}
 public function processApproval($applicationID, $adminID, $status, $remarks = null) {
    try {
        $pdo = $this->db->connect();
        
        // 0. Get the Real Role of the Approver (Existing Logic - OK)
        $roleStmt = $pdo->prepare("SELECT Role FROM account WHERE EmployeeID = :eid");
        $roleStmt->execute([':eid' => $adminID]);
        $approverRole = $roleStmt->fetchColumn() ?: 'Admin';

        $pdo->beginTransaction();

        // 1. Generate Unique ApprovalID (Existing Logic - OK)
        $approvalID = $this->generateUniqueApprovalID(); 
        
        // 2. Update status in leaveapplication table (Existing Logic - OK)
        $this->appManager->updateApplicationStatus($applicationID, $status);

        // 3. Insert record into leaveapproval table (Existing Logic - OK)
        $sqlLog = "INSERT INTO leaveapproval (
                        ApprovalID, LeaveID, EmployeeApproverID, ApproverRole, Decision, Remarks
                    ) VALUES (
                        :approvalID, :leaveID, :approverID, :approverRole, :status, :remarks
                    )";
        
        $queryLog = $pdo->prepare($sqlLog);
        $queryLog->execute([
            ":approvalID"   => $approvalID,
            ":leaveID"      => $applicationID,
            ":approverID"   => $adminID,
            ":approverRole" => $approverRole, 
            ":status"       => $status,
            ":remarks"      => $remarks
        ]);

        // --- 4. Deduct credit if approved (CRITICAL FIX APPLIED) ---
        if ($status === 'Approved') {
            
            // CRITICAL FIX: JOIN leavetype to get TypeName needed by adjustLeaveCredit
            $appSql = "SELECT 
                           la.EmployeeID, 
                           lt.TypeName AS LeaveType, -- <<< NOW returns the name
                           la.NumberOfDays 
                       FROM leaveapplication la
                       JOIN leavetype lt ON la.LeaveTypeID = lt.LeaveTypeID
                       WHERE la.LeaveID = :id";
            
            $appQuery = $pdo->prepare($appSql);
            $appQuery->execute([':id' => $applicationID]);
            $appDetails = $appQuery->fetch(PDO::FETCH_ASSOC);
            
            if ($appDetails) {
                $daysToDeduct = -abs($appDetails['NumberOfDays']); 
                
                // Call the Credit Manager, which now receives the correct LeaveType string ('Sick'/'Vacation')
                $this->creditManager->adjustLeaveCredit(
                    $appDetails['EmployeeID'], 
                    $appDetails['LeaveType'], // This is now 'Sick' or 'Vacation'
                    $daysToDeduct
                );
            }
        }
        
        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // error_log($e->getMessage()); // Revert to logging for production
        return false;
    }
}
}

// =======================================================================
// UTILITY/SUPPORTING DATA CLASSES (Department, Position)
// =======================================================================

/**
 * Class for managing departments.
 * Interacts with `department` table.
 */
class DepartmentManager {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Create: Add a new department
    public function addDepartment($name) {
        $sql = "INSERT INTO department (DepartmentName) VALUES (:name)";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":name", $name);
        return $query->execute();
    }

    // Read: Get all departments
    public function getAllDepartments() {
        $sql = "SELECT * FROM department ORDER BY DepartmentName";
        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update: Update a department name
    public function updateDepartment($id, $name) {
        $sql = "UPDATE department SET DepartmentName = :name WHERE DepartmentID = :id";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":name", $name);
        $query->bindParam(":id", $id);
        return $query->execute();
    }
}

// -----------------------------------------------------------------------

/**
 * Class for managing job positions.
 * Interacts with `jobposition` table.
 */
class PositionManager {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Create: Add a new position
    public function addPosition($name) {
        $sql = "INSERT INTO jobposition (PositionName) VALUES (:name)";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":name", $name);
        return $query->execute();
    }

    // Read: Get all job positions
    public function getAllPositions() {
        $sql = "SELECT * FROM jobposition ORDER BY PositionName";
        $query = $this->db->connect()->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update: Update a position name
    public function updatePosition($id, $name) {
        $sql = "UPDATE jobposition SET PositionName = :name WHERE PositionID = :id";
        $query = $this->db->connect()->prepare($sql);
        $query->bindParam(":name", $name);
        $query->bindParam(":id", $id);
        return $query->execute();
    }
}

// =======================================================================
// DASHBOARD DATA FUNCTION
// =======================================================================

/**
 * Aggregates all necessary data for the Admin Dashboard view.
 *
 * @param EmployeeManager $employeeManager Instance of EmployeeManager.
 * @param LeaveApplicationManager $appManager Instance of LeaveApplicationManager.
 * @return array Dashboard data structure.
 */
function getDashboardData($employeeManager, $appManager) {
    
    $totalEmployees = $employeeManager->getTotalEmployeeCount(); 
    $onLeaveCount = $appManager->getEmployeesOnLeaveCount();
    $leaveDaysByType = $appManager->getTotalLeaveDaysByType();
    $onLeaveVacation = $appManager->getEmployeesOnLeaveByType('Vacation');
    $onLeaveSick = $appManager->getEmployeesOnLeaveByType('Sick');

    return [
        'statusData' => $appManager->getStatusCounts(), 
        'monthlyData' => $appManager->getMonthlyLeaveApplications(), 
        'leaveSummary' => [
        'totalEmployees' => $totalEmployees,
        'onLeave' => $onLeaveCount,
            'onLeaveVacation' => $onLeaveVacation,
            'onLeaveSick' => $onLeaveSick,
        ]
    ];
}


// =======================================================================
// UNIVERSAL FUNCTIONS
// =======================================================================

/**
 * Transfers an ID via session and redirects to a registration page.
 */
function transferIdAndRedirect() {
    session_start();
    $_SESSION["employeeID"] = $_POST["id"] ?? "";
    header("Location: accountSetup/register.php");
    exit;
}

/**
 * Transfers ID and PositionID via session and redirects to an account page.
 */
function transferIdAndPositionToAccount($employeeID, $positionID) {
    session_start();
    $_SESSION["employeeID"] = $employeeID;
    $_SESSION["positionID"] = $positionID;
    header("Location: account.php");
    exit;
}

class PasswordResetManager {
    protected $db;

    public function __construct() {
        // Assuming Database class is defined elsewhere and handles connection
        $this->db = new Database(); 
    }

    /**
     * Initiates the password reset process by generating a numeric OTP.
     * * @param string $email The email address of the user requesting a reset.
     * @return array|false An array containing the OTP code if successful, otherwise false.
     */
    public function initiateReset($email) {
        $pdo = null; 
        $sql = "";
        
        try {
            $pdo = $this->db->connect();
            
            // 1. Get Account details (Lookup query)
            // This query must successfully retrieve the AccountID for the active user.
            $sql = "SELECT a.AccountID, a.EmployeeID, a.Username 
                    FROM account a
                    INNER JOIN adminregistration ar ON a.EmployeeID = ar.EmployeeID
                    WHERE ar.Email = :email AND ar.Status = 'Active'";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                // Fails if email not found or account is inactive
                return false; 
            }

            // --- OTP GENERATION (New Logic) ---
            // Generate a secure 6-digit numeric OTP (000000 to 999999)
            $otp_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT); 
            // OTPs are valid for 10 minutes
            $expiry_time_php = date('Y-m-d H:i:s', strtotime('+10 minutes')); 
            // ------------------------------------

            $pdo->beginTransaction();

            // 3. Delete Old OTPs/tokens for this user
            $sql = "DELETE FROM password_resets WHERE AccountID = :accID";
            $delStmt = $pdo->prepare($sql);
            $delStmt->bindParam(':accID', $account['AccountID'], PDO::PARAM_INT);
            $delStmt->execute();

            // 4. Insert New OTP
            $sql = "INSERT INTO password_resets (AccountID, OTP_Code, ResetExpiry) 
                       VALUES (:accID, :otp, :expiry)"; 
            
            $insStmt = $pdo->prepare($sql);
            $insStmt->bindParam(':accID', $account['AccountID'], PDO::PARAM_INT);
            $insStmt->bindParam(':otp', $otp_code, PDO::PARAM_STR);
            $insStmt->bindParam(':expiry', $expiry_time_php, PDO::PARAM_STR); 
            $insStmt->execute();

            $pdo->commit(); 

            // IMPORTANT: Return the OTP code for the EmailSender
            return [
                'otp' => $otp_code, 
                'username' => $account['Username'],
                'employeeID' => $account['EmployeeID']
            ];

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("DB ERROR in initiateReset (OTP): " . $e->getMessage()); 
            return false;
        }
    }


    /**
     * Verifies the numeric OTP against the database and checks for expiration.
     * @param string $otp The 6-digit OTP provided by the user.
     * @return array|false The OTP data if valid, otherwise false.
     */
    public function verifyOTP($otp) {
        $pdo = $this->db->connect();
        
        // Note the change to OTP_Code column
        $sql = "SELECT * FROM password_resets WHERE OTP_Code = :otp AND ResetExpiry > NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':otp', $otp, PDO::PARAM_STR);
        $stmt->execute();
        
        // Returns the row if found and not expired.
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Updates the user's password using a valid OTP and deletes the OTP record.
     * @param string $otp The valid OTP code.
     * @param string $newPassword The new password string.
     * @return bool True on success, false on failure.
     */
    public function updatePassword($otp, $newPassword) {
        $pdo = null;
        try {
            $pdo = $this->db->connect();
            $pdo->beginTransaction();

            // 1. Verify the OTP and get the AccountID
            $resetRequest = $this->verifyOTP($otp);

            if (!$resetRequest) {
                $pdo->rollBack();
                return false;
            }

            $accountID = $resetRequest['AccountID'];
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // 2. Update the account password
            $sql = "UPDATE account SET Password = :password WHERE AccountID = :accID";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':accID', $accountID, PDO::PARAM_INT);
            $stmt->execute();

            // 3. Delete the used OTP (CRITICAL: Invalidates the code)
            $sql = "DELETE FROM password_resets WHERE AccountID = :accID";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':accID', $accountID, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Password Update Error: " . $e->getMessage());
            return false;
        }
    }
}
class NotificationManager {
    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Main function to fetch and merge all pending notifications
     */
    public function getNotifications() {
        $pdo = $this->db->connect();
        $notifications = [];
        // --- 1. FETCH PENDING ACCOUNT REQUESTS ---
        $sqlAccounts = "SELECT 
                            EmployeeID, 
                            Email, 
                            DateAdded as DateCreated
                        FROM adminregistration 
                        WHERE Status = 'Pending' 
                        ORDER BY DateCreated DESC";
        
        $stmtAcc = $pdo->prepare($sqlAccounts);
        $stmtAcc->execute();
        $accountReqs = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accountReqs as $acc) {
            $notifications[] = [
                'type'      => 'account',
                'id'        => $acc['EmployeeID'],
                'title'     => 'New Account Request',
                'message'   => "Employee ID: " . $acc['EmployeeID'],
                'sub_text'  => $acc['Email'], // Show email as context
                'timestamp' => $acc['DateCreated'],
                'time_ago'  => $this->time_elapsed_string($acc['DateCreated']),
                'pic' => 'assets/image/default-avatar.png', // Placeholder image
                'icon'      => 'fa-user-plus', // Icon overlay
                'color'     => 'text-blue-600',
                'bg_color'  => 'bg-blue-100',
                'link'      => 'adminhome.php?section=request' // Redirects to Employee Request page
            ];
        }

        // --- 2. FETCH PENDING LEAVE APPLICATIONS ---
        // Join with Employee table to get the real Name and Profile Pic.
        $sqlLeaves = "SELECT 
                        l.LeaveID, 
                        l.EmployeeID, 
                        lt.TypeName AS LeaveType,  -- Changed to use TypeName and gave it an alias
                        DATEDIFF(l.EndDate, l.StartDate) + 1 AS NumberOfDays, -- The calculated number of days
                        l.DateApplied,
                        e.FirstName, 
                        e.LastName, 
                        e.profilePic 
                      FROM leaveapplication l
                      JOIN employee e ON l.EmployeeID = e.EmployeeID
                      JOIN leavetype lt ON l.LeaveTypeID = lt.LeaveTypeID -- NEW JOIN added here
                      WHERE l.Status = 'Pending'
                      ORDER BY l.DateApplied DESC";

        $stmtLeave = $pdo->prepare($sqlLeaves);
        $stmtLeave->execute();
        $leaveReqs = $stmtLeave->fetchAll(PDO::FETCH_ASSOC);

        foreach ($leaveReqs as $leave) {
            // Handle empty profile pic
           $profilePic = !empty($leave['profilePic']) ? $leave['profilePic'] : 'assets/image/default-avatar.png'; // FIXED PATH
            
            // Pluralize 'Day'
            $daysText = $leave['NumberOfDays'] . ($leave['NumberOfDays'] > 1 ? ' Days' : ' Day');

            $notifications[] = [
                'type'      => 'leave',
                'id'        => $leave['LeaveID'],
                'title'     => 'Leave Application',
                'message'   => $leave['FirstName'] . ' ' . $leave['LastName'],
                'sub_text'  => "{$leave['LeaveType']} • {$daysText}", // e.g., "Sick Leave • 3 Days"
                'timestamp' => $leave['DateApplied'],
                'time_ago'  => $this->time_elapsed_string($leave['DateApplied']),
                'pic'       => $profilePic, // Real user photo
                'icon'      => 'fa-file-signature', // Icon overlay
                'color'     => 'text-red-600',
                'bg_color'  => 'bg-red-100',
                'link'      => 'adminhome.php?section=leave_list' // Redirects to Leave List page
            ];
        }

        // --- 3. MERGE & SORT ---
        // Sort combined array by timestamp descending (Newest first)
        usort($notifications, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return $notifications;
    }

    private function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

      public function getUserNotifications($employeeID) {
    $pdo = $this->db->connect();
    $notifications = [];

    // CRITICAL FIX: Add JOIN to leavetype (lt) table and select TypeName as LeaveType
    $sql = "SELECT 
                la.LeaveID, 
                lt.TypeName AS LeaveType,  -- <<< FIXED: Fetches name from joined table
                la.StartDate, 
                la.NumberOfDays,
                lap.Decision, 
                lap.Remarks, 
                lap.DecidedDate
            FROM 
                leaveapproval lap
            JOIN 
                leaveapplication la ON lap.LeaveID = la.LeaveID
            -- CRITICAL FIX: Add JOIN to get the Leave Type Name
            JOIN 
                leavetype lt ON la.LeaveTypeID = lt.LeaveTypeID 
            WHERE 
                la.EmployeeID = :eid
            ORDER BY lap.DecidedDate DESC
            LIMIT 5"; 

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':eid' => $employeeID]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $isApproved = ($row['Decision'] === 'Approved');
            
            $notifications[] = [
                'type'      => 'decision',
                'id'        => $row['LeaveID'],
                'title'     => $isApproved ? 'Application Approved' : 'Application Rejected',
                // This now uses the correctly aliased 'LeaveType' key
                'message'   => "Your {$row['LeaveType']} for {$row['NumberOfDays']} days has been {$row['Decision']}.",
                'sub_text'  => $row['Remarks'] ? "Remarks: " . $row['Remarks'] : "No remarks provided.",
                'timestamp' => $row['DecidedDate'],
                'time_ago'  => $this->time_elapsed_string($row['DecidedDate']),
                'icon'      => $isApproved ? 'fa-check-circle' : 'fa-times-circle',
                'color'     => $isApproved ? 'text-green-600' : 'text-red-600',
                'bg_color'  => $isApproved ? 'bg-green-100' : 'bg-red-100',
                'pic'       => '',
                'link'      => 'home.php?section=leave_log' 
            ];
        }

        return $notifications;

    } catch (PDOException $e) {
        error_log("DB Error in getUserNotifications: " . $e->getMessage());
        return []; // Return empty array on failure
    }
}

public function markAsRead($employeeID) {
        try {
            $pdo = $this->db->connect();
            // Only update 'unread' items to 'read'
            $sql = "UPDATE notification SET status = 'read' WHERE EmployeeID = :eid AND status = 'unread'";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([':eid' => $employeeID]);
        } catch (Exception $e) {
            return false;
        }
    }
}

class LeaveTypeManager {
    
    // Property to hold the Database wrapper object
    protected $db;

    /**
     * Constructor: Instantiates the Database wrapper object directly.
     * Takes no arguments.
     */
    public function __construct() {
        // ASSUMPTION: The 'Database' class is available (included/required).
        $this->db = new Database(); 
    }

    /**
     * Fetches all leave types (ID and Name) from the 'leavetype' table.
     * * @return array An array of associative arrays, each containing LeaveTypeID and TypeName.
     */
    public function getAllLeaveTypes(): array {
        try {
            // SQL Query to get Leave Types
            $sqlLeaveTypes = "SELECT LeaveTypeID, TypeName FROM leavetype ORDER BY TypeName ASC";
            
            // Use the $this->db wrapper to get the PDO connection object and prepare
            $query = $this->db->connect()->prepare($sqlLeaveTypes); 
            $query->execute();
            
            return $query->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // Log the error for debugging
            error_log("Database error in getAllLeaveTypes: " . $e->getMessage());
            
            // Return an empty array on failure
            return [];
        }
    }

    // You can add more methods here like getLeaveTypeByID, addLeaveType, etc.
}

?>
