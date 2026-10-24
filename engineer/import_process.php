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

            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE name = ? OR sku = ?");
            $stmtUpdate = $pdo->prepare("UPDATE products SET category = ?, stock_quantity = stock_quantity + ? WHERE name = ? OR sku = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO products (sku, name, category, stock_quantity, cost_price, selling_price) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($rows as $index => $data) {
                // ข้ามแถวหัวตาราง (Header)
                if ($index === 0) {
                    $firstCol = strtolower(trim($data[0] ?? ''));
                    $secondCol = strtolower(trim($data[1] ?? ''));
                    if (str_contains($firstCol, 'ลำดับ') || str_contains($firstCol, 'no') || str_contains($secondCol, 'รายการ')) {
                        continue;
                    }
                }

                // ดึงข้อมูลตามตำแหน่งคอลัมน์จริงจากไฟล์ Excel
                // Col A [0] = ลำดับที่
                // Col B [1] = รายการ (ชื่อสินค้า)
                // Col C [2] = รุ่น/ขนาด
                // Col D [3] = จำนวน
                // Col E [4] = หน่วย
                // Col F [5] = หมายเหตุ

                $name           = trim($data[1] ?? '');                      // ชื่อสินค้า / รายการ
                $sku            = $name;                                     // ใช้ชื่อสินค้าเป็น SKU หลัก
                $stock_quantity = intval(str_replace(',', '', $data[3] ?? 0)); // จำนวน
                $unit           = trim($data[4] ?? '-');                     // หน่วยนับ (เก็บลง category)

                if ($name !== '' && $stock_quantity > 0) {
                    $cost_price    = 0.00;
                    $selling_price = 0.00;

                    // เช็คว่ามีสินค้านี้ในระบบหรือยัง (เช็คจากชื่อหรือ SKU)
                    $stmtCheck->execute([$name, $sku]);
                    $existingProduct = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingProduct) {
                        // อัปเดตหน่วยนับและเพิ่มจำนวนสต็อกสะสม
                        $stmtUpdate->execute([$unit, $stock_quantity, $name, $sku]);
                    } else {
                        // เพิ่มข้อมูลใหม่ลงในตาราง products
                        $stmtInsert->execute([$sku, $name, $unit, $stock_quantity, $cost_price, $selling_price]);
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