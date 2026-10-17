<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า installation_id (ถ้าเลือกส่วนกลาง ค่าจะเป็น NULL)
    $installation_id = !empty($_POST['installation_id']) ? intval($_POST['installation_id']) : null;
    $title = trim($_POST['title'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $category = trim($_POST['category'] ?? 'รายรับโครงการ');
    $receipt_image_path = null;

    if (empty($title) || $amount <= 0) {
        header("Location: expenses.php?status=error");
        exit();
    }

    // จัดการอัปโหลดรูปภาพหลักฐาน
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['receipt_image']['tmp_name'];
        $file_name = $_FILES['receipt_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = 'income_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
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
        $sql = "INSERT INTO incomes (installation_id, title, amount, category, receipt_image, created_at) 
                VALUES (:installation_id, :title, :amount, :category, :receipt_image, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':installation_id' => $installation_id,
            ':title'           => $title,
            ':amount'          => $amount,
            ':category'        => $category,
            ':receipt_image'   => $receipt_image_path
        ]);

        header("Location: expenses.php?status=success");
        exit();

    } catch (PDOException $e) {
        header("Location: expenses.php?status=error");
        exit();
    }
}