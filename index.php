<?php
session_start();
include 'db.php';

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// PROSES PADAM ITEM DAN SEMUA REKOD TRANSAKSINYA
if (isset($_GET['delete_item_id'])) {
    $delete_item_id = (int)$_GET['delete_item_id'];

    if ($delete_item_id > 0) {
        $conn->query("DELETE FROM stock_transactions WHERE item_id = $delete_item_id");
        $conn->query("DELETE FROM items WHERE id = $delete_item_id");
    }

    header("Location: index.php");
    exit();
}

// Ambil senarai semua item untuk dropdown
$all_items = $conn->query("SELECT id, item_name, ref_number FROM items ORDER BY item_name ASC");

// Tentukan item yang dipilih
$selected_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($selected_item_id == 0 && $all_items->num_rows > 0) {
    $first_item = $all_items->fetch_assoc();
    $selected_item_id = $first_item['id'];
    $all_items->data_seek(0);
}

// PROSES PADAM REKOD & KIRA SEMULA BAKI
if (isset($_GET['delete_id']) && isset($_GET['item_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $item_id = (int)$_GET['item_id'];

    // Padam rekod pilihan
    $conn->query("DELETE FROM stock_transactions WHERE id = $delete_id");

    // Kira semula Baki (Balance) untuk semua rekod item ini dari awal
    $all_trans = $conn->query("SELECT id, stock_in_qty, stock_out_qty FROM stock_transactions WHERE item_id = $item_id ORDER BY id ASC");
    $running_balance = 0;

    while ($row = $all_trans->fetch_assoc()) {
        $running_balance = $running_balance + $row['stock_in_qty'] - $row['stock_out_qty'];
        $conn->query("UPDATE stock_transactions SET balance = $running_balance WHERE id = {$row['id']}");
    }

    header("Location: index.php?item_id=" . $item_id);
    exit();
}

// PROSES SIMPAN TRANSAKSI STOK BAHARU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_transaction'])) {
    $item_id = (int)$_POST['item_id'];
    $lot_no = $_POST['lot_no'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    $stock_in_qty = (int)$_POST['stock_in_qty'];
    $date_received = !empty($_POST['date_received']) ? $_POST['date_received'] : NULL;
    $stock_out_qty = (int)$_POST['stock_out_qty'];
    $date_out = !empty($_POST['date_out']) ? $_POST['date_out'] : NULL;
    $initials = $_POST['initials'];
    $acc_test = $_POST['acceptance_test_performed'];
    $acc_date = !empty($_POST['acceptance_test_date']) ? $_POST['acceptance_test_date'] : NULL;

    // Ambil baki terakhir
    $res = $conn->query("SELECT balance FROM stock_transactions WHERE item_id = $item_id ORDER BY id DESC LIMIT 1");
    $last_balance = ($res->num_rows > 0) ? $res->fetch_assoc()['balance'] : 0;
    
    $new_balance = $last_balance + $stock_in_qty - $stock_out_qty;

    $stmt = $conn->prepare("INSERT INTO stock_transactions (item_id, lot_no, expiry_date, stock_in_qty, date_received, stock_out_qty, date_out, balance, initials, acceptance_test_performed, acceptance_test_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issisisisss", $item_id, $lot_no, $expiry_date, $stock_in_qty, $date_received, $stock_out_qty, $date_out, $new_balance, $initials, $acc_test, $acc_date);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Rekod transaksi stok berjaya disimpan.';
    } else {
        $_SESSION['success_message'] = 'Rekod tidak berjaya disimpan: ' . $stmt->error;
    }

    header("Location: index.php?item_id=" . $item_id);
    exit();
}

// Ambil maklumat item yang dipilih
$item = null;
$latest_balance = 0;
$par_warning = false;
$expiry_warnings = [];
$dashboard_expiry_warnings = [];
$dashboard_par_warnings = [];
$today = new DateTimeImmutable('today');

$dashboard_items = $conn->query("SELECT items.id, items.item_name, items.ref_number, items.par_level, COALESCE((SELECT balance FROM stock_transactions WHERE stock_transactions.item_id = items.id ORDER BY stock_transactions.id DESC LIMIT 1), 0) AS current_balance FROM items ORDER BY items.item_name ASC");
if ($dashboard_items) {
    while ($dashboard_item = $dashboard_items->fetch_assoc()) {
        if ((int)$dashboard_item['current_balance'] <= (int)$dashboard_item['par_level']) {
            $dashboard_par_warnings[] = $dashboard_item;
        }
    }
}

$dashboard_expiry_records = $conn->query("SELECT stock_transactions.lot_no, stock_transactions.expiry_date, items.id AS item_id, items.item_name, items.ref_number FROM stock_transactions INNER JOIN items ON items.id = stock_transactions.item_id WHERE stock_transactions.expiry_date IS NOT NULL ORDER BY stock_transactions.expiry_date ASC");
if ($dashboard_expiry_records) {
    while ($dashboard_expiry_record = $dashboard_expiry_records->fetch_assoc()) {
        $dashboard_expiry_date = DateTimeImmutable::createFromFormat('Y-m-d', $dashboard_expiry_record['expiry_date']);
        if (!$dashboard_expiry_date) {
            continue;
        }

        $dashboard_days_until_expiry = (int)$today->diff($dashboard_expiry_date)->format('%r%a');
        if ($dashboard_days_until_expiry <= 10) {
            $dashboard_expiry_record['days_until_expiry'] = $dashboard_days_until_expiry;
            $dashboard_expiry_warnings[] = $dashboard_expiry_record;
        }
    }
}

if ($selected_item_id > 0) {
    $item = $conn->query("SELECT * FROM items WHERE id = $selected_item_id")->fetch_assoc();

    if ($item) {
        $latest_balance_result = $conn->query("SELECT balance FROM stock_transactions WHERE item_id = $selected_item_id ORDER BY id DESC LIMIT 1");
        if ($latest_balance_result && $latest_balance_result->num_rows > 0) {
            $latest_balance = (int)$latest_balance_result->fetch_assoc()['balance'];
        }

        $par_level = (int)$item['par_level'];
        $par_warning = $latest_balance <= $par_level;

        $expiry_records = $conn->query("SELECT lot_no, expiry_date FROM stock_transactions WHERE item_id = $selected_item_id AND expiry_date IS NOT NULL ORDER BY expiry_date ASC");
        while ($expiry_record = $expiry_records->fetch_assoc()) {
            $expiry_date = DateTimeImmutable::createFromFormat('Y-m-d', $expiry_record['expiry_date']);
            if (!$expiry_date) {
                continue;
            }

            $days_until_expiry = (int)$today->diff($expiry_date)->format('%r%a');
            if ($days_until_expiry <= 10) {
                $expiry_warnings[] = [
                    'lot_no' => $expiry_record['lot_no'],
                    'expiry_date' => $expiry_record['expiry_date'],
                    'days_until_expiry' => $days_until_expiry
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Sistem Bin Card SGSB-F26a</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background-color: #f4f6f9; color: #333; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 20px; }
        
        .header-container { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; border-bottom: 2px solid #003366; padding-bottom: 12px; }
        .header-logo { height: 55px; width: auto; object-fit: contain; }
        .header-text h2 { margin: 0; font-size: 18px; color: #003366; text-transform: uppercase; }
        .header-text h3 { margin: 4px 0 0 0; font-size: 13px; color: #555; font-weight: 600; }
        .active-item-title { margin: 0 0 15px; padding: 12px 16px; background: #003366; color: white; border-radius: 6px; font-size: 18px; font-weight: 700; }
        .active-item-title span { display: block; margin-top: 4px; color: #d9e8f5; font-size: 12px; font-weight: 400; }
        .dashboard { margin-bottom: 20px; }
        .dashboard h2 { margin: 0 0 12px; color: #003366; font-size: 20px; }
        .dashboard-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
        .dashboard-stat { padding: 14px; border-radius: 6px; color: white; }
        .dashboard-stat strong { display: block; font-size: 24px; }
        .dashboard-stat span { font-size: 12px; }
        .dashboard-total { background: #003366; }
        .dashboard-par { background: #b22222; }
        .dashboard-expiry { background: #e67e22; }
        .dashboard-content { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .dashboard-panel { padding: 14px; border: 1px solid #d7dee5; border-radius: 6px; background: white; }
        .dashboard-panel h3 { margin: 0 0 10px; font-size: 14px; color: #003366; }
        .dashboard-panel ul { margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.7; }
        .dashboard-panel li a { color: #003366; font-weight: bold; text-decoration: none; }
        .dashboard-empty { color: #1f7a1f; font-size: 12px; }

        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; background: #eef2f5; padding: 15px; border-radius: 6px; font-size: 13px; }
        .grid div { background: white; padding: 8px 12px; border-radius: 4px; border: 1px solid #e0e0e0; }
        
        .selector-box { background: #d9e2ec; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
        .transaction-section { border: 1px solid #d7dee5; border-radius: 6px; padding: 12px; margin-bottom: 12px; }
        .transaction-section h4 { margin: 0 0 10px; font-size: 13px; }
        .stock-in-section { background: #f2fbf4; border-left: 5px solid #28a745; }
        .stock-in-section h4 { color: #1f7a1f; }
        .stock-out-section { background: #fff7f2; border-left: 5px solid #e67e22; }
        .stock-out-section h4 { color: #a6530b; }
        .transaction-details { background: #f7f8fa; border-left: 5px solid #003366; }
        .transaction-details h4 { color: #003366; }
        .form-group { margin-bottom: 5px; }
        label { display: block; font-size: 11px; font-weight: bold; margin-bottom: 4px; color: #444; }
        input, select { width: 100%; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 12px; }
        
        .btn { background-color: #28a745; color: white; border: none; padding: 9px 18px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; text-decoration: none; }
        .btn-blue { background-color: #003366; }
        .btn-danger { background-color: #dc3545; padding: 4px 8px; font-size: 11px; border-radius: 3px; }
        .btn:hover { opacity: 0.9; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; font-size: 12px; }
        th { background-color: #003366; color: white; font-weight: 600; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>

<div class="card dashboard">
    <h2>Dashboard Stok</h2>
    <div class="dashboard-summary">
        <div class="dashboard-stat dashboard-total">
            <strong><?= $all_items->num_rows ?></strong>
            <span>Jumlah Item</span>
        </div>
        <div class="dashboard-stat dashboard-par">
            <strong><?= count($dashboard_par_warnings) ?></strong>
            <span>Item Pada / Bawah Tahap PAR</span>
        </div>
        <div class="dashboard-stat dashboard-expiry">
            <strong><?= count($dashboard_expiry_warnings) ?></strong>
            <span>Lot Luput Dalam 10 Hari / Sudah Luput</span>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="dashboard-panel">
            <h3>⚠️ Amaran Tarikh Luput</h3>
            <?php if ($dashboard_expiry_warnings): ?>
                <ul>
                    <?php foreach ($dashboard_expiry_warnings as $dashboard_expiry_warning): ?>
                        <li>
                            <a href="index.php?item_id=<?= $dashboard_expiry_warning['item_id'] ?>">
                                <?= htmlspecialchars($dashboard_expiry_warning['item_name']) ?>
                            </a>
                            - Lot <?= htmlspecialchars($dashboard_expiry_warning['lot_no']) ?>:
                            <?php if ($dashboard_expiry_warning['days_until_expiry'] < 0): ?>
                                sudah luput (<?= htmlspecialchars($dashboard_expiry_warning['expiry_date']) ?>)
                            <?php elseif ($dashboard_expiry_warning['days_until_expiry'] === 0): ?>
                                luput hari ini
                            <?php else: ?>
                                luput dalam <?= $dashboard_expiry_warning['days_until_expiry'] ?> hari
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dashboard-empty">Tiada amaran tarikh luput.</div>
            <?php endif; ?>
        </div>

        <div class="dashboard-panel">
            <h3>⚠️ Item Perlu Pesan Stok</h3>
            <?php if ($dashboard_par_warnings): ?>
                <ul>
                    <?php foreach ($dashboard_par_warnings as $dashboard_par_warning): ?>
                        <li>
                            <a href="index.php?item_id=<?= $dashboard_par_warning['id'] ?>">
                                <?= htmlspecialchars($dashboard_par_warning['item_name']) ?>
                            </a>
                            - baki <?= (int)$dashboard_par_warning['current_balance'] ?> / Tahap PAR <?= (int)$dashboard_par_warning['par_level'] ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dashboard-empty">Tiada item di bawah Tahap PAR.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bar Pilihan Item -->
<div class="selector-box">
    <label style="font-size: 13px; margin: 0;">PILIH ITEM / BAHAN:</label>
    <select onchange="location = this.value;" style="width: 320px;">
        <?php if ($all_items->num_rows == 0): ?>
            <option>-- Tiada Item Didaftarkan --</option>
        <?php else: ?>
            <?php while ($row = $all_items->fetch_assoc()): ?>
                <option value="index.php?item_id=<?= $row['id'] ?>" <?= ($row['id'] == $selected_item_id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['item_name']) ?> (<?= htmlspecialchars($row['ref_number']) ?>)
                </option>
            <?php endwhile; ?>
        <?php endif; ?>
    </select>
    <a href="add_item.php" class="btn btn-blue">+ Tambah Item Baharu</a>
    <?php if ($item): ?>
        <a href="index.php?delete_item_id=<?= $item['id'] ?>" class="btn btn-danger" style="padding: 9px 12px; font-size: 13px;" onclick="return confirm('AMARAN: Padam item <?= htmlspecialchars(addslashes($item['item_name']), ENT_QUOTES) ?> dan semua rekod transaksinya? Tindakan ini tidak boleh dibuat semula.');">Padam Item</a>
    <?php endif; ?>
<!-- Butang Export semua item -->
<a href="export_excel.php" 
   class="btn btn-success mb-3" style="background-color: #198754; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px;">
    📊 Eksport Semua Item
</a>
</div>

<?php if ($item): ?>
<!-- Header Kad Stok -->
<div class="card">
    <div class="header-container">
        <img src="logo.png" alt="Selcare Logo" class="header-logo" onerror="this.style.display='none'">
        <div class="header-text">
            <h2>SELCARE DIAGNOSTICS SDN BHD</h2>
            <h3>REAGENT & CONSUMABLES BIN CARD (SGSB-F26a)</h3>
        </div>
    </div>

    <div class="active-item-title">
        ITEM SEDANG DIBUKA: <?= htmlspecialchars($item['item_name']) ?>
        <span>Nombor Rujukan: <?= htmlspecialchars($item['ref_number']) ?></span>
    </div>

    <div class="grid">
        <div><strong>Nama Item:</strong><br><?= htmlspecialchars($item['item_name']) ?></div>
        <div><strong>Nombor Rujukan:</strong><br><?= htmlspecialchars($item['ref_number']) ?></div>
        <div><strong>Unit Ukuran:</strong><br><?= htmlspecialchars($item['uom']) ?></div>
        <div><strong>Cawangan Makmal:</strong><br><?= htmlspecialchars($item['lab_branch']) ?></div>
        <div><strong>Tahap Minimum:</strong> <?= $item['min_level'] ?></div>
        <div><strong>Tahap Maksimum:</strong> <?= $item['max_level'] ?></div>
        <div><strong>Tahap PAR:</strong> <?= $item['par_level'] ?></div>
        <div><strong>Baki Semasa:</strong><br><?= $latest_balance ?></div>
        <div><strong>Lokasi:</strong><br><?= htmlspecialchars($item['location']) ?></div>
        <div>
            <strong>Status PAR:</strong><br>
            <?php if ($par_warning): ?>
                <span style="color: #b22222; font-weight: bold;">⚠️ AMARAN TAHAP PAR</span>
            <?php else: ?>
                <span style="color: #1f7a1f; font-weight: bold;">✅ Dalam keadaan baik</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($expiry_warnings): ?>
        <div style="margin-top: 15px; padding: 12px 15px; border-left: 6px solid #e67e22; background: #fff7e6; color: #8a4b08; border-radius: 6px;">
            <strong>⚠️ AMARAN TARIKH LUPUT</strong>
            <div style="margin-top: 6px; font-weight: normal;">
                <?php foreach ($expiry_warnings as $expiry_warning): ?>
                    <?php if ($expiry_warning['days_until_expiry'] < 0): ?>
                        <div>Lot <strong><?= htmlspecialchars($expiry_warning['lot_no']) ?></strong> telah luput pada <?= htmlspecialchars($expiry_warning['expiry_date']) ?>.</div>
                    <?php elseif ($expiry_warning['days_until_expiry'] === 0): ?>
                        <div>Lot <strong><?= htmlspecialchars($expiry_warning['lot_no']) ?></strong> luput hari ini (<?= htmlspecialchars($expiry_warning['expiry_date']) ?>).</div>
                    <?php else: ?>
                        <div>Lot <strong><?= htmlspecialchars($expiry_warning['lot_no']) ?></strong> akan luput dalam <strong><?= $expiry_warning['days_until_expiry'] ?> hari</strong> (<?= htmlspecialchars($expiry_warning['expiry_date']) ?>). Sila rancang order stok.</div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($par_warning): ?>
        <div style="margin-top: 15px; padding: 12px 15px; border-left: 6px solid #d9534f; background: #fff3f3; color: #7a1f1f; border-radius: 6px; font-weight: bold;">
            AMARAN TAHAP PAR: Baki semasa item ini ialah <?= $latest_balance ?> dan berada pada atau di bawah Tahap PAR <?= $item['par_level'] ?>.
        </div>
    <?php endif; ?>
</div>

<!-- Borang Transaksi Stok -->
<div class="card">
    <h3 style="color: #003366; margin-top: 0; font-size: 15px;">Tambah Transaksi Stok</h3>
    <form method="POST">
        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
        <div class="transaction-section transaction-details">
            <h4>MAKLUMAT TRANSAKSI</h4>
            <div class="form-grid">
                <div class="form-group"><label>Nombor Lot:</label><input type="text" name="lot_no" required placeholder="Masukkan nombor lot"></div>
                <div class="form-group"><label>Tarikh Luput:</label><input type="date" name="expiry_date"></div>
            </div>
        </div>

        <div class="transaction-section stock-in-section">
            <h4>STOK MASUK</h4>
            <div class="form-grid">
                <div class="form-group"><label>Kuantiti Stok Masuk:</label><input type="number" name="stock_in_qty" value="" placeholder="Masukkan kuantiti masuk" data-default-value="0"></div>
                <div class="form-group"><label>Tarikh Diterima:</label><input type="date" name="date_received"></div>
            </div>
        </div>

        <div class="transaction-section stock-out-section">
            <h4>STOK KELUAR</h4>
            <div class="form-grid">
                <div class="form-group"><label>Kuantiti Stok Keluar:</label><input type="number" name="stock_out_qty" value="" placeholder="Masukkan kuantiti keluar" data-default-value="0"></div>
                <div class="form-group"><label>Tarikh Dikeluarkan:</label><input type="date" name="date_out"></div>
            </div>
        </div>

        <div class="transaction-section transaction-details">
            <h4>PENGESAHAN TRANSAKSI</h4>
            <div class="form-grid">
            <div class="form-group"><label>Inisial:</label><input type="text" name="initials" required placeholder="Masukkan inisial"></div>
            <div class="form-group">
                <label>Ujian Penerimaan?</label>
                <select name="acceptance_test_performed">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable" selected>Tidak Berkenaan</option>
                </select>
            </div>
            <div class="form-group"><label>Tarikh Ujian Penerimaan:</label><input type="date" name="acceptance_test_date"></div>
            </div>
        </div>
        <br>
        <button type="submit" name="add_transaction" class="btn">Simpan Rekod</button>
    </form>
</div>

<!-- Jadual Rekod -->
<div class="card">
    <h3 style="color: #003366; margin-top: 0; font-size: 15px;">Rekod Stok Bin Card</h3>
    <table>
        <thead>
            <tr>
                <th rowspan="2">BIL.</th>
                <th rowspan="2">NOMBOR LOT</th>
                <th colspan="2">STOK MASUK</th>
                <th colspan="2">STOK KELUAR</th>
                <th>BAKI</th>
                <th rowspan="2">INISIAL</th>
                <th colspan="2">STATUS UJIAN PENERIMAAN</th>
                <th rowspan="2">TINDAKAN</th>
            </tr>
            <tr>
                <th>Kuantiti</th>
                <th>Tarikh Diterima</th>
                <th>Kuantiti</th>
                <th>Tarikh Dikeluarkan</th>
                <th>Kuantiti</th>
                <th>Dilaksanakan?</th>
                <th>Tarikh</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $records = $conn->query("SELECT * FROM stock_transactions WHERE item_id = {$item['id']} ORDER BY id ASC");
            $no = 1;
            while ($row = $records->fetch_assoc()) {
                echo "<tr>
                    <td>{$no}</td>
                    <td>" . htmlspecialchars($row['lot_no']) . "</td>
                    <td>{$row['stock_in_qty']}</td>
                    <td>{$row['date_received']}</td>
                    <td>{$row['stock_out_qty']}</td>
                    <td>{$row['date_out']}</td>
                    <td><strong>{$row['balance']}</strong></td>
                    <td>" . htmlspecialchars($row['initials']) . "</td>
                    <td>{$row['acceptance_test_performed']}</td>
                    <td>{$row['acceptance_test_date']}</td>
                    <td>
                        <a href='index.php?item_id={$item['id']}&delete_id={$row['id']}' class='btn btn-danger' onclick='return confirm(\"Adakah anda pasti mahu padam rekod ini?\");'>Padam</a>
                    </td>
                </tr>";
                $no++;
            }
            ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="card">
        <p>Sila klik butang <strong>+ Tambah Item Baharu</strong> untuk mendaftarkan item pertama anda.</p>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            title: <?= json_encode(str_starts_with($success_message, 'Rekod transaksi stok berjaya') ? 'Berjaya!' : 'Ralat') ?>,
            text: <?= json_encode($success_message) ?>,
            icon: <?= json_encode(str_starts_with($success_message, 'Rekod transaksi stok berjaya') ? 'success' : 'error') ?>,
            confirmButtonText: 'OK',
            confirmButtonColor: '#003366'
        });
    });
</script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-default-value]').forEach(function (field) {
            const defaultValue = field.dataset.defaultValue;

            field.addEventListener('focus', function () {
                if (this.value === defaultValue || this.value === '') {
                    this.value = '';
                }
            });
        });
    });
</script>
</body>
</html>