<?php
require_once __DIR__ . '/functions.php';
require_login();

$allowedUnits = ['ctn', 'kg', 'pcs'];
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$search = trim($_GET['search'] ?? '');
$unitFilter = trim($_GET['unit'] ?? '');
if (!in_array($unitFilter, $allowedUnits, true)) {
    $unitFilter = '';
}
$message = '';
$error = '';

if (!$supplierId) {
    $suppliers = get_user_suppliers($conn, $userId, $role);
    if ($suppliers && $suppliers->num_rows > 0) {
        $supplierId = (int)$suppliers->fetch_assoc()['id'];
    }
}

if ($supplierId && !user_can_access_supplier($conn, $userId, $role, $supplierId)) {
    die('Access denied');
}

if (isset($_POST['update_soh'])) {
    $id = (int)($_POST['id'] ?? 0);
    $soh = max((int)($_POST['soh'] ?? 0), 0);

    if (!user_can_touch_product($conn, $userId, $role, $id)) {
        die('Access denied');
    }

    $stmt = $conn->prepare('SELECT supplier_id, csoh FROM products WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $newSupplierId = (int)$row['supplier_id'];
        $mos = (int)$row['csoh'];
        $po = max($mos - $soh, 0);

        $update = $conn->prepare('UPDATE products SET soh=?, op=? WHERE id=?');
        $update->bind_param('iii', $soh, $po, $id);

        if ($update->execute()) {
            $redirectParams = ['supplier_id=' . $newSupplierId];
            if ($search !== '') {
                $redirectParams[] = 'search=' . urlencode($search);
            }
            if ($unitFilter !== '') {
                $redirectParams[] = 'unit=' . urlencode($unitFilter);
            }
            $redirectParams[] = 'msg=soh_updated';
            header('Location: products.php?' . implode('&', $redirectParams));
            exit;
        } else {
            $error = 'SOH update failed.';
        }
    }
}

if (isset($_POST['save_product']) && !is_admin()) die('Access denied');
if (isset($_GET['delete_id']) && !is_admin()) die('Access denied');
if (isset($_GET['edit_id']) && !is_admin()) die('Access denied');

if (isset($_POST['save_product']) && is_admin()) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $supplierPost = (int)($_POST['supplier_id'] ?? 0);
    $productName = trim($_POST['product_name'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $soh = max((int)($_POST['soh'] ?? 0), 0);
    $mos = max((int)($_POST['csoh'] ?? 0), 0);

    if ($productName === '') {
        $error = 'Product name is required.';
    } elseif (!in_array($unit, $allowedUnits, true)) {
        $error = 'Please select a valid unit.';
    } elseif (!user_can_access_supplier($conn, $userId, $role, $supplierPost)) {
        $error = 'Invalid supplier selected.';
    } else {
        $po = max($mos - $soh, 0);

        if ($editId > 0) {
            $stmt = $conn->prepare('UPDATE products SET supplier_id=?, product_name=?, unit=?, soh=?, csoh=?, op=? WHERE id=?');
            $stmt->bind_param('issiiii', $supplierPost, $productName, $unit, $soh, $mos, $po, $editId);
        } else {
            $stmt = $conn->prepare('INSERT INTO products(supplier_id, product_name, unit, soh, csoh, op) VALUES(?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issiii', $supplierPost, $productName, $unit, $soh, $mos, $po);
        }

        if ($stmt->execute()) {
            header('Location: products.php?supplier_id=' . $supplierPost . '&msg=saved');
            exit;
        } else {
            $error = 'Save failed. Check your database import and try again.';
        }
    }
}

if (isset($_GET['delete_id']) && is_admin()) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare('SELECT supplier_id FROM products WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $deleteId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $deleteProduct = $result->fetch_assoc();
        $redirectSupplierId = (int)$deleteProduct['supplier_id'];
        $deleteStmt = $conn->prepare('DELETE FROM products WHERE id=?');
        $deleteStmt->bind_param('i', $deleteId);
        $deleteStmt->execute();
        header('Location: products.php?supplier_id=' . $redirectSupplierId . '&msg=deleted');
        exit;
    }
    $error = 'Product not found.';
}

