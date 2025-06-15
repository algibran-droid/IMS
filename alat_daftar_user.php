<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "ims";

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Export ke Excel
if (isset($_POST['export'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Daftar_Alat_Lab.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $sql    = "SELECT * FROM kalibrasi";
    $result = $conn->query($sql);

    if (!$result) {
        die("Query failed: " . $conn->error);
    }

    echo "<table border='1'>";
    echo "<tr>
            <th>No</th>
            <th>No. Inventaris</th>
            <th>Nama Peralatan</th>
            <th>Spesifikasi</th>
            <th>Besaran</th>
            <th>Lab</th>
            <th>Lab Aggregate</th>
            <th>Tanggal Terakhir Kalibrasi</th>
            <th>Bulan Terakhir Kalibrasi</th>
            <th>Instansi</th>
            <th>Periode Kalibrasi</th>
            <th>Jadwal Kalibrasi Selanjutnya</th>
            <th>Realisasi</th>
            <th>Bulan Realisasi</th>
            <th>OD Calibrate</th>
            <th>Estimate Calibrate</th>
            <th>Klasifikasi Estimasi</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Keterangan</th>
            <th>Link Tanda Terima</th>
            <th>Invoice</th>
            <th>Link Sertifikat</th>
            <th>Nomor TTA</th>
            <th>Status Bayar Invoice</th>
          </tr>";

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        // Tentukan style baris berdasarkan status_bayar_invoice
        $stat = strtolower($row['status_bayar_invoice']);
        if ($stat === 'belum dibayar') {
            // merah muda
            $style = 'style="background-color:#f8d7da;"';
        } else {
            // hijau muda
            $style = 'style="background-color:#d4edda;"';
        }

        echo "<tr {$style}>";

        // Kolom No urut
        echo "<td>" . $no++ . "</td>";

        // Tampilkan kolom lainnya
        echo "<td>" . htmlspecialchars($row['no_inventaris_dbr']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_peralatan_utama_standar']) . "</td>";
        echo "<td>" . htmlspecialchars($row['spesifikasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['besaran_yang_dikalibrasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['lab']) . "</td>";
        echo "<td>" . htmlspecialchars($row['lab_aggregate']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tanggal_terakhir_dikalibrasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['bulan_terakhir_dikalibrasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['instansi_yang_mengkalibrasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['periode_kalibrasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jadwal_kalibrasi_selanjutnya']) . "</td>";
        echo "<td>" . htmlspecialchars($row['realisasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['bulan_realisasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['od_calibrate']) . "</td>";
        echo "<td>" . htmlspecialchars($row['estimate_to_calibrate']) . "</td>";
        echo "<td>" . htmlspecialchars($row['klasifikasi_estimasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['harga']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['ket']) . "</td>";
        echo "<td>" . htmlspecialchars($row['link_tanda_terima_alat']) . "</td>";
        echo "<td>" . htmlspecialchars($row['invoice']) . "</td>";
        echo "<td>" . htmlspecialchars($row['link_sertifikat_kalibrasi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nomor_tta']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status_bayar_invoice']) . "</td>";

        echo "</tr>";
    }

    echo "</table>";
    exit;
}

// Proses pembaruan jika form disubmit (bukan search dan bukan export)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['search']) && !isset($_POST['export'])) {
    $no_inventaris_dbr            = $_POST['no_inventaris_dbr'] ?? NULL;
    $nama_peralatan_utama_standar = $_POST['nama_peralatan_utama_standar'] ?? NULL;
    $spesifikasi                  = $_POST['spesifikasi'] ?? NULL;
    $besaran_yang_dikalibrasi     = $_POST['besaran_yang_dikalibrasi'] ?? NULL;
    $lab                          = $_POST['lab'] ?? NULL;
    $lab_aggregate                = $_POST['lab_aggregate'] ?? NULL;
    $tanggal_terakhir_dikalibrasi = $_POST['tanggal_terakhir_dikalibrasi'] ?? NULL;
    $bulan_terakhir_dikalibrasi   = $_POST['bulan_terakhir_dikalibrasi'] ?? NULL;
    $instansi_yang_mengkalibrasi  = $_POST['instansi_yang_mengkalibrasi'] ?? NULL;
    $periode_kalibrasi            = $_POST['periode_kalibrasi'] ?? NULL;
    $jadwal_kalibrasi_selanjutnya = $_POST['jadwal_kalibrasi_selanjutnya'] ?? NULL;
    $realisasi                    = $_POST['realisasi'] ?? NULL;
    $bulan_realisasi              = $_POST['bulan_realisasi'] ?? NULL;
    $od_calibrate                 = $_POST['od_calibrate'] ?? NULL;
    $estimate_to_calibrate        = $_POST['estimate_to_calibrate'] ?? NULL;
    $klasifikasi_estimasi         = $_POST['klasifikasi_estimasi'] ?? NULL;
    $harga                        = $_POST['harga'] ?? NULL;
    $status                       = $_POST['status'] ?? NULL;
    $ket                          = $_POST['ket'] ?? NULL;
    $link_tanda_terima_alat       = $_POST['link_tanda_terima_alat'] ?? NULL;
    $invoice                      = $_POST['invoice'] ?? NULL;
    $link_sertifikat_kalibrasi    = $_POST['link_sertifikat_kalibrasi'] ?? NULL;
    $nomor_tta                    = $_POST['nomor_tta'] ?? NULL;
    $status_bayar_invoice         = $_POST['status_bayar_invoice'] ?? NULL;

    // Query untuk update
    $sql = "UPDATE kalibrasi SET
                no_inventaris_dbr = '$no_inventaris_dbr',
                nama_peralatan_utama_standar = '$nama_peralatan_utama_standar',
                spesifikasi = '$spesifikasi',
                besaran_yang_dikalibrasi = '$besaran_yang_dikalibrasi',
                lab = '$lab',
                lab_aggregate = '$lab_aggregate',
                tanggal_terakhir_dikalibrasi = '$tanggal_terakhir_dikalibrasi',
                bulan_terakhir_dikalibrasi = '$bulan_terakhir_dikalibrasi',
                instansi_yang_mengkalibrasi = '$instansi_yang_mengkalibrasi',
                periode_kalibrasi = '$periode_kalibrasi',
                jadwal_kalibrasi_selanjutnya = '$jadwal_kalibrasi_selanjutnya',
                realisasi = '$realisasi',
                bulan_realisasi = '$bulan_realisasi',
                od_calibrate = '$od_calibrate',
                estimate_to_calibrate = '$estimate_to_calibrate',
                klasifikasi_estimasi = '$klasifikasi_estimasi',
                harga = '$harga',
                status = '$status',
                ket = '$ket',
                link_tanda_terima_alat = '$link_tanda_terima_alat',
                invoice = '$invoice',
                link_sertifikat_kalibrasi = '$link_sertifikat_kalibrasi',
                nomor_tta = '$nomor_tta',
                status_bayar_invoice = '$status_bayar_invoice'
            WHERE no_inventaris_dbr = '$no_inventaris_dbr'
            AND nama_peralatan_utama_standar = '$nama_peralatan_utama_standar'";
    if (mysqli_query($conn, $sql)) {
        echo "Data berhasil diupdate.";
    } else {
        echo "Error updating record: " . mysqli_error($conn);
    }
}

// Pencarian
$searchQuery = isset($_POST['search']) ? $_POST['search'] : '';
$escaped = $conn->real_escape_string($searchQuery);

$sql = "SELECT * FROM kalibrasi WHERE 
    no_inventaris_dbr LIKE '%$escaped%' OR
    nama_peralatan_utama_standar LIKE '%$escaped%' OR
    spesifikasi LIKE '%$escaped%' OR
    besaran_yang_dikalibrasi LIKE '%$escaped%' OR
    lab LIKE '%$escaped%' OR
    lab_aggregate LIKE '%$escaped%' OR
    tanggal_terakhir_dikalibrasi LIKE '%$escaped%' OR
    bulan_terakhir_dikalibrasi LIKE '%$escaped%' OR
    instansi_yang_mengkalibrasi LIKE '%$escaped%' OR
    periode_kalibrasi LIKE '%$escaped%' OR
    jadwal_kalibrasi_selanjutnya LIKE '%$escaped%' OR
    realisasi LIKE '%$escaped%' OR
    bulan_realisasi LIKE '%$escaped%' OR
    od_calibrate LIKE '%$escaped%' OR
    estimate_to_calibrate LIKE '%$escaped%' OR
    klasifikasi_estimasi LIKE '%$escaped%' OR
    harga LIKE '%$escaped%' OR
    status LIKE '%$escaped%' OR
    ket LIKE '%$escaped%' OR
    link_tanda_terima_alat LIKE '%$escaped%' OR
    invoice LIKE '%$escaped%' OR
    link_sertifikat_kalibrasi LIKE '%$escaped%' OR
    nomor_tta LIKE '%$escaped%' OR
    status_bayar_invoice LIKE '%$escaped%'";

$result = $conn->query($sql);

// Cek hasil query
if (!$result) {
    die("Query gagal: " . $conn->error);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alat - User</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
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

        /* Wrapper tabel untuk scroll horizontal */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        /* Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            min-width: 1200px; /* Supaya scroll muncul kalau kolom banyak */
        }

        /* Header tabel */
        th {
            background-color: #2c3e50;
            color: #ffffff;
            text-align: left;
            padding: 12px 15px;
            white-space: nowrap; /* Biar teks header nggak pecah ke bawah */
        }

        /* Sel tabel */
        td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            white-space: nowrap; /* Biar teks sel nggak pecah ke bawah */
        }

        /* Baris genap */
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Hover efek */
        tr:hover {
            background-color: #f1f1f1;
            transition: background-color 0.2s ease-in-out;
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

        #barangTable_filter {
            text-align: left !important;
        }

        #barangTable_filter input {
            width: 300px; /* Ini normal, enak dilihat */
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .yellow-row {
            background-color: yellow !important; /* Warna kuning untuk baris rusak ringan */
        }

        .red-row {
            background-color: red !important; /* Warna merah untuk baris rusak berat */
            color: white; /* Warna teks putih untuk kontras */
        }

        .blue-row {
            background-color: blue !important; /* Warna biru untuk baris perbaikan */
            color: white; /* Warna teks putih untuk kontras */
        }
        /* ===== Tambahan untuk status bayar ===== */
        .status-belum {
        background-color: #f8d7da !important; /* merah muda */
        }
        .status-sudah {
        background-color: #d4edda !important; /* hijau muda */
        }

        .modal {
        display: none; /* Hidden by default */
        position: fixed; /* Stay in place */
        z-index: 1; /* Sit on top */
        left: 0;
        top: 0;
        width: 100%; /* Full width */
        height: 100%; /* Full height */
        overflow: auto; /* Enable scroll if needed */
        background-color: rgb(0,0,0); /* Fallback color */
        background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto; /* 15% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 80%; /* Could be more or less, depending on screen size */
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

</style>
</head>
<body>
<div class="flex-grow p-6" id="content">
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
                <a href="#" onclick="toggleDropdown('bhpDropdown')">📑 Barang Habis Pakai</a>
                <div class="dropdown-content" id="bhpDropdown">
                    <a href="barang_daftar.php">📋 Daftar Barang</a>
                    <a href="barang_submit.php">📩 Buat Pengajuan</a>
                </div>
            </div>
            
            <div>
                <a href="#" onclick="toggleDropdown('alatlabDropdown')">🧪 Alat Lab</a>
                <div class="dropdown-content" id="alatlabDropdown">
                    <a href="alat_daftar_user.php">📋 Daftar Alat</a>
                    <a href="alat_submit.php">📩 Buat Pengajuan</a>
                    <a href="#">🔧 Pengadaan Alat</a>
                    <a href="#">🎚️ Kalibrasi</a>
                </div>       
            </div>
            <a href="#">⚙️ Pengaturan</a>
            <a href="#">🔓 Logout</a>
        </div>

        <!-- Main Content -->
        <div class="flex-grow p-6">
            <header class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-semibold">Daftar Alat Lab</h1>
            </header>

            <!-- Table to Display Barang -->
            <div class="bg-white p-4 rounded shadow">
                <header class="flex justify-between items-center mb-6">
                </header>
                
                <!-- Container untuk tombol-tombol -->
                 <div class="flex mb-4">
                
                <div class="mr-4">
                    <button id="updateButton" class="bg-red-500 text-white px-4 py-2 rounded"><a href="barang_submit .php">Kalibrasi Alat</a></button>
                </div>
                 

                <!-- Tombol Ekspor -->
                 
                <div>
                    <form method="POST" action="alat_daftar_user.php">
                        <button type="submit" name="export" class="bg-blue-500 text-white px-4 py-2 rounded">Ekspor ke Excel</button>
                    </form>
                </div>
            </div>

                <table id="barangTable" class="min-w-full table-auto border-collapse">
                    <thead>
                    <tr>
                        <th class="px-4 py-2 border">No</th>
                        <th class="px-4 py-2 border">No Inventaris</th>
                        <th class="px-4 py-2 border">Nama Peralatan Utama Standar</th>
                        <th class="px-4 py-2 border">Spesifikasi</th>
                        <th class="px-4 py-2 border">Besaran yang Dikalibrasi</th>
                        <th class="px-4 py-2 border">Lab</th>
                        <th class="px-4 py-2 border">Lab Aggregate</th>
                        <th class="px-4 py-2 border">Tanggal Terakhir Dikalibrasi</th>
                        <th class="px-4 py-2 border">Bulan Terakhir Dikalibrasi</th>
                        <th class="px-4 py-2 border">Instansi yang Mengkalibrasi</th>
                        <th class="px-4 py-2 border">Periode Kalibrasi</th>
                        <th class="px-4 py-2 border">Jadwal Kalibrasi Selanjutnya</th>
                        <th class="px-4 py-2 border">Realisasi</th>
                        <th class="px-4 py-2 border">Bulan Realisasi</th>
                        <th class="px-4 py-2 border">OD Calibrate</th>
                        <th class="px-4 py-2 border">Estimate to Calibrate</th>
                        <th class="px-4 py-2 border">Klasifikasi Estimasi</th>
                        <th class="px-4 py-2 border">Harga</th>
                        <th class="px-4 py-2 border">Status</th>
                        <th class="px-4 py-2 border">Keterangan</th>
                        <th class="px-4 py-2 border">Link Tanda Terima Alat</th>
                        <th class="px-4 py-2 border">Invoice</th>
                        <th class="px-4 py-2 border">Link Sertifikat Kalibrasi</th>
                        <th class="px-4 py-2 border">Nomor TTA</th>
                        <th class="px-4 py-2 border">Status Bayar Invoice</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            $no = 1; // Inisialisasi nomor urut
                            while($row = $result->fetch_assoc()) {
                                $rowClass = "";
                                if (isset($row['kondisi'])) {
                                    if ($row['kondisi'] === "Rusak Ringan") {
                                        $rowClass = "yellow-row"; // Kelas untuk baris kuning
                                    } elseif ($row['kondisi'] === "Rusak Berat") {
                                        $rowClass = "red-row"; // Kelas untuk baris merah
                                    } elseif ($row['kondisi'] === "Dalam Perbaikan") {
                                        $rowClass = "blue-row"; // Kelas untuk baris biru
                                    }
                                }

                                $fotoId = htmlspecialchars($row['foto']);
                                $fotoUrl = isset($images[$fotoId]) ? htmlspecialchars($images[$fotoId]) : '';


                                echo "<tr class='$rowClass'>
                                        <td>" . $no++ . "</td>
                                        <td>" . htmlspecialchars($row['no_inventaris_dbr']) . "</td>  <!-- No Inventaris -->
                                        <td>" . htmlspecialchars($row['nama_peralatan_utama_standar']) . "</td>  <!-- Nama Peralatan Utama Standar -->
                                        <td>" . htmlspecialchars($row['spesifikasi']) . "</td>  <!-- Spesifikasi -->
                                        <td>" . htmlspecialchars($row['besaran_yang_dikalibrasi']) . "</td>  <!-- Besaran yang Dikalibrasi -->
                                        <td>" . htmlspecialchars($row['lab']) . "</td>  <!-- Lab -->
                                        <td>" . htmlspecialchars($row['lab_aggregate']) . "</td>  <!-- Lab Aggregate -->
                                        <td>" . htmlspecialchars($row['tanggal_terakhir_dikalibrasi']) . "</td>  <!-- Tanggal Terakhir Dikalibrasi -->
                                        <td>" . htmlspecialchars($row['bulan_terakhir_dikalibrasi']) . "</td>  <!-- Bulan Terakhir Dikalibrasi -->
                                        <td>" . htmlspecialchars($row['instansi_yang_mengkalibrasi']) . "</td>  <!-- Instansi yang Mengkalibrasi -->
                                        <td>" . htmlspecialchars($row['periode_kalibrasi']) . "</td>  <!-- Periode Kalibrasi -->
                                        <td>" . htmlspecialchars($row['jadwal_kalibrasi_selanjutnya']) . "</td>  <!-- Jadwal Kalibrasi Selanjutnya -->
                                        <td>" . htmlspecialchars($row['realisasi']) . "</td>  <!-- Realisasi -->
                                        <td>" . htmlspecialchars($row['bulan_realisasi']) . "</td>  <!-- Bulan Realisasi -->
                                        <td>" . htmlspecialchars($row['od_calibrate']) . "</td>  <!-- OD Calibrate -->
                                        <td>" . htmlspecialchars($row['estimate_to_calibrate']) . "</td>  <!-- Estimate to Calibrate -->
                                        <td>" . htmlspecialchars($row['klasifikasi_estimasi']) . "</td>  <!-- Klasifikasi Estimasi -->
                                        <td>" . htmlspecialchars($row['harga']) . "</td>  <!-- Harga -->
                                        <td>" . htmlspecialchars($row['status']) . "</td>  <!-- Status -->
                                        <td>" . htmlspecialchars($row['ket']) . "</td>  <!-- Keterangan -->
                                        <td>" . htmlspecialchars($row['link_tanda_terima_alat']) . "</td>  <!-- Link Tanda Terima Alat -->
                                        <td>" . htmlspecialchars($row['invoice']) . "</td>  <!-- Invoice -->
                                        <td>" . htmlspecialchars($row['link_sertifikat_kalibrasi']) . "</td>  <!-- Link Sertifikat Kalibrasi -->
                                        <td>" . htmlspecialchars($row['nomor_tta']) . "</td>  <!-- Nomor TTA -->
                                        <td>" . htmlspecialchars($row['status_bayar_invoice']) . "</td>  <!-- Status Bayar Invoice -->
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10' class='px-4 py-2 border'>No results found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JavaScript to toggle BPMB submenu -->
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
    </script>

    <!-- Tambahkan jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Tambahkan DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <script>
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

</script>
<script>
document.getElementById('updateButton').onclick = function() {
    document.getElementById('updateModal').style.display = 'block';
}

document.getElementById('closeModal').onclick = function() {
    document.getElementById('updateModal').style.display = 'none';
}

// Close the modal if the user clicks anywhere outside of it
window.onclick = function(event) {
    if (event.target == document.getElementById('updateModal')) {
        document.getElementById('updateModal').style.display = 'none';
    }
}
</script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>