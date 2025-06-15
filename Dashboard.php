<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS Homepage - Inventory Management System</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <nav>
            <ul>
                <li><a href="Dashboard.html">Home</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#Contact">Contact</a></li>
            </ul>
        </nav>
    </div>

    <!-- Banner Section -->
    <div class="banner">
        <div class="homepageContainer">
            <div class="bannerHeader">
                <h1>IMS</h1>
                <p>Inventory Management System</p>
            </div>
            <p class="bannerTagline">
                Track your goods throughout your entire supply chain, from purchasing to production to end sales.
            </p>
            <div class="bannerIcons">
                <a href="logins.php" class="login-button">
                    <i class="fa-solid fa-user"></i> Log In
                </a>
            </div>            
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="homepageFeatures">
        <div class="homepageFeature">
            <span><i class="fa-solid fa-gear"></i></span>
            <h3>Editable Theme</h3>
            <p>Customize your IMS theme to match your brand’s identity effortlessly.</p>
        </div>
        <div class="homepageFeature">
            <span><i class="fa-solid fa-star"></i></span>
            <h3>Customizable Interface</h3>
            <p>Modify and adjust the interface according to your workflow needs.</p>
        </div>
        <div class="homepageFeature">
            <span><i class="fa-solid fa-globe"></i></span>
            <h3>Global Access</h3>
            <p>Access your inventory system from anywhere in the world.</p>
        </div>
    </div>

    <!-- Notification Section -->
    <div id="Contact" class="homepageNotified">
        <div class="emailForm">
            <h3>Get Notified Of Any Updates!</h3>
            <p>Sign up to receive the latest updates and news about IMS.</p>
            <form id="contact-form" action="submit.php" method="POST">
                <input type="text" name="name" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="number" name="phone" placeholder="Phone No" required>
                <textarea name="message" rows="5" placeholder="Message" required></textarea> 
                <button type="submit" value="Submit" id="Submit">Submit</button>
            </form>
        </div>

        <!-- Video Section -->
        <div class="video">
            <iframe width="500" height="300" src="https://www.youtube.com/embed/KmuyFRVNQO8" frameborder="0" allowfullscreen></iframe>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; 2025 DSPM. All rights reserved.</p>
        <p>Follow us on 
            <a href="https://www.instagram.com/bpmb_kemendag?igsh=bDRzOWp6b2g3Nm9n"><i class="fa-brands fa-instagram"></i></a> 
            <a href="https://youtube.com/@ditjenpktnkemendag562?si=eC1JEG6DA8QsR7OC"><i class="fa-brands fa-youtube"></i></a> 
            <a href="#"><i class="fa-brands fa-linkedin"></i></a>
        </p>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            console.log("JavaScript Loaded"); // Debugging
    
            // Smooth Scrolling for Navbar Links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener("click", function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute("href").substring(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 50,
                            behavior: "smooth"
                        });
                    }
                });
            });
    
            // Form Submission with Google Apps Script
            const form = document.getElementById("contact-form");

            if (!form) {
                console.error("Form not found!");
                return;
            }

            form.addEventListener("submit", function (e) {
                e.preventDefault(); // Biar nggak reload halaman

                let formData = new FormData(form);

                fetch("http://localhost/web%20kemendag/submit.php", { 
                    method: "POST",
                    body: formData
                })

                .then(response => response.text()) 
                .then(data => {
                    console.log("Server Response:", data); // Debugging
                    alert(data); // Tampilkan response dari PHP
                })
                .catch(error => {
                    console.error("Error!", error);
                    alert("Oops! Something went wrong.");
                });
            });
        });
    </script>
    
    
</body>
</html>
