<?php
require_once __DIR__ . '/functions.php';
require_admin();

$checkTable = $conn->query("SHOW TABLES LIKE 'orders'");
if (!$checkTable || $checkTable->num_rows === 0) {
    die('Analytics tables not found. Run analytics_migration.sql first.');
}

$message = '';
$error   = '';

// Update order status
if (isset($_POST['update_status'])) {
    $orderId    = (int)($_POST['order_id'] ?? 0);
    $newStatus  = $_POST['status'] ?? '';
    $allowed    = ['pending', 'received', 'cancelled'];
    if ($orderId > 0 && in_array($newStatus, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $stmt->execute();
        $message = 'Order status updated.';
    }
}

// Delete order
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    // Remove saved PDF file if it exists
    $fileRow = $conn->prepare("SELECT pdf_filename FROM orders WHERE id = ?");
    $fileRow->bind_param("i", $deleteId);
    $fileRow->execute();
    $fileData = $fileRow->get_result()->fetch_assoc();
    if ($fileData && $fileData['pdf_filename']) {
        @unlink(__DIR__ . '/storage/orders/' . basename($fileData['pdf_filename']));
    }
    $conn->prepare("DELETE FROM order_items WHERE order_id = ?")->bind_param("i", $deleteId)->execute();
    $conn->prepare("DELETE FROM orders WHERE id = ?")->bind_param("i", $deleteId)->execute();
    header('Location: admin_orders.php?msg=deleted');
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = 'Order deleted.';
}

// Filters
$filterSupplier = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$filterStatus   = isset($_GET['status']) && in_array($_GET['status'], ['pending','received','cancelled'], true) ? $_GET['status'] : '';

$suppliers = $conn->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC");

// Build query
$where  = [];
$params = [];
$types  = '';
if ($filterSupplier > 0) { $where[] = 'o.supplier_id = ?'; $params[] = $filterSupplier; $types .= 'i'; }
if ($filterStatus !== '')  { $where[] = 'o.status = ?'; $params[] = $filterStatus; $types .= 's'; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "SELECT o.*, s.supplier_name, u.name AS ordered_by_name FROM orders o JOIN suppliers s ON s.id = o.supplier_id LEFT JOIN users u ON u.id = o.ordered_by {$whereSQL} ORDER BY o.order_date DESC, o.created_at DESC";

if ($params) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders = $stmt->get_result();
} else {
    $orders = $conn->query($query);
}

