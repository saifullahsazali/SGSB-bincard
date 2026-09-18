
<?php
include 'db.php';

// Ambil parameter penapis dari URL
$filter_item = isset($_GET['item_id']) ? $_GET['item_id'] : '';
$search_lot  = isset($_GET['search_lot']) ? trim($_GET['search_lot']) : '';

// Bina kueri SQL
$query = "SELECT stock_transactions.*, items.item_name 
          FROM stock_transactions 
          JOIN items ON stock_transactions.item_id = items.id 
          WHERE 1=1";

if (!empty($filter_item)) {
    $query .= " AND stock_transactions.item_id = '$filter_item'";
}
if (!empty($search_lot)) {
    $query .= " AND stock_transactions.lot_no LIKE '%$search_lot%'";
}

$query .= " ORDER BY stock_transactions.id ASC";
$result = $conn->query($query);

// Header muat turun fail Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Bin_Card_SGSB_F26a_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="7" style="background-color: #0d6efd; color: white; font-size: 14pt; height: 30px;">
                REKOD BIN CARD (SGSB-F26a) - LAPORAN STOK
            </th>
        </tr>
        <tr style="background-color: #e9ecef; font-weight: bold;">
            <th>No.</th>
            <th>Nama Item</th>
            <th>No. Lot / Batch</th>
            <th>Masuk (In)</th>
            <th>Keluar (Out)</th>
            <th>Baki (Balance)</th>
            <th>Catatan / Penerima</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if ($result && $result->num_rows > 0): 
            while ($row = $result->fetch_assoc()): 
                // Semakan nama lajur kuantiti masuk
                $in = $row['quantity_in'] ?? $row['qty_in'] ?? $row['in_qty'] ?? 0;
                
                // Semakan nama lajur kuantiti keluar
                $out = $row['quantity_out'] ?? $row['qty_out'] ?? $row['out_qty'] ?? 0;
                
                // Semakan nama lajur catatan/remarks
                $remarks = $row['remarks'] ?? $row['remark'] ?? $row['description'] ?? '';
        ?>
            <tr>
                <td align="center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['item_name'] ?? ''); ?></td>
                <td align="center"><?= htmlspecialchars($row['lot_no'] ?? ''); ?></td>
                <td align="center"><?= $in > 0 ? $in : '-'; ?></td>
                <td align="center"><?= $out > 0 ? $out : '-'; ?></td>
                <td align="center"><strong><?= $row['balance'] ?? 0; ?></strong></td>
                <td><?= htmlspecialchars((string)$remarks); ?></td>
            </tr>
        <?php 
            endwhile; 
        else: 
        ?>
            <tr>
                <td colspan="7" align="center">Tiada rekod dijumpai.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>