$editProduct = null;
if (isset($_GET['edit_id']) && is_admin()) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $conn->prepare('SELECT * FROM products WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editProduct = $result->fetch_assoc();
        $supplierId = (int)$editProduct['supplier_id'];
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $message = 'Product saved.';
    if ($_GET['msg'] === 'deleted') $message = 'Product deleted.';
    if ($_GET['msg'] === 'soh_updated') $message = 'SOH updated.';
}

$supplierOptions = get_user_suppliers($conn, $userId, $role);

$productQuery = 'SELECT p.*, s.supplier_name FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id WHERE p.supplier_id = ?';
$types = 'i';
if ($unitFilter !== '') {
    $productQuery .= ' AND p.unit = ?';
    $types .= 's';
}
if ($search !== '') {
    $productQuery .= ' AND p.product_name LIKE ?';
    $types .= 's';
    $like = '%' . $search . '%';
}
$productQuery .= ' ORDER BY p.id DESC';

$stmt = $conn->prepare($productQuery);
if ($unitFilter !== '' && $search !== '') {
    $stmt->bind_param($types, $supplierId, $unitFilter, $like);
} elseif ($unitFilter !== '') {
    $stmt->bind_param($types, $supplierId, $unitFilter);
} elseif ($search !== '') {
    $stmt->bind_param($types, $supplierId, $like);
} else {
    $stmt->bind_param($types, $supplierId);
}
$stmt->execute();
$products = $stmt->get_result();

$pageTitle = 'Products';
include __DIR__ . '/partials/header.php';
?>

