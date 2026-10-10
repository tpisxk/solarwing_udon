<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // เริ่ม Transaction เพื่อความปลอดภัยในการลบข้อมูลที่เกี่ยวข้องกัน
        $pdo->beginTransaction();

        // 1. ลบงานย่อยที่เกี่ยวข้องในตาราง installation_tasks ก่อน
        $stmt_tasks = $pdo->prepare("DELETE FROM installation_tasks WHERE installation_id = ?");
        $stmt_tasks->execute([$id]);

        // 2. ลบข้อมูลโครงการหลักในตาราง installations
        $stmt_install = $pdo->prepare("DELETE FROM installations WHERE id = ?");
        $stmt_install->execute([$id]);

        // ยืนยันการทำรายการ
        $pdo->commit();

        header("Location: work_progress.php?success=deleted");
        exit();
    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกการเปลี่ยนแปลงทั้งหมด
        $pdo->rollBack();
        header("Location: work_progress.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: work_progress.php?error=invalid_id");
exit();