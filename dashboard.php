<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$totalPages = 1;

$totalShipments = 0;
$totalAmount = 0;
$recentShipments = [];
$error = '';
$success = '';
$formValues = [
    'id' => '',
    'bilty_number' => '',
    'bilty_date' => '',
    'importer' => '',
    'exporter' => '',
    'sb_number' => '',
    'sb_date' => '',
    'amount' => '',
    'other' => ''
];

$biltyFormValues = [
    'id' => '',
    'entry_date' => '',
    'bilty_number' => '',
    'truck_number' => '',
    'from_location' => '',
    'to_location' => '',
    'bilty_freight' => ''
];
$totalBilties = 0;
$totalFreight = 0;
$totalAdvance = 0;
$recentBilties = [];
$biltyError = '';
$biltySuccess = '';

$showAdvance = false;
$advanceBilty = null;
$advanceRecords = [];
$advanceTotalPaid = 0;
$advanceRemaining = 0;
$advanceError = '';
$advanceSuccess = '';
$advanceLookupValue = '';
$advanceEditId = 0;
$advanceEditValues = [
    'id' => 0,
    'adv_date' => '',
    'adv_office' => '',
    'advance_amount' => ''
];

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['delete'], 'user_id' => $user_id]);
    header('Location: dashboard.php' . ($page > 1 ? '?page=' . $page : ''));
    exit;
}

