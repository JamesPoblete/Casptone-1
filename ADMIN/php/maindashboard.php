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

// Get the current date for defaults
$currentYear = date('Y');
$currentMonth = date('m');
$currentDay = date('d');

// Handling Date Selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedYear = $_POST['year'];
    $selectedMonth = $_POST['month'] ?? null; // Default to null if not provided
    $selectedDay = $_POST['day'] ?? null; // Default to null if not provided
} else {
    $selectedYear = $currentYear;
    $selectedMonth = null;
    $selectedDay = null;
}

// ==========================
// Determine the DATE Field Type
// ==========================

// Initialize the variable
$isDateTime = false;

// Determine the DATE field type
try {
    $stmt = $conn->prepare("DESCRIBE laundry");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if (strtolower($column['Field']) === 'date') {
            // Check if the field type is DATETIME or TIMESTAMP
            $isDateTime = in_array(strtolower($column['Type']), ['datetime', 'timestamp']);
            echo "<!-- DATE field type: " . $column['Type'] . " -->\n"; // Remove after verification
            break; // Exit the loop once the DATE field is found
        }
    }
} catch (PDOException $e) {
    die("Error describing the laundry table: " . $e->getMessage());
}

// Default to false if DATE field is not found
if (!isset($isDateTime)) {
    $isDateTime = false;
    echo "<!-- DATE field not found. Defaulting to DATE type. -->\n"; // Debugging
}

// ==========================
// Fetch Today's Sales (Always Based on Current Date)
// ==========================

// Today's Sales - always based on current date using MySQL's CURDATE()
try {
    if ($isDateTime) {
        // For DATETIME or TIMESTAMP, use range to cover the entire day
        $stmt_today_sales = $conn->prepare("
        SELECT SUM(TOTAL) as total_sales 
        FROM laundry 
        WHERE `DATE` >= CURDATE() AND `DATE` < CURDATE() + INTERVAL 1 DAY AND `PAYMENT_STATUS` = 'Paid'
        ");
    } else {
        // For DATE, direct comparison
        $stmt_today_sales = $conn->prepare("
        SELECT SUM(TOTAL) as total_sales 
        FROM laundry 
        WHERE `DATE` >= CURDATE() AND `DATE` < CURDATE() + INTERVAL 1 DAY AND `PAYMENT_STATUS` = 'Paid'
        ");
    }
    $stmt_today_sales->execute();
    $today_sales = $stmt_today_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;
    echo "<!-- Debugging: Today's Sales Query Executed Successfully. Total Sales: $today_sales -->\n";
} catch (PDOException $e) {
    die("Error fetching today's sales: " . $e->getMessage());
}

// ==========================
// Fetch Sales Data
// ==========================

// Monthly Sales
$monthlySalesCondition = "WHERE YEAR(DATE) = :selectedYear";
$params = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $monthlySalesCondition .= " AND MONTH(DATE) = :selectedMonth";
    $params['selectedMonth'] = $selectedMonth;
}

$stmt_monthly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry $monthlySalesCondition AND PAYMENT_STATUS = 'Paid'");
$stmt_monthly_sales->execute(array_filter($params));
$monthly_sales = $stmt_monthly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;


$stmt_yearly_sales = $conn->prepare("
    SELECT SUM(TOTAL) as total_sales 
    FROM laundry 
    WHERE YEAR(`DATE`) = :selectedYear AND `PAYMENT_STATUS` = 'Paid'
");
$stmt_yearly_sales->execute(['selectedYear' => $selectedYear]);
$yearly_sales = $stmt_yearly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

// Inventory Expenses
$inventoryExpensesCondition = "WHERE YEAR(ExpenseDate) = :selectedYear";
$params_inventory_expenses = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $inventoryExpensesCondition .= " AND MONTH(ExpenseDate) = :selectedMonth";
    $params_inventory_expenses['selectedMonth'] = $selectedMonth;
}
if ($selectedDay) {
    $inventoryExpensesCondition .= " AND DAY(ExpenseDate) = :selectedDay";
    $params_inventory_expenses['selectedDay'] = $selectedDay;
}

$stmt_inventory_expenses = $conn->prepare("SELECT SUM(Amount) as total_expenses FROM inventory_expenses $inventoryExpensesCondition");
$stmt_inventory_expenses->execute(array_filter($params_inventory_expenses));
$total_inventory_expenses = $stmt_inventory_expenses->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?: 0;

// ==========================
// Calculate Revenue 
// ==========================

if ($selectedDay) {
    $total_revenue = $today_sales - $total_inventory_expenses;
} elseif ($selectedMonth) {
    $total_revenue = $monthly_sales - $total_inventory_expenses;
} else {
    $total_revenue = $yearly_sales - $total_inventory_expenses;
}

// Sales by Month
$stmt_sales_by_month = $conn->prepare("
    SELECT MONTH(DATE) as month, SUM(TOTAL) as total_sales
    FROM laundry
    WHERE YEAR(DATE) = :selectedYear
    GROUP BY month
    ORDER BY month
");
$stmt_sales_by_month->execute(['selectedYear' => $selectedYear]);
$sales_by_month = $stmt_sales_by_month->fetchAll(PDO::FETCH_ASSOC);

// Prepare the sales data for each month (initialize an array for all 12 months)
$monthly_sales_data = array_fill(1, 12, 0); // Array for all 12 months
foreach ($sales_by_month as $sale) {
    $monthly_sales_data[(int)$sale['month']] = (float)$sale['total_sales'];
}

// Convert the monthly sales data to a format usable by JavaScript
echo "<script>const salesByMonth = " . json_encode(array_values($monthly_sales_data)) . ";</script>";

// Convert the revenue to JavaScript (if needed for display in charts or other UI components)
echo "<script>const totalRevenue = " . json_encode($total_revenue) . ";</script>";

// ==========================
// Fetch Detergents Data
// ==========================

// Top Used Detergents
$detergentCondition = "WHERE YEAR(DATE) = :selectedYear";
$paramsDetergent = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $detergentCondition .= " AND MONTH(DATE) = :selectedMonth";
    $paramsDetergent['selectedMonth'] = $selectedMonth;
}
if ($selectedDay) {
    $detergentCondition .= " AND DAY(DATE) = :selectedDay";
    $paramsDetergent['selectedDay'] = $selectedDay;
}

$stmt_top_detergents = $conn->prepare("SELECT DETERGENT, COUNT(*) as count FROM laundry $detergentCondition GROUP BY DETERGENT ORDER BY count DESC LIMIT 5");
$stmt_top_detergents->execute(array_filter($paramsDetergent));
$top_detergents = $stmt_top_detergents->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for the pie chart
$detergent_names = [];
$detergent_counts = [];
foreach ($top_detergents as $detergent) {
    $detergent_names[] = $detergent['DETERGENT'];
    $detergent_counts[] = $detergent['count'];
}

// Top Used Fabric Detergents
$fabricDetergentCondition = "WHERE YEAR(DATE) = :selectedYear";
$paramsFabricDetergent = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $fabricDetergentCondition .= " AND MONTH(DATE) = :selectedMonth";
    $paramsFabricDetergent['selectedMonth'] = $selectedMonth;
}
if ($selectedDay) {
    $fabricDetergentCondition .= " AND DAY(DATE) = :selectedDay";
    $paramsFabricDetergent['selectedDay'] = $selectedDay;
}

