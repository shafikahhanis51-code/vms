<?php include 'includes/header.php'; ?>
<?php
require_once 'includes/db.php';

if (isset($_SESSION['user_id']))
{
    if ($_SESSION['user_id'] != '' && $_SESSION['role'] == 'admin'){
    echo '<script>
        window.location.href = "admin/dashboard.php";
    </script>';
    }
    else if ($_SESSION['user_id'] != '' && $_SESSION['role'] == 'guard'){
    echo '<script>
        window.location.href = "guard/dashboard.php";
    </script>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $storedHash = $user['password'];
        $isValid = false;

        $hashInfo = password_get_info($storedHash);
        if (!empty($hashInfo['algo'])) {
            $isValid = password_verify($password, $storedHash);
        } else {
            $isValid = hash_equals($storedHash, md5($password));
            if ($isValid) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $updateStmt->bind_param('si', $newHash, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
                $storedHash = $newHash;
            }
        }

        if ($isValid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                echo '<script>
        alert("Login successful as Admin");
        window.location.href = "admin/dashboard.php";
    </script>';
                exit();
            } else {
                echo '<script>
        alert("Login successful as Guard");
        window.location.href = "guard/dashboard.php";
    </script>';
                exit();
            }
        }
    }

    $error = "Invalid username or password.";
    $stmt->close();
}
?>


<div class="flex min-h-[calc(100vh-6rem)] items-center justify-center">
    <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-soft transition hover:shadow-xl">
        <div class="text-center">
            <h3 class="text-2xl font-semibold text-primary">Welcome Back</h3>
            <p class="mt-1 text-sm text-gray-500">Sign in to continue managing visitors.</p>
        </div>
        <?php if (isset($error)): ?>
            <div class="mt-6 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="mt-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 shadow-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-700 shadow-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40">
            </div>
            <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-light shadow-soft transition hover:bg-secondary">
                Login
            </button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>