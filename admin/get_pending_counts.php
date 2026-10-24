<?php
session_start();
require_once '../db/conn.php';

header('Content-Type: application/json');

$response = [
    'procurement' => 0,
    'requisitions' => 0
];

try {
    // เปลี่ยนจากตาราง procurement เป็น purchase_orders ตามโครงสร้างจริง
    $stmt_proc = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'Pending'");
    $response['procurement'] = intval($stmt_proc->fetchColumn());
} catch (PDOException $e) {
    $response['procurement'] = 0;
}

try {
    // นับจำนวนใบเบิกที่รอตรวจสอบ (Pending)
    $stmt_req = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending'");
    $response['requisitions'] = intval($stmt_req->fetchColumn());
} catch (PDOException $e) {
    $response['requisitions'] = 0;
}

echo json_encode($response);
?>