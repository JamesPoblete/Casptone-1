<?php
session_start(); // Start the session at the very beginning

require_once 'dbconnection.php'; // Ensure this points to the right file

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

// Connect to the database
$conn = connectDB();

// Set the desired time zone
date_default_timezone_set('Asia/Manila'); // Adjust as needed

// Get the current date for defaults
$currentYear = date('Y');
$currentMonth = date('m');
$currentDay = date('d');

// Handling Date Selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedYear = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT) ?: $currentYear;
    $selectedMonth = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT) ?: $currentMonth; // Set to currentMonth if not provided
    $selectedDay = filter_input(INPUT_POST, 'day', FILTER_VALIDATE_INT) ?: null; // Set to null if not provided
} else {
    $selectedYear = $currentYear;
    $selectedMonth = $currentMonth; // Default to current month
    $selectedDay = null;
}

// ==========================
// Debugging: Display Selected Filters
// ==========================
echo "<!-- Debugging: Selected Filters -->\n";
echo "<!-- Year: $selectedYear, Month: $selectedMonth, Day: $selectedDay -->\n";

// ==========================
// Determine the DATE Field Type
// ==========================

// Replace this with actual verification if possible
// For demonstration, let's assume DATE is DATE
$isDateTime = false; // Set to true if DATE is of type DATETIME or TIMESTAMP

// ==========================
// Fetch Today's Sales
// ==========================

if ($selectedDay && $selectedMonth && $selectedYear) {
    // Validate the date
    if (checkdate($selectedMonth, $selectedDay, $selectedYear)) {
        $selectedDate = "$selectedYear-" . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . "-" . str_pad($selectedDay, 2, '0', STR_PAD_LEFT);
    } else {
        // Invalid date, default to today's date
        $selectedDate = date('Y-m-d');
    }
} else {
    // If no specific day is selected, default to today's date
    $selectedDate = date('Y-m-d');
}

echo "<!-- Debugging: Selected Date for Today's Sales: $selectedDate -->\n";

if ($isDateTime) {
    // For DATETIME, use range to cover the entire day
    $selectedDateStart = "$selectedDate 00:00:00";
    $selectedDateEnd = "$selectedDate 23:59:59";
    $stmt_today_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE `DATE` BETWEEN :start AND :end AND PAYMENT_STATUS = 'Paid'");
    $stmt_today_sales->execute(['start' => $selectedDateStart, 'end' => $selectedDateEnd]);
    
} else {
    // For DATE, direct comparison
    $stmt_today_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE `DATE` = :selectedDate AND PAYMENT_STATUS = 'Paid'");
    $stmt_today_sales->execute(['selectedDate' => $selectedDate]);
    
}

$today_sales = $stmt_today_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;
echo "<!-- Debugging: Today's Sales: $today_sales -->\n";

// ==========================
// Fetch Weekly Sales
// ==========================

// Calculate the start of the week (Monday)
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek = date('Y-m-d'); // Up to today

echo "<!-- Debugging: Start of Week: $startOfWeek, End of Week: $endOfWeek -->\n";

if ($isDateTime) {
    $startOfWeekStart = "$startOfWeek 00:00:00";
    $endOfWeekEnd = "$endOfWeek 23:59:59";
    $stmt_weekly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE `DATE` BETWEEN :start AND :end AND PAYMENT_STATUS = 'Paid'");
    $stmt_weekly_sales->execute(['start' => $startOfWeek, 'end' => $endOfWeek]);
    
} else {
    $stmt_weekly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE `DATE` BETWEEN :start AND :end AND PAYMENT_STATUS = 'Paid'");
    $stmt_weekly_sales->execute(['start' => $startOfWeek, 'end' => $endOfWeek]);
    
}

$weekly_sales = $stmt_weekly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;
echo "<!-- Debugging: Weekly Sales: $weekly_sales -->\n";

// ==========================
// Fetch Monthly Sales
// ==========================

$monthlySalesCondition = "WHERE YEAR(`DATE`) = :selectedYear";
$params = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $monthlySalesCondition .= " AND MONTH(`DATE`) = :selectedMonth";
    $params['selectedMonth'] = $selectedMonth;
}

$stmt_monthly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry $monthlySalesCondition AND PAYMENT_STATUS = 'Paid'");
$stmt_monthly_sales->execute(array_filter($params));
$monthly_sales = $stmt_monthly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

