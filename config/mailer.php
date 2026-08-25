<?php
/**
 * config/mailer.php — Automated Email Sender Service
 * Uses PHPMailer to send OTP verification codes and system notifications via SMTP.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// SMTP Configuration Constants
if (!defined('SMTP_HOST'))       define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT', 587);
if (!defined('SMTP_SECURE'))     define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS); // TLS on 587
if (!defined('SMTP_AUTH'))       define('SMTP_AUTH', true);
if (!defined('SMTP_USER'))       define('SMTP_USER', 'naaporganization@gmail.com');
if (!defined('SMTP_PASS'))       define('SMTP_PASS', 'aqcjtkrenvqobpms');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'naaporganization@gmail.com');
if (!defined('SMTP_FROM_NAME'))  define('SMTP_FROM_NAME', 'NAAP Student Organization');

/**
 * Send an OTP code email for password reset or account verification.
 * 
 * @param string $toEmail Recipient email address
 * @param string $recipientName Recipient display name (optional)
 * @param string $otpCode 6-digit OTP code
 * @param string $subject Email subject line
 * @return array ['success' => bool, 'message' => string, 'error' => string|null, 'code' => string]
 */
function sendOtpEmail(string $toEmail, string $recipientName = 'Student', string $otpCode = '', string $subject = 'Your Password Reset OTP Code'): array {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';

        // Sender & Recipient
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $displayName = !empty($recipientName) && $recipientName !== 'Student' ? $recipientName : $toEmail;
        $mail->addAddress($toEmail, $displayName);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $safeOtp  = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');

        // Modern, responsive HTML email template
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #0f172a; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 540px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 35px rgba(0, 0, 0, 0.35);" cellspacing="0" cellpadding="0" border="0">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px;">NAAP Student Organization</h1>
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #93c5fd; font-weight: 500;">Campus Event & Account Portal</p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 32px 28px 32px;">
                            <h2 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #0f172a;">Password Reset Request</h2>
                            <p style="margin: 0 0 18px 0; font-size: 15px; line-height: 1.6; color: #475569;">
                                Hello <strong>{$safeName}</strong>,
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #475569;">
                                We received a request to reset your password. Use the 6-digit One-Time Password (OTP) below to proceed with setting your new password:
                            </p>

                            <!-- OTP Box -->
                            <div style="background: #f0f9ff; border: 2px dashed #0284c7; border-radius: 12px; padding: 22px 15px; text-align: center; margin: 24px 0;">
                                <span style="display: block; font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Your One-Time Verification Code</span>
                                <span style="display: inline-block; font-size: 34px; font-weight: 800; color: #0284c7; letter-spacing: 8px; font-family: 'Courier New', Courier, monospace;">{$safeOtp}</span>
                                <span style="display: block; font-size: 12px; color: #64748b; margin-top: 8px;">Valid for <strong>15 minutes</strong></span>
                            </div>

                            <!-- Security Warning -->
                            <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; margin: 24px 0 16px 0;">
                                <p style="margin: 0; font-size: 12.5px; line-height: 1.5; color: #92400e;">
                                    <strong>Important Security Notice:</strong> Never share this code with anyone. NAAP administrators will never ask for your verification code or password.
                                </p>
                            </div>

                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #64748b;">
                                If you did not initiate this request, you can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                                &copy; 2026 NAAP Student Organization System. All rights reserved.<br>
                                This is an automated email. Please do not reply directly to this message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        // Plain text fallback
        $mail->AltBody = "Hello {$displayName},\n\nYour One-Time Password (OTP) for resetting your NAAP account password is: {$otpCode}\n\nThis code expires in 15 minutes.\nIf you did not request this, please ignore this email.\n\nNAAP Student Organization";

        $mail->send();

        return [
            'success' => true,
            'message' => "Verification code sent to your email address ({$toEmail}).",
            'code'    => $otpCode,
            'error'   => null
        ];

    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        error_log("[Mailer Error] Failed to send email to {$toEmail}: {$errorMessage}");

        $needsAppPassword = strpos($errorMessage, 'Application-specific password required') !== false ||
                            strpos($errorMessage, 'InvalidSecondFactor') !== false ||
                            strpos($errorMessage, 'Could not authenticate') !== false;

        return [
            'success'            => false,
            'message'            => $needsAppPassword 
                ? 'Unable to authenticate with email provider. Please ensure a 16-character Google App Password is configured.' 
                : 'Failed to send verification email. Please try again.',
            'error'              => $errorMessage,
            'needs_app_password' => $needsAppPassword,
            'code'               => $otpCode // Kept for dev/testing fallback
        ];
    }
}
