<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// ======================
// Konfigurasi Koneksi Database & Mode Exception
// ======================
$host = "localhost";
$user_db = "root";
$pass = "";

// Koneksi ke database inventory (misalnya "inventory_lab")
$inventory_db = "inventory_lab";
mysqli_report(MYSQLI_REPORT_STRICT);
try {
    $inventory_connection = new mysqli($host, $user_db, $pass, $inventory_db);
    // Jika koneksi berhasil, hapus error yang mungkin tersisa di session
    if (isset($_SESSION['error_details'])) {
        unset($_SESSION['error_details']);
        unset($_SESSION['error_code']);
        unset($_SESSION['error_message']);
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['error_code'] = "INV-001";
    $_SESSION['error_message'] = $e->getMessage();
    $_SESSION['error_details'] = $e->getMessage();
}

// Koneksi ke database error (misalnya "error")
$error_db = "error";
try {
    $error_connection = new mysqli($host, $user_db, $pass, $error_db);
} catch (mysqli_sql_exception $e) {
    $_SESSION['error_code'] = "ERR-001";
    $_SESSION['error_message'] = $e->getMessage();
    $_SESSION['error_details'] = $e->getMessage();
}

// ======================
// Fungsi untuk Menganalisis Error (Logging & Panggil FastAPI)
// ======================
function getAIErrorSolution($error_details, $error_connection) {
    // Log error ke tabel solutions
    $stmt = $error_connection->prepare("INSERT INTO solutions (error_details, created_at) VALUES (?, NOW())");
    if ($stmt) {
        $stmt->bind_param("s", $error_details);
        $stmt->execute();
        $stmt->close();
    }
    // Jika tersedia, simpan juga error_code dan error_message
    if (isset($_SESSION['error_code']) && isset($_SESSION['error_message'])) {
        $stmt2 = $error_connection->prepare("INSERT INTO solutions (error_code, error_message, error_details, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt2) {
            $stmt2->bind_param("sss", $_SESSION['error_code'], $_SESSION['error_message'], $error_details);
            $stmt2->execute();
            $stmt2->close();
        }
    }
    // Debug: catat URL yang dikirim ke API FastAPI
    $url = "http://localhost:5000/analyze_error?error_code=" . urlencode($error_details) . "&error_message=" . urlencode($error_details);
    error_log("Mengirim ke FastAPI: " . $url);
    // Panggil API FastAPI
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ======================
// Ambil Data Barang/Alat dan Data Profil
// ======================
$result = false;
$profileData = [
    'photo'    => '',
    'nickname' => '',
    'fullname' => '',
    'gender'   => '',
    'country'  => ''
];
if (!isset($_SESSION['error_details'])) {
    $result = $inventory_connection->query("SELECT * FROM barang");
    $profileQuery = "SELECT * FROM pengguna WHERE id=1";
    $profileResult = $inventory_connection->query($profileQuery);
    if ($profileResult && $profileResult->num_rows > 0) {
        $profileData = $profileResult->fetch_assoc();
    }
}

// Jika terdapat error, panggil fungsi analisis error
$aiResult = [];
if (isset($_SESSION['error_details']) && isset($error_connection)) {
    $aiResult = getAIErrorSolution($_SESSION['error_details'], $error_connection);
}
ob_end_clean();

// ======================
// Endpoint AJAX untuk Admin (jika ada parameter GET action)
// ======================
if (isset($_GET['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    // Contoh: Endpoint getPermintaan
    if ($_GET['action'] === 'getPermintaan') {
        $conn = new mysqli("localhost", "root", "", "surat_db");
        if ($conn->connect_error) {
            echo json_encode([
                "error_code"    => "DB_CONN_FAIL",
                "error_message" => $conn->connect_error,
                "solution"      => "Periksa kredensial dan konfigurasi database."
            ]);
            exit;
        }
        $sql = "SELECT COUNT(*) AS count FROM acc_surat WHERE status = 'pending'";
        $resultCount = $conn->query($sql);
        $count = 0;
        if ($resultCount) {
            $row = $resultCount->fetch_assoc();
            $count = (int)$row['count'];
        }
        $conn->close();
        echo json_encode(["pending_requests" => $count]);
        exit;
    }
    
    // Contoh: Endpoint getPermintaanDetails
    if ($_GET['action'] === 'getPermintaanDetails') {
        $conn = new mysqli("localhost", "root", "", "surat_db");
        if ($conn->connect_error) {
            echo json_encode([
                "error_code"    => "DB_CONN_FAIL",
                "error_message" => $conn->connect_error,
                "solution"      => "Periksa kredensial dan konfigurasi database."
            ]);
            exit;
        }
        $month = isset($_GET['month']) ? intval($_GET['month']) : date("n");
        $sql = "SELECT nama, jenis_permohonan, created_at 
                FROM acc_surat 
                WHERE MONTH(created_at) = $month 
                  AND YEAR(created_at) = YEAR(CURDATE())";
        $resultDetails = $conn->query($sql);
        $details = [];
        if ($resultDetails) {
            while ($row = $resultDetails->fetch_assoc()) {
                $details[] = $row;
            }
        }
        $conn->close();
        echo json_encode($details);
        exit;
    }
    
    // Jika action tidak dikenali
    echo json_encode([
      "error_code"    => "UNKNOWN_ACTION",
      "error_message" => "Action not recognized",
      "solution"      => "Periksa parameter ?action=..."
    ]);
    exit;
}

require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

// Konfigurasi Database
$host = 'localhost';
$dbname = 'inventory_lab';
$username = 'root';
$password = '';

// Koneksi ke Database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// Inisialisasi Google Client
$client = new Client();
$client->setAuthConfig('edit-profile-456401-0e394d0e82eb.json'); // Path to your service account JSON file
$client->addScope(Drive::DRIVE_FILE);

// Inisialisasi layanan Google Drive
$driveService = new Drive($client);

// Proses unggah file dan update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Periksa apakah semua data ada
    if (isset($_POST['nickname'], $_POST['fullname'], $_POST['country'], $_POST['gender'])) {
        // Ambil data dari form
        $nickname = $_POST['nickname'];
        $fullname = $_POST['fullname'];
        $country = $_POST['country'];
        $gender = $_POST['gender']; // Ambil gender dari form
        
        // Proses unggah foto jika ada
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $fileName = basename($file['name']);
            $filePath = $file['tmp_name'];
            $fileMimeType = mime_content_type($filePath);

            // Unggah ke Google Drive
            $driveFile = new DriveFile();
            $driveFile->setName($fileName);
            $driveFile->setParents(['1vx0nLlwbQ6FvEIQQj9eASP96IrVShIX6']); // Set the parent folder ID

            $result = $driveService->files->create($driveFile, [
                'data' => file_get_contents($filePath),
                'mimeType' => $fileMimeType,
                'uploadType' => 'multipart'
            ]);

            // Dapatkan ID file yang diunggah
            $fileId = $result->id;

            // Buat file dapat diakses secara publik
            $permission = new Drive\Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $driveService->permissions->create($fileId, $permission);

            // Dapatkan tautan berbagi
            $fileUrl = "https://drive.google.com/uc?id=$fileId";

            // Simpan tautan dan data profil ke database
            $stmt = $pdo->prepare("UPDATE pengguna SET photo = :photo, nickname = :nickname, fullname = :fullname, country = :country, gender = :gender WHERE id = :id");
            $stmt->execute([
                'photo' => $fileUrl,
                'nickname' => $nickname,
                'fullname' => $fullname,
                'country' => $country,
                'gender' => $gender, // Simpan gender ke database
                'id' => 1 // Ganti 1 dengan ID pengguna yang sesuai
            ]);

            echo "";
        } else {
            echo "";
        }
    } else {
        echo "Semua field harus diisi.";
    }
}
?>
<?php
// Koneksi ke database
$koneksi = new mysqli("localhost", "root", "", "inventory_lab");

// Ambil data pengguna
$query = "SELECT photo FROM pengguna WHERE id = 1"; // Ubah sesuai kebutuhan
$result = $koneksi->query($query);
$data = $result->fetch_assoc();

$photo_url = $data['photo'] ?? '';
$photo_id = '';

// Ekstrak ID dari URL
if (preg_match('/id=([a-zA-Z0-9_-]+)/', $photo_url, $match)) {
    $photo_id = $match[1];
    $embed_url = "https://drive.google.com/file/d/{$photo_id}/preview";
} else {
    $embed_url = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    /* Global & Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }
    body {
      background: #f8f9fa;
      padding: 20px;
    }
    header {
      text-align: center;
      margin-bottom: 20px;
    }
    
    /* Sidebar */
    .sidebar {
      position: fixed;
      left: -250px;
      top: 0;
      width: 250px;
      height: 100vh;
      background: #2c3e50;
      color: white;
      transition: 0.3s;
    }
    .toggle-btn {
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
    .sidebar-inner {
      height: 100vh;
      overflow-y: auto;
      padding-top: 20px;
    }
    .sidebar-header {
      text-align: center;
      padding: 10px 20px 20px;
      border-bottom: 1px solid #ddd;
    }
    .frame-foto {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      display: block;
      margin: 10px auto 0;
      overflow: hidden;
            margin: 10px auto 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .frame-foto iframe {
            width: 100%; /* Menyesuaikan ukuran lebar */
            height: 100%; /* Menyesuaikan ukuran tinggi */
            object-fit: cover; /* Agar gambar terpotong dengan baik */
            display: block;
            pointer-events: none; /* Menonaktifkan interaksi pengguna pada iframe */
        }    
    .sidebar a, .sidebar button {
      display: block;
      padding: 15px;
      color: white;
      text-decoration: none;
      transition: 0.2s;
      background: none;
      border: none;
      width: 100%;
      text-align: left;
      cursor: pointer;
    }
    .sidebar a:hover, .sidebar button:hover {
      background: #34495e;
    }
    .dropdown-content {
      display: none;
      background-color: #2c3e50;
      padding-left: 20px;
    }
    @media screen and (max-width: 768px) {
      .sidebar {
        width: 100%;
        left: -100%;
      }
    }
    
    /* Main Content */
    .main-content {
      margin-left: 20px;
      transition: margin-left 0.3s ease;
    }
    
    /* Flex container untuk 3 kolom: Permintaan Surat, Events, Pengumuman */
    .flex-container {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    .flex-item {
      flex: 1;
      min-width: 300px;
      max-width: 32%;
      padding: 10px;
      background: #fff;
      border-radius: 5px;
      border: 1px solid #ddd;
    }
    .flex-item h3 {
      text-align: center;
      margin-bottom: 10px;
      font-size: 1.1em;
    }
    .flex-item table {
      width: 100%;
      border-collapse: collapse;
    }
    .flex-item th, .flex-item td {
      padding: 8px;
      text-align: left;
      border-bottom: 1px solid #ccc;
      font-size: 0.9em;
    }
    .scroll-container {
      max-height: 300px;
      overflow-y: auto;
      border: 1px solid #ccc;
      min-height: 100px;
      padding: 5px;
    }
    
    /* Card putih untuk "Cari Barang/Alat" */
    .card-white {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 15px;
      margin-top: 20px;
    }
    .search-container h3 {
      margin-bottom: 10px;
      font-size: 1.1em;
      font-weight: bold;
    }
    .search-container input[type="text"] {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
    }
    #itemsTable {
      width: 100%;
      border-collapse: collapse;
    }
    #itemsTable thead th {
      border-bottom: 2px solid #000;
      text-align: left;
      padding: 8px;
      font-weight: normal;
    }
    #itemsTable tbody td {
      padding: 8px;
      border-bottom: 1px solid #ccc;
    }
    #itemsTable tbody td[colspan] {
      text-align: center;
      color: #666;
      font-style: italic;
    }
    
    /* Popup & Modal */
    .popup-container {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      justify-content: center;
      align-items: center;
      z-index: 2000;
    }
    .popup-content {
      background: white;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0px 0px 10px rgba(0,0,0,0.3);
    }
    .popup-content .btn {
      padding: 10px 20px;
      margin: 10px;
      border: none;
      cursor: pointer;
      border-radius: 5px;
    }
    .popup-content .logout-btn {
      background-color: red;
      color: white;
    }
    .popup-content .cancel-btn {
      background-color: gray;
      color: white;
    }
    
    .modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 3000;
    }
    .modal-content {
      background: #fff;
      padding: 20px;
      border-radius: 5px;
      width: 80%;
      max-width: 500px;
      position: relative;
    }
    .modal-content .close {
      position: absolute;
      top: 10px;
      right: 15px;
      cursor: pointer;
      font-size: 20px;
    }
    #updateProfileForm div {
      margin-bottom: 15px;
    }
    #updateProfileForm label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
    }
    #updateProfileForm input[type="text"],
    #updateProfileForm input[type="file"],
    #updateProfileForm select {
      width: 100%;
      padding: 8px;
      box-sizing: border-box;
    }
    #updateProfileForm button {
      padding: 10px 20px;
      background-color: #007bff;
      color: #fff;
      border: none;
      cursor: pointer;
    }
    #updateResponseMessage {
      margin-top: 10px;
      text-align: center;
    }

    /* Styling khusus untuk Pengumuman Terbaru */
    .pengumuman-input {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 10px;
      align-items: flex-end;
    }
    .pengumuman-field {
      display: flex;
      flex-direction: column;
    }
    .pengumuman-field label {
      font-size: 0.85em;
      margin-bottom: 4px;
      font-weight: bold;
    }
    .pengumuman-field input[type="date"],
    .pengumuman-field input[type="text"] {
      padding: 6px;
      font-size: 0.9em;
      width: 150px;
    }
    #addAnnouncementBtn {
      padding: 8px 12px;
      font-size: 0.9em;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <div class="sidebar" id="sidebar">
    <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
    <div class="sidebar-inner">
      <div class="sidebar-header">
        <h4 id="title">IMS</h4>
        <?php if ($embed_url): ?>
        <div class="frame-foto">
            <iframe src="<?= $embed_url ?>" sandbox="allow-same-origin allow-scripts" frameborder="0"></iframe>
        </div>
    <?php else: ?>
        <p align="center">Foto tidak tersedia atau format link tidak valid.</p>
    <?php endif; ?>      </div>
      <a href="admin.php">🏠 Dashboard</a>
      <a href="javascript:void(0)" onclick="toggleDropdown('bhpDropdown')">📑 BHP</a>
      <div class="dropdown-content" id="bhpDropdown">
        <a href="barang_daftar.php">📋 Daftar Barang</a>
        <a href="barang_submit.php">📩 Daftar Pengajuan</a>
      </div>
      <a href="javascript:void(0)" onclick="toggleDropdown('alatlabDropdown')">🧪 Alat Lab</a>
      <div class="dropdown-content" id="alatlabDropdown">
        <a href="alat_daftar_admin.php">📋 Daftar Alat</a>
        <a href="alat_submit.php">📩 Daftar Pengajuan</a>
        <a href="#">🔧 Pemeliharaan</a>
        <a href="#">🎚️ Kalibrasi</a>
      </div>
      <a href="#" id="editProfileLink">⚙️ Pengaturan</a>
      <button onclick="openLogoutPopup()">🔓 Logout</button>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content" id="mainContent">
    <header>
      <h2>Dashboard Admin</h2>
      <div class="current-date"></div>
    </header>
    
    <!-- Flex Container untuk 3 kolom: Permintaan Surat, Events & Pengumuman -->
    <div class="flex-container">
      <!-- Permintaan Surat -->
      <div class="flex-item">
        <h3>Permintaan Surat</h3>
        <div id="permintaanNotification" class="notification" style="display:none; font-size:0.9em;"></div>
        <div class="scroll-container">
          <table>
            <thead>
              <tr>
                <th>Nama</th>
                <th>Jenis Permintaan</th>
                <th>Tanggal Permintaan</th>
              </tr>
            </thead>
            <tbody id="permintaanDetailsTableBody">
              <!-- Data detail permintaan surat -->
            </tbody>
          </table>
        </div>
      </div>
      <!-- Events & Schedule -->
      <div class="flex-item">
        <h3>Events & Schedule</h3>
        <div id="calendar"></div>
      </div>
      <!-- Pengumuman Terbaru -->
      <div class="flex-item">
        <h3>Pengumuman Terbaru</h3>
        
        <!-- Komponen input untuk menambah pengumuman, dengan label -->
        <div class="pengumuman-input">
          <div class="pengumuman-field">
            <label for="announcementDate">Tanggal:</label>
            <input type="date" id="announcementDate" placeholder="dd/mm/yyyy">
          </div>
          <div class="pengumuman-field">
            <label for="announcementName">Nama:</label>
            <input type="text" id="announcementName" placeholder="Nama">
          </div>
          <div class="pengumuman-field">
            <label for="announcementActivity">Kegiatan:</label>
            <input type="text" id="announcementActivity" placeholder="Kegiatan">
          </div>
          <button id="addAnnouncementBtn">Tambah</button>
        </div>
        
        <div class="scroll-container">
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Nama</th>
                <th>Kegiatan</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="announcementTableBody">
              <!-- Data pengumuman akan di-render -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
    
    <!-- BAGIAN "Cari Barang/Alat" dengan CARD PUTIH -->
    <section class="search-container card-white">
      <h3>Cari Barang/Alat</h3>
      <input type="text" id="searchInput" placeholder="Cari nama barang..." onkeyup="searchTable()" />
      <table id="itemsTable">
        <thead>
          <tr>
            <th>Nama Barang</th>
            <th>Jenis</th>
            <th>Merk / Penyedia</th>
            <th>No. Katalog</th>
            <th>Ukuran</th>
            <th>Jumlah</th>
            <th>Satuan</th>
            <th>Lokasi</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if (!isset($_SESSION['error_details']) && $result) {
              if ($result->num_rows > 0) {
                  while($row = $result->fetch_assoc()) {
                      echo "<tr>
                              <td>" . htmlspecialchars($row['namaBarang']) . "</td>
                              <td>" . htmlspecialchars($row['jenisBarang']) . "</td>
                              <td>" . htmlspecialchars($row['merkPenyedia']) . "</td>
                              <td>" . htmlspecialchars($row['noKatalog']) . "</td>
                              <td>" . htmlspecialchars($row['ukuran']) . "</td>
                              <td>" . htmlspecialchars($row['total_jumlah']) . "</td>
                              <td>" . htmlspecialchars($row['satuan']) . "</td>
                              <td>" . htmlspecialchars($row['lokasi']) . "</td>
                              <td>" . htmlspecialchars($row['keterangan']) . "</td>
                            </tr>";
                  }
              } else {
                  echo "<tr><td colspan='9'>No results found</td></tr>";
              }
          } else {
              echo "<tr><td colspan='9'>No results found</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </section>
  </div>
  
  <!-- POPUP LOGOUT -->
  <div id="logoutPopup" class="popup-container">
    <div class="popup-content">
      <h2>Logout</h2>
      <p>Apakah Anda yakin ingin keluar?</p>
      <button class="btn logout-btn" onclick="logout()">Yes, Logout</button>
      <button class="btn cancel-btn" onclick="closeLogoutPopup()">Cancel</button>
    </div>
  </div>
  
  <!-- MODAL EDIT PROFILE -->
  <div id="editProfileModal" class="modal">
    <div class="modal-content">
      <span class="close" id="closeEditProfile">&times;</span>
      <h3>Edit Profile</h3>
      <form action="" method="post" enctype="multipart/form-data">
      <div>
          <label for="photo">Profile Picture:</label>
          <input type="file" name="photo" id="photo" accept="image/*" required>
          </div>
        <div>
          <label for="nickname">Nickname:</label>
          <input type="text" name="nickname" id="nickname" required>
          </div>
        <div>
          <label for="fullname">Full Name:</label>
          <input type="text" name="fullname" id="fullname" required>
          </div>
        <div>
          <label for="gender">Gender:</label>
          <select name="gender" id="gender">
            <option value="Male" <?= ($profileData['gender']=='Male') ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?= ($profileData['gender']=='Female') ? 'selected' : ''; ?>>Female</option>
          </select>
        </div>
        <div>
          <label for="country">Country:</label>
          <input type="text" name="country" id="country" required>
          </div>
        <input type="hidden" name="action" value="update_profile">
        <button type="submit">Update Profile</button>
      </form>
      <div id="updateResponseMessage"></div>
    </div>
  </div>
  
  <!-- JAVASCRIPT -->
  <script>
    // Sidebar & Dropdown Functions
    function toggleSidebar() {
      var sidebar = document.getElementById("sidebar");
      var mainContent = document.getElementById("mainContent");
      if (sidebar.style.left === "-250px" || sidebar.style.left === "-100%") {
        sidebar.style.left = "0";
        mainContent.style.marginLeft = "250px";
      } else {
        sidebar.style.left = "-250px";
        mainContent.style.marginLeft = "20px";
      }
    }
    function toggleDropdown(dropdownId) {
      var dropdown = document.getElementById(dropdownId);
      dropdown.style.display = (dropdown.style.display === "block") ? "none" : "block";
    }
  
    // Popup Logout
    function openLogoutPopup() {
      document.getElementById("logoutPopup").style.display = "flex";
    }
    function closeLogoutPopup() {
      document.getElementById("logoutPopup").style.display = "none";
    }
    function logout() {
      alert("You have been logged out.");
      window.location.href = "Dashboard.php";
    }
  
    // Modal Edit Profile
    document.getElementById("editProfileLink").addEventListener("click", function(e) {
      e.preventDefault();
      document.getElementById("editProfileModal").style.display = "flex";
    });
    document.getElementById("closeEditProfile").addEventListener("click", function(){
      document.getElementById("editProfileModal").style.display = "none";
    });
    window.addEventListener("click", function(e) {
      var modal = document.getElementById("editProfileModal");
      if (e.target == modal) {
        modal.style.display = "none";
      }
    });
  
    // Update Profile AJAX
    $("#updateProfileForm").on("submit", function(e) {
      e.preventDefault();
      var formData = new FormData(this);
      $.ajax({
        url: "admin.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          if(response.trim() === "success"){
            $("#updateResponseMessage").html("<p style='color:green;'>Profil berhasil diperbarui!</p>");
          } else {
            $("#updateResponseMessage").html("<p style='color:red;'>Gagal memperbarui profil.</p>");
          }
        },
        error: function() {
          $("#updateResponseMessage").html("<p style='color:red;'>Terjadi kesalahan.</p>");
        }
      });
    });
  
    // Fungsi pencarian pada tabel "Cari Barang/Alat"
    function searchTable() {
      var input = document.getElementById("searchInput");
      var filter = input.value.toUpperCase();
      var table = document.getElementById("itemsTable");
      var tr = table.getElementsByTagName("tr");
      for (var i = 1; i < tr.length; i++) {
        var td = tr[i].getElementsByTagName("td")[0];
        if (td) {
          var txtValue = td.textContent || td.innerText;
          tr[i].style.display = (txtValue.toUpperCase().indexOf(filter) > -1) ? "" : "none";
        }
      }
    }
  
    // Notifikasi Permintaan Surat
    function updatePermintaanNotification() {
      fetch("admin.php?action=getPermintaan")
        .then(response => response.json())
        .then(data => {
          if (data.error_code) {
            Swal.fire({
              icon: 'error',
              title: 'Terjadi Error!',
              html: `<strong>Error:</strong> ${data.error_code}<br>
                     <strong>Pesan:</strong> ${data.error_message}<br>
                     <strong>Solusi:</strong> ${data.solution}`
            });
            return;
          }
          const notifElem = document.getElementById("permintaanNotification");
          if(data.pending_requests > 0){
            notifElem.style.display = "block";
            notifElem.textContent = "Ada " + data.pending_requests + " permintaan surat baru.";
          } else {
            notifElem.style.display = "none";
          }
        })
        .catch(error => {
          console.error("Error fetching permintaan:", error);
        });
    }
  
    // Detail Permintaan Surat
    function updatePermintaanDetails() {
      fetch("admin.php?action=getPermintaanDetails")
        .then(response => response.json())
        .then(data => {
          if (data.error_code) {
            Swal.fire({
              icon: 'error',
              title: 'Terjadi Error!',
              html: `<strong>Error:</strong> ${data.error_code}<br>
                     <strong>Pesan:</strong> ${data.error_message}<br>
                     <strong>Solusi:</strong> ${data.solution}`
            });
            return;
          }
          const tableBody = document.getElementById("permintaanDetailsTableBody");
          tableBody.innerHTML = "";
          data.forEach(item => {
            tableBody.innerHTML += `
              <tr>
                <td>${item.nama}</td>
                <td>${item.jenis_permohonan}</td>
                <td>${item.created_at}</td>
              </tr>
            `;
          });
        })
        .catch(error => {
          console.error("Error fetching permintaan details:", error);
        });
    }
  
    // Fungsi untuk Mengirim Pengumuman ke user.php
    document.getElementById('addAnnouncementBtn').addEventListener('click', function() {
      const dateValue = document.getElementById('announcementDate').value;
      const nameValue = document.getElementById('announcementName').value;
      const activityValue = document.getElementById('announcementActivity').value;
  
      // Validasi: pastikan semua field terisi
      if (!dateValue || !nameValue || !activityValue) {
        alert("Mohon isi semua field pengumuman!");
        return;
      }
  
      // Kirim data ke user.php dengan method POST dan action=addAnnouncement
      fetch('user.php?action=addAnnouncement', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          date: dateValue,
          name: nameValue,
          activity: activityValue
        })
      })
      .then(response => response.json())
      .then(data => {
         if(data.success){
             alert("Pengumuman berhasil ditambahkan!");
             loadAnnouncements(); // refresh data pengumuman
         } else {
             alert("Gagal menambahkan pengumuman: " + (data.message || ""));
         }
      })
      .catch(err => {
         console.error(err);
         alert("Terjadi kesalahan saat mengirim data.");
      });
    });
  
    // Fungsi untuk load data pengumuman dari user.php
    function loadAnnouncements() {
      fetch('user.php?action=getAnnouncements')
      .then(response => response.json())
      .then(data => {
         const tableBody = document.getElementById('announcementTableBody');
         tableBody.innerHTML = "";
         if(data.length > 0) {
             data.forEach(item => {
                 const row = document.createElement('tr');
                 row.innerHTML = `<td>${item.date}</td><td>${item.name}</td><td>${item.activity}</td><td><button onclick="deleteAnnouncementServer('${item.id}')">Hapus</button></td>`;
                 tableBody.appendChild(row);
             });
         } else {
             tableBody.innerHTML = "<tr><td colspan='4'>Tidak ada pengumuman</td></tr>";
         }
      })
      .catch(err => {
         console.error(err);
      });
    }
  
    // Fungsi untuk menghapus pengumuman melalui user.php
    function deleteAnnouncementServer(id) {
      if(!confirm("Hapus pengumuman ini?")) return;
      fetch('user.php?action=deleteAnnouncement&id=' + encodeURIComponent(id), {
        method: 'GET'
      })
      .then(response => response.json())
      .then(data => {
         if(data.success){
             alert("Pengumuman berhasil dihapus!");
             loadAnnouncements();
         } else {
             alert("Gagal menghapus pengumuman: " + (data.message || ""));
         }
      })
      .catch(err => {
         console.error(err);
         alert("Terjadi kesalahan saat menghapus pengumuman.");
      });
    }
  
    // On DOM Loaded
    document.addEventListener("DOMContentLoaded", function () {
      var dateElement = document.querySelector(".current-date");
      function updateDate() {
        var today = new Date();
        var options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
        dateElement.textContent = today.toLocaleDateString("en-US", options);
      }
      updateDate();
  
      // Inisialisasi FullCalendar
      var calendarEl = document.getElementById("calendar");
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        selectable: true,
        editable: true,
        eventClick: function (info) {
          if (confirm("Hapus catatan ini?")) info.event.remove();
        },
        dateClick: function (info) {
          const eventTitle = prompt("Masukkan catatan untuk tanggal: " + info.dateStr);
          if (eventTitle && eventTitle.trim() !== "") {
            calendar.addEvent({
              title: eventTitle,
              start: info.dateStr,
              allDay: true,
            });
          } else {
            alert("Catatan tidak boleh kosong!");
          }
        },
      });
      calendar.render();
  
      // Update notifikasi & detail permintaan setiap 10 detik
      updatePermintaanNotification();
      updatePermintaanDetails();
      setInterval(updatePermintaanNotification, 10000);
      setInterval(updatePermintaanDetails, 10000);
      
      // Load data pengumuman dari user.php saat halaman dimuat
      loadAnnouncements();
    });
  
    // Jika terdapat error (error dari koneksi) tampilkan popup analisis error
    <?php if(isset($_SESSION['error_details'])): ?>
      Swal.fire({
        icon: 'error',
        title: 'Terjadi Error!',
        html: `<strong>Kode Error:</strong> <?php echo isset($aiResult['error_code']) ? $aiResult['error_code'] : ($_SESSION['error_code'] ?? 'Tidak tersedia'); ?><br>
               <strong>Pesan:</strong> <?php echo isset($aiResult['error_message']) ? $aiResult['error_message'] : ($_SESSION['error_message'] ?? $_SESSION['error_details']); ?><br>
               <strong>Solusi:</strong> <?php echo isset($aiResult['solution']) ? $aiResult['solution'] : 'Belum ada solusi'; ?>`,
        confirmButtonText: 'Mengerti'
      });
    <?php endif; ?>
  </script>
</body>
</html>