<?php
include 'db.php';

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
    $stmt->execute();
    
    header("Location: index.php?item_id=" . $item_id);
    exit();
}

// Ambil maklumat item yang dipilih
$item = null;
if ($selected_item_id > 0) {
    $item = $conn->query("SELECT * FROM items WHERE id = $selected_item_id")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Sistem Bin Card SGSB-F26a</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background-color: #f4f6f9; color: #333; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 20px; }
        
        .header-container { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; border-bottom: 2px solid #003366; padding-bottom: 12px; }
        .header-logo { height: 55px; width: auto; object-fit: contain; }
        .header-text h2 { margin: 0; font-size: 18px; color: #003366; text-transform: uppercase; }
        .header-text h3 { margin: 4px 0 0 0; font-size: 13px; color: #555; font-weight: 600; }

        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; background: #eef2f5; padding: 15px; border-radius: 6px; font-size: 13px; }
        .grid div { background: white; padding: 8px 12px; border-radius: 4px; border: 1px solid #e0e0e0; }
        
        .selector-box { background: #d9e2ec; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
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
<!-- Butang Export to Excel -->
<a href="export_excel.php<?= isset($_GET['item_id']) ? '?item_id='.$_GET['item_id'] : ''; ?>" 
   class="btn btn-success mb-3" style="background-color: #198754; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px;">
   📊 Eksport ke Excel
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

    <div class="grid">
        <div><strong>Name of Item:</strong><br><?= htmlspecialchars($item['item_name']) ?></div>
        <div><strong>Ref Number:</strong><br><?= htmlspecialchars($item['ref_number']) ?></div>
        <div><strong>UOM:</strong><br><?= htmlspecialchars($item['uom']) ?></div>
        <div><strong>Lab Branch:</strong><br><?= htmlspecialchars($item['lab_branch']) ?></div>
        <div><strong>Min Level:</strong> <?= $item['min_level'] ?></div>
        <div><strong>Max Level:</strong> <?= $item['max_level'] ?></div>
        <div><strong>PAR Level:</strong> <?= $item['par_level'] ?></div>
        <div><strong>Location:</strong><br><?= htmlspecialchars($item['location']) ?></div>
    </div>
</div>

<!-- Borang Transaksi Stok -->
<div class="card">
    <h3 style="color: #003366; margin-top: 0; font-size: 15px;">Tambah Transaksi Stok</h3>
    <form method="POST">
        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
        <div class="form-grid">
            <div class="form-group"><label>Lot No:</label><input type="text" name="lot_no" required></div>
            <div class="form-group"><label>Tarikh Luput:</label><input type="date" name="expiry_date"></div>
            <div class="form-group"><label>Stock In (Qty):</label><input type="number" name="stock_in_qty" value="0"></div>
            <div class="form-group"><label>Tarikh Terima:</label><input type="date" name="date_received"></div>
            <div class="form-group"><label>Stock Out (Qty):</label><input type="number" name="stock_out_qty" value="0"></div>
            <div class="form-group"><label>Tarikh Keluar:</label><input type="date" name="date_out"></div>
            <div class="form-group"><label>Initials:</label><input type="text" name="initials" required></div>
            <div class="form-group">
                <label>Ujian Penerimaan?</label>
                <select name="acceptance_test_performed">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                    <option value="Not Applicable" selected>Not Applicable</option>
                </select>
            </div>
            <div class="form-group"><label>Tarikh Ujian:</label><input type="date" name="acceptance_test_date"></div>
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
                <th rowspan="2">NO.</th>
                <th rowspan="2">LOT NO.</th>
                <th colspan="2">STOCK IN</th>
                <th colspan="2">STOCK OUT</th>
                <th>BALANCE</th>
                <th rowspan="2">INITIAL</th>
                <th colspan="2">ACCEPTANCE TEST STATUS</th>
                <th rowspan="2">TINDAKAN</th>
            </tr>
            <tr>
                <th>Qty</th>
                <th>Date Received</th>
                <th>Qty Out</th>
                <th>Date Out</th>
                <th>Qty</th>
                <th>Performed?</th>
                <th>Date</th>
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

</body>
</html>