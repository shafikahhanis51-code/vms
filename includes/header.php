<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isLoggedIn = !empty($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? null;
$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$navLinks = [];

if ($isLoggedIn) {
    if ($userRole === 'admin') {
        $navLinks = [
            ['label' => 'Dashboard', 'href' => BASE_URL . 'admin/dashboard.php'],
            ['label' => 'Owners', 'href' => BASE_URL . 'admin/manage_owners.php'],
            ['label' => 'Guards', 'href' => BASE_URL . 'admin/manage_guards.php'],
            ['label' => 'Visitor History', 'href' => BASE_URL . 'admin/visitor_history.php'],
        ];
    } elseif ($userRole === 'guard') {
        $navLinks = [
            ['label' => 'Guard Dashboard', 'href' => BASE_URL . 'guard/dashboard.php'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management System</title>
    <link rel="shortcut icon" href="<?= BASE_URL ?>assets/images/window.png" type="image/png">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="bg-light text-dark font-sans">
<nav class="fixed inset-x-0 top-0 z-40 bg-primary text-light shadow-soft">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a class="flex items-center space-x-3 text-light no-underline" href="<?= BASE_URL ?>">
            <img src="<?= BASE_URL ?>assets/images/window.png" alt="Logo" class="h-9 w-9 transition-transform duration-200 hover:rotate-12">
            <span class="text-lg font-semibold tracking-wide animate__animated animate__fadeIn">Visitor Management System</span>
        </a>
        <?php if ($isLoggedIn): ?>
            <div class="flex flex-wrap items-center justify-end gap-3 sm:gap-5">
                <?php if (!empty($navLinks)): ?>
                    <nav class="flex flex-wrap items-center justify-end gap-2 text-xs font-medium text-light/80 sm:text-sm">
                        <?php foreach ($navLinks as $link): ?>
                            <?php $isActive = $currentPath === basename($link['href']); ?>
                            <a class="rounded-full px-3 py-2 transition <?= $isActive ? 'bg-secondary/40 text-light shadow-soft' : 'hover:text-light' ?>" href="<?= htmlspecialchars($link['href']) ?>">
                                <?= htmlspecialchars($link['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <a class="flex items-center gap-2 rounded-full bg-secondary/30 px-4 py-2 text-sm font-medium text-light transition hover:bg-secondary/50" href="<?= BASE_URL ?>logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        <?php else: ?>
            <span class="rounded-full bg-secondary/20 px-4 py-2 text-sm font-medium text-light/80">Secure Access Portal</span>
        <?php endif; ?>
    </div>
</nav>
<div class="mx-auto mt-24 w-full max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">