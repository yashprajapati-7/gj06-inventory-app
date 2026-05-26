<?php
require_once __DIR__ . '/config.php';

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function require_login(){ if(!isset($_SESSION['user_id'])){ header('Location: login.php'); exit; } }
function require_admin(){ require_login(); if(($_SESSION['role'] ?? '') !== 'admin'){ header('Location: supplier_select.php'); exit; } }
function is_admin(){ return (($_SESSION['role'] ?? '') === 'admin'); }

function get_user_suppliers($conn, $userId, $role){
    if($role === 'admin'){
        return $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
    }
    $stmt = $conn->prepare("SELECT s.* FROM suppliers s INNER JOIN user_supplier_access usa ON usa.supplier_id=s.id WHERE usa.user_id=? ORDER BY s.supplier_name ASC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result();
}

function user_can_access_supplier($conn, $userId, $role, $supplierId){
    if($role === 'admin'){ return true; }
    $stmt = $conn->prepare("SELECT id FROM user_supplier_access WHERE user_id=? AND supplier_id=? LIMIT 1");
    $stmt->bind_param("ii", $userId, $supplierId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function user_can_touch_product($conn, $userId, $role, $productId){
    if($role === 'admin'){ return true; }
    $stmt = $conn->prepare("SELECT p.id FROM products p INNER JOIN user_supplier_access usa ON usa.supplier_id=p.supplier_id WHERE p.id=? AND usa.user_id=? LIMIT 1");
    $stmt->bind_param("ii", $productId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function supplier_icon($name){
    $map = [
        'Indian grocery'=>'🛒',
        'Local Indian Grocery'=>'🏬',
        'Disposal'=>'🧻',
        'Supermarket'=>'🏪',
        'Fruit & Veggies'=>'🥬',
        'Coffee'=>'☕',
        'Frozen goods'=>'🧊'
    ];
    return $map[$name] ?? '📦';
}

function pdf_escape($text){
    $text = str_replace("\\", "\\\\", (string)$text);
    $text = str_replace("(", "\\(", $text);
    $text = str_replace(")", "\\)", $text);
    return preg_replace('/[^\x20-\x7E]/', '', $text);
}

function create_table_pdf($title, $rows, $outputName='purchase_order_sheet.pdf', $meta=[]){
    $pageWidth = 595;
    $pageHeight = 842;
    $margin = 40;
    $titleY = 800;
    $subY = 780;
    $tableTop = 735;
    $rowHeight = 24;

    $col1 = 320;
    $col2 = 90;
    $col3 = 100;

    $x1 = $margin;
    $x2 = $x1 + $col1;
    $x3 = $x2 + $col2;
    $x4 = $x3 + $col3;

    $displayRows = max(count($rows), 1);
    $rowCount = $displayRows + 1;
    $tableBottom = $tableTop - ($rowCount * $rowHeight);

    $content = [];
    $content[] = "0.08 0.08 0.08 rg";
    $content[] = "0.08 0.08 0.08 RG";
    $content[] = "1 w";
    $content[] = "BT /F2 18 Tf {$x1} {$titleY} Td (" . pdf_escape($title) . ") Tj ET";

    $subtitle = !empty($meta['subtitle']) ? $meta['subtitle'] : ('Date: ' . date('d M Y'));
    $content[] = "BT /F1 10 Tf {$x1} {$subY} Td (" . pdf_escape($subtitle) . ") Tj ET";

    $content[] = "0.93 0.95 0.98 rg";
    $content[] = "{$x1} " . ($tableTop - $rowHeight) . " " . ($x4 - $x1) . " {$rowHeight} re f";
    $content[] = "0.08 0.08 0.08 rg";
    $content[] = "0.08 0.08 0.08 RG";

    $content[] = "{$x1} {$tableTop} m {$x4} {$tableTop} l S";
    $content[] = "{$x1} {$tableBottom} m {$x4} {$tableBottom} l S";
    $content[] = "{$x1} {$tableTop} m {$x1} {$tableBottom} l S";
    $content[] = "{$x4} {$tableTop} m {$x4} {$tableBottom} l S";
    $content[] = "{$x2} {$tableTop} m {$x2} {$tableBottom} l S";
    $content[] = "{$x3} {$tableTop} m {$x3} {$tableBottom} l S";

    for ($i = 0; $i <= $rowCount; $i++) {
        $y = $tableTop - ($i * $rowHeight);
        $content[] = "{$x1} {$y} m {$x4} {$y} l S";
    }

    $headerY = $tableTop - 16;
    $content[] = "BT /F2 11 Tf " . ($x1 + 8) . " {$headerY} Td (" . pdf_escape('Product Name') . ") Tj ET";
    $content[] = "BT /F2 11 Tf " . ($x2 + 8) . " {$headerY} Td (" . pdf_escape('PO') . ") Tj ET";
    $content[] = "BT /F2 11 Tf " . ($x3 + 8) . " {$headerY} Td (" . pdf_escape('Unit') . ") Tj ET";

    $currentY = $tableTop - $rowHeight - 16;
    if (empty($rows)) {
        $content[] = "BT /F1 11 Tf " . ($x1 + 8) . " {$currentY} Td (" . pdf_escape('No purchase items') . ") Tj ET";
    } else {
        foreach ($rows as $row) {
            $product = pdf_escape($row['product_name'] ?? '');
            $po = pdf_escape((string)($row['po'] ?? 0));
            $unit = pdf_escape($row['unit'] ?? '');

            $content[] = "BT /F1 10 Tf " . ($x1 + 8) . " {$currentY} Td ({$product}) Tj ET";
            $content[] = "BT /F1 10 Tf " . ($x2 + 8) . " {$currentY} Td ({$po}) Tj ET";
            $content[] = "BT /F1 10 Tf " . ($x3 + 8) . " {$currentY} Td ({$unit}) Tj ET";

            $currentY -= $rowHeight;
        }
    }

    $stream = implode("\n", $content);

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
    $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    for ($i = 0; $i < count($objects); $i++) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}

function create_grouped_reorder_pdf($title, $groups, $outputName='reorder_products.pdf', $meta=[]){
    $pageWidth = 595;
    $pageHeight = 842;
    $margin = 40;
    $bottomMargin = 48;
    $companyName = !empty($meta['company_name']) ? (string)$meta['company_name'] : 'GJ06 Cafe & Bakehouse';
    $companyY = 808;
    $titleY = 780;
    $subY = 756;
    $startY = 718;
    $rowHeight = 28;
    $sectionGap = 24;
    $logoPath = __DIR__ . '/assets/gj06_logo.png';
    $logoWidth = 108;
    $logoHeight = 108;
    $logoX = $pageWidth - $margin - $logoWidth;
    $logoY = $pageHeight - 26 - $logoHeight;

    $tableWidth = $pageWidth - ($margin * 2);
    $col1 = 55;
    $col2 = 295;
    $col3 = 70;
    $col4 = $tableWidth - ($col1 + $col2 + $col3);

    $x1 = $margin;
    $x2 = $x1 + $col1;
    $x3 = $x2 + $col2;
    $x4 = $x3 + $col3;
    $x5 = $x1 + $tableWidth;

    $hasLogo = false;
    $logoObject = '';
    if (is_file($logoPath)) {
        $logoInfo = @getimagesize($logoPath);
        $logoData = @file_get_contents($logoPath);
        if ($logoInfo && $logoData !== false && ($logoInfo[2] ?? null) === IMAGETYPE_JPEG) {
            $hasLogo = true;
            $logoObject = "<< /Type /XObject /Subtype /Image /Width {$logoInfo[0]} /Height {$logoInfo[1]} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logoData) . " >>\nstream\n" . $logoData . "\nendstream";
        }
    }

    $subtitle = !empty($meta['subtitle']) ? $meta['subtitle'] : ('Date: ' . date('d M Y'));
    $pages = [];
    $content = [];

    $startPage = function() use (&$content, $margin, $companyY, $titleY, $subY, $companyName, $title, $subtitle, $hasLogo, $logoWidth, $logoHeight, $logoX, $logoY) {
        $content = [];
        $content[] = "0.08 0.08 0.08 rg";
        $content[] = "0.08 0.08 0.08 RG";
        $content[] = "1.3 w";
        if ($hasLogo) {
            $content[] = "q {$logoWidth} 0 0 {$logoHeight} {$logoX} {$logoY} cm /Im1 Do Q";
        }
        $content[] = "BT /F2 16 Tf {$margin} {$companyY} Td (" . pdf_escape($companyName) . ") Tj ET";
        $content[] = "BT /F2 22 Tf {$margin} {$titleY} Td (" . pdf_escape($title) . ") Tj ET";
        $content[] = "BT /F1 13 Tf {$margin} {$subY} Td (" . pdf_escape($subtitle) . ") Tj ET";
    };

    $finishPage = function() use (&$pages, &$content) {
        $pages[] = implode("\n", $content);
    };

    $drawTable = function($supplierName, $rows, $startIndex, $continued, $currentY) use (&$content, $margin, $rowHeight, $tableWidth, $x1, $x2, $x3, $x4, $x5) {
        $rowCount = max(count($rows), 1);
        $tableTop = $currentY - 34;
        $tableBottom = $tableTop - (($rowCount + 1) * $rowHeight);
        $displayName = $supplierName . ($continued ? ' (continued)' : '');

        $content[] = "BT /F2 17 Tf {$margin} {$currentY} Td (" . pdf_escape($displayName) . ") Tj ET";
        $content[] = "0 0 0 rg";
        $content[] = "{$x1} " . ($tableTop - $rowHeight) . " {$tableWidth} {$rowHeight} re f";
        $content[] = "1 1 1 rg";
        for ($i = 0; $i < $rowCount; $i++) {
            $bodyY = $tableTop - (($i + 2) * $rowHeight);
            $content[] = "{$x1} {$bodyY} {$tableWidth} {$rowHeight} re f";
        }
        $content[] = "1 1 1 rg";
        $content[] = "BT /F2 12 Tf " . ($x1 + 10) . " " . ($tableTop - 18) . " Td (" . pdf_escape('No.') . ") Tj ET";
        $content[] = "BT /F2 12 Tf " . ($x2 + 10) . " " . ($tableTop - 18) . " Td (" . pdf_escape('Product Name') . ") Tj ET";
        $content[] = "BT /F2 12 Tf " . ($x3 + 10) . " " . ($tableTop - 18) . " Td (" . pdf_escape('PO') . ") Tj ET";
        $content[] = "BT /F2 12 Tf " . ($x4 + 10) . " " . ($tableTop - 18) . " Td (" . pdf_escape('Unit') . ") Tj ET";

        $content[] = "0 0 0 rg";
        $content[] = "0 0 0 RG";
        $content[] = "{$x1} {$tableTop} m {$x5} {$tableTop} l S";
        $content[] = "{$x1} {$tableBottom} m {$x5} {$tableBottom} l S";
        $content[] = "{$x1} {$tableTop} m {$x1} {$tableBottom} l S";
        $content[] = "{$x5} {$tableTop} m {$x5} {$tableBottom} l S";
        $content[] = "{$x2} {$tableTop} m {$x2} {$tableBottom} l S";
        $content[] = "{$x3} {$tableTop} m {$x3} {$tableBottom} l S";
        $content[] = "{$x4} {$tableTop} m {$x4} {$tableBottom} l S";

        for ($i = 0; $i <= ($rowCount + 1); $i++) {
            $y = $tableTop - ($i * $rowHeight);
            $content[] = "{$x1} {$y} m {$x5} {$y} l S";
        }

        if (empty($rows)) {
            $content[] = "BT /F1 12 Tf " . ($x2 + 10) . " " . ($tableTop - $rowHeight - 18) . " Td (" . pdf_escape('No reorder products found') . ") Tj ET";
        } else {
            $textY = $tableTop - $rowHeight - 18;
            foreach ($rows as $index => $row) {
                $number = pdf_escape((string)($startIndex + $index + 1));
                $product = pdf_escape($row['product_name'] ?? '');
                $po = pdf_escape((string)($row['po'] ?? 0));
                $unit = pdf_escape($row['unit'] ?? '');

                $content[] = "BT /F1 12 Tf " . ($x1 + 10) . " {$textY} Td ({$number}) Tj ET";
                $content[] = "BT /F1 12 Tf " . ($x2 + 10) . " {$textY} Td ({$product}) Tj ET";
                $content[] = "BT /F1 12 Tf " . ($x3 + 10) . " {$textY} Td ({$po}) Tj ET";
                $content[] = "BT /F1 12 Tf " . ($x4 + 10) . " {$textY} Td ({$unit}) Tj ET";
                $textY -= $rowHeight;
            }
        }

        return $tableBottom;
    };

    $startPage();
    $currentY = $startY;
    if (empty($groups)) {
        $content[] = "BT /F1 11 Tf {$margin} {$currentY} Td (" . pdf_escape('No reorder products found') . ") Tj ET";
    } else {
        foreach ($groups as $group) {
            $supplierName = (string)($group['supplier_name'] ?? 'Unknown Supplier');
            $rows = $group['rows'] ?? [];

            if ($currentY - 34 - (2 * $rowHeight) < $bottomMargin) {
                $finishPage();
                $startPage();
                $currentY = $startY;
            }

            if (empty($rows)) {
                $tableBottom = $drawTable($supplierName, [], 0, false, $currentY);
                $currentY = $tableBottom - $sectionGap;
                continue;
            }

            $offset = 0;
            while ($offset < count($rows)) {
                $tableTop = $currentY - 34;
                $rowsThatFit = (int)floor(($tableTop - $bottomMargin) / $rowHeight) - 1;

                if ($rowsThatFit < 1) {
                    $finishPage();
                    $startPage();
                    $currentY = $startY;
                    $tableTop = $currentY - 34;
                    $rowsThatFit = (int)floor(($tableTop - $bottomMargin) / $rowHeight) - 1;
                }

                $chunk = array_slice($rows, $offset, $rowsThatFit);
                $tableBottom = $drawTable($supplierName, $chunk, $offset, $offset > 0, $currentY);
                $offset += count($chunk);
                $currentY = $tableBottom - $sectionGap;

                if ($offset < count($rows)) {
                    $finishPage();
                    $startPage();
                    $currentY = $startY;
                }
            }
        }
    }
    $finishPage();

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
    $logoObjectNumber = $hasLogo ? 5 : null;
    if ($hasLogo) {
        $objects[] = $logoObject;
    }

    $pageObjects = [];
    $contentObjects = [];
    $pageCount = count($pages);
    $firstPageObjectNumber = count($objects) + 2;
    $firstContentObjectNumber = $firstPageObjectNumber + $pageCount;
    $pageResources = "<< /Font << /F1 3 0 R /F2 4 0 R >>" . ($hasLogo ? " /XObject << /Im1 {$logoObjectNumber} 0 R >>" : "") . " >>";

    for ($i = 0; $i < $pageCount; $i++) {
        $pageObjectNumber = $firstPageObjectNumber + $i;
        $contentObjectNumber = $firstContentObjectNumber + $i;
        $pageObjects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources {$pageResources} /Contents {$contentObjectNumber} 0 R >>";
        $stream = $pages[$i];
        $contentObjects[] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
    }

    $kids = [];
    for ($i = 0; $i < $pageCount; $i++) {
        $kids[] = ($firstPageObjectNumber + $i) . " 0 R";
    }
    array_splice($objects, 1, 0, ["<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount} >>"]);
    $objects = array_merge($objects, $pageObjects, $contentObjects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    for ($i = 0; $i < count($objects); $i++) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}
?>
