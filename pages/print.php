<?php
// --- FIX: Start Output Buffering to catch accidental whitespace/warnings ---
ob_start();

// --- 1. SETUP AND INCLUDES ---
require_once __DIR__ . '/../vendor/autoload.php'; 

// Fallback for manual TCPDF installation
if (!class_exists('TCPDF')) {
    $manualPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($manualPath)) {
        require_once $manualPath;
    }
}

// --- DYNAMIC CLASS INHERITANCE ---
// If FPDI is installed (via Composer), use it. Otherwise, fallback to standard TCPDF.
if (class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
    class PdfBase extends \setasign\Fpdi\Tcpdf\Fpdi {}
} else {
    // If FPDI is not installed, we use standard TCPDF
    if (!class_exists('TCPDF')) {
        die("Error: TCPDF class not found. Check vendor installation.");
    }
    class PdfBase extends TCPDF {}
}

require_once __DIR__ . '/../dbRelated/db_connect.php';
require_once __DIR__ . '/../dbRelated/operation.php';

// --- 2. FETCH DATA ---
$leaveID = $_GET['leave_id'] ?? null;
if (!$leaveID) { ob_end_clean(); die("Error: No Leave ID provided."); }

$applicationManager = new LeaveApplicationManager();
$details = $applicationManager->getOneApplicationWithApproval($leaveID);
if (!$details) { ob_end_clean(); die("Error: Application not found."); }

// --- 3. EXTEND CLASS FOR PDF (MODIFIED) ---
class LeavePDF extends PdfBase {
    protected $applicationDetails;

    // Custom constructor to accept application details
    public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $details=null) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
        $this->applicationDetails = $details;
    }

    public function Header() {
        // --- FIX 2: SUPPRESS HEADER AFTER PAGE 1 ---
        if ($this->getPage() > 1) {
            // Default top margin is 15mm.
            $this->SetY(15); 
            return;
        }

        // --- Header Setup ---
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(102, 102, 102); // Dark Gray color for text
        $headerY = 10;
        $logoX = 15;
        $logoWidth = 20; // WMSU logo width

        // --- 1. WMSU Logo ---
        $logoPath = __DIR__ . '/../assets/image/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, $logoX, $headerY, $logoWidth, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false);
        }

        // --- 2. Certification Logo (Placeholder, assuming file name is certification.png) ---
        $certLogoPath = __DIR__ . '/../assets/image/cert.png';
        if (file_exists($certLogoPath)) {
            // Position the cert logo next to the WMSU logo
            $this->Image($certLogoPath, $logoX + $logoWidth + 3, $headerY + 5, 25, 12, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false);
        }
        
        // --- 3. Main Header Text ---
        $textX = $logoX + $logoWidth + 30; // Start text after logos
        
        $this->SetY($headerY);
        $this->SetX($textX);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Republic of the Philippines', 0, 1, 'L');
        
        $this->SetX($textX);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 5, 'WESTERN MINDANAO STATE UNIVERSITY', 0, 1, 'L');
        
        $this->SetX($textX);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'Normal Road, Baliwasan, Zamboanga City 7000', 0, 1, 'L');

        // --- 4. Application Details (Applied Date) ---
        $dateX = 160; // Right side position for the date text
        
        $this->SetY($headerY + 5); // Vertically align with the university name row
        $this->SetX($dateX);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 5, 'Leave Application System', 0, 1, 'L');
        
        $this->SetX($dateX);
        $this->SetFont('helvetica', '', 10);
        $appliedDate = $this->applicationDetails['AppliedDate'] ?? date('Y-m-d');
        $this->Cell(0, 5, 'Date Generated: ' . $appliedDate, 0, 1, 'L');
        
        // --- Draw Separator Line ---
        $this->SetY($headerY + 25);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        // --- CONTENT START POSITION MANIPULATION ---
        // Set a safe Y-position for the header to exit (less critical, body takes over)
        $this->SetY(40); 
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Helper function to print labeled rows
function printRow($pdf, $label, $value, $lblX, $valX, $h, $colLbl, $colTxt, $isBoldValue = false) {
    $pdf->SetX($lblX);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColorArray($colLbl);
    $pdf->Cell(35, $h, $label, 0, 0, 'L');
    $pdf->SetX($valX);
    $pdf->SetFont('helvetica', $isBoldValue ? 'B' : '', 10);
    $pdf->SetTextColorArray($colTxt);
    $pdf->Cell(0, $h, $value, 0, 1, 'L');
}


