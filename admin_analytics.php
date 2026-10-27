<?php
require_once __DIR__ . '/functions.php';
require_admin();

function an_rows($conn, $sql) {
    $rows = [];
    $res = $conn->query($sql);
    while ($res && ($row = $res->fetch_assoc())) { $rows[] = $row; }
    return $rows;
}
function an_pct($part, $total) { return $total > 0 ? round($part / $total * 100) : 0; }

// Stock health: out = SOH 0, low = needs reorder (PO > 0) but still has stock, healthy = no PO needed
$stock = $conn->query("SELECT COUNT(*) AS total,
    SUM(soh = 0) AS out_cnt,
    SUM(soh > 0 AND op > 0) AS low_cnt,
    SUM(soh > 0 AND op = 0) AS ok_cnt,
    COALESCE(SUM(op), 0) AS po_qty
    FROM products")->fetch_assoc();
$totalProducts = (int)$stock['total'];
$outCount      = (int)$stock['out_cnt'];
$lowCount      = (int)$stock['low_cnt'];
$okCount       = (int)$stock['ok_cnt'];
$poQty         = (int)$stock['po_qty'];
$healthPct     = an_pct($okCount, $totalProducts);
$supplierTotal = (int)($conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'] ?? 0);

// Per-supplier health
$supplierRows = an_rows($conn, "SELECT s.id, s.supplier_name,
    COUNT(p.id) AS total,
    COALESCE(SUM(p.soh = 0), 0) AS out_cnt,
    COALESCE(SUM(p.soh > 0 AND p.op > 0), 0) AS low_cnt,
    COALESCE(SUM(p.soh > 0 AND p.op = 0), 0) AS ok_cnt,
    COALESCE(SUM(p.op), 0) AS po_qty
    FROM suppliers s LEFT JOIN products p ON p.supplier_id = s.id
    GROUP BY s.id, s.supplier_name
    ORDER BY (COALESCE(SUM(p.soh = 0), 0) + COALESCE(SUM(p.soh > 0 AND p.op > 0), 0)) DESC, s.supplier_name ASC");

// Products needing attention (out of stock or below MOS), lowest coverage first
$attentionRows = an_rows($conn, "SELECT p.product_name, p.unit, p.soh, p.csoh, p.op, s.supplier_name
    FROM products p JOIN suppliers s ON s.id = p.supplier_id
    WHERE p.op > 0 OR p.soh = 0
    ORDER BY (p.soh = 0) DESC, (p.soh / NULLIF(p.csoh, 0)) ASC, p.product_name ASC
    LIMIT 200");

// Products by unit
$unitRows = an_rows($conn, "SELECT unit, COUNT(*) AS cnt FROM products GROUP BY unit ORDER BY cnt DESC, unit ASC");
$maxUnit = max(array_column($unitRows, 'cnt') ?: [0]);

// Orders
$ordersOn = ($conn->query("SHOW TABLES LIKE 'orders'")->num_rows ?? 0) > 0;
$ordersMonth = $ordersYear = 0;
$lastOrder = null;
$monthly = [];
$topProducts = $topSuppliers = $recentOrders = [];
if ($ordersOn) {
    $ordersMonth = (int)$conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE_FORMAT(order_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['c'];
    $ordersYear  = (int)$conn->query("SELECT COUNT(*) AS c FROM orders WHERE YEAR(order_date) = YEAR(CURDATE())")->fetch_assoc()['c'];
    $lastOrder   = $conn->query("SELECT MAX(order_date) AS d FROM orders")->fetch_assoc()['d'] ?? null;

    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i month"));
        $monthly[$ym] = ['lbl' => date('M', strtotime($ym . '-01')), 'full' => date('F Y', strtotime($ym . '-01')), 'orders' => 0, 'items' => 0];
    }
    foreach (an_rows($conn, "SELECT DATE_FORMAT(order_date,'%Y-%m') AS ym, COUNT(*) AS cnt, COALESCE(SUM(total_items),0) AS items FROM orders WHERE order_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01') GROUP BY ym") as $row) {
        if (isset($monthly[$row['ym']])) {
            $monthly[$row['ym']]['orders'] = (int)$row['cnt'];
            $monthly[$row['ym']]['items']  = (int)$row['items'];
        }
    }

    $topProducts = an_rows($conn, "SELECT oi.product_name, s.supplier_name, COUNT(DISTINCT oi.order_id) AS times, SUM(oi.quantity) AS qty, MAX(oi.unit) AS unit
        FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN suppliers s ON s.id = o.supplier_id
        GROUP BY oi.product_name, s.supplier_name ORDER BY qty DESC LIMIT 10");
    $topSuppliers = an_rows($conn, "SELECT s.supplier_name, COUNT(o.id) AS cnt, MAX(o.order_date) AS last_date
        FROM orders o JOIN suppliers s ON s.id = o.supplier_id GROUP BY s.id, s.supplier_name ORDER BY cnt DESC LIMIT 8");
    $recentOrders = an_rows($conn, "SELECT o.id, o.order_date, o.total_items, o.status, s.supplier_name
        FROM orders o JOIN suppliers s ON s.id = o.supplier_id ORDER BY o.order_date DESC, o.id DESC LIMIT 6");
}
$maxMonth    = max(array_column($monthly, 'orders') ?: [0]);
$maxTopQty   = max(array_column($topProducts, 'qty') ?: [0]);
$maxSupOrder = max(array_column($topSuppliers, 'cnt') ?: [0]);

// Donut geometry (circumference of r=15.915 circle = 100)
$donut = [
    ['key' => 'ok',  'label' => 'Healthy',      'cnt' => $okCount,  'color' => '#0ca30c', 'icon' => '✓'],
    ['key' => 'low', 'label' => 'Low stock',    'cnt' => $lowCount, 'color' => '#fab219', 'icon' => '!'],
    ['key' => 'out', 'label' => 'Out of stock', 'cnt' => $outCount, 'color' => '#d03b3b', 'icon' => '✕'],
];

$pageTitle = 'Analytics';
include __DIR__ . '/partials/header.php';
?>

<section class='card an-hero'>
  <div>
    <h2 class='an-title'>Analytics</h2>
    <p class='an-subtitle'>Stock health &amp; ordering overview &middot; updated <?= date('j M Y, g:i a') ?></p>
  </div>
  <div class='action-row'>
    <a class='btn btn-dark' href='reorder.php'>Reorder List</a>
    <a class='btn btn-outline' href='admin_orders.php'>Order History</a>
  </div>
</section>

<!-- KPI tiles -->
<div class='an-kpis'>
  <div class='an-kpi'>
    <div class='an-kpi-label'>Total Products</div>
    <div class='an-kpi-value'><?= $totalProducts ?></div>
    <div class='an-kpi-sub'>across <?= $supplierTotal ?> suppliers</div>
  </div>
  <div class='an-kpi'>
    <div class='an-kpi-label'>Stock Health</div>
    <div class='an-kpi-value'><?= $healthPct ?>%</div>
    <div class='an-kpi-sub'><?= $okCount ?> products fully stocked</div>
  </div>
  <div class='an-kpi'>
    <div class='an-kpi-label'><span class='an-dot' style='background:#fab219'></span>Need Reorder</div>
    <div class='an-kpi-value'><?= $lowCount + $outCount ?></div>
    <div class='an-kpi-sub'><?= $poQty ?> units on PO in total</div>
  </div>
  <div class='an-kpi'>
    <div class='an-kpi-label'><span class='an-dot' style='background:#d03b3b'></span>Out of Stock</div>
    <div class='an-kpi-value'><?= $outCount ?></div>
    <div class='an-kpi-sub'>SOH is zero</div>
  </div>
  <div class='an-kpi'>
    <div class='an-kpi-label'>Orders This Month</div>
    <div class='an-kpi-value'><?= $ordersMonth ?></div>
    <div class='an-kpi-sub'><?= $ordersYear ?> so far in <?= date('Y') ?></div>
  </div>
  <div class='an-kpi'>
    <div class='an-kpi-label'>Last Order</div>
    <div class='an-kpi-value an-kpi-value-sm'><?= $lastOrder ? esc(date('j M', strtotime($lastOrder))) : '—' ?></div>
    <div class='an-kpi-sub'><?= $lastOrder ? esc(floor((time() - strtotime($lastOrder)) / 86400)) . ' days ago' : 'no orders yet' ?></div>
  </div>
</div>

<div class='an-grid'>
  <!-- Stock health donut -->
  <section class='card an-card'>
    <div class='section-head'>
      <h2>Stock Health</h2>
      <p>Every product grouped by its current stock level</p>
    </div>
    <?php if ($totalProducts === 0): ?>
      <div class='helper-box'>No products yet.</div>
    <?php else: ?>
    <div class='an-donut-wrap'>
      <svg viewBox='0 0 42 42' class='an-donut' role='img' aria-label='Stock health: <?= $okCount ?> healthy, <?= $lowCount ?> low, <?= $outCount ?> out of stock'>
        <circle cx='21' cy='21' r='15.915' fill='none' stroke='#edf2f8' stroke-width='6'></circle>
        <?php $offset = 25; foreach ($donut as $seg): $len = $totalProducts ? $seg['cnt'] / $totalProducts * 100 : 0; if ($len <= 0) continue; $gap = $len >= 100 ? 0 : 0.8; ?>
          <circle class='an-tip' data-tip='<?= esc($seg['label']) ?>: <?= $seg['cnt'] ?> (<?= an_pct($seg['cnt'], $totalProducts) ?>%)'
            cx='21' cy='21' r='15.915' fill='none' stroke='<?= $seg['color'] ?>' stroke-width='6'
            stroke-dasharray='<?= max($len - $gap, 0.1) ?> <?= 100 - max($len - $gap, 0.1) ?>' stroke-dashoffset='<?= $offset ?>'></circle>
        <?php $offset -= $len; endforeach; ?>
        <text x='21' y='20.5' text-anchor='middle' class='an-donut-num'><?= $healthPct ?>%</text>
        <text x='21' y='26' text-anchor='middle' class='an-donut-lbl'>healthy</text>
      </svg>
      <div class='an-legend'>
        <?php foreach ($donut as $seg): ?>
          <div class='an-legend-row'>
            <span class='an-badge' style='background:<?= $seg['color'] ?>;color:<?= $seg['key'] === 'low' ? '#0b0b0b' : '#fff' ?>'><?= $seg['icon'] ?></span>
            <span class='an-legend-name'><?= esc($seg['label']) ?></span>
            <strong><?= $seg['cnt'] ?></strong>
            <span class='an-muted'><?= an_pct($seg['cnt'], $totalProducts) ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- Orders trend -->
  <section class='card an-card'>
    <div class='section-head'>
      <h2>Orders per Month</h2>
      <p>Purchase orders sent in the last 12 months</p>
    </div>
    <?php if (!$ordersOn || $maxMonth === 0): ?>
      <div class='helper-box'>No orders yet. Orders appear here once a PO is downloaded.</div>
    <?php else: ?>
    <div class='an-cols'>
      <?php foreach ($monthly as $ym => $m): ?>
        <div class='an-col an-tip' data-tip='<?= esc($m['full']) ?>: <?= $m['orders'] ?> orders, <?= $m['items'] ?> items'>
          <div class='an-col-val'><?= $m['orders'] > 0 && $m['orders'] === $maxMonth ? $m['orders'] : '' ?></div>
          <div class='an-col-track'>
            <div class='an-col-bar <?= $ym === date('Y-m') ? 'an-col-now' : '' ?>' style='height:<?= an_pct($m['orders'], $maxMonth) ?>%'></div>
          </div>
          <div class='an-col-lbl'><?= esc($m['lbl']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>

<!-- Supplier health -->
<section class='card'>
  <div class='section-head'>
    <h2>Supplier Stock Status</h2>
    <p>Suppliers with the most items needing attention are listed first</p>
  </div>
  <div class='an-legend an-legend-inline'>
    <?php foreach ($donut as $seg): ?>
      <span class='an-legend-row'><span class='an-badge' style='background:<?= $seg['color'] ?>;color:<?= $seg['key'] === 'low' ? '#0b0b0b' : '#fff' ?>'><?= $seg['icon'] ?></span><?= esc($seg['label']) ?></span>
    <?php endforeach; ?>
  </div>
  <div class='an-suppliers'>
    <?php foreach ($supplierRows as $s): $t = (int)$s['total']; ?>
      <a class='an-supplier' href='products.php?supplier_id=<?= (int)$s['id'] ?>'>
        <div class='an-supplier-head'>
          <strong><?= esc($s['supplier_name']) ?></strong>
          <span class='an-muted'><?= $t ?> product<?= $t === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($t === 0): ?>
          <div class='an-stack'><div class='an-stack-empty'></div></div>
        <?php else: ?>
          <div class='an-stack'>
            <?php foreach ($donut as $seg): $c = (int)$s[$seg['key'] . '_cnt']; if ($c === 0) continue; ?>
              <div class='an-tip' data-tip='<?= esc($seg['label']) ?>: <?= $c ?>' style='flex:<?= $c ?>;background:<?= $seg['color'] ?>'></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class='an-supplier-foot'>
          <span><?= (int)$s['out_cnt'] ?> out</span>
          <span><?= (int)$s['low_cnt'] ?> low</span>
          <span><?= (int)$s['ok_cnt'] ?> healthy</span>
          <span class='an-po'>PO <?= (int)$s['po_qty'] ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- Needs attention -->
<section class='card'>
  <div class='section-head'>
    <h2>Needs Attention</h2>
    <p>Out of stock and low stock products, most urgent first</p>
  </div>
  <?php if (empty($attentionRows)): ?>
    <div class='helper-box'>Everything is stocked above MOS. Nice work!</div>
  <?php else: ?>
  <div class='an-filters'>
    <input type='text' id='anSearch' placeholder='Search product'>
    <select id='anSupplier'>
      <option value=''>All suppliers</option>
      <?php foreach ($supplierRows as $s): if ((int)$s['out_cnt'] + (int)$s['low_cnt'] === 0) continue; ?>
        <option value='<?= esc($s['supplier_name']) ?>'><?= esc($s['supplier_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id='anStatus'>
      <option value=''>All statuses</option>
      <option value='out'>Out of stock</option>
      <option value='low'>Low stock</option>
    </select>
  </div>
  <div class='an-attn' id='anAttn'>
    <?php foreach ($attentionRows as $r):
      $soh = (int)$r['soh']; $mos = (int)$r['csoh'];
      $isOut = $soh === 0;
      $cov = $mos > 0 ? min(an_pct($soh, $mos), 100) : ($soh > 0 ? 100 : 0); ?>
      <div class='an-attn-row' data-name='<?= esc(strtolower($r['product_name'])) ?>' data-supplier='<?= esc($r['supplier_name']) ?>' data-status='<?= $isOut ? 'out' : 'low' ?>'>
        <span class='an-badge' style='background:<?= $isOut ? '#d03b3b' : '#fab219' ?>;color:<?= $isOut ? '#fff' : '#0b0b0b' ?>' title='<?= $isOut ? 'Out of stock' : 'Low stock' ?>'><?= $isOut ? '✕' : '!' ?></span>
        <div class='an-attn-main'>
          <div class='an-attn-name'><?= esc($r['product_name']) ?></div>
          <div class='an-muted'><?= esc($r['supplier_name']) ?> &middot; <?= $isOut ? 'Out of stock' : 'Low stock' ?></div>
          <div class='bar-wrap an-tip' data-tip='SOH <?= $soh ?> of MOS <?= $mos ?> (<?= $cov ?>%)' style='margin-top:6px;height:8px'>
            <div class='bar-fill' style='width:<?= $cov ?>%;background:<?= $isOut ? '#d03b3b' : '#fab219' ?>'></div>
          </div>
        </div>
        <div class='an-attn-nums'>
          <div><span>SOH</span><strong><?= $soh ?></strong></div>
          <div><span>MOS</span><strong><?= $mos ?></strong></div>
          <div><span>PO</span><strong><?= (int)$r['op'] ?> <small><?= esc($r['unit']) ?></small></strong></div>
        </div>
      </div>
    <?php endforeach; ?>
    <div class='helper-box' id='anNoMatch' style='display:none'>No products match your filter.</div>
  </div>
  <?php endif; ?>
</section>

<div class='an-grid'>
  <!-- Top ordered products -->
  <section class='card an-card'>
    <div class='section-head'>
      <h2>Most Ordered Products</h2>
      <p>All time, by total quantity ordered</p>
    </div>
    <?php if (empty($topProducts)): ?>
      <div class='helper-box'>No order history yet.</div>
    <?php else: ?>
      <?php foreach ($topProducts as $i => $p): ?>
        <div class='an-rank an-tip' data-tip='Ordered <?= (int)$p['times'] ?> times, <?= (int)$p['qty'] ?> <?= esc($p['unit']) ?> total'>
          <div class='an-rank-num'><?= $i + 1 ?></div>
          <div class='an-rank-main'>
            <div class='an-rank-name'><?= esc($p['product_name']) ?> <span class='an-muted'>&middot; <?= esc($p['supplier_name']) ?></span></div>
            <div class='bar-wrap' style='height:8px'><div class='bar-fill' style='width:<?= an_pct((int)$p['qty'], $maxTopQty) ?>%'></div></div>
          </div>
          <div class='an-rank-val'><?= (int)$p['qty'] ?> <small><?= esc($p['unit']) ?></small></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- Orders by supplier -->
  <section class='card an-card'>
    <div class='section-head'>
      <h2>Orders by Supplier</h2>
      <p>All time, number of purchase orders</p>
    </div>
    <?php if (empty($topSuppliers)): ?>
      <div class='helper-box'>No order history yet.</div>
    <?php else: ?>
      <?php foreach ($topSuppliers as $s): ?>
        <div class='bar-row an-tip' data-tip='Last order <?= esc(date('j M Y', strtotime($s['last_date']))) ?>'>
          <div class='bar-label'><?= esc($s['supplier_name']) ?></div>
          <div class='bar-wrap'><div class='bar-fill' style='width:<?= an_pct((int)$s['cnt'], $maxSupOrder) ?>%'></div></div>
          <div class='bar-val'><?= (int)$s['cnt'] ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<div class='an-grid'>
  <!-- Recent orders -->
  <section class='card an-card'>
    <div class='section-head'>
      <h2>Recent Orders</h2>
      <p>Latest purchase orders sent</p>
    </div>
    <?php if (empty($recentOrders)): ?>
      <div class='helper-box'>No orders yet.</div>
    <?php else: ?>
      <?php foreach ($recentOrders as $o): ?>
        <div class='an-order'>
          <span class='month-badge'><?= esc(date('j M', strtotime($o['order_date']))) ?></span>
          <div class='an-order-main'><strong><?= esc($o['supplier_name']) ?></strong><div class='an-muted'><?= (int)$o['total_items'] ?> items</div></div>
          <span class='an-status an-status-<?= esc($o['status']) ?>'><?= esc(ucfirst($o['status'])) ?></span>
        </div>
      <?php endforeach; ?>
      <div class='action-row' style='margin-top:12px'><a class='btn btn-outline btn-small' href='admin_orders.php'>View all orders</a></div>
    <?php endif; ?>
  </section>

  <!-- Products by unit -->
  <section class='card an-card'>
    <div class='section-head'>
      <h2>Products by Unit</h2>
      <p>How your products are measured</p>
    </div>
    <?php if (empty($unitRows)): ?>
      <div class='helper-box'>No products yet.</div>
    <?php else: ?>
      <?php foreach ($unitRows as $u): ?>
        <div class='bar-row'>
          <div class='bar-label'><?= esc($u['unit']) ?></div>
          <div class='bar-wrap'><div class='bar-fill' style='width:<?= an_pct((int)$u['cnt'], $maxUnit) ?>%'></div></div>
          <div class='bar-val'><?= (int)$u['cnt'] ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<div class='an-tooltip' id='anTooltip'></div>

<script>
(function () {
  const tip = document.getElementById('anTooltip');
  function show(e) {
    const el = e.target.closest('.an-tip');
    if (!el) return;
    tip.textContent = el.getAttribute('data-tip');
    tip.style.display = 'block';
    const p = e.touches ? e.touches[0] : e;
    const x = Math.min(p.clientX + 12, window.innerWidth - tip.offsetWidth - 8);
    tip.style.left = Math.max(x, 8) + 'px';
    tip.style.top = (p.clientY - tip.offsetHeight - 12) + 'px';
  }
  document.addEventListener('mousemove', function (e) { e.target.closest('.an-tip') ? show(e) : (tip.style.display = 'none'); });
  document.addEventListener('touchstart', function (e) { e.target.closest('.an-tip') ? show(e) : (tip.style.display = 'none'); }, { passive: true });

  const search = document.getElementById('anSearch');
  const sup = document.getElementById('anSupplier');
  const status = document.getElementById('anStatus');
  if (!search) return;
  function filter() {
    const q = search.value.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('#anAttn .an-attn-row').forEach(function (row) {
      const ok = (!q || row.dataset.name.includes(q))
        && (!sup.value || row.dataset.supplier === sup.value)
        && (!status.value || row.dataset.status === status.value);
      row.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    document.getElementById('anNoMatch').style.display = shown ? 'none' : 'block';
  }
  [search, sup, status].forEach(function (el) { el.addEventListener('input', filter); });
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
