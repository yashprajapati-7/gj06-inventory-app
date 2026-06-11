<?php require_once __DIR__ . '/../functions.php'; ?>
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title><?= isset($pageTitle) ? 'GJ06 - ' . esc($pageTitle) : 'GJ06' ?></title>
  <link rel='stylesheet' href='assets/style.css'>
</head>
<body>

<div class='app-loader' id='appLoader'>
  <div class='loader-card'>
    <img src='assets/gj06_logo.png' alt='GJ06 Logo'>
  </div>
</div>

<?php if (empty($hideHeader)): ?>
<header class='top-header'>
  <div class='brand-box'>
    <img src='assets/gj06_logo.png' alt='GJ06 Logo' class='brand-logo'>
  </div>
  <div class='header-right'>
    <div class='datetime-pill'><span id='liveDateTime'></span></div>
    <a href='logout.php' class='logout-btn'>Logout</a>
  </div>
</header>

<?php if (is_admin()): ?>
<?php $cp = basename($_SERVER['PHP_SELF']); ?>
<nav class='icon-menu'>
    <a href='products.php' class='icon-menu-item <?= $cp==='products.php' ? 'active' : '' ?>'>
      <span class='icon-emoji'>&#128230;</span>
      <span class='icon-label'>PRODUCTS</span>
    </a>
    <a href='users.php' class='icon-menu-item <?= $cp==='users.php' ? 'active' : '' ?>'>
      <span class='icon-emoji'>&#128100;</span>
      <span class='icon-label'>USERS</span>
    </a>
    <a href='supplier_select.php' class='icon-menu-item <?= $cp==='supplier_select.php' ? 'active' : '' ?>'>
      <span class='icon-emoji'>&#127970;</span>
      <span class='icon-label'>SUPPLIERS</span>
    </a>
    <a href='admin_analytics.php' class='icon-menu-item <?= in_array($cp, ['admin_analytics.php','admin_orders.php','admin_invoices.php']) ? 'active' : '' ?>'>
      <span class='icon-emoji'>&#128202;</span>
      <span class='icon-label'>ANALYTICS</span>
    </a>
</nav>
<?php endif; ?>

<div class='drawer' id='drawer'>
  <div class='drawer-header'>
    <img src='assets/gj06_logo.png' alt='GJ06 Logo' class='drawer-logo-image'>
    <div>
      <div class='drawer-title'>GJ06</div>
      <div class='drawer-subtitle'>Menu</div>
    </div>
  </div>
  <nav class='drawer-links'>
    <a href='products.php' onclick='closeMenu()'>Products</a>
    <?php if (is_admin()): ?>
      <a href='supplier_select.php' onclick='closeMenu()'>Suppliers</a>
      <a href='users.php' onclick='closeMenu()'>Users</a>
      <a href='admin_analytics.php' onclick='closeMenu()'>Analytics</a>
      <a href='admin_orders.php' onclick='closeMenu()'>Order History</a>
      <a href='admin_invoices.php' onclick='closeMenu()'>Invoices</a>
    <?php endif; ?>
    <a href='logout.php' onclick='closeMenu()'>Logout</a>
  </nav>
</div>

<div class='drawer-overlay' id='drawerOverlay' onclick='closeMenu()'></div>
<?php endif; ?>
<div class='page-shell'>
