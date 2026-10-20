<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $installation_id = intval($_POST['installation_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    
    if ($product_id <= 0 || $quantity <= 0 || $installation_id <= 0) {
        header("Location: stockout_admin.php?status=error");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. ดึงชื่อโครงการจาก installations
        $stmt_inst = $pdo->prepare("SELECT name FROM installations WHERE id = ?");
        $stmt_inst->execute([$installation_id]);
        $inst = $stmt_inst->fetch(PDO::FETCH_ASSOC);
        $project_name = $inst['name'] ?? 'ไม่ระบุโครงการ';

        // 2. ดึงชื่อผู้ขอ (จาก session)
        $engineer_name = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'วิศวกร';

        // 3. บันทึกหัวบิลลงตาราง requisitions (เพิ่ม installation_id เข้าไปตรงนี้)
        $stmt_req = $pdo->prepare("
            INSERT INTO requisitions (installation_id, project_name, engineer_name, req_date, status, created_at) 
            VALUES (?, ?, ?, CURDATE(), 'Pending', NOW())
        ");
        $stmt_req->execute([$installation_id, $project_name, $engineer_name]);
        $requisition_id = $pdo->lastInsertId();

        // 4. บันทึกรายการสินค้าลงตาราง requisition_items
        $stmt_item = $pdo->prepare("
            INSERT INTO requisition_items (requisition_id, product_id, quantity) 
            VALUES (?, ?, ?)
        ");
        $stmt_item->execute([$requisition_id, $product_id, $quantity]);

        $pdo->commit();
        header("Location: stockout_admin.php?status=success");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        header("Location: stockout_admin.php?status=error");
        exit();
    }
} else {
    header("Location: stockout_admin.php");
    exit();
}