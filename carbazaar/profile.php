<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to view your profile.";
    header("Location: login.php");
    exit();
}

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "car_rental_db";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die($e->getMessage());
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, phone, profile_pic, aadhaar_status, aadhaar_rejection_reason FROM users WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    try {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : null;

        $profile_pic = $user['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file = $_FILES['profile_pic'];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Only JPEG or PNG allowed.");
            }
            if ($file['size'] > $max_size) {
                throw new Exception("File size exceeds 2MB limit.");
            }
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = $user_id . '_' . time() . '_' . basename($file['name']);
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception("Failed to upload profile picture.");
            }
            $profile_pic = $file_path;
        }

        $query = "UPDATE users SET username = ?, email = ?, phone = ?, profile_pic = ?";
        $params = [$username, $email, $phone, $profile_pic];
        $types = "ssss";
        if ($password) {
            $query .= ", password = ?";
            $params[] = $password;
            $types .= "s";
        }
        $query .= " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Profile updated successfully!";
            $_SESSION['username'] = $username;
            header("Location: profile.php");
            exit();
        } else {
            throw new Exception("Failed to update profile: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --green: #008000;
            --accent: #4895ef;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        html {
            scroll-behavior: smooth; /* Enable smooth scrolling */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .profile-container {
            display: flex;
            gap: 30px;
            margin-top: 30px;
        }

        .profile-info {
            flex: 1;
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: block;
            object-fit: cover;
        }

        .profile-info h3 {
            font-size: 24px;
            color: var(--dark);
            text-align: center;
            margin: 0 0 15px;
        }

        .profile-info p {
            font-size: 16px;
            color: var(--gray);
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-info .btn {
            display: block;
            text-align: center;
            margin: 20px 0;
        }

        .profile-info form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-control {
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
        }

        .table-container {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
            font-size: 22px;
            color: var(--dark);
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            text-align: left;
        }

        th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--dark);
        }

        tr:hover {
            background-color: var(--light);
        }

        .car-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
            vertical-align: middle;
        }

        .car-name {
            display: flex;
            align-items: center;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            outline: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #d1145a;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Profile Actions Styles */
        .profile-actions {
            display: flex;
            align-items: center;
            gap: 20px; /* Increased spacing between icons */
        }

        .profile-action-icon {
            color: var(--primary);
            font-size: 22px; /* Slightly larger for visibility */
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .profile-action-icon:hover {
            color: var(--secondary);
        }

        /* Profile Dropdown Styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-dropdown-toggle {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .profile-dropdown-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .profile-dropdown-toggle i {
            margin-left: 8px;
            color: var(--primary);
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        .profile-dropdown-toggle.active i {
            transform: rotate(180deg);
        }

        .profile-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
            margin-top: 5px;
            transform: scale(0.8);
            opacity: 0;
            transform-origin: top right;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .profile-dropdown-menu.active {
            display: block;
            transform: scale(1);
            opacity: 1;
        }

        .profile-dropdown-menu li {
            list-style: none;
        }

        .profile-dropdown-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--dark);
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .profile-dropdown-menu a i {
            margin-right: 10px;
            color: var(--primary);
        }

        .profile-dropdown-menu a:hover {
            background-color: var(--light);
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .profile-container {
                flex-direction: column;
            }
            .action-buttons {
                flex-direction: column;
                align-items: flex-end;
            }
            .profile-dropdown-img {
                width: 32px;
                height: 32px;
            }
            .profile-action-icon {
                font-size: 18px;
            }
            .profile-dropdown-menu {
                min-width: 150px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-car"></i></div>
                <div class="logo-text">Car<span>Bazaar</span></div>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="profile-actions">
                    <a href="messages.php" class="profile-action-icon" title="Messages"><i class="fas fa-envelope"></i></a>
                    <a href="favorites.php" class="profile-action-icon" title="Favorites"><i class="fas fa-heart"></i></a>
                    <div class="profile-dropdown">
                        <div class="profile-dropdown-toggle" id="profileToggle">
                            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="Profile Picture" class="profile-dropdown-img">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <ul class="profile-dropdown-menu" id="profileMenu">
                            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                            <?php if ($_SESSION['user_type'] === 'seller' || $_SESSION['user_type'] === 'admin'): ?>
                                <li><a href="profile.php#car-listings"><i class="fas fa-car"></i> Your Listings</a></li>
                            <?php endif; ?>
                            <li><a href="index.php?logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="section-header">
            <h2 class="section-title">Your Profile</h2>
        </div>

        <div class="profile-container">
            <div class="profile-info">
                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="Profile Picture" class="profile-pic">
                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></p>
                <?php if ($_SESSION['user_type'] === 'seller'): ?>
                    <p><i class="fas fa-id-card"></i> Aadhaar Status: 
                        <span style="color: <?php echo $user['aadhaar_status'] == 'approved' ? 'var(--green)' : ($user['aadhaar_status'] == 'rejected' ? 'var(--danger)' : '#ffc107'); ?>">
                            <?php echo ucfirst($user['aadhaar_status']); ?>
                        </span>
                    </p>
                    <?php if ($user['aadhaar_status'] == 'rejected' && $user['aadhaar_rejection_reason']): ?>
                        <p style="color: var(--danger);"><strong>Rejection Reason:</strong> <?php echo htmlspecialchars($user['aadhaar_rejection_reason']); ?></p>
                    <?php endif; ?>
                    <a href="verify_aadhaar.php" class="btn btn-primary"><i class="fas fa-id-card"></i> Manage Aadhaar</a>
                <?php endif; ?>
                <h4>Update Profile</h4>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> New Password (Leave blank to keep current)</label>
                        <input type="password" id="password" name="password" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="profile_pic"><i class="fas fa-image"></i> Profile Picture</label>
                        <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/jpeg,image/png">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>CarBazaar</h3>
                    <p>Your trusted platform for buying and selling quality used cars across India.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#cars">Browse Cars</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="favorites.php">Favorites</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Help & Support</h3>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">How to Sell</a></li>
                        <li><a href="#">Buyer Guide</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Changanacherry</li>
                        <li><i class="fas fa-phone-alt"></i> +91 9876543210</li>
                        <li><i class="fas fa-envelope"></i> support@carbazaar.com</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // JavaScript for handling dropdown toggle and click outside to close
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('profileToggle');
            const menu = document.getElementById('profileMenu');

            // Toggle dropdown on click
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('active');
                toggle.classList.toggle('active');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('active');
                    toggle.classList.remove('active');
                }
            });

            // Close dropdown when clicking a menu item
            menu.querySelectorAll('a').forEach(item => {
                item.addEventListener('click', function () {
                    menu.classList.remove('active');
                    toggle.classList.remove('active');
                });
            });
        });
    </script>
</body>
</html>