<?php
session_start();
require_once '../db/conn.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../auth_check.php';

// รับค่าเดือนและปีสำหรับ redirect กลับหน้าปฏิทิน admin ให้ตรงกับเดือนเดิม
$redirect_month = isset($_POST['redirect_month']) ? intval($_POST['redirect_month']) : date('n');
$redirect_year  = isset($_POST['redirect_year']) ? intval($_POST['redirect_year']) : date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ตรวจสอบ CSRF Token เพื่อความปลอดภัย
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: calendar_admin.php?status=error&message=csrf_invalid&month={$redirect_month}&year={$redirect_year}");
        exit();
    }

    $id         = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $title      = trim($_POST['title'] ?? '');
    $start_date = $_POST['start_date'] ?? $_POST['appointment_date'] ?? '';
    $end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : $start_date;
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time   = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $status     = !empty($_POST['status']) ? $_POST['status'] : 'pending';
    $details    = trim($_POST['details'] ?? '');

    if ($end_date < $start_date) {
        $end_date = $start_date;
    }

    if (!empty($title) && !empty($start_date)) {
        try {
            // ดึงเดือน/ปี จากวันเริ่มต้นงานเพื่อ redirect กลับได้ถูกเดือน
            $date_parts = explode('-', $start_date);
            if (count($date_parts) === 3) {
                $redirect_year  = intval($date_parts[0]);
                $redirect_month = intval($date_parts[1]);
            }

            // จัดรูปแบบ datetime สำหรับ appointment_date
            $appointment_datetime = $start_date . ' ' . ($start_time ? $start_time . ':00' : '00:00:00');

            if ($id) {
                // 1. อัปเดตข้อมูลเดิม (Update)
                $stmt = $pdo->prepare("UPDATE appointments 
                                       SET title = ?, 
                                           appointment_date = ?, 
                                           end_date = ?, 
                                           start_time = ?, 
                                           end_time = ?, 
                                           status = ?, 
                                           details = ? 
                                       WHERE id = ?");
                $stmt->execute([
                    $title, 
                    $appointment_datetime, 
                    $end_date, 
                    $start_time, 
                    $end_time, 
                    $status, 
                    $details, 
                    $id
                ]);

                header("Location: calendar_admin.php?status=success&action=edit&month={$redirect_month}&year={$redirect_year}");
                exit();
            } else {
                // 2. เพิ่มข้อมูลใหม่ (Create)
                $stmt = $pdo->prepare("INSERT INTO appointments 
                                       (title, appointment_date, end_date, start_time, end_time, status, details) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $title, 
                    $appointment_datetime, 
                    $end_date, 
                    $start_time, 
                    $end_time, 
                    $status, 
                    $details
                ]);

                header("Location: calendar_admin.php?status=success&action=add&month={$redirect_month}&year={$redirect_year}");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: calendar_admin.php?status=error&month={$redirect_month}&year={$redirect_year}");
            exit();
        }
    }
}

// แก้ไข Path ให้ redirect กลับไปที่ calendar_admin.php (ไม่ติด User/)
header("Location: calendar_admin.php?month={$redirect_month}&year={$redirect_year}");
exit();