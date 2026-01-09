<?php
// halaman/rekap_presensi.php

include '../config/database.php';
include '../lib/fungsi_jadwal.php';
include '../lib/fungsi_telat.php';
include '../lib/fungsi_data_absen.php';

// Inisialisasi variabel agar tidak error undefined
$laporan = [];
$pegawai_dipilih = [];
$pesan_error = "";

// Cek apakah tombol filter ditekan
if (isset($_GET['filter'])) {
    
    // INPUT DARI USER ADALAH NIK
    $nik_input  = $_GET['nik']; 
    $bulan      = $_GET['bulan'];
    $tahun      = $_GET['tahun'];

    // --- TAHAP 1: JEMBATAN (KONVERSI NIK KE ID) ---
    // Kita cari ID-nya di tabel pegawai berdasarkan NIK yang diinput
    $q_cek = mysqli_query($koneksi, "SELECT * FROM pegawai WHERE nik = '$nik_input'");
    $data_pegawai = mysqli_fetch_assoc($q_cek);

    if ($data_pegawai) {
        // JIKA NIK DITEMUKAN
        $id_pegawai = $data_pegawai['id']; // <--- INI KUNCINYA (Dapat ID 137 dari NIK 0120201)
        $pegawai_dipilih = $data_pegawai;

        // --- TAHAP 2: PROSES DATA MENGGUNAKAN ID ---
        
        // A. Ambil Data Jadwal (Pakai ID)
        $list_jadwal = get_jadwal_karyawan($koneksi, $id_pegawai, $bulan, $tahun);

        // B. Ambil Data Presensi (Pakai ID, sesuai perbaikan terakhir kita)
        $list_absen  = get_data_presensi($koneksi, $id_pegawai, $bulan, $tahun);

        // C. GABUNGKAN (LOGIKA UTAMA)
        foreach ($list_jadwal as $jadwal) {
            $tgl_ini = $jadwal['tanggal'];
            
            // Siapkan template baris data
            $data_final = [
                'tanggal'       => $tgl_ini,
                'jadwal_masuk'  => $jadwal['jam_masuk'],
                'jadwal_pulang' => $jadwal['jam_pulang'],
                'shift'         => $jadwal['kode_shift'],
                'jam_scan_masuk'=> '-',
                'jam_scan_pulang'=> '-',
                'status_akhir'  => '',
                'telat_menit'   => 0,
                'keterangan'    => ''
            ];

            // Cek apakah ada data absen di tanggal ini?
            if (isset($list_absen[$tgl_ini])) {
                // --- ADA DATA ABSEN ---
                $absen_row = $list_absen[$tgl_ini];
                
                // Ambil jam saja
                $jam_scan_masuk_only = date('H:i:s', strtotime($absen_row['jam_datang']));
                $jam_scan_pulang_only = ($absen_row['jam_pulang'] != null) ? date('H:i:s', strtotime($absen_row['jam_pulang'])) : '-';

                // HITUNG TELAT (Logic 15 Menit)
                $analisa_telat = hitung_status_presensi($jadwal['jam_masuk'], $jam_scan_masuk_only);

                $data_final['jam_scan_masuk']  = $jam_scan_masuk_only;
                $data_final['jam_scan_pulang'] = $jam_scan_pulang_only;
                $data_final['status_akhir']    = $analisa_telat['status']; 
                $data_final['telat_menit']     = $analisa_telat['telat_menit'];
                $data_final['keterangan']      = $analisa_telat['keterangan'];

            } else {
                // --- TIDAK ADA DATA ABSEN ---
                if ($jadwal['status'] == 'Kerja') {
                    $data_final['status_akhir'] = 'Tidak Masuk';
                    $data_final['keterangan']   = 'Alpha / Belum Scan';
                } else {
                    $data_final['status_akhir'] = 'Libur';
                    $data_final['keterangan']   = '-';
                }
            }

            $laporan[] = $data_final;
        }

    } else {
        // JIKA NIK TIDAK DITEMUKAN
        $pesan_error = "NIK <b>$nik_input</b> tidak ditemukan di data pegawai.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Rekap Presensi (Search by NIK)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background-color: #f4f6f9; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h3 { margin-top: 0; color: #333; }
        
        /* Form Style */
        input[type="text"], button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; margin-right: 10px; }
        button { background-color: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }

        /* Table Style */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        th, td { border: 1px solid #dee2e6; padding: 10px; text-align: center; font-size: 14px; }
        th { background-color: #343a40; color: white; }
        
        /* Status Colors */
        .bg-merah { background-color: #ffe3e3; color: #a71d2a; } 
        .bg-kuning { background-color: #fff9db; color: #e67700; }
        .bg-hijau { background-color: #d3f9d8; color: #2b8a3e; }
        .bg-abu { background-color: #f8f9fa; color: #adb5bd; }
        
        .alert { padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="card">
        <h3>Laporan Rekapitulasi Presensi</h3>
        
        <form method="GET">
            <label>NIK Pegawai:</label>
            <input type="text" name="nik" value="<?= $_GET['nik'] ?? '0120201' ?>" placeholder="Contoh: 0120201" required>
            
            <label>Bulan:</label>
            <input type="text" name="bulan" value="<?= $_GET['bulan'] ?? date('m') ?>" size="3" required>
            
            <label>Tahun:</label>
            <input type="text" name="tahun" value="<?= $_GET['tahun'] ?? date('Y') ?>" size="5" required>
            
            <button type="submit" name="filter">Tampilkan Data</button>
        </form>
    </div>

    <?php if($pesan_error): ?>
        <div class="alert"><?= $pesan_error ?></div>
    <?php endif; ?>

    <?php if(!empty($pegawai_dipilih)): ?>
        <div class="card">
            <table style="width: auto; margin-bottom: 15px; border:none;">
                <tr>
                    <td style="border:none; text-align:left; padding: 5px;"><strong>Nama Karyawan</strong></td>
                    <td style="border:none; text-align:left; padding: 5px;">: <?= $pegawai_dipilih['nama'] ?></td>
                </tr>
                <tr>
                    <td style="border:none; text-align:left; padding: 5px;"><strong>NIK</strong></td>
                    <td style="border:none; text-align:left; padding: 5px;">: <?= $pegawai_dipilih['nik'] ?></td>
                </tr>
                <tr>
                    <td style="border:none; text-align:left; padding: 5px;"><strong>ID System</strong></td>
                    <td style="border:none; text-align:left; padding: 5px;">: <?= $pegawai_dipilih['id'] ?></td>
                </tr>
            </table>

            <?php if(!empty($laporan)): ?>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">Tanggal</th>
                        <th rowspan="2">Shift</th>
                        <th colspan="2">Jadwal</th>
                        <th colspan="2">Realisasi (Scan)</th>
                        <th rowspan="2">Status</th>
                        <th rowspan="2">Telat</th>
                        <th rowspan="2">Keterangan</th>
                    </tr>
                    <tr>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($laporan as $row): 
                        $warna = '';
                        if($row['status_akhir'] == 'Tidak Masuk') $warna = 'bg-merah';
                        elseif($row['status_akhir'] == 'Terlambat') $warna = 'bg-kuning';
                        elseif(strpos($row['status_akhir'], 'Tepat') !== false) $warna = 'bg-hijau';
                        elseif($row['status_akhir'] == 'Libur') $warna = 'bg-abu';
                    ?>
                    <tr class="<?= $warna ?>">
                        <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                        <td><?= $row['shift'] ?></td>
                        <td><?= $row['jadwal_masuk'] ?></td>
                        <td><?= $row['jadwal_pulang'] ?></td>
                        
                        <td><strong><?= $row['jam_scan_masuk'] ?></strong></td>
                        <td><?= $row['jam_scan_pulang'] ?></td>
                        
                        <td><?= $row['status_akhir'] ?></td>
                        <td style="font-weight:bold;"><?= $row['telat_menit'] > 0 ? $row['telat_menit']."'" : "-" ?></td>
                        <td style="text-align:left; font-size: 13px;"><?= $row['keterangan'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Jadwal belum tersedia untuk periode ini.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>