// --- 4. INITIALIZE PDF ---
// Pass the $details object to the custom constructor
$pdf = new LeavePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, $details);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Leave Application - ' . $details['LeaveID']);
$pdf->SetMargins(15, 15, 15); 
$pdf->SetAutoPageBreak(TRUE, 15);

// The AddPage() call executes the custom Header()
$pdf->AddPage();

// --- 5. STYLING SETUP ---
$col_label = [185, 28, 28]; // Red
$col_text  = [0, 0, 0];     // Black
$col_gray  = [128, 128, 128]; 
$col_green = [0, 128, 0];
$font_main = 'helvetica';

// --- 6. RENDER CONTENT ---

// --- CRITICAL FIX 2: FORCE Y-POSITION IN MAIN BODY ---
// This ensures the content starts where we intend it, overriding any reset by TCPDF.
$pdf->SetY(35); 

$pdf->SetFont($font_main, 'B', 12);
$pdf->SetTextColorArray($col_gray);
$pdf->Cell(0, 10, 'LEAVE APPLICATION', 0, 1, 'C');
$pdf->Ln(5); 

// --- PROFILE SECTION ---
$startY = $pdf->GetY();
$imageSize = 35; // size in mm
$profilePath = __DIR__ . '/../' . ($details['profilePic'] ?? 'assets/image/default-avatar.png');
// Double check profile image exists, otherwise use default
if (!file_exists($profilePath) || empty($details['profilePic'])) { 
    $profilePath = __DIR__ . '/../assets/image/default-avatar.png'; 
}

// Check for necessary PHP extensions for graphics handling (GD or Imagick)
$hasGraphics = (extension_loaded('gd') || extension_loaded('imagick'));

if (file_exists($profilePath) && $hasGraphics) {
    // Note: Image needs to be aligned to the left of the content area
    $pdf->Image($profilePath, 25, $startY, $imageSize, $imageSize, '', '', '', true, 300, '', false, false, 0, true, false);
    $pdf->SetDrawColorArray($col_label);
    $pdf->Rect(25, $startY, $imageSize, $imageSize, 'D');
} else {
    // Render empty box with error message
    $pdf->Rect(25, $startY, $imageSize, $imageSize, 'D');
    $pdf->SetY($startY + ($imageSize / 2) - 5); 
    $pdf->SetX(25);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell($imageSize, 10, ($hasGraphics ? 'No Image' : 'GD Disabled'), 0, 0, 'C');
}

// Right Column Data (Employee Info)
$leftColX = 70; 
$rightColX = 110; 
$lineHeight = 8;
$pdf->SetY($startY); // Align vertical start position

