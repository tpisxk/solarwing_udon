<?php
session_start();
require_once '../db/conn.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../auth_check.php';
// รับค่าเดือนและปีสำหรับ redirect กลับหน้าปฏิทิน
$redirect_month = isset($_POST['redirect_month']) ? intval($_POST['redirect_month']) : date('n');
$redirect_year = isset($_POST['redirect_year']) ? intval($_POST['redirect_year']) : date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id               = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $title            = trim($_POST['title']);
    $appointment_date = $_POST['appointment_date'];
    $start_time       = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time         = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $status           = !empty($_POST['status']) ? $_POST['status'] : 'pending';
    $details          = trim($_POST['details']);

    // ตรวจสอบว่ามี ID และข้อมูลสำคัญครบถ้วนหรือไม่
    if ($id && !empty($title) && !empty($appointment_date)) {
        try {
            // ดึงเดือน/ปี จากวันที่นัดหมาย เพื่อให้ redirect กลับไปแสดงผลเดือนนั้นๆ ได้ถูกต้อง
            $date_parts = explode('-', $appointment_date);
            if (count($date_parts) === 3) {
                $redirect_month = intval($date_parts[1]);
                $redirect_year = intval($date_parts[0]);
            }

            // อัปเดตข้อมูลลงในฐานข้อมูลตาราง appointments
            $stmt = $pdo->prepare("UPDATE appointments SET title = ?, appointment_date = ?, start_time = ?, end_time = ?, status = ?, details = ? WHERE id = ?");
            $stmt->execute([$title, $appointment_date, $start_time, $end_time, $status, $details, $id]);

            // ส่งสถานะกลับไปแสดง Popup แก้ไขสำเร็จที่หน้า calendar.php
            header("Location: calendar.php?status=success&action=edit&month={$redirect_month}&year={$redirect_year}");
            exit();

        } catch (PDOException $e) {
            // หากเกิดข้อผิดพลาดจากฐานข้อมูล
            header("Location: calendar.php?status=error&month={$redirect_month}&year={$redirect_year}");
            exit();
        }
    }
}

// หากเข้ามาโดยไม่มีการส่งข้อมูล POST หรือข้อมูลไม่ครบ
header("Location: User/calendar.php?month={$redirect_month}&year={$redirect_year}");
exit();