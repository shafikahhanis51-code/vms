<?php
require_once '../includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../includes/auth.php';

if (($_SESSION['role'] ?? '') !== 'guard') {
    header('Location: ../index.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/crypto.php';
require_once '../send_mail.php';

$categoryDurations = json_decode(VISIT_CATEGORY_DURATIONS, true);
if (!$categoryDurations) {
    $categoryDurations = [
        'family' => 120,
        'delivery' => 30,
        'friend' => 60,
        'service' => 90,
    ];
}

$categoryLabels = [
    'family' => 'Family – 2 hours',
    'delivery' => 'Delivery – 30 minutes',
    'friend' => 'Friend – 1 hour',
    'service' => 'Service – 1 hour 30 minutes',
];

$errors = [];
$notification = null;

function vm_status_badge_class(string $status): string
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

$guardId = null;
$guardName = '';

$guardStmt = $conn->prepare('SELECT guard_id, name FROM guards WHERE user_id = ? LIMIT 1');
if ($guardStmt) {
    $guardStmt->bind_param('i', $_SESSION['user_id']);
    $guardStmt->execute();
    $guardResult = $guardStmt->get_result();
    if ($guardRow = $guardResult->fetch_assoc()) {
        $guardId = (int)$guardRow['guard_id'];
        $guardName = $guardRow['name'];
    } else {
        $errors[] = 'Guard profile not found. Please contact an administrator.';
    }
    $guardStmt->close();
} else {
    $errors[] = 'Unable to load guard profile.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visitor'])) {
    if ($guardId === null) {
        $errors[] = 'Unable to register visitor because guard profile is missing.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $icNumber = trim($_POST['ic_number'] ?? '');
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        $category = $_POST['category'] ?? '';
        $plateNumber = trim($_POST['plate_number'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');

        if ($name === '' || $icNumber === '' || $ownerId === 0 || $category === '') {
            $errors[] = 'All required fields must be completed.';
        }

        if (!isset($categoryDurations[$category])) {
            $errors[] = 'Invalid visit category selected.';
        }

        if (empty($errors)) {
            $durationMinutes = (int)$categoryDurations[$category];

            $encryptedName = vm_encrypt($name);
            $encryptedIc = vm_encrypt($icNumber);
            $encryptedPlate = vm_encrypt($plateNumber);
            $encryptedPurpose = vm_encrypt($purpose);

            $insertVisitor = $conn->prepare('INSERT INTO visitors (name, ic_number, plate_number, purpose, category, owner_id, guard_id, visit_duration_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($insertVisitor === false) {
                $errors[] = 'Database error while preparing visitor insert.';
            } else {
                $insertVisitor->bind_param('ssssssii', $encryptedName, $encryptedIc, $encryptedPlate, $encryptedPurpose, $category, $ownerId, $guardId, $durationMinutes);

                if ($insertVisitor->execute()) {
                    $visitorId = $insertVisitor->insert_id;
                    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $otpToken = bin2hex(random_bytes(16));
                    $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
                    $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

                    $insertOtp = $conn->prepare('INSERT INTO otp_links (visitor_id, otp_token, otp_code_hash, expires_at, last_sent_at) VALUES (?, ?, ?, ?, NOW())');
                    if ($insertOtp) {
                        $insertOtp->bind_param('isss', $visitorId, $otpToken, $otpHash, $expiresAt);
                        $insertOtp->execute();
                        $insertOtp->close();
                    }

                    $ownerEmail = '';
                    $ownerStmt = $conn->prepare('SELECT email FROM owners WHERE owner_id = ?');
                    if ($ownerStmt) {
                        $ownerStmt->bind_param('i', $ownerId);
                        $ownerStmt->execute();
                        $ownerResult = $ownerStmt->get_result();
                        if ($ownerRow = $ownerResult->fetch_assoc()) {
                            $ownerEmail = $ownerRow['email'];
                        }
                        $ownerStmt->close();
                    }

                    if ($ownerEmail !== '') {
                        send_otp_email($ownerEmail, $otpToken, $visitorId, $otpCode);
                        $notification = 'email-sent';
                    } else {
                        $notification = 'email-missing';
                    }

                    $insertVisitor->close();
                    $redirect = $_SERVER['PHP_SELF'] . '?success=visitor_added';
                    if ($notification) {
                        $redirect .= '&notice=' . $notification;
                    }
                    header('Location: ' . $redirect);
                    exit();
                } else {
                    $errors[] = 'Failed to register visitor. Please try again.';
                }

                $insertVisitor->close();
            }
        }
    }
}

if (isset($_GET['checkout'])) {
    $visitorId = (int)$_GET['checkout'];
    $update = $conn->prepare("UPDATE visitors SET status = 'Checked Out', checked_out_at = NOW() WHERE visitor_id = ? AND status IN ('Allowed', 'Overdue')");
    if ($update) {
        $update->bind_param('i', $visitorId);
        $update->execute();
        $update->close();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=visitor_checkedout');
    exit();
}

$conn->query("UPDATE visitors SET status = 'Overdue' WHERE status = 'Allowed' AND expected_checkout IS NOT NULL AND NOW() > expected_checkout");

$owners = [];
$ownersRes = $conn->query('SELECT owner_id, name, house_number FROM owners ORDER BY name');
if ($ownersRes) {
    while ($row = $ownersRes->fetch_assoc()) {
        $owners[] = $row;
    }
}

$visitorRecords = [];
$visitorSql = "SELECT v.*, o.name AS owner_name, o.house_number, g.name AS guard_name FROM visitors v JOIN owners o ON v.owner_id = o.owner_id LEFT JOIN guards g ON v.guard_id = g.guard_id ORDER BY v.created_at DESC";
$visitorsRes = $conn->query($visitorSql);
if ($visitorsRes) {
    while ($row = $visitorsRes->fetch_assoc()) {
        $row['name_plain'] = vm_decrypt($row['name']) ?? '[Encrypted]';
        $row['ic_plain'] = vm_decrypt($row['ic_number']) ?? '';
        $row['plate_plain'] = vm_decrypt($row['plate_number']) ?? '';
        $row['purpose_plain'] = vm_decrypt($row['purpose']) ?? '';

        $allowedTs = (!empty($row['allowed_at']) && $row['allowed_at'] !== '0000-00-00 00:00:00') ? strtotime($row['allowed_at']) : null;
        $expectedTs = (!empty($row['expected_checkout']) && $row['expected_checkout'] !== '0000-00-00 00:00:00') ? strtotime($row['expected_checkout']) : null;

        $row['allowed_iso'] = $allowedTs ? date('c', $allowedTs) : '';
        $row['expected_iso'] = $expectedTs ? date('c', $expectedTs) : '';
        $row['expected_epoch'] = $expectedTs;
        $row['status_class'] = vm_status_badge_class($row['status']);
        $row['category_label'] = $categoryLabels[$row['category']] ?? ucfirst($row['category']);
        $visitorRecords[] = $row;
    }
}

$totalVisitors = count($visitorRecords);
$allowedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;
foreach ($visitorRecords as $record) {
    switch ($record['status']) {
        case 'Allowed':
        case 'Overdue':
        case 'Checked Out':
            $allowedCount++;
            break;
        case 'Pending':
            $pendingCount++;
            break;
        case 'Rejected':
            $rejectedCount++;
            break;
    }
}

include '../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($errors)): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Action required',
    html: <?= json_encode('<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $errors)) . '</li></ul>') ?>,
    confirmButtonColor: '#d33'
});
</script>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
<?php
    $message = '';
    if ($_GET['success'] === 'visitor_added') {
        $message = 'Visitor added successfully.';
        if (isset($_GET['notice'])) {
            if ($_GET['notice'] === 'email-sent') {
                $message .= ' Approval link emailed to the owner.';
            } elseif ($_GET['notice'] === 'email-missing') {
                $message .= ' Owner email is missing. Please update the owner record and resend.';
            }
        }
    } elseif ($_GET['success'] === 'visitor_checkedout') {
        $message = 'Visitor checked out successfully.';
    }
    if ($message !== ''):
?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: <?= json_encode($message) ?>,
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
});
</script>
<?php endif; ?>
<?php endif; ?>


