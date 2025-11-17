<?php
require_once __DIR__ . '/../../_imports.php';
pageHead('Users - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$q = trim((string)($_GET['q'] ?? ''));
$filter_active = isset($_GET['active']) ? $_GET['active'] : '';

// discover available columns on users table to be defensive
$cols = [];
$colRes = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
if ($colRes) {
    while ($r = $colRes->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];
}

$wanted = ['id', 'first_name', 'last_name', 'email', 'phone', 'is_active', 'created_at'];
$selectCols = array_values(array_intersect($wanted, $cols));
if (empty($selectCols)) {
    // fallback to selecting all
    $selectSQL = '*';
} else {
    $selectSQL = implode(', ', array_map(function ($c) {
        return $c;
    }, $selectCols));
}

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    // search across likely columns if present
    $searchParts = [];
    foreach (['first_name', 'last_name', 'email', 'phone'] as $col) {
        if (in_array($col, $cols)) {
            $searchParts[] = "$col LIKE ?";
            $params[] = "%$q%";
            $types .= 's';
        }
    }
    if (!empty($searchParts)) $where[] = '(' . implode(' OR ', $searchParts) . ')';
}

if ($filter_active !== '') {
    if (in_array('is_active', $cols)) {
        $where[] = 'is_active = ?';
        $params[] = (int)$filter_active ? 1 : 0;
        $types .= 'i';
    }
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// count total
$total = 0;
$cntSql = "SELECT COUNT(*) AS cnt FROM users $whereSQL";
$cntStmt = $conn->prepare($cntSql);
if ($cntStmt) {
    if (!empty($params)) {
        // bind using references
        $refs = [];
        $refs[] = &$types;
        for ($i = 0; $i < count($params); $i++) $refs[] = &$params[$i];
        call_user_func_array([$cntStmt, 'bind_param'], $refs);
    }
    $cntStmt->execute();
    $cres = $cntStmt->get_result();
    if ($cres) {
        $row = $cres->fetch_assoc();
        $total = (int)($row['cnt'] ?? 0);
    }
    $cntStmt->close();
}

$users = [];
$sql = "SELECT $selectSQL FROM users $whereSQL ORDER BY created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    // bind params + offset/perPage
    if (!empty($params)) {
        $bindTypes = $types . 'ii';
        $bindParams = $params;
    } else {
        $bindTypes = 'ii';
        $bindParams = [];
    }
    $bindParams[] = $offset;
    $bindParams[] = $perPage;

    $refs = [];
    $refs[] = &$bindTypes;
    for ($i = 0; $i < count($bindParams); $i++) $refs[] = &$bindParams[$i];
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) while ($row = $res->fetch_assoc()) $users[] = $row;
    $stmt->close();
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

?>
<style>
    /* Small local styles for the users search box */
    .admin-entity-header{
        display: flex;
        flex-wrap: wrap;
    }
    .users-search {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .search-input-wrap {
        position: relative;
        display: inline-block;
    }

    .search-input {
        padding: 8px 36px 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        min-width: 320px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
    }

    .search-clear {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: 0;
        cursor: pointer;
        font-size: 16px;
        color: #6b7280;
        padding: 4px;
    }

    .search-clear[hidden] {
        display: none;
    }

    .search-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* Styled select to match input */
    .users-search select {
        padding: 8px 10px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #fff;
        color: #111827;
    }

    .users-search select:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
        border-color: #6366f1;
    }

    /* Buttons */
    .users-search .btn {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: #4f46e5;
        color: #fff;
        text-decoration: none;
        display: inline-block;
    }

    .users-search .btn:hover {
        opacity: 0.95;
    }

    .users-search a.btn {
        background: #efefef;
        color: #111827;
        border-color: #e5e7eb;
    }

    @media (max-width:640px) {
        .search-input {
            min-width: 140px;
        }
    }
</style>
<div class="admin-container">

    <div class="admin-entity-header">
        <h1>Users</h1>
        <div>
            <form method="GET" class="users-search" id="usersSearchForm">
                <div class="search-input-wrap">
                    <input
                        type="text"
                        name="q"
                        id="usersSearchInput"
                        class="search-input"
                        placeholder="Search name, email or phone"
                        value="<?php echo htmlspecialchars($q); ?>"
                        aria-label="Search users"
                        autocomplete="off" />
                    <button type="button" id="usersSearchClear" class="search-clear" title="Clear search" <?php echo $q === '' ? 'hidden' : ''; ?>>&times;</button>
                </div>

                <?php if (in_array('is_active', $cols)): ?>
                    <select name="active" aria-label="Filter by status">
                        <option value="">All</option>
                        <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>Restricted</option>
                    </select>
                <?php endif; ?>

                <div class="search-actions">
                    <button class="btn" type="submit">Search</button>
                    <a class="btn" href="/admin/users/index.php">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px">ID</th>
                        <th style="text-align:left;padding:8px">Name</th>
                        <th style="text-align:left;padding:8px">Email</th>
                        <th style="text-align:left;padding:8px">Phone</th>
                        <?php if (in_array('is_active', $cols)): ?><th style="text-align:left;padding:8px">Active</th><?php endif; ?>
                        <?php if (in_array('created_at', $cols)): ?><th style="text-align:left;padding:8px">Created</th><?php endif; ?>
                        <th style="text-align:left;padding:8px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="padding:12px">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td style="padding:8px;vertical-align:middle"><?php echo (int)($u['id'] ?? 0); ?></td>
                                <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars(trim((($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))); ?></td>
                                <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                                <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                <?php if (in_array('is_active', $cols)): ?><td style="padding:8px"><?php echo !empty($u['is_active']) ? 'Yes' : 'No'; ?></td><?php endif; ?>
                                <?php if (in_array('created_at', $cols)): ?><td style="padding:8px"><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td><?php endif; ?>
                                <td style="padding:8px;vertical-align:middle">
                                    <a class="btn" href="<?php echo url('/admin/users/show.php') . '?id=' . (int)$u['id']; ?>">Show</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php if ($page > 1): ?><a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">« Prev</a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $page): ?>
                    <strong style="padding:6px 10px;border-radius:6px;background:#eef2ff"><?php echo $p; ?></strong>
                <?php else: ?>
                    <a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next »</a><?php endif; ?>
            <div style="margin-left:8px;color:#64748b">Page <?php echo $page; ?> of <?php echo $totalPages; ?> — <?php echo $total; ?> users</div>
        </div>
    <?php endif; ?>

</div>
<script>
    (function() {
        var input = document.getElementById('usersSearchInput');
        var clearBtn = document.getElementById('usersSearchClear');
        var form = document.getElementById('usersSearchForm');

        function updateClear() {
            if (!clearBtn) return;
            if (input.value.trim() === '') clearBtn.setAttribute('hidden', '');
            else clearBtn.removeAttribute('hidden');
        }

        if (input) {
            // simply update clear button visibility on input; do NOT auto-submit
            input.addEventListener('input', function() {
                updateClear();
            });

            // pressing ESC clears (but does not submit)
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    input.value = '';
                    updateClear();
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                input.value = '';
                updateClear();
                input.focus();
            });
        }
    })();
</script>
<?php pageFooter(); ?>