<?php
include 'db.php';

// Menetapkan header supaya penyemak web memuat turun fail sebagai Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Bin_Card_SGSB_F26a_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Dapatkan item_id jika ada tapisan
$item_id = isset($_GET['item_id']) ? $_GET['item_id'] : '';

$query = "SELECT transactions.*, items.item_name, items.code_no 
          FROM transactions 
          JOIN items ON transactions.item_id = items.id";

if (!empty($item_id)) {
    $query .= " WHERE transactions.item_id = '$item_id'";
}

$query .= " ORDER BY transactions.transaction_date ASC, transactions.id ASC";
$result = $conn->query($query);
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="8" style="background-color: #f2f2f2; font-size: 14pt;">REKOD BIN CARD (SGSB-F26a)</th>
        </tr>
        <tr style="background-color: #d9edf7;">
            <th>No.</th>
            <th>Nama Item</th>
            <th>Tarikh</th>
            <th>No. Lot / Batch</th>
            <th>Masuk (In)</th>
            <th>Keluar (Out)</th>
            <th>Baki (Balance)</th>
            <th>Penerima / Catatan</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if ($result && $result->num_rows > 0): 
            while ($row = $result->fetch_assoc()): 
        ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['item_name']); ?></td>
                <td><?= date('d/m/Y', strtotime($row['transaction_date'])); ?></td>
                <td><?= htmlspecialchars($row['lot_no']); ?></td>
                <td><?= $row['quantity_in'] > 0 ? $row['quantity_in'] : '-'; ?></td>
                <td><?= $row['quantity_out'] > 0 ? $row['quantity_out'] : '-'; ?></td>
                <td><strong><?= $row['balance']; ?></strong></td>
                <td><?= htmlspecialchars($row['remarks']); ?></td>
            </tr>
        <?php 
            endwhile; 
        else: 
        ?>
            <tr>
                <td colspan="8" align="center">Tiada rekod dijumpai.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>