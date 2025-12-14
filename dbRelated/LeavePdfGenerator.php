<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Fallback logic for manual TCPDF installation if Composer autoload fails
if (!class_exists('TCPDF')) {
    $manualPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($manualPath)) {
        require_once $manualPath;
    }
}

// Dynamically inherit from FPDI if available for PDF importing, else fallback to standard TCPDF
if (class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
    class GeneratorPdfBase extends \setasign\Fpdi\Tcpdf\Fpdi {}
} else {
    if (!class_exists('TCPDF')) {
        die("Error: TCPDF class not found. Check vendor installation.");
    }
    class GeneratorPdfBase extends TCPDF {}
}

require_once __DIR__ . '/../dbRelated/db_connect.php';
require_once __DIR__ . '/../dbRelated/operation.php';

// Custom PDF class extending the base generator to handle leave application specific formatting
class LeavePDFDoc extends GeneratorPdfBase {
    protected $applicationDetails;

    // Initialize PDF with orientation, unit, format, and application specific data
    public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $details=null) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
        $this->applicationDetails = $details;
    }

    // Generate custom header for the first page only
    public function Header() {
        if ($this->getPage() > 1) {
            $this->SetY(15); 
            return;
        }

        // Set font style and color for header text
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(102, 102, 102); 
        $headerY = 10;
        $logoX = 15;
        $logoWidth = 20;

        // Render WMSU logo if file exists
        $logoPath = __DIR__ . '/../assets/image/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, $logoX, $headerY, $logoWidth, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false);
        }

        // Render certification logo if file exists
        $certLogoPath = __DIR__ . '/../assets/image/cert.png';
        if (file_exists($certLogoPath)) {
            $this->Image($certLogoPath, $logoX + $logoWidth + 3, $headerY + 5, 25, 12, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false);
        }
        
        // Render institutional text details
        $textX = $logoX + $logoWidth + 30;
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

        // Render application system name and generation date
        $dateX = 160; 
        $this->SetY($headerY + 5); 
        $this->SetX($dateX);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 5, 'Leave Application System', 0, 1, 'L');
        
        $this->SetX($dateX);
        $this->SetFont('helvetica', '', 10);
        $appliedDate = $this->applicationDetails['AppliedDate'] ?? date('Y-m-d'); 
        $this->Cell(0, 5, 'Date Generated: ' . $appliedDate, 0, 1, 'L');
        
        // Draw separator line below header
        $this->SetY($headerY + 25);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        // Reset Y position for body content
        $this->SetY(40); 
    }

    // Generate footer with page numbering
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

class LeavePdfGenerator {
    
    // Helper method to render labeled data rows in the PDF
    private function printRow($pdf, $label, $value, $lblX, $valX, $h, $colLbl, $colTxt, $isBoldValue = false) {
        $pdf->SetX($lblX);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColorArray($colLbl);
        $pdf->Cell(35, $h, $label, 0, 0, 'L');
        $pdf->SetX($valX);
        $pdf->SetFont('helvetica', $isBoldValue ? 'B' : '', 10);
        $pdf->SetTextColorArray($colTxt);
        $pdf->Cell(0, $h, $value, 0, 1, 'L');
    }

