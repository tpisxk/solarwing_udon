<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$redirect_month = isset($_POST['redirect_month']) ? intval($_POST['redirect_month']) : date('n');
$redirect_year  = isset($_POST['redirect_year']) ? intval($_POST['redirect_year']) : date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ตรวจสอบ CSRF Token
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: calendar.php?status=error&message=csrf_invalid&month={$redirect_month}&year={$redirect_year}");
        exit();
    }

    $id         = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $title      = trim($_POST['title'] ?? '');
    
    // ดึง user_id จาก Session ของคนที่ล็อกอินอยู่
    $session_user_id = $_SESSION['user_id'] 
                    ?? $_SESSION['id'] 
                    ?? $_SESSION['user']['id'] 
                    ?? $_SESSION['user']['user_id'] 
                    ?? null;

    // หากรับค่า user_id จาก Dropdown มา ให้ใช้ค่านั้น ถ้าไม่มีให้ใช้ ID จาก Session
    $user_id    = !empty($_POST['user_id']) ? intval($_POST['user_id']) : $session_user_id;

    $start_date = $_POST['start_date'] ?? '';
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
            $date_parts = explode('-', $start_date);
            if (count($date_parts) === 3) {
                $redirect_year  = intval($date_parts[0]);
                $redirect_month = intval($date_parts[1]);
            }

            // จัดฟอร์แมตวันที่เป็น datetime
            $appointment_datetime = $start_date . ' ' . ($start_time ? $start_time . ':00' : '00:00:00');

            if ($id) {
                // UPDATE ข้อมูลนัดหมาย
                $stmt = $pdo->prepare("UPDATE appointments 
                                       SET user_id = ?,
                                           title = ?, 
                                           appointment_date = ?, 
                                           end_date = ?, 
                                           start_time = ?, 
                                           end_time = ?, 
                                           status = ?, 
                                           details = ? 
                                       WHERE id = ?");
                $stmt->execute([
                    $user_id,
                    $title, 
                    $appointment_datetime, 
                    $end_date, 
                    $start_time, 
                    $end_time, 
                    $status, 
                    $details, 
                    $id
                ]);

                header("Location: calendar.php?status=success&action=edit&month={$redirect_month}&year={$redirect_year}");
                exit();
            } else {
                // INSERT นัดหมายใหม่
                $stmt = $pdo->prepare("INSERT INTO appointments 
                                       (user_id, title, appointment_date, end_date, start_time, end_time, status, details) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $title, 
                    $appointment_datetime, 
                    $end_date, 
                    $start_time, 
                    $end_time, 
                    $status, 
                    $details
                ]);

                header("Location: calendar.php?status=success&action=add&month={$redirect_month}&year={$redirect_year}");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: calendar.php?status=error&month={$redirect_month}&year={$redirect_year}");
            exit();
        }
    }
}

header("Location: calendar.php?month={$redirect_month}&year={$redirect_year}");
exit();