<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

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

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No car specified.";
    header("Location: index.php");
    exit();
}

$car_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $conn->prepare("SELECT cars.*, users.id AS seller_id, users.username AS seller_name, users.phone AS seller_phone, users.email AS seller_email, users.profile_pic AS seller_profile_pic 
                        FROM cars 
                        JOIN users ON cars.seller_id = users.id 
                        WHERE cars.id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Car not found.";
    header("Location: index.php");
    exit();
}
$car = $result->fetch_assoc();
$stmt->close();

// Handle favorite toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_favorite']) && isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND car_id = ?");
        $stmt->bind_param("ii", $user_id, $car_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
            $stmt->bind_param("ii", $user_id, $car_id);
            $action = "removed from";
        } else {
            $stmt = $conn->prepare("INSERT INTO favorites (user_id, car_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $car_id);
            $action = "added to";
        }
        if (!$stmt->execute()) {
            throw new Exception("Failed to update favorites: " . $stmt->error);
        }
        $_SESSION['message'] = "Car $action favorites!";
        header("Location: view_car.php?id=$car_id");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    $stmt->close();
}

// Check if car is in favorites
$is_favorite = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND car_id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
    $stmt->execute();
    $is_favorite = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

// Count unread messages for the logged-in user
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_count = $result->fetch_assoc()['unread'];
    $stmt->close();
}

