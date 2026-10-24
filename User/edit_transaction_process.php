<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_type   = trim($_POST['type'] ?? ''); // ประเภทเดิม (income หรือ expense)
    $target_type     = trim($_POST['target_type'] ?? $original_type); // ประเภทใหม่ที่ต้องการเปลี่ยน
    $id              = intval($_POST['id'] ?? 0);
    $installation_id = !empty($_POST['installation_id']) ? intval($_POST['installation_id']) : null;
    $title           = trim($_POST['title'] ?? '');
    $amount          = floatval($_POST['amount'] ?? 0);
    $category        = trim($_POST['category'] ?? '');

    if ($id <= 0 || empty($title) || $amount <= 0 || !in_array($original_type, ['income', 'expense']) || !in_array($target_type, ['income', 'expense'])) {
        header("Location: expenses.php?status=error");
        exit();
    }

    $old_table = ($original_type === 'income') ? 'incomes' : 'expenses';
    $new_table = ($target_type === 'income') ? 'incomes' : 'expenses';

    // 1. ดึงข้อมูลรูปภาพเดิมเตรียมไว้
    $existing_receipt = null;
    try {
        $stmt_old = $pdo->prepare("SELECT receipt_image FROM {$old_table} WHERE id = ?");
        $stmt_old->execute([$id]);
        $row_old = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $existing_receipt = $row_old['receipt_image'] ?? null;
    } catch (PDOException $e) {
        header("Location: expenses.php?status=error");
        exit();
    }

    // 2. จัดการรูปภาพใหม่ (ถ้ามีอัปโหลดภาพเข้ามาใหม่)
    $receipt_image_path = $existing_receipt;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['receipt_image']['tmp_name'];
        $file_name = $_FILES['receipt_image']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        if (in_array($file_ext, $allowed_exts)) {
            $prefix = ($target_type === 'income') ? 'income_' : 'expense_';
            $new_file_name = $prefix . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $upload_dir = '../uploads/receipts/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $receipt_image_path = '../uploads/receipts/' . $new_file_name;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        if ($original_type !== $target_type) {
            // กรณีสลับประเภท (ลบจากตารางเดิม แล้วเพิ่มเข้าตารางใหม่)
            $stmt_del = $pdo->prepare("DELETE FROM {$old_table} WHERE id = ?");
            $stmt_del->execute([$id]);

            $sql_ins = "INSERT INTO {$new_table} (installation_id, title, amount, category, receipt_image, created_at) 
                        VALUES (:installation_id, :title, :amount, :category, :receipt_image, NOW())";
            $stmt_ins = $pdo->prepare($sql_ins);
            $stmt_ins->execute([
                ':installation_id' => $installation_id,
                ':title'           => $title,
                ':amount'          => $amount,
                ':category'        => $category,
                ':receipt_image'   => $receipt_image_path
            ]);
        } else {
            // กรณีประเภทเดิม (อัปเดตข้อมูลในตารางเดิม)
            $sql_upd = "UPDATE {$old_table} 
                        SET installation_id = :installation_id, title = :title, amount = :amount, category = :category, receipt_image = :receipt_image 
                        WHERE id = :id";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':installation_id' => $installation_id,
                ':title'           => $title,
                ':amount'          => $amount,
                ':category'        => $category,
                ':receipt_image'   => $receipt_image_path,
                ':id'              => $id
            ]);
        }

        $pdo->commit();
        header("Location: expenses.php?status=updated");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: expenses.php?status=error");
        exit();
    }
} else {
    header("Location: expenses.php");
    exit();
}