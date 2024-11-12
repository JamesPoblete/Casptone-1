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
// Fetch Sales Data
// ==========================

// Monthly Sales
$monthlySalesCondition = "WHERE YEAR(DATE) = :selectedYear";
$params = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $monthlySalesCondition .= " AND MONTH(DATE) = :selectedMonth";
    $params['selectedMonth'] = $selectedMonth;
}

$stmt_monthly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry $monthlySalesCondition");
$stmt_monthly_sales->execute(array_filter($params));
$monthly_sales = $stmt_monthly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

// Today's Sales
if ($selectedDay && $selectedMonth && $selectedYear) {
    // Use the selected date for "Today's Sales"
    $selectedDate = "$selectedYear-$selectedMonth-$selectedDay";
} else {
    // If no specific day is selected, default to today's date
    $selectedDate = date('Y-m-d');
}

$stmt_today_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE DATE = :selectedDate");
$stmt_today_sales->execute(['selectedDate' => $selectedDate]);
$today_sales = $stmt_today_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

// Yearly Sales
$stmt_yearly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE YEAR(DATE) = :selectedYear");
$stmt_yearly_sales->execute(['selectedYear' => $selectedYear]);
$yearly_sales = $stmt_yearly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

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
            <li><a href="../php/manageuser.php"><i class="fas fa-users-cog"></i> Manage User</a></li>
            <li><a href="../html/login.html" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header class="main-header">
            <!-- Date Selection Form -->
            <form method="POST" action="" class="date-filter-form">
                <label for="year">Year:</label>
                <select name="year" id="year">
                    <?php for ($i = 2023; $i <= 2025; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $i == $selectedYear ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>

                <label for="month">Month:</label>
                <select name="month" id="month">
                    <option value="">All</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $i == $selectedMonth ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $i, 1)); ?></option>
                    <?php endfor; ?>
                </select>

                <label for="day">Day:</label>
                <select name="day" id="day">
                    <option value="">All</option>
                    <?php for ($i = 1; $i <= 31; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $i == $selectedDay ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>

                <button type="submit">Filter</button>
                <button type="reset" onclick="window.location.href='';">Reset</button>
            </form>
            <div class="header-left">
                <h1 class="logo"></h1>
            </div>

            <div class="header-right">
                <i class="fa fa-bell"></i>
                <div class="user-profile">
                    <i class="fa fa-user-circle"></i>
                    <span>Admin</span>
                    <i class="fa fa-caret-down"></i>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="cards">
                <div class="card">
                    <h3><i class="fas fa-money-bill-wave"></i> Today's Sales</h3>
                    <p>₱ <?php echo number_format($today_sales, 2); ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-calendar-alt"></i> Monthly Sales</h3>
                    <p>₱ <?php echo number_format($monthly_sales, 2); ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-calendar-check"></i> Yearly Sales</h3>
                    <p>₱ <?php echo number_format($yearly_sales, 2); ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-user-friends"></i> Average Customers</h3>
                    <p><?php echo number_format($average_customers_per_day, 2); ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-chart-line"></i> Average Sales</h3>
                    <p>₱ <?php echo number_format($average_sales_per_day, 2); ?></p>
                </div>
                <!-- Inventory Expenses Card -->
                <div class="card">
                    <h3><i class="fas fa-chart-line"></i> Inventory Expenses</h3>
                    <p>₱ <span id="totalExpenses"><?php echo number_format($total_expenses, 2); ?></span></p>
                </div>
                <!-- Predicted Sales Card -->
    <!-- Predicted Sales Card -->
    <div class="card">
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

    <!-- jQuery and Bootstrap JS (if needed) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <!-- Include Moment.js and the date adapter for Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.1.0"></script>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1"></script>

    <script>
        // Chart Data Preparation
        const detergentNames = <?php echo json_encode($detergent_names); ?>;
        const detergentCounts = <?php echo json_encode($detergent_counts); ?>;
        const fabricDetergentNames = <?php echo json_encode($fabric_detergent_names); ?>;
        const fabricDetergentCounts = <?php echo json_encode($fabric_detergent_counts); ?>;

        // Bar Chart for Monthly Sales
        const ctxBar = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Sales (₱)',
                    data: salesByMonth, // Use the PHP data passed above
                    backgroundColor: 'rgba(99, 132, 255, 0.8)',  // A single color for all bars (light blue)
                    hoverBackgroundColor: 'rgba(99, 132, 255, 1)',  // Darker on hover
                    borderRadius: 10, // Rounded corners for bars
                    borderSkipped: false, // Removes border at the top of each bar
                    barThickness: 40 // Controls the thickness of the bars
                }]
            },
            options: {
                scales: {
                    x: {
                        grid: {
                            display: false // Remove grid lines for the X-axis
                        },
                        ticks: {
                            font: {
                                size: 14,  // Adjusts the font size for the month labels
                                weight: 'bold'
                            },
                            color: '#666' // Set label color
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(200, 200, 200, 0.5)', // Light grid lines on Y-axis
                            borderDash: [5, 5]  // Dashed grid lines
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱ ' + value; // Format ticks to show currency
                            },
                            font: {
                                size: 12, // Adjust the font size for the Y-axis values
                            },
                            color: '#666' // Set label color
                        },
                        beginAtZero: true // Ensure Y-axis starts at zero
                    }
                },
                responsive: true,
                maintainAspectRatio: false, // Makes the chart responsive
                plugins: {
                    legend: {
                        display: false // Hides the legend as in the example
                    },
                    tooltip: {
                        backgroundColor: 'rgba(99, 132, 255, 0.8)', // Tooltip background matches the bar color
                        titleFont: { size: 16, weight: 'bold' },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.raw.toLocaleString(); // Format tooltip with currency
                            }
                        }
                    }
                }
            }
        });

        // Bar Chart for Inventory Expenses
        const ctxExpenses = document.getElementById('expensesChart').getContext('2d');
        const expensesChart = new Chart(ctxExpenses, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Inventory Expenses (₱)',
                    data: expensesByMonthData, // Use the PHP data passed above
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',  // Light red
                    hoverBackgroundColor: 'rgba(255, 99, 132, 1)',  // Darker red on hover
                    borderRadius: 10,
                    borderSkipped: false,
                    barThickness: 40
                }]
            },
            options: {
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#666'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(200, 200, 200, 0.5)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱ ' + value;
                            },
                            font: {
                                size: 12,
                            },
                            color: '#666'
                        },
                        beginAtZero: true
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        titleFont: { size: 16, weight: 'bold' },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Pie Chart for Top Used Detergents
        const ctxPie = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: detergentNames,
                datasets: [{
                    data: detergentCounts,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',   // Blue
                        'rgba(75, 192, 192, 0.7)',   // Teal
                        'rgba(153, 102, 255, 0.7)',  // Light Purple
                        'rgba(255, 205, 86, 0.7)',   // Light Yellow
                        'rgba(255, 99, 132, 0.7)'    // Soft Red
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',     // Blue Border
                        'rgba(75, 192, 192, 1)',     // Teal Border
                        'rgba(153, 102, 255, 1)',    // Light Purple Border
                        'rgba(255, 205, 86, 1)',     // Light Yellow Border
                        'rgba(255, 99, 132, 1)'      // Soft Red Border
                    ],
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
                            color: '#1D2B53'
                        }
                    },
                    title: {
                        display: false
                    }
                }
            }
        });

        // Pie Chart for Top Used Fabric Detergents
        const ctxFabricPie = document.getElementById('fabricPieChart').getContext('2d');
        const fabricPieChart = new Chart(ctxFabricPie, {
            type: 'pie',
            data: {
                labels: fabricDetergentNames,
                datasets: [{
                    data: fabricDetergentCounts,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',   // Blue
                        'rgba(75, 192, 192, 0.7)',   // Teal
                        'rgba(153, 102, 255, 0.7)',  // Light Purple
                        'rgba(255, 205, 86, 0.7)',   // Light Yellow
                        'rgba(255, 159, 64, 0.7)'    // Light Orange
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',     // Blue Border
                        'rgba(75, 192, 192, 1)',     // Teal Border
                        'rgba(153, 102, 255, 1)',    // Light Purple Border
                        'rgba(255, 205, 86, 1)',     // Light Yellow Border
                        'rgba(255, 159, 64, 1)'      // Light Orange Border
                    ],
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
                            color: '#1D2B53'
                        }
                    },
                    title: {
                        display: false
                    }
                }
            }
        });

        // Bar Chart for Inventory Stock Levels
        const inventoryStockBarChart = document.getElementById('inventoryStockBarChart').getContext('2d');
        const barChart2 = new Chart(inventoryStockBarChart, {
            type: 'bar',
            data: {
                labels: inventoryNames, // Data from PHP
                datasets: [{
                    label: 'Stock Levels',
                    data: inventoryStockData, // Data from PHP
                    backgroundColor: 'rgba(255, 159, 64, 0.8)',
                    borderRadius: 10,
                    borderSkipped: false,
                    barThickness: 40
                }]
            },
            options: {
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

// Line Chart for Actual Sales vs Predicted Sales
const ctxLine = document.getElementById('salesLineChart').getContext('2d');
const salesLineChart = new Chart(ctxLine, {
    type: 'line',
    data: {
        labels: salesLabels,
        datasets: [
            {
                label: 'Actual Sales (₱)',
                data: actualSalesData,
                borderColor: 'rgba(54, 162, 235, 1)', // Blue
                backgroundColor: 'rgba(54, 162, 235, 0.2)', // Light Blue fill
                tension: 0.1,
                spanGaps: true, // Connect lines over missing data
            },
            {
                label: 'Predicted Sales (₱)',
                data: predictedSalesData,
                borderColor: 'rgba(255, 99, 132, 1)', // Red
                backgroundColor: 'rgba(255, 99, 132, 0.2)', // Light Red fill
                borderDash: [5, 5], // Dashed line for predicted sales
                tension: 0.1,
                spanGaps: true,
            }
        ]
    },
    options: {
        scales: {
            x: {
                type: 'category',
                title: { display: true, text: 'Month' },
                ticks: {
                    callback: function(value, index) {
                        return moment(salesLabels[index], 'YYYY-MM').format('MMM YYYY');
                    }
                }
            },
            y: {
                title: { display: true, text: 'Sales (₱)' },
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱ ' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₱ ' + context.parsed.y.toLocaleString();
                    }
                }
            },
            legend: { display: true, position: 'top' }
        },
        responsive: true,
        maintainAspectRatio: false,
    }
});

        // Display Predicted Sales using JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            if (predictionError) {
                // Display error message
                document.getElementById('predictedSales').textContent = 'Error: ' + predictionError;
                document.getElementById('predictedMonth').textContent = '...';
            } else if (predictedSales !== null && predictedMonth !== null) {
                // Update the predicted sales and month
                document.getElementById('predictedSales').textContent = '₱ ' + Number(predictedSales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('predictedMonth').textContent = predictedMonth;
            } else {
                // Default values
                document.getElementById('predictedSales').textContent = 'N/A';
                document.getElementById('predictedMonth').textContent = '...';
            }
        });
    </script>

    <!-- Optional: Add script for active sidebar link -->
    <script>
      $(document).ready(function() {
        // Get the current path from the URL
        var path = window.location.pathname.split("/").pop();

        // Set default path for home page
        if (path === "") {
          path = "index.html";
        }

        // Find the sidebar link that matches the current path
        var target = $('.sidebar ul li a[href="' + path + '"]');

        // Add active class to the matching link
        target.addClass("active");
      });
    </script>

</body>
</html>
