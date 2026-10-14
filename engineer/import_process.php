<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_submit'])) {
    $rows = [];

    // 1. ตรวจสอบข้อมูล CSV ที่แปลงมาจาก SheetJS (.xlsx หรือ .xls)
    if (!empty($_POST['converted_csv_data'])) {
        $csvText = $_POST['converted_csv_data'];
        $lines = explode("\n", $csvText);
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine !== '') {
                $rows[] = str_getcsv($trimmedLine);
            }
        }
    } 
    // 2. ตรวจสอบไฟล์ .csv ปกติผ่าน $_FILES
    elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (!empty(array_filter($data))) {
                    $rows[] = $data;
                }
            }
            fclose($handle);
        }
    }

    if (!empty($rows)) {
        try {
            $pdo->beginTransaction();
            $updatedCount = 0;

            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
            $stmtUpdate = $pdo->prepare("UPDATE products SET name = ?, category = ?, stock_quantity = ?, cost_price = ? WHERE sku = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO products (sku, name, category, stock_quantity, cost_price, selling_price) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($rows as $index => $data) {
                // ข้ามแถวหัวตาราง (Header)
                if ($index === 0) {
                    $firstCol = strtolower(trim($data[0] ?? ''));
                    $secondCol = strtolower(trim($data[1] ?? ''));
                    if (str_contains($firstCol, 'sku') || str_contains($firstCol, 'รหัส') || str_contains($secondCol, 'ชื่อ')) {
                        continue;
                    }
                }

                // ดึงข้อมูลตามลำดับคอลัมน์จากภาพไฟล์ Excel จริง
                $sku = trim($data[0] ?? '');           // Col A: รหัสสินค้า
                $name = trim($data[1] ?? '');          // Col B: ชื่อสินค้า[cite: 3]

                if ($sku !== '' && $name !== '') {
                    $category = trim($data[2] ?? '-');     // Col C: หน่วยนับ (เก็บลง category)[cite: 3]
                    $stock_quantity = intval(str_replace(',', '', $data[3] ?? 0)); // Col D: จำนวนคงเหลือ[cite: 3]
                    $cost_price = floatval(str_replace(',', '', $data[5] ?? 0));   // Col F: ต้นทุนเฉลี่ย[cite: 3]
                    $selling_price = $cost_price;          // กำหนดราคาขายเริ่มต้นเท่ากับต้นทุน (ปรับเปลี่ยนได้ตามต้องการ)

                    // เช็คว่ามี SKU นี้ในระบบหรือยัง
                    $stmtCheck->execute([$sku]);
                    $existingProduct = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingProduct) {
                        // อัปเดตข้อมูลเดิม
                        $stmtUpdate->execute([$name, $category, $stock_quantity, $cost_price, $sku]);
                    } else {
                        // เพิ่มข้อมูลใหม่
                        $stmtInsert->execute([$sku, $name, $category, $stock_quantity, $cost_price, $selling_price]);
                    }
                    
                    $updatedCount++;
                }
            }

            $pdo->commit();
            header("Location: inventory.php?import=success&count=" . $updatedCount);
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: inventory.php?import=error");
            exit();
        }
    } else {
        header("Location: inventory.php?import=error");
        exit();
    }
} else {
    header("Location: inventory.php");
    exit();
}
?>