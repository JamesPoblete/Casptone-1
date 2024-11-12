<?php
require_once 'dbconnection.php'; // Ensure this points to the right file

// Connect to the database
$conn = connectDB();

// Get the current date for defaults
$currentYear = date('Y');
$currentMonth = date('m');
$currentDay = date('d');

// Handling Date Selection in a Cleaner Way
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedYear = $_POST['year'];
    $selectedMonth = $_POST['month'] ?? null; // Default to null if not provided
    $selectedDay = $_POST['day'] ?? null; // Default to null if not provided
} else {
    $selectedYear = $currentYear;
    $selectedMonth = null;
    $selectedDay = null;
}

// Modify the Monthly Sales Query for Accuracy
$monthlySalesCondition = "WHERE YEAR(DATE) = :selectedYear";
$params = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $monthlySalesCondition .= " AND MONTH(DATE) = :selectedMonth";
    $params['selectedMonth'] = $selectedMonth;
}

// Important: Do not include the day filter for monthly sales calculation
// because we want the total for the entire month, not a specific day

$stmt_monthly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry $monthlySalesCondition");
$stmt_monthly_sales->execute(array_filter($params));
$monthly_sales = $stmt_monthly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

// Check if a specific day is selected
if ($selectedDay && $selectedMonth && $selectedYear) {
    // Use the selected date for "Today's Sales"
    $selectedDate = "$selectedYear-$selectedMonth-$selectedDay";
} else {
    // If no specific day is selected, default to today's date
    $selectedDate = date('Y-m-d');
}

// Query to compute today's sales (or the sales for the selected day)
$stmt_today_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE DATE = :selectedDate");
$stmt_today_sales->execute(['selectedDate' => $selectedDate]);
$today_sales = $stmt_today_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

// Query to compute yearly sales
$stmt_yearly_sales = $conn->prepare("SELECT SUM(TOTAL) as total_sales FROM laundry WHERE YEAR(DATE) = :selectedYear");
$stmt_yearly_sales->execute(['selectedYear' => $selectedYear]);
$yearly_sales = $stmt_yearly_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?: 0;

// Query to compute sales by month for the selected year
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

// Query for top used detergents
$detergentCondition = "WHERE YEAR(DATE) = :selectedYear";
if ($selectedMonth) {
    $detergentCondition .= " AND MONTH(DATE) = :selectedMonth";
}
if ($selectedDay) {
    $detergentCondition .= " AND DAY(DATE) = :selectedDay";
}
$stmt_top_detergents = $conn->prepare("SELECT DETERGENT, COUNT(*) as count FROM laundry $detergentCondition GROUP BY DETERGENT ORDER BY count DESC LIMIT 5");
$paramsDetergent = ['selectedYear' => $selectedYear, 'selectedMonth' => $selectedMonth, 'selectedDay' => $selectedDay];
$stmt_top_detergents->execute(array_filter($paramsDetergent));
$top_detergents = $stmt_top_detergents->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for the pie chart
$detergent_names = [];
$detergent_counts = [];
foreach ($top_detergents as $detergent) {
    $detergent_names[] = $detergent['DETERGENT'];
    $detergent_counts[] = $detergent['count'];
}

// Query for laundry data based on the selected date
$laundryCondition = "WHERE YEAR(DATE) = :selectedYear";
if ($selectedMonth) {
    $laundryCondition .= " AND MONTH(DATE) = :selectedMonth";
}
if ($selectedDay) {
    $laundryCondition .= " AND DAY(DATE) = :selectedDay";
}
$stmt_laundry_list = $conn->prepare("SELECT * FROM laundry $laundryCondition ORDER BY DATE DESC");
$paramsLaundry = ['selectedYear' => $selectedYear, 'selectedMonth' => $selectedMonth, 'selectedDay' => $selectedDay];
$stmt_laundry_list->execute(array_filter($paramsLaundry));
$laundry_list = $stmt_laundry_list->fetchAll(PDO::FETCH_ASSOC);

// Query for top used fabric detergents
$fabricDetergentCondition = "WHERE YEAR(DATE) = :selectedYear";
if ($selectedMonth) {
    $fabricDetergentCondition .= " AND MONTH(DATE) = :selectedMonth";
}
if ($selectedDay) {
    $fabricDetergentCondition .= " AND DAY(DATE) = :selectedDay";
}
$stmt_top_fabric_detergents = $conn->prepare("SELECT FABRIC_DETERGENT, COUNT(*) as count FROM laundry $fabricDetergentCondition GROUP BY FABRIC_DETERGENT ORDER BY count DESC LIMIT 5");
$paramsFabricDetergent = ['selectedYear' => $selectedYear, 'selectedMonth' => $selectedMonth, 'selectedDay' => $selectedDay];
$stmt_top_fabric_detergents->execute(array_filter($paramsFabricDetergent));
$top_fabric_detergents = $stmt_top_fabric_detergents->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for the fabric detergent pie chart
$fabric_detergent_names = [];
$fabric_detergent_counts = [];
foreach ($top_fabric_detergents as $detergent) {
    $fabric_detergent_names[] = $detergent['FABRIC_DETERGENT'];
    $fabric_detergent_counts[] = $detergent['count'];

}

// Start with the basic condition for the selected year
$customerCondition = "WHERE YEAR(DATE) = :selectedYear";
$paramsCustomer = ['selectedYear' => $selectedYear];

