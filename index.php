<?php
// index.php (FINAL ULTIMATE: DASHBOARD + FITUR LENGKAP + FOOTER + BACK TO TOP)

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. Include Koneksi
$root = __DIR__; 
include "$root/config/database.php"; 

// Set Timezone
date_default_timezone_set('Asia/Jakarta');

// --- FILTER INPUT ---
$tgl_awal  = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-d', strtotime('-1 day'));
$tgl_akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d', strtotime('-1 day'));
$dept_sel  = isset($_GET['departemen']) ? $_GET['departemen'] : '';

// Validasi Range
$diff = abs(strtotime($tgl_akhir) - strtotime($tgl_awal));
if(floor($diff / (365*60*60*24)) > 1) {
    $tgl_awal = date('Y-m-d'); $tgl_akhir = date('Y-m-d');
}

if (!$koneksi) { die("Gagal koneksi database."); }

// --- 0.1 DATA MASTER SHIFT ---
$shift_map = [];
$q_shift = mysqli_query($koneksi, "SELECT shift, jam_masuk, jam_pulang FROM jam_jaga");
if($q_shift) {
    while($s = mysqli_fetch_assoc($q_shift)) {
        $k = strtolower(trim($s['shift'])); 
        $jam_str = date('H:i', strtotime($s['jam_masuk'])) . ' - ' . date('H:i', strtotime($s['jam_pulang']));
        $shift_map[$k] = $jam_str;
    }
}

// --- 0.2 DATA PEGAWAI ---
$sql_peg = "SELECT p.id, p.nik, p.nama, d.nama AS nama_dept 
            FROM pegawai p
            LEFT JOIN departemen d ON p.departemen = d.dep_id";

if($dept_sel != '') { $sql_peg .= " WHERE p.departemen = '$dept_sel'"; }

$q_peg = mysqli_query($koneksi, $sql_peg);

$all_pegawai = []; 
$pegawai_info = []; 

while($p = mysqli_fetch_assoc($q_peg)) {
    $all_pegawai[] = $p['id'];
    $pegawai_info[$p['id']] = [
        'nik'  => $p['nik'],
        'nama' => $p['nama'],
        'dept' => $p['nama_dept'] ?? '-' 
    ];
}
$total_karyawan = count($all_pegawai);


// --- 1. AMBIL DATA PRESENSI ---
$presensi_map = []; 
$total_hadir_real = 0;
$total_telat_real = 0;

$sql_absen = "SELECT r.id, r.jam_datang, r.status 
              FROM rekap_presensi r 
              JOIN pegawai p ON r.id = p.id
              WHERE DATE(r.jam_datang) BETWEEN '$tgl_awal' AND '$tgl_akhir'";

if($dept_sel != '') { $sql_absen .= " AND p.departemen = '$dept_sel'"; }

$q_absen = mysqli_query($koneksi, $sql_absen);
while($r = mysqli_fetch_assoc($q_absen)) {
    $tgl_only = date('Y-m-d', strtotime($r['jam_datang']));
    $key = $tgl_only . "_" . $r['id'];
    
    if(!isset($presensi_map[$key])) {
        $presensi_map[$key] = $r['status']; 
        $total_hadir_real++; 
        if(strpos($r['status'], 'Terlambat') !== false) {
            $total_telat_real++;
        }
    }
}


// --- 2. LOGIKA HITUNG DETAIL ---
$total_libur = 0;
$total_alpha = 0; 

$list_telat = [];
$list_alpha = [];
$list_libur = [];
$ranking_pegawai = []; 

$streak_track = [];
$alert_telat_3x = [];
$alert_alpha_2x = [];

$period = new DatePeriod(
    new DateTime($tgl_awal),
    new DateInterval('P1D'),
    (new DateTime($tgl_akhir))->modify('+1 day')
);

// Cache Jadwal
$jadwal_db = []; 
$months_loaded = [];

