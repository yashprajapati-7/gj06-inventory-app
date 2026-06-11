<?php
require_once __DIR__ . '/functions.php';
require_admin();

$checkTable = $conn->query("SHOW TABLES LIKE 'orders'");
$analyticsReady = ($checkTable && $checkTable->num_rows > 0);

if ($analyticsReady) {
    // Summary stats
    $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS yr FROM invoices WHERE YEAR(invoice_date)=YEAR(CURDATE())")->fetch_assoc();
    $totalYearSpend = (float)$r['yr'];

    $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS mo FROM invoices WHERE DATE_FORMAT(invoice_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc();
    $thisMonthSpend = (float)$r['mo'];

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE YEAR(order_date)=YEAR(CURDATE())")->fetch_assoc();
    $totalOrdersYear = (int)$r['cnt'];

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM invoices WHERE YEAR(invoice_date)=YEAR(CURDATE())")->fetch_assoc();
    $totalInvoicesYear = (int)$r['cnt'];

    // Monthly combined table — last 12 months
    $monthlyData = [];
    $ordersRes = $conn->query("SELECT DATE_FORMAT(order_date,'%Y-%m') AS ym, DATE_FORMAT(order_date,'%b %Y') AS lbl, COUNT(*) AS cnt, SUM(total_items) AS items, COALESCE(SUM(estimated_total),0) AS est FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym, lbl ORDER BY ym DESC");
    while ($row = $ordersRes->fetch_assoc()) {
        $monthlyData[$row['ym']] = ['lbl' => $row['lbl'], 'order_count' => (int)$row['cnt'], 'total_items' => (int)$row['items'], 'estimated_total' => (float)$row['est'], 'invoice_count' => 0, 'invoice_amount' => 0.0];
    }
    $invRes = $conn->query("SELECT DATE_FORMAT(invoice_date,'%Y-%m') AS ym, DATE_FORMAT(invoice_date,'%b %Y') AS lbl, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt FROM invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym, lbl ORDER BY ym DESC");
    while ($row = $invRes->fetch_assoc()) {
        if (!isset($monthlyData[$row['ym']])) {
            $monthlyData[$row['ym']] = ['lbl' => $row['lbl'], 'order_count' => 0, 'total_items' => 0, 'estimated_total' => 0.0];
        }
        $monthlyData[$row['ym']]['invoice_count']  = (int)$row['cnt'];
        $monthlyData[$row['ym']]['invoice_amount'] = (float)$row['amt'];
    }
    krsort($monthlyData);
    $maxInvAmt = max(array_column($monthlyData, 'invoice_amount') ?: [0]);

    // Supplier spending this year (from invoices)
    $supplierSpend = $conn->query("SELECT s.supplier_name, COUNT(i.id) AS inv_count, COALESCE(SUM(i.total_amount),0) AS total FROM invoices i JOIN suppliers s ON s.id = i.supplier_id WHERE YEAR(i.invoice_date)=YEAR(CURDATE()) GROUP BY i.supplier_id, s.supplier_name ORDER BY total DESC");
    $supplierRows = [];
    while ($row = $supplierSpend->fetch_assoc()) { $supplierRows[] = $row; }
    $maxSupplierAmt = max(array_column($supplierRows, 'total') ?: [0]);

    // Top 10 most ordered products
    $topProducts = $conn->query("SELECT oi.product_name, s.supplier_name, COUNT(DISTINCT oi.order_id) AS order_count, SUM(oi.quantity) AS total_qty FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN suppliers s ON s.id = o.supplier_id GROUP BY oi.product_id, oi.product_name, s.supplier_name ORDER BY total_qty DESC LIMIT 10");
    $topRows = [];
    while ($row = $topProducts->fetch_assoc()) { $topRows[] = $row; }
    $maxQty = max(array_column($topRows, 'total_qty') ?: [0]);

    // Current reorder needs with estimated cost
    $currentReorders = $conn->query("SELECT p.product_name, s.supplier_name, p.op, p.soh, p.csoh, p.unit, p.unit_price, ROUND(p.op * p.unit_price, 2) AS est_cost FROM products p JOIN suppliers s ON s.id = p.supplier_id WHERE p.op > 0 ORDER BY est_cost DESC, p.op DESC LIMIT 30");
    $reorderRows = [];
    while ($row = $currentReorders->fetch_assoc()) { $reorderRows[] = $row; }
    $totalReorderEst = (float)($conn->query("SELECT COALESCE(SUM(op * unit_price),0) AS t FROM products WHERE op > 0")->fetch_assoc()['t'] ?? 0);
}

$pageTitle = 'Analytics';
include __DIR__ . '/partials/header.php';
?>

<?php if (!$analyticsReady): ?>
<section class='card'>
  <div class='section-head'>
    <h2>Analytics</h2>
    <p>Analytics tables not found. Run the migration to enable this feature.</p>
  </div>
  <div class='helper-box'>
    <strong>Setup required:</strong> Import <code>analytics_migration.sql</code> into your database,
    then refresh this page.
  </div>
</section>

<?php else: ?>

<section class='card'>
  <div class='section-head'>
    <h2>Analytics</h2>
    <p>Spending overview &amp; order insights &mdash; <?= date('Y') ?></p>
  </div>
  <div class='action-row'>
    <a class='btn btn-dark' href='admin_orders.php'>Order History</a>
    <a class='btn btn-outline' href='admin_invoices.php'>Invoices</a>
  </div>
</section>

<!-- Summary stat boxes -->
<div class='stat-grid'>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>This Month (Invoices)</div>
    <div class='stat-value'>$<?= number_format($thisMonthSpend, 2) ?></div>
    <div class='stat-sub'><?= date('F Y') ?></div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>This Year (Invoices)</div>
    <div class='stat-value'>$<?= number_format($totalYearSpend, 2) ?></div>
    <div class='stat-sub'><?= $totalInvoicesYear ?> invoice<?= $totalInvoicesYear !== 1 ? 's' : '' ?> recorded</div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Orders This Year</div>
    <div class='stat-value'><?= $totalOrdersYear ?></div>
    <div class='stat-sub'>purchase orders sent</div>
  </div>
  <div class='stat-box stat-accent'>
    <div class='stat-label'>Est. Current Reorder</div>
    <div class='stat-value'>$<?= number_format($totalReorderEst, 2) ?></div>
    <div class='stat-sub'>based on unit prices</div>
  </div>
</div>

<!-- Monthly overview table -->
<section class='card'>
  <div class='section-head'>
    <h2>Monthly Overview</h2>
    <p>Last 12 months &mdash; orders placed and invoices received</p>
  </div>

  <?php if (empty($monthlyData)): ?>
    <div class='helper-box'>No monthly data yet. Start placing orders and recording invoices.</div>
  <?php else: ?>
  <div class='table-wrap' style='display:block'>
    <table class='table'>
      <thead>
        <tr>
          <th>Month</th>
          <th>Orders</th>
          <th>Items Ordered</th>
          <th>Invoices</th>
          <th>Invoice Amount</th>
          <th>Spend Bar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($monthlyData as $ym => $d): ?>
        <tr>
          <td><span class='month-badge'><?= esc($d['lbl']) ?></span></td>
          <td><?= (int)$d['order_count'] ?></td>
          <td><?= (int)$d['total_items'] ?></td>
          <td><?= (int)$d['invoice_count'] ?></td>
          <td><strong>$<?= number_format((float)$d['invoice_amount'], 2) ?></strong></td>
          <td style='min-width:120px'>
            <div class='bar-wrap'>
              <div class='bar-fill' style='width:<?= $maxInvAmt > 0 ? round((float)$d['invoice_amount'] / $maxInvAmt * 100) : 0 ?>%'></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards for monthly data -->
  <div class='mobile-cards'>
    <?php foreach ($monthlyData as $ym => $d): ?>
    <div class='data-card' style='background:#f7f9fc;border-radius:16px;padding:14px'>
      <div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:10px'>
        <span class='month-badge'><?= esc($d['lbl']) ?></span>
        <strong>$<?= number_format((float)$d['invoice_amount'], 2) ?></strong>
      </div>
      <div style='display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;font-size:12px;text-align:center'>
        <div><div style='color:#888;font-size:10px'>Orders</div><strong><?= (int)$d['order_count'] ?></strong></div>
        <div><div style='color:#888;font-size:10px'>Items</div><strong><?= (int)$d['total_items'] ?></strong></div>
        <div><div style='color:#888;font-size:10px'>Invoices</div><strong><?= (int)$d['invoice_count'] ?></strong></div>
      </div>
      <div class='bar-wrap' style='margin-top:10px'>
        <div class='bar-fill' style='width:<?= $maxInvAmt > 0 ? round((float)$d['invoice_amount'] / $maxInvAmt * 100) : 0 ?>%'></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- Supplier spending this year -->
<section class='card'>
  <div class='section-head'>
    <h2>Spending by Supplier</h2>
    <p>Invoice totals per supplier &mdash; <?= date('Y') ?></p>
  </div>

  <?php if (empty($supplierRows)): ?>
    <div class='helper-box'>No invoices recorded yet. Add invoices via the Invoices page.</div>
  <?php else: ?>
    <?php foreach ($supplierRows as $sr): ?>
    <div class='bar-row'>
      <div class='bar-label'><?= esc($sr['supplier_name']) ?></div>
      <div class='bar-wrap'>
        <div class='bar-fill' style='width:<?= $maxSupplierAmt > 0 ? round((float)$sr['total'] / $maxSupplierAmt * 100) : 0 ?>%'></div>
      </div>
      <div class='bar-val'>$<?= number_format((float)$sr['total'], 0) ?></div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- Top ordered products -->
<section class='card'>
  <div class='section-head'>
    <h2>Top Ordered Products</h2>
    <p>All time &mdash; by total quantity ordered</p>
  </div>

  <?php if (empty($topRows)): ?>
    <div class='helper-box'>No order history yet. Products will appear here once orders are placed.</div>
  <?php else: ?>
  <div class='table-wrap' style='display:block'>
    <table class='table'>
      <thead>
        <tr><th>#</th><th>Product</th><th>Supplier</th><th>Times Ordered</th><th>Total Qty</th><th>Qty Bar</th></tr>
      </thead>
      <tbody>
        <?php foreach ($topRows as $i => $p): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= esc($p['product_name']) ?></td>
          <td><?= esc($p['supplier_name']) ?></td>
          <td><?= (int)$p['order_count'] ?></td>
          <td><strong><?= (int)$p['total_qty'] ?></strong></td>
          <td style='min-width:100px'>
            <div class='bar-wrap'>
              <div class='bar-fill' style='width:<?= $maxQty > 0 ? round((int)$p['total_qty'] / $maxQty * 100) : 0 ?>%'></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile -->
  <div class='mobile-cards'>
    <?php foreach ($topRows as $i => $p): ?>
    <div class='data-card' style='background:#f7f9fc;border-radius:14px;padding:12px;display:flex;align-items:center;gap:12px'>
      <div style='width:28px;height:28px;border-radius:50%;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0'><?= $i+1 ?></div>
      <div style='flex:1;min-width:0'>
        <div style='font-size:12px;font-weight:800'><?= esc($p['product_name']) ?></div>
        <div style='font-size:10px;color:#888'><?= esc($p['supplier_name']) ?></div>
        <div class='bar-wrap' style='margin-top:5px'>
          <div class='bar-fill' style='width:<?= $maxQty > 0 ? round((int)$p['total_qty'] / $maxQty * 100) : 0 ?>%'></div>
        </div>
      </div>
      <div style='text-align:right;flex-shrink:0'>
        <div style='font-size:16px;font-weight:800'><?= (int)$p['total_qty'] ?></div>
        <div style='font-size:10px;color:#888'><?= (int)$p['order_count'] ?> orders</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- Current reorder needs -->
<section class='card'>
  <div class='section-head'>
    <h2>Current Reorder Needs</h2>
    <p>Products with PO &gt; 0 &mdash; sorted by estimated cost</p>
  </div>
  <div class='action-row' style='margin-bottom:14px'>
    <div class='pill'>Est. Total: $<?= number_format($totalReorderEst, 2) ?></div>
    <a class='btn btn-outline' href='reorder.php'>View Reorder List</a>
  </div>

  <?php if (empty($reorderRows)): ?>
    <div class='helper-box'>No products currently need reordering.</div>
  <?php else: ?>
  <div class='table-wrap' style='display:block'>
    <table class='table'>
      <thead>
        <tr><th>Supplier</th><th>Product</th><th>Unit</th><th>PO</th><th>SOH</th><th>MOS</th><th>Unit Price</th><th>Est. Cost</th></tr>
      </thead>
      <tbody>
        <?php foreach ($reorderRows as $r): ?>
        <tr>
          <td><?= esc($r['supplier_name']) ?></td>
          <td><?= esc($r['product_name']) ?></td>
          <td><?= esc($r['unit']) ?></td>
          <td><strong><?= (int)$r['op'] ?></strong></td>
          <td><?= (int)$r['soh'] ?></td>
          <td><?= (int)$r['csoh'] ?></td>
          <td><?= (float)$r['unit_price'] > 0 ? '$' . number_format((float)$r['unit_price'], 2) : '<span style="color:#bbb">—</span>' ?></td>
          <td><?= (float)$r['est_cost'] > 0 ? '<strong>$' . number_format((float)$r['est_cost'], 2) . '</strong>' : '<span style="color:#bbb">—</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan='7' style='text-align:right'>Estimated Total</th>
          <th>$<?= number_format($totalReorderEst, 2) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Mobile reorder cards -->
  <div class='mobile-cards'>
    <?php foreach ($reorderRows as $r): ?>
    <div class='data-card' style='background:#f7f9fc;border-radius:14px;padding:12px'>
      <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px'>
        <div>
          <div style='font-size:12px;font-weight:800'><?= esc($r['product_name']) ?></div>
          <div style='font-size:10px;color:#888'><?= esc($r['supplier_name']) ?></div>
        </div>
        <?php if ((float)$r['est_cost'] > 0): ?>
          <span class='pill' style='font-size:11px'>$<?= number_format((float)$r['est_cost'], 2) ?></span>
        <?php endif; ?>
      </div>
      <div style='display:grid;grid-template-columns:repeat(4,1fr);gap:5px;font-size:11px;text-align:center'>
        <div><div style='color:#888;font-size:9px'>PO</div><strong><?= (int)$r['op'] ?></strong></div>
        <div><div style='color:#888;font-size:9px'>SOH</div><?= (int)$r['soh'] ?></div>
        <div><div style='color:#888;font-size:9px'>MOS</div><?= (int)$r['csoh'] ?></div>
        <div><div style='color:#888;font-size:9px'>Price</div><?= (float)$r['unit_price'] > 0 ? '$'.number_format((float)$r['unit_price'],2) : '—' ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
