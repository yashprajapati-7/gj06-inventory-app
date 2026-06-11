<?php
require_once __DIR__ . '/functions.php';
require_admin();

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); die('Invalid request.'); }

if ($type === 'order') {
    $stmt = $conn->prepare("SELECT pdf_filename FROM orders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !$row['pdf_filename']) { http_response_code(404); die('Order PDF not found.'); }
    $path         = __DIR__ . '/storage/orders/' . basename($row['pdf_filename']);
    $downloadName = 'order_' . $id . '_' . basename($row['pdf_filename']);

} elseif ($type === 'invoice') {
    $stmt = $conn->prepare("SELECT pdf_filename FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !$row['pdf_filename']) { http_response_code(404); die('Invoice PDF not found.'); }
    $path         = __DIR__ . '/storage/invoices/' . basename($row['pdf_filename']);
    $downloadName = 'invoice_' . $id . '.pdf';

} else {
    http_response_code(400);
    die('Invalid type.');
}

if (!is_file($path)) {
    http_response_code(404);
    die('File not found on disk. It may have been cleared during a server restart. Please regenerate the PDF from the Reorder page.');
}

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
