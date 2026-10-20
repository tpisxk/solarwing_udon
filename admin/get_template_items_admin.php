<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db/conn.php';
require_once '../auth_check.php';

$template_id = intval($_GET['template_id'] ?? 0);

if ($template_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Template ID']);
    exit;
}

try {
    // แก้ไขคำสั่ง SQL โดยเติม SELECT และระบุคอลัมน์ให้ครบถ้วนถูกต้อง
    $stmt = $pdo->prepare("
        SELECT 
            t.id as template_id,
            ti.product_id,
            ti.quantity,
            ti.remark,
            p.stock_quantity
        FROM requisition_template_items ti
        JOIN requisition_templates t ON ti.template_id = t.id
        LEFT JOIN products p ON ti.product_id = p.id
        WHERE ti.template_id = ?
    ");
    $stmt->execute([$template_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}