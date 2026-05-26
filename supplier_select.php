<?php
require_once __DIR__ . '/functions.php';
require_login();
if (!is_admin()) {
    header('Location: products.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$suppliers = get_user_suppliers($conn, $userId, $role);

$pageTitle = 'Suppliers';
include __DIR__ . '/partials/header.php';
?>

<section class='card'>
  <div class='section-head'>
    <h2>Suppliers</h2>
    <p>Select one supplier and open its stock page.</p>
  </div>

  <div class='action-row' style='margin-bottom:16px;'>
    <a href='supplier_form.php' class='btn btn-dark'>Add Supplier</a>
    <button type='button' class='btn btn-outline' onclick='editSelectedSupplier()'>Edit Supplier</button>
  </div>

  <form action='products.php' method='get' class='form-grid single-column'>
    <div>
      <label class='field-label'>Supplier</label>
      <select name='supplier_id' id='supplierSelect' required>
        <option value=''>Select Supplier</option>
        <?php while ($s = $suppliers->fetch_assoc()): ?>
          <option value='<?= (int)$s['id'] ?>'><?= esc($s['supplier_name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <button class='btn btn-dark'>Open Supplier</button>
  </form>
</section>

<section class='card'>
  <div class='section-head'>
    <h2>Quick Open</h2>
    <p>Tap any supplier card below.</p>
  </div>

  <div class='supplier-grid'>
    <?php $cardSuppliers = get_user_suppliers($conn, $userId, $role); ?>
    <?php while ($item = $cardSuppliers->fetch_assoc()): ?>
      <a class='supplier-card' href='products.php?supplier_id=<?= (int)$item['id'] ?>'>
        <div>
          <div class='supplier-name'><?= esc($item['supplier_name']) ?></div>
          <div class='supplier-meta'>Open stock page</div>
        </div>
      </a>
    <?php endwhile; ?>
  </div>
</section>

<script>
function editSelectedSupplier() {
  const select = document.getElementById('supplierSelect');
  if (!select || !select.value) {
    alert('Please select a supplier first.');
    return;
  }
  window.location.href = 'supplier_form.php?id=' + encodeURIComponent(select.value);
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
