<?php
require_once '../includes/config.php';
include '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/crypto.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$sql = "SELECT v.*, o.name AS owner_name, o.house_number, g.name AS guard_name
        FROM visitors v
        JOIN owners o ON v.owner_id = o.owner_id
        LEFT JOIN guards g ON v.guard_id = g.guard_id
        ORDER BY v.created_at DESC";
$result = $conn->query($sql);

$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['name_plain'] = vm_decrypt($row['name']) ?? '[Encrypted]';
        $row['ic_plain'] = vm_decrypt($row['ic_number']) ?? '';
        $row['plate_plain'] = vm_decrypt($row['plate_number']) ?? '';
        $row['purpose_plain'] = vm_decrypt($row['purpose']) ?? '';
        $records[] = $row;
    }
}
?>
<div class="flex flex-col gap-6">
    <div class="flex flex-col justify-between gap-4 rounded-3xl bg-white p-6 shadow-soft sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-semibold text-primary">Visitor History</h2>
            <p class="text-sm text-gray-500">Full audit of every visit, approval decision, and guard on duty.</p>
        </div>
        <a href="dashboard.php" class="inline-flex items-center gap-2 self-start rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-dark hover:text-dark">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <section class="rounded-3xl bg-white p-6 shadow-soft" id="active">
        <div class="flex items-center justify-between text-primary">
            <div class="flex items-center gap-3">
                <i class="bi bi-journal-text text-2xl"></i>
                <h5 class="text-lg font-semibold">All Visits</h5>
            </div>
            <span class="rounded-full bg-light px-3 py-1 text-xs font-medium text-primary/80">Total: <?= count($records) ?></span>
        </div>
        <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200">
            <div class="max-h-[520px] overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-dark/90 text-light">
                        <tr>
                            <th class="px-4 py-3 font-semibold">#</th>
                            <th class="px-4 py-3 font-semibold">Visitor</th>
                            <th class="px-4 py-3 font-semibold">IC Number</th>
                            <th class="px-4 py-3 font-semibold">Plate</th>
                            <th class="px-4 py-3 font-semibold">Owner / House</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Guard</th>
                            <th class="px-4 py-3 font-semibold">Created</th>
                            <th class="px-4 py-3 font-semibold">Allowed</th>
                            <th class="px-4 py-3 font-semibold">Checkout</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (!empty($records)): ?>
                            <?php $i = 0; foreach ($records as $row): ?>
                                <tr class="hover:bg-light/40">
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-500"><?= ++$i ?></td>
                                    <td class="px-4 py-3 font-medium text-dark"><?= htmlspecialchars($row['name_plain']) ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['ic_plain']) ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['plate_plain'] ?: 'N/A') ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['owner_name']) ?> (<?= htmlspecialchars($row['house_number']) ?>)</td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars(ucfirst($row['category'])) ?></td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-secondary/10 px-3 py-1 text-xs font-semibold text-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['guard_name'] ?: '—') ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= date('d M Y h:i A', strtotime($row['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= $row['allowed_at'] ? date('d M Y h:i A', strtotime($row['allowed_at'])) : '—' ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= $row['checked_out_at'] ? date('d M Y h:i A', strtotime($row['checked_out_at'])) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="px-4 py-6 text-center text-sm text-gray-400">No visitor history available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
