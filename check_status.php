<?php
require_once 'includes/db.php';

$conn->query("UPDATE visitors SET status = 'Overdue' WHERE status = 'Allowed' AND expected_checkout IS NOT NULL AND NOW() > expected_checkout");

$result = $conn->query("SELECT visitor_id, status, allowed_at, expected_checkout FROM visitors");
$data = [];
while ($row = $result->fetch_assoc()) {
    $badgeClass = 'bg-gray-200 text-gray-700';
    switch ($row['status']) {
        case 'Pending':
            $badgeClass = 'bg-amber-100 text-amber-700';
            break;
        case 'Allowed':
            $badgeClass = 'bg-emerald-100 text-emerald-700';
            break;
        case 'Rejected':
            $badgeClass = 'bg-red-100 text-red-700';
            break;
        case 'Overdue':
            $badgeClass = 'bg-dark text-light';
            break;
        case 'Checked Out':
            $badgeClass = 'bg-gray-200 text-gray-700';
            break;
    }

    $allowedTs = (!empty($row['allowed_at']) && $row['allowed_at'] !== '0000-00-00 00:00:00') ? strtotime($row['allowed_at']) : null;
    $expectedTs = (!empty($row['expected_checkout']) && $row['expected_checkout'] !== '0000-00-00 00:00:00') ? strtotime($row['expected_checkout']) : null;

    $data[$row['visitor_id']] = [
        'status' => $row['status'],
        'badgeClass' => $badgeClass,
        'allowed' => $allowedTs ? date('c', $allowedTs) : null,
        'expected' => $expectedTs ? date('c', $expectedTs) : null,
        'allowed_epoch' => $allowedTs,
        'expected_epoch' => $expectedTs,
    ];
}

header('Content-Type: application/json');
echo json_encode($data);