<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id      = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity        = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $installation_id = !empty($_POST['installation_id']) ? intval($_POST['installation_id']) : 0;
    $note            = isset($_POST['note']) ? trim($_POST['note']) : '';
    $user_id         = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;

    // ตรวจสอบค่าที่ส่งมา: บังคับให้ product_id, quantity และ installation_id ต้องมากกว่า 0
    if ($product_id <= 0 || $quantity <= 0 || $installation_id <= 0) {
        header("Location: stockout_admin.php?status=error");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. ตรวจสอบสต็อกปัจจุบัน (ใช้ FOR UPDATE เพื่อป้องกัน Race Condition)
        $stmt_check = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
        $stmt_check->execute([$product_id]);
        $product = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$product || intval($product['stock_quantity']) < $quantity) {
            $pdo->rollBack();
            header("Location: stockout_admin.php?status=insufficient");
            exit();
        }

        $new_stock = intval($product['stock_quantity']) - $quantity;

        // 2. ตัดสต็อกสินค้า
        $stmt_update = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt_update->execute([$new_stock, $product_id]);

        // 3. บันทึกประวัติการเบิกออก (เชื่อมโยงกับ installation_id เสมอ)
        $stmt_insert = $pdo->prepare("
            INSERT INTO stock_transactions (product_id, installation_id, type, quantity, reference_no, user_id, note, created_at) 
            VALUES (?, ?, 'out', ?, '', ?, ?, NOW())
        ");
        $stmt_insert->execute([$product_id, $installation_id, $quantity, $user_id, $note]);

        $pdo->commit();
        header("Location: stockout_admin.php?status=success");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: stockout_admin.php?status=error");
        exit();
    }
} else {
    header("Location: stockout_admin.php");
    exit();
}
?>