<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installation_id = intval($_POST['installation_id']);
    $doc_type = trim($_POST['doc_type']);
    $doc_title = trim($_POST['doc_title']);
    
    $upload_dir = '../uploads/billings/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_path_db = '';

    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_name = $_FILES['document_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($file_ext, $allowed_extensions)) {
            $new_filename = 'doc_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $file_path_db = 'uploads/billings/' . $new_filename;
            } else {
                header("Location: billing_admin.php?status=error");
                exit();
            }
        } else {
            header("Location: billing_admin.php?status=error");
            exit();
        }
    } else {
        header("Location: billing_admin.php?status=error");
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO project_billings (installation_id, doc_type, doc_title, file_path, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$installation_id, $doc_type, $doc_title, $file_path_db]);
        
        header("Location: billing_admin.php?status=success");
        exit();
    } catch (PDOException $e) {
        header("Location: billing_admin.php?status=error");
        exit();
    }
} else {
    header("Location: billing_admin.php");
    exit();
}