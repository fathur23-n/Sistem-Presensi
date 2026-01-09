<?php
// halaman/cetak_laporan_periode.php
// KHUSUS CETAK CUSTOM RANGE TANGGAL

include '../config/database.php';
include '../lib/fungsi_jadwal.php';
include '../lib/fungsi_telat.php';
include '../lib/fungsi_data_absen.php';

// Set Timezone
date_default_timezone_set('Asia/Jakarta');

$dept_selected = $_GET['departemen'];
$tgl_awal      = $_GET['awal'];
$tgl_akhir     = $_GET['akhir'];
$search_nama   = isset($_GET['search_nama']) ? $_GET['search_nama'] : '';

// Ambil Nama Departemen
$q_label = mysqli_query($koneksi, "SELECT nama FROM departemen WHERE dep_id = '$dept_selected'");
$data_label = mysqli_fetch_assoc($q_label);
$nama_dept_label = $data_label ? $data_label['nama'] : $dept_selected;

// --- LOGIKA NAMA FILE PDF OTOMATIS ---
$dept_clean = strtoupper(str_replace(' ', '_', $nama_dept_label));
$periode_clean = date('dMy', strtotime($tgl_awal)) . "_sd_" . date('dMy', strtotime($tgl_akhir));
$nama_file_cetak = "REKAP_" . $dept_clean . "_" . $periode_clean;

// Query Karyawan
$sql_karyawan = "SELECT * FROM pegawai WHERE departemen = '$dept_selected'";
if (!empty($search_nama)) {
    $sql_karyawan .= " AND nama LIKE '%$search_nama%'";
}
$sql_karyawan .= " ORDER BY nama ASC";
$q_karyawan = mysqli_query($koneksi, $sql_karyawan);

