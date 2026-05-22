<?php
/**
 * AR Radius - Shared layout (header + sidebar)
 * Use:
 *   $pageTitle = 'Dashboard';
 *   $activeNav = 'dashboard';
 *   require __DIR__ . '/includes/layout.php';
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
Auth::require();

$pageTitle = $pageTitle ?? 'AR Radius';
$activeNav = $activeNav ?? '';
$user = Auth::user();
$version = ar_version();

$nav = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'home',      'href' => '/index.php'],
    'users'     => ['label' => 'Users',     'icon' => 'users',     'href' => '/users.php'],
    'sessions'  => ['label' => 'Sessions',  'icon' => 'activity',  'href' => '/sessions.php'],
    'system'    => ['label' => 'System',    'icon' => 'settings',  'href' => '/system.php'],
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="csrf-token" content="<?= e(Auth::csrf()) ?>">
<title><?= e($pageTitle) ?> · AR Radius</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="ar-layout">

  <!-- Sidebar -->
  <aside class="ar-sidebar" id="arSidebar">
    <div class="ar-brand">
      <span class="ar-brand-mark">AR</span>
      <span class="ar-brand-text">AR Radius</span>
    </div>
    <nav class="ar-nav">
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= e($item['href']) ?>"
           class="ar-nav-link <?= $activeNav === $key ? 'active' : '' ?>">
          <span class="ar-icon" data-icon="<?= e($item['icon']) ?>"></span>
          <span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach ?>
    </nav>
    <div class="ar-sidebar-footer">
      <div class="ar-user">
        <span class="ar-icon" data-icon="user"></span>
        <span><?= e($user) ?></span>
      </div>
      <a href="/logout.php" class="ar-logout">Logout</a>
      <div class="ar-version">v<?= e($version) ?></div>
    </div>
  </aside>

  <!-- Main -->
  <main class="ar-main">
    <header class="ar-topbar">
      <button class="ar-toggle" id="arToggle" aria-label="Toggle menu">&#9776;</button>
      <h1 class="ar-page-title"><?= e($pageTitle) ?></h1>
      <div class="ar-topbar-right">
        <span class="text-muted small d-none d-md-inline">Signed in as <strong><?= e($user) ?></strong></span>
      </div>
    </header>

    <section class="ar-content">