echo "<!-- Debugging: Monthly Sales: $monthly_sales -->\n";

// ==========================
// Fetch Count of Today's Customers
// ==========================

if ($isDateTime) {
    // For DATETIME, use range
    $stmt_today_customers = $conn->prepare("SELECT COUNT(DISTINCT NAME) as customer_count FROM laundry WHERE `DATE` BETWEEN :start AND :end");
    $stmt_today_customers->execute(['start' => $selectedDateStart, 'end' => $selectedDateEnd]);
} else {
    // For DATE, direct comparison
    $stmt_today_customers = $conn->prepare("SELECT COUNT(DISTINCT NAME) as customer_count FROM laundry WHERE `DATE` = :selectedDate");
    $stmt_today_customers->execute(['selectedDate' => $selectedDate]);
}

$today_customers = $stmt_today_customers->fetch(PDO::FETCH_ASSOC)['customer_count'] ?: 0;
echo "<!-- Debugging: Today's Customers: $today_customers -->\n";

// ==========================
// Optional Detailed Query Debugging
// ==========================

/*
echo "<pre>";
echo "Today's Sales Query:\n";
print_r($stmt_today_sales->debugDumpParams());
echo "Weekly Sales Query:\n";
print_r($stmt_weekly_sales->debugDumpParams());
echo "Monthly Sales Query:\n";
print_r($stmt_monthly_sales->debugDumpParams());
echo "Today's Customers Query:\n";
print_r($stmt_today_customers->debugDumpParams());
echo "</pre>";
exit();
*/
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AN'E Laundry Dashboard</title>
    <link rel="stylesheet" href="../css/maindashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Include custom fonts if needed -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo">
        <ul>
            <li><a href="maindashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-box"></i> Inventory</a></li>
            <li><a href="../html/laundrylist.html"><i class="fas fa-list-alt"></i> Laundries List</a></li>
            <li><a href="../html/login.html" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header class="main-header">
            <!-- Filter Button and Panel -->
            <div class="filter-container">
                <button class="filter-button" id="filterToggle">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <!-- Filter Options Panel -->
                <div class="filter-panel" id="filterPanel">
                    <form method="POST" action="" class="filter-form">
                        <div class="filter-group">
                            <label for="year">Year:</label>
                            <select name="year" id="year">
                                <?php for ($i = 2022; $i <= 2025; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $selectedYear ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="month">Month:</label>
                            <select name="month" id="month">
                                <option value="">All</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $selectedMonth ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="day">Day:</label>
                            <select name="day" id="day">
                                <option value="">All</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $selectedDay ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="filter-buttons">
                            <button type="submit" class="apply-btn">Apply</button>
                            <button type="button" class="reset-btn" onclick="window.location.href='maindashboard.php';">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="header-right">
                <div class="user-profile">
                    <i class="fa fa-user-circle"></i>
                    <span>Staff</span>
                </div>
            </div>
        </header>

        <!-- Loading Spinner (Optional) -->
        <div class="loading-spinner" id="loadingSpinner" style="display: none;">
            <div class="spinner"></div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Cards Section -->
            <div class="cards">
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-info">
                        <h3>Today's Sales</h3>
                        <p>₱ <?php echo number_format($today_sales, 2); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-info">
                        <h3>Weekly Sales</h3>
                        <p>₱ <?php echo number_format($weekly_sales, 2); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="card-info">
                        <h3>Monthly Sales</h3>
                        <p>₱ <?php echo number_format($monthly_sales, 2); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3>Today's Customers</h3>
                        <p><?php echo number_format($today_customers, 0); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and Custom JS -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="../js/maindashboard.js"></script>

    <!-- Optional: Add script for active sidebar link -->
    <script>
        $(document).ready(function () {
            // Get the current path from the URL
            var path = window.location.pathname.split("/").pop();

            // Set default path for home page
            if (path === "") {
                path = "maindashboard.php";
            }

            // Find the sidebar link that matches the current path
            var target = $('.sidebar ul li a[href="' + path + '"]');

            // Add active class to the matching link
            target.addClass("active");
        });
    </script>

    <!-- Optional: Display Debugging Information (Remove in Production) -->
    <script>
        // You can access the PHP debugging comments in the HTML source
        // Example: Open the browser's developer tools and check the HTML comments for debugging information
    </script>

</body>
</html>
