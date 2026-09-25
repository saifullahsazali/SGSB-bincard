
<?php
include 'db.php';

// Ambil semua item, termasuk item yang belum mempunyai transaksi.
$query = "SELECT items.id AS item_id, items.item_name, items.ref_number, items.uom,
                 items.lab_branch, items.location, items.min_level, items.max_level,
                 items.par_level, items.ordering_qty,
                 stock_transactions.lot_no, stock_transactions.expiry_date,
                 stock_transactions.stock_in_qty, stock_transactions.date_received,
                 stock_transactions.stock_out_qty, stock_transactions.date_out,
                 stock_transactions.balance, stock_transactions.initials,
                 stock_transactions.acceptance_test_performed,
                 stock_transactions.acceptance_test_date
          FROM items
          LEFT JOIN stock_transactions ON stock_transactions.item_id = items.id
          ORDER BY items.item_name ASC, stock_transactions.id ASC";
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
            <th colspan="16" style="background-color: #0d6efd; color: white; font-size: 14pt; height: 30px;">
                REKOD BIN CARD (SGSB-F26a) - LAPORAN SEMUA ITEM
            </th>
        </tr>
        <tr style="background-color: #e9ecef; font-weight: bold;">
            <th>No.</th>
            <th>Nama Item</th>
            <th>No. Rujukan</th>
            <th>Unit Ukuran</th>
            <th>Cawangan Makmal</th>
            <th>Lokasi</th>
            <th>No. Lot / Batch</th>
            <th>Tarikh Luput</th>
            <th>Stok Masuk</th>
            <th>Tarikh Diterima</th>
            <th>Stok Keluar</th>
            <th>Tarikh Dikeluarkan</th>
            <th>Baki</th>
            <th>Inisial</th>
            <th>Status Ujian Penerimaan</th>
            <th>Tarikh Ujian</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if ($result && $result->num_rows > 0): 
            while ($row = $result->fetch_assoc()): 
        ?>
            <tr>
                <td align="center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['item_name'] ?? ''); ?></td>
                <td><?= htmlspecialchars($row['ref_number'] ?? ''); ?></td>
                <td><?= htmlspecialchars($row['uom'] ?? ''); ?></td>
                <td><?= htmlspecialchars($row['lab_branch'] ?? ''); ?></td>
                <td><?= htmlspecialchars($row['location'] ?? ''); ?></td>
                <td align="center"><?= htmlspecialchars($row['lot_no'] ?? ''); ?></td>
                <td align="center"><?= htmlspecialchars($row['expiry_date'] ?? '-'); ?></td>
                <td align="center"><?= ($row['stock_in_qty'] ?? 0) > 0 ? $row['stock_in_qty'] : '-'; ?></td>
                <td align="center"><?= htmlspecialchars($row['date_received'] ?? '-'); ?></td>
                <td align="center"><?= ($row['stock_out_qty'] ?? 0) > 0 ? $row['stock_out_qty'] : '-'; ?></td>
                <td align="center"><?= htmlspecialchars($row['date_out'] ?? '-'); ?></td>
                <td align="center"><strong><?= $row['balance'] ?? 0; ?></strong></td>
                <td><?= htmlspecialchars($row['initials'] ?? ''); ?></td>
                <td><?= htmlspecialchars($row['acceptance_test_performed'] ?? ''); ?></td>
                <td align="center"><?= htmlspecialchars($row['acceptance_test_date'] ?? '-'); ?></td>
            </tr>
        <?php 
            endwhile; 
        else: 
        ?>
            <tr>
                <td colspan="16" align="center">Tiada item dijumpai.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>