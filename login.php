<?php require_once __DIR__ . '/functions.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . (is_admin() ? 'supplier_select.php' : 'products.php'));
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $stmt = $conn->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $storedPassword = (string)($user['password'] ?? '');
        $passwordValid = password_verify($password, $storedPassword);

        if (!$passwordValid && hash_equals($storedPassword, $password)) {
            $passwordValid = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
            $updateStmt->bind_param('si', $newHash, $user['id']);
            $updateStmt->execute();
        } elseif ($passwordValid && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
            $updateStmt->bind_param('si', $newHash, $user['id']);
            $updateStmt->execute();
        }

        if ($passwordValid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: ' . (($user['role'] ?? '') === 'admin' ? 'supplier_select.php' : 'products.php'));
            exit;
        }
    }
    $error = 'Invalid username or password.';
} ?><!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>GJ06 - Login</title>
    <link rel='stylesheet' href='assets/style.css'>
</head>

<body class='login-body'>
    <div class='login-shell'>
        <div class='login-card'>
            <div class='login-logo'><img src='assets/gj06_logo.png' alt='GJ06 Logo'></div>
            <h1>Inventory Login</h1>
             <?php if ($error): ?>
                <div class='alert-box full-width'>
                    <?= esc($error) ?>
                </div><?php endif; ?>
            <form method='post' class='form-grid single-column'>
                <div><label class='field-label'>Username</label><input type='text' name='username'
                        placeholder='Enter username' required></div>
                <div><label class='field-label'>Password</label><input type='password' name='password'
                        placeholder='Enter password' required></div><button class='btn btn-dark'
                    name='login'>Login</button>
            </form>

        </div>
    </div>
</body>

</html>
