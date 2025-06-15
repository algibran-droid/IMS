<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Untuk debugging, aktifkan error reporting (nonaktifkan di production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

// Koneksi database
$host         = "localhost";
$user_db      = "root";
$pass         = "";
$inventory_db = "inventory_lab";
mysqli_report(MYSQLI_REPORT_STRICT);

try {
    $inventory_connection = new mysqli($host, $user_db, $pass, $inventory_db);
    // Reset error session jika ada
    if (isset($_SESSION['error_details'])) {
        unset($_SESSION['error_details'], $_SESSION['error_code'], $_SESSION['error_message']);
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['error_code']    = "INV-001";
    $_SESSION['error_message'] = $e->getMessage();
    $_SESSION['error_details'] = $e->getMessage();
}

// Database untuk analisis error
$error_db = "error";
try {
    $error_connection = new mysqli($host, $user_db, $pass, $error_db);
} catch (mysqli_sql_exception $e) {
    $_SESSION['error_code']    = "ERR-001";
    $_SESSION['error_message'] = $e->getMessage();
    $_SESSION['error_details'] = $e->getMessage();
}


if (isset($_GET['ajax_search'])) {
  header('Content-Type: application/json');
  $q   = $inventory_connection->real_escape_string($_GET['q'] ?? '');
  $out = [];
  if ($q !== '') {
      $stmt = $inventory_connection->prepare(
          "SELECT DISTINCT namaBarang 
           FROM barang 
           WHERE namaBarang LIKE CONCAT('%', ?, '%') 
           ORDER BY namaBarang 
           LIMIT 10"
      );
      $stmt->bind_param("s", $q);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($r = $res->fetch_assoc()) {
          $out[] = $r['namaBarang'];
      }
      $stmt->close();
  }
  echo json_encode($out);
  exit;
}

// Ambil data barang untuk tabel
$result = $inventory_connection->query("SELECT * FROM barang");

// Query stok per lokasi untuk Chart.js
$chartQuery  = "
    SELECT lokasi, SUM(total_jumlah) AS total_stok
    FROM barang
    GROUP BY lokasi
    ORDER BY lokasi
";
$chartResult = $inventory_connection->query($chartQuery);

// Siapkan array untuk Chart.js
$locations = [];
$totals    = [];
if ($chartResult && $chartResult->num_rows > 0) {
    while ($row = $chartResult->fetch_assoc()) {
        $locations[] = $row['lokasi'];
        $totals[]    = (int)$row['total_stok'];
    }
}

// Ambil data profil pengguna
$userId       = $_SESSION['user_id'];
$profileQuery = "SELECT * FROM pengguna WHERE id='$userId'";
$profileRes   = $inventory_connection->query($profileQuery);
$profileData  = ($profileRes && $profileRes->num_rows > 0)
                ? $profileRes->fetch_assoc()
                : [
                    'photo'    => 'default.png',
                    'nickname' => '',
                    'fullname' => '',
                    'gender'   => 'Male',
                    'country'  => ''
                  ];



// ------------------ GOOGLE DRIVE API FUNCTIONS ------------------

function getDriveService() {
    $client = new Google_Client();
    $client->setAuthConfig(__DIR__ . '/config/credentials.json');
    $client->addScope(Google_Service_Drive::DRIVE);
    return new Google_Service_Drive($client);
}

function uploadFileToDrive($fileTmpPath, $originalName, $mimeType, $folderId = null) {
    $driveService = getDriveService();
    $extension    = pathinfo($originalName, PATHINFO_EXTENSION);
    $newFileName  = uniqid() . '.' . $extension;

    $fileMetadata = new Google_Service_Drive_DriveFile([
        'name' => $newFileName
    ]);
    if ($folderId) {
        $fileMetadata->setParents([$folderId]);
    }

    $content = file_get_contents($fileTmpPath);

    try {
        $file = $driveService->files->create($fileMetadata, [
            'data'       => $content,
            'mimeType'   => $mimeType,
            'uploadType' => 'multipart',
            'fields'     => 'id'
        ]);
        $permission = new Google_Service_Drive_Permission([
            'type' => 'anyone',
            'role' => 'reader'
        ]);
        $driveService->permissions->create($file->id, $permission);
        return $file->id;
    } catch (Exception $e) {
        error_log("Gagal mengupload file ke Google Drive: " . $e->getMessage());
        return false;
    }
}

// ------------------ END GOOGLE DRIVE API FUNCTIONS ------------------

// Fungsi memanggil API FastAPI untuk solusi error
function getAIErrorSolution($error_details, $error_connection) {
    $stmt = $error_connection->prepare(
        "INSERT INTO solutions (error_details, created_at) VALUES (?, NOW())"
    );
    if ($stmt) {
        $stmt->bind_param("s", $error_details);
        $stmt->execute();
        $stmt->close();
    }
    if (isset($_SESSION['error_code'], $_SESSION['error_message'])) {
        $stmt2 = $error_connection->prepare(
            "INSERT INTO solutions (error_code, error_message, error_details, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        if ($stmt2) {
            $stmt2->bind_param(
                "sss",
                $_SESSION['error_code'],
                $_SESSION['error_message'],
                $error_details
            );
            $stmt2->execute();
            $stmt2->close();
        }
    }
    $url = "http://localhost:5000/analyze_error?"
         . "error_code=" . urlencode($error_details)
         . "&error_message=" . urlencode($error_details);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Jika ada error di session, panggil analisis error
$aiResult = [];
if (isset($_SESSION['error_details']) && isset($error_connection)) {
    $aiResult = getAIErrorSolution($_SESSION['error_details'], $error_connection);
}

// ------------------ PROSES UPDATE PROFILE VIA AJAX ------------------
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nickname = mysqli_real_escape_string(
        $inventory_connection,
        $_POST['nickname'] ?? ''
    );
    $fullname = mysqli_real_escape_string(
        $inventory_connection,
        $_POST['fullname'] ?? ''
    );
    $gender   = mysqli_real_escape_string(
        $inventory_connection,
        $_POST['gender'] ?? 'Male'
    );
    $country  = mysqli_real_escape_string(
        $inventory_connection,
        $_POST['country'] ?? ''
    );

    $photo = "";
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $originalName = basename($_FILES['photo']['name']);
        $mimeType     = $_FILES['photo']['type'];
        $driveFileId  = uploadFileToDrive(
            $_FILES['photo']['tmp_name'],
            $originalName,
            $mimeType
        );
        if ($driveFileId) {
            $photo = "https://drive.google.com/uc?id=" . $driveFileId;
        } else {
            echo "Gagal mengupload file ke Google Drive.";
            exit();
        }
    }

    $updateQuery = "UPDATE pengguna SET "
                 . (!empty($photo)
                    ? "photo='$photo', "
                    : "")
                 . "nickname='$nickname', "
                 . "fullname='$fullname', "
                 . "gender='$gender', "
                 . "country='$country' "
                 . "WHERE id='{$_SESSION['user_id']}'";

    if ($inventory_connection->query($updateQuery)) {
        echo "success";
    } else {
        echo "error: " . $inventory_connection->error;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard User</title>
  <!-- FullCalendar, Chart.js, SweetAlert2, dan jQuery -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    /* ============ STYLE CSS ============ */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }
    body {
      margin: 0;
      padding: 20px;
      background: #f8f9fa;
    }
    header {
      text-align: center;
      margin-bottom: 20px;
    }
    .current-date {
      margin-top: 10px;
      font-size: 14px;
      color: #555;
    }
    .sidebar {
      position: fixed;
      left: -250px;
      top: 0;
      width: 250px;
      height: 100vh;
      background: #2c3e50;
      color: white;
      transition: 0.3s;
      z-index: 1000;
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
    .profile-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      display: block;
      margin: 10px auto 0;
    }
    .sidebar a, .sidebar button {
      display: block;
      padding: 15px;
      color: white;
      text-decoration: none;
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
    .main-content {
      margin-left: 20px;
      transition: margin-left 0.3s ease;
    }
    .content-container {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      margin-top: 20px;
    }
    .chart-container, .table-container {
      background: white;
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0px 4px 6px rgba(0,0,0,0.1);
      flex: 1;
      position: relative;
      height: 350px;
      overflow: hidden;
    }
    .chart-container h3, .table-container h3 {
      text-align: center;
      margin-bottom: 10px;
    }
    .chart-wrapper {
      position: relative;
      width: 100%;
      height: calc(100% - 40px);
    }
    .table-container table, .search-container table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    .table-container th, .table-container td, .search-container th, .search-container td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    .search-container {
      background: white;
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0px 4px 6px rgba(0,0,0,0.1);
      margin-top: 20px;
    }
    .search-container h3 {
      margin-bottom: 10px;
    }
    /* Popup Logout & Modal Edit Profile */
    .popup-container, .modal {
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
    .popup-content, .modal-content {
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
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <div class="sidebar" id="sidebar">
    <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
    <div class="sidebar-inner">
      <div class="sidebar-header">
        <h4 id="title">IMS</h4>
        <img src="<?= htmlspecialchars($profileData['photo']) ?>?t=<?= time() ?>" alt="Foto Profil" class="profile-img" />
      </div>
      <a href="user.php">🏠 Dashboard</a>
      <a href="javascript:void(0)" onclick="toggleDropdown('bhpDropdown')">📑 BHP</a>
      <div class="dropdown-content" id="bhpDropdown">
        <a href="barang_daftar.php">📋 Daftar Barang</a>
        <a href="barang_submit.php">📩 Daftar Pengajuan</a>
      </div>
      <a href="javascript:void(0)" onclick="toggleDropdown('alatlabDropdown')">🧪 Alat Lab</a>
      <div class="dropdown-content" id="alatlabDropdown">
        <a href="alat_daftar_user.php">📋 Daftar Alat</a>
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
      <h2>Dashboard User</h2>
      <div class="current-date"></div>
    </header>
    <!-- CHART & PENGUMUMAN -->
    <section class="content-container">
      <div class="chart-container">
        <h3>Jumlah Barang per Lab</h3>
        <div class="chart-wrapper">
          <canvas id="locationChart"></canvas>
        </div>
      </div>
      <div class="table-container">
        <h3>Pengumuman Terbaru</h3>
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Nama</th>
              <th>Kegiatan</th>
            </tr>
          </thead>
          <tbody id="announcementTableBody">
            <!-- Data pengumuman akan dirender secara dinamis -->
          </tbody>
        </table>
      </div>
    </section>
    <!-- SEARCH BARANG/ALAT -->
    <section class="search-container">
      <h3>Cari Barang/Alat</h3>
      <input type="text" id="searchInput" list="searchList" placeholder="Cari nama barang..." onkeyup="searchTable()" />
      <datalist id="searchList"></datalist>
      <table id="itemsTable">
        <thead>
          <tr>
            <th>Kode Barang</th>
            <th67>Nama Barang</th>
            <th>Jumlah</th>
            <th>Satuan</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result): ?>
            <?php while($r=$result->fetch_assoc()): ?>
              <tr>
                <td><?=htmlspecialchars($r['kode_barang'])?></td>
                <td><?=htmlspecialchars($r['nama_barang'])?></td>
                <td><?=htmlspecialchars($r['total_jumlah'])?></td>
                <td><?=htmlspecialchars($r['satuan'])?></td>
                <td><?=htmlspecialchars($r['lokasi'])?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5">No results found</td></tr>
          <?php endif; ?>
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
      <form id="updateProfileForm" enctype="multipart/form-data">
        <div>
          <label for="photo">Profile Picture:</label>
          <input type="file" name="photo" id="photo">
        </div>
        <div>
          <label for="nickname">Nickname:</label>
          <input type="text" name="nickname" id="nickname" value="<?= htmlspecialchars($profileData['nickname']); ?>">
        </div>
        <div>
          <label for="fullname">Full Name:</label>
          <input type="text" name="fullname" id="fullname" value="<?= htmlspecialchars($profileData['fullname']); ?>">
        </div>
        <div>
          <label for="gender">Gender:</label>
          <select name="gender" id="gender">
            <option value="Male" <?= ($profileData['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?= ($profileData['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
          </select>
        </div>
        <div>
          <label for="country">Country:</label>
          <input type="text" name="country" id="country" value="<?= htmlspecialchars($profileData['country']); ?>">
        </div>
        <input type="hidden" name="action" value="update_profile">
        <button type="submit">Update Profile</button>
      </form>
      <div id="updateResponseMessage"></div>
    </div>
  </div>

  <script>
  // Toggle Sidebar
  function toggleSidebar() {
    const sidebar     = document.getElementById("sidebar");
    const mainContent = document.getElementById("mainContent");
    if (sidebar.style.left === "-250px" || sidebar.style.left === "-100%") {
      sidebar.style.left       = "0";
      mainContent.style.marginLeft = "250px";
    } else {
      sidebar.style.left       = "-250px";
      mainContent.style.marginLeft = "20px";
    }
  }

  // Toggle Dropdown
  function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    dropdown.style.display = (dropdown.style.display === "block") ? "none" : "block";
  }

  // Pencarian barang/alat
  function searchTable() {
    const input  = document.getElementById("searchInput");
    const filter = input.value.toUpperCase();
    const table  = document.getElementById("itemsTable");
    const tr     = table.getElementsByTagName("tr");
    for (let i = 1; i < tr.length; i++) {
      const td = tr[i].getElementsByTagName("td")[0];
      if (td) {
        const txtValue = td.textContent || td.innerText;
        tr[i].style.display = (txtValue.toUpperCase().indexOf(filter) > -1) ? "" : "none";
      }
    }
  }

  // Pengumuman (dummy)
  function getAnnouncements() {
    const announcements = localStorage.getItem("announcements");
    return announcements ? JSON.parse(announcements) : [];
  }
  function updateAnnouncementTable() {
    const tableBody     = document.getElementById("announcementTableBody");
    const announcements = getAnnouncements();
    tableBody.innerHTML = "";
    announcements.forEach(a => {
      tableBody.innerHTML += `
        <tr>
          <td>${a.date}</td>
          <td>${a.name}</td>
          <td>${a.activity}</td>
        </tr>`;
    });
  }

  // DOMContentLoaded: tanggal, chart, pengumuman, error popup
  document.addEventListener("DOMContentLoaded", function () {
    // Tampilkan tanggal (id-ID)
    const dateElement = document.querySelector(".current-date");
    function updateDate() {
      const today   = new Date();
      const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
      dateElement.textContent = today.toLocaleDateString("id-ID", options);
    }
    updateDate();

    // Chart.js Dynamic: stok per lokasi
    const ctx       = document.getElementById("locationChart").getContext("2d");
    const locations = <?php echo json_encode($locations, JSON_UNESCAPED_UNICODE); ?>;
    const totals    = <?php echo json_encode($totals); ?>;
    new Chart(ctx, {
      type: "bar",
      data: {
        labels: locations,
        datasets: [{
          label: "Total Stok",
          data: totals,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // Render pengumuman dummy
    updateAnnouncementTable();

    // Jika ada error di session, tampilkan popup dengan SweetAlert2
    <?php if (isset($_SESSION['error_details'])): ?>
      Swal.fire({
        icon: 'error',
        title: 'Terjadi Error!',
        html: `
          <strong>Kode Error:</strong> <?= $_SESSION['error_code'] ?? 'N/A'; ?><br>
          <strong>Pesan:</strong> <?= $_SESSION['error_message'] ?? $_SESSION['error_details']; ?><br>
          <strong>Solusi:</strong> <?= $aiResult['solution'] ?? 'Belum ada solusi'; ?>
        `,
        confirmButtonText: 'Mengerti'
      });
    <?php endif; ?>
  });

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

  // Edit Profile (Modal)
  document.getElementById("editProfileLink").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("editProfileModal").style.display = "flex";
  });
  document.getElementById("closeEditProfile").addEventListener("click", function() {
    document.getElementById("editProfileModal").style.display = "none";
  });
  window.addEventListener("click", function(e) {
    const modal = document.getElementById("editProfileModal");
    if (e.target === modal) {
      modal.style.display = "none";
    }
  });

  // Autocomplete live ke DB
document.getElementById("searchInput").addEventListener("input", function(){
  const q  = this.value.trim();
  const dl = document.getElementById("searchList");
  if (q.length < 2) {
    dl.innerHTML = "";
    return;
  }
  fetch(`user.php?ajax_search=1&q=${encodeURIComponent(q)}`)
    .then(res => res.json())
    .then(arr => {
      dl.innerHTML = "";
      arr.forEach(name => {
        const opt = document.createElement("option");
        opt.value = name;
        dl.appendChild(opt);
      });
    });
});

// Autocomplete live ke DB
document.getElementById("searchInput").addEventListener("input", function(){
  const q  = this.value.trim();
  const dl = document.getElementById("searchList");
  if (q.length < 2) {
    dl.innerHTML = "";
    return;
  }
  fetch(`user.php?ajax_search=1&q=${encodeURIComponent(q)}`)
    .then(res => res.json())
    .then(arr => {
      dl.innerHTML = "";
      arr.forEach(name => {
        const opt = document.createElement("option");
        opt.value = name;
        dl.appendChild(opt);
      });
    });
});

// Filter tabel saat user pilih suggestion
document.getElementById("searchInput").addEventListener("change", function(){
  const filter = this.value.toUpperCase();
  document.querySelectorAll("#itemsTable tbody tr").forEach(tr => {
    const nama = tr.cells[1].textContent.toUpperCase();
    tr.style.display = nama.includes(filter) ? "" : "none";
  });
});

  // AJAX Submit Update Profil
  $("#updateProfileForm").on("submit", function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
      url: "user.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        if (response.trim() === "success") {
          $("#updateResponseMessage").html("<p style='color:green;'>Profil berhasil diperbarui!</p>");
          setTimeout(() => location.reload(), 1500);
        } else {
          $("#updateResponseMessage").html("<p style='color:red;'>Gagal memperbarui profil. " + response + "</p>");
        }
      },
      error: function() {
        $("#updateResponseMessage").html("<p style='color:red;'>Terjadi kesalahan.</p>");
      }
    });
  });
</script>
</body>
</html>