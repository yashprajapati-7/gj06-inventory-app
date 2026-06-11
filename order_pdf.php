<?php
require_once __DIR__ . '/functions.php';
require_login();

$userId     = (int)$_SESSION['user_id'];
$role       = $_SESSION['role'];
$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$groups     = [];
$meta       = ['subtitle' => 'Date: ' . date('d M Y'), 'company_name' => 'GJ06 Cafe & Bakehouse'];

// Admin + specific supplier: save order record and PDF to disk
if ($role === 'admin' && $supplierId > 0) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $itemStmt = $conn->prepare("SELECT p.id AS product_id, p.product_name, p.op AS quantity, p.unit, p.unit_price FROM products p WHERE p.supplier_id = ? AND p.op > 0");
        $itemStmt->bind_param("i", $supplierId);
        $itemStmt->execute();
        $orderItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($orderItems)) {
            $orderId     = save_order_record($conn, $supplierId, $userId, $orderItems);
            $pdfFilename = 'order_' . $orderId . '_' . date('Y-m-d') . '.pdf';
            update_order_pdf($conn, $orderId, $pdfFilename);
            $meta['save_path'] = __DIR__ . '/storage/orders/' . $pdfFilename;
        }
    }
}

if ($role === 'admin') {
    if ($supplierId > 0) {
        $stmt = $conn->prepare("SELECT s.supplier_name, p.product_name, p.op AS po, p.unit FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id WHERE p.supplier_id = ? AND p.op > 0 ORDER BY s.supplier_name ASC, p.product_name ASC");
        $stmt->bind_param("i", $supplierId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT s.supplier_name, p.product_name, p.op AS po, p.unit FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id WHERE p.op > 0 ORDER BY s.supplier_name ASC, p.product_name ASC");
    }
} else {
    if ($supplierId > 0 && !user_can_access_supplier($conn, $userId, $role, $supplierId)) {
        die("Access denied");
    }

    if ($supplierId > 0) {
        $stmt = $conn->prepare("SELECT s.supplier_name, p.product_name, p.op AS po, p.unit FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id INNER JOIN user_supplier_access usa ON usa.supplier_id = p.supplier_id WHERE usa.user_id = ? AND p.supplier_id = ? AND p.op > 0 ORDER BY s.supplier_name ASC, p.product_name ASC");
        $stmt->bind_param("ii", $userId, $supplierId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $stmt = $conn->prepare("SELECT s.supplier_name, p.product_name, p.op AS po, p.unit FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id INNER JOIN user_supplier_access usa ON usa.supplier_id = p.supplier_id WHERE usa.user_id = ? AND p.op > 0 ORDER BY s.supplier_name ASC, p.product_name ASC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

while ($row = $result->fetch_assoc()) {
    $supplierName = $row['supplier_name'] ?? 'Unknown Supplier';
    if (!isset($groups[$supplierName])) {
        $groups[$supplierName] = ['supplier_name' => $supplierName, 'rows' => []];
    }
    $groups[$supplierName]['rows'][] = [
        'product_name' => $row['product_name'],
        'po'           => (int)$row['po'],
        'unit'         => $row['unit'],
    ];
}

create_grouped_reorder_pdf('Reorder Products', array_values($groups), 'reorder_products.pdf', $meta);
?>
