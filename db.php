<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "bincard_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Sambungan pangkalan data gagal: " . $conn->connect_error);
}
?>