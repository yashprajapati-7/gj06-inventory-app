<?php require_once __DIR__ . '/functions.php';
require_admin();
$users = $conn->query('SELECT * FROM users ORDER BY id DESC');
$userRows = [];
while ($u = $users->fetch_assoc()) {
    $accessCount = 0;
    if ($u['role'] !== 'admin') {
        $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM user_supplier_access WHERE user_id = ?');
        $countStmt->bind_param('i', $u['id']);
        $countStmt->execute();
        $accessCount = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    }
    $u['access_count'] = $u['role'] === 'admin' ? 'All' : $accessCount;
    $userRows[] = $u;
}
$pageTitle = 'Users';

include __DIR__ . '/partials/header.php'; ?>
<section class='card'>
    <div class='section-head'>
        <h2>Users</h2>
        
    </div>
    <div class='action-row' style='margin-bottom:16px;'><a class='btn btn-dark' href='user_form.php'>Create User</a>
    </div>

    <div class='table-wrap user-table-wrap'>
        <table class='table'>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Access</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userRows as $u): ?>
                    <tr>
                        <td><?= esc($u['username']) ?></td>
                        <td>********</td>
                        <td><?= esc((string) $u['access_count']) ?></td>
                        <td>
                            <div class='action-row action-row-inline'><a class='btn btn-small btn-outline'
                                    href='user_form.php?id=<?= (int) $u['id'] ?>'>Edit</a><?php if ((int) $u['id'] !== 1): ?><a
                                        class='btn btn-small btn-danger' href='user_delete.php?id=<?= (int) $u['id'] ?>'
                                        onclick="return confirm('Delete this user?')">Delete</a><?php endif; ?></div>
                        </td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section><?php include __DIR__ . '/partials/footer.php'; ?>