<div class="space-y-8">
    <section class="flex flex-col justify-between gap-4 rounded-3xl bg-white p-6 shadow-soft sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-semibold text-primary">Guard Dashboard</h2>
            <p class="text-sm text-gray-500">Active guard: <?= htmlspecialchars($guardName ?: ($_SESSION['username'] ?? 'Guard')) ?></p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" data-modal-open="add-visitor" <?= $guardId === null ? 'disabled' : '' ?> class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-light shadow-soft transition hover:bg-secondary <?= $guardId === null ? 'opacity-60 cursor-not-allowed' : '' ?>">
                <i class="bi bi-person-plus"></i>
                <span>Add Visitor</span>
            </button>
            <button type="button" id="refreshBtn" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-primary hover:text-primary">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Refresh</span>
            </button>
        </div>
    </section>

    <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-3xl bg-primary p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Total Visitors</h6>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-3xl font-semibold"><?= $totalVisitors ?></p>
                <i class="bi bi-people text-3xl opacity-60"></i>
            </div>
        </article>
        <article class="rounded-3xl bg-secondary p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Allowed / Completed</h6>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-3xl font-semibold"><?= $allowedCount ?></p>
                <i class="bi bi-check-circle text-3xl opacity-60"></i>
            </div>
        </article>
        <article class="rounded-3xl bg-amber-100 p-6 text-amber-800 shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-amber-700/70">Pending</h6>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-3xl font-semibold"><?= $pendingCount ?></p>
                <i class="bi bi-hourglass-split text-3xl opacity-60"></i>
            </div>
        </article>
        <article class="rounded-3xl bg-red-500 p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Rejected</h6>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-3xl font-semibold"><?= $rejectedCount ?></p>
                <i class="bi bi-x-circle text-3xl opacity-60"></i>
            </div>
        </article>
    </section>

    <section class="rounded-3xl bg-white p-6 shadow-soft">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3 text-primary">
                <i class="bi bi-list-ul text-2xl"></i>
                <h3 class="text-lg font-semibold">Visitor List</h3>
            </div>
            <div class="relative w-full max-w-sm">
                <input id="searchInput" type="text" placeholder="Search visitors..." class="w-full rounded-full border border-gray-200 bg-white/80 px-5 py-2.5 pl-11 text-sm text-gray-700 shadow-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30">
                <i class="bi bi-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200">
            <div class="max-h-[620px] overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-secondary/10 text-secondary">
                        <tr>
                            <th class="px-4 py-3 font-semibold">#</th>
                            <th class="px-4 py-3 font-semibold">Visitor</th>
                            <th class="px-4 py-3 font-semibold">Plate</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Owner / House</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Countdown</th>
                            <th class="px-4 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="visitorTableBody">
                        <?php $i = 0; foreach ($visitorRecords as $record): ?>
                            <tr class="transition hover:bg-light/40" data-visitor-row data-id="<?= $record['visitor_id'] ?>" data-status="<?= htmlspecialchars($record['status']) ?>" data-allowed="<?= htmlspecialchars($record['allowed_iso']) ?>" data-expected="<?= htmlspecialchars($record['expected_iso']) ?>" data-expected-epoch="<?= $record['expected_epoch'] !== null ? (int)$record['expected_epoch'] : '' ?>" data-duration="<?= (int)$record['visit_duration_minutes'] ?>">
                                <td class="px-4 py-4 text-sm font-semibold text-gray-500 align-top"><?= ++$i ?></td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary">
                                            <?= strtoupper(substr($record['name_plain'], 0, 1)) ?>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-semibold text-dark"><?= htmlspecialchars($record['name_plain']) ?></p>
                                            <p class="text-xs text-gray-500">IC: <?= htmlspecialchars($record['ic_plain']) ?></p>
                                            <p class="text-xs text-gray-400">Created: <?= date('d M Y h:i A', strtotime($record['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-gray-500"><?= htmlspecialchars($record['plate_plain'] ?: '—') ?></td>
                                <td class="px-4 py-4 align-top text-sm text-gray-500"><?= htmlspecialchars($record['category_label']) ?></td>
                                <td class="px-4 py-4 align-top text-sm text-gray-500">
                                    <p class="font-medium text-dark"><?= htmlspecialchars($record['owner_name']) ?></p>
                                    <p class="text-xs text-gray-400">House <?= htmlspecialchars($record['house_number']) ?></p>
                                    <?php if (!empty($record['guard_name'])): ?>
                                        <p class="text-xs text-gray-400">Guard: <?= htmlspecialchars($record['guard_name']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="status-badge inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $record['status_class'] ?>"><?= htmlspecialchars($record['status']) ?></span>
                                </td>
                                <td class="px-4 py-4 align-top text-sm font-mono text-gray-600" data-countdown>—</td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-center gap-2" data-action-wrap>
                                        <?php if (in_array($record['status'], ['Allowed', 'Overdue'], true)): ?>
                                            <a href="?checkout=<?= $record['visitor_id'] ?>" class="inline-flex items-center gap-2 rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50" data-checkout-button>
                                                <i class="bi bi-box-arrow-right"></i>
                                                <span>Checkout</span>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-primary px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary hover:text-light" data-modal-open="visitor-details"
                                            data-detail-name="<?= htmlspecialchars($record['name_plain'], ENT_QUOTES) ?>"
                                            data-detail-ic="<?= htmlspecialchars($record['ic_plain'], ENT_QUOTES) ?>"
                                            data-detail-owner="<?= htmlspecialchars($record['owner_name'], ENT_QUOTES) ?>"
                                            data-detail-house="<?= htmlspecialchars($record['house_number'], ENT_QUOTES) ?>"
                                            data-detail-status="<?= htmlspecialchars($record['status'], ENT_QUOTES) ?>"
                                            data-detail-category="<?= htmlspecialchars($record['category_label'], ENT_QUOTES) ?>"
                                            data-detail-plate="<?= htmlspecialchars($record['plate_plain'] ?: 'N/A', ENT_QUOTES) ?>"
                                            data-detail-purpose="<?= htmlspecialchars($record['purpose_plain'] ?: 'N/A', ENT_QUOTES) ?>"
                                            data-detail-created="<?= htmlspecialchars(date('d M Y h:i A', strtotime($record['created_at'])), ENT_QUOTES) ?>"
                                            data-detail-allowed="<?= htmlspecialchars($record['allowed_at'] ? date('d M Y h:i A', strtotime($record['allowed_at'])) : 'N/A', ENT_QUOTES) ?>"
                                            data-detail-expected="<?= htmlspecialchars($record['expected_checkout'] ? date('d M Y h:i A', strtotime($record['expected_checkout'])) : 'N/A', ENT_QUOTES) ?>"
                                            data-detail-checkedout="<?= htmlspecialchars($record['checked_out_at'] ? date('d M Y h:i A', strtotime($record['checked_out_at'])) : 'N/A', ENT_QUOTES) ?>">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p id="emptyState" class="<?= empty($visitorRecords) ? '' : 'hidden' ?> px-6 py-8 text-center text-sm text-gray-400">No visitors registered yet.</p>
        </div>
    </section>
</div>

<div data-modal="add-visitor" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 text-primary">
                <i class="bi bi-person-plus text-2xl"></i>
                <h3 class="text-lg font-semibold">Add New Visitor</h3>
            </div>
            <button type="button" class="rounded-full bg-light px-3 py-1 text-sm font-semibold text-primary transition hover:bg-primary hover:text-light" data-modal-close>
                Close
            </button>
        </div>
        <form method="POST" id="visitorForm" class="mt-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-600">Visitor Name *</label>
                    <input type="text" name="name" placeholder="Enter visitor name" required <?= $guardId === null ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">IC Number *</label>
                    <input type="text" name="ic_number" placeholder="Enter IC number" required <?= $guardId === null ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">License Plate</label>
                    <input type="text" name="plate_number" placeholder="Enter vehicle plate number" <?= $guardId === null ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Select Owner *</label>
                    <select name="owner_id" required <?= $guardId === null ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                        <option value="">-- Select Owner --</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= $owner['owner_id'] ?>"><?= htmlspecialchars($owner['name']) ?> (House <?= htmlspecialchars($owner['house_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Visit Category *</label>
                    <select name="category" required <?= $guardId === null ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categoryLabels as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600">Purpose of Visit</label>
                <textarea name="purpose" rows="3" placeholder="Describe the purpose of the visit" <?= $guardId === null ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold text-gray-500 transition hover:text-primary" data-modal-close>Cancel</button>
                <button type="submit" name="add_visitor" <?= $guardId === null ? 'disabled' : '' ?> class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-light shadow-soft transition hover:bg-secondary <?= $guardId === null ? 'opacity-60 cursor-not-allowed' : '' ?>">
                    <i class="bi bi-paper-plane"></i>
                    <span>Submit Request</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div data-modal="visitor-details" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between text-primary">
            <h3 class="text-lg font-semibold">Visitor Details</h3>
            <button type="button" class="rounded-full bg-light px-3 py-1 text-sm font-semibold text-primary transition hover:bg-primary hover:text-light" data-modal-close>Close</button>
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-600 sm:grid-cols-2">
            <div><dt class="font-medium text-dark">Name</dt><dd data-details="name"></dd></div>
            <div><dt class="font-medium text-dark">IC Number</dt><dd data-details="ic"></dd></div>
            <div><dt class="font-medium text-dark">Owner</dt><dd data-details="owner"></dd></div>
            <div><dt class="font-medium text-dark">House</dt><dd data-details="house"></dd></div>
            <div><dt class="font-medium text-dark">Category</dt><dd data-details="category"></dd></div>
            <div><dt class="font-medium text-dark">Plate</dt><dd data-details="plate"></dd></div>
            <div class="sm:col-span-2"><dt class="font-medium text-dark">Purpose</dt><dd data-details="purpose"></dd></div>
            <div><dt class="font-medium text-dark">Status</dt><dd data-details="status"></dd></div>
            <div><dt class="font-medium text-dark">Created</dt><dd data-details="created"></dd></div>
            <div><dt class="font-medium text-dark">Allowed</dt><dd data-details="allowed"></dd></div>
            <div><dt class="font-medium text-dark">Expected Checkout</dt><dd data-details="expected"></dd></div>
            <div class="sm:col-span-2"><dt class="font-medium text-dark">Checked Out</dt><dd data-details="checkedout"></dd></div>
        </dl>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('visitorTableBody');
    const emptyState = document.getElementById('emptyState');
    const rows = () => Array.from(tableBody.querySelectorAll('[data-visitor-row]'));
    const STATUS_CLASS_MAP = {
        Pending: 'bg-amber-100 text-amber-700',
        Allowed: 'bg-emerald-100 text-emerald-700',
        Rejected: 'bg-red-100 text-red-700',
        Overdue: 'bg-dark text-light',
        'Checked Out': 'bg-gray-200 text-gray-700',
        default: 'bg-gray-200 text-gray-700'
    };
    const BADGE_BASE = 'status-badge inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition';

    function updateEmptyState() {
        const visible = rows().some(row => !row.classList.contains('hidden'));
        if (emptyState) {
            emptyState.classList.toggle('hidden', visible);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();
            rows().forEach(row => {
                const text = row.innerText.toLowerCase();
                row.classList.toggle('hidden', term !== '' && !text.includes(term));
            });
            updateEmptyState();
        });
    }

    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => window.location.reload());
    }

    function openModal(id) {
        const modal = document.querySelector(`[data-modal="${id}"]`);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(modal) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-modal-open]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            if (trigger.disabled) return;
            openModal(trigger.dataset.modalOpen);
        });
    });

    document.querySelectorAll('[data-modal]').forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
        modal.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => closeModal(modal));
        });
    });

    document.querySelectorAll('[data-modal-open="visitor-details"]').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.querySelector('[data-modal="visitor-details"]');
            if (!modal) return;
            modal.querySelector('[data-details="name"]').textContent = button.dataset.detailName || 'N/A';
            modal.querySelector('[data-details="ic"]').textContent = button.dataset.detailIc || 'N/A';
            modal.querySelector('[data-details="owner"]').textContent = button.dataset.detailOwner || 'N/A';
            modal.querySelector('[data-details="house"]').textContent = button.dataset.detailHouse || 'N/A';
            modal.querySelector('[data-details="category"]').textContent = button.dataset.detailCategory || 'N/A';
            modal.querySelector('[data-details="plate"]').textContent = button.dataset.detailPlate || 'N/A';
            modal.querySelector('[data-details="purpose"]').textContent = button.dataset.detailPurpose || 'N/A';
            modal.querySelector('[data-details="status"]').textContent = button.dataset.detailStatus || 'N/A';
            modal.querySelector('[data-details="created"]').textContent = button.dataset.detailCreated || 'N/A';
            modal.querySelector('[data-details="allowed"]').textContent = button.dataset.detailAllowed || 'N/A';
            modal.querySelector('[data-details="expected"]').textContent = button.dataset.detailExpected || 'N/A';
            modal.querySelector('[data-details="checkedout"]').textContent = button.dataset.detailCheckedout || 'N/A';
        });
    });

    function updateCountdowns() {
        const now = Date.now();
        rows().forEach(row => {
            const status = row.dataset.status;
            const countdownCell = row.querySelector('[data-countdown]');
            if (!countdownCell) return;

            const expectedEpoch = Number.parseInt(row.dataset.expectedEpoch || '', 10);

            if ((status !== 'Allowed' && status !== 'Overdue') || !Number.isFinite(expectedEpoch) || Number.isNaN(expectedEpoch)) {
                if (status === 'Overdue') {
                    countdownCell.textContent = 'Overdue';
                    countdownCell.classList.add('text-red-600', 'font-semibold');
                } else {
                    countdownCell.textContent = '—';
                    countdownCell.classList.remove('text-red-600', 'font-semibold');
                }
                return;
            }

            const diffMs = expectedEpoch * 1000 - now;
            if (diffMs <= 0) {
                countdownCell.textContent = 'Overdue';
                countdownCell.classList.add('text-red-600', 'font-semibold');
            } else {
                countdownCell.classList.remove('text-red-600', 'font-semibold');
                const totalSeconds = Math.floor(diffMs / 1000);
                const hrs = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
                const mins = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
                const secs = (totalSeconds % 60).toString().padStart(2, '0');
                countdownCell.textContent = `${hrs}:${mins}:${secs}`;
            }
        });
    }

    updateCountdowns();
    setInterval(updateCountdowns, 1000);

    function ensureCheckoutButton(row, visitorId) {
        let checkoutBtn = row.querySelector('[data-checkout-button]');
        if (!checkoutBtn) {
            checkoutBtn = document.createElement('a');
            checkoutBtn.href = `?checkout=${visitorId}`;
            checkoutBtn.className = 'inline-flex items-center gap-2 rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50';
            checkoutBtn.setAttribute('data-checkout-button', '');
            checkoutBtn.innerHTML = '<i class="bi bi-box-arrow-right"></i><span>Checkout</span>';
            const wrap = row.querySelector('[data-action-wrap]');
            wrap?.prepend(checkoutBtn);
        }
    }

    function removeCheckoutButton(row) {
        const btn = row.querySelector('[data-checkout-button]');
        if (btn) {
            btn.remove();
        }
    }

    async function refreshStatuses() {
        try {
            const response = await fetch('../check_status.php', { cache: 'no-store' });
            if (!response.ok) throw new Error('Network response was not ok');
            const payload = await response.json();
            rows().forEach(row => {
                const id = row.dataset.id;
                if (!payload[id]) return;
                const info = payload[id];
                row.dataset.status = info.status;
                row.dataset.allowed = info.allowed || '';
                row.dataset.expected = info.expected || '';
                row.dataset.expectedEpoch = (info.expected_epoch !== null && info.expected_epoch !== undefined)
                    ? String(info.expected_epoch)
                    : '';
                const badge = row.querySelector('.status-badge');
                if (badge) {
                    const classes = STATUS_CLASS_MAP[info.status] || STATUS_CLASS_MAP.default;
                    badge.className = `${BADGE_BASE} ${classes}`;
                    badge.textContent = info.status;
                }
                if (info.status === 'Allowed' || info.status === 'Overdue') {
                    ensureCheckoutButton(row, id);
                } else {
                    removeCheckoutButton(row);
                }
            });
            updateCountdowns();
        } catch (error) {
            console.error('Failed to refresh statuses', error);
        }
    }

    setInterval(refreshStatuses, 5000);
    updateEmptyState();
});
</script>

<?php include '../includes/footer.php'; ?>
