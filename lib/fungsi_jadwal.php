<?php
// lib/fungsi_jadwal.php

/**
 * Fungsi untuk mengambil jadwal lengkap satu bulan dalam bentuk Array Vertikal
 * @param object $koneksi  Variabel koneksi database
 * @param int    $id_pegawai ID Karyawan (sesuai tabel pegawai)
 * @param string $bulan    Format '01' - '12'
 * @param string $tahun    Format 'YYYY'
 */
function get_jadwal_karyawan($koneksi, $id_pegawai, $bulan, $tahun) {
    
    // --- STEP 1: Ambil Referensi Jam Jaga (Shift) ---
    // Kita jadikan array assoc agar mudah dicari. Contoh: $ref_shift['Pagi3']
    $ref_shift = [];
    $q_shift = mysqli_query($koneksi, "SELECT * FROM jam_jaga");
    while($row = mysqli_fetch_assoc($q_shift)) {
        // Kuncinya adalah nama shift (misal: 'Pagi3')
        $ref_shift[ $row['shift'] ] = [
            'jam_masuk'  => $row['jam_masuk'],
            'jam_pulang' => $row['jam_pulang']
        ];
    }

    // --- STEP 2: Ambil Data Jadwal Horizontal (h1-h31) ---
    // Pastikan 'id' di tabel jadwal_pegawai merujuk ke id pegawai
    $q_jadwal = mysqli_query($koneksi, "SELECT * FROM jadwal_pegawai 
                                        WHERE id = '$id_pegawai' 
                                        AND bulan = '$bulan' 
                                        AND tahun = '$tahun'");
    
    $data_jadwal = mysqli_fetch_assoc($q_jadwal);

    // Hasil akhir yang akan dikembalikan
    $hasil_jadwal = [];

    // Jika data jadwal tidak ditemukan, kembalikan array kosong
    if (!$data_jadwal) {
        return []; 
    }

    // --- STEP 3: Looping Tanggal (Konversi Horizontal ke Vertikal) ---
    // Hitung jumlah hari dalam bulan tersebut
    $jumlah_hari = date('t', strtotime("$tahun-$bulan-01"));

    for ($tgl = 1; $tgl <= $jumlah_hari; $tgl++) {
        // Nama kolom di database (h1, h2, ... h31)
        $nama_kolom = 'h' . $tgl;
        
        // Ambil kode shift dari kolom tersebut (misal: "Pagi3")
        $kode_shift = isset($data_jadwal[$nama_kolom]) ? $data_jadwal[$nama_kolom] : '';

        // Siapkan data default (Libur/Off)
        $jam_masuk  = '-';
        $jam_pulang = '-';
        $status_ket = 'Libur';

        // Cek apakah kode shift ini ada di tabel jam_jaga?
        if ($kode_shift != '' && isset($ref_shift[$kode_shift])) {
            $jam_masuk  = $ref_shift[$kode_shift]['jam_masuk'];
            $jam_pulang = $ref_shift[$kode_shift]['jam_pulang'];
            $status_ket = 'Kerja';
        }

        // Masukkan ke array hasil
        // Kita format tanggalnya jadi YYYY-MM-DD agar mudah dipakai query nanti
        $tanggal_full = $tahun . '-' . $bulan . '-' . str_pad($tgl, 2, '0', STR_PAD_LEFT);

        $hasil_jadwal[] = [
            'tanggal'    => $tanggal_full,     // 2024-09-01
            'kode_shift' => $kode_shift,       // Pagi3
            'jam_masuk'  => $jam_masuk,        // 07:00:00
            'jam_pulang' => $jam_pulang,       // 14:00:00
            'status'     => $status_ket        // Kerja / Libur
        ];
    }

    return $hasil_jadwal;
}
?>