<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

$template_id = intval($_GET['template_id'] ?? 0);

if ($template_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, kwp, quantity, remark FROM requisition_template_items WHERE template_id = ?");
        $stmt->execute([$template_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'items' => $items]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>