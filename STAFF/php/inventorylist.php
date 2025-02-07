<?php
//inventorylist.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

include 'dbconnection.php';

// Get the PDO connection
$pdo = connectDB();

// Set the desired timezone
date_default_timezone_set('Asia/Manila'); // Change to your timezone if different

// Set MySQL session timezone to match PHP timezone
$pdo->exec("SET time_zone = '".date('P')."'");

// Pagination setup (Optional)
$itemsPerPage = 10; // Number of items per page
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch all inventory items for the logged-in user with pagination
$sql = "SELECT InventoryID, ProductName, ProductType, CurrentStock, PricePerStock, TotalExpense 
        FROM inventory 
        WHERE userID = :userID 
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':userID', $_SESSION['userID'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

// Fetch all results
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of items for pagination
$countSql = "SELECT COUNT(*) FROM inventory WHERE userID = :userID";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute([':userID' => $_SESSION['userID']]);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Fetch aggregated expenses per month and year from inventory_expenses for the current year
$currentYear = date('Y');
$expenseSql = "SELECT 
                MONTH(ExpenseDate) AS month, 
                SUM(Amount) AS monthly_expense 
              FROM inventory_expenses 
              WHERE InventoryID IN (SELECT InventoryID FROM inventory WHERE userID = :userID)
                AND YEAR(ExpenseDate) = :currentYear
              GROUP BY MONTH(ExpenseDate)
              ORDER BY MONTH(ExpenseDate)";
$expenseStmt = $pdo->prepare($expenseSql);
$expenseStmt->execute([
    ':userID' => $_SESSION['userID'],
    ':currentYear' => $currentYear
]);
$monthlyExpenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get month name
function getMonthName($monthNumber) {
    return DateTime::createFromFormat('!m', $monthNumber)->format('F');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../css/inventorylist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo"> 
        <ul>
            <li><a href="maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="inventorylist.php" class="active"><i class="fas fa-box"></i> Inventory</a></li>
            <li><a href="../html/laundrylist.html"><i class="fas fa-list-alt"></i> Laundries List</a></li>
            <li><a href="../html/login.html" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
        </ul>
    </div>

    <!-- Main content area -->
    <div class="main-content">
        <header class="main-header">
            <div class="header-left">
                <h1 class="logo"></h1>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <i class="fa fa-user-circle"></i>
                    <span>Staff</span>
                </div>
            </div>
        </header>

        <!-- Inventory List Header Section -->
        <div class="inventory-list-header">
            <h2>Inventory</h2>
            <button class="print-btn" id="printBtn" aria-label="Print Inventory Report"><i class="fas fa-print" aria-hidden="true">&nbsp&nbsp</i> Print Report</button>
        </div>

        <!-- Search Bar -->
        <div class="search-filter-container">
            <input type="text" id="search" name="search" placeholder="Search by Product ID, Name, or Type" autocomplete="off">
            <select class="status-filter" id="statusFilter" name="status">
                <option value="all">All</option> 
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="out-of-stock">Out of Stock</option> 
            </select>
        </div>

        <!-- Inventory List Table -->
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Type</th>
                    <th>Current Stock</th>
                    <th>Price Per Stock (₱)</th>
                    <th>Total Expense (₱)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <?php
                if (count($inventoryItems) > 0) {
                    foreach ($inventoryItems as $row) {
                        // Determine the status based on CurrentStock
                        if ($row['CurrentStock'] == 0) {
                            $status = 'out-of-stock';
                            $statusText = 'Out of Stock';
                        } elseif ($row['CurrentStock'] >= 10 && $row['CurrentStock'] <= 15) {
                            $status = 'high';
                            $statusText = 'High';
                        } elseif ($row['CurrentStock'] >= 6 && $row['CurrentStock'] <= 9) {
                            $status = 'medium';
                            $statusText = 'Medium';
                        } elseif ($row['CurrentStock'] >= 1 && $row['CurrentStock'] <= 5) {
                            $status = 'low';
                            $statusText = 'Low';
                        } else {
                            $status = 'unknown';
                            $statusText = 'Unknown';
                        }

                        // Calculate expense for the current item
                        // If TotalExpense is already in the database, use it
                        $totalExpense = $row['TotalExpense'];
                        // Removed accumulation of $totalExpenses since Total Inventory Expenses is removed

                        echo "<tr>";
                        echo "<td>#".htmlspecialchars($row['InventoryID'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".htmlspecialchars($row['ProductName'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".htmlspecialchars($row['ProductType'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".htmlspecialchars($row['CurrentStock'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".number_format($row['PricePerStock'], 2)."</td>";
                        echo "<td>".number_format($totalExpense, 2)."</td>";
                        echo "<td><span class='status ".$status."'>".$statusText."</span></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Pagination (Optional) -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?>">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo ($i === $currentPage) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Report Section (Hidden on Screen, Visible on Print) -->
        <div id="printReport" class="print-report">
            <h1>AN'E Laundry Inventory Report</h1>
            <h2><?php echo date('F Y'); ?></h2>
            
            <!-- Current Inventory Stock -->
            <h3>Current Inventory Stock</h3>
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>Current Stock</th>
                        <th>Price Per Stock (₱)</th>
                        <th>Total Expense (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($inventoryItems) > 0) {
                        foreach ($inventoryItems as $item) {
                            echo "<tr>";
                            echo "<td>#".htmlspecialchars($item['InventoryID'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($item['ProductName'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($item['ProductType'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($item['CurrentStock'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".number_format($item['PricePerStock'], 2)."</td>";
                            echo "<td>".number_format($item['TotalExpense'], 2)."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No inventory records found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Stocks Added Today -->
            <h3>Stocks Added Today</h3>
            <table class="stocks-today-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Quantity Added</th>
                        <th>Price Per Stock (₱)</th>
                        <th>Total Expense (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch stocks added today
                    $today = date('Y-m-d');
                    $stocksTodaySql = "SELECT 
                                            inventory_expenses.InventoryID, 
                                            inventory.ProductName, 
                                            inventory_expenses.Amount AS TotalExpense, 
                                            inventory.PricePerStock,
                                            inventory_expenses.Description
                                       FROM inventory_expenses 
                                       JOIN inventory ON inventory_expenses.InventoryID = inventory.InventoryID
                                       WHERE inventory.userID = :userID 
                                         AND DATE(inventory_expenses.ExpenseDate) = :today
                                         AND inventory_expenses.Description LIKE 'Added stock:%'";
                    $stocksTodayStmt = $pdo->prepare($stocksTodaySql);
                    $stocksTodayStmt->execute([
                        ':userID' => $_SESSION['userID'],
                        ':today' => $today
                    ]);
                    $stocksToday = $stocksTodayStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($stocksToday) > 0) {
                        foreach ($stocksToday as $stock) {
                            // Extract quantity from the Description
                            // Assuming Description is "Added stock: X units."
                            $description = $stock['Description']; // Fetch the Description
                            $quantityAdded = 0;
                            if (preg_match('/Added stock:\s*(\d+)\s*units\./i', $description, $matches)) {
                                $quantityAdded = (int)$matches[1];
                            }

                            echo "<tr>";
                            echo "<td>#".htmlspecialchars($stock['InventoryID'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($stock['ProductName'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($quantityAdded, ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".number_format($stock['PricePerStock'], 2)."</td>";
                            echo "<td>".number_format($stock['TotalExpense'], 2)."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No stocks added today.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Expenses Summary for Current Year -->
            <h3>Expenses Summary for <?php echo $currentYear; ?></h3>
            <table class="expenses-summary-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Expense (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($monthlyExpenses) > 0) {
                        foreach ($monthlyExpenses as $expense) {
                            echo "<tr>";
                            echo "<td>".htmlspecialchars(getMonthName($expense['month']), ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".number_format($expense['monthly_expense'], 2)."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2'>No expense records found for the current year.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- JavaScript -->
        <script>
        // JavaScript for Client-Side Search with Debouncing
        const searchInput = document.getElementById('search');
        const statusFilter = document.getElementById('statusFilter');
        const table = document.querySelector('table tbody');

        let debounceTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(filterTable, 300);
        });
        statusFilter.addEventListener('change', filterTable);

        function filterTable() {
            const searchValue = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 7) return; // Skip if not enough cells (e.g., no records)

                const productID = cells[0].textContent.toLowerCase().replace('#', '');
                const productName = cells[1].textContent.toLowerCase();
                const productType = cells[2].textContent.toLowerCase();
                const currentStatus = cells[6].textContent.toLowerCase();

                const matchesSearch = 
                    productID.includes(searchValue) || 
                    productName.includes(searchValue) || 
                    productType.includes(searchValue);

                const matchesStatus = statusValue === 'all' || currentStatus.includes(statusValue);

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // JavaScript to handle the success and error messages (if any)
        // You can add similar code here if you have success/error messages

        // Print Report Functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });

        // Sidebar Active State without jQuery
        document.addEventListener('DOMContentLoaded', function() {
            var path = window.location.pathname.split("/").pop() || "index.html";
            var links = document.querySelectorAll('.sidebar ul li a');
            links.forEach(function(link) {
                if (link.getAttribute('href') === path) {
                    link.classList.add('active');
                }
            });
        });
        </script>
    </div>

    <!-- Optional: Include jQuery if needed for other functionalities -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> -->

</body>
</html>
