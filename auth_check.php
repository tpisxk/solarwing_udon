<?php
/**
 * auth_check.php
 * ไฟล์ตรวจสอบสถานะการเข้าสู่ระบบและสิทธิ์การใช้งาน อิงตาม id จากตาราง user_type
 * สำหรับระบบ SolarStock Pro
 */

// ป้องกันการเปิด session ซ้ำซ้อน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ป้องกันการแคชหน้าเว็บ เพื่อให้พอกดปุ่ม Back จะต้องโหลดและเช็คสิทธิ์ใหม่ทันที
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
// 1. ตรวจสอบเบื้องต้นว่าผู้ใช้ล็อกอินเข้ามาหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: User/login.php"); // หรือพาธที่ถูกต้องไปยังหน้า login ในโฟลเดอร์ User
    exit();
}

/**
 * 2. ฟังก์ชันจำกัดสิทธิ์การเข้าถึง อิงตามไอดี (id) ของ Role
 * @param array $allowed_ids รายการ ID ที่อนุญาต เช่น [1] สำหรับ Admin, [1, 2] สำหรับ Admin และ Ceo
 */
function checkRole($allowed_ids = []) {
    $current_role_id = intval($_SESSION['role_id'] ?? 3);
    
    if (!empty($allowed_ids)) {
        if (!in_array($current_role_id, $allowed_ids)) {
            // ปรับเส้นทางให้วิ่งเข้าโฟลเดอร์ User/dashboard.php
            header("Location: User/dashboard.php?error=unauthorized");
            exit();
        }
    }
}
?>