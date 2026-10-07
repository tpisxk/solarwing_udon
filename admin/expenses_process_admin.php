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
        header("Location: expenses_admin.php?status=error");
        exit();
    }

    try {
        // บันทึกข้อมูลค่าใช้จ่ายลงตาราง expenses (รวม installation_id)
        $stmt = $pdo->prepare("
            INSERT INTO expenses (installation_id, title, amount, category, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$installation_id, $title, $amount, $category]);

        // บันทึกสำเร็จ กลับไปหน้า expenses.php พร้อมแจ้งเตือน success
        header("Location: expenses_admin.php?status=success");
        exit();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้กลับไปหน้า expenses.php พร้อมแจ้งเตือน error
        header("Location: expenses_admin.php?status=error");
        exit();
    }
} else {
    // ถ้าไม่ได้เข้ามาด้วยวิธี POST ให้เด้งกลับหน้า expenses.php
    header("Location: expenses_admin.php");
    exit();
}
?>