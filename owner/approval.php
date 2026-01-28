<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/crypto.php';

$categoryDurations = json_decode(VISIT_CATEGORY_DURATIONS, true) ?: [
    'family' => 120,
    'delivery' => 30,
    'friend' => 60,
    'service' => 90,
];

$categoryLabels = [
    'family' => 'Family – 2 hours',
    'delivery' => 'Delivery – 30 minutes',
    'friend' => 'Friend – 1 hour',
    'service' => 'Service – 1 hour 30 minutes',
];

$token = $_GET['token'] ?? '';
$visitorId = isset($_GET['visitor']) ? (int)$_GET['visitor'] : 0;

if ($token === '' || $visitorId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$otpStmt = $conn->prepare('SELECT * FROM otp_links WHERE otp_token = ? AND visitor_id = ? LIMIT 1');
$otpStmt->bind_param('si', $token, $visitorId);
$otpStmt->execute();
$otpResult = $otpStmt->get_result();
$otpData = $otpResult->fetch_assoc();
$otpStmt->close();

if (!$otpData) {
    http_response_code(404);
    exit('This approval link is invalid or has already been used.');
}

$isOtpUsed = (bool)$otpData['is_used'];
$isOtpExpired = false;
if (!empty($otpData['expires_at'])) {
    $isOtpExpired = strtotime($otpData['expires_at']) < time();
}

$visitorStmt = $conn->prepare('SELECT v.*, o.name AS owner_name, o.house_number, o.phone, g.name AS guard_name
    FROM visitors v
    JOIN owners o ON v.owner_id = o.owner_id
    LEFT JOIN guards g ON v.guard_id = g.guard_id
    WHERE v.visitor_id = ?');
$visitorStmt->bind_param('i', $visitorId);
$visitorStmt->execute();
$visitorResult = $visitorStmt->get_result();
$visitor = $visitorResult->fetch_assoc();
$visitorStmt->close();

if (!$visitor) {
    http_response_code(404);
    exit('Visitor record not found.');
}

$visitor['name_plain'] = vm_decrypt($visitor['name']) ?? '[Encrypted]';
$visitor['ic_plain'] = vm_decrypt($visitor['ic_number']) ?? '';
$visitor['plate_plain'] = vm_decrypt($visitor['plate_number']) ?? '';
$visitor['purpose_plain'] = vm_decrypt($visitor['purpose']) ?? '';
$visitor['category_label'] = $categoryLabels[$visitor['category']] ?? ucfirst($visitor['category']);

$errors = [];
$successMessage = '';
$canTakeAction = !$isOtpUsed && !$isOtpExpired && $visitor['status'] === 'Pending';

function vm_tailwind_status_badge(string $status): string
{
    switch ($status) {
        case 'Pending':
            return 'bg-amber-100 text-amber-700';
        case 'Allowed':
            return 'bg-emerald-100 text-emerald-700';
        case 'Rejected':
            return 'bg-red-100 text-red-700';
        case 'Overdue':
            return 'bg-dark text-light';
        case 'Checked Out':
            return 'bg-gray-200 text-gray-700';
        default:
            return 'bg-gray-200 text-gray-700';
    }
}

$statusBadgeClass = vm_tailwind_status_badge($visitor['status']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canTakeAction) {
    $decision = $_POST['decision'] ?? '';
    $otpCode = trim($_POST['otp_code'] ?? '');

    if ($otpCode === '') {
        $errors[] = 'OTP code is required.';
    } elseif (!password_verify($otpCode, $otpData['otp_code_hash'])) {
        $errors[] = 'The OTP you entered is incorrect.';
    }

    if (!in_array($decision, ['approve', 'reject'], true)) {
        $errors[] = 'Invalid action requested.';
    }

    if (empty($errors)) {
        if ($decision === 'approve') {
            $durationMinutes = (int)$visitor['visit_duration_minutes'];
            if ($durationMinutes <= 0) {
                $durationMinutes = (int)($categoryDurations[$visitor['category']] ?? 60);
            }

            $update = $conn->prepare("UPDATE visitors SET status = 'Allowed', allowed_at = NOW(), expected_checkout = DATE_ADD(NOW(), INTERVAL ? MINUTE), checked_out_at = NULL WHERE visitor_id = ?");
            $update->bind_param('ii', $durationMinutes, $visitorId);
            $update->execute();
            $update->close();

            $successMessage = 'Visitor approved. Access granted.';
            $visitor['status'] = 'Allowed';
            $visitor['allowed_at'] = date('Y-m-d H:i:s');
            $visitor['expected_checkout'] = date('Y-m-d H:i:s', time() + $durationMinutes * 60);
        } else {
            $update = $conn->prepare("UPDATE visitors SET status = 'Rejected', allowed_at = NULL, expected_checkout = NULL WHERE visitor_id = ?");
            $update->bind_param('i', $visitorId);
            $update->execute();
            $update->close();

            $successMessage = 'Visitor rejected. Access denied.';
            $visitor['status'] = 'Rejected';
            $visitor['allowed_at'] = null;
            $visitor['expected_checkout'] = null;
        }

        $markUsed = $conn->prepare('UPDATE otp_links SET is_used = 1 WHERE otp_id = ?');
        $markUsed->bind_param('i', $otpData['otp_id']);
        $markUsed->execute();
        $markUsed->close();

        $isOtpUsed = true;
        $canTakeAction = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Approval</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3A4D39',
                        secondary: '#4F6F52',
                        accent: '#739072',
                        light: '#ECE3CE',
                        dark: '#1A1A1A'
                    },
                    boxShadow: {
                        soft: '0 4px 6px rgba(0, 0, 0, 0.1)'
                    }
                }
            }
        };
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-screen bg-light px-4 py-10 font-sans text-dark">
<main class="mx-auto max-w-3xl rounded-3xl bg-white p-8 shadow-soft">
    <header class="flex flex-col items-center gap-3 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-3xl text-primary">
            <i class="bi bi-person-check"></i>
        </div>
        <div>
            <h1 class="text-2xl font-semibold text-primary">Visitor Approval</h1>
            <p class="text-sm text-gray-500">Confirm the visitor request by validating the emailed OTP.</p>
        </div>
    </header>

    <section class="mt-8 space-y-3 rounded-2xl bg-light/60 p-6">
        <div class="grid grid-cols-1 gap-3 text-sm text-gray-600 sm:grid-cols-2">
            <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Visitor</span><?= htmlspecialchars($visitor['name_plain']) ?></p>
            <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">IC Number</span><?= htmlspecialchars($visitor['ic_plain']) ?></p>
            <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Vehicle Plate</span><?= htmlspecialchars($visitor['plate_plain'] ?: 'N/A') ?></p>
            <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Category</span><?= htmlspecialchars($visitor['category_label']) ?></p>
            <p class="sm:col-span-2"><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Purpose</span><?= htmlspecialchars($visitor['purpose_plain'] ?: 'N/A') ?></p>
            <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">House Owner</span><?= htmlspecialchars($visitor['owner_name']) ?> (<?= htmlspecialchars($visitor['house_number']) ?>)</p>
            <?php if (!empty($visitor['guard_name'])): ?>
                <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Guard on Duty</span><?= htmlspecialchars($visitor['guard_name']) ?></p>
            <?php endif; ?>
            <p class="sm:col-span-2 flex items-center gap-3"><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Status</span>
                <span class="inline-flex items-center rounded-full px-4 py-1 text-xs font-semibold <?= $statusBadgeClass ?>"><?= htmlspecialchars($visitor['status']) ?></span>
            </p>
            <?php if (!empty($visitor['allowed_at'])): ?>
                <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Allowed At</span><?= date('d M Y h:i A', strtotime($visitor['allowed_at'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($visitor['expected_checkout'])): ?>
                <p><span class="block text-xs font-semibold uppercase tracking-wide text-primary">Expected Checkout</span><?= date('d M Y h:i A', strtotime($visitor['expected_checkout'])) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($successMessage !== ''): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: <?= json_encode($successMessage) ?>,
                confirmButtonColor: '#4F6F52'
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Unable to process request',
                html: <?= json_encode('<ul class="space-y-1 text-left"><li>' . implode('</li><li>', array_map('htmlspecialchars', $errors)) . '</li></ul>') ?>,
                confirmButtonColor: '#c0392b'
            });
        </script>
    <?php endif; ?>

    <section class="mt-6 space-y-4">
        <?php if ($isOtpExpired): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">This OTP has expired. Please ask the guard to resend a new verification code.</div>
        <?php elseif ($isOtpUsed && $successMessage === ''): ?>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">This OTP has already been used. If you need to change your decision, please contact the guard.</div>
        <?php elseif ($visitor['status'] !== 'Pending' && $successMessage === ''): ?>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">This visitor request has already been processed.</div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-soft">
            <label class="block text-sm font-medium text-gray-600">Enter OTP Code</label>
            <input type="text" name="otp_code" maxlength="6" placeholder="6-digit OTP" required <?= !$canTakeAction ? 'disabled' : '' ?> class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40<?= !$canTakeAction ? ' bg-gray-100 text-gray-400 cursor-not-allowed' : '' ?>">
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <button type="submit" name="decision" value="approve" <?= !$canTakeAction ? 'disabled' : '' ?> class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700<?= !$canTakeAction ? ' opacity-60 cursor-not-allowed' : '' ?>">
                    <i class="bi bi-check-circle"></i>
                    <span>Approve</span>
                </button>
                <button type="submit" name="decision" value="reject" <?= !$canTakeAction ? 'disabled' : '' ?> class="inline-flex items-center justify-center gap-2 rounded-full bg-red-500 px-6 py-3 text-sm font-semibold text-white transition hover:bg-red-600<?= !$canTakeAction ? ' opacity-60 cursor-not-allowed' : '' ?>">
                    <i class="bi bi-x-circle"></i>
                    <span>Reject</span>
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
