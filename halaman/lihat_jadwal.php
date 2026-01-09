<?php
include '../config/database.php';
include '../lib/fungsi_jadwal.php';

$data_jadwal = [];
// Jika tombol Filter ditekan
if (isset($_GET['filter'])) {
    $id_pegawai = $_GET['id_pegawai'];
    $bulan      = $_GET['bulan'];
    $tahun      = $_GET['tahun'];
    
    // Panggil fungsi sakti yang sudah kita buat
    $data_jadwal = get_jadwal_karyawan($koneksi, $id_pegawai, $bulan, $tahun);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lihat Jadwal Karyawan</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .box-filter { background: #f4f4f4; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #007bff; color: white; }
        .libur { background-color: #ffeeba; } /* Warna kuning untuk libur */
    </style>
</head>
<body>

    <h3>1. Cek Jadwal Karyawan</h3>
    
    <div class="box-filter">
        <form method="GET">
            <label>ID Pegawai:</label>
            <input type="text" name="id_pegawai" value="<?= isset($_GET['id_pegawai']) ? $_GET['id_pegawai'] : '137' ?>" required>
            
            <label>Bulan:</label>
            <select name="bulan">
                <option value="09">September</option>
                </select>
            
            <label>Tahun:</label>
            <input type="number" name="tahun" value="2024" required>
            
            <button type="submit" name="filter">Tampilkan Jadwal</button>
        </form>
    </div>

    <?php if(!empty($data_jadwal)): ?>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kode Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data_jadwal as $row): ?>
                    <tr class="<?= $row['status'] == 'Libur' ? 'libur' : '' ?>">
                        <td><?= $row['tanggal'] ?></td>
                        <td><?= $row['kode_shift'] ?></td>
                        <td><?= $row['jam_masuk'] ?></td>
                        <td><?= $row['jam_pulang'] ?></td>
                        <td><?= $row['status'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif(isset($_GET['filter'])): ?>
        <p style="color:red;">Data jadwal tidak ditemukan atau ID salah.</p>
    <?php endif; ?>

</body>
</html>