$fullName = $details['FirstName'] . ' ' . ($details['MiddleName'] ? $details['MiddleName'] . ' ' : '') . $details['LastName'];
printRow($pdf, "Employee ID:", $details['EmployeeID'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
printRow($pdf, "Full Name:", $fullName, $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
printRow($pdf, "Position:", $details['PositionName'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
printRow($pdf, "College:", $details['DepartmentName'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
printRow($pdf, "Email:", $details['Email'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);

$pdf->Ln(10); 
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// --- LEAVE DETAILS ---
$pdf->SetFont($font_main, 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Leave Details (Application ID: ' . $details['LeaveID'] . ')', 0, 1, 'L');

$lblX = 15; $valX = 50; $lh = 8;
printRow($pdf, "Leave Type:", $details['LeaveType'], $lblX, $valX, $lh, $col_label, $col_text);
printRow($pdf, "Total Days:", $details['NumberOfDays'], $lblX, $valX, $lh, $col_label, $col_text);
printRow($pdf, "Start Date:", $details['StartDate'], $lblX, $valX, $lh, $col_label, $col_text);
printRow($pdf, "End Date:", $details['EndDate'], $lblX, $valX, $lh, $col_label, $col_text);
printRow($pdf, "Reason:", $details['Reason'], $lblX, $valX, $lh, $col_label, $col_text);

$pdf->Ln(5);
$pdf->Line(15, $pdf->GetY(), 100, $pdf->GetY()); 
$pdf->Ln(5);

// --- APPROVAL DECISION ---
$pdf->SetFont($font_main, 'B', 12);
$pdf->SetTextColor(60, 20, 20); 
$pdf->Cell(0, 10, 'Approval Decision', 0, 1, 'L');

$statusColor = $col_gray; 
if ($details['Status'] === 'Approved') $statusColor = $col_green;
if ($details['Status'] === 'Rejected') $statusColor = $col_label; 

$pdf->SetX($lblX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColorArray($col_label);
$pdf->Cell(35, $lh, "Final Status:", 0, 0, 'L');
$pdf->SetX($valX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColorArray($statusColor);
$pdf->Cell(0, $lh, $details['Status'], 0, 1, 'L');

printRow($pdf, "Decision Date:", $details['DecidedDate'] ?? 'N/A', $lblX, $valX, $lh, $col_label, $col_text);
printRow($pdf, "Approver ID:", $details['EmployeeApproverID'] ?? 'N/A', $lblX, $valX, $lh, $col_label, $col_text);

$pdf->SetX($lblX);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColorArray($col_label);
$pdf->Cell(35, $lh, "Approver Remarks:", 0, 0, 'L');
$pdf->SetX($valX);
$pdf->SetFont('helvetica', 'I', 10); 
$pdf->SetTextColorArray($col_text);
$remarks = $details['ApproverRemarks'] ?? 'No remarks provided.';
$pdf->MultiCell(0, $lh, $remarks, 0, 'L');

// --- 7. ATTACHMENT LOGIC (IMAGE OR PDF) ---
if (!empty($details['Attachment'])) {
    $filePath = __DIR__ . '/../' . $details['Attachment'];
    
    if (file_exists($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // --- CASE A: IMAGE ---
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $pdf->AddPage();
            $pdf->SetFont($font_main, 'B', 14);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell(0, 10, 'Attached Document (Image)', 0, 1, 'L');
            $pdf->Ln(5);
            
            // Fit to Page Width (A4 is 210mm wide, margins are 15mm)
            $pdf->Image($filePath, 15, $pdf->GetY(), 180, 0, '', '', '', false, 300, '', false, false, 0, false, false); 
        }

        // --- CASE B: PDF MERGING ---
        elseif ($ext === 'pdf') {
            // Check if FPDI is effectively loaded (method exists)
            if (method_exists($pdf, 'setSourceFile')) {
                try {
                    $pdf->AddPage();
                    $pdf->SetFont($font_main, 'B', 14);
                    $pdf->SetTextColor(0,0,0);
                    $pdf->Cell(0, 10, 'Attached Document (PDF Merge)', 0, 1, 'L');
                    $pdf->Ln(5);
                    
                    $pageCount = $pdf->setSourceFile($filePath);
                    
                    // Import every page
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        // Ensure pages after the first start on a new sheet
                        if ($pageNo > 1) {
                             $pdf->AddPage();
                        }
                        
                        $templateId = $pdf->importPage($pageNo);
                        
                        // FIX: Use getTemplateSize() which is the correct FPDI method for size
                        $size = $pdf->getTemplateSize($templateId);
                        
                        // Use template, scale to fit width (180mm)
                        $pdf->useTemplate($templateId, 15, $pdf->GetY(), 180, $size['height'] * (180 / $size['width']));
                    }
                } catch (Exception $e) {
                    $pdf->AddPage();
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell(0, 10, 'Error merging PDF: ' . $e->getMessage(), 0, 1);
                }
            } else {
                // Fallback if user didn't install setasign/fpdi
                $pdf->AddPage();
                $pdf->SetFont($font_main, 'B', 12);
                $pdf->SetTextColor(255, 0, 0); // Red Warning
                $pdf->Cell(0, 10, 'Attachment is a PDF.', 0, 1);
                $pdf->SetFont($font_main, '', 10);
                $pdf->Cell(0, 10, 'To merge it, you must install FPDI: "composer require setasign/fpdi"', 0, 1);
            }
        }
    }
}

// --- OUTPUT ---
ob_end_clean(); 
$pdf->Output('Leave_Application_' . $details['LeaveID'] . '.pdf', 'I');
?>