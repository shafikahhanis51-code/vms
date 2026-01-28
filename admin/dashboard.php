<?php
require_once '../includes/config.php';
include '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$ownerCount = (int)$conn->query('SELECT COUNT(*) AS total FROM owners')->fetch_assoc()['total'];
$guardCount = (int)$conn->query('SELECT COUNT(*) AS total FROM guards')->fetch_assoc()['total'];
$visitorCount = (int)$conn->query('SELECT COUNT(*) AS total FROM visitors')->fetch_assoc()['total'];
$activeVisitors = (int)$conn->query("SELECT COUNT(*) AS total FROM visitors WHERE status IN ('Allowed', 'Pending')")->fetch_assoc()['total'];
?>

<div class="space-y-8">
    <header class="flex flex-col gap-2 rounded-3xl bg-white px-8 py-6 shadow-soft">
        <h2 class="text-2xl font-semibold text-primary">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?></h2>
        <p class="text-sm text-gray-500">Use the quick actions below to manage owners, guards, and visitor history.</p>
    </header>

    <section class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-3xl bg-primary p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Owners</h6>
            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-3xl font-semibold"><?= $ownerCount ?></h3>
                <i class="bi bi-people text-3xl opacity-60"></i>
            </div>
            <a href="manage_owners.php" class="mt-6 inline-flex items-center gap-2 rounded-full bg-light/10 px-4 py-2 text-sm font-medium text-light transition hover:bg-light/20">
                <i class="bi bi-people"></i>
                <span>Manage Owners</span>
            </a>
        </article>
        <article class="rounded-3xl bg-secondary p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Guards</h6>
            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-3xl font-semibold"><?= $guardCount ?></h3>
                <i class="bi bi-shield-lock text-3xl opacity-60"></i>
            </div>
            <a href="manage_guards.php" class="mt-6 inline-flex items-center gap-2 rounded-full bg-light/10 px-4 py-2 text-sm font-medium text-light transition hover:bg-light/20">
                <i class="bi bi-shield-lock"></i>
                <span>Manage Guards</span>
            </a>
        </article>
        <article class="rounded-3xl bg-accent/90 p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Visitors</h6>
            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-3xl font-semibold"><?= $visitorCount ?></h3>
                <i class="bi bi-clock-history text-3xl opacity-60"></i>
            </div>
            <a href="visitor_history.php" class="mt-6 inline-flex items-center gap-2 rounded-full bg-light/10 px-4 py-2 text-sm font-medium text-light transition hover:bg-light/20">
                <i class="bi bi-clock-history"></i>
                <span>View History</span>
            </a>
        </article>
        <article class="rounded-3xl bg-primary/80 p-6 text-light shadow-soft">
            <h6 class="text-xs uppercase tracking-[0.2em] text-light/70">Active Visits</h6>
            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-3xl font-semibold"><?= $activeVisitors ?></h3>
                <i class="bi bi-stopwatch text-3xl opacity-60"></i>
            </div>
            <a href="visitor_history.php#active" class="mt-6 inline-flex items-center gap-2 rounded-full bg-light/10 px-4 py-2 text-sm font-medium text-light transition hover:bg-light/20">
                <i class="bi bi-stopwatch"></i>
                <span>Monitor</span>
            </a>
        </article>
    </section>

    <section class="grid gap-6 md:grid-cols-3">
        <article class="flex flex-col justify-between rounded-3xl bg-white p-6 shadow-soft">
            <div>
                <h5 class="text-lg font-semibold text-primary">Register House Owners</h5>
                <p class="mt-2 text-sm text-gray-500">Add or update owner profiles along with their contact details and houses.</p>
            </div>
            <a href="manage_owners.php" class="mt-6 inline-flex items-center gap-2 self-start rounded-full border border-primary px-4 py-2 text-sm font-medium text-primary transition hover:bg-primary hover:text-light">
                <i class="bi bi-person-plus"></i>
                <span>Go to Owners</span>
            </a>
        </article>
        <article class="flex flex-col justify-between rounded-3xl bg-white p-6 shadow-soft">
            <div>
                <h5 class="text-lg font-semibold text-primary">Register Guards</h5>
                <p class="mt-2 text-sm text-gray-500">Create guard accounts with login credentials and assign shifts.</p>
            </div>
            <a href="manage_guards.php" class="mt-6 inline-flex items-center gap-2 self-start rounded-full border border-secondary px-4 py-2 text-sm font-medium text-secondary transition hover:bg-secondary hover:text-light">
                <i class="bi bi-shield-plus"></i>
                <span>Go to Guards</span>
            </a>
        </article>
        <article class="flex flex-col justify-between rounded-3xl bg-white p-6 shadow-soft">
            <div>
                <h5 class="text-lg font-semibold text-primary">Visitor History</h5>
                <p class="mt-2 text-sm text-gray-500">Review every visit, approval decision, and guard on duty.</p>
            </div>
            <a href="visitor_history.php" class="mt-6 inline-flex items-center gap-2 self-start rounded-full border border-dark px-4 py-2 text-sm font-medium text-dark transition hover:bg-dark hover:text-light">
                <i class="bi bi-journal-text"></i>
                <span>View History</span>
            </a>
        </article>
    </section>
</div>

<?php include '../includes/footer.php'; ?>