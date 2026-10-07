<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าที่ส่งมาจากฟอร์มรับเข้าสินค้า
    $product_id      = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity        = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $reference_no    = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';
    $note            = isset($_POST['note']) ? trim($_POST['note']) : '';
    $user_id         = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1; // ค่าเริ่มต้นพนักงาน ID 1

    // เพิ่มการรับค่า โครงการ / หน้างาน (รองรับทั้ง name="installation_id" และ name="project_id")
    $installation_id = null;
    if (!empty($_POST['installation_id'])) {
        $installation_id = intval($_POST['installation_id']);
    } elseif (!empty($_POST['project_id'])) {
        $installation_id = intval($_POST['project_id']);
    }

    // ตรวจสอบความถูกต้องเบื้องต้น
    if ($product_id <= 0 || $quantity <= 0) {
        header("Location: stockin_admin.php?status=error");
        exit();
    }

    try {
        // เริ่มต้น Transaction เพื่อความปลอดภัยของข้อมูล
        $pdo->beginTransaction();

        // 1. ตรวจสอบว่าสินค้ามีอยู่จริงหรือไม่ และล็อกแถวข้อมูลเพื่อป้องกันการชนกัน (Race Condition)
        $stmt_check = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
        $stmt_check->execute([$product_id]);
        $product = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $pdo->rollBack();
            header("Location: stockin_admin.php?status=error");
            exit();
        }

        $current_stock = intval($product['stock_quantity']);
        $new_stock     = $current_stock + $quantity;

        // 2. อัปเดตสต็อกสินค้าเพิ่มขึ้นในตาราง products
        $stmt_update = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt_update->execute([$new_stock, $product_id]);

        // 3. บันทึกประวัติการรับสินค้าเข้าลงในตาราง stock_transactions (เพิ่ม installation_id)
        $stmt_insert = $pdo->prepare("
            INSERT INTO stock_transactions (product_id, type, quantity, installation_id, reference_no, user_id, note, created_at) 
            VALUES (?, 'in', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt_insert->execute([$product_id, $quantity, $installation_id, $reference_no, $user_id, $note]);

        // ยืนยันการทำรายการทั้งหมด
        $pdo->commit();

        // สำเร็จ กลับไปหน้า stockin.php พร้อมแจ้งสถานะ success
        header("Location: stockin_admin.php?status=success");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // หากต้องการดีบักข้อผิดพลาด สามารถเปิดบรรทัดล่างนี้ดูได้
        // echo "Error: " . $e->getMessage(); exit();

        header("Location: stockin_admin.php?status=error");
        exit();
    }
} else {
    // ถ้าไม่ได้ส่งผ่านวิธี POST ให้เด้งกลับหน้า stockin.php ทันที
    header("Location: stockin_admin.php");
    exit();
}
?>