<?php
include 'dbconnection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB();

    try {
        // Begin Transaction for atomicity
        $conn->beginTransaction();

        // Retrieve form data
        $name = $_POST['NAME'];
        $date = $_POST['DATE'];
        $service = $_POST['SERVICE'];
        $load = $_POST['LOAD'];
        $total = $_POST['TOTAL'];
        $status = 'Pending'; // Default status

        // Retrieve article data
        $clothesWeightKg = !empty($_POST['CLOTHES_WEIGHT_KG']) ? $_POST['CLOTHES_WEIGHT_KG'] : null;
        $comforterSingle = !empty($_POST['COMFORTER_SINGLE']) ? $_POST['COMFORTER_SINGLE'] : 0;
        $comforterDouble = !empty($_POST['COMFORT_DOUBLE']) ? $_POST['COMFORT_DOUBLE'] : 0;
        $bedsheetsCurtainsTowelBlankets = !empty($_POST['BEDSHEETS_CURTAINS_TOWEL_BLANKETS']) ? $_POST['BEDSHEETS_CURTAINS_TOWEL_BLANKETS'] : 0;
        $others = !empty($_POST['OTHERS']) ? $_POST['OTHERS'] : 0;

        // Retrieve detergent data
        $detergentType = isset($_POST['DETERGENT_TYPE']) ? $_POST['DETERGENT_TYPE'] : null;
        $detergentAdditional = isset($_POST['DETERGENT_ADDITIONAL']) && is_numeric($_POST['DETERGENT_ADDITIONAL']) ? $_POST['DETERGENT_ADDITIONAL'] : 0;

        // Retrieve fabric detergent data
        $fabricDetergentType = isset($_POST['FABRIC_DETERGENT_TYPE']) ? $_POST['FABRIC_DETERGENT_TYPE'] : null;
        $fabricDetergentAdditional = isset($_POST['FABRIC_DETERGENT_ADDITIONAL']) && is_numeric($_POST['FABRIC_DETERGENT_ADDITIONAL']) ? $_POST['FABRIC_DETERGENT_ADDITIONAL'] : 0;

        // Calculate ADDITIONAL_COST
        $additionalCost = ($detergentAdditional + $fabricDetergentAdditional) * 15;

        // Retrieve pickup time
        $pickupTime = isset($_POST['PICKUP_TIME']) ? $_POST['PICKUP_TIME'] . ":00" : null;

        // Prepare SQL statement with escaped column names
        $sql = "INSERT INTO laundry (
                    `NAME`, 
                    `DATE`, 
                    `SERVICE`, 
                    `LAUNDRY_LOAD`, 
                    `TOTAL`, 
                    `STATUS`, 
                    `CLOTHES_WEIGHT_KG`, 
                    `COMFORTER_SINGLE`, 
                    `COMFORT_DOUBLE`, 
                    `BEDSHEETS_CURTAINS_TOWEL_BLANKETS`, 
                    `REMARKS`, 
                    `DETERGENT`, 
                    `DETERGENT_ADDITIONAL`, 
                    `FABRIC_DETERGENT`, 
                    `FABRIC_DETERGENT_ADDITIONAL`, 
                    `ADDITIONAL_COST`,
                    `PICKUP_TIME`
                ) 
                VALUES (
                    :name, 
                    :date, 
                    :service, 
                    :load, 
                    :total, 
                    :status, 
                    :clothesWeightKg, 
                    :comforterSingle, 
                    :comforterDouble, 
                    :bedsheetsCurtainsTowelBlankets, 
                    :remarks, 
                    :detergent, 
                    :detergentAdditional, 
                    :fabricDetergent, 
                    :fabricDetergentAdditional, 
                    :additionalCost, 
                    :pickupTime
                )";
        $stmt = $conn->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':service', $service);
        $stmt->bindParam(':load', $load, PDO::PARAM_INT);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':clothesWeightKg', $clothesWeightKg);
        $stmt->bindParam(':comforterSingle', $comforterSingle, PDO::PARAM_INT);
        $stmt->bindParam(':comforterDouble', $comforterDouble, PDO::PARAM_INT);
        $stmt->bindParam(':bedsheetsCurtainsTowelBlankets', $bedsheetsCurtainsTowelBlankets, PDO::PARAM_INT);
        $stmt->bindParam(':remarks', $others);
        $stmt->bindParam(':detergent', $detergentType);
        $stmt->bindParam(':detergentAdditional', $detergentAdditional, PDO::PARAM_INT);
        $stmt->bindParam(':fabricDetergent', $fabricDetergentType);
        $stmt->bindParam(':fabricDetergentAdditional', $fabricDetergentAdditional, PDO::PARAM_INT);
        $stmt->bindParam(':additionalCost', $additionalCost, PDO::PARAM_INT);
        $stmt->bindParam(':pickupTime', $pickupTime, PDO::PARAM_STR);

        // Execute the statement
        if ($stmt->execute()) {
            echo "Success";
        } else {
            // Retrieve error information
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to insert data. Error: " . $errorInfo[2]);
        }

        // **Updated TransactionCount Calculation**

        // **Step 1:** Calculate `articlesCount` excluding clothes
        $articlesCount = 0;
        if ($comforterSingle > 0) {
            $articlesCount += $comforterSingle;
        }
        if ($comforterDouble > 0) {
            $articlesCount += $comforterDouble;
        }
        if ($bedsheetsCurtainsTowelBlankets > 0) {
            $articlesCount += $bedsheetsCurtainsTowelBlankets;
        }

        // **Step 2:** Calculate total `transactionCount`
        // Formula: transactionCount = load + articlesCount + detergentAdditional + fabricDetergentAdditional
        $transactionCount = $load + $articlesCount + $detergentAdditional + $fabricDetergentAdditional;

        // **Step 3:** List of detergents used with the calculated `transactionCount`
        $detergentsUsed = [];
        if ($detergentType) {
            $detergentsUsed[] = ['type' => $detergentType, 'transactions' => $transactionCount];
        }
        if ($fabricDetergentType) {
            $detergentsUsed[] = ['type' => $fabricDetergentType, 'transactions' => $transactionCount];
        }

        // **Step 4:** Initialize an array to hold stock reductions (for logging or further processing)
        $stockReductions = [];

        foreach ($detergentsUsed as $detergentData) {
            $detergent = $detergentData['type'];
            $detergentTransactions = $detergentData['transactions'];

            if ($detergentTransactions === 0) {
                continue; // Skip if no transactions for this detergent type
            }

            // Fetch current transaction count and inventory ID for the detergent
            $usageStmt = $conn->prepare("
                SELECT du.transactionCount, du.detergentID, i.inventoryID 
                FROM detergent_usage du
                JOIN inventory i ON du.inventoryID = i.inventoryID
                WHERE i.ProductName = :detergent_type
                FOR UPDATE
            ");
            $usageStmt->bindParam(':detergent_type', $detergent);
            $usageStmt->execute();
            $usageResult = $usageStmt->fetch(PDO::FETCH_ASSOC);

            if ($usageResult) {
                $currentCount = intval($usageResult['transactionCount']);
                $detergentUsageID = intval($usageResult['detergentID']);
                $inventoryID = intval($usageResult['inventoryID']);
            } else {
                // If detergent not found in usage table, initialize it
                // First, get the InventoryID
                $invStmt = $conn->prepare("
                    SELECT inventoryID 
                    FROM inventory 
                    WHERE ProductName = :detergent_type 
                    LIMIT 1
                ");
                $invStmt->bindParam(':detergent_type', $detergent);
                $invStmt->execute();
                $invResult = $invStmt->fetch(PDO::FETCH_ASSOC);
                if ($invResult) {
                    $inventoryID = intval($invResult['inventoryID']);
                    // Insert into detergent_usage with initial transactionCount=0
                    $initStmt = $conn->prepare("
                        INSERT INTO detergent_usage (inventoryID, transactionCount) 
                        VALUES (:inventoryID, 0)
                    ");
                    $initStmt->bindParam(':inventoryID', $inventoryID, PDO::PARAM_INT);
                    $initStmt->execute();
                    $detergentUsageID = intval($conn->lastInsertId());
                    $currentCount = 0;
                } else {
                    throw new Exception("Detergent type '{$detergent}' not found in inventory.");
                }
            }

            // Calculate new transaction count
            $newCount = $currentCount + $detergentTransactions;
            $reduceStock = 0;

            if ($newCount >= 15) {
                // Determine how many times to reduce stock
                $reduceStock = floor($newCount / 15);
                $remainingCount = $newCount % 15;

                // Update inventory stock
                $inventoryStmt = $conn->prepare("
                    UPDATE inventory 
                    SET CurrentStock = CurrentStock - :reduceStock 
                    WHERE inventoryID = :inventoryID 
                      AND CurrentStock >= :reduceStock
                ");
                $inventoryStmt->bindParam(':reduceStock', $reduceStock, PDO::PARAM_INT);
                $inventoryStmt->bindParam(':inventoryID', $inventoryID, PDO::PARAM_INT);
                $inventoryStmt->execute();

                if ($inventoryStmt->rowCount() == 0) {
                    // If not enough stock, rollback and return error
                    throw new Exception("Insufficient stock for detergent: " . $detergent);
                }

                // Update transaction count to the remaining count after stock reduction
                $updateUsageStmt = $conn->prepare("
                    UPDATE detergent_usage 
                    SET transactionCount = :remainingCount 
                    WHERE detergentID = :usage_id
                ");
                $updateUsageStmt->bindParam(':remainingCount', $remainingCount, PDO::PARAM_INT);
                $updateUsageStmt->bindParam(':usage_id', $detergentUsageID, PDO::PARAM_INT);
                $updateUsageStmt->execute();

                // Log stock reductions (optional)
                $stockReductions[] = [
                    'detergent' => $detergent,
                    'reduced_by' => $reduceStock
                ];
            } else {
                // Update transaction count without reducing stock
                $updateUsageStmt = $conn->prepare("
                    UPDATE detergent_usage 
                    SET transactionCount = :newCount 
                    WHERE detergentID = :usage_id
                ");
                $updateUsageStmt->bindParam(':newCount', $newCount, PDO::PARAM_INT);
                $updateUsageStmt->bindParam(':usage_id', $detergentUsageID, PDO::PARAM_INT);
                $updateUsageStmt->execute();
            }
        }

        // Commit Transaction
        $conn->commit();

    } catch (Exception $e) {
        // Rollback Transaction in case of error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "Error: " . $e->getMessage();
    }

    // Close the connection
    $conn = null;
}
?>
