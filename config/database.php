<?php
// config/database.php

$host = "localhost";
$user = "root";      // Default XAMPP biasanya root
$pass = "";          // Default XAMPP biasanya kosong
$db   = "sik"; // Sesuaikan nama database di screenshot Kakak

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Gagal terkoneksi ke database: " . mysqli_connect_error());
}
?>