// Add conditions for month and day if they are selected
if ($selectedMonth) {
    $customerCondition .= " AND MONTH(DATE) = :selectedMonth";
    $paramsCustomer['selectedMonth'] = $selectedMonth;
}


// Query to count total number of customers and distinct days based on the selection
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

// Average Sales Start by building the condition for calculating based on the selected year, month, and day
$salesCondition = "WHERE YEAR(DATE) = :selectedYear";
$paramsSales = ['selectedYear' => $selectedYear];

if ($selectedMonth) {
    $salesCondition .= " AND MONTH(DATE) = :selectedMonth";
    $paramsSales['selectedMonth'] = $selectedMonth;
}


// Query to calculate total sales and distinct days
$stmt_total_sales = $conn->prepare("
    SELECT SUM(TOTAL) as total_sales, COUNT(DISTINCT DATE) as total_days
    FROM laundry
    $salesCondition
");
$stmt_total_sales->execute(array_filter($paramsSales));
$total_sales_data = $stmt_total_sales->fetch(PDO::FETCH_ASSOC);

// Get total sales and number of distinct days
$total_sales = $total_sales_data['total_sales'] ?: 0;  // If no sales, set to 0
$total_days = $total_sales_data['total_days'] ?: 1;  // If no distinct days, set to 1 to avoid division by zero

// Calculate the average sales per day
$average_sales_per_day = $total_sales / $total_days;



// Fetch inventory data from the database
$stmt_inventory = $conn->prepare("SELECT ProductName, CurrentStock FROM inventory");
$stmt_inventory->execute();
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AN'E Laundry Dashboard</title>
    <link rel="stylesheet" href="../css/maindashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo">
        <ul>
            <li><a href="maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-box"></i> Inventory</a></li>
            <li><a href="../html/laundrylist.html"><i class="fas fa-list-alt"></i> Laundries List</a></li>
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
                    <option value="<?= $i ?>" <?= $i == $selectedYear ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>

                <label for="month">Month:</label>
                <select name="month" id="month">
                    <option value="">All</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $i == $selectedMonth ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                    <?php endfor; ?>
                </select>

                <label for="day">Day:</label>
                <select name="day" id="day">
                    <option value="">All</option>
                    <?php for ($i = 1; $i <= 31; $i++): ?>
                    <option value="<?= $i ?>" <?= $i == $selectedDay ? 'selected' : '' ?>><?= $i ?></option>
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

        <!-- Bootstrap Carousel for pages -->
        <div id="dashboardCarousel" class="carousel slide" data-ride="carousel">

            <!-- Carousel items -->
            <div class="carousel-inner">

                <!-- First Carousel Item (Existing Dashboard) -->
                <div class="carousel-item active">
                    <div class="cards">
                        <div class="card">
                            <h3><i class="fas fa-money-bill-wave"></i> Today's Sales</h3>
                            <p>₱ <?= number_format($today_sales, 2) ?></p>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-calendar-alt"></i> Monthly Sales</h3>
                            <p>₱ <?= number_format($monthly_sales, 2) ?></p>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-calendar-check"></i> Yearly Sales</h3>
                            <p>₱ <?= number_format($yearly_sales, 2) ?></p>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-user-friends"></i> Average Customers</h3>
                            <p><?= number_format($average_customers_per_day, 2) ?></p> <!-- New logic for average customers -->
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-chart-line"></i> Average Sales</h3>
                            <p>₱ <?= number_format($average_sales_per_day, 2) ?></p>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-chart-line"></i> Inventory Expenses</h3>
                            
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-chart-line"></i> Average Sales</h3>
                            
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-chart-line"></i> Average Sales</h3>
                            
                        </div>
                    </div>

                    <!-- Charts Section (First Page) -->
                    <div class="charts-container">
                        <div class="bar-chart-container">
                            <h3>Sales Overview</h3>
                            <canvas id="barChart"></canvas>
                        </div>
                        
                    </div>
                </div>

                <!-- Second Carousel Item (Additional Graphs) -->
                <div class="carousel-item">

                    <!-- New Charts for Inventory & Laundry Analytics -->
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

            <!-- Carousel Controls -->
            <a class="carousel-control-prev" href="#dashboardCarousel" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#dashboardCarousel" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
            </a>

        </div>

    </div>

    <!-- Bootstrap and jQuery JS for Carousel Functionality -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    


    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart Data Preparation
        const detergentNames = <?= json_encode($detergent_names) ?>;
        const detergentCounts = <?= json_encode($detergent_counts) ?>;
        const fabricDetergentNames = <?= json_encode($fabric_detergent_names) ?>;
        const fabricDetergentCounts = <?= json_encode($fabric_detergent_counts) ?>;

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
                position: 'right', // Move the legend to the right
                labels: {
                    color: '#1D2B53' // Dark blue color for labels to match the theme
                }
            },
            title: {
                display: false // No title for simplicity
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
                position: 'right', // Move the legend to the right
                labels: {
                    color: '#1D2B53' // Dark blue color for labels to match the theme
                }
            },
            title: {
                display: false // No title for simplicity
            }
        }
    }
});

  // Inventory Stock Levels Chart
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
        }
    }
});


    </script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> <!-- jQuery library -->

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