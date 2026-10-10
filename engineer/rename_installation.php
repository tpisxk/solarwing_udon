<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$id = intval($_GET['id'] ?? 0);
$new_name = trim($_GET['name'] ?? '');

if ($id > 0 && !empty($new_name)) {
    try {
        // ตรวจสอบว่าตารางใช้คอลัมน์ชื่อ 'name' หรือ 'title'
        // อัปเดตทั้งสองฟิลด์เพื่อความปลอดภัย
        $stmt = $pdo->prepare("UPDATE installations SET name = ?, title = ? WHERE id = ?");
        $stmt->execute([$new_name, $new_name, $id]);

        header("Location: work_progress.php?success=renamed");
        exit();
    } catch (PDOException $e) {
        header("Location: work_progress.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: work_progress.php?error=invalid");
exit();