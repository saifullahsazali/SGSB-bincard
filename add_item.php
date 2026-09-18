<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_item'])) {
    $item_name = $_POST['item_name'];
    $ref_number = $_POST['ref_number'];
    $uom = $_POST['uom'];
    $min_level = (int)$_POST['min_level'];
    $max_level = (int)$_POST['max_level'];
    $par_level = (int)$_POST['par_level'];
    $ordering_qty = (int)$_POST['ordering_qty'];
    $lab_branch = $_POST['lab_branch'];
    $location = $_POST['location'];

    $stmt = $conn->prepare("INSERT INTO items (item_name, ref_number, uom, min_level, max_level, par_level, ordering_qty, lab_branch, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiiiss", $item_name, $ref_number, $uom, $min_level, $max_level, $par_level, $ordering_qty, $lab_branch, $location);
    $stmt->execute();

    $new_id = $stmt->insert_id;
    header("Location: index.php?item_id=" . $new_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Tambah Item Baharu - Bin Card</title>
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
    <form method="POST">
        <div class="form-group"><label>Nama Item (Name of Item):</label><input type="text" name="item_name" required placeholder="cth: PCR Mastermix"></div>
        <div class="form-group"><label>Ref Number:</label><input type="text" name="ref_number" placeholder="cth: REF-9901"></div>
        <div class="form-group"><label>Unit of Measure (UOM):</label><input type="text" name="uom" placeholder="cth: Vials / Kit / Box"></div>
        <div class="form-group"><label>Lab Branch:</label><input type="text" name="lab_branch" placeholder="cth: Selcare Rawang"></div>
        <div class="form-group"><label>Location of Item:</label><input type="text" name="location" placeholder="cth: Freezer B2"></div>
        <div style="display: flex; gap: 10px;">
            <div class="form-group"><label>Min Level:</label><input type="number" name="min_level" value="0"></div>
            <div class="form-group"><label>Max Level:</label><input type="number" name="max_level" value="0"></div>
            <div class="form-group"><label>PAR Level:</label><input type="number" name="par_level" value="0"></div>
            <div class="form-group"><label>Ordering Qty:</label><input type="number" name="ordering_qty" value="0"></div>
        </div>
        <br>
        <button type="submit" name="save_item" class="btn">Simpan Item Baharu</button>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </form>
</div>

</body>
</html>