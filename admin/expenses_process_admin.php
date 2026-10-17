<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installation_id = !empty($_POST['installation_id']) ? $_POST['installation_id'] : null;
    $title = $_POST['title'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $category = $_POST['category'] ?? 'ทั่วไป';
    $receipt_image_path = null;

    // จัดการอัปโหลดรูปสลิป
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['receipt_image']['tmp_name'];
        $file_name = $_FILES['receipt_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // อนุญาตเฉพาะไฟล์รูปภาพ
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_exts)) {
            // ตั้งชื่อไฟล์ใหม่ป้องกันชื่อซ้ำ
            $new_file_name = 'receipt_' . uniqid() . '.' . $file_ext;
            
            // โฟลเดอร์เป้าหมาย (อยู่นอก admin ออกมาที่ root/uploads/receipts/)
            $upload_dir = '../uploads/receipts/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                // บันทึกพาธที่จะเก็บลง DB (เช่น ../uploads/receipts/receipt_xxx.jpg)
                $receipt_image_path = '../uploads/receipts/' . $new_file_name;
            }
        }
    }

    try {
        // บันทึกลงตาราง expenses
        $stmt = $pdo->prepare("INSERT INTO expenses (installation_id, title, amount, category, receipt_image, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$installation_id, $title, $amount, $category, $receipt_image_path]);

        header("Location: expenses_admin.php?status=success");
        exit();
    } catch (PDOException $e) {
        header("Location: expenses_admin.php?status=error");
        exit();
    }
}