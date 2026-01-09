<?php
// halaman/detail_popup_periode.php
// (KHUSUS UNTUK DETAIL RANGE TANGGAL)

include '../config/database.php';
include '../lib/fungsi_jadwal.php';
include '../lib/fungsi_telat.php';
include '../lib/fungsi_data_absen.php';

// Set Timezone
date_default_timezone_set('Asia/Jakarta');

$id_pegawai = $_GET['id']; 
// Perbedaan 1: Parameter yang diterima adalah TANGGAL AWAL & AKHIR
$tgl_awal   = $_GET['awal'];
$tgl_akhir  = $_GET['akhir'];

// Ambil Data Pegawai & Nama Departemen (JOIN)
$q_peg = mysqli_query($koneksi, "
    SELECT p.*, d.nama AS nama_dept 
    FROM pegawai p
    LEFT JOIN departemen d ON p.departemen = d.dep_id
    WHERE p.id = '$id_pegawai'
");
$pegawai = mysqli_fetch_assoc($q_peg);
$nama_departemen_tampil = $pegawai['nama_dept'] ? $pegawai['nama_dept'] : $pegawai['departemen'];

// --- LOGIKA BARU: MENGGABUNGKAN DATA JADWAL LINTAS BULAN ---
$start    = new DateTime($tgl_awal);
$end      = new DateTime($tgl_akhir);
$end->modify('+1 day'); 

$interval = DateInterval::createFromDateString('1 month');
$period   = new DatePeriod($start, $interval, $end);

$raw_jadwal = [];
$list_absen  = []; // Ditumpuk jadi satu array besar

foreach ($period as $dt) {
    $m = $dt->format('m');
    $y = $dt->format('Y');
    
    $j = get_jadwal_karyawan($koneksi, $id_pegawai, $m, $y);
    $a = get_data_presensi($koneksi, $id_pegawai, $m, $y);
    
    if(!empty($j)) $raw_jadwal = array_merge($raw_jadwal, $j);
    if(!empty($a)) $list_absen  = $list_absen + $a;
}

// Filter: Hanya ambil tanggal yang diminta user
$list_jadwal = [];
foreach($raw_jadwal as $j) {
    if($j['tanggal'] >= $tgl_awal && $j['tanggal'] <= $tgl_akhir) {
        $list_jadwal[] = $j;
    }
}
// -----------------------------------------------------------

// --- PRE-PROCESSING DATA (SAMA SEPERTI SEBELUMNYA) ---
$summary = [ 'hadir' => 0, 'telat_freq' => 0, 'telat_menit' => 0, 'alpha' => 0, 'libur' => 0 ];
$tabel_final = []; 

if(!empty($list_jadwal)) {
    foreach($list_jadwal as $jadwal) {
        $tgl = $jadwal['tanggal'];
        $row_data = [
            'tanggal' => $tgl,
            'jadwal_masuk' => $jadwal['jam_masuk'],
            'jadwal_pulang' => $jadwal['jam_pulang'],
            'scan_masuk' => '-', 'scan_pulang' => '-',
            'status' => '', 'telat_info' => '-', 'css_class' => ''
        ];

        if(isset($list_absen[$tgl])) {
            $absen = $list_absen[$tgl];
            $row_data['scan_masuk'] = date('H:i', strtotime($absen['jam_datang']));
            $row_data['scan_pulang'] = ($absen['jam_pulang']) ? date('H:i', strtotime($absen['jam_pulang'])) : '-';
            
            $analisa = hitung_status_presensi($jadwal['jam_masuk'], $row_data['scan_masuk'].":00");
            
            if($analisa['status'] == 'Terlambat') {
                $row_data['status'] = 'Terlambat';
                $row_data['telat_info'] = $analisa['telat_menit'] . "m";
                $row_data['css_class'] = 'status-warning'; 
                $summary['telat_freq']++;
                $summary['telat_menit'] += $analisa['telat_menit'];
            } else {
                $row_data['status'] = 'Tepat Waktu';
                $row_data['css_class'] = 'status-success';
            }
            $summary['hadir']++;
        } else {
            if($jadwal['status'] == 'Kerja') {
                if($jadwal['jam_masuk'] != '-' && $jadwal['jam_masuk'] != '') {
                    $row_data['status'] = 'Tidak Masuk (Alpha)';
                    $row_data['css_class'] = 'status-danger';
                    $summary['alpha']++;
                } else {
                    $row_data['status'] = 'Off / Kosong';
                }
            } else {
                $row_data['status'] = 'Libur';
                $row_data['css_class'] = 'status-libur';
                $summary['libur']++;
            }
        }
        $tabel_final[] = $row_data;
    }
}

// Konversi Menit ke Jam
$total_m = $summary['telat_menit'];
$info_durasi = "";
if ($total_m >= 60) {
    $jam = floor($total_m / 60);
    $sisa_m = $total_m % 60;
    $info_durasi = "$jam Jam";
    if ($sisa_m > 0) $info_durasi .= " $sisa_m Menit";
} else {
    $info_durasi = "$total_m Menit";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Custom - <?= $pegawai['nama'] ?></title>
    <style>
        /* CSS SAMA PERSIS DENGAN SEBELUMNYA */
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: #fffdf5; margin: 0; padding: 20px; color: #333; -webkit-print-color-adjust: exact; }
        
        /* Ubah warna border Header jadi Kuning (Penanda Custom) */
        .profile-card { background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-left: 5px solid #ffc107; }
        .profile-info h2 { margin: 0; font-size: 18px; color: #2c3e50; }
        .profile-info p { margin: 5px 0 0; color: #7f8c8d; font-size: 14px; }
        .btn-print { background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 13px; }
        
        .stats-container { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-bottom: 3px solid transparent; }
        .stat-box h3 { margin: 0; font-size: 22px; font-weight: bold; }
        .stat-box span { font-size: 11px; text-transform: uppercase; color: #aaa; letter-spacing: 0.5px; display:block; margin-top:5px; }
        
        .box-hadir { border-bottom-color: #28a745; color: #28a745; }
        .box-telat { border-bottom-color: #ffc107; color: #d39e00; }
        .box-durasi { border-bottom-color: #6f42c1; color: #6f42c1; }
        .box-alpha { border-bottom-color: #dc3545; color: #dc3545; }
        .box-libur { border-bottom-color: #198754; color: #198754; }

        .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: #343a40; color: #fff; padding: 12px 15px; text-align: left; font-weight: 600; text-transform: uppercase; font-size: 11px; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; color: #555; vertical-align: middle; }
        
        tr.status-danger { background-color: #ffe3e3 !important; }
        tr.status-libur { background-color: #d3f9d8 !important; }
        tr.status-warning { background-color: #fff3cd !important; }

        .badge { padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; display: inline-block; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.1); }
        .status-success .badge { background: #d4edda; color: #155724; }
        .status-libur .badge { color: #0f5132; border-color: #0f5132; }
        .status-danger .badge { color: #842029; border-color: #842029; }
        .status-warning .badge { color: #664d03; border-color: #664d03; }

        .print-footer { text-align: right; font-size: 11px; color: #888; font-style: italic; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 20px; }

        @media print {
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background-color: white; }
            .btn-print { display: none; }
            tr.status-danger { background-color: #ffe3e3 !important; }
            tr.status-libur { background-color: #d3f9d8 !important; }
            tr.status-warning { background-color: #fff3cd !important; }
            .profile-card, .stat-box, .table-container { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

    <div class="profile-card">
        <div class="profile-info">
            <h2><?= $pegawai['nama'] ?></h2>
            <p>NIK: <?= $pegawai['nik'] ?> | <strong><?= $nama_departemen_tampil ?></strong></p>
            <p style="margin-top:2px;">Periode: <strong><?= date("d/m/Y", strtotime($tgl_awal)) ?> s/d <?= date("d/m/Y", strtotime($tgl_akhir)) ?></strong></p>
        </div>
        <div>
            <button class="btn-print" onclick="window.print()">🖨 Cetak</button>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-box box-hadir">
            <h3><?= $summary['hadir'] ?></h3>
            <span>Total Hadir</span>
        </div>
        <div class="stat-box box-telat">
            <h3><?= $summary['telat_freq'] ?>x</h3>
            <span>Kali Terlambat</span>
        </div>
        <div class="stat-box box-durasi">
            <h3><?= $summary['telat_menit'] ?></h3>
            <span>Menit (Setara <?= $info_durasi ?>)</span>
        </div>
        <div class="stat-box box-alpha">
            <h3><?= $summary['alpha'] ?></h3>
            <span>Tidak Masuk</span>
        </div>
        <div class="stat-box box-libur">
            <h3><?= $summary['libur'] ?></h3>
            <span>Hari Libur</span>
        </div>
    </div>

    <div class="table-container">
        <?php if(empty($tabel_final)): ?>
            <div style="padding: 30px; text-align: center; color: #aaa;">
                <h4>Belum ada jadwal kerja untuk range tanggal ini.</h4>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jadwal</th>
                        <th>Scan Masuk</th>
                        <th>Scan Pulang</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tabel_final as $row): 
                        $date_obj = strtotime($row['tanggal']);
                        $tgl_indo = date('d-m-Y', $date_obj);
                        $hari_list = ['Sun'=>'Minggu', 'Mon'=>'Senin', 'Tue'=>'Selasa', 'Wed'=>'Rabu', 'Thu'=>'Kamis', 'Fri'=>'Jumat', 'Sat'=>'Sabtu'];
                        $hari_indo = $hari_list[date('D', $date_obj)];
                    ?>
                    <tr class="<?= $row['css_class'] ?>">
                        <td><strong><?= $hari_indo ?></strong>, <?= $tgl_indo ?></td>
                        <td>
                            <span style="background:rgba(255,255,255,0.5); padding:2px 5px; border-radius:3px; font-size:11px; border:1px solid #ccc;">
                                <?= substr($row['jadwal_masuk'],0,5) ?> - <?= substr($row['jadwal_pulang'],0,5) ?>
                            </span>
                        </td>
                        <td style="font-weight:bold; color:#333;"><?= $row['scan_masuk'] ?></td>
                        <td><?= $row['scan_pulang'] ?></td>
                        <td>
                            <?php if($row['status'] == 'Terlambat'): ?>
                                <span class="badge">⚠️ Telat <?= $row['telat_info'] ?></span>
                            <?php elseif($row['status'] == 'Tepat Waktu'): ?>
                                <span class="badge">✔ Tepat Waktu</span>
                            <?php elseif(strpos($row['status'], 'Tidak Masuk') !== false): ?>
                                <span class="badge">✖ Tidak Masuk</span>
                            <?php elseif($row['status'] == 'Libur'): ?>
                                <span class="badge">☕ Libur</span>
                            <?php else: ?>
                                <span style="font-size:11px;"><?= $row['status'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="print-footer">
        Dicetak oleh Sistem pada: <strong><?= date('d F Y') ?></strong>, Pukul <strong><?= date('H:i:s') ?> WIB</strong>
    </div>

</body>
</html>