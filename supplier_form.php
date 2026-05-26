<?php
require_once __DIR__ . '/functions.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$supplier = ['supplier_name' => ''];
$error = '';

if ($editing) {
    $stmt = $conn->prepare('SELECT * FROM suppliers WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        header('Location: supplier_select.php');
        exit;
    }
    $supplier = $result->fetch_assoc();
}

if (isset($_POST['save'])) {
    $supplierName = trim($_POST['supplier_name'] ?? '');

    if ($supplierName === '') {
        $error = 'Supplier name is required.';
    } else {
        if ($editing) {
            $stmt = $conn->prepare('UPDATE suppliers SET supplier_name = ? WHERE id = ?');
            $stmt->bind_param('si', $supplierName, $id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare('INSERT INTO suppliers(supplier_name) VALUES (?)');
            $stmt->bind_param('s', $supplierName);
            $stmt->execute();
        }

        header('Location: supplier_select.php');
        exit;
    }
}

$pageTitle = $editing ? 'Edit Supplier' : 'Add Supplier';
include __DIR__ . '/partials/header.php';
?>

<section class='card'>
  <div class='section-head'>
    <h2><?= $editing ? 'Edit Supplier' : 'Add Supplier' ?></h2>
    <p><?= $editing ? 'Update this supplier name.' : 'Create a new supplier.' ?></p>
  </div>

  <?php if ($error !== ''): ?>
    <div class='alert-box page-message'><?= esc($error) ?></div>
  <?php endif; ?>

  <form method='post' class='form-grid single-column'>
    <div>
      <label class='field-label'>Supplier Name</label>
      <input type='text' name='supplier_name' value='<?= esc($supplier['supplier_name']) ?>' placeholder='Supplier name' required>
    </div>
    <div class='action-row'>
      <button class='btn btn-dark' name='save'>Save Supplier</button>
      <a class='btn btn-outline' href='supplier_select.php'>Back</a>
    </div>
  </form>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
