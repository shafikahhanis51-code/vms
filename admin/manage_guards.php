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
    $icNumber = trim($_POST['ic_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $shift = trim($_POST['shift'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $icNumber === '' || $phone === '' || $shift === '' || $username === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (empty($errors)) {
        $existsStmt = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $existsStmt->bind_param('s', $username);
        $existsStmt->execute();
        $existsStmt->store_result();
        if ($existsStmt->num_rows > 0) {
            $errors[] = 'Username already exists. Choose another username.';
        }
        $existsStmt->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, "guard")');
            $email = $username . '@guard.local';
            $userStmt->bind_param('sss', $username, $email, $passwordHash);
            $userStmt->execute();
            $userId = $userStmt->insert_id;
            $userStmt->close();

            $guardStmt = $conn->prepare('INSERT INTO guards (user_id, name, ic_number, phone, shift) VALUES (?, ?, ?, ?, ?)');
            $guardStmt->bind_param('issss', $userId, $name, $icNumber, $phone, $shift);
            $guardStmt->execute();
            $guardStmt->close();

            $conn->commit();
            $success = 'Guard account created successfully.';
        } catch (Exception $ex) {
            $conn->rollback();
            $errors[] = 'Failed to create guard account: ' . $ex->getMessage();
        }
    }
}

$guardQuery = $conn->query('SELECT g.*, u.username FROM guards g JOIN users u ON g.user_id = u.id ORDER BY g.created_at DESC');
?>
<div class="flex flex-col gap-6">
    <div class="flex flex-col justify-between gap-4 rounded-3xl bg-white p-6 shadow-soft sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-semibold text-primary">Manage Guards</h2>
            <p class="text-sm text-gray-500">Register new guards and manage their login credentials.</p>
        </div>
        <a href="dashboard.php" class="inline-flex items-center gap-2 self-start rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-secondary hover:text-secondary">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[350px,1fr]">
        <section class="rounded-3xl bg-white p-6 shadow-soft">
            <div class="flex items-center gap-3 text-secondary">
                <i class="bi bi-shield-plus text-2xl"></i>
                <h5 class="text-lg font-semibold">Register Guard</h5>
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
                    <label class="block text-sm font-medium text-gray-600">Name</label>
                    <input type="text" name="name" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">IC Number</label>
                    <input type="text" name="ic_number" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Phone</label>
                    <input type="text" name="phone" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Shift</label>
                    <select name="shift" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                        <option value="">-- Select Shift --</option>
                        <option value="Morning">Morning</option>
                        <option value="Afternoon">Afternoon</option>
                        <option value="Night">Night</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Username</label>
                    <input type="text" name="username" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Password</label>
                    <input type="password" name="password" minlength="6" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>
                <button type="submit" class="w-full rounded-xl bg-secondary px-4 py-3 text-sm font-semibold text-light shadow-soft transition hover:bg-primary">Create Guard</button>
            </form>
        </section>

        <section class="rounded-3xl bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between text-primary">
                <div class="flex items-center gap-3">
                    <i class="bi bi-shield-lock text-2xl"></i>
                    <h5 class="text-lg font-semibold">Registered Guards</h5>
                </div>
                <span class="rounded-full bg-light px-3 py-1 text-xs font-medium text-primary/80">Total: <?= $guardQuery ? $guardQuery->num_rows : 0 ?></span>
            </div>
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200">
                <div class="max-h-[420px] overflow-y-auto">
                    <table class="min-w-full text-left text-sm text-gray-600">
                        <thead class="bg-secondary/10 text-secondary">
                            <tr>
                                <th class="px-4 py-3 font-semibold">#</th>
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold">IC Number</th>
                                <th class="px-4 py-3 font-semibold">Phone</th>
                                <th class="px-4 py-3 font-semibold">Shift</th>
                                <th class="px-4 py-3 font-semibold">Username</th>
                                <th class="px-4 py-3 font-semibold">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($guardQuery && $guardQuery->num_rows > 0): ?>
                                <?php $i = 0; while ($row = $guardQuery->fetch_assoc()): ?>
                                    <tr class="hover:bg-light/40">
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-500"><?= ++$i ?></td>
                                        <td class="px-4 py-3 font-medium text-dark"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['ic_number']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['phone']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['shift']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($row['username']) ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?= date('d M Y h:i A', strtotime($row['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-400">No guards registered yet.</td>
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
