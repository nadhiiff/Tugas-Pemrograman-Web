<?php

declare(strict_types=1);

require_once __DIR__ . '/Transaction.php';

session_start();

$_SESSION['balance'] ??= 0.0;
$_SESSION['history'] ??= [];

// Token CSRF disimpan di sesi
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** cegah XSS */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $type = (string) ($_POST['type'] ?? '');
    $rawAmount = trim((string) ($_POST['amount'] ?? ''));

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Token CSRF tidak valid. Permintaan ditolak.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $rawAmount) || (float) $rawAmount <= 0) {
        // cuma angka desimal positif (maks. 2 digit di belakang titik)
        $error = 'Jumlah harus berupa angka desimal positif, contoh: 150000 atau 25.50.';
    } else {
        // cocokkan jenis transaksi dengan match
        $validType = match ($type) {
            'deposit'  => 'deposit',
            'withdraw' => 'withdraw',
            default    => null,
        };

        if ($validType === null) {
            $error = 'Jenis transaksi tidak dikenali.';
        } else {
            try {
                $transaction = new Transaction(
                    bin2hex(random_bytes(4)),
                    $validType,
                    (float) $rawAmount
                );
                $transaction->process();

                $_SESSION['history'][] = [
                    'id'     => $transaction->getId(),
                    'type'   => $transaction->getType(),
                    'amount' => $transaction->getAmount(),
                ];

                // Rotasi token setelah aksi berhasil
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $success = 'Transaksi berhasil diproses.';
            } catch (InvalidArgumentException $ex) {
                $error = $ex->getMessage();
            }
        }
    }
}

$balance = (float) $_SESSION['balance'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Manajemen Keuangan Sederhana</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        .msg-error { color: #b00020; }
        .msg-ok { color: #1b7f3b; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; }
    </style>
</head>
<body>
    <h1>Manajemen Keuangan Sederhana</h1>
    <h2>Saldo: Rp <?= e(number_format($balance, 2, ',', '.')) ?></h2>

    <?php if ($error !== ''): ?>
        <p class="msg-error"><?= e($error) ?></p>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <p class="msg-ok"><?= e($success) ?></p>
    <?php endif; ?>

    <form method="post" action="finance.php">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

        <label for="type">Jenis</label>
        <select name="type" id="type" required>
            <option value="deposit">Deposit</option>
            <option value="withdraw">Penarikan</option>
        </select>

        <label for="amount">Jumlah</label>
        <input type="text" name="amount" id="amount" inputmode="decimal" required>

        <button type="submit">Proses</button>
    </form>

    <h3>Riwayat Transaksi</h3>
    <?php if ($_SESSION['history'] === []): ?>
        <p>Belum ada transaksi.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Jenis</th><th>Jumlah</th></tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($_SESSION['history']) as $item): ?>
                    <tr>
                        <td><?= e((string) $item['id']) ?></td>
                        <td><?= e((string) $item['type']) ?></td>
                        <td>Rp <?= e(number_format((float) $item['amount'], 2, ',', '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>