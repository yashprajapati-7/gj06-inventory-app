<?php
require_once __DIR__ . '/functions.php';
require_admin();

$checkTable = $conn->query("SHOW TABLES LIKE 'invoices'");
if (!$checkTable || $checkTable->num_rows === 0) {
    die('Analytics tables not found. Run analytics_migration.sql first.');
}

$message = '';
$error   = '';
$invoicesDir = __DIR__ . '/storage/invoices/';
if (!is_dir($invoicesDir)) { @mkdir($invoicesDir, 0755, true); }

// Pre-fill from order link if coming from admin_orders
$preOrderId    = isset($_GET['order_id'])    ? (int)$_GET['order_id']    : 0;
$preSupplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Save new invoice
if (isset($_POST['save_invoice'])) {
    $supplierId    = (int)($_POST['supplier_id'] ?? 0);
    $invoiceDate   = trim($_POST['invoice_date'] ?? '');
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $totalAmount   = max(0.0, (float)($_POST['total_amount'] ?? 0));
    $orderId       = (int)($_POST['order_id'] ?? 0) ?: null;
    $notes         = trim($_POST['notes'] ?? '');
    $pdfFilename   = null;

    if ($supplierId === 0 || $invoiceDate === '') {
        $error = 'Supplier and invoice date are required.';
    } else {
        // Handle PDF upload
        if (!empty($_FILES['invoice_pdf']['name']) && $_FILES['invoice_pdf']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['invoice_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $error = 'Only PDF files are allowed.';
            } else {
                $pdfFilename = 'inv_' . time() . '_' . uniqid() . '.pdf';
                if (!move_uploaded_file($_FILES['invoice_pdf']['tmp_name'], $invoicesDir . $pdfFilename)) {
                    $error = 'File upload failed. Check server permissions.';
                    $pdfFilename = null;
                }
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare("INSERT INTO invoices (order_id, supplier_id, invoice_number, invoice_date, total_amount, pdf_filename, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdss", $orderId, $supplierId, $invoiceNumber, $invoiceDate, $totalAmount, $pdfFilename, $notes);
            if ($stmt->execute()) {
                $message = 'Invoice saved.';
                $preOrderId = 0;
                $preSupplierId = 0;
            } else {
                $error = 'Failed to save invoice.';
            }
        }
    }
}

// Delete invoice
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $fileRow  = $conn->prepare("SELECT pdf_filename FROM invoices WHERE id = ?");
    $fileRow->bind_param("i", $deleteId);
    $fileRow->execute();
    $fileData = $fileRow->get_result()->fetch_assoc();
    if ($fileData && $fileData['pdf_filename']) {
        @unlink($invoicesDir . basename($fileData['pdf_filename']));
    }
    $conn->prepare("DELETE FROM invoices WHERE id = ?")->bind_param("i", $deleteId)->execute();
    header('Location: admin_invoices.php?msg=deleted');
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') { $message = 'Invoice deleted.'; }

// Data for form
$suppliers = $conn->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
$recentOrders = $conn->query("SELECT o.id, o.order_date, s.supplier_name FROM orders o JOIN suppliers s ON s.id = o.supplier_id ORDER BY o.order_date DESC LIMIT 30");

// Invoice list
$invoices = $conn->query("SELECT i.*, s.supplier_name, o.order_date FROM invoices i JOIN suppliers s ON s.id = i.supplier_id LEFT JOIN orders o ON o.id = i.order_id ORDER BY i.invoice_date DESC, i.created_at DESC");

// Year total
$yearTotal = (float)($conn->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM invoices WHERE YEAR(invoice_date)=YEAR(CURDATE())")->fetch_assoc()['t'] ?? 0);
$monthTotal = (float)($conn->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM invoices WHERE DATE_FORMAT(invoice_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['t'] ?? 0);
$invoiceCount = (int)($conn->query("SELECT COUNT(*) AS c FROM invoices")->fetch_assoc()['c'] ?? 0);

$pageTitle = 'Invoices';
include __DIR__ . '/partials/header.php';
?>

<?php if ($message): ?><div class='pill page-message'><?= esc($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class='alert-box page-message'><?= esc($error) ?></div><?php endif; ?>

<section class='card'>
  <div class='section-head'>
    <h2>Invoices</h2>
    <p>Save and track invoices received from suppliers</p>
  </div>
  <div class='action-row'>
    <a class='btn btn-dark' href='admin_analytics.php'>Analytics</a>
    <a class='btn btn-outline' href='admin_orders.php'>Order History</a>
  </div>
</section>

<!-- Summary -->
<div class='stat-grid'>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>This Month</div>
    <div class='stat-value'>$<?= number_format($monthTotal, 2) ?></div>
    <div class='stat-sub'><?= date('F Y') ?></div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>This Year</div>
    <div class='stat-value'>$<?= number_format($yearTotal, 2) ?></div>
    <div class='stat-sub'><?= date('Y') ?></div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Total Invoices</div>
    <div class='stat-value'><?= $invoiceCount ?></div>
    <div class='stat-sub'>all time</div>
  </div>
</div>

<!-- Add Invoice Form -->
<section class='card'>
  <div class='section-head'>
    <h2>Save Invoice</h2>
    <?php if ($preOrderId > 0): ?>
      <p>Adding invoice for Order #<?= $preOrderId ?></p>
    <?php else: ?>
      <p>Record an invoice received from a supplier</p>
    <?php endif; ?>
  </div>

  <form method='post' enctype='multipart/form-data' class='form-grid'>
    <div>
      <label class='field-label'>Supplier *</label>
      <select name='supplier_id' required>
        <option value=''>Select Supplier</option>
        <?php mysqli_data_seek($suppliers, 0); while ($s = $suppliers->fetch_assoc()): ?>
          <option value='<?= (int)$s['id'] ?>' <?= (int)$s['id'] === $preSupplierId ? 'selected' : '' ?>>
            <?= esc($s['supplier_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class='field-label'>Invoice Date *</label>
      <input type='date' name='invoice_date' value='<?= date('Y-m-d') ?>' required>
    </div>

    <div>
      <label class='field-label'>Invoice Number</label>
      <input type='text' name='invoice_number' placeholder='e.g. INV-0042 (optional)'>
    </div>

    <div>
      <label class='field-label'>Total Amount ($) *</label>
      <input type='number' name='total_amount' step='0.01' min='0' placeholder='0.00' required>
    </div>

    <div>
      <label class='field-label'>Link to Order (optional)</label>
      <select name='order_id'>
        <option value='0'>— No linked order —</option>
        <?php while ($ord = $recentOrders->fetch_assoc()): ?>
          <option value='<?= (int)$ord['id'] ?>' <?= (int)$ord['id'] === $preOrderId ? 'selected' : '' ?>>
            #<?= (int)$ord['id'] ?> &mdash; <?= esc($ord['supplier_name']) ?> &mdash; <?= date('d M Y', strtotime($ord['order_date'])) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class='field-label'>Upload Invoice PDF (optional)</label>
      <input type='file' name='invoice_pdf' accept='.pdf' style='padding:10px 12px'>
    </div>

    <div class='full'>
      <label class='field-label'>Notes</label>
      <input type='text' name='notes' placeholder='Any notes (optional)'>
    </div>

    <div class='full action-row'>
      <button class='btn btn-dark' name='save_invoice'>Save Invoice</button>
      <?php if ($preOrderId): ?>
        <a class='btn btn-outline' href='admin_orders.php'>Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<!-- Invoice List -->
<section class='card'>
  <div class='section-head'>
    <h2>Invoice History</h2>
  </div>

  <?php if ($invoices && $invoices->num_rows > 0): ?>
  <div class='table-wrap' style='display:block'>
    <table class='table'>
      <thead>
        <tr>
          <th>Date</th>
          <th>Supplier</th>
          <th>Invoice #</th>
          <th>Amount</th>
          <th>Order</th>
          <th>PDF</th>
          <th>Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php while ($inv = $invoices->fetch_assoc()): ?>
        <tr>
          <td><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
          <td><?= esc($inv['supplier_name']) ?></td>
          <td><?= $inv['invoice_number'] ? esc($inv['invoice_number']) : '<span style="color:#bbb">—</span>' ?></td>
          <td><strong>$<?= number_format((float)$inv['total_amount'], 2) ?></strong></td>
          <td><?= $inv['order_id'] ? '<a href="admin_orders.php" style="text-decoration:underline">#' . (int)$inv['order_id'] . '</a>' : '<span style="color:#bbb">—</span>' ?></td>
          <td>
            <?php if ($inv['pdf_filename'] && is_file($invoicesDir . basename($inv['pdf_filename']))): ?>
              <a class='btn btn-small btn-outline' href='download_file.php?type=invoice&id=<?= (int)$inv['id'] ?>'>Download</a>
            <?php else: ?>
              <span style='color:#bbb;font-size:12px'>—</span>
            <?php endif; ?>
          </td>
          <td style='max-width:150px;font-size:11px;color:#666'><?= $inv['notes'] ? esc(mb_strimwidth($inv['notes'], 0, 60, '…')) : '' ?></td>
          <td><a class='btn btn-small btn-danger' href='admin_invoices.php?delete_id=<?= (int)$inv['id'] ?>' onclick="return confirm('Delete this invoice?')">Delete</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <?php $invRes2 = $conn->query("SELECT i.*, s.supplier_name FROM invoices i JOIN suppliers s ON s.id = i.supplier_id ORDER BY i.invoice_date DESC"); ?>
  <div class='mobile-cards'>
    <?php while ($inv = $invRes2->fetch_assoc()): ?>
    <div class='data-card' style='background:#f7f9fc;border-radius:14px;padding:14px'>
      <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px'>
        <div>
          <div style='font-size:13px;font-weight:800'><?= esc($inv['supplier_name']) ?></div>
          <div style='font-size:11px;color:#888'><?= date('d M Y', strtotime($inv['invoice_date'])) ?><?= $inv['invoice_number'] ? ' &bull; ' . esc($inv['invoice_number']) : '' ?></div>
        </div>
        <span class='pill' style='font-size:12px'>$<?= number_format((float)$inv['total_amount'], 2) ?></span>
      </div>
      <?php if ($inv['notes']): ?><div style='font-size:11px;color:#666;margin-bottom:8px'><?= esc(mb_strimwidth($inv['notes'],0,80,'…')) ?></div><?php endif; ?>
      <div class='action-row action-row-inline'>
        <?php if ($inv['pdf_filename'] && is_file($invoicesDir . basename($inv['pdf_filename']))): ?>
          <a class='btn btn-small btn-outline' href='download_file.php?type=invoice&id=<?= (int)$inv['id'] ?>'>PDF</a>
        <?php endif; ?>
        <a class='btn btn-small btn-danger' href='admin_invoices.php?delete_id=<?= (int)$inv['id'] ?>' onclick="return confirm('Delete?')">Delete</a>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <?php else: ?>
    <div class='helper-box'>No invoices recorded yet. Use the form above to save your first invoice.</div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