$showBilty = false;
if (isset($_GET['delete_bilty'])) {
    $stmt = $pdo->prepare("DELETE FROM direct_bilty WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['delete_bilty'], 'user_id' => $user_id]);
    header('Location: dashboard.php?view=bilty');
    exit;
}

if (isset($_GET['edit_bilty'])) {
    $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['edit_bilty'], 'user_id' => $user_id]);
    $rec = $stmt->fetch();
    if ($rec) {
        $showBilty = true;
        $biltyFormValues = [
            'id' => $rec['id'],
            'entry_date' => $rec['entry_date'] ?? '',
            'bilty_number' => $rec['bilty_number'],
            'truck_number' => $rec['truck_number'] ?? '',
            'from_location' => $rec['from_location'] ?? '',
            'to_location' => $rec['to_location'] ?? '',
            'bilty_freight' => $rec['bilty_freight']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'direct_bilty') {
    $bBiltyNumber = trim($_POST['bilty_number'] ?? '');
    $bEntryDate = !empty($_POST['entry_date']) ? $_POST['entry_date'] : null;
    $bTruckNumber = trim($_POST['truck_number'] ?? '');
    $bFrom = trim($_POST['from_location'] ?? '');
    $bTo = trim($_POST['to_location'] ?? '');
    $bFreight = trim($_POST['bilty_freight'] ?? '');
    $bRecordId = (int)($_POST['id'] ?? 0);

    if (empty($bBiltyNumber)) {
        $biltyError = 'Bilty number is required.';
    } else {
        $bFreight = $bFreight === '' ? 0 : (float)$bFreight;

        try {
            if ($bRecordId > 0) {
                $stmt = $pdo->prepare("UPDATE direct_bilty SET entry_date = :entry_date, bilty_number = :bilty_number, truck_number = :truck_number, from_location = :from_location, to_location = :to_location, bilty_freight = :bilty_freight WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    'id' => $bRecordId,
                    'user_id' => $user_id,
                    'entry_date' => $bEntryDate,
                    'bilty_number' => $bBiltyNumber,
                    'truck_number' => $bTruckNumber,
                    'from_location' => $bFrom,
                    'to_location' => $bTo,
                    'bilty_freight' => $bFreight
                ]);
                $biltySuccess = 'Direct bilty record updated successfully.';
                $showBilty = true;
            } else {
                $stmt = $pdo->prepare("INSERT INTO direct_bilty (user_id, entry_date, bilty_number, truck_number, from_location, to_location, bilty_freight) VALUES (:user_id, :entry_date, :bilty_number, :truck_number, :from_location, :to_location, :bilty_freight)");
                $stmt->execute([
                    'user_id' => $user_id,
                    'entry_date' => $bEntryDate,
                    'bilty_number' => $bBiltyNumber,
                    'truck_number' => $bTruckNumber,
                    'from_location' => $bFrom,
                    'to_location' => $bTo,
                    'bilty_freight' => $bFreight
                ]);
                $biltySuccess = 'Direct bilty data saved successfully.';
                $showBilty = true;
            }
        } catch (PDOException $e) {
            $biltyError = 'Failed to save data. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'advance_lookup') {
    $showAdvance = true;
    $advanceLookupValue = trim($_POST['advance_lookup_number'] ?? '');
    if ($advanceLookupValue === '') {
        $advanceError = 'Please enter a bilty number.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE bilty_number = :b AND user_id = :u LIMIT 1");
        $stmt->execute(['b' => $advanceLookupValue, 'u' => $user_id]);
        $rec = $stmt->fetch();
        if ($rec) {
            $advanceBilty = $rec;
        } else {
            $advanceError = 'Bilty number not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'advance_entry') {
    $showAdvance = true;
    $advNum = trim($_POST['advance_bilty_number'] ?? '');
    $advDate = !empty($_POST['adv_date']) ? $_POST['adv_date'] : null;
    $advOffice = trim($_POST['adv_office'] ?? '');
    $advAmount = trim($_POST['advance_amount'] ?? '');
    $advanceEditId = (int)($_POST['advance_id'] ?? 0);

    if ($advNum === '') {
        $advanceError = 'Bilty number is required.';
    } elseif ($advAmount === '' || (float)$advAmount <= 0) {
        $advanceError = 'Please enter a valid advance amount.';
        $advanceLookupValue = $advNum;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE bilty_number = :b AND user_id = :u LIMIT 1");
        $stmt->execute(['b' => $advNum, 'u' => $user_id]);
        $brec = $stmt->fetch();
        if (!$brec) {
            $advanceError = 'Bilty number not found.';
            $advanceLookupValue = $advNum;
        } else {
            $advanceBilty = $brec;
            $advanceLookupValue = $brec['bilty_number'];
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(advance_amount), 0) AS paid FROM advances WHERE bilty_number = :b AND user_id = :u");
            $stmt->execute(['b' => $advNum, 'u' => $user_id]);
            $paid = (float)$stmt->fetch()['paid'];
            $oldAmount = 0;
            if ($advanceEditId > 0) {
                $stmt = $pdo->prepare("SELECT advance_amount FROM advances WHERE id = :id AND user_id = :u");
                $stmt->execute(['id' => $advanceEditId, 'u' => $user_id]);
                $oldRec = $stmt->fetch();
                if (!$oldRec) {
                    $advanceError = 'Advance record not found.';
                    $advanceEditId = 0;
                } else {
                    $oldAmount = (float)$oldRec['advance_amount'];
                }
            }
            if (!$advanceError) {
                $remaining = (float)$brec['bilty_freight'] - ($paid - $oldAmount);
                $advAmount = (float)$advAmount;
                if ($advAmount > $remaining) {
                    $advanceError = 'Advance exceeds remaining balance. Remaining: ' . number_format($remaining, 2) . '.';
                } else {
                    try {
                        if ($advanceEditId > 0) {
                            $stmt = $pdo->prepare("UPDATE advances SET adv_date = :d, adv_office = :o, advance_amount = :a WHERE id = :id AND user_id = :u");
                            $stmt->execute(['id' => $advanceEditId, 'u' => $user_id, 'd' => $advDate, 'o' => $advOffice, 'a' => $advAmount]);
                            $advanceSuccess = 'Advance record updated successfully.';
                            $advanceEditId = 0;
                            $advanceEditValues = ['id' => 0, 'adv_date' => '', 'adv_office' => '', 'advance_amount' => ''];
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO advances (user_id, bilty_number, adv_date, adv_office, advance_amount) VALUES (:u, :b, :d, :o, :a)");
                            $stmt->execute(['u' => $user_id, 'b' => $advNum, 'd' => $advDate, 'o' => $advOffice, 'a' => $advAmount]);
                            $advanceSuccess = 'Advance entry saved successfully.';
                        }
                    } catch (PDOException $e) {
                        $advanceError = 'Failed to save advance. Please try again.';
                    }
                }
            }
        }
    }
}

if (isset($_GET['delete_advance'])) {
    $stmt = $pdo->prepare("DELETE FROM advances WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['delete_advance'], 'user_id' => $user_id]);
    $advNum = trim($_GET['b'] ?? '');
    header('Location: dashboard.php?view=advance&b=' . urlencode($advNum));
    exit;
}

if (isset($_GET['edit_advance'])) {
    $stmt = $pdo->prepare("SELECT * FROM advances WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['edit_advance'], 'user_id' => $user_id]);
    $rec = $stmt->fetch();
    if ($rec) {
        $advanceEditId = (int)$rec['id'];
        $advanceEditValues = [
            'id' => $rec['id'],
            'adv_date' => $rec['adv_date'] ?? '',
            'adv_office' => $rec['adv_office'] ?? '',
            'advance_amount' => $rec['advance_amount']
        ];
        $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE bilty_number = :b AND user_id = :u LIMIT 1");
        $stmt->execute(['b' => $rec['bilty_number'], 'u' => $user_id]);
        $brec = $stmt->fetch();
        if ($brec) {
            $advanceBilty = $brec;
            $advanceLookupValue = $brec['bilty_number'];
            $showAdvance = true;
        }
    }
}

if ($advanceBilty) {
    $stmt = $pdo->prepare("SELECT * FROM advances WHERE bilty_number = :b AND user_id = :u ORDER BY created_at DESC");
    $stmt->execute(['b' => $advanceBilty['bilty_number'], 'u' => $user_id]);
    $advanceRecords = $stmt->fetchAll();
    $advanceTotalPaid = 0;
    foreach ($advanceRecords as $ar) {
        $advanceTotalPaid += (float)$ar['advance_amount'];
    }
    $advanceRemaining = (float)$advanceBilty['bilty_freight'] - $advanceTotalPaid;
    $advanceLookupValue = $advanceBilty['bilty_number'];
}

$advanceByBilty = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute(['user_id' => $user_id]);
    $allBilties = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM advances WHERE user_id = :user_id ORDER BY adv_date ASC, created_at ASC");
    $stmt->execute(['user_id' => $user_id]);
    $allAdvances = $stmt->fetchAll();
    $advMap = [];
    foreach ($allAdvances as $a) {
        $advMap[$a['bilty_number']][] = $a;
    }
    foreach ($allBilties as $bl) {
        $advs = $advMap[$bl['bilty_number']] ?? [];
        $paid = 0;
        foreach ($advs as $ad) {
            $paid += (float)$ad['advance_amount'];
        }
        $advanceByBilty[] = [
            'bilty' => $bl,
            'advances' => $advs,
            'paid' => $paid,
            'remaining' => (float)$bl['bilty_freight'] - $paid
        ];
    }
} catch (PDOException $e) {
    $advanceByBilty = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['form']) || $_POST['form'] === 'shipment')) {
    $bilty_number = trim($_POST['bilty_number'] ?? '');
    $bilty_date = !empty($_POST['bilty_date']) ? $_POST['bilty_date'] : null;
    $importer = trim($_POST['importer'] ?? '');
    $exporter = trim($_POST['exporter'] ?? '');
    $sb_number = trim($_POST['sb_number'] ?? '');
    $sb_date = !empty($_POST['sb_date']) ? $_POST['sb_date'] : null;
    $amount = trim($_POST['amount'] ?? '');
    $other = trim($_POST['other'] ?? '');
    $recordId = (int)($_POST['id'] ?? 0);

    if (empty($bilty_number)) {
        $error = 'Bilty number is required.';
    } else {
        $amount = $amount === '' ? 0 : (float)$amount;

        try {
            if ($recordId > 0) {
                $stmt = $pdo->prepare("UPDATE shipments SET bilty_number = :bilty_number, bilty_date = :bilty_date, importer = :importer, exporter = :exporter, sb_number = :sb_number, sb_date = :sb_date, amount = :amount, other = :other WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    'id' => $recordId,
                    'user_id' => $user_id,
                    'bilty_number' => $bilty_number,
                    'bilty_date' => $bilty_date,
                    'importer' => $importer,
                    'exporter' => $exporter,
                    'sb_number' => $sb_number,
                    'sb_date' => $sb_date,
                    'amount' => $amount,
                    'other' => $other
                ]);
                $success = 'Shipment record updated successfully.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO shipments (user_id, bilty_number, bilty_date, importer, exporter, sb_number, sb_date, amount, other) VALUES (:user_id, :bilty_number, :bilty_date, :importer, :exporter, :sb_number, :sb_date, :amount, :other)");
                $stmt->execute([
                    'user_id' => $user_id,
                    'bilty_number' => $bilty_number,
                    'bilty_date' => $bilty_date,
                    'importer' => $importer,
                    'exporter' => $exporter,
                    'sb_number' => $sb_number,
                    'sb_date' => $sb_date,
                    'amount' => $amount,
                    'other' => $other
                ]);
                $success = 'Shipment data saved successfully.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to save data. Please try again.';
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['edit'], 'user_id' => $user_id]);
    $rec = $stmt->fetch();
    if ($rec) {
        $formValues = [
            'id' => $rec['id'],
            'bilty_number' => $rec['bilty_number'],
            'bilty_date' => $rec['bilty_date'] ?? '',
            'importer' => $rec['importer'] ?? '',
            'exporter' => $rec['exporter'] ?? '',
            'sb_number' => $rec['sb_number'] ?? '',
            'sb_date' => $rec['sb_date'] ?? '',
            'amount' => $rec['amount'],
            'other' => $rec['other'] ?? ''
        ];
    }
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(amount), 0) AS total_amount FROM shipments WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $stats = $stmt->fetch();
    $totalShipments = $stats['total'];
    $totalAmount = $stats['total_amount'];

    $totalPages = max(1, (int)ceil($totalShipments / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue('user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recentShipments = $stmt->fetchAll();
} catch (PDOException $e) {
    $totalShipments = 0;
    $totalAmount = 0;
    $totalPages = 1;
    $recentShipments = [];
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(bilty_freight), 0) AS total_freight FROM direct_bilty WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $bStats = $stmt->fetch();
    $totalBilties = $bStats['total'];
    $totalFreight = $bStats['total_freight'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(advance_amount), 0) AS total_advance FROM advances WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $totalAdvance = $stmt->fetch()['total_advance'];

    $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute(['user_id' => $user_id]);
    $recentBilties = $stmt->fetchAll();
} catch (PDOException $e) {
    $totalBilties = 0;
    $totalFreight = 0;
    $totalAdvance = 0;
    $recentBilties = [];
}

$initials = strtoupper(substr($username, 0, 2));
$showShipment = (($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['form']) || $_POST['form'] === 'shipment')) || isset($_GET['edit']));
if (isset($_GET['view']) && $_GET['view'] === 'bilty') {
    $showBilty = true;
}
if (isset($_GET['view']) && $_GET['view'] === 'advance') {
    $showAdvance = true;
    if (!$advanceBilty && !empty($_GET['b'])) {
        $stmt = $pdo->prepare("SELECT * FROM direct_bilty WHERE bilty_number = :b AND user_id = :u LIMIT 1");
        $stmt->execute(['b' => trim($_GET['b']), 'u' => $user_id]);
        $rec = $stmt->fetch();
        if ($rec) {
            $advanceBilty = $rec;
            $stmt = $pdo->prepare("SELECT * FROM advances WHERE bilty_number = :b AND user_id = :u ORDER BY created_at DESC");
            $stmt->execute(['b' => $rec['bilty_number'], 'u' => $user_id]);
            $advanceRecords = $stmt->fetchAll();
            $advanceTotalPaid = 0;
            foreach ($advanceRecords as $ar) {
                $advanceTotalPaid += (float)$ar['advance_amount'];
            }
            $advanceRemaining = (float)$rec['bilty_freight'] - $advanceTotalPaid;
            $advanceLookupValue = $rec['bilty_number'];
        } else {
            $advanceError = 'Bilty number not found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-shell" id="appShell">

        <aside class="sidebar">
            <div class="brand">
                <span class="brand-logo">LS</span>
                <span class="brand-name">Login System</span>
            </div>

            <nav class="nav">
                <a href="javascript:void(0)" id="nav-dashboard" class="nav-link <?= $showShipment ? '' : 'active' ?>" onclick="showDashboard();">
                    <span class="nav-icon">&#9632;</span> Dashboard
                </a>
                <a href="javascript:void(0)" id="nav-shipment" class="nav-link <?= $showShipment ? 'active' : '' ?>" onclick="showShipment();">
                    <span class="nav-icon">&#9998;</span> Shipment Entry
                </a>
                <a href="javascript:void(0)" id="nav-bilty" class="nav-link <?= $showBilty ? 'active' : '' ?>" onclick="showBilty();">
                    <span class="nav-icon">&#128666;</span> Direct Bilty Entry
                </a>
                <a href="javascript:void(0)" id="nav-advance" class="nav-link <?= $showAdvance ? 'active' : '' ?>" onclick="showAdvance();">
                    <span class="nav-icon">&#128176;</span> Advance
                </a>
            </nav>

            <div class="sidebar-foot">
                <a href="logout.php" class="nav-link logout-link">
                    <span class="nav-icon">&#8617;</span> Logout
                </a>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <h2 class="page-title">Dashboard</h2>
                <div class="user-badge">
                    <span class="avatar"><?= htmlspecialchars($initials) ?></span>
                    <span class="user-name"><?= htmlspecialchars($username) ?></span>
                </div>
            </header>

            <div class="content">

                <div id="view-dashboard" class="view" <?= ($showShipment || $showBilty || $showAdvance) ? 'hidden' : '' ?>>
                    <div class="stats-grid">
                        <div class="stat-card card-blue">
                            <span class="stat-label">Total Shipments</span>
                            <span class="stat-value"><?= htmlspecialchars($totalShipments) ?></span>
                        </div>
                        <div class="stat-card card-green">
                            <span class="stat-label">Total Amount</span>
                            <span class="stat-value"><?= htmlspecialchars(number_format($totalAmount, 2)) ?></span>
                        </div>
                        <div class="stat-card card-orange">
                            <span class="stat-label">Pages</span>
                            <span class="stat-value"><?= htmlspecialchars($totalPages) ?></span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Shipments</h3>
                            <a href="shipments.php" target="_blank" rel="noopener" class="btn-view-all">View All</a>
                        </div>

                        <?php if (count($recentShipments) > 0): ?>
                            <div class="table-wrap">
                                <table id="recent-shipment-table">
                                    <thead>
                                        <tr>
                                            <th>Bilty No</th>
                                            <th>Bilty Date</th>
                                            <th>Importer</th>
                                            <th>Exporter</th>
                                            <th>S.B Number</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                        <tr id="filter-row">
                                            <th><input type="text" class="column-search" data-col="0" placeholder="Search..." onkeyup="filterShipments()"></th>
                                            <th><input type="text" class="column-search" data-col="1" placeholder="Search..." onkeyup="filterShipments()"></th>
                                            <th><input type="text" class="column-search" data-col="2" placeholder="Search..." onkeyup="filterShipments()"></th>
                                            <th><input type="text" class="column-search" data-col="3" placeholder="Search..." onkeyup="filterShipments()"></th>
                                            <th><input type="text" class="column-search" data-col="4" placeholder="Search..." onkeyup="filterShipments()"></th>
                                            <th><input type="text" class="column-search" data-col="5" placeholder="Search..." onkeyup="filterShipments()"></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-shipment-body">
                                        <?php foreach ($recentShipments as $s): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($s['bilty_number']) ?></td>
                                                <td><?= htmlspecialchars($s['bilty_date'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($s['importer'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($s['exporter'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($s['sb_number'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars(number_format($s['amount'], 2)) ?></td>
                                                <td class="actions">
                                                    <a href="dashboard.php?edit=<?= $s['id'] ?>" class="btn-edit">Edit</a>
                                                    <a href="dashboard.php?delete=<?= $s['id'] ?>" class="btn-delete" onclick="return confirm('Delete this shipment record?');">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="dashboard.php?page=<?= $page - 1 ?>" class="page-link">&laquo; Prev</a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="dashboard.php?page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="dashboard.php?page=<?= $page + 1 ?>" class="page-link">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No shipments yet. <a href="javascript:void(0)" onclick="showShipment()">Create your first entry</a>.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="view-shipment" class="view" <?= $showShipment ? '' : 'hidden' ?>>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Shipment Entry</h3>
                        </div>

                        <?php if ($error): ?>
                            <div class="error"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($formValues['id']) ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="bilty_number">Bilty Number *</label>
                                    <input type="text" id="bilty_number" name="bilty_number" value="<?= htmlspecialchars($formValues['bilty_number']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="bilty_date">Bilty Date</label>
                                    <input type="date" id="bilty_date" name="bilty_date" value="<?= htmlspecialchars($formValues['bilty_date']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="importer">Importer</label>
                                    <input type="text" id="importer" name="importer" value="<?= htmlspecialchars($formValues['importer']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="exporter">Exporter</label>
                                    <input type="text" id="exporter" name="exporter" value="<?= htmlspecialchars($formValues['exporter']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="sb_number">S.B Number</label>
                                    <input type="text" id="sb_number" name="sb_number" value="<?= htmlspecialchars($formValues['sb_number']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="sb_date">S.B Date</label>
                                    <input type="date" id="sb_date" name="sb_date" value="<?= htmlspecialchars($formValues['sb_date']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                    <input type="number" step="0.01" id="amount" name="amount" value="<?= htmlspecialchars($formValues['amount']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="other">Other</label>
                                    <input type="text" id="other" name="other" value="<?= htmlspecialchars($formValues['other']) ?>">
                                </div>
                            </div>
                            <button type="submit"><?= $formValues['id'] ? 'Update' : 'Submit' ?></button>
                        </form>
                    </div>
                </div>

                <div id="view-bilty" class="view" <?= $showBilty ? '' : 'hidden' ?>>
                    <div class="stats-grid">
                        <div class="stat-card card-blue">
                            <span class="stat-label">Total Bilties</span>
                            <span class="stat-value"><?= htmlspecialchars($totalBilties) ?></span>
                        </div>
                        <div class="stat-card card-green">
                            <span class="stat-label">Total Freight</span>
                            <span class="stat-value"><?= htmlspecialchars(number_format($totalFreight, 2)) ?></span>
                        </div>
                        <div class="stat-card card-orange">
                            <span class="stat-label">Total Advance</span>
                            <span class="stat-value"><?= htmlspecialchars(number_format($totalAdvance, 2)) ?></span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Direct Bilty Entry</h3>
                        </div>

                        <?php if ($biltyError): ?>
                            <div class="error"><?= htmlspecialchars($biltyError) ?></div>
                        <?php endif; ?>
                        <?php if ($biltySuccess): ?>
                            <div class="success"><?= htmlspecialchars($biltySuccess) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="form" value="direct_bilty">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($biltyFormValues['id']) ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="entry_date">Date</label>
                                    <input type="date" id="entry_date" name="entry_date" value="<?= htmlspecialchars($biltyFormValues['entry_date']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="b_number">Bilty Number *</label>
                                    <input type="text" id="b_number" name="bilty_number" value="<?= htmlspecialchars($biltyFormValues['bilty_number']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="truck_number">Truck Number</label>
                                    <input type="text" id="truck_number" name="truck_number" value="<?= htmlspecialchars($biltyFormValues['truck_number']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="from_location">From</label>
                                    <input type="text" id="from_location" name="from_location" value="<?= htmlspecialchars($biltyFormValues['from_location']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="to_location">To</label>
                                    <input type="text" id="to_location" name="to_location" value="<?= htmlspecialchars($biltyFormValues['to_location']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bilty_freight">Bilty Freight</label>
                                    <input type="number" step="0.01" id="bilty_freight" name="bilty_freight" value="<?= htmlspecialchars($biltyFormValues['bilty_freight']) ?>">
                                </div>
                            </div>
                            <button type="submit"><?= $biltyFormValues['id'] ? 'Update' : 'Submit' ?></button>
                        </form>
                    </div>

                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h3 class="card-title">Direct Bilty Records</h3>
                        </div>

                        <?php if (count($recentBilties) > 0): ?>
                            <div class="table-wrap">
                                <table id="recent-bilty-table">
                                    <thead>
                                        <tr>
                                            <th>Bilty No</th>
                                            <th>Date</th>
                                            <th>Truck Number</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Freight</th>
                                            <th>Actions</th>
                                        </tr>
                                        <tr id="bilty-filter-row">
                                            <th><input type="text" class="column-search" data-col="0" placeholder="Search..." onkeyup="filterBilties()"></th>
                                            <th><input type="text" class="column-search" data-col="1" placeholder="Search..." onkeyup="filterBilties()"></th>
                                            <th><input type="text" class="column-search" data-col="2" placeholder="Search..." onkeyup="filterBilties()"></th>
                                            <th><input type="text" class="column-search" data-col="3" placeholder="Search..." onkeyup="filterBilties()"></th>
                                            <th><input type="text" class="column-search" data-col="4" placeholder="Search..." onkeyup="filterBilties()"></th>
                                            <th><input type="text" class="column-search" data-col="5" placeholder="Search..." onkeyup="filterBilties()"></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-bilty-body">
                                        <?php foreach ($recentBilties as $b): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($b['bilty_number']) ?></td>
                                                <td><?= htmlspecialchars($b['entry_date'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($b['truck_number'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($b['from_location'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($b['to_location'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars(number_format($b['bilty_freight'], 2)) ?></td>
                                                <td class="actions">
                                                    <a href="dashboard.php?view=advance&b=<?= urlencode($b['bilty_number']) ?>" class="btn-edit btn-advance">Advance</a>
                                                    <a href="dashboard.php?edit_bilty=<?= $b['id'] ?>&view=bilty" class="btn-edit">Edit</a>
                                                    <a href="dashboard.php?delete_bilty=<?= $b['id'] ?>&view=bilty" class="btn-delete" onclick="return confirm('Delete this direct bilty record?');">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No direct bilty records yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="view-advance" class="view" <?= $showAdvance ? '' : 'hidden' ?>>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Advance Entry</h3>
                        </div>

                        <?php if ($advanceError): ?>
                            <div class="error"><?= htmlspecialchars($advanceError) ?></div>
                        <?php endif; ?>
                        <?php if ($advanceSuccess): ?>
                            <div class="success"><?= htmlspecialchars($advanceSuccess) ?></div>
                        <?php endif; ?>

                        <p class="advance-hint">Enter a bilty number to record an advance against it.</p>

                        <form method="POST" class="lookup-form">
                            <input type="hidden" name="form" value="advance_lookup">
                            <div class="lookup-row">
                                <div class="form-group" style="margin:0; flex:1;">
                                    <input type="text" id="advance_lookup_number" name="advance_lookup_number" value="<?= htmlspecialchars($advanceLookupValue) ?>" placeholder="Bilty Number" required>
                                </div>
                                <button type="submit" class="btn-lookup">Check Bilty</button>
                            </div>
                        </form>
                    </div>

                    <?php if ($advanceBilty): ?>
                        <div class="card" style="margin-top: 24px;">
                            <div class="card-header">
                                <h3 class="card-title">Bilty Details</h3>
                            </div>
                            <div class="bilty-detail-grid">
                                <div class="detail-cell"><span class="detail-label">Bilty No</span><span class="detail-value"><?= htmlspecialchars($advanceBilty['bilty_number']) ?></span></div>
                                <div class="detail-cell"><span class="detail-label">Truck</span><span class="detail-value"><?= htmlspecialchars($advanceBilty['truck_number'] ?: '-') ?></span></div>
                                <div class="detail-cell"><span class="detail-label">Route</span><span class="detail-value"><?= htmlspecialchars(($advanceBilty['from_location'] ?: '-') . ' → ' . ($advanceBilty['to_location'] ?: '-')) ?></span></div>
                                <div class="detail-cell"><span class="detail-label">Freight</span><span class="detail-value"><?= htmlspecialchars(number_format($advanceBilty['bilty_freight'], 2)) ?></span></div>
                                <div class="detail-cell"><span class="detail-label">Advance Paid</span><span class="detail-value"><?= htmlspecialchars(number_format($advanceTotalPaid, 2)) ?></span></div>
                                <div class="detail-cell"><span class="detail-label">Remaining</span><span class="detail-value <?= $advanceRemaining <= 0 ? 'detail-done' : '' ?>"><?= htmlspecialchars(number_format($advanceRemaining, 2)) ?></span></div>
                            </div>
                        </div>

                        <div class="card" style="margin-top: 24px;">
                            <div class="card-header">
                                <h3 class="card-title"><?= $advanceEditId ? 'Edit Advance' : 'Add Advance' ?></h3>
                            </div>
                            <?php if ($advanceEditId <= 0 && $advanceRemaining <= 0): ?>
                                <p class="no-data">This bilty is fully paid. No further advances allowed.</p>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="form" value="advance_entry">
                                    <input type="hidden" name="advance_id" value="<?= htmlspecialchars($advanceEditId) ?>">
                                    <input type="hidden" name="advance_bilty_number" value="<?= htmlspecialchars($advanceBilty['bilty_number']) ?>">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="adv_date">Date</label>
                                            <input type="date" id="adv_date" name="adv_date" value="<?= htmlspecialchars($advanceEditValues['adv_date']) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="adv_office">Advance Office</label>
                                            <input type="text" id="adv_office" name="adv_office" value="<?= htmlspecialchars($advanceEditValues['adv_office']) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="advance_amount">Advance Amount</label>
                                            <input type="number" step="0.01" min="0.01" id="advance_amount" name="advance_amount" value="<?= htmlspecialchars($advanceEditValues['advance_amount']) ?>" placeholder="Max remaining: <?= htmlspecialchars(number_format($advanceRemaining < 0 ? 0 : $advanceRemaining, 2)) ?>" required>
                                        </div>
                                    </div>
                                    <button type="submit"><?= $advanceEditId ? 'Update Advance' : 'Save Advance' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="card" style="margin-top: 24px;">
                            <div class="card-header">
                                <h3 class="card-title">Advance Records for <?= htmlspecialchars($advanceBilty['bilty_number']) ?></h3>
                            </div>
                            <?php if (count($advanceRecords) > 0): ?>
                                <div class="table-wrap">
                                    <table id="advance-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Office</th>
                                                <th>Amount</th>
                                                <th>Actions</th>
                                            </tr>
                                            <tr id="advance-filter-row">
                                                <th><input type="text" class="column-search" data-col="0" placeholder="Search..." onkeyup="filterAdvances()"></th>
                                                <th><input type="text" class="column-search" data-col="1" placeholder="Search..." onkeyup="filterAdvances()"></th>
                                                <th><input type="text" class="column-search" data-col="2" placeholder="Search..." onkeyup="filterAdvances()"></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="advance-body">
                                            <?php foreach ($advanceRecords as $ar): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ar['adv_date'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($ar['adv_office'] ?: '-') ?></td>
                                                    <td><?= htmlspecialchars(number_format($ar['advance_amount'], 2)) ?></td>
                                                    <td class="actions">
                                                        <a href="dashboard.php?view=advance&b=<?= urlencode($advanceBilty['bilty_number']) ?>&edit_advance=<?= $ar['id'] ?>" class="btn-edit">Edit</a>
                                                        <a href="dashboard.php?delete_advance=<?= $ar['id'] ?>&b=<?= urlencode($advanceBilty['bilty_number']) ?>" class="btn-delete" onclick="return confirm('Delete this advance record?');">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No advances recorded for this bilty yet.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h3 class="card-title">Advances by Direct Bilty</h3>
                        </div>
                        <?php if (count($advanceByBilty) > 0): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Bilty No</th>
                                            <th>Truck</th>
                                            <th>Route</th>
                                            <th>Freight</th>
                                            <th>Date</th>
                                            <th>Office</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($advanceByBilty as $grp): $blt = $grp['bilty']; ?>
                                            <?php if (count($grp['advances']) > 0): ?>
                                                <?php foreach ($grp['advances'] as $i => $ar): ?>
                                                    <tr class="<?= $i === 0 ? 'group-first' : '' ?>">
                                                        <?php if ($i === 0): ?>
                                                            <td><?= htmlspecialchars($blt['bilty_number']) ?></td>
                                                            <td><?= htmlspecialchars($blt['truck_number'] ?: '-') ?></td>
                                                            <td><?= htmlspecialchars(($blt['from_location'] ?: '-') . ' → ' . ($blt['to_location'] ?: '-')) ?></td>
                                                            <td><?= htmlspecialchars(number_format($blt['bilty_freight'], 2)) ?></td>
                                                        <?php else: ?>
                                                            <td></td><td></td><td></td><td></td>
                                                        <?php endif; ?>
                                                        <td><?= htmlspecialchars($ar['adv_date'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($ar['adv_office'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars(number_format($ar['advance_amount'], 2)) ?></td>
                                                        <td class="actions">
                                                            <a href="dashboard.php?view=advance&b=<?= urlencode($blt['bilty_number']) ?>&edit_advance=<?= $ar['id'] ?>" class="btn-edit">Edit</a>
                                                            <a href="dashboard.php?view=advance&b=<?= urlencode($blt['bilty_number']) ?>" class="btn-edit btn-advance">Manage</a>
                                                            <a href="dashboard.php?delete_advance=<?= $ar['id'] ?>&b=<?= urlencode($blt['bilty_number']) ?>" class="btn-delete" onclick="return confirm('Delete this advance record?');">Delete</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="group-footer">
                                                    <td colspan="4">Total for <?= htmlspecialchars($blt['bilty_number']) ?></td>
                                                    <td colspan="3">Paid: <?= htmlspecialchars(number_format($grp['paid'], 2)) ?> &nbsp;|&nbsp; Remaining: <?= htmlspecialchars(number_format($grp['remaining'], 2)) ?></td>
                                                    <td></td>
                                                </tr>
                                            <?php else: ?>
                                                <tr class="group-first">
                                                    <td><?= htmlspecialchars($blt['bilty_number']) ?></td>
                                                    <td><?= htmlspecialchars($blt['truck_number'] ?: '-') ?></td>
                                                    <td><?= htmlspecialchars(($blt['from_location'] ?: '-') . ' → ' . ($blt['to_location'] ?: '-')) ?></td>
                                                    <td><?= htmlspecialchars(number_format($blt['bilty_freight'], 2)) ?></td>
                                                    <td colspan="3">No advance yet</td>
                                                    <td class="actions">
                                                        <a href="dashboard.php?view=advance&b=<?= urlencode($blt['bilty_number']) ?>" class="btn-edit btn-advance">Manage</a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No direct bilty records found.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script>
        function showDashboard() {
            document.getElementById('view-dashboard').hidden = false;
            document.getElementById('view-shipment').hidden = true;
            document.getElementById('view-bilty').hidden = true;
            document.getElementById('view-advance').hidden = true;
            document.getElementById('nav-dashboard').classList.add('active');
            document.getElementById('nav-shipment').classList.remove('active');
            document.getElementById('nav-bilty').classList.remove('active');
            document.getElementById('nav-advance').classList.remove('active');
        }

        function showShipment() {
            document.getElementById('view-dashboard').hidden = true;
            document.getElementById('view-shipment').hidden = false;
            document.getElementById('view-bilty').hidden = true;
            document.getElementById('view-advance').hidden = true;
            document.getElementById('nav-dashboard').classList.remove('active');
            document.getElementById('nav-shipment').classList.add('active');
            document.getElementById('nav-bilty').classList.remove('active');
            document.getElementById('nav-advance').classList.remove('active');
        }

        function showBilty() {
            document.getElementById('view-dashboard').hidden = true;
            document.getElementById('view-shipment').hidden = true;
            document.getElementById('view-bilty').hidden = false;
            document.getElementById('view-advance').hidden = true;
            document.getElementById('nav-dashboard').classList.remove('active');
            document.getElementById('nav-shipment').classList.remove('active');
            document.getElementById('nav-bilty').classList.add('active');
            document.getElementById('nav-advance').classList.remove('active');
        }

        function showAdvance() {
            document.getElementById('view-dashboard').hidden = true;
            document.getElementById('view-shipment').hidden = true;
            document.getElementById('view-bilty').hidden = true;
            document.getElementById('view-advance').hidden = false;
            document.getElementById('nav-dashboard').classList.remove('active');
            document.getElementById('nav-shipment').classList.remove('active');
            document.getElementById('nav-bilty').classList.remove('active');
            document.getElementById('nav-advance').classList.add('active');
        }

        function filterShipments() {
            const inputs = document.querySelectorAll('#filter-row .column-search');
            const rows = document.getElementById('recent-shipment-body').getElementsByTagName('tr');
            for (const row of rows) {
                let show = true;
                for (const input of inputs) {
                    const query = input.value.toLowerCase().trim();
                    if (query !== '') {
                        const cell = row.cells[Number(input.dataset.col)];
                        if (!cell || !cell.textContent.toLowerCase().includes(query)) {
                            show = false;
                            break;
                        }
                    }
                }
                row.style.display = show ? '' : 'none';
            }
        }

        function filterBilties() {
            const inputs = document.querySelectorAll('#bilty-filter-row .column-search');
            const rows = document.getElementById('recent-bilty-body').getElementsByTagName('tr');
            for (const row of rows) {
                let show = true;
                for (const input of inputs) {
                    const query = input.value.toLowerCase().trim();
                    if (query !== '') {
                        const cell = row.cells[Number(input.dataset.col)];
                        if (!cell || !cell.textContent.toLowerCase().includes(query)) {
                            show = false;
                            break;
                        }
                    }
                }
                row.style.display = show ? '' : 'none';
            }
        }

        function filterAdvances() {
            const inputs = document.querySelectorAll('#advance-filter-row .column-search');
            const rows = document.getElementById('advance-body').getElementsByTagName('tr');
            for (const row of rows) {
                let show = true;
                for (const input of inputs) {
                    const query = input.value.toLowerCase().trim();
                    if (query !== '') {
                        const cell = row.cells[Number(input.dataset.col)];
                        if (!cell || !cell.textContent.toLowerCase().includes(query)) {
                            show = false;
                            break;
                        }
                    }
                }
                row.style.display = show ? '' : 'none';
            }
        }
    </script>
</body>
</html>
