<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'inventory_app';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) { die("Database connection failed: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, username VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'staff') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS suppliers (id INT AUTO_INCREMENT PRIMARY KEY, supplier_name VARCHAR(120) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS user_supplier_access (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, supplier_id INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS products (id INT AUTO_INCREMENT PRIMARY KEY, supplier_id INT NOT NULL, product_name VARCHAR(150) NOT NULL, unit VARCHAR(20) NOT NULL DEFAULT 'kg', soh INT NOT NULL DEFAULT 0, csoh INT NOT NULL DEFAULT 0, op INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$passwordColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
if ($passwordColumn && ($column = $passwordColumn->fetch_assoc())) {
    if (strcasecmp((string)$column['Type'], 'varchar(255)') !== 0) {
        $conn->query("ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL");
    }
}
$check = $conn->query("SHOW COLUMNS FROM products LIKE 'unit'");
if ($check && $check->num_rows === 0) { $conn->query("ALTER TABLE products ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'kg' AFTER product_name"); }

$checkPrice = $conn->query("SHOW COLUMNS FROM products LIKE 'unit_price'");
if ($checkPrice && $checkPrice->num_rows === 0) { $conn->query("ALTER TABLE products ADD COLUMN unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00"); }

$conn->query("CREATE TABLE IF NOT EXISTS orders (id INT(11) NOT NULL AUTO_INCREMENT, supplier_id INT(11) NOT NULL, order_date DATE NOT NULL, ordered_by INT(11) NOT NULL, pdf_filename VARCHAR(255) DEFAULT NULL, total_items INT(11) NOT NULL DEFAULT 0, estimated_total DECIMAL(10,2) NOT NULL DEFAULT 0.00, status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending', notes TEXT DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_supplier_date (supplier_id, order_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS order_items (id INT(11) NOT NULL AUTO_INCREMENT, order_id INT(11) NOT NULL, product_id INT(11) NOT NULL, product_name VARCHAR(150) NOT NULL, quantity INT(11) NOT NULL DEFAULT 0, unit VARCHAR(20) NOT NULL DEFAULT '', unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00, line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00, PRIMARY KEY (id), KEY idx_order_id (order_id), KEY idx_product_id (product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS invoices (id INT(11) NOT NULL AUTO_INCREMENT, order_id INT(11) DEFAULT NULL, supplier_id INT(11) NOT NULL, invoice_number VARCHAR(100) DEFAULT NULL, invoice_date DATE NOT NULL, total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, pdf_filename VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_supplier_date (supplier_id, invoice_date), KEY idx_order_id (order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$admin = $conn->query("SELECT id, password FROM users WHERE username='admin' LIMIT 1");
if ($admin && $admin->num_rows === 0) {
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users(name, username, password, role) VALUES ('Main Admin','admin',?,'admin')");
    $stmt->bind_param('s', $adminPassword);
    $stmt->execute();
} elseif ($admin && ($adminRow = $admin->fetch_assoc())) {
    if (($adminRow['password'] ?? '') === 'admin123') {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $adminPassword, $adminRow['id']);
        $stmt->execute();
    }
}

$supplierCount = 0; $supplierRes = $conn->query("SELECT COUNT(*) AS total FROM suppliers");
if ($supplierRes) { $supplierCount = (int)($supplierRes->fetch_assoc()['total'] ?? 0); }
if ($supplierCount === 0) {
    $conn->query("INSERT INTO suppliers(supplier_name) VALUES ('Indian grocery'),('Local Indian Grocery'),('Disposal'),('Supermarket'),('Fruit & Veggies'),('Coffee'),('Frozen goods')");
}
?>
