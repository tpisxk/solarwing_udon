<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';
if (isset($_POST['import_submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $filename = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($filename, "r")) !== FALSE) {
            
            // ข้ามแถวหัวตาราง (Header) แถวแรก
            $header = fgetcsv($handle, 1000, ",");
            $successCount = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // ลำดับคอลัมน์จากไฟล์ Excel ของคุณ:
                // [0] = รหัสสินค้า (sku)
                // [1] = ชื่อสินค้า (name)
                // [2] = หน่วยนับ / หมวดหมู่ (category)
                // [3] = จำนวนคงเหลือ (stock_quantity)
                // [4] = ต้นทุนเฉลี่ยไม่รวม Vat (cost_price เดิม)
                // [5] = ต้นทุนรวมปัจจุบัน (นำมาใช้เป็น cost_price แทน)

                $sku            = trim($data[0] ?? '');
                $name           = trim($data[1] ?? '');
                $category       = trim($data[2] ?? '');
                
                // ทำความสะอาดตัวเลข (ตัดเครื่องหมายคอมมา และสัญลักษณ์เงินออก)
                $raw_qty        = str_replace([',', ' '], '', $data[3] ?? 0);
                $raw_total_cost = str_replace([',', '฿', ' '], '', $data[5] ?? 0); // ดึงจากคอลัมน์ต้นทุนรวมปัจจุบันมาเป็นราคาต้นทุน

                $stock_quantity = intval($raw_qty);
                
                // นำค่าต้นทุนรวมปัจจุบันไปใส่ใน cost_price และกำหนด selling_price เป็น 0 หรือปรับตามต้องการ
                $cost_price     = floatval($raw_total_cost);
                $selling_price  = 0.00; 

                if (!empty($sku)) {
                    // ตรวจสอบว่ามี SKU นี้อยู่แล้วในฐานข้อมูลหรือไม่
                    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
                    $checkStmt->execute([$sku]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        // ถ้ามีแล้ว -> อัปเดตข้อมูล
                        $updateStmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, cost_price = ?, selling_price = ?, stock_quantity = ? WHERE sku = ?");
                        $updateStmt->execute([$name, $category, $cost_price, $selling_price, $stock_quantity, $sku]);
                    } else {
                        // ถ้ายังไม่มี -> เพิ่มข้อมูลใหม่
                        $insertStmt = $pdo->prepare("INSERT INTO products (sku, name, category, cost_price, selling_price, stock_quantity) VALUES (?, ?, ?, ?, ?, ?)");
                        $insertStmt->execute([$sku, $name, $category, $cost_price, $selling_price, $stock_quantity]);
                    }
                    $successCount++;
                }
            }
            fclose($handle);

            header("Location: admin/inventory_admin.php?import=success&count=" . $successCount);
            exit();
        }
    }
}

header("Location: admin/inventory_admin.php?import=error");
exit();
?>