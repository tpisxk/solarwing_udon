<?php
/**
 * logout.php
 * ไฟล์สำหรับออกจากระบบ ล้างค่า Session และทำลาย Session ทั้งหมด
 * สำหรับระบบ SolarStock Pro
 */

// เปิด Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ล้างค่าตัวแปร Session ทั้งหมด
$_SESSION = array();

// 2. ลบ Cookie ที่ใช้เก็บบันทึก Session (ถ้ามี)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session ทิ้งอย่างสมบูรณ์
session_destroy();

// 4. สั่งเคลียร์ Cache ของเบราว์เซอร์เพื่อป้องกันการกดปุ่ม Back กลับมาดูข้อมูลเก่า


// 5. ดีดกลับไปยังหน้า Login (ปรับ Path ตามโครงสร้างโฟลเดอร์จริงของคุณ)
// ถ้าไฟล์ login.php อยู่ในโฟลเดอร์เดียวกัน ใช้:
header("Location: login.php");

// หรือถ้าหน้า login อยู่ข้างนอก ให้ปรับเป็น:
// header("Location: ../login.php");

exit();
?>