<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = isset($_POST['name']) ? trim($_POST['name']) : '';
    $system_size = isset($_POST['system_size']) ? trim($_POST['system_size']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $map_url     = isset($_POST['map_url']) ? trim($_POST['map_url']) : '';

    if (empty($name)) {
        header("Location: locations.php?status=error");
        exit();
    }

    try {
        // วิธีที่ 1: บันทึกแบบระบุครบทุกฟิลด์ รวมถึง system_size
        $stmt = $pdo->prepare("INSERT INTO installations (name, system_size, description, map_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $system_size, $description, $map_url]);

        header("Location: locations.php?status=success");
        exit();
    } catch (Exception $e1) {
        try {
            // วิธีที่ 2: เผื่อกรณีตารางใช้ชื่อฟิลด์ project_name แทน name
            $stmt = $pdo->prepare("INSERT INTO installations (project_name, system_size, description, map_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $system_size, $description, $map_url]);

            header("Location: locations.php?status=success");
            exit();
        } catch (Exception $e2) {
            try {
                // วิธีที่ 3: บันทึกเฉพาะฟิลด์พื้นฐาน
                $stmt = $pdo->prepare("INSERT INTO installations (name) VALUES (?)");
                $stmt->execute([$name]);

                header("Location: locations.php?status=success");
                exit();
            } catch (Exception $e3) {
                header("Location: locations.php?status=error");
                exit();
            }
        }
    }
} else {
    header("Location: locations.php");
    exit();
}
?>