<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bilty_number = trim($_POST['bilty_number'] ?? '');
    $bilty_date = !empty($_POST['bilty_date']) ? $_POST['bilty_date'] : null;
    $importer = trim($_POST['importer'] ?? '');
    $exporter = trim($_POST['exporter'] ?? '');
    $sb_number = trim($_POST['sb_number'] ?? '');
    $sb_date = !empty($_POST['sb_date']) ? $_POST['sb_date'] : null;
    $amount = trim($_POST['amount'] ?? '');
    $other = trim($_POST['other'] ?? '');

    if (empty($bilty_number)) {
        $error = 'Bilty number is required.';
    } else {
        $amount = $amount === '' ? 0 : (float)$amount;

        try {
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
        } catch (PDOException $e) {
            $error = 'Failed to save data. Please try again.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM shipments WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => $user_id]);
$shipments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment Entry</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-wide">
        <div class="dashboard">
            <h1>Shipment Data Entry</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="container-inner">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="bilty_number">Bilty Number *</label>
                        <input type="text" id="bilty_number" name="bilty_number" required>
                    </div>
                    <div class="form-group">
                        <label for="bilty_date">Bilty Date</label>
                        <input type="date" id="bilty_date" name="bilty_date">
                    </div>
                    <div class="form-group">
                        <label for="importer">Importer</label>
                        <input type="text" id="importer" name="importer">
                    </div>
                    <div class="form-group">
                        <label for="exporter">Exporter</label>
                        <input type="text" id="exporter" name="exporter">
                    </div>
                    <div class="form-group">
                        <label for="sb_number">S.B Number</label>
                        <input type="text" id="sb_number" name="sb_number">
                    </div>
                    <div class="form-group">
                        <label for="sb_date">S.B Date</label>
                        <input type="date" id="sb_date" name="sb_date">
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" step="0.01" id="amount" name="amount">
                    </div>
                    <div class="form-group">
                        <label for="other">Other</label>
                        <input type="text" id="other" name="other">
                    </div>
                </div>
                <button type="submit">Submit</button>
            </form>
        </div>

        <h2 class="section-title">Submitted Shipments</h2>
        <?php if (count($shipments) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Bilty No</th>
                            <th>Bilty Date</th>
                            <th>Importer</th>
                            <th>Exporter</th>
                            <th>S.B Number</th>
                            <th>S.B Date</th>
                            <th>Amount</th>
                            <th>Other</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shipments as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['bilty_number']) ?></td>
                                <td><?= htmlspecialchars($s['bilty_date'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['importer'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['exporter'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['sb_number'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['sb_date'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['amount'] !== null ? number_format($s['amount'], 2) : '0.00') ?></td>
                                <td><?= htmlspecialchars($s['other'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-data">No shipments submitted yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
