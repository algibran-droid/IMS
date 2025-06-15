<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Pastikan PHPMailer terinstal
$host = "localhost";
$user = "root";
$pass = ""; // Kosongin kalau pakai XAMPP default
$db   = "login";

// Buat koneksi
$con = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$con) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$error_message = ""; // Variabel untuk menampung pesan error

if (isset($_POST['submit'])) {
    // Ambil dan amankan data dari form
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $email    = mysqli_real_escape_string($con, $_POST['email']);
    $age      = mysqli_real_escape_string($con, $_POST['age']);
    $password_plain = $_POST['password'];
    $password = password_hash($password_plain, PASSWORD_DEFAULT); // Hash password

    // Cek apakah email sudah digunakan di tabel admin
    $verify_query = mysqli_query($con, "SELECT Email FROM admin WHERE Email='$email'");
    if (!$verify_query) {
        $error_message = "Error pada query: " . mysqli_error($con);
    } elseif (mysqli_num_rows($verify_query) != 0) {
        $error_message = "This Email is already in use, please try another one!";
    } else {
        // Simpan data ke database pada tabel admin
        $query = "INSERT INTO admin (Username, Email, Age, Password) VALUES ('$username', '$email', '$age', '$password')";
        if (mysqli_query($con, $query)) {
            // Kirim email konfirmasi
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = '#'; // Ganti dengan email kamu
                $mail->Password   = '#'; // Ganti dengan password aplikasi Gmail
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Set pengirim dan penerima
                $mail->setFrom('your-email@gmail.com', 'IMS Support');
                $mail->addAddress($email, $username);

                // Set format email ke HTML
                $mail->isHTML(true);
                $mail->Subject = 'Welcome to IMS - Registration Successful';
                $mail->Body    = "<h3>Hi, $username!</h3><p>Your account has been successfully registered in IMS. You can now log in and start managing inventory easily.</p>";

                $mail->send();
            } catch (Exception $e) {
                $error_message = "Registration successful, but email confirmation failed: " . $mail->ErrorInfo;
            }

            // Jika tidak ada error, redirect ke halaman login
            if (empty($error_message)) {
                header("Location: logins.php");
                exit;
            }
        } else {
            $error_message = "Error occurred during registration: " . mysqli_error($con);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - IMS</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Contoh CSS sederhana untuk menampilkan pesan error */
        .message.error {
            padding: 10px;
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="box form-box">
            <header>Sign Up</header>

            <!-- Tampilkan Pesan Error dengan pop-up alert -->
            <?php if (!empty($error_message)): ?>
                <script type="text/javascript">
                    alert("<?php echo addslashes($error_message); ?>");
                </script>
                <div class="message error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <div class="field input">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" autocomplete="off" required>
                </div>

                <div class="field input">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" autocomplete="off" required>
                </div>

                <div class="field input">
                    <label for="age">Age</label>
                    <input type="number" name="age" id="age" autocomplete="off" required>
                </div>

                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Sign Up">
                </div>
                
                <div class="links">
                    Already a member? <a href="logins.php">Sign In</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