$stmt_top_fabric_detergents = $conn->prepare("SELECT FABRIC_DETERGENT, COUNT(*) as count FROM laundry $fabricDetergentCondition GROUP BY FABRIC_DETERGENT ORDER BY count DESC LIMIT 5");
$stmt_top_fabric_detergents->execute(array_filter($paramsFabricDetergent));
$top_fabric_detergents = $stmt_top_fabric_detergents->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for the fabric detergent pie chart
$fabric_detergent_names = [];
$fabric_detergent_counts = [];
foreach ($top_fabric_detergents as $detergent) {
    $fabric_detergent_names[] = $detergent['FABRIC_DETERGENT'];
    $fabric_detergent_counts[] = $detergent['count'];
}

// ==========================
// Fetch Customer and Sales Data
// ==========================

// Total Customers and Days
$customerCondition = "WHERE YEAR(DATE) = :selectedYear";
$paramsCustomer = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $customerCondition .= " AND MONTH(DATE) = :selectedMonth";
    $paramsCustomer['selectedMonth'] = $selectedMonth;
}

$stmt_total_customers = $conn->prepare("
    SELECT COUNT(*) as total_customers, COUNT(DISTINCT DATE) as total_days
    FROM laundry
    $customerCondition
");
$stmt_total_customers->execute(array_filter($paramsCustomer));
$total_customers_data = $stmt_total_customers->fetch(PDO::FETCH_ASSOC);

$total_customers = $total_customers_data['total_customers'] ?: 0;
$total_days = $total_customers_data['total_days'] ?: 1; // Avoid division by zero

// Calculate the average number of customers per day
$average_customers_per_day = $total_customers / $total_days;

// Pass the average customers per day to JavaScript
echo "<script>const averageCustomersPerDay = " . json_encode($average_customers_per_day) . ";</script>";

// Average Sales
$salesCondition = "WHERE YEAR(DATE) = :selectedYear";
$paramsSales = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $salesCondition .= " AND MONTH(DATE) = :selectedMonth";
    $paramsSales['selectedMonth'] = $selectedMonth;
}

