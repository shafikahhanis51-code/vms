<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/crypto.php';

function send_otp_email($to_email, $token, $visitor_id, ?string $otp_code = null) {
    global $conn;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'platinumenc99@gmail.com';
        $mail->Password   = 'gvxvhkzbxcbitkdt';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('admin@gmail.com', 'Visitor Management System');
        $mail->addAddress($to_email);

        $link = BASE_URL . 'owner/approval.php?token=' . urlencode($token) . '&visitor=' . urlencode((string)$visitor_id);

        $rawVisitorName = 'Visitor';
        $rawIcNumber = 'N/A';
        $rawPlateNumber = 'Not provided';
        $rawPurpose = 'Not provided';
        $rawCategoryLabel = 'Visitor Request';
        $rawOwnerName = 'Resident';
        $rawHouseNumber = '—';
        $rawGuardName = 'Security Team';
        $rawCreatedAt = null;
        $rawVisitWindow = null;

        if ($conn instanceof mysqli) {
            $stmt = $conn->prepare('SELECT v.name, v.ic_number, v.plate_number, v.purpose, v.category, v.visit_duration_minutes, v.created_at, v.expected_checkout, o.name AS owner_name, o.house_number, g.name AS guard_name FROM visitors v JOIN owners o ON v.owner_id = o.owner_id LEFT JOIN guards g ON v.guard_id = g.guard_id WHERE v.visitor_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $visitor_id);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $categoryLabels = [
                            'family' => 'Family Visit',
                            'delivery' => 'Delivery',
                            'friend' => 'Friend Visit',
                            'service' => 'Service Provider',
                        ];

                        $rawVisitorName = vm_decrypt($row['name']) ?? 'Visitor';
                        $rawIcNumber = vm_decrypt($row['ic_number']) ?? 'N/A';
                        $rawPlateNumber = vm_decrypt($row['plate_number']) ?? 'Not provided';
                        $rawPurpose = vm_decrypt($row['purpose']) ?? 'Not provided';
                        $rawCategoryLabel = $categoryLabels[$row['category']] ?? 'Visitor Request';
                        $rawOwnerName = $row['owner_name'] ?? 'Resident';
                        $rawHouseNumber = $row['house_number'] ?? '—';
                        $rawGuardName = $row['guard_name'] ?? 'Security Team';

                        $createdAt = $row['created_at'] ? new DateTime($row['created_at']) : null;
                        $expectedCheckout = $row['expected_checkout'] ? new DateTime($row['expected_checkout']) : null;

                        if ($createdAt) {
                            $rawCreatedAt = $createdAt->format('d M Y · h:i A');
                        }

                        if ($expectedCheckout && $createdAt) {
                            $rawVisitWindow = $createdAt->format('h:i A') . ' — ' . $expectedCheckout->format('h:i A');
                        }
                    }
                }
                $stmt->close();
            }
        }

        $mail->isHTML(true);
        $mail->Subject = 'Visitor Approval Request';
        $visitorName = htmlspecialchars($rawVisitorName, ENT_QUOTES, 'UTF-8');
        $ownerName = htmlspecialchars($rawOwnerName, ENT_QUOTES, 'UTF-8');
        $houseNumber = htmlspecialchars($rawHouseNumber, ENT_QUOTES, 'UTF-8');
        $categoryLabel = htmlspecialchars($rawCategoryLabel, ENT_QUOTES, 'UTF-8');
        $purpose = htmlspecialchars($rawPurpose, ENT_QUOTES, 'UTF-8');
        $plateNumber = htmlspecialchars($rawPlateNumber, ENT_QUOTES, 'UTF-8');
        $icNumber = htmlspecialchars($rawIcNumber, ENT_QUOTES, 'UTF-8');
        $guardName = htmlspecialchars($rawGuardName, ENT_QUOTES, 'UTF-8');
        $createdAt = htmlspecialchars($rawCreatedAt ?: date('d M Y · h:i A'), ENT_QUOTES, 'UTF-8');
        $visitWindow = htmlspecialchars($rawVisitWindow ?: 'Pending scheduling', ENT_QUOTES, 'UTF-8');
        $currentYear = date('Y');
        $otpExpiry = (int)OTP_EXPIRY_MINUTES;
        $otpExpiryText = htmlspecialchars((string)$otpExpiry, ENT_QUOTES, 'UTF-8');
        $otpCodeDisplay = htmlspecialchars($otp_code ?? 'Not available', ENT_QUOTES, 'UTF-8');

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visitor Approval Request</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1f1f1f;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e3e3;border-radius:12px;padding:28px 26px;">
    <h1 style="margin:0 0 12px;font-size:20px;color:#3A4D39;">Visitor approval needed</h1>
    <p style="margin:0 0 18px;font-size:14px;color:#434d41;">Hi {$ownerName}, a visitor is waiting for your confirmation. Use the code below with the approval link.</p>

    <div style="margin:0 0 22px;padding:18px 20px;border-radius:10px;background:#f0f4f0;border:1px solid #d5e0d4;">
      <p style="margin:0 0 6px;font-size:12px;font-weight:600;text-transform:uppercase;color:#4F6F52;letter-spacing:0.08em;">Your OTP code</p>
      <p style="margin:0;font-size:26px;font-weight:700;letter-spacing:10px;color:#1A1A1A;">{$otpCodeDisplay}</p>
      <p style="margin:12px 0 0;font-size:12px;color:#6b736b;">Expires in {$otpExpiryText} minutes.</p>
    </div>

    <p style="margin:0 0 12px;font-size:13px;color:#434d41;">Visitor details</p>
    <table style="width:100%;border-collapse:collapse;font-size:13px;color:#333;margin-bottom:18px;">
      <tbody>
        <tr><td style="padding:6px 0;width:34%;font-weight:600;color:#3A4D39;">Visitor</td><td style="padding:6px 0;">{$visitorName}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">NRIC / ID</td><td style="padding:6px 0;">{$icNumber}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Vehicle</td><td style="padding:6px 0;">{$plateNumber}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Purpose</td><td style="padding:6px 0;">{$purpose}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Category</td><td style="padding:6px 0;">{$categoryLabel}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Requested</td><td style="padding:6px 0;">{$createdAt}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Visit window</td><td style="padding:6px 0;">{$visitWindow}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Guard on duty</td><td style="padding:6px 0;">{$guardName}</td></tr>
        <tr><td style="padding:6px 0;font-weight:600;color:#3A4D39;">Residence</td><td style="padding:6px 0;">{$ownerName} · {$houseNumber}</td></tr>
      </tbody>
    </table>

    <p style="margin:0 0 14px;font-size:13px;color:#434d41;">Open the approval link:</p>
    <p style="margin:0 0 18px;font-size:13px;"><a href="{$link}" style="color:#3A4D39;text-decoration:none;font-weight:600;">Review visitor request</a></p>
    <p style="margin:0 0 8px;font-size:12px;color:#6b736b;">If the link above does not work, copy and paste this URL into your browser:</p>
    <p style="margin:0 0 18px;font-size:12px;color:#3A4D39;word-break:break-all;">{$link}</p>

    <p style="margin:0 0 16px;font-size:12px;color:#6b736b;">This request expires automatically after {$otpExpiryText} minutes or once actioned.</p>
    <p style="margin:0;font-size:11px;color:#9aa59a;">© {$currentYear} Visitor Management System · Automated message, do not reply.</p>
  </div>
</body>
</html>
HTML;

        $plainOtp = $otp_code ?? 'Not available';
        $mail->AltBody = "Visitor approval request for {$rawVisitorName}\nOTP: {$plainOtp}\nPurpose: {$rawPurpose}\nVisit window: " . ($rawVisitWindow ?: 'Pending scheduling') . "\nApproval link: {$link}";

        $mail->send();
    } catch (Exception $e) {
    }
}