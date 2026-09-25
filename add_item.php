<?php
include 'db.php';

$show_success = false;
$new_id = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_item'])) {
    $item_name    = $_POST['item_name'];
    $ref_number   = $_POST['ref_number'];
    $uom          = $_POST['uom'];
    $min_level    = (int)$_POST['min_level'];
    $max_level    = (int)$_POST['max_level'];
    $par_level    = (int)$_POST['par_level'];
    $ordering_qty = (int)$_POST['ordering_qty'];
    $lab_branch   = $_POST['lab_branch'];
    $location     = $_POST['location'];

    $stmt = $conn->prepare("INSERT INTO items (item_name, ref_number, uom, min_level, max_level, par_level, ordering_qty, lab_branch, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiiiss", $item_name, $ref_number, $uom, $min_level, $max_level, $par_level, $ordering_qty, $lab_branch, $location);
    
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $show_success = true;
    } else {
        echo "<script>alert('Ralat: " . addslashes($stmt->error) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Tambah Item Baharu - Bin Card</title>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f6f9; }
        .card { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #003366; }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px; }
        input { width: 95%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn { background-color: #003366; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary { background-color: #6c757d; }
    </style>
</head>
<body>

<div class="card">
    <h2>Daftar Item / Bahan Ujian Baharu</h2>
    <form action="add_item.php" method="POST">
        <div class="form-group"><label>Nama Item:</label><input type="text" name="item_name" required placeholder="cth: PCR Mastermix"></div>
        <div class="form-group"><label>Nombor Rujukan:</label><input type="text" name="ref_number" placeholder="cth: REF-9901"></div>
        <div class="form-group"><label>Unit Ukuran:</label><input type="text" name="uom" placeholder="cth: Botol / Kit / Kotak"></div>
        <div class="form-group"><label>Cawangan Makmal:</label><input type="text" name="lab_branch" placeholder="cth: Selcare Rawang"></div>
        <div class="form-group"><label>Lokasi Item:</label><input type="text" name="location" placeholder="cth: Peti Sejuk B2"></div>
        <div style="display: flex; gap: 10px;">
            <div class="form-group"><label>Tahap Minimum:</label><input type="number" name="min_level" value="0" data-default-value="0"></div>
            <div class="form-group"><label>Tahap Maksimum:</label><input type="number" name="max_level" value="0" data-default-value="0"></div>
            <div class="form-group"><label>Tahap PAR:</label><input type="number" name="par_level" value="0" data-default-value="0"></div>
            <div class="form-group"><label>Kuantiti Pesanan:</label><input type="number" name="ordering_qty" value="0" data-default-value="0"></div>
        </div>
        <br>
        <button type="submit" name="save_item" class="btn">Simpan Item Baharu</button>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-default-value]').forEach(function (field) {
            const defaultValue = field.dataset.defaultValue;

            field.addEventListener('focus', function () {
                if (this.value === defaultValue) {
                    this.value = '';
                }
            });

            field.addEventListener('blur', function () {
                if (this.value === '') {
                    this.value = defaultValue;
                }
            });
        });
    });
</script>

<?php if ($show_success): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Berjaya!',
            text: 'Item baharu telah berjaya didaftarkan.',
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#003366'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php?item_id=<?= $new_id ?>';
            }
        });
    });
</script>
<?php endif; ?>

</body>
</html>