foreach ($period as $dt) {
    $m = $dt->format('m'); $y = $dt->format('Y'); $key = "$m-$y";
    if(!in_array($key, $months_loaded)) {
        $qj = mysqli_query($koneksi, "SELECT id, h1, h2, h3, h4, h5, h6, h7, h8, h9, h10, 
                                      h11, h12, h13, h14, h15, h16, h17, h18, h19, h20, 
                                      h21, h22, h23, h24, h25, h26, h27, h28, h29, h30, h31 
                                      FROM jadwal_pegawai WHERE bulan='$m' AND tahun='$y'");
        while($rj = mysqli_fetch_assoc($qj)) {
            $jadwal_db[$m][$y][$rj['id']] = $rj;
        }
        $months_loaded[] = $key;
    }
}

// LOOPING UTAMA
foreach ($period as $dt) {
    $d = $dt->format('j'); $m = $dt->format('m'); $y = $dt->format('Y');
    $tgl_full = $dt->format('Y-m-d');
    $tgl_view = $dt->format('d/m/Y');
    $col = "h" . $d; 
    
    foreach($all_pegawai as $id_peg) {
        if(!isset($ranking_pegawai[$id_peg])) {
            $ranking_pegawai[$id_peg] = [
                'id' => $id_peg, 'nik' => $pegawai_info[$id_peg]['nik'], 'nama' => $pegawai_info[$id_peg]['nama'], 'dept' => $pegawai_info[$id_peg]['dept'],
                'hadir_tepat' => 0, 'telat' => 0, 'alpha' => 0
            ];
        }
        if(!isset($streak_track[$id_peg])) {
            $streak_track[$id_peg] = ['telat' => 0, 'alpha' => 0];
        }

        $isi_jadwal = isset($jadwal_db[$m][$y][$id_peg][$col]) ? $jadwal_db[$m][$y][$id_peg][$col] : '';
        $isi_jadwal_clean = strtolower(trim($isi_jadwal)); 
        $is_libur_jadwal = in_array($isi_jadwal_clean, ['off', 'l', 'libur', 'lepas', 'minggu', '-', '']);
        
        $key_cek = $tgl_full . "_" . $id_peg;
        $status_absen = isset($presensi_map[$key_cek]) ? $presensi_map[$key_cek] : null;
        $is_hadir_absen = ($status_absen !== null);
        
        $p_info = $pegawai_info[$id_peg];

        // Format Visual Shift
        if(!$is_libur_jadwal && $isi_jadwal != '' && isset($shift_map[$isi_jadwal_clean])) {
            $jam_shift = $shift_map[$isi_jadwal_clean];
            $tampilan_shift = "<div class='shift-capsule'><span class='shift-name'>".strtoupper($isi_jadwal)."</span><span class='shift-divider'></span><span class='shift-time'><i class='far fa-clock me-1'></i> $jam_shift</span></div>";
        } elseif(!$is_libur_jadwal && $isi_jadwal != '') {
             $tampilan_shift = "<span class='badge bg-secondary rounded-pill'>".strtoupper($isi_jadwal)."</span>";
        } else {
             $tampilan_shift = "<span class='badge bg-light text-muted border'>".strtoupper($isi_jadwal ?: 'TIDAK ADA JADWAL')."</span>";
        }

        if ($is_libur_jadwal) {
            $total_libur++;
            $list_libur[] = ['tgl' => $tgl_view, 'nik' => $p_info['nik'], 'nama' => $p_info['nama'], 'dept' => $p_info['dept'], 'ket' => $tampilan_shift];
        } else {
            if (!$is_hadir_absen) {
                $total_alpha++; 
                $ranking_pegawai[$id_peg]['alpha']++;
                $list_alpha[] = ['tgl' => $tgl_view, 'nik' => $p_info['nik'], 'nama' => $p_info['nama'], 'dept' => $p_info['dept'], 'shift' => $tampilan_shift];
                
                $streak_track[$id_peg]['alpha']++;
                $streak_track[$id_peg]['telat'] = 0; 
            } else {
                if(strpos($status_absen, 'Terlambat') !== false) {
                    $ranking_pegawai[$id_peg]['telat']++;
                    $list_telat[] = ['tgl' => $tgl_view, 'nik' => $p_info['nik'], 'nama' => $p_info['nama'], 'dept' => $p_info['dept'], 'status' => $status_absen];
                    
                    $streak_track[$id_peg]['telat']++;
                    $streak_track[$id_peg]['alpha'] = 0; 
                } else {
                    $ranking_pegawai[$id_peg]['hadir_tepat']++; 
                    $streak_track[$id_peg]['telat'] = 0;
                    $streak_track[$id_peg]['alpha'] = 0;
                }
            }
        }

        if($streak_track[$id_peg]['telat'] >= 3) { $alert_telat_3x[$id_peg] = $p_info; $alert_telat_3x[$id_peg]['streak'] = $streak_track[$id_peg]['telat']; }
        if($streak_track[$id_peg]['alpha'] >= 2) { $alert_alpha_2x[$id_peg] = $p_info; $alert_alpha_2x[$id_peg]['streak'] = $streak_track[$id_peg]['alpha']; }
    }
}

// RANKING TOP 3
usort($ranking_pegawai, function($a, $b) {
    if ($a['hadir_tepat'] == $b['hadir_tepat']) { return $a['telat'] - $b['telat']; }
    return $b['hadir_tepat'] - $a['hadir_tepat']; 
});
$top_3_karyawan = array_slice($ranking_pegawai, 0, 3);


// --- 3. HITUNG PERSENTASE ---
$total_sampel = $total_hadir_real + $total_alpha + $total_libur;
$p_hadir = ($total_sampel > 0) ? round(($total_hadir_real / $total_sampel) * 100, 1) : 0;
$p_telat = ($total_hadir_real > 0) ? round(($total_telat_real / $total_hadir_real) * 100, 1) : 0; 
$p_libur = ($total_sampel > 0) ? round(($total_libur / $total_sampel) * 100, 1) : 0;
$p_alpha = ($total_sampel > 0) ? round(($total_alpha / $total_sampel) * 100, 1) : 0;


// --- 4. GRAFIK TREN ---
$grafik_label = []; $grafik_data = []; $data_harian_db = [];
$sql_grafik = "SELECT DATE(r.jam_datang) as tgl, COUNT(DISTINCT r.id) as total 
               FROM rekap_presensi r JOIN pegawai p ON r.id = p.id
               WHERE DATE(r.jam_datang) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
if($dept_sel != '') { $sql_grafik .= " AND p.departemen = '$dept_sel'"; }
$sql_grafik .= " GROUP BY DATE(r.jam_datang)";
$q_grafik = mysqli_query($koneksi, $sql_grafik);
while($row = mysqli_fetch_assoc($q_grafik)) { $data_harian_db[$row['tgl']] = $row['total']; }
foreach ($period as $dt) {
    $c = $dt->format('Y-m-d');
    $grafik_label[] = $dt->format('d M');
    $grafik_data[] = isset($data_harian_db[$c]) ? $data_harian_db[$c] : 0;
}

$q_list_dept = mysqli_query($koneksi, "SELECT dep_id, nama FROM departemen ORDER BY nama ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Presensi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; padding-bottom: 80px; } /* Space for Footer */
        .dashboard-header { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.03); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: flex-end; border-left: 5px solid #0d6efd; flex-wrap: wrap; gap: 15px; }
        .menu-card { background: white; border: none; border-radius: 12px; padding: 25px; text-align: center; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: block; text-decoration: none; color: #555; border: 1px solid #f0f0f0; height: 100%; }
        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .menu-card i { font-size: 35px; margin-bottom: 15px; }
        .stat-card { border-radius: 12px; color: white; padding: 20px; position: relative; overflow: hidden; height: 100%; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-card h3 { font-weight: 800; font-size: 2.2rem; margin: 5px 0 0 0; }
        .stat-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 3rem; opacity: 0.2; }
        
        .bg-grad-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); } 
        .bg-grad-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); } 
        .bg-grad-info    { background: linear-gradient(135deg, #36D1DC 0%, #5B86E5 100%); } 
        .bg-grad-danger  { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); } 
        .bg-grad-warning { background: linear-gradient(135deg, #fce38a 0%, #f38181 100%); }

        .filter-box { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 11px; font-weight: bold; color: #666; margin-bottom: 3px; }
        .filter-group input, .filter-group select { border: 1px solid #ddd; padding: 6px 10px; border-radius: 6px; font-size: 13px; min-width: 130px; }
        .btn-filter { background: #0d6efd; color: white; border: none; padding: 7px 20px; border-radius: 6px; font-weight: bold; font-size: 13px; height: 34px; margin-bottom: 1px;}
        
        .chart-label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; display: flex; justify-content: space-between; }
        .progress { height: 20px; border-radius: 10px; margin-bottom: 12px; background-color: #e9ecef; }
        .progress-bar { font-size: 11px; font-weight: bold; line-height: 20px; }

        .nav-tabs .nav-link { color: #555; font-weight: 600; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        .table-list { font-size: 13px; white-space: nowrap; }
        .table-list th { background-color: #f8f9fa; vertical-align: middle; }
        .sensitive-data { transition: 0.3s; }
        .blur-text { letter-spacing: 2px; filter: blur(4px); }

        .shift-capsule { display: inline-flex; align-items: center; background-color: #f0f4ff; border: 1px solid #dbeafe; border-radius: 50px; padding: 4px 12px; color: #1e3a8a; font-size: 12px; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .shift-name { font-weight: 800; letter-spacing: 0.5px; }
        .shift-divider { height: 12px; width: 1px; background-color: #bfdbfe; margin: 0 8px; }
        .shift-time { font-weight: 500; opacity: 0.85; }

        .rank-card { transition: 0.3s; border: none; border-radius: 15px; position: relative; overflow: hidden; }
        .rank-card:hover { transform: translateY(-5px); }
        .rank-1 { background: linear-gradient(135deg, #FFF9C4 0%, #FFF176 100%); border-bottom: 5px solid #FBC02D; color: #555; }
        .rank-2 { background: linear-gradient(135deg, #F5F5F5 0%, #E0E0E0 100%); border-bottom: 5px solid #9E9E9E; color: #555; }
        .rank-3 { background: linear-gradient(135deg, #D7CCC8 0%, #BCAAA4 100%); border-bottom: 5px solid #8D6E63; color: #555; }
        
        .search-container { position: relative; }
        .search-input { padding-left: 35px; border-radius: 20px; border: 1px solid #ced4da; font-size: 13px; width: 250px; transition: 0.3s; }
        .search-input:focus { width: 300px; box-shadow: 0 0 5px rgba(13, 110, 253, 0.3); border-color: #86b7fe; outline: none; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px; }
        .no-search-data td { background-color: #fdfdfd; }
        
        .alert-sdm-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); background: white; margin-bottom: 20px; }
        .alert-sdm-header { padding: 15px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; }
        .alert-sdm-title { font-weight: bold; margin: 0; font-size: 15px; }
        .alert-sdm-body { padding: 0; max-height: 250px; overflow-y: auto; }
        .alert-item { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .alert-item:last-child { border-bottom: none; }
        .alert-item:hover { background-color: #f9f9f9; }

        /* FOOTER STYLE */
        .main-footer { position: fixed; left: 0; bottom: 0; width: 100%; background-color: #fff; text-align: center; padding: 15px; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); font-size: 12px; font-weight: bold; color: #555; z-index: 999; border-top: 1px solid #eee; }
        
        /* BACK TO TOP */
        .btn-back-to-top {
            position: fixed; bottom: 80px; right: 25px;
            width: 50px; height: 50px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            font-size: 20px; cursor: pointer; z-index: 1000;
            display: none; transition: all 0.3s ease;
            align-items: center; justify-content: center;
        }
        .btn-back-to-top:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(13, 110, 253, 0.4); }

        /* MOBILE RESPONSIVE */
        @media (max-width: 768px) {
            .dashboard-header { flex-direction: column; align-items: stretch !important; text-align: center; }
            .filter-box { flex-direction: column; width: 100%; }
            .filter-group { width: 100%; margin-bottom: 10px; }
            .filter-group input, .filter-group select { width: 100%; }
            .btn-filter { width: 100%; margin-top: 5px; }
            .stat-card { margin-bottom: 10px; }
            .search-container { width: 100%; margin-bottom: 10px; }
            .search-input, .search-input:focus { width: 100%; }
            .rank-card { margin-bottom: 15px; }
            #chartDirektur, #chartTren, #chartPie { max-height: 250px; }
            .table-responsive:before { content: '← Geser untuk melihat detail →'; display: block; text-align: center; font-size: 10px; color: #999; margin-bottom: 5px; font-style: italic;}
        }
    </style>
</head>
<body>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="dashboard-header">
        <div>
            <h4 class="fw-bold text-dark m-0">Dashboard Monitoring</h4>
            <p class="text-muted m-0" style="font-size:13px;">
                <i class="fas fa-calendar-check me-1"></i> Periode: <b><?= date('d M Y', strtotime($tgl_awal)) ?></b> s/d <b><?= date('d M Y', strtotime($tgl_akhir)) ?></b>
                <?php if($dept_sel) echo " | Filter Dept ID: <b>$dept_sel</b>"; ?>
            </p>
        </div>
        <form method="GET" class="filter-box">
            <div class="filter-group"><label>Departemen:</label><select name="departemen"><option value="">-- Semua Departemen --</option><?php while($dp = mysqli_fetch_assoc($q_list_dept)): ?><option value="<?= $dp['dep_id'] ?>" <?= ($dept_sel == $dp['dep_id']) ? 'selected' : '' ?>><?= $dp['nama'] ?></option><?php endwhile; ?></select></div>
            <div class="filter-group"><label>Dari:</label><input type="date" name="awal" value="<?= $tgl_awal ?>"></div>
            <div class="filter-group"><label>Sampai:</label><input type="date" name="akhir" value="<?= $tgl_akhir ?>"></div>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 mb-3 mb-md-0"><a href="halaman/rekap_departemen.php" class="menu-card text-primary"><i class="fas fa-building"></i><h5 class="fw-bold m-0">Laporan Per Departemen</h5><p class="small text-muted mt-2">Lihat rekapitulasi standar bulanan</p></a></div>
        <div class="col-md-6"><a href="halaman/rekap_periode.php" class="menu-card text-warning"><i class="fas fa-calendar-alt"></i><h5 class="fw-bold m-0">Laporan Custom Periode</h5><p class="small text-muted mt-2">Lihat rekap berdasarkan range tanggal</p></a></div>
    </div>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card bg-grad-primary"><span class="small fw-bold text-uppercase opacity-75">Total Pegawai</span><h3><?= number_format($total_karyawan) ?></h3><i class="fas fa-id-badge stat-icon"></i></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card bg-grad-success"><span class="small fw-bold text-uppercase opacity-75">Hadir (Real)</span><h3><?= number_format($total_hadir_real) ?></h3><i class="fas fa-user-check stat-icon"></i></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card bg-grad-info"><span class="small fw-bold text-uppercase opacity-75">Libur / Off</span><h3><?= number_format($total_libur) ?></h3><i class="fas fa-umbrella-beach stat-icon"></i></div></div>
        <div class="col-md-3"><div class="stat-card bg-grad-danger"><span class="small fw-bold text-uppercase opacity-75">Tidak Masuk (Alpha)</span><h3><?= number_format($total_alpha) ?></h3><i class="fas fa-user-times stat-icon"></i></div></div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-4"><i class="fas fa-chart-pie text-primary me-2"></i> Ringkasan Persentase</h5>
                <div class="row align-items-center">
                    <div class="col-md-8 mb-4 mb-md-0"><canvas id="chartDirektur" style="height: 220px;"></canvas></div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="mb-3"><div class="chart-label">Hadir <span class="text-success"><?= $p_hadir ?>%</span></div><div class="progress"><div class="progress-bar bg-success" style="width: <?= $p_hadir ?>%"><?= $p_hadir ?>%</div></div></div>
                            <div class="mb-3"><div class="chart-label">Keterlambatan <span class="text-warning"><?= $p_telat ?>%</span></div><div class="progress"><div class="progress-bar bg-warning text-dark" style="width: <?= $p_telat ?>%"><?= $p_telat ?>%</div></div><small class="text-muted" style="font-size:10px;">*Persentase dari total yang hadir</small></div>
                            <div class="mb-3"><div class="chart-label">Libur / Off <span class="text-info"><?= $p_libur ?>%</span></div><div class="progress"><div class="progress-bar bg-info" style="width: <?= $p_libur ?>%"><?= $p_libur ?>%</div></div></div>
                            <div><div class="chart-label">Tidak Masuk (Alpha) <span class="text-danger"><?= $p_alpha ?>%</span></div><div class="progress"><div class="progress-bar bg-danger" style="width: <?= $p_alpha ?>%"><?= $p_alpha ?>%</div></div></div>
                        </div>
                    </div>
                </div>
            </div></div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold m-0"><i class="fas fa-list-ul text-secondary me-2"></i> Rincian Karyawan</h5>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="flex:1; justify-content: flex-end;">
                        <div class="search-container"><i class="fas fa-search search-icon"></i><input type="text" id="liveSearch" class="form-control search-input" placeholder="Cari nama, nik, departemen..."></div>
                        <button class="btn btn-outline-dark btn-sm rounded-pill px-3" onclick="togglePrivacy()" id="btnPrivacy"><i class="fas fa-eye-slash"></i> Sensor</button>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                        <li class="nav-item"><button class="nav-link active text-danger" data-bs-toggle="tab" data-bs-target="#tabAlpha">❌ Tidak Masuk (<?= count($list_alpha) ?>)</button></li>
                        <li class="nav-item"><button class="nav-link text-warning" data-bs-toggle="tab" data-bs-target="#tabTelat">⚠️ Terlambat (<?= count($list_telat) ?>)</button></li>
                        <li class="nav-item"><button class="nav-link text-info" data-bs-toggle="tab" data-bs-target="#tabLibur">🏖️ Libur / Off (<?= count($list_libur) ?>)</button></li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="tabAlpha">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-hover table-list"><thead class="sticky-top"><tr><th>Tanggal</th><th>NIK</th><th>Nama Karyawan</th><th>Departemen</th><th>Shift Dijadwalkan</th></tr></thead><tbody><?php if(empty($list_alpha)): ?><tr><td colspan="5" class="text-center">Tidak ada data alpha. Bagus!</td></tr><?php endif; ?><?php foreach($list_alpha as $row): ?><tr><td><?= $row['tgl'] ?></td><td><span class="sensitive-data" data-real="<?= $row['nik'] ?>"><?= $row['nik'] ?></span></td><td><span class="sensitive-data fw-bold" data-real="<?= $row['nama'] ?>"><?= $row['nama'] ?></span></td><td><?= $row['dept'] ?></td><td><?= $row['shift'] ?></td></tr><?php endforeach; ?><tr class="no-search-data" style="display: none;"><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-folder-open mb-2" style="font-size: 2rem; opacity: 0.5;"></i><br>Data karyawan tidak ditemukan...</td></tr></tbody></table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tabTelat">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-hover table-list"><thead class="sticky-top"><tr><th>Tanggal</th><th>NIK</th><th>Nama Karyawan</th><th>Departemen</th><th>Status</th></tr></thead><tbody><?php if(empty($list_telat)): ?><tr><td colspan="5" class="text-center">Tidak ada keterlambatan.</td></tr><?php endif; ?><?php foreach($list_telat as $row): ?><tr><td><?= $row['tgl'] ?></td><td><span class="sensitive-data" data-real="<?= $row['nik'] ?>"><?= $row['nik'] ?></span></td><td><span class="sensitive-data fw-bold" data-real="<?= $row['nama'] ?>"><?= $row['nama'] ?></span></td><td><?= $row['dept'] ?></td><td class="text-danger fw-bold"><?= $row['status'] ?></td></tr><?php endforeach; ?><tr class="no-search-data" style="display: none;"><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-folder-open mb-2" style="font-size: 2rem; opacity: 0.5;"></i><br>Data karyawan tidak ditemukan...</td></tr></tbody></table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tabLibur">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-hover table-list"><thead class="sticky-top"><tr><th>Tanggal</th><th>NIK</th><th>Nama Karyawan</th><th>Departemen</th><th>Keterangan</th></tr></thead><tbody><?php foreach($list_libur as $row): ?><tr><td><?= $row['tgl'] ?></td><td><span class="sensitive-data" data-real="<?= $row['nik'] ?>"><?= $row['nik'] ?></span></td><td><span class="sensitive-data fw-bold" data-real="<?= $row['nama'] ?>"><?= $row['nama'] ?></span></td><td><?= $row['dept'] ?></td><td><?= $row['ket'] ?></td></tr><?php endforeach; ?><tr class="no-search-data" style="display: none;"><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-folder-open mb-2" style="font-size: 2rem; opacity: 0.5;"></i><br>Data karyawan tidak ditemukan...</td></tr></tbody></table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(!empty($top_3_karyawan)): ?>
    <div class="row mb-5 justify-content-center">
        <div class="col-12 text-center mb-4"><h4 class="fw-bold text-dark"><i class="fas fa-trophy text-warning"></i> TOP 3 KARYAWAN TELADAN</h4><p class="text-muted small">Berdasarkan Kehadiran Tepat Waktu Terbanyak</p></div>
        <?php $rank_classes = [1 => 'rank-1', 2 => 'rank-2', 3 => 'rank-3']; $rank_titles = [1 => 'JUARA 1 🥇', 2 => 'JUARA 2 🥈', 3 => 'JUARA 3 🥉']; $i = 1; foreach($top_3_karyawan as $winner): if($winner['hadir_tepat'] == 0) continue; ?>
        <div class="col-md-4 mb-3"><div class="card rank-card <?= $rank_classes[$i] ?> text-center p-4 h-100 shadow-sm"><div class="mb-2"><h5 class="fw-bold m-0"><?= $rank_titles[$i] ?></h5></div><div class="card-body p-0"><h4 class="fw-bold mt-3 sensitive-data" data-real="<?= $winner['nama'] ?>"><?= $winner['nama'] ?></h4><p class="mb-1 sensitive-data" data-real="<?= $winner['nik'] ?>"><?= $winner['nik'] ?></p><span class="badge bg-dark mb-3"><?= $winner['dept'] ?></span><div class="row mt-2 border-top pt-3"><div class="col-6 border-end"><h5 class="fw-bold text-success m-0"><?= $winner['hadir_tepat'] ?></h5><small>Tepat Waktu</small></div><div class="col-6"><h5 class="fw-bold text-danger m-0"><?= $winner['telat'] ?></h5><small>Terlambat</small></div></div></div></div></div>
        <?php $i++; endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row mb-5">
        <div class="col-12 mb-3"><h5 class="fw-bold text-dark border-bottom pb-2">📋 Evaluasi Kedisiplinan SDM</h5></div>
        <div class="col-md-6 mb-3">
            <div class="alert-sdm-card"><div class="alert-sdm-header bg-warning bg-opacity-10 text-warning"><h6 class="alert-sdm-title"><i class="fas fa-exclamation-triangle me-2"></i> Terlambat ≥ 3x Berturut</h6><span class="badge bg-warning text-dark"><?= count($alert_telat_3x) ?> Orang</span></div><div class="alert-sdm-body"><?php if(empty($alert_telat_3x)): ?><div class="text-center p-4 text-muted small">Tidak ada karyawan yang sering terlambat.</div><?php else: ?><?php foreach($alert_telat_3x as $alert): ?><div class="alert-item"><div><div class="fw-bold sensitive-data" data-real="<?= $alert['nama'] ?>" style="font-size:13px;"><?= $alert['nama'] ?></div><div class="small text-muted sensitive-data" data-real="<?= $alert['nik'] ?>" style="font-size:11px;"><?= $alert['nik'] ?> - <?= $alert['dept'] ?></div></div><span class="badge bg-warning text-dark">Streak: <?= $alert['streak'] ?>x</span></div><?php endforeach; ?><?php endif; ?></div></div>
        </div>
        <div class="col-md-6">
            <div class="alert-sdm-card"><div class="alert-sdm-header bg-danger bg-opacity-10 text-danger"><h6 class="alert-sdm-title"><i class="fas fa-user-times me-2"></i> Tidak Masuk ≥ 2x Berturut</h6><span class="badge bg-danger"><?= count($alert_alpha_2x) ?> Orang</span></div><div class="alert-sdm-body"><?php if(empty($alert_alpha_2x)): ?><div class="text-center p-4 text-muted small">Semua karyawan rajin masuk.</div><?php else: ?><?php foreach($alert_alpha_2x as $alert): ?><div class="alert-item"><div><div class="fw-bold sensitive-data" data-real="<?= $alert['nama'] ?>" style="font-size:13px;"><?= $alert['nama'] ?></div><div class="small text-muted sensitive-data" data-real="<?= $alert['nik'] ?>" style="font-size:11px;"><?= $alert['nik'] ?> - <?= $alert['dept'] ?></div></div><span class="badge bg-danger">Streak: <?= $alert['streak'] ?>x</span></div><?php endforeach; ?><?php endif; ?></div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8 mb-3 mb-md-0"><div class="card h-100 border-0 shadow-sm rounded-4"><div class="card-body"><h6 class="fw-bold mb-3">Tren Kehadiran Harian</h6><div style="height: 300px;"><canvas id="chartTren"></canvas></div></div></div></div>
        <div class="col-lg-4"><div class="card h-100 border-0 shadow-sm rounded-4"><div class="card-body"><h6 class="fw-bold mb-3">Kualitas Kedatangan</h6><div style="height: 250px; display:flex; justify-content:center;"><canvas id="chartPie"></canvas></div></div></div></div>
    </div>

</div>

<button id="btnBackToTop" class="btn-back-to-top" title="Kembali ke Atas"><i class="fas fa-arrow-up"></i></button>

<div class="main-footer">Create By IT. RSEM &copy; 2026</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    
    // CHART DIREKTUR
    new Chart(document.getElementById('chartDirektur'), {
        type: 'bar',
        data: {
            labels: ['Hadir', 'Telat', 'Libur/Off', 'Tidak Masuk'],
            datasets: [{
                label: 'Persentase (%)',
                data: [<?= $p_hadir ?>, <?= $p_telat ?>, <?= $p_libur ?>, <?= $p_alpha ?>],
                backgroundColor: ['rgba(25, 135, 84, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(13, 202, 240, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                borderColor: ['rgb(25, 135, 84)', 'rgb(255, 193, 7)', 'rgb(13, 202, 240)', 'rgb(220, 53, 69)'],
                borderWidth: 1, borderRadius: 5
            }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return c.raw + '%'; } } } }, scales: { x: { beginAtZero: true, max: 100, grid: { borderDash: [2, 2] } }, y: { grid: { display: false } } } }
    });

    // CHART TREN
    new Chart(document.getElementById('chartTren'), {
        type: 'line',
        data: {
            labels: <?= json_encode($grafik_label) ?>,
            datasets: [{
                label: 'Hadir', data: <?= json_encode($grafik_data) ?>,
                borderColor: '#4e54c8', backgroundColor: 'rgba(78, 84, 200, 0.1)', borderWidth: 2, tension: 0.3, fill: true, pointRadius: 3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    // CHART PIE
    new Chart(document.getElementById('chartPie'), {
        type: 'doughnut',
        data: {
            labels: ['Tepat Waktu', 'Terlambat'],
            datasets: [{
                data: [<?= max(0, $total_hadir_real - $total_telat_real) ?>, <?= $total_telat_real ?>],
                backgroundColor: ['#11998e', '#fce38a'], borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '70%' }
    });

    // JS PRIVACY TOGGLE
    var isPrivacyOn = false;
    function togglePrivacy() {
        isPrivacyOn = !isPrivacyOn;
        var btn = document.getElementById('btnPrivacy');
        var elements = document.querySelectorAll('.sensitive-data');
        if (isPrivacyOn) {
            btn.innerHTML = '<i class="fas fa-eye"></i> Tampilkan Nama & NIK';
            btn.classList.replace('btn-outline-dark', 'btn-dark');
            elements.forEach(function(el) { el.innerText = '********'; el.classList.add('blur-text'); });
        } else {
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> Sensor';
            btn.classList.replace('btn-dark', 'btn-outline-dark');
            elements.forEach(function(el) { el.innerText = el.getAttribute('data-real'); el.classList.remove('blur-text'); });
        }
    }

    // LIVE SEARCH FUNCTION
    document.getElementById('liveSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let activePane = document.querySelector('.tab-pane.active');
        let rows = activePane.querySelectorAll('tbody tr:not(.no-search-data)');
        let noDataRow = activePane.querySelector('.no-search-data');
        let visibleCount = 0;

        rows.forEach(row => {
            let text = row.innerText.toLowerCase();
            let realData = "";
            row.querySelectorAll('[data-real]').forEach(el => {
                realData += el.getAttribute('data-real').toLowerCase() + " ";
            });

            if (text.includes(filter) || realData.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount === 0) { if(noDataRow) noDataRow.style.display = ''; } else { if(noDataRow) noDataRow.style.display = 'none'; }
    });

    // BACK TO TOP LOGIC
    const btnBackToTop = document.getElementById('btnBackToTop');
    window.onscroll = function() {
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            btnBackToTop.style.display = "flex";
        } else {
            btnBackToTop.style.display = "none";
        }
    };
    btnBackToTop.addEventListener("click", function() {
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
</script>

</body>
</html>