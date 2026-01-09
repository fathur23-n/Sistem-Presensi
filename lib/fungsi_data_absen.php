<?php
// lib/fungsi_data_absen.php

function get_data_presensi($koneksi, $id_pegawai, $bulan, $tahun) {
    // UPDATED: Menggunakan kolom 'id' sebagai acuan ID Pegawai
    // Filter juga berdasarkan bulan dan tahun dari kolom jam_datang
    
    $sql = "SELECT * FROM rekap_presensi 
            WHERE id = '$id_pegawai' 
            AND MONTH(jam_datang) = '$bulan' 
            AND YEAR(jam_datang) = '$tahun'";
            
    $query = mysqli_query($koneksi, $sql);
    
    // Cek error untuk memastikan query benar
    if (!$query) {
        die("Query Error: " . mysqli_error($koneksi));
    }
    
    $data_absen = [];
    while ($row = mysqli_fetch_assoc($query)) {
        // Ambil tanggalnya saja (2024-09-01) untuk dijadikan kunci array
        $tanggal_saja = date('Y-m-d', strtotime($row['jam_datang']));
        
        // Simpan data row ke dalam array
        $data_absen[$tanggal_saja] = $row;
    }
    
    return $data_absen;
}
?>