$laporan = [];
while ($karyawan = mysqli_fetch_assoc($q_karyawan)) {
    $id_pegawai = $karyawan['id'];

    // --- LOGIKA GABUNG DATA LINTAS BULAN (SAMA SEPERTI DI REKAP) ---
    $start    = new DateTime($tgl_awal);
    $end      = new DateTime($tgl_akhir);
    $end->modify('+1 day'); 
    $interval = DateInterval::createFromDateString('1 month');
    $period   = new DatePeriod($start, $interval, $end);

    $raw_jadwal = [];
    $raw_absen  = [];

    foreach ($period as $dt) {
        $m = $dt->format('m');
        $y = $dt->format('Y');
        $j = get_jadwal_karyawan($koneksi, $id_pegawai, $m, $y);
        $a = get_data_presensi($koneksi, $id_pegawai, $m, $y);
        if(!empty($j)) $raw_jadwal = array_merge($raw_jadwal, $j);
        if(!empty($a)) $raw_absen  = $raw_absen + $a;
    }

    // Filter Tanggal
    $list_jadwal_final = [];
    foreach($raw_jadwal as $j) {
        if($j['tanggal'] >= $tgl_awal && $j['tanggal'] <= $tgl_akhir) {
            $list_jadwal_final[] = $j;
        }
    }
    // -------------------------------------------------------------

    $total_hadir = 0; $total_alpha = 0; $total_telat_freq = 0; $total_telat_menit = 0;
    $ada_jadwal = false;

    if (!empty($list_jadwal_final)) {
        $ada_jadwal = true;
        foreach ($list_jadwal_final as $jadwal) {
            $tgl_ini = $jadwal['tanggal'];
            if (isset($raw_absen[$tgl_ini])) {
                $absen_row = $raw_absen[$tgl_ini];
                $jam_scan = date('H:i:s', strtotime($absen_row['jam_datang']));
                $analisa = hitung_status_presensi($jadwal['jam_masuk'], $jam_scan);
                if ($analisa['status'] == 'Terlambat') {
                    $total_telat_freq++;
                    $total_telat_menit += $analisa['telat_menit'];
                }
                $total_hadir++;
            } else {
                if ($jadwal['status'] == 'Kerja') {
                    if($jadwal['jam_masuk'] != '-' && $jadwal['jam_masuk'] != '') {
                        $total_alpha++;
                    }
                }
            }
        }
    } 

    $laporan[] = [
        'nik'         => $karyawan['nik'],
        'nama'        => $karyawan['nama'],
        'jabatan'     => $karyawan['jbtn'],
        'hadir'       => $total_hadir,
        'alpha'       => $total_alpha,
        'kali_telat'  => $total_telat_freq,
        'total_menit' => $total_telat_menit,
        'ada_jadwal'  => $ada_jadwal
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $nama_file_cetak ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 20px; color: #000; -webkit-print-color-adjust: exact; }
        
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 14px; }
        
        .sub-header { text-align: center; margin-bottom: 20px; }
        .sub-header h3 { margin: 0; text-decoration: underline; }
        
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: center; vertical-align: middle; }
        th { background-color: #ddd; font-weight: bold; } 
        td.kiri { text-align: left; }
        
        /* FOOTER SEJAJAR */
        .footer-container { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-start; }
        .timestamp-box { font-size: 11px; font-style: italic; color: #555; text-align: left; padding-top: 5px; }
        .ttd-box { text-align: center; width: 250px; }
        .ttd-box p { margin: 5px 0; }
        .space-ttd { height: 70px; } 

        @media print {
            @page { size: landscape; margin: 10mm; } 
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h1>RUMAH SAKIT EMMA MOJOKERTO</h1>
        <p>Jl. Raya Ijen No. 123, Mojokerto, Jawa Timur</p>
        <p>Telp: (0321) 123456 | Email: info@rsemma.com</p>
    </div>

    <div class="sub-header">
        <h3>LAPORAN REKAPITULASI PRESENSI (PERIODE)</h3>
        <p>
            Departemen: <strong><?= $nama_dept_label ?></strong> | 
            Periode: <strong><?= date('d/m/Y', strtotime($tgl_awal)) ?> s/d <?= date('d/m/Y', strtotime($tgl_akhir)) ?></strong>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="12%">NIK</th>
                <th width="25%">Nama Karyawan</th>
                <th width="15%">Jabatan</th>
                <th width="8%">Hadir</th>
                <th width="8%">Alpha</th>
                <th width="27%">Keterlambatan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            if(!empty($laporan)):
                foreach($laporan as $row): 
                    $telat_text = "-";
                    if ($row['kali_telat'] > 0) {
                        $total_m = $row['total_menit'];
                        if ($total_m >= 60) {
                            $jam = floor($total_m / 60);
                            $sisa_m = $total_m % 60;
                            $durasi_str = "$jam Jam";
                            if ($sisa_m > 0) $durasi_str .= " $sisa_m mnt";
                        } else {
                            $durasi_str = "$total_m mnt";
                        }
                        $telat_text = "<b>" . $row['kali_telat'] . "x</b> <br> (" . $total_m . " mnt / " . $durasi_str . ")";
                    }
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $row['nik'] ?></td>
                <td class="kiri"><?= $row['nama'] ?></td>
                <td><?= $row['jabatan'] ?></td>
                <td><?= $row['hadir'] ?></td>
                <td><?= $row['alpha'] ?></td>
                <td style="font-size:11px;">
                    <?php if($row['ada_jadwal']): ?>
                        <?= $telat_text ?>
                    <?php else: ?>
                        <i style="color:#888;">Jadwal Kosong</i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="7" style="padding:20px;">Data tidak ditemukan untuk periode ini.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-container">
        <div class="timestamp-box">
            Dicetak oleh Sistem pada:<br>
            <strong><?= date('d-m-Y H:i:s') ?> WIB</strong>
        </div>
        <div class="ttd-box">
            <p>Mojokerto, <?= date('d F Y') ?></p>
            <p>Mengetahui,</p>
            <p><strong>Kepala HRD</strong></p> 
            <div class="space-ttd"></div> 
            <hr style="width: 80%; border: 1px solid #000;">
        </div>
    </div>

</body>
</html>