    // Main function to generate the PDF document as a string
    public function generatePdfString($leaveID) {
        // Start output buffering
        ob_start();

        $applicationManager = new LeaveApplicationManager();
        $details = $applicationManager->getOneApplicationWithApproval($leaveID);

        if (!$details) {
            ob_end_clean();
            return null;
        }

        // Initialize custom PDF object with application details
        $pdf = new LeavePDFDoc(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, $details);
        
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Leave Application - ' . $details['LeaveID']);
        $pdf->SetMargins(15, 15, 15); 
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        // Define color schemes
        $col_label = [185, 28, 28]; // Red
        $col_text  = [0, 0, 0];     // Black
        $col_gray  = [128, 128, 128]; 
        $col_green = [0, 128, 0];
        $font_main = 'helvetica';

        // Force start position for content
        $pdf->SetY(35); 

        // Render Main Title
        $pdf->SetFont($font_main, 'B', 16); 
        $pdf->SetTextColorArray($col_label); 
        $pdf->Cell(0, 10, 'LEAVE APPLICATION', 0, 1, 'C'); 
        $pdf->Ln(5);

        // Render Profile Subsection Title
        $pdf->SetFont($font_main, 'B', 12);
        $pdf->SetTextColorArray($col_gray);
        $pdf->Cell(0, 5, 'Employee and Profile Details', 0, 1, 'L'); 
        $pdf->Ln(5); 

        // Render Profile Image with fallback handling
        $startY = $pdf->GetY();
        $imageSize = 35; // size in mm
        $profilePath = __DIR__ . '/../' . ($details['profilePic'] ?? 'assets/image/default-avatar.png');
        if (!file_exists($profilePath) || empty($details['profilePic'])) { 
            $profilePath = __DIR__ . '/../assets/image/default-avatar.png'; 
        }

        $hasGraphics = (extension_loaded('gd') || extension_loaded('imagick'));

        if (file_exists($profilePath) && $hasGraphics) {
            $pdf->Image($profilePath, 25, $startY, $imageSize, $imageSize, '', '', '', true, 300, '', false, false, 0, true, false);
            $pdf->SetDrawColorArray($col_label);
            $pdf->Rect(25, $startY, $imageSize, $imageSize, 'D');
        } else {
            $pdf->Rect(25, $startY, $imageSize, $imageSize, 'D');
            $pdf->SetY($startY + ($imageSize / 2) - 5); 
            $pdf->SetX(25);
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->Cell($imageSize, 10, ($hasGraphics ? 'No Image' : 'GD Disabled'), 0, 0, 'C');
        }

        // Render Employee Data Columns
        $leftColX = 70; 
        $rightColX = 110; 
        $lineHeight = 8;
        $pdf->SetY($startY); 

        $fullName = $details['FirstName'] . ' ' . ($details['MiddleName'] ? $details['MiddleName'] . ' ' : '') . $details['LastName'];
        $this->printRow($pdf, "Employee ID:", $details['EmployeeID'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
        $this->printRow($pdf, "Full Name:", $fullName, $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
        $this->printRow($pdf, "Position:", $details['PositionName'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);
        $this->printRow($pdf, "College:", $details['DepartmentName'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text); 
        $this->printRow($pdf, "Email:", $details['Email'], $leftColX, $rightColX, $lineHeight, $col_label, $col_text);

        $pdf->Ln(10); 
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        // Render Leave Details Section
        $pdf->SetFont($font_main, 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'Leave Details (Application ID: ' . $details['LeaveID'] . ')', 0, 1, 'L');

        $lblX = 15; $valX = 50; $lh = 8;
        $this->printRow($pdf, "Leave Type:", $details['LeaveType'], $lblX, $valX, $lh, $col_label, $col_text);
        $this->printRow($pdf, "Total Days:", $details['NumberOfDays'], $lblX, $valX, $lh, $col_label, $col_text);
        $this->printRow($pdf, "Start Date:", $details['StartDate'], $lblX, $valX, $lh, $col_label, $col_text);
        $this->printRow($pdf, "End Date:", $details['EndDate'], $lblX, $valX, $lh, $col_label, $col_text);
        $this->printRow($pdf, "Reason:", $details['Reason'], $lblX, $valX, $lh, $col_label, $col_text);

        $pdf->Ln(5);
        $pdf->Line(15, $pdf->GetY(), 100, $pdf->GetY()); 
        $pdf->Ln(5);

        // Render Approval Decision Section
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

        $this->printRow($pdf, "Decision Date:", $details['DecidedDate'] ?? 'N/A', $lblX, $valX, $lh, $col_label, $col_text);
        $this->printRow($pdf, "Approver ID:", $details['EmployeeApproverID'] ?? 'N/A', $lblX, $valX, $lh, $col_label, $col_text);

        $pdf->SetX($lblX);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColorArray($col_label);
        $pdf->Cell(35, $lh, "Approver Remarks:", 0, 0, 'L');
        $pdf->SetX($valX);
        $pdf->SetFont('helvetica', 'I', 10); 
        $pdf->SetTextColorArray($col_text);
        $remarks = $details['ApproverRemarks'] ?? 'No remarks provided.';
        $pdf->MultiCell(0, $lh, $remarks, 0, 'L');

        // Render and merge attached documents if available
        if (!empty($details['Attachment'])) {
            $filePath = __DIR__ . '/../' . $details['Attachment'];
            if (file_exists($filePath)) {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $pdf->AddPage();
                    $pdf->SetFont($font_main, 'B', 14);
                    $pdf->SetTextColor(0,0,0);
                    $pdf->Cell(0, 10, 'Attached Document (Image)', 0, 1, 'L');
                    $pdf->Ln(5);
                    $pdf->Image($filePath, 15, $pdf->GetY(), 180, 0, '', '', '', false, 300, '', false, false, 0, false, false); 
                } elseif ($ext === 'pdf' && method_exists($pdf, 'setSourceFile')) {
                    try {
                        $pdf->AddPage();
                        $pdf->SetFont($font_main, 'B', 14);
                        $pdf->SetTextColor(0,0,0);
                        $pdf->Cell(0, 10, 'Attached Document (PDF Merge)', 0, 1, 'L');
                        $pdf->Ln(5);

                        $pageCount = $pdf->setSourceFile($filePath);
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                             if ($pageNo > 1) {
                                 $pdf->AddPage();
                             }
                            $templateId = $pdf->importPage($pageNo);
                            
                            $size = $pdf->getTemplateSize($templateId);
                            $pdf->useTemplate($templateId, 15, $pdf->GetY(), 180, $size['height'] * (180 / $size['width']));
                        }
                    } catch (Exception $e) {
                         $pdf->AddPage();
                         $pdf->SetTextColor(255, 0, 0);
                         $pdf->Cell(0, 10, 'Error merging PDF: ' . $e->getMessage(), 0, 1);
                    }
                } else {
                      $pdf->AddPage();
                      $pdf->SetFont($font_main, 'B', 12);
                      $pdf->SetTextColor(255, 0, 0);
                      $pdf->Cell(0, 10, 'Attachment is a PDF.', 0, 1);
                }
            }
        }

        // Output PDF as a string for email attachment or download
        ob_end_clean();
        return $pdf->Output('leave_application_' . $details['LeaveID'] . '.pdf', 'S');
    }
}
?>