<?php if ($message): ?><div class='pill page-message'><?= esc($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class='alert-box page-message'><?= esc($error) ?></div><?php endif; ?>

<?php if (is_admin()): ?>
<section class='card'>
  <div class='section-head'>
    <h2>Products</h2>
   
  </div>

  <div class='action-row'>
    <button type='button' class='btn btn-dark' onclick='openProductModal()'>Add Product</button>
    <?php if ($editProduct): ?>
      <a class='btn btn-outline' href='products.php?supplier_id=<?= (int)$supplierId ?>'>Cancel Edit</a>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (is_admin()): ?>
<div class='modal-overlay <?= $editProduct ? 'show' : '' ?>' id='productModalOverlay' onclick='closeProductModal()'></div>

<div class='modal-box <?= $editProduct ? 'show' : '' ?>' id='productModal'>
  <div class='modal-head'>
    <h2><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h2>
    <button type='button' class='modal-close' onclick='closeProductModal()'>✕</button>
  </div>

  <form method='post' class='form-grid'>
    <input type='hidden' name='edit_id' value='<?= $editProduct ? (int)$editProduct['id'] : 0 ?>'>

    <div>
      <label class='field-label'>Supplier</label>
      <select name='supplier_id' required>
        <?php $formSuppliers = get_user_suppliers($conn, $userId, $role); while ($supplier = $formSuppliers->fetch_assoc()): ?>
          <option value='<?= (int)$supplier['id'] ?>' <?= ((int)$supplier['id'] === (int)($editProduct['supplier_id'] ?? $supplierId)) ? 'selected' : '' ?>>
            <?= esc($supplier['supplier_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class='field-label'>Product Name</label>
      <input type='text' name='product_name' value='<?= esc($editProduct['product_name'] ?? '') ?>' placeholder='Example: Basmati Rice' required>
    </div>

    <div>
      <label class='field-label'>Unit</label>
      <select name='unit' required>
        <option value=''>Select Unit</option>
        <option value='ctn' <?= (($editProduct['unit'] ?? '') === 'ctn') ? 'selected' : '' ?>>ctn</option>
        <option value='kg' <?= (($editProduct['unit'] ?? '') === 'kg') ? 'selected' : '' ?>>kg</option>
        <option value='pcs' <?= (($editProduct['unit'] ?? '') === 'pcs') ? 'selected' : '' ?>>pcs</option>
      </select>
    </div>

    <div>
      <label class='field-label'>SOH</label>
      <input type='number' name='soh' min='0' value='<?= esc($editProduct['soh'] ?? '0') ?>' placeholder='SOH example: stock on hand 12' required>
    </div>

    <div>
      <label class='field-label'>MOS</label>
      <input type='number' name='csoh' min='0' value='<?= esc($editProduct['csoh'] ?? '0') ?>' placeholder='MOS example: minimum operating stock 30' required>
    </div>

    <div>
      <label class='field-label'>PO</label>
      <input type='number' value='<?= $editProduct ? max((int)$editProduct['csoh'] - (int)$editProduct['soh'], 0) : 0 ?>' placeholder='PO auto calculated' readonly>
    </div>

    <div class='full helper-box'>
      MOS = Minimum operating stock. SOH = Stock on hand. PO = Purchase order = MOS - SOH.
    </div>

    <div class='full action-row'>
      <button class='btn btn-dark' name='save_product'><?= $editProduct ? 'Update Product' : 'Save Product' ?></button>
    </div>
  </form>
</div>
<?php endif; ?>

<section class='card search-card'>
  <div class='section-head compact-head'>
    <h2>Select Supplier</h2>
  </div>

  <form method='get' class='search-inline-form'>
    <select name='supplier_id' onchange='this.form.submit()' required>
      <?php while ($supplier = $supplierOptions->fetch_assoc()): ?>
        <option value='<?= (int)$supplier['id'] ?>' <?= (int)$supplier['id'] === (int)$supplierId ? 'selected' : '' ?>>
          <?= esc($supplier['supplier_name']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <?php if ($search !== ''): ?>
      <input type='hidden' name='search' value='<?= esc($search) ?>'>
    <?php endif; ?>
    <?php if ($unitFilter !== ''): ?>
      <input type='hidden' name='unit' value='<?= esc($unitFilter) ?>'>
    <?php endif; ?>
  
  </form>
</section>

<section class='card search-card'>
  <div class='section-head compact-head'>
   
  </div>

  <form method='get' class='search-inline-form search-inline-form-mobile'>
    <input type='hidden' name='supplier_id' value='<?= (int)$supplierId ?>'>
    <div class='search-field-wrap'>
      <input type='text' name='search' value='<?= esc($search) ?>' placeholder='Search product'>
    </div>
    <div class='search-action-bar'>
      <button class='btn btn-outline search-btn' type='submit'>Search</button>
      <a class='btn btn-outline search-btn' href='order_pdf.php?supplier_id=<?= (int)$supplierId ?>'>Download PO</a>
      <?php if ($search !== '' || $unitFilter !== ''): ?>
        <a class='btn btn-outline search-btn' href='products.php?supplier_id=<?= (int)$supplierId ?>'>Reset</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<section class='card'>
  <div class='section-head'>
    <h2>Product List</h2>
    
  </div>

  

  


  <div class='mobile-cards product-list-layout'>
    <?php $productNumber = 1; ?>
    <?php mysqli_data_seek($products, 0); while ($p = $products->fetch_assoc()): ?>
      <details class='store-card store-dropdown'>
        <summary class='store-summary'>
          <div class='store-top store-top-compact'>
            <div class='store-number'><?= $productNumber ?></div>
            <div class='store-main'>
              <div class='store-title'><?= esc($p['product_name']) ?></div>
            </div>
          </div>
        </summary>

        <div class='store-details'>
          <div class='store-subtitle'><?= esc($p['supplier_name']) ?></div>

        <form method='post' class='store-form'>
          <input type='hidden' name='id' value='<?= (int)$p['id'] ?>'>

          <div class='store-row'>
            <div class='store-label'>SOH</div>
            <div class='store-control soh-control'>
              <button type='button' class='qty-btn' onclick='changeQty(this,-1)'>−</button>
              <input type='number' name='soh' value='<?= (int)$p['soh'] ?>' min='0' step='1' inputmode='numeric' pattern='[0-9]*' class='qty-input'>
              <button type='button' class='qty-btn' onclick='changeQty(this,1)'>+</button>
            </div>
          </div>

          <div class='store-info-grid'>
            <div class='mini-box'>
              <span>MOS</span>
              <strong><?= (int)$p['csoh'] ?></strong>
            </div>
            <div class='mini-box'>
              <span>PO</span>
              <strong><?= (int)$p['op'] ?></strong>
            </div>
            <div class='mini-box'>
              <span>Unit</span>
              <strong><?= esc($p['unit']) ?></strong>
            </div>
          </div>

          <button type='submit' name='update_soh' class='btn btn-dark full-btn'>Save SOH</button>
        </form>

          <?php if (is_admin()): ?>
            <div class='action-row' style='margin-top:12px;'>
              <a class='btn btn-outline btn-small' href='products.php?supplier_id=<?= (int)$supplierId ?>&edit_id=<?= (int)$p['id'] ?>'>Edit</a>
              <a class='btn btn-danger btn-small' href='products.php?supplier_id=<?= (int)$supplierId ?>&delete_id=<?= (int)$p['id'] ?>' onclick="return confirm('Delete this product?')">Delete</a>
            </div>
          <?php endif; ?>
        </div>
      </details>
      <?php $productNumber++; ?>
    <?php endwhile; ?>
    <?php if ($productNumber === 1): ?>
      <div class='helper-box'>No products found for this supplier.</div>
    <?php endif; ?>
  </div>

  <div class='table-wrap'>
    <?php mysqli_data_seek($products, 0); ?>
    <table class='table reorder-table'>
      <thead>
        <tr>
          <th>No.</th>
          <th>Supplier</th>
          <th>Product Name</th>
          <th>Unit</th>
          <th>SOH</th>
          <th>MOS</th>
          <th>PO</th>
          <?php if (is_admin()): ?><th>Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php $tableNumber = 1; ?>
        <?php while ($p = $products->fetch_assoc()): ?>
          <tr>
            <td><?= $tableNumber ?></td>
            <td><?= esc($p['supplier_name']) ?></td>
            <td class='product-name-cell'><?= esc($p['product_name']) ?></td>
            <td><?= esc($p['unit']) ?></td>
            <td>
              <form method='post' class='table-update'>
                <input type='hidden' name='id' value='<?= (int)$p['id'] ?>'>
                <input type='number' name='soh' value='<?= (int)$p['soh'] ?>' min='0' step='1' inputmode='numeric' pattern='[0-9]*' style='width:90px;'>
                <button type='submit' name='update_soh' class='btn btn-small btn-outline'>Save</button>
              </form>
            </td>
            <td><?= (int)$p['csoh'] ?></td>
            <td><?= (int)$p['op'] ?></td>
            <?php if (is_admin()): ?>
              <td>
                <div class='action-row action-row-inline'>
                  <a class='btn btn-small btn-outline' href='products.php?supplier_id=<?= (int)$supplierId ?>&edit_id=<?= (int)$p['id'] ?>'>Edit</a>
                  <a class='btn btn-small btn-danger' href='products.php?supplier_id=<?= (int)$supplierId ?>&delete_id=<?= (int)$p['id'] ?>' onclick="return confirm('Delete this product?')">Delete</a>
                </div>
              </td>
            <?php endif; ?>
          </tr>
          <?php $tableNumber++; ?>
        <?php endwhile; ?>
        <?php if ($products->num_rows === 0): ?>
          <tr>
            <td colspan='<?= is_admin() ? 8 : 7 ?>'>No products found for this supplier.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
