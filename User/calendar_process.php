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

// กรณีเป็นการลบข้อมูลผ่าน GET Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    $del_month = isset($_GET['redirect_month']) ? intval($_GET['redirect_month']) : $redirect_month;
    $del_year  = isset($_GET['redirect_year']) ? intval($_GET['redirect_year']) : $redirect_year;

    try {
        $stmt_del = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt_del->execute([$delete_id]);

        header("Location: calendar.php?status=success&action=delete&month={$del_month}&year={$del_year}");
        exit();
    } catch (PDOException $e) {
        header("Location: calendar.php?status=error&month={$del_month}&year={$del_year}");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ตรวจสอบ CSRF Token
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: calendar.php?status=error&message=csrf_invalid&month={$redirect_month}&year={$redirect_year}");
        exit();
    }

    $id    = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $title = trim($_POST['title'] ?? '');
    
    // จัดการทีมติดตั้งที่ถูกเลือกแบบ Checkbox หลายค่า (นำมารวมกันเป็นข้อความคั่นด้วยคอมม่า เช่น W1,W2)
    $installation_team_arr = $_POST['installation_team'] ?? [];
    $installation_team = !empty($installation_team_arr) ? implode(',', $installation_team_arr) : null;
    
    // บังคับดึง user_id จาก Session ของคนที่กำลังล็อกอินอยู่เสมอ
    $user_id = $_SESSION['user_id'] 
            ?? $_SESSION['id'] 
            ?? $_SESSION['user']['id'] 
            ?? $_SESSION['user']['user_id'] 
            ?? null;

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
                                           installation_team = ?,
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
                    $installation_team,
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
                                       (user_id, installation_team, title, appointment_date, end_date, start_time, end_time, status, details) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $installation_team,
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