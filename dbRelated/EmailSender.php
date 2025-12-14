<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust the path to vendor/autoload.php based on your actual directory structure
require_once __DIR__ . '/../vendor/autoload.php';

class EmailSender {
    private $mail;

   public function __construct() {
        $this->mail = new PHPMailer(true);

        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            
            // --- YOUR GMAIL CREDENTIALS ---
            $this->mail->Username   = 'wmsu.leave.system@gmail.com'; 
            $this->mail->Password   = 'isai ziew bgdd vkxu'; 
            
            // --- FIX 1: Change Encryption to SMTPS (SSL) ---
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            
            // --- FIX 2: Change Port to 465 (Standard for SMTPS) ---
            $this->mail->Port       = 465; // Changed from 587

            // Default Sender
            $this->mail->setFrom('wmsu.leave.system@gmail.com', 'Leave Management System');
        } catch (Exception $e) {
            error_log("Mailer Init Error: " . $this->mail->ErrorInfo);
        }
    }

    /**
     * Sends a notification with PDF attachment and detailed body.
     */
    public function sendDecisionNotification($toEmail, $employeeName, $status, $leaveID, $remarks, $pdfContent, $details) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            $this->mail->addAddress($toEmail, $employeeName);

            // Attach the PDF
            if ($pdfContent) {
                $this->mail->addStringAttachment($pdfContent, "Leave_Application_$leaveID.pdf");
            }

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = "Formal Notification: Leave Application Update (ID: $leaveID)";
            
            // Colors
            $statusColor = ($status === 'Approved') ? '#166534' : '#991b1b'; 
            $bgHeader = ($status === 'Approved') ? '#dcfce7' : '#fee2e2';
            $borderColor = ($status === 'Approved') ? '#86efac' : '#fca5a5';

            // Helper Vars for Body
            $startDate = $details['StartDate'] ?? 'N/A';
            $endDate = $details['EndDate'] ?? 'N/A';
            $days = $details['NumberOfDays'] ?? 'N/A';
            $type = $details['LeaveType'] ?? 'N/A';
            $approverRemarks = !empty($remarks) ? $remarks : "None";

            $body = "
            <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333; line-height: 1.6;'>
                
                <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-bottom: 3px solid #3b82f6;'>
                    <h2 style='margin:0; color: #1e293b;'>Leave Application Status</h2>
                </div>

                <div style='padding: 30px; border: 1px solid #e2e8f0; border-top: none;'>
                    <p>Dear <strong>$employeeName</strong>,</p>
                    
                    <p>This email serves as a formal notification regarding your recent leave application.</p>

                    <div style='background-color: $bgHeader; border-left: 5px solid $borderColor; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; font-size: 16px;'>Application Status: <strong style='color: $statusColor; font-size: 18px;'>$status</strong></p>
                    </div>

                    <h3 style='border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-top: 25px; color: #475569;'>Application Summary</h3>
                    <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b; width: 140px;'>Application ID:</td>
                            <td style='padding: 8px 0; font-weight: bold;'>$leaveID</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b;'>Leave Type:</td>
                            <td style='padding: 8px 0;'>$type</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b;'>Duration:</td>
                            <td style='padding: 8px 0;'>$startDate to $endDate ($days days)</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b;'>Approver Remarks:</td>
                            <td style='padding: 8px 0; font-style: italic;'>\"$approverRemarks\"</td>
                        </tr>
                    </table>

                    <p style='margin-top: 30px;'>
                        <strong>Note:</strong> A PDF copy of your complete application, including the official decision, has been attached to this email for your records.
                    </p>

                    <p>If you have any questions regarding this decision, please contact the HR department.</p>
                    
                    <br>
                    <p style='margin: 0;'>Sincerely,</p>
                    <p style='margin: 0; font-weight: bold;'>Leave Management System</p>
                </div>
                
                <div style='text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;'>
                    &copy; " . date("Y") . " WMSU Leave Management System. All rights reserved.<br>
                    This is an automated system generated message.
                </div>
            </div>";

            $this->mail->Body = $body;
            $this->mail->AltBody = "Your leave application (ID: $leaveID) has been $status. A PDF copy is attached.";

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Email Send Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    public function sendAccountConfirmation($toEmail, $employeeID) {
        try {
            // Clear any previous settings if object is reused
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            $this->mail->addAddress($toEmail);

            $this->mail->isHTML(true);
            $this->mail->Subject = "Account Confirmation: Registration Approved";

            $body = "
            <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333; line-height: 1.6;'>
                
                <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-bottom: 3px solid #166534;'>
                    <h2 style='margin:0; color: #1e293b;'>Account Verified</h2>
                </div>

                <div style='padding: 30px; border: 1px solid #e2e8f0; border-top: none;'>
                    <p>Greetings,</p>
                    
                    <p>We are pleased to inform you that your account request for Employee ID <strong>$employeeID</strong> has been verified and confirmed by the system administrator.</p>

                    <div style='background-color: #dcfce7; border-left: 5px solid #166534; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; font-size: 16px; color: #14532d;'><strong>Status: Ready for Registration</strong></p>
                    </div>

                    <p>You may now proceed to the login page to complete your setup or access your dashboard.</p>

                    <br>
                    <p style='margin: 0;'>Sincerely,</p>
                    <p style='margin: 0; font-weight: bold;'>Leave Management System</p>
                </div>
            </div>";

            $this->mail->Body = $body;
            $this->mail->AltBody = "Your Employee ID $employeeID has been verified. You may now proceed to login/register.";

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Email Send Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Sends a contact inquiry FROM a user TO the HR/System Email.
     * Sets Reply-To as the user's email.
     */
    public function sendContactInquiry($fromEmail, $messageBody) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            // 1. Send TO the HR email (Using the system email for now)
            $this->mail->addAddress('wmsu.leave.system@gmail.com', 'HR Department');

            // 2. Set Reply-To so HR can reply directly to the user
            $this->mail->addReplyTo($fromEmail);

            $this->mail->isHTML(true);
            $this->mail->Subject = "Support Inquiry from: $fromEmail";

            $body = "
            <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px; border: 1px solid #eee;'>
                <h2 style='color: #800000;'>New Support Inquiry</h2>
                <p><strong>Sender:</strong> <a href='mailto:$fromEmail'>$fromEmail</a></p>
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <p><strong>Message:</strong></p>
                <div style='background-color: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;'>
                    " . nl2br(htmlspecialchars($messageBody)) . "
                </div>
            </div>";

            $this->mail->Body = $body;
            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Contact Email Send Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Sends a notification TO THE ADMIN when a new user requests registration.
     */
    public function sendRegistrationRequest($adminEmail, $employeeID, $requestorEmail) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            // Send TO the Admin
            $this->mail->addAddress($adminEmail); 

            $this->mail->isHTML(true);
            $this->mail->Subject = "New Registration Request: ID $employeeID";

            $body = "
            <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px; border: 1px solid #eee; max-width: 600px;'>
                <div style='background-color: #800000; color: white; padding: 15px; text-align: center;'>
                    <h2 style='margin:0;'>New Account Request</h2>
                </div>
                <div style='padding: 20px;'>
                    <p>A faculty member has requested account activation.</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #ddd;'>
                        <tr style='background-color: #f9fafb;'>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd; width: 150px; font-weight: bold;'>Employee ID</td>
                            <td style='padding: 12px; border-bottom: 1px solid #ddd;'>$employeeID</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px; font-weight: bold;'>Email Provided</td>
                            <td style='padding: 12px;'>$requestorEmail</td>
                        </tr>
                    </table>

                    <div style='margin-top: 30px; padding: 15px; background-color: #fff5f5; border-left: 4px solid #800000;'>
                        <p style='margin: 0; font-size: 14px;'>
                            Please log in to the <a href='#' style='color: #800000; font-weight: bold;'>Admin Dashboard</a> to review and approve this request.
                        </p>
                    </div>
                </div>
                <div style='background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; color: #666;'>
                    Automated Message from WMSU Leave System
                </div>
            </div>";

            $this->mail->Body = $body;
            $this->mail->AltBody = "New Registration Request.\nID: $employeeID\nEmail: $requestorEmail\nPlease check admin dashboard.";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Admin Notification Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
public function sendOTP($toEmail, $otpCode) {
        // Assuming PHPMailer object $this->mail is configured in the constructor
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            $this->mail->addAddress($toEmail);

            $this->mail->isHTML(true);
            $this->mail->Subject = "Your Password Reset Code (OTP)";

            $body = "
            <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee;'>
                <div style='background-color: #800000; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin:0;'>Password Reset OTP</h2>
                </div>
                <div style='padding: 30px; text-align: center;'>
                    <p>Your one-time password (OTP) for resetting your WMSU Leave System password is:</p>
                    
                    <div style='font-size: 32px; font-weight: bold; color: #800000; letter-spacing: 5px; margin: 20px 0; border: 2px solid #800000; padding: 10px; display: inline-block; border-radius: 5px;'>
                        $otpCode
                    </div>

                    <p>This code is valid for 10 minutes.</p>
                    <p style='color: #999; font-size: 12px;'>Do not share this code with anyone.</p>
                </div>
            </div>";

            $this->mail->Body = $body;
            $this->mail->AltBody = "Your OTP is: $otpCode. It expires in 10 minutes.";

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("OTP Send Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
    /**
     * NEW FUNCTION: Notification for Promotion / Employment Update
     */
    public function sendPromotionNotification($toEmail, $employeeName, $newPosition, $oldPosition) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            $this->mail->addAddress($toEmail, $employeeName);

            $this->mail->isHTML(true);
            $this->mail->Subject = "Employment Update: Promotion Notification";

            $body = "
            <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee;'>
                
                <!-- Header: Green for Positive News -->
                <div style='background-color: #15803d; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin:0;'>Congratulations!</h2>
                </div>

                <div style='padding: 30px;'>
                    <p>Dear <strong>$employeeName</strong>,</p>
                    
                    <p>We are pleased to inform you that your employment record has been successfully updated to reflect your promotion.</p>
                    
                    <div style='background-color: #f0fdf4; border-left: 4px solid #15803d; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; font-size: 16px; color: #14532d;'><strong>New Position:</strong> $newPosition</p>
                        <p style='margin: 5px 0 0; color: #666; font-size: 0.9em;'>Previous Position: $oldPosition</p>
                    </div>

                    <h3 style='color: #15803d; margin-top: 25px; border-bottom: 1px solid #eee; padding-bottom: 5px;'>Leave Credit Adjustment</h3>
                    <p>As a result of this promotion, your leave credit accrual rates have been updated to align with your new rank.</p>
                    <p><strong>Note:</strong> Our system has automatically recalculated your available credits to ensure you receive the correct benefits starting from the effective date of this change. Your previous credits remain safe.</p>
                    
                    <p>You can view your updated balance by logging into the Faculty Leave System.</p>

                    <div style='text-align: center; margin-top: 30px;'>
                        <!-- Replace with actual link -->
                        <a href='#' style='background-color: #15803d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login to Dashboard</a>
                    </div>
                    
                    <br>
                    <p style='margin: 0;'>Sincerely,</p>
                    <p style='margin: 0; font-weight: bold;'>HR Department</p>
                </div>
                
                <div style='background-color: #f9fafb; text-align: center; padding: 15px; color: #9ca3af; font-size: 12px;'>
                    &copy; " . date("Y") . " WMSU Leave Management System.
                </div>
            </div>";

            $this->mail->Body = $body;
            $this->mail->AltBody = "Congratulations on your promotion to $newPosition! Your leave credits have been adjusted accordingly.";

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Promotion Email Send Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
?>