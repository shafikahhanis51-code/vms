<?php
require_once '../includes/config.php';
include '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $houseNumber = trim($_POST['house_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $houseNumber === '' || $address === '') {
        $errors[] = 'All fields are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO owners (name, email, phone, house_number, address) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('sssss', $name, $email, $phone, $houseNumber, $address);
            if ($stmt->execute()) {
                $success = 'Owner registered successfully.';
            } else {
                $errors[] = 'Failed to save owner. Please try again.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error while preparing the statement.';
        }
    }
}

$owners = $conn->query('SELECT * FROM owners ORDER BY created_at DESC');
?>
<div class="flex flex-col gap-6">
    <div class="flex flex-col justify-between gap-4 rounded-3xl bg-white p-6 shadow-soft sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-semibold text-primary">Manage Owners</h2>
            <p class="text-sm text-gray-500">Register new house owners and review existing records.</p>
        </div>
        <a href="dashboard.php" class="inline-flex items-center gap-2 self-start rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-primary hover:text-primary">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[350px,1fr]">
        <section class="rounded-3xl bg-white p-6 shadow-soft">
            <div class="flex items-center gap-3 text-primary">
                <i class="bi bi-person-plus text-2xl"></i>
                <h5 class="text-lg font-semibold">Register Owner</h5>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600">Owner Name</label>
                    <input type="text" name="name" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Email</label>
                    <input type="email" name="email" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Phone Number</label>
                    <input type="text" name="phone" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">House Number</label>
                    <input type="text" name="house_number" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Address</label>
                    <textarea name="address" rows="3" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40"></textarea>
                </div>
                <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-light shadow-soft transition hover:bg-secondary">Save Owner</button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between text-primary">
                <div class="flex items-center gap-3">
                    <i class="bi bi-people text-2xl"></i>
                    <h5 class="text-lg font-semibold">Registered Owners</h5>
                </div>
                <span class="rounded-full bg-light px-3 py-1 text-xs font-medium text-primary/80">Total: <?= $owners ? $owners->num_rows : 0 ?></span>
            </div>
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200">
                <div class="max-h-[420px] overflow-y-auto">
                    <table class="min-w-full text-left text-sm text-gray-600">
                        <thead class="bg-secondary/10 text-secondary">
                            <tr>
                                <th class="px-4 py-3 font-semibold">#</th>
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold">Email</th>
                                <th class="px-4 py-3 font-semibold">Phone</th>
                                <th class="px-4 py-3 font-semibold">House No.</th>
                                <th class="px-4 py-3 font-semibold">Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($owners && $owners->num_rows > 0): ?>
                                <?php $i = 0; while ($row = $owners->fetch_assoc()): ?>
                                    <tr class="hover:bg-light/40">
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-500"><?= ++$i ?></td>
                                        <td class="px-4 py-3 font-medium text-dark"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="px-4 py-3"><a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-accent hover:text-secondary"><?= htmlspecialchars($row['email']) ?></a></td>
                                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['phone']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['house_number']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= nl2br(htmlspecialchars($row['address'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400">No owners registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
