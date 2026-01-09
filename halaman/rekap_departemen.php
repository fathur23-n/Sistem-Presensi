<?php
// halaman/rekap_departemen.php (Versi Live Search JavaScript)
include '../config/database.php';
include '../lib/fungsi_jadwal.php';
include '../lib/fungsi_telat.php';
include '../lib/fungsi_data_absen.php';

$summary_laporan = [];
$dept_selected = '';
$nama_dept_label = ''; 
$bulan_pilih = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_pilih = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$list_bulan = [ '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September','10' => 'Oktober', '11' => 'November','12' => 'Desember' ];

$q_dept = mysqli_query($koneksi, "SELECT dep_id, nama FROM departemen ORDER BY nama ASC");

if (isset($_GET['filter'])) {
    $dept_selected = $_GET['departemen'];
    $bulan_pilih   = $_GET['bulan'];
    $tahun_pilih   = $_GET['tahun'];

    // Ambil Label Nama Dept
    $q_label = mysqli_query($koneksi, "SELECT nama FROM departemen WHERE dep_id = '$dept_selected'");
    $data_label = mysqli_fetch_assoc($q_label);
    $nama_dept_label = $data_label ? $data_label['nama'] : $dept_selected;

    // --- QUERY KEMBALI NORMAL (LOAD SEMUA) ---
    // Kita biarkan PHP mengambil SEMUA karyawan di dept ini.
    // Nanti filter namanya ditangani oleh JavaScript (Live Search).
    $q_karyawan = mysqli_query($koneksi, "SELECT * FROM pegawai WHERE departemen = '$dept_selected' ORDER BY nama ASC");

    while ($karyawan = mysqli_fetch_assoc($q_karyawan)) {
        $id_pegawai = $karyawan['id'];
        $list_jadwal = get_jadwal_karyawan($koneksi, $id_pegawai, $bulan_pilih, $tahun_pilih);
        $list_absen  = get_data_presensi($koneksi, $id_pegawai, $bulan_pilih, $tahun_pilih);

        $total_hadir = 0; $total_alpha = 0; $total_libur = 0; $total_telat_freq = 0; $total_telat_menit = 0;
        $status_data = 'Jadwal Blm Ada'; 

        if (!empty($list_jadwal)) {
            $status_data = 'Oke'; 
            foreach ($list_jadwal as $jadwal) {
                $tgl_ini = $jadwal['tanggal'];
                if (isset($list_absen[$tgl_ini])) {
                    $absen_row = $list_absen[$tgl_ini];
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
                    } else {
                        $total_libur++;
                    }
                }
            }
        } 

        $summary_laporan[] = [
            'id'          => $karyawan['id'],
            'nik'         => $karyawan['nik'],
            'nama'        => $karyawan['nama'],
            'hadir'       => $total_hadir,
            'alpha'       => $total_alpha,
            'kali_telat'  => $total_telat_freq,
            'total_menit' => $total_telat_menit,
            'status_data' => $status_data
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Rekap Departemen</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; background-color: #f4f6f9; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; }
        h3 { margin-top: 0; color: #333; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 10px; display:inline-block;}
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th { background-color: #343a40; color: white; padding: 12px; text-align: center; }
        td { border-bottom: 1px solid #dee2e6; padding: 10px; text-align: center; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-danger { background-color: #ffe3e3; color: #c92a2a; }
        .badge-warning { background-color: #fff3bf; color: #f08c00; }
        .badge-success { background-color: #d3f9d8; color: #2b8a3e; }
        .badge-secondary { background-color: #e2e3e5; color: #383d41; }
        
        .form-group { display: inline-block; margin-right: 15px; margin-bottom: 10px; vertical-align: top;}
        label { font-weight: 600; display: block; margin-bottom: 5px; color: #555; }
        select, button { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; min-width: 150px; }
        button { background-color: #007bff; color: white; border: none; cursor: pointer; margin-top: 23px; font-weight: bold;}
        button:hover { background-color: #0056b3; }
        
        /* Input Live Search yang Keren */
        #inputCari {
            padding: 8px 12px; 
            border: 2px solid #17a2b8; /* Border Biru Muda */
            border-radius: 20px; /* Rounded */
            min-width: 250px;
            outline: none;
            transition: 0.3s;
        }
        #inputCari:focus {
            box-shadow: 0 0 8px rgba(23, 162, 184, 0.4);
        }

        .btn-detail { background-color: #17a2b8; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; border:none; }
        .btn-detail:hover { background-color: #138496; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-icon { font-size: 40px; margin-bottom: 15px; display: block; }

        /* --- CSS MODAL --- */
        .modal {
            display: none; position: fixed; z-index: 999; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); 
        }
        .modal-content {
            background-color: #fefefe; margin: 2% auto; padding: 0; border: none;
            width: 80%; height: 90%; border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative; display: flex; flex-direction: column; overflow: hidden;
        }
        .modal-header {
            padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;
            text-align: right; display: flex; justify-content: flex-end; align-items: center;
        }
        .btn-close-modal {
            background-color: #dc3545; color: white; border: none; padding: 8px 15px;
            border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold;
            display: inline-flex; align-items: center; margin: 0; min-width: auto;
        }
        .btn-close-modal:hover { background-color: #c82333; }
        iframe { width: 100%; height: 100%; border: none; flex-grow: 1; }
        .btn-cetak-laporan {
            background-color: #28a745; /* Warna Hijau Excel */
            color: white;
            padding: 9px 15px; /* Padding disesuaikan biar sama tinggi dgn tombol Tampilkan */
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin-left: 10px; /* Jarak dari tombol Tampilkan */
            font-family: 'Segoe UI', sans-serif;
            font-size: 13.3px;
            border: 1px solid #218838;
        }

        .btn-cetak-laporan:hover {
            background-color: #218838;
            color: white;
        }

        .btn-back {
            text-decoration: none; display: inline-block; padding: 8px 15px;
            background-color: #6c757d; color: white; border-radius: 5px;
            font-size: 14px; font-weight: bold; margin-bottom: 20px;
        }
        .btn-back:hover { background-color: #5a6268; }
    </style>
</head>
<body>

<div class="card">
    <div style="margin-bottom: 15px;">
        <a href="../index.php" class="btn-back">🏠 Kembali ke Dashboard</a>
    </div>
    <h3>Filter Laporan Per Departemen</h3>
    <form method="GET">
        <div class="form-group">
            <label>Departemen:</label>
            <select name="departemen" required>
                <option value="">-- Pilih Departemen --</option>
                <?php while($d = mysqli_fetch_assoc($q_dept)): ?>
                    <option value="<?= $d['dep_id'] ?>" <?= ($dept_selected == $d['dep_id']) ? 'selected' : '' ?>>
                        <?= $d['nama'] ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Bulan:</label>
            <select name="bulan" required>
                <?php foreach($list_bulan as $key => $val): ?>
                    <option value="<?= $key ?>" <?= ($bulan_pilih == $key) ? 'selected' : '' ?>><?= $val ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Tahun:</label>
            <select name="tahun" required>
                <?php 
                $thn_sekarang = date('Y');
                for($t = $thn_sekarang - 1; $t <= $thn_sekarang; $t++): 
                ?>
                    <option value="<?= $t ?>" <?= ($tahun_pilih == $t) ? 'selected' : '' ?>><?= $t ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" name="filter">Tampilkan Data</button>
            
            <?php if(isset($_GET['filter'])): ?>
                <a href="cetak_laporan.php?departemen=<?= $dept_selected ?>&bulan=<?= $bulan_pilih ?>&tahun=<?= $tahun_pilih ?>&search_nama=<?= $search_nama ?>" 
                target="_blank" 
                class="btn-cetak-laporan">
                🖨 Cetak Laporan
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if(isset($_GET['filter'])): ?>
    <div class="card">
        <?php if(!empty($summary_laporan)): ?>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div>
                    <h4>
                        Departemen: <span style="color:#007bff;"><?= $nama_dept_label ?></span> | 
                        Periode: <?= $list_bulan[$bulan_pilih] ?> <?= $tahun_pilih ?>
                    </h4>
                </div>
                <div>
                    <input type="text" id="inputCari" onkeyup="fungsiCari()" placeholder="🔍 Ketik nama karyawan...">
                </div>
            </div>
            
            <table id="tabelKaryawan">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">NIK</th>
                        <th width="25%">Nama Pegawai</th>
                        <th width="10%">Hadir</th>
                        <th width="10%">Alpha</th>
                        <th width="20%">Keterlambatan</th>
                        <th width="15%">Status Data</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach($summary_laporan as $row): ?>
                    <tr class="baris-data">
                        <td><?= $no++ ?></td>
                        <td><?= $row['nik'] ?></td>
                        <td style="text-align:left; font-weight:500;" class="kolom-nama"><?= $row['nama'] ?></td>
                        <td><span class="<?= $row['hadir'] > 0 ? 'badge badge-success' : '' ?>"><?= $row['hadir'] ?></span></td>
                        <td><?= $row['alpha'] > 0 ? '<span class="badge badge-danger">'.$row['alpha'].'</span>' : '-' ?></td>
                        <td><?= $row['kali_telat'] > 0 ? '<span class="badge badge-warning">'.$row['kali_telat'].'x ('.$row['total_menit'].'m)</span>' : '-' ?></td>
                        <td>
                            <?php if($row['status_data'] == 'Oke'): ?>
                                <span style="color:green; font-weight:bold; font-size:12px;">&#10003; Ada</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Jadwal Kosong</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status_data'] == 'Oke'): ?>
                                <button class="btn-detail" 
                                    onclick="bukaModal('<?= $row['id'] ?>', '<?= $bulan_pilih ?>', '<?= $tahun_pilih ?>')">
                                    🔍 Detail
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p id="pesanKosong" style="display:none; text-align:center; color:#999; margin-top:20px;">
                Nama karyawan tidak ditemukan.
            </p>
            
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">📂</span>
                <h4>Data Karyawan Tidak Ditemukan</h4>
                <p>Tidak ada karyawan di departemen <strong><?= $nama_dept_label ?></strong>.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div id="modalPresensi" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <button class="btn-close-modal" onclick="tutupModal()">
                Tutup ❌
            </button>
        </div>
        <iframe id="iframeDetail" src=""></iframe>
    </div>
</div>

<script>
    // 1. FUNGSI LIVE SEARCH
    function fungsiCari() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("inputCari");
        filter = input.value.toUpperCase();
        table = document.getElementById("tabelKaryawan");
        tr = table.getElementsByClassName("baris-data");
        var ketemu = false;

        for (i = 0; i < tr.length; i++) {
            // Kolom Nama ada di index ke-2 (0=No, 1=NIK, 2=Nama)
            td = tr[i].getElementsByClassName("kolom-nama")[0];
            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = ""; // Tampilkan
                    ketemu = true;
                } else {
                    tr[i].style.display = "none"; // Sembunyikan
                }
            }       
        }

        // Tampilkan pesan jika tidak ada yang cocok
        if(!ketemu) {
            document.getElementById("pesanKosong").style.display = "block";
            table.style.display = "none"; // Sembunyikan header tabel biar rapi
        } else {
            document.getElementById("pesanKosong").style.display = "none";
            table.style.display = "table"; // Tampilkan tabel
        }
    }

    // 2. FUNGSI MODAL
    function bukaModal(id, bulan, tahun) {
        var modal = document.getElementById("modalPresensi");
        var iframe = document.getElementById("iframeDetail");
        iframe.src = "detail_popup.php?id=" + id + "&bulan=" + bulan + "&tahun=" + tahun;
        modal.style.display = "block";
    }

    function tutupModal() {
        document.getElementById("modalPresensi").style.display = "none";
        document.getElementById("iframeDetail").src = ""; 
    }

    window.onclick = function(event) {
        var modal = document.getElementById("modalPresensi");
        if (event.target == modal) {
            tutupModal();
        }
    }
</script>

</body>
</html>