// Function to format price in Indian number system
function formatIndianPrice($number) {
    $number = (int)$number;
    if ($number < 1000) {
        return $number;
    }
    $last_three = substr($number, -3);
    $remaining = substr($number, 0, -3);
    $formatted = '';
    if (strlen($remaining) > 2) {
        $formatted = substr($remaining, -2) . ',' . $last_three;
        $remaining = substr($remaining, 0, -2);
    } else {
        $formatted = $remaining . ',' . $last_three;
        $remaining = '';
    }
    while ($remaining) {
        if (strlen($remaining) > 2) {
            $formatted = substr($remaining, -2) . ',' . $formatted;
            $remaining = substr($remaining, 0, -2);
        } else {
            $formatted = $remaining . ',' . $formatted;
            $remaining = '';
        }
    }
    return rtrim($formatted, ',');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?> - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4361ee;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
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

        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-icon {
            font-size: 28px;
            color: var(--primary);
            margin-right: 10px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }

        .logo-text span {
            color: var(--primary);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 25px;
            position: relative;
        }

        nav ul li a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }

        nav ul li a i {
            margin-right: 8px;
            font-size: 18px;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        nav ul li .badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            align-items: center;
        }
	
	.login-margin{
	margin-right:10px;
	}

        .user-greeting {
            margin-right: 20px;
            font-weight: 500;
            color: var(--dark);
        }

        .user-greeting span {
            color: var(--primary);
            font-weight: 600;
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

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #0ea5e9;
            transform: translateY(-2px);
        }

        /* Car Details Styles */
        .car-details-container {
            display: flex;
            gap: 40px;
            max-width: 1200px;
            margin: 50px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            animation: slideIn 0.5s ease;
        }

        .car-images {
            flex: 1;
            position: relative;
        }

        .car-main-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 12px;
            transition: opacity 0.3s ease;
        }

        .sold-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 8px 15px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            background: linear-gradient(to right, var(--danger), #d1145a);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .car-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .car-gallery img {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease, border 0.3s ease;
            border: 2px solid transparent;
        }

        .car-gallery img:hover,
        .car-gallery img.active {
            transform: scale(1.05);
            border: 2px solid var(--primary);
        }

        .gallery-nav {
            position: absolute;
            top: 20%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            z-index: 10;
        }

        .gallery-nav button {
            background-color: rgba(0, 0, 0, 0.5);
            border: none;
            color: white;
            font-size: 20px;
            padding: 10px;
            cursor: pointer;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .gallery-nav button:hover {
            background-color: var(--primary);
            transform: scale(1.1);
        }

        .gallery-nav button:disabled {
            background-color: rgba(0, 0, 0, 0.3);
            cursor: not-allowed;
        }

        .car-info {
            flex: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .car-title {
            font-size: 32px;
            color: var(--dark);
            margin: 0;
            position: relative;
        }

        .car-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--primary);
            margin: 10px 0;
        }

        .car-price {
            font-size: 24px;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .car-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .car-spec {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--gray);
        }

        .car-spec i {
            color: var(--accent);
        }

        .car-description {
            font-size: 14px;
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .seller-info {
            background-color: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .seller-info h3 {
            font-size: 18px;
            color: var(--dark);
            margin: 0 0 15px;
        }

        .seller-info .seller-details {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .seller-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-gray);
        }

        .seller-info a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease, text-decoration 0.3s ease;
        }

        .seller-info a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .seller-info p {
            margin: 5px 0;
            font-size: 14px;
            color: var(--gray);
        }

        .car-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .favorite-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 24px;
            color: var(--gray);
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .favorite-btn.active {
            color: var(--danger);
        }

        .favorite-btn:hover {
            color: var(--danger);
            transform: scale(1.1);
        }

     .alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-weight: 500;
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

        /* Footer Styles */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0;
            margin-top: 40px;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .footer-column h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column li {
            margin-bottom: 10px;
        }

        .footer-column a {
            color: var(--light-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column a:hover {
            color: var(--primary);
        }

        .footer-social {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .footer-social a {
            color: white;
            font-size: 18px;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        /* Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .car-details-container {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
                margin: 20px auto;
            }
            .car-main-image {
                max-height: 300px;
            }
            .car-gallery img {
                width: 80px;
                height: 60px;
            }
            .gallery-nav button {
                font-size: 18px;
                width: 32px;
                height: 32px;
            }
            .car-title {
                font-size: 24px;
            }
            .car-price {
                font-size: 20px;
            }
            .car-specs {
                grid-template-columns: 1fr;
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
                    <li><a href="index.php#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="index.php#about"><i class="fas fa-info-circle"></i> About</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                        <li>
                            <a href="messages.php"><i class="fas fa-envelope"></i> Messages
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="user-actions">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="user-greeting">Welcome, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span></div>
                    <a href="index.php?logout" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline login-margin"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
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

        <div class="car-details-container">
            <div class="car-images">
                <?php if ($car['is_sold']): ?>
                    <div class="sold-badge">SOLD</div>
                <?php endif; ?>
                <img src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="Main Image" class="car-main-image" id="main-image">
                <div class="gallery-nav">
                    <button onclick="prevImage()" class="prev-btn"><i class="fas fa-chevron-left"></i></button>
                    <button onclick="nextImage()" class="next-btn"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="car-gallery">
                    <img src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="Main Image" class="sub-image active" onclick="changeMainImage(this.src, 0)">
                    <?php 
                    $images = [$car['main_image']];
                    $index = 1;
                    foreach (['sub_image1', 'sub_image2', 'sub_image3'] as $img_field): 
                        if ($car[$img_field]): 
                            $images[] = $car[$img_field];
                    ?>
                        <img src="<?php echo htmlspecialchars($car[$img_field]); ?>" alt="Car Image" class="sub-image" onclick="changeMainImage(this.src, <?php echo $index; ?>)">
                    <?php 
                        $index++;
                        endif; 
                    endforeach; 
                    ?>
                </div>
            </div>
            <div class="car-info">
                <h2 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2>
                <div class="car-price">₹<?php echo formatIndianPrice($car['price']); ?></div>
                <div class="car-specs">
                    <div class="car-spec"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($car['year']); ?></div>
                    <div class="car-spec"><i class="fas fa-tachometer-alt"></i> <?php echo formatIndianPrice($car['km_driven']); ?> km</div>
                    <div class="car-spec"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['fuel_type']); ?></div>
                    <div class="car-spec"><i class="fas fa-cog"></i> <?php echo htmlspecialchars($car['transmission']); ?></div>
                    <div class="car-spec"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($car['location']); ?></div>
                    <div class="car-spec"><i class="fas fa-user"></i> <?php echo htmlspecialchars($car['ownership']); ?> Owner</div>
                    <div class="car-spec"><i class="fas fa-shield-alt"></i> Insurance: <?php echo htmlspecialchars($car['insurance_status']); ?></div>
                </div>
                <h3>Description</h3>
                <p class="car-description"><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
                <div class="seller-info">
                    <h3>Seller Information</h3>
                    <div class="seller-details">
                        <img src="<?php echo htmlspecialchars($car['seller_profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="Seller Profile">
                        <div>
                            <p><a href="seller_profile.php?id=<?php echo $car['seller_id']; ?>"><?php echo htmlspecialchars($car['seller_name']); ?></a></p>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($car['seller_email']); ?></p>
                            <?php if ($car['seller_phone']): ?>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($car['seller_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="car-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                            <button type="submit" name="toggle_favorite" class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                        <?php if ($_SESSION['user_id'] != $car['seller_id']): ?>
                            <a href="messages.php?car_id=<?php echo $car['id']; ?>&seller_id=<?php echo $car['seller_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-envelope"></i> Chat with Seller
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Listings</a>
                </div>
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
        const images = [
            "<?php echo htmlspecialchars($car['main_image']); ?>",
            <?php foreach (['sub_image1', 'sub_image2', 'sub_image3'] as $img_field): ?>
                <?php if ($car[$img_field]): ?>
                    "<?php echo htmlspecialchars($car[$img_field]); ?>",
                <?php endif; ?>
            <?php endforeach; ?>
        ];
        let currentIndex = 0;

        function changeMainImage(src, index) {
            const mainImage = document.getElementById('main-image');
            mainImage.style.opacity = '0';
            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            }, 300);
            currentIndex = index;
            updateActiveImage();
            updateNavButtons();
        }

        function updateActiveImage() {
            const galleryImages = document.querySelectorAll('.car-gallery img');
            galleryImages.forEach((img, index) => {
                img.classList.toggle('active', index === currentIndex);
            });
        }

        function updateNavButtons() {
            const prevBtn = document.querySelector('.prev-btn');
            const nextBtn = document.querySelector('.next-btn');
            prevBtn.disabled = currentIndex === 0;
            nextBtn.disabled = currentIndex === images.length - 1;
        }

        function prevImage() {
            if (currentIndex > 0) {
                currentIndex--;
                changeMainImage(images[currentIndex], currentIndex);
            }
        }

        function nextImage() {
            if (currentIndex < images.length - 1) {
                currentIndex++;
                changeMainImage(images[currentIndex], currentIndex);
            }
        }

        updateActiveImage();
        updateNavButtons();
    </script>
</body>
</html>