// Summary
$totalOrders    = (int)($conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'] ?? 0);
$pendingOrders  = (int)($conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$receivedOrders = (int)($conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='received'")->fetch_assoc()['c'] ?? 0);

$pageTitle = 'Order History';
include __DIR__ . '/partials/header.php';
?>

<?php if ($message): ?><div class='pill page-message'><?= esc($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class='alert-box page-message'><?= esc($error) ?></div><?php endif; ?>

<section class='card'>
  <div class='section-head'>
    <h2>Order History</h2>
    <p>All purchase orders placed by admin</p>
  </div>
  <div class='action-row'>
    <a class='btn btn-dark' href='admin_analytics.php'>Analytics</a>
    <a class='btn btn-outline' href='admin_invoices.php'>Invoices</a>
  </div>
</section>

<!-- Summary -->
<div class='stat-grid'>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Total Orders</div>
    <div class='stat-value'><?= $totalOrders ?></div>
    <div class='stat-sub'>all time</div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Pending</div>
    <div class='stat-value'><?= $pendingOrders ?></div>
    <div class='stat-sub'>awaiting receipt</div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Received</div>
    <div class='stat-value'><?= $receivedOrders ?></div>
    <div class='stat-sub'>completed</div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Cancelled</div>
    <div class='stat-value'><?= $totalOrders - $pendingOrders - $receivedOrders ?></div>
    <div class='stat-sub'>not fulfilled</div>
  </div>
</div>

<!-- Filters -->
<section class='card search-card'>
  <form method='get' class='search-inline-form'>
    <select name='supplier_id' onchange='this.form.submit()'>
      <option value='0'>All Suppliers</option>
      <?php mysqli_data_seek($suppliers, 0); while ($s = $suppliers->fetch_assoc()): ?>
        <option value='<?= (int)$s['id'] ?>' <?= (int)$s['id'] === $filterSupplier ? 'selected' : '' ?>>
          <?= esc($s['supplier_name']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <select name='status' onchange='this.form.submit()'>
      <option value=''>All Statuses</option>
      <option value='pending'   <?= $filterStatus === 'pending'   ? 'selected' : '' ?>>Pending</option>
      <option value='received'  <?= $filterStatus === 'received'  ? 'selected' : '' ?>>Received</option>
      <option value='cancelled' <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
    <?php if ($filterSupplier || $filterStatus): ?>
      <a class='btn btn-outline search-btn' href='admin_orders.php'>Reset</a>
    <?php endif; ?>
  </form>
</section>

<!-- Orders table -->
<section class='card'>
  <?php if ($orders && $orders->num_rows > 0): ?>

  <!-- Desktop table -->
  <div class='table-wrap' style='display:block'>
    <table class='table'>
      <thead>
        <tr>
          <th>Date</th>
          <th>Supplier</th>
          <th>Items</th>
          <th>Est. Total</th>
          <th>Status</th>
          <th>PDF</th>
          <th>Invoice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
        <tr>
          <td><?= date('d M Y', strtotime($o['order_date'])) ?></td>
          <td><?= esc($o['supplier_name']) ?></td>
          <td><?= (int)$o['total_items'] ?></td>
          <td><?= (float)$o['estimated_total'] > 0 ? '$' . number_format((float)$o['estimated_total'], 2) : '<span style="color:#bbb">—</span>' ?></td>
          <td>
            <form method='post' style='display:inline'>
              <input type='hidden' name='order_id' value='<?= (int)$o['id'] ?>'>
              <select name='status' onchange='this.form.submit()' style='min-height:34px;padding:6px 10px;border-radius:10px;font-size:12px;width:auto'>
                <option value='pending'   <?= $o['status']==='pending'   ? 'selected':'' ?>>Pending</option>
                <option value='received'  <?= $o['status']==='received'  ? 'selected':'' ?>>Received</option>
                <option value='cancelled' <?= $o['status']==='cancelled' ? 'selected':'' ?>>Cancelled</option>
              </select>
              <input type='hidden' name='update_status' value='1'>
            </form>
          </td>
          <td>
            <?php if ($o['pdf_filename'] && is_file(__DIR__ . '/storage/orders/' . basename($o['pdf_filename']))): ?>
              <a class='btn btn-small btn-outline' href='download_file.php?type=order&id=<?= (int)$o['id'] ?>'>Download</a>
            <?php else: ?>
              <span style='color:#bbb;font-size:12px'>—</span>
            <?php endif; ?>
          </td>
          <td>
            <a class='btn btn-small btn-outline' href='admin_invoices.php?order_id=<?= (int)$o['id'] ?>&supplier_id=<?= (int)$o['supplier_id'] ?>'>Add Invoice</a>
          </td>
          <td>
            <a class='btn btn-small btn-danger' href='admin_orders.php?delete_id=<?= (int)$o['id'] ?>' onclick="return confirm('Delete this order record?')">Delete</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <?php
  $stmt2 = $filterSupplier || $filterStatus
    ? (function() use ($conn, $filterSupplier, $filterStatus, $types, $params, $whereSQL) {
        $q = "SELECT o.*, s.supplier_name FROM orders o JOIN suppliers s ON s.id = o.supplier_id {$whereSQL} ORDER BY o.order_date DESC, o.created_at DESC";
        $s2 = $conn->prepare($q); $s2->bind_param($types, ...$params); $s2->execute(); return $s2->get_result();
      })()
    : $conn->query("SELECT o.*, s.supplier_name FROM orders o JOIN suppliers s ON s.id = o.supplier_id ORDER BY o.order_date DESC, o.created_at DESC");
  ?>
  <div class='mobile-cards'>
    <?php while ($o = $stmt2->fetch_assoc()): ?>
    <div class='data-card' style='background:#f7f9fc;border-radius:14px;padding:14px'>
      <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px'>
        <div>
          <div style='font-size:13px;font-weight:800'><?= esc($o['supplier_name']) ?></div>
          <div style='font-size:11px;color:#888'><?= date('d M Y', strtotime($o['order_date'])) ?></div>
        </div>
        <span class='status-pill status-<?= $o['status'] ?>'><?= ucfirst($o['status']) ?></span>
      </div>
      <div style='display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;font-size:12px'>
        <div><span style='color:#888'>Items:</span> <strong><?= (int)$o['total_items'] ?></strong></div>
        <div><span style='color:#888'>Est:</span> <strong><?= (float)$o['estimated_total']>0 ? '$'.number_format((float)$o['estimated_total'],2) : '—' ?></strong></div>
      </div>
      <div class='action-row action-row-inline'>
        <?php if ($o['pdf_filename'] && is_file(__DIR__ . '/storage/orders/' . basename($o['pdf_filename']))): ?>
          <a class='btn btn-small btn-outline' href='download_file.php?type=order&id=<?= (int)$o['id'] ?>'>PDF</a>
        <?php endif; ?>
        <a class='btn btn-small btn-outline' href='admin_invoices.php?order_id=<?= (int)$o['id'] ?>&supplier_id=<?= (int)$o['supplier_id'] ?>'>Add Invoice</a>
        <a class='btn btn-small btn-danger' href='admin_orders.php?delete_id=<?= (int)$o['id'] ?>' onclick="return confirm('Delete?')">Delete</a>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <?php else: ?>
    <div class='helper-box'>No orders found. Orders are saved automatically when admin downloads a per-supplier PDF.</div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
