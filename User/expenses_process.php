<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าที่ส่งมาจากฟอร์มในหน้า expenses.php
    $installation_id = isset($_POST['installation_id']) && $_POST['installation_id'] !== '' ? intval($_POST['installation_id']) : null;
    $title           = isset($_POST['title']) ? trim($_POST['title']) : '';
    $amount          = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $category        = isset($_POST['category']) ? trim($_POST['category']) : 'ทั่วไป';

    // ตรวจสอบความถูกต้องเบื้องต้น
    if (empty($title) || $amount <= 0) {
        header("Location: expenses.php?status=error");
        exit();
    }

    // จัดการอัปโหลดรูปสลิปหลักฐาน
    $receipt_path = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/receipts/'; // โฟลเดอร์สำหรับเก็บสลิป
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES['receipt_image']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = 'receipt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target_file)) {
                $receipt_path = $target_file; // พาธที่จะบันทึกลงฐานข้อมูล
            }
        }
    }

    try {
        // บันทึกข้อมูลค่าใช้จ่ายและพาธรูปสลิปลงตาราง expenses
        $stmt = $pdo->prepare("
            INSERT INTO expenses (installation_id, title, amount, category, receipt_image, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$installation_id, $title, $amount, $category, $receipt_path]);

        // บันทึกสำเร็จ กลับไปหน้า expenses.php พร้อมแจ้งเตือน success
        header("Location: expenses.php?status=success");
        exit();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้กลับไปหน้า expenses.php พร้อมแจ้งเตือน error
        header("Location: expenses.php?status=error");
        exit();
    }
} else {
    // ถ้าไม่ได้เข้ามาด้วยวิธี POST ให้เด้งกลับหน้า expenses.php
    header("Location: expenses.php");
    exit();
}
?>