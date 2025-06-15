<?php
// Koneksi ke Database
$host = "localhost"; // Ganti dengan host database Anda
$user = "root";      // Ganti dengan username MySQL Anda
$password = "";      // Ganti dengan password MySQL Anda
$dbname = "ims";     // Ganti dengan nama database Anda

// Membuat koneksi
$conn = new mysqli($host, $user, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Proses jika form status dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_dbr = $conn->real_escape_string($_POST['no_dbr']);
    $konfirmasi_kasubbag_tu = $conn->real_escape_string($_POST['konfirmasi_kasubbag_tu']);

    // Update status permintaan perbaikan
    $update_status_sql = "UPDATE alat_rusak SET konfirmasi_kasubbag_tu = ? WHERE no_dbr = ?";
    $stmt = $conn->prepare($update_status_sql);
    if ($stmt === false) {
        die('Prepare statement failed: ' . $conn->error);
    }
    $stmt->bind_param("si", $konfirmasi_kasubbag_tu, $no_dbr);
    
    if (!$stmt->execute()) {
        die("Error executing query: " . $stmt->error);
    }
    
    // Jika disetujui, update kondisi alat
    if ($konfirmasi_kasubbag_tu === 'disetujui') {
        // Ambil kode_barang dan nup dari no_dbr
        // Misalkan no_dbr memiliki format "kode_barang-nup"
        list($kode_barang, $nup) = explode('-', $no_dbr);

        $update_kondisi_sql = "UPDATE alat SET kondisi = 'sedang diperbaiki' WHERE kode_barang = ? AND nup = ?";
        $stmt_kondisi = $conn->prepare($update_kondisi_sql);
        $stmt_kondisi->bind_param("si", $kode_barang, $nup);
        if (!$stmt_kondisi->execute()) {
            die("Error updating kondisi alat: " . $stmt_kondisi->error);
        }
        $stmt_kondisi->close();
    }
    
    $stmt->close();

    // Redirect agar data tidak diproses ulang jika refresh halaman
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Mengambil data permintaan perbaikan
$sql = "SELECT nama_alat_barang, no_dbr, tanggal_kerusakan, jenis_kerusakan, konfirmasi_kasubbag_tu, nama_pengajuan FROM alat_rusak";
$result = $conn->query($sql);

// Cek apakah query berhasil
if ($result === false) {
    die("Error executing query: " . $conn->error);
}

// Menutup koneksi setelah selesai
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alat</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS styles here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #2c3e50;
            color: white;
            position: fixed;
            left: -250px;
            top: 0;
            transition: 0.3s;
            padding-top: 20px;
            z-index: 1000; /* Pastikan sidebar tetap di atas */
        }

        .sidebar-header {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #ddd;
            margin-top: -10px;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 10px auto;
        }

        .sidebar-title {
            margin-top: 10px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
        }

        .sidebar a {
            display: block;
            padding: 15px;
            color: white;
            text-decoration: none;
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #34495e;
        }

        .sidebar .toggle-btn {
            position: absolute;
            top: 20px;
            right: -40px;
            background: #2c3e50;
            color: white;
            border-radius: 0 5px 5px 0;
            width: 40px;
            height: 40px;
            text-align: center;
            line-height: 40px;
            cursor: pointer;
        }

        /* Konten utama */
        .content {
            flex: 1;
            padding: 20px;
            margin-left: 0 !important;
            transition: 0.3s;
            width: 100%;
        }

        /* Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #2c3e50; /* Warna latar belakang header */
            color: white; /* Warna teks header */
        }

        tr:nth-child(even) {
            background-color: #f2f2f2; /* Warna latar belakang baris genap */
        }

        tr:hover {
            background-color: #ddd; /* Warna latar belakang saat hover */
        }

        /* Pencarian */
        .search-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .search-container input {
            width: 100%; /* Lebar input pencarian sama dengan tabel */
            max-width: 600px; /* Maksimal lebar input pencarian */
        }

        /* Dropdown */
        .dropdown-content {
            display: none; /* Sembunyikan dropdown secara default */
            background-color: #2c3e50;
            padding: 0;
            position: relative;
            margin-left: 50px; /* Spasi dari sidebar */
        }

        .dropdown-content a {
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            display: block;
        }

        .dropdown-content a:hover {
            background: #34495e;
        }
        /* Wrapper utama DataTables */
        .dataTables_wrapper {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        /* Kontainer atas (dropdown dan info) */
        .dataTables_length,
        .dataTables_info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dataTables_length {
            justify-content: flex-start; /* Sejajar ke kiri */
        }

        .dataTables_info {
            justify-content: center;
        }

        /* Pagination tetap di tengah */
        .dataTables_paginate {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 10px;
        }

        /* Style dropdown */
        .dataTables_length select {
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        /* Style pagination */
        .dataTables_paginate .paginate_button {
            padding: 5px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f8f9fa;
            cursor: pointer;
            font-size: 14px;
        }

        .dataTables_paginate .paginate_button:hover {
            background-color: #e9ecef;
        }

        .dataTables_paginate .paginate_button.current {
            background-color: #2c3e50;
            color: white;
            border-color: #2c3e50;
        }

        #barangTable_filter input {
            width: 1500px; /* Sesuaikan ukuran input pencarian */
            padding: 2px 12px;
            border: 1px solid #ccc;
            border-radius: 1px;
        }        
    </style>
</head>
<body>
<div class="flex-grow p -6" id="content">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
            <div class="sidebar-header text-center">
                <h4 id="title">IMS</h4>
                <img src="standal.jpg" alt="Foto Profil" class="profile-img">
            </div>
            <a href="">🏠 Dashboard</a>
            <div>
                <a href="#" onclick="toggleDropdown('bhpDropdown')">📑 BHP</a>
                <div class="dropdown-content" id="bhpDropdown">
                    <a href="barang_tambah.php">➕ Tambah Barang</a>
                    <a href="barang_daftar.php">📋 Daftar Barang</a>
                    <a href="barang_approval.php">📩 Daftar Pengajuan</a>
                </div>
            </div>
            
            <div>
                <a href="#" onclick="toggleDropdown('alatlabDropdown')">🧪 Alat Lab</a>
                <div class="dropdown-content" id="alatlabDropdown">
                    <a href="alat_tambah.php">➕ Tambah Alat</a>
                    <a href="alat_daftar.php">📋 Daftar Alat</a>
                    <a href="alat_approval.php">📩 Daftar Pengajuan</a>
                    <a href="#">🔧 Pemeliharaan</a>
                    <a href="#">🎚️ Kalibrasi</a>
                </div>       
            </div>
            <a href="#">🔔 Notifikasi</a>
            <a href="#">⚙️ Pengaturan</a>
            <a href="#">🔓 Logout</a>
        </div>

        <!-- Main Content -->
        <main class="flex-grow p-6">
            <h2 class="text-center text-2xl font-semibold mb-4">Data Pengajuan Perbaikan Peralatan Pengujian dan Sarana</h2>
            
            <div class="bg-white p-4 rounded shadow mb-6">
                <form method="POST" action="halaman.php">
                    <div id="searchContainer"></div> <!-- Tempat untuk memindahkan pencarian DataTables -->
                </form>
            </div>

            <table id="barangTable">
                <thead>
                    <tr>
                        <th>Nama Alat</th>
                        <th>No DBR</th>
                        <th>Tanggal Kerusakan</th>
                        <th>Jenis Kerusakan</th>
                        <th>Nama Pengajuan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result->num_rows > 0): 
                        while ($row = $result->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nama_alat_barang']) ?></td>
                            <td><?= htmlspecialchars($row['no_dbr']) ?></td>
                            <td><?= htmlspecialchars($row['tanggal_kerusakan']) ?></td>
                            <td><?= htmlspecialchars($row['jenis_kerusakan']) ?></td>
                            <td><?= htmlspecialchars($row['nama_pengajuan']) ?></td>
                            <td>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="no_dbr" value="<?= htmlspecialchars($row['no_dbr']) ?>">
                                    <select name="konfirmasi_kasubbag_tu" onchange="this.form.submit()">
                                        <option value="pending" <?= $row['konfirmasi_kasubbag_tu'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="disetujui" <?= $row['konfirmasi_kasubbag_tu'] == 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                                        <option value="ditolak" <?= $row['konfirmasi_kasubbag_tu'] == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">Tidak ada permintaan perbaikan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

    <script>
    function toggleSidebar() {
        let sidebar = document.getElementById("sidebar");
        let content = document.getElementById("content");

        if (sidebar.style.left === "-250px") {
            sidebar.style.left = "0";
            content.style.marginLeft = "250px"; // Geser konten ke kanan
        } else {
            sidebar.style.left = "-250px";
            content.style.marginLeft = "0"; // Balikin ke posisi awal
        }
    }

    // Toggle dropdown untuk sub-tombol
    function toggleDropdown(dropdownId) {
        let dropdown = document.getElementById(dropdownId);
        if (dropdown.style.display === "block") {
            dropdown.style.display = "none"; // Sembunyikan dropdown jika sudah terbuka
        } else {
            dropdown.style.display = "block"; // Tampilkan dropdown
        }
    }

    $(document).ready(function () {
        $('#barangTable').DataTable({
            "pageLength": 10,  // Default jumlah data yang ditampilkan
            "lengthMenu": [[10, 25, -1], [10 , 25, "Semua"]],
            "order": [[0, "desc"]],  // Urutkan berdasarkan kolom pertama (index 0) secara descending (terbaru dulu)
            "language": {
                "lengthMenu": "Tampilkan _MENU_ data per halaman",
                "zeroRecords": "Data tidak ditemukan",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                "infoEmpty": "Tidak ada data tersedia",
                "infoFiltered": "(disaring dari _MAX_ total data)"
            }
        });

        // Memindahkan pencarian DataTables ke dalam form pencarian yang sudah ada
        $("#barangTable_filter").appendTo("#searchContainer");
    });
    </script>
</body>
</html>