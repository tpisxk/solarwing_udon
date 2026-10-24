<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'expenses'; // กำหนดหน้าปัจจุบันสำหรับไฮไลต์ Sidebar

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!in_array($type, ['income', 'expense']) || $id <= 0) {
    header("Location: expenses_admin.php");
    exit();
}

$table = ($type === 'income') ? 'incomes' : 'expenses';

// ดึงข้อมูลเดิมขึ้นมาแสดง
try {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header("Location: expenses_admin.php?status=error");
        exit();
    }
} catch (PDOException $e) {
    header("Location: expenses_admin.php?status=error");
    exit();
}

// ดึงรายชื่อโครงการทั้งหมดสำหรับ Dropdown
try {
    $stmt_inst = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsDropdown = $stmt_inst->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $installationsDropdown = [];
}

// จัดการเมื่อกดบันทึกการแก้ไข (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installation_id = !empty($_POST['installation_id']) ? intval($_POST['installation_id']) : null;
    $title = trim($_POST['title'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $receipt_image_path = $item['receipt_image']; // ใช้ไฟล์เดิมเป็นค่าเริ่มต้น

    if (empty($title) || $amount <= 0) {
        $error_message = "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง";
    } else {
        // ตรวจสอบการอัปโหลดไฟล์ใหม่ (ถ้ามี)
        if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['receipt_image']['tmp_name'];
            $file_name = $_FILES['receipt_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
            if (in_array($file_ext, $allowed_exts)) {
                $new_file_name = $type . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                $upload_dir = '../uploads/receipts/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $destination = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $destination)) {
                    // ลบไฟล์เก่าทิ้งถ้ามี
                    if (!empty($item['receipt_image']) && file_exists('../' . $item['receipt_image'])) {
                        @unlink('../' . $item['receipt_image']);
                    }
                    $receipt_image_path = 'uploads/receipts/' . $new_file_name;
                }
            }
        }

        try {
            $update_sql = "UPDATE $table SET installation_id = ?, title = ?, amount = ?, category = ?, receipt_image = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$installation_id, $title, $amount, $category, $receipt_image_path, $id]);

            header("Location: expenses_admin.php?status=updated");
            exit();
        } catch (PDOException $e) {
            $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไข<?= ($type === 'income') ? 'รายการรายรับ' : 'รายการค่าใช้จ่าย' ?> - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'sans-serif'] },
                    colors: { darkSidebar: '#000007', brandGreen: '#059669' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_admin.php')) include 'sidebar_admin.php'; ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">แก้ไข<?= ($type === 'income') ? 'รายการรายรับ' : 'รายการค่าใช้จ่าย' ?></h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="expenses_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-emerald-600"></i> <span class="hidden sm:inline">กลับหน้าจัดการ</span>
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">
            <div class="max-w-2xl mx-auto w-full bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 md:p-8 space-y-6">
                
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="text-base md:text-lg font-bold text-slate-900">
                        <i class="fa-solid fa-pen-to-square text-amber-500 me-2"></i> ข้อมูลรายการ
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">ปรับปรุงรายละเอียดจำนวนเงิน หมวดหมู่ หรือเปลี่ยนไฟล์หลักฐานใหม่</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-medium">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน</label>
                        <select name="installation_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="">-- ส่วนกลาง / ไม่ระบุโครงการ --</option>
                            <?php foreach ($installationsDropdown as $inst): ?>
                                <option value="<?= $inst['id'] ?>" <?= ($item['installation_id'] == $inst['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">รายการ / รายละเอียด <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนเงิน (บาท) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="amount" value="<?= $item['amount'] ?>" min="0.01" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-bold <?= ($type === 'income') ? 'text-emerald-600' : 'text-rose-600' ?>">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">หมวดหมู่</label>
                        <input type="text" name="category" value="<?= htmlspecialchars($item['category']) ?>" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="space-y-2 pt-2">
                        <label class="block text-xs font-semibold text-slate-600">หลักฐานเดิมในระบบ</label>
                        <?php if (!empty($item['receipt_image'])): 
                            $ext = strtolower(pathinfo($item['receipt_image'], PATHINFO_EXTENSION));
                        ?>
                            <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-2xl">
                                <?php if ($ext === 'pdf'): ?>
                                    <a href="../<?= htmlspecialchars($item['receipt_image']) ?>" target="_blank" class="text-rose-600 font-bold text-xs inline-flex items-center gap-1">
                                        <i class="fa-solid fa-file-pdf"></i> เปิดดูไฟล์ PDF เดิม
                                    </a>
                                <?php else: ?>
                                    <img src="../<?= htmlspecialchars($item['receipt_image']) ?>" class="w-12 h-12 object-cover rounded-xl border border-slate-200" alt="หลักฐาน">
                                    <span class="text-xs text-slate-500">มีรูปภาพหลักฐานเดิมเรียบร้อยแล้ว</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-slate-400">ยังไม่มีไฟล์หลักฐาน</p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-1.5 pt-2">
                        <label class="block text-xs font-semibold text-slate-600">เปลี่ยนไฟล์หลักฐานใหม่ (รองรับรูปภาพและ PDF)</label>
                        <input type="file" name="receipt_image" accept="image/*,application/pdf" class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="expenses_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-6 py-3 rounded-2xl text-xs font-semibold transition">ยกเลิก</a>
                        <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition">
                            <i class="fa-solid fa-save me-1"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }
    </script>
</body>
</html>