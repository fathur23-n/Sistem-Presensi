<?php
// lib/fungsi_telat.php

function hitung_status_presensi($jam_jadwal_masuk, $jam_scan_masuk) {
    // 1. Jika Jadwalnya Libur/Kosong, return null
    if ($jam_jadwal_masuk == '-' || $jam_jadwal_masuk == '') {
        return [
            'status' => 'Libur',
            'telat_menit' => 0,
            'keterangan' => 'Tidak ada jadwal'
        ];
    }

    // 2. Konversi ke Timestamp (detik)
    // Asumsi input format "H:i:s" (07:00:00)
    // Kita pakai tanggal dummy hari ini agar bisa dibandingkan jamnya saja
    $tgl_dummy = date('Y-m-d');
    $time_jadwal = strtotime("$tgl_dummy $jam_jadwal_masuk");
    $time_scan   = strtotime("$tgl_dummy $jam_scan_masuk");

    // 3. Hitung Selisih (Scan - Jadwal)
    $selisih_detik = $time_scan - $time_jadwal;
    $selisih_menit = floor($selisih_detik / 60);

    // Default Output
    $hasil = [
        'status' => 'Tepat Waktu',
        'telat_menit' => 0,
        'keterangan' => 'Datang lebih awal / tepat waktu'
    ];

    // --- LOGIKA ATURAN KAKAK DI SINI ---
    
    if ($selisih_menit > 0) {
        // Artinya datang LEBIH dari jam jadwal (Telat secara waktu)
        
        if ($selisih_menit <= 15) {
            // CASE: Telat tapi masih dalam toleransi (misal 1 - 15 menit)
            $hasil['status'] = 'Tepat Waktu (Toleransi)';
            $hasil['telat_menit'] = 0; // Dianggap tidak telat
            $hasil['keterangan'] = "Terlambat $selisih_menit menit (Masih Toleransi)";
        } 
        else {
            // CASE: Telat >= 16 menit
            // Hitungan telat dimulai setelah menit ke-15
            // Contoh: Telat 16 menit, maka terhitung 1 menit.
            $terhitung_telat = $selisih_menit - 15; 
            
            $hasil['status'] = 'Terlambat';
            $hasil['telat_menit'] = $terhitung_telat;
            $hasil['keterangan'] = "Terlambat $terhitung_telat menit (Total durasi scan $selisih_menit mnt)";
        }
    }

    return $hasil;
}
?>