$stmt_total_sales = $conn->prepare("
    SELECT SUM(TOTAL) as total_sales, COUNT(DISTINCT DATE) as total_days
    FROM laundry
    $salesCondition
");
$stmt_total_sales->execute(array_filter($paramsSales));
$total_sales_data = $stmt_total_sales->fetch(PDO::FETCH_ASSOC);

// Get total sales and number of distinct days
$total_sales = $total_sales_data['total_sales'] ?: 0;  // If no sales, set to 0
$total_days_sales = $total_sales_data['total_days'] ?: 1;  // If no distinct days, set to 1 to avoid division by zero

// Calculate the average sales per day
$average_sales_per_day = $total_sales / $total_days_sales;

// Pass the average sales per day to JavaScript
echo "<script>const averageSalesPerDay = " . json_encode($average_sales_per_day) . ";</script>";

// ==========================
// Fetch Inventory Data
// ==========================

$stmt_inventory = $conn->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID");
$stmt_inventory->execute(['userID' => $_SESSION['userID']]);
$inventory_data = $stmt_inventory->fetchAll(PDO::FETCH_ASSOC);

// Prepare the data for Chart.js
$inventory_names = [];
$inventory_stock_data = [];

foreach ($inventory_data as $inventory) {
    $inventory_names[] = $inventory['ProductName'];
    $inventory_stock_data[] = (int)$inventory['CurrentStock']; // Cast to integer for Chart.js
}

// Pass the data to JavaScript
echo "<script>
    const inventoryNames = " . json_encode($inventory_names) . ";
    const inventoryStockData = " . json_encode($inventory_stock_data) . ";
</script>";

// ==========================
// Fetch Inventory Expenses Data
// ==========================

$expensesCondition = "WHERE ie.InventoryID = i.InventoryID AND i.userID = :userID";
$paramsExpenses = ['userID' => $_SESSION['userID']];

if ($selectedYear) {
    $expensesCondition .= " AND YEAR(ie.ExpenseDate) = :selectedYear";
    $paramsExpenses['selectedYear'] = $selectedYear;
}
if ($selectedMonth) {
    $expensesCondition .= " AND MONTH(ie.ExpenseDate) = :selectedMonth";
    $paramsExpenses['selectedMonth'] = $selectedMonth;
}
if ($selectedDay) {
    $expensesCondition .= " AND DAY(ie.ExpenseDate) = :selectedDay";
    $paramsExpenses['selectedDay'] = $selectedDay;
}

// Query to get total inventory expenses
$stmt_total_expenses = $conn->prepare("SELECT SUM(ie.Amount) as total_expenses FROM inventory_expenses ie JOIN inventory i ON ie.InventoryID = i.InventoryID $expensesCondition");
$stmt_total_expenses->execute(array_filter($paramsExpenses));
$total_expenses = $stmt_total_expenses->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?: 0;

// Query to get expenses grouped by month (for bar chart)
$expensesByMonthCondition = "WHERE ie.InventoryID = i.InventoryID AND i.userID = :userID";
$paramsExpensesByMonth = ['userID' => $_SESSION['userID']];

if ($selectedYear) {
    $expensesByMonthCondition .= " AND YEAR(ie.ExpenseDate) = :selectedYear";
    $paramsExpensesByMonth['selectedYear'] = $selectedYear;
}
if ($selectedMonth) {
    $expensesByMonthCondition .= " AND MONTH(ie.ExpenseDate) = :selectedMonth";
    $paramsExpensesByMonth['selectedMonth'] = $selectedMonth;
}

$stmt_expenses_by_month = $conn->prepare("
    SELECT MONTH(ie.ExpenseDate) as month, SUM(ie.Amount) as total_expenses
    FROM inventory_expenses ie
    JOIN inventory i ON ie.InventoryID = i.InventoryID
    $expensesByMonthCondition
    GROUP BY month
    ORDER BY month ASC
");
$stmt_expenses_by_month->execute(array_filter($paramsExpensesByMonth));
$expenses_by_month = $stmt_expenses_by_month->fetchAll(PDO::FETCH_ASSOC);

// Prepare the expenses data for each month (initialize an array for all 12 months)
$monthly_expenses_data = array_fill(1, 12, 0); // Array for all 12 months
foreach ($expenses_by_month as $expense) {
    $monthly_expenses_data[(int)$expense['month']] = (float)$expense['total_expenses'];
}

// Convert the monthly expenses data to a format usable by JavaScript
echo "<script>const expensesByMonthData = " . json_encode(array_values($monthly_expenses_data)) . ";</script>";

// ==========================
// Fetch Predicted Sales from Flask API
// ==========================

function fetchPredictedSales($year, $month, $day) {
    // URL of your Flask API
    $apiUrl = 'http://127.0.0.1:5000/predict';

    // Your API key
    $apiKey = 'testkey123'; // Replace with your actual API key

    // Build query parameters
    $queryParams = http_build_query([
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'api_key' => $apiKey // Pass API key as query parameter
    ]);

    // Initialize cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, "$apiUrl?$queryParams");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout after 10 seconds

    // Execute the request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => $error_msg];
    }

    // Close cURL
    curl_close($ch);

    // Decode JSON response
    $result = json_decode($response, true);

    // Check if JSON decoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response from API'];
    }

    return $result;
}

// Fetch prediction data
$prediction_data = fetchPredictedSales($selectedYear, $selectedMonth, $selectedDay);

$predicted_sales = $prediction_data['predicted_sales'] ?? null;
$predicted_month = $prediction_data['next_period'] ?? null;
$prediction_error = $prediction_data['error'] ?? null;

// Pass the predicted sales to JavaScript
echo "<script>
    const predictedSales = " . json_encode($predicted_sales) . ";
    const predictedMonth = " . json_encode($predicted_month) . ";
    const predictionError = " . json_encode($prediction_error) . ";
</script>";

// ==========================
// Fetch Actual Sales and Historical Predictions Data for the Chart
// ==========================

// Fetch actual monthly sales data for the selected year
$stmt_actual_sales = $conn->prepare("
    SELECT DATE_FORMAT(DATE, '%Y-%m') as month, SUM(TOTAL) as total_sales
    FROM laundry
    WHERE YEAR(DATE) = :selectedYear
    GROUP BY month
    ORDER BY month
");
$stmt_actual_sales->execute(['selectedYear' => $selectedYear]);
$actual_sales_data = $stmt_actual_sales->fetchAll(PDO::FETCH_ASSOC);

// Fetch historical predictions from sales_predictions table for the selected year or previous year if necessary
$stmt_predicted_sales = $conn->prepare("
    SELECT DATE_FORMAT(prediction_date, '%Y-%m') as month, predicted_sales
    FROM sales_predictions
    WHERE YEAR(prediction_date) = :selectedYear OR (YEAR(prediction_date) = :previousYear AND :selectedYear > YEAR(NOW()))
    ORDER BY prediction_date
");
$stmt_predicted_sales->execute(['selectedYear' => $selectedYear, 'previousYear' => $selectedYear - 1]);
$predicted_sales_data = $stmt_predicted_sales->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JavaScript
$actual_sales_labels = [];
$actual_sales_values = [];
$predicted_sales_values = [];

// Populate actual sales data
foreach ($actual_sales_data as $data) {
    $actual_sales_labels[] = $data['month'];
    $actual_sales_values[] = (float)$data['total_sales'];
}

// Populate predicted sales data based on the same labels
foreach ($actual_sales_labels as $month) {
    // Find the matching predicted sales for the month or set it to null if not available
    $predicted_value = null;
    foreach ($predicted_sales_data as $pred) {
        if ($pred['month'] === $month) {
            $predicted_value = (float)$pred['predicted_sales'];
            break;
        }
    }
    $predicted_sales_values[] = $predicted_value;
}

// Add the next month’s prediction if available
if ($predicted_sales !== null && $predicted_month !== null) {
    $actual_sales_labels[] = date('Y-m', strtotime($predicted_month)); // Format next period to 'YYYY-MM'
    $actual_sales_values[] = null; // No actual sales for the next month
    $predicted_sales_values[] = (float)$predicted_sales; // Add the predicted sales for the next month
}

// Pass the data to JavaScript
echo "<script>
    const salesLabels = " . json_encode($actual_sales_labels) . ";
    const actualSalesData = " . json_encode($actual_sales_values) . ";
    const predictedSalesData = " . json_encode($predicted_sales_values) . ";
</script>";

// ==========================
// Fetch Low and Out-of-Stock Inventory Data
// ==========================

// Define the threshold for low stock
$low_stock_threshold = 5;

// Query to fetch out-of-stock products
$stmt_out_of_stock = $conn->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID AND CurrentStock = 0");
$stmt_out_of_stock->execute(['userID' => $_SESSION['userID']]);
$out_of_stock_products = $stmt_out_of_stock->fetchAll(PDO::FETCH_ASSOC);

// Query to fetch low-stock products
$stmt_low_stock = $conn->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID AND CurrentStock > 0 AND CurrentStock <= :threshold");
$stmt_low_stock->execute(['userID' => $_SESSION['userID'], 'threshold' => $low_stock_threshold]);
$low_stock_products = $stmt_low_stock->fetchAll(PDO::FETCH_ASSOC);

// Combine both out-of-stock and low-stock products
$notifications = [
    'out_of_stock' => $out_of_stock_products,
    'low_stock' => $low_stock_products
];

// Calculate the total number of notifications
$total_notifications = count($out_of_stock_products) + count($low_stock_products);

// Pass the notifications data to JavaScript
echo "<script>
    const notifications = " . json_encode($notifications) . ";
    const totalNotifications = " . json_encode($total_notifications) . ";
</script>";
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
            <li><a href="../php/laundry.php"><i class="fas fa-list-alt"></i> Laundries List</a></li>
            <li><a href="../php/manageuser.php"><i class="fas fa-users-cog"></i> Manage User</a></li>
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
                <!-- Notifications Icon -->
                <div class="notifications">
                    <i class="fas fa-bell" id="notificationsIcon"></i>
                    <?php if ($total_notifications > 0): ?>
                        <span class="badge" id="notificationBadge"><?php echo $total_notifications; ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-profile">
                    <i class="fa fa-user-circle"></i>
                    <span>Admin</span>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Cards Section -->
            <div class="cards">
                <div class="row">
                    <div class="card">
                        <h3><i class="fas fa-money-bill-wave"></i> Today's Sales</h3>
                        <p>₱ <?php echo number_format($today_sales, 2); ?></p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-chart-bar"></i> Monthly Sales</h3>
                        <p>₱ <?php echo number_format($monthly_sales, 2); ?></p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-calendar-check"></i> Yearly Sales</h3>
                        <p>₱ <?php echo number_format($yearly_sales, 2); ?></p>
                    </div>
                    <div class="card predicted-sales">
                        <h3><i class="fas fa-chart-pie"></i> Predicted Sales for <span id="predictedMonth"><?php echo htmlspecialchars($predicted_month); ?></span></h3>
                        <p id="predictedSales">
                            <?php
                            if ($predicted_sales !== null) {
                                echo '₱ ' . number_format($predicted_sales, 2);
                            } elseif ($prediction_error !== null) {
                                echo 'Error: ' . htmlspecialchars($prediction_error);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <div class="row">
                    <div class="card">
                        <h3><i class="fas fa-balance-scale-right"></i> Total Revenue</h3>
                        <p>₱ <?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-chart-line"></i> Average Sales</h3>
                        <p>₱ <?php echo number_format($average_sales_per_day, 2); ?></p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-box"></i> Inventory Expenses</h3>
                        <p>₱ <span id="totalExpenses"><?php echo number_format($total_expenses, 2); ?></span></p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-user-friends"></i> Average Customers</h3>
                        <p><?php echo number_format($average_customers_per_day, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Line Chart for Actual vs Predicted Sales -->
            <div class="line-chart-container">
                <h3>Actual Sales vs Predicted Sales</h3>
                <canvas id="salesLineChart"></canvas>
            </div>

            <!-- Charts Section -->
            <div class="charts-container">
                <div class="bar-chart-container">
                    <h3>Sales Overview</h3>
                    <canvas id="barChart"></canvas>
                </div>
                <div class="bar-chart-container">
                    <h3>Inventory Expenses Overview</h3>
                    <canvas id="expensesChart"></canvas>
                </div>
            </div>
            <div class="charts-container">
                <div class="bar-chart-container">
                    <h3>Inventory Stock Levels</h3>
                    <canvas id="inventoryStockBarChart"></canvas>
                </div>
                <div class="pie-charts">
                    <div class="pie-chart-container">
                        <h3>Top Used Detergents</h3>
                        <canvas id="pieChart"></canvas>
                    </div>
                    <div class="pie-chart-container">
                        <h3>Top Used Fabric Detergents</h3>
                        <canvas id="fabricPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="notifmodal">
        <div class="modal-content">
            <span class="close-button" id="closeModal">&times;</span>
            <h2>Notifications</h2>
            <?php if ($total_notifications > 0): ?>
                <div class="notifications-section">
                    <?php if (count($out_of_stock_products) > 0): ?>
                        <h3>Out of Stock</h3>
                        <ul>
                            <?php foreach ($out_of_stock_products as $product): ?>
                                <li><?php echo htmlspecialchars($product['ProductName']); ?> - Current Stock: <?php echo htmlspecialchars($product['CurrentStock']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (count($low_stock_products) > 0): ?>
                        <h3>Low Stock</h3>
                        <ul>
                            <?php foreach ($low_stock_products as $product): ?>
                                <li><?php echo htmlspecialchars($product['ProductName']); ?> - Current Stock: <?php echo htmlspecialchars($product['CurrentStock']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p>No inventory issues detected.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS (if needed) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <!-- Include Moment.js and the date adapter for Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.1.0"></script>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1"></script>

    <script>
        // Custom Shadow Plugin for Chart.js
        const shadowPlugin = {
            id: 'shadowPlugin',
            beforeDatasetsDraw: (chart, args, options) => {
                const ctx = chart.ctx;
                ctx.save();
                ctx.shadowColor = options.shadowColor || 'rgba(0, 0, 0, 0.1)';
                ctx.shadowBlur = options.shadowBlur || 10;
                ctx.shadowOffsetX = options.shadowOffsetX || 0;
                ctx.shadowOffsetY = options.shadowOffsetY || 0;
            },
            afterDatasetsDraw: (chart, args, options) => {
                chart.ctx.restore();
            }
        };

        // Register the Shadow Plugin
        Chart.register(shadowPlugin);

        // Chart Data Preparation
        const detergentNames = <?php echo json_encode($detergent_names); ?>;
        const detergentCounts = <?php echo json_encode($detergent_counts); ?>;
        const fabricDetergentNames = <?php echo json_encode($fabric_detergent_names); ?>;
        const fabricDetergentCounts = <?php echo json_encode($fabric_detergent_counts); ?>;

        // ==========================
        // Bar Chart for Monthly Sales
        // ==========================
        const ctxBar = document.getElementById('barChart').getContext('2d');

        // Create gradient for monthly sales bars
        const gradientSales = ctxBar.createLinearGradient(0, 0, 0, 400);
        gradientSales.addColorStop(0, 'rgba(99, 132, 255, 0.8)');
        gradientSales.addColorStop(1, 'rgba(54, 162, 235, 0.8)');

        const barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Sales (₱)',
                    data: salesByMonth,
                    backgroundColor: gradientSales,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 30,
                    hoverBackgroundColor: 'rgba(54, 162, 235, 1)',
                    hoverBorderColor: 'rgba(54, 162, 235, 1)',
                }]
            },
            options: {
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: 'bold' },
                            color: '#666'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(200, 200, 200, 0.5)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) { return '₱ ' + value; },
                            font: { size: 12 },
                            color: '#666'
                        },
                        beginAtZero: true
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(99, 132, 255, 0.8)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });

        // ==========================
        // Bar Chart for Inventory Expenses
        // ==========================
        const ctxExpenses = document.getElementById('expensesChart').getContext('2d');

        // Create gradient for inventory expenses bars
        const gradientExpenses = ctxExpenses.createLinearGradient(0, 0, 0, 400);
        gradientExpenses.addColorStop(0, 'rgba(255, 99, 132, 0.8)');
        gradientExpenses.addColorStop(1, 'rgba(255, 159, 64, 0.8)');

        const expensesChart = new Chart(ctxExpenses, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Inventory Expenses (₱)',
                    data: expensesByMonthData,
                    backgroundColor: gradientExpenses,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 30,
                    hoverBackgroundColor: 'rgba(255, 99, 132, 1)',
                    hoverBorderColor: 'rgba(255, 99, 132, 1)',
                }]
            },
            options: {
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: 'bold' },
                            color: '#666'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(200, 200, 200, 0.5)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) { return '₱ ' + value; },
                            font: { size: 12 },
                            color: '#666'
                        },
                        beginAtZero: true
                    }
                },
                responsive: true,
                maintainAspectRatio: false, // Ensures the chart adjusts to the container size
                plugins: {
                    legend: { 
                        display: true, // Enable legend
                        position: 'top', // Set legend position to top
                        labels: {
                            font: { size: 12 }, // Adjust font size
                            color: '#333' // Set label color
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 99, 132, 0.9)', // Slightly darker tooltip background for better readability
                        titleFont: { size: 14, weight: 'bold' }, // Bold title font
                        bodyFont: { size: 12 }, // Font size for tooltip body
                        padding: 10, // Add padding around tooltip content
                        cornerRadius: 5, // Round tooltip corners
                        callbacks: {
                            label: function(context) {
                                // Add '₱' prefix and format the number with commas
                                return context.dataset.label + ': ₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });

        // ==========================
        // Pie Chart for Top Used Detergents
        // ==========================
        const ctxPie = document.getElementById('pieChart').getContext('2d');

        const pieGradient = [
            'rgba(54, 162, 235, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 205, 86, 0.8)',
            'rgba(255, 99, 132, 0.8)'
        ];

        const borderColorsPie = [
            'rgba(54, 162, 235, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 205, 86, 1)',
            'rgba(255, 99, 132, 1)'
        ];

        const pieChart = new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: detergentNames,
                datasets: [{
                    data: detergentCounts,
                    backgroundColor: pieGradient,
                    borderColor: borderColorsPie,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#1D2B53',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                label += context.parsed + ' uses';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // ==========================
        // Pie Chart for Top Used Fabric Detergents
        // ==========================
        const ctxFabricPie = document.getElementById('fabricPieChart').getContext('2d');

        const fabricPieGradient = [
            'rgba(255, 159, 64, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 99, 132, 0.8)'
        ];

        const borderColorsFabricPie = [
            'rgba(255, 159, 64, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 99, 132, 1)'
        ];

        const fabricPieChart = new Chart(ctxFabricPie, {
            type: 'pie',
            data: {
                labels: fabricDetergentNames,
                datasets: [{
                    data: fabricDetergentCounts,
                    backgroundColor: fabricPieGradient,
                    borderColor: borderColorsFabricPie,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#1D2B53',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 159, 64, 0.8)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                label += context.parsed + ' uses';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // ==========================
        // Bar Chart for Inventory Stock Levels (Horizontal)
        // ==========================
        const inventoryStockBarChart = document.getElementById('inventoryStockBarChart').getContext('2d');

        // Create gradient for the bars
        const gradientInventory = inventoryStockBarChart.createLinearGradient(0, 0, inventoryStockBarChart.canvas.width, 0);
        gradientInventory.addColorStop(0, 'rgba(75, 192, 192, 0.8)');
        gradientInventory.addColorStop(1, 'rgba(153, 102, 255, 0.8)');

        const barChart2 = new Chart(inventoryStockBarChart, {
            type: 'bar',
            data: {
                labels: inventoryNames, // Data from PHP
                datasets: [{
                    label: 'Stock Levels',
                    data: inventoryStockData, // Data from PHP
                    backgroundColor: gradientInventory, // Use gradient
                    borderColor: 'rgba(75, 192, 192, 1)', // Border color
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 30,
                    hoverBackgroundColor: 'rgba(75, 192, 192, 1)',
                    hoverBorderColor: 'rgba(75, 192, 192, 1)',
                }]
            },
            options: {
                indexAxis: 'y', // This makes the bar chart horizontal
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            callback: function(value) { return ' ' + value.toLocaleString(); },
                            font: { size: 12, weight: 'bold' },
                            color: '#666'
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: 'bold' },
                            color: '#666'
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                return '' + context.parsed.x.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // ==========================
        // Line Chart for Actual Sales vs Predicted Sales with Enhancements
        // ==========================
        const ctxLine = document.getElementById('salesLineChart').getContext('2d');

        // Create gradients for the lines
        const gradientActual = ctxLine.createLinearGradient(0, 0, 0, 400);
        gradientActual.addColorStop(0, 'rgba(54, 162, 235, 0.5)');
        gradientActual.addColorStop(1, 'rgba(54, 162, 235, 0)');

        const gradientPredicted = ctxLine.createLinearGradient(0, 0, 0, 400);
        gradientPredicted.addColorStop(0, 'rgba(255, 99, 132, 0.5)');
        gradientPredicted.addColorStop(1, 'rgba(255, 99, 132, 0)');

        const salesLineChart = new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [
                    {
                        label: 'Actual Sales (₱)',
                        data: actualSalesData,
                        borderColor: 'rgba(54, 162, 235, 1)', // Blue
                        backgroundColor: gradientActual, // Gradient fill
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)', // Blue dots
                        pointBorderColor: '#fff', // White border for points
                        pointRadius: 5, // Size of data points
                        pointHoverRadius: 8, // Hover size of data points
                        tension: 0.4, // Smooth curve
                        fill: true, // Fill area under the curve
                        spanGaps: true, // Connect lines over missing data
                        plugins: {
                            shadowPlugin: {
                                shadowColor: 'rgba(0, 0, 0, 0.2)',
                                shadowBlur: 10,
                                shadowOffsetX: 2,
                                shadowOffsetY: 2
                            }
                        }
                    },
                    {
                        label: 'Predicted Sales (₱)',
                        data: predictedSalesData,
                        borderColor: 'rgba(255, 99, 132, 1)', // Red
                        backgroundColor: gradientPredicted, // Gradient fill
                        pointBackgroundColor: 'rgba(255, 99, 132, 1)', // Red dots
                        pointBorderColor: '#fff', // White border for points
                        pointRadius: 5, // Size of data points
                        pointHoverRadius: 8, // Hover size of data points
                        tension: 0.4, // Smooth curve
                        fill: true, // Fill area under the curve
                        spanGaps: true, // Connect lines over missing data
                        plugins: {
                            shadowPlugin: {
                                shadowColor: 'rgba(0, 0, 0, 0.2)',
                                shadowBlur: 10,
                                shadowOffsetX: 2,
                                shadowOffsetY: 2
                            }
                        }
                    }
                ]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₱ ' + context.parsed.y.toLocaleString();
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        padding: 10,
                        cornerRadius: 5
                    },
                    legend: { 
                        display: true, 
                        position: 'top', 
                        labels: { 
                            font: { size: 14 },
                            color: '#333'
                        } 
                    },
                    shadowPlugin: { // Ensure the shadow plugin is applied
                        shadowColor: 'rgba(0, 0, 0, 0.2)',
                        shadowBlur: 10,
                        shadowOffsetX: 2,
                        shadowOffsetY: 2
                    }
                },
                scales: {
                    x: {
                        type: 'category',
                        title: { display: true, text: 'Month', font: { size: 14 } },
                        ticks: {
                            callback: function(value, index) {
                                return moment(salesLabels[index], 'YYYY-MM').format('MMM YYYY');
                            },
                            font: { size: 12 },
                            color: '#666'
                        }
                    },
                    y: {
                        title: { display: true, text: 'Sales (₱)', font: { size: 14 } },
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱ ' + value.toLocaleString();
                            },
                            font: { size: 12 },
                            color: '#666'
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
            }
        });

        // ==========================
        // Display Predicted Sales using JavaScript
        // ==========================
        document.addEventListener('DOMContentLoaded', function() {
            if (predictionError) {
                // Display error message
                document.getElementById('predictedSales').textContent = 'Error: ' + predictionError;
                document.getElementById('predictedMonth').textContent = '...';
            } else if (predictedSales !== null && predictedMonth !== null) {
                // Update the predicted sales and month with peso symbol
                document.getElementById('predictedSales').textContent = '₱ ' + Number(predictedSales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('predictedMonth').textContent = predictedMonth;
            } else {
                // Default values
                document.getElementById('predictedSales').textContent = 'N/A';
                document.getElementById('predictedMonth').textContent = '...';
            }
        });

        // Handle Notifications Modal with Animations
        document.addEventListener('DOMContentLoaded', function() {
            // Notification Modal Elements
            const notificationsIcon = document.getElementById('notificationsIcon');
            const notificationsModal = document.getElementById('notificationsModal');
            const closeModalBtn = document.getElementById('closeModal');

            // Function to Open Modal with Animation
            function openModal() {
                notificationsModal.style.display = 'flex'; // Use 'flex' to align items centrally
                // Allow the browser to render the display change before adding the class
                setTimeout(() => {
                    notificationsModal.classList.add('show');
                }, 10); // 10ms delay to ensure the transition
            }

            // Function to Close Modal with Animation
            function closeModal() {
                notificationsModal.classList.remove('show');
                // Wait for the animation to finish before hiding
                setTimeout(() => {
                    notificationsModal.style.display = 'none';
                }, 500); // Duration should match the CSS transition (0.5s)
            }

            // Event Listener to Open Modal
            notificationsIcon.addEventListener('click', openModal);

            // Event Listener to Close Modal via Close Button
            closeModalBtn.addEventListener('click', closeModal);

            // Event Listener to Close Modal by Clicking Outside the Modal Content
            window.addEventListener('click', function(event) {
                if (event.target === notificationsModal) {
                    closeModal();
                }
            });

            // Update Notification Badge if Necessary
            if (totalNotifications > 0) {
                document.getElementById('notificationBadge').textContent = totalNotifications;
            }
        });

        // Handle Filter Panel Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const filterToggle = document.getElementById('filterToggle');
            const filterPanel = document.getElementById('filterPanel');

            filterToggle.addEventListener('click', function() {
                filterPanel.classList.toggle('active');
            });
        });
    </script>


    <!-- Optional: Add script for active sidebar link -->
    <script>
      $(document).ready(function() {
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

</body>
</html>
