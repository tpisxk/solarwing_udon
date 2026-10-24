<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'billing_admin';

// รับค่าโครงการที่ถูกเลือกจาก Dropdown ตัวกรอง
$selected_installation_id = isset($_GET['installation_id']) && $_GET['installation_id'] !== '' ? $_GET['installation_id'] : 'all';

// รับค่าจำนวนแถวต่อหน้า (ค่าเริ่มต้น 5 แถว)
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
if (!in_array($limit, [5, 10, 25, 50, 100])) { $limit = 5; }

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// 1. ดึงรายชื่อโครงการทั้งหมดสำหรับใส่ใน Dropdown
try {
    $stmt_inst_list = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsDropdown = $stmt_inst_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $installationsDropdown = [];
}

// เงื่อนไขการกรอง
$where_clause = "";
$params = [];
if ($selected_installation_id !== 'all' && intval($selected_installation_id) > 0) {
    $where_clause = " WHERE b.installation_id = ? ";
    $params[] = intval($selected_installation_id);
}

// 2. ดึงข้อมูลเอกสารทางบัญชี
try {
    $sql_count = "SELECT COUNT(*) as total FROM project_billings b " . $where_clause;
    $stmt_c = $pdo->prepare($sql_count);
    $stmt_c->execute($params);
    $total_rows = $stmt_c->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $total_pages = ceil($total_rows / $limit);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) $page = $total_pages;
    $offset = ($page - 1) * $limit;

    $sql = "
        SELECT b.*, i.name as project_name 
        FROM project_billings b 
        LEFT JOIN installations i ON b.installation_id = i.id 
        $where_clause
        ORDER BY b.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $billingsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $billingsList = [];
    $total_pages = 1;
    $total_rows = 0;
    $offset = 0;
}

// ฟังก์ชันแปลงรูปแบบวันที่
function formatDateTH($datetime_str) {
    if (empty($datetime_str)) return '-';
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) return htmlspecialchars($datetime_str);
    return date('d/m/Y H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเอกสาร ใบวางบิล / ใบกำกับภาษี / หัก ณ ที่จ่าย - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Prompt', 'Inter', 'sans-serif'],
                        sarabun: ['Sarabun', 'sans-serif']
                    },
                    colors: { darkSidebar: '#000007', brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
    </style>
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
                <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">จัดการเอกสารบัญชี (ใบวางบิล / กำกับภาษี / หัก ณ ที่จ่าย)</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">
            
            <!-- แจ้งเตือนสถานะ -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>✨ บันทึกและอัปโหลดเอกสารเรียบร้อยแล้ว</span>
                        <a href="billing_admin.php" class="text-emerald-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (รองรับเฉพาะไฟล์รูปภาพและ PDF)</span>
                        <a href="billing_admin.php" class="text-rose-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- แถบกรองข้อมูลตามโครงการ -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">กรองเอกสารตามโครงการ</h3>
                        <p class="text-xs text-slate-500">เลือกโครงการเพื่อเรียกดูเอกสารที่เกี่ยวข้อง</p>
                    </div>
                </div>

                <form method="GET" action="" class="flex items-center gap-3 w-full md:w-auto">
                    <select name="installation_id" onchange="this.form.submit()" class="w-full md:w-72 px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-semibold text-slate-700 cursor-pointer">
                        <option value="all" <?= ($selected_installation_id === 'all') ? 'selected' : '' ?>>-- แสดงทุกโครงการทั้งหมด --</option>
                        <?php foreach ($installationsDropdown as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= ($selected_installation_id == $inst['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="limit" value="<?= $limit ?>">
                </form>
            </div>

            <!-- ฟอร์มแนบเอกสารใหม่ -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div>
                    <h3 class="text-base md:text-lg font-bold text-slate-900">แนบเอกสารทางบัญชีใหม่</h3>
                    <p class="text-xs text-slate-500 mt-1">อัปโหลดไฟล์ใบวางบิล, ใบกำกับภาษี หรือใบหัก ณ ที่จ่าย (รองรับไฟล์รูปภาพ JPG, PNG และ PDF)</p>
                </div>

                <form action="billing_process_admin.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">เลือกโครงการ / หน้างาน</label>
                        <select name="installation_id" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($installationsDropdown as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">ประเภทเอกสาร</label>
                        <select name="doc_type" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="ใบวางบิล">ใบวางบิล (Billing Note)</option>
                            <option value="ใบกำกับภาษี">ใบกำกับภาษี (Tax Invoice)</option>
                            <option value="ใบหัก ณ ที่จ่าย">ใบหัก ณ ที่จ่าย (Withholding Tax)</option>
                            <option value="อื่นๆ">เอกสารอื่นๆ</option>
                        </select>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">ชื่อเอกสาร / เลขที่เอกสาร</label>
                        <input type="text" name="doc_title" required placeholder="เช่น INV-2026-001 หรือ ค่าติดตั้งโซล่าเซลล์..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="space-y-1.5 md:col-span-6">
                        <label class="block text-xs font-semibold text-slate-600">อัปโหลดไฟล์เอกสาร (รองรับรูปภาพ JPG, PNG และไฟล์ PDF)</label>
                        <input type="file" name="document_file" accept=".jpg, .jpeg, .png, .pdf" required class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>

                    <div class="md:col-span-6 flex justify-end pt-2">
                        <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all cursor-pointer">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i> อัปโหลดและบันทึกเอกสาร
                        </button>
                    </div>
                </form>
            </div>

            <!-- ตารางแสดงประวัติเอกสาร -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden p-6 space-y-4">
                <div class="px-2">
                    <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-file-invoice text-emerald-600 me-1"></i> ประวัติเอกสารทางบัญชีทั้งหมด</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">วันที่อัปโหลด</th>
                                <th class="py-3 px-4 font-semibold">โครงการ</th>
                                <th class="py-3 px-4 font-semibold">ประเภทเอกสาร</th>
                                <th class="py-3 px-4 font-semibold">ชื่อ / เลขที่เอกสาร</th>
                                <th class="py-3 px-4 font-semibold text-center">ไฟล์แนบ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($billingsList)): ?>
                                <?php foreach ($billingsList as $row): 
                                    $file_path = trim($row['file_path'] ?? '');
                                    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                    $full_url = '';
                                    if ($file_path !== '') {
                                        if (preg_match('/^http?:\/\//i', $file_path) || preg_match('/^\//', $file_path)) {
                                            $full_url = $file_path;
                                        } else {
                                            $clean = preg_replace('#^(\.\./)+#', '', $file_path);
                                            $clean = preg_replace('#^(uploads/billings/|uploads/)+#', '', $clean);
                                            $full_url = '../uploads/billings/' . $clean;
                                        }
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 text-slate-500"><?= formatDateTH($row['created_at'] ?? '') ?></td>
                                        <td class="py-3.5 px-4 text-slate-600 font-semibold"><?= htmlspecialchars($row['project_name'] ?? 'ไม่ระบุ') ?></td>
                                        <td class="py-3.5 px-4">
                                            <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-[11px] font-semibold">
                                                <?= htmlspecialchars($row['doc_type'] ?? 'เอกสาร') ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($row['doc_title'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if ($full_url): ?>
                                                <div class="flex items-center justify-center gap-2">
                                                    <?php if ($ext === 'pdf'): ?>
                                                        <a href="<?= htmlspecialchars($full_url) ?>" target="_blank" class="bg-rose-50 text-rose-700 hover:bg-rose-100 px-3 py-1.5 rounded-xl text-[11px] font-bold inline-flex items-center gap-1">
                                                            <i class="fa-solid fa-file-pdf"></i> เปิดดู PDF
                                                        </a>
                                                    <?php else: ?>
                                                        <img src="<?= htmlspecialchars($full_url) ?>" alt="เอกสาร" onclick="openImageModal('<?= htmlspecialchars($full_url) ?>')" class="w-10 h-10 object-cover rounded-xl border border-slate-200 cursor-pointer hover:opacity-80 transition-opacity shadow-xs" onerror="this.onerror=null; this.src='../Pic/456456.png';">
                                                        <button onclick="openImageModal('<?= htmlspecialchars($full_url) ?>')" class="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-2.5 py-1 rounded-xl text-[11px] font-bold inline-flex items-center gap-1">
                                                            <i class="fa-solid fa-expand"></i> ดูรูป
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 font-normal">ยังไม่มีเอกสารแนบในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Pagination -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100 text-xs text-slate-500">
                    <?php 
                        $start = ($total_rows > 0) ? ($offset + 1) : 0;
                        $end = min($offset + $limit, $total_rows);
                    ?>
                    <span>กำลังแสดง <?= $start ?> ถึง <?= $end ?> จากทั้งหมด <?= $total_rows ?> รายการ</span>
                    
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">แสดง:</span>
                            <form method="GET" action="" class="inline-block">
                                <input type="hidden" name="installation_id" value="<?= htmlspecialchars($selected_installation_id) ?>">
                                <select name="limit" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-200 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-semibold text-slate-700 cursor-pointer">
                                    <option value="5" <?= ($limit == 5) ? 'selected' : '' ?>>5 แถว</option>
                                    <option value="10" <?= ($limit == 10) ? 'selected' : '' ?>>10 แถว</option>
                                    <option value="25" <?= ($limit == 25) ? 'selected' : '' ?>>25 แถว</option>
                                    <option value="50" <?= ($limit == 50) ? 'selected' : '' ?>>50 แถว</option>
                                    <option value="100" <?= ($limit == 100) ? 'selected' : '' ?>>100 แถว</option>
                                </select>
                            </form>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="flex items-center gap-1.5">
                                <?php 
                                $prev_page = $page - 1;
                                if ($page > 1) {
                                    echo '<a href="?installation_id=' . urlencode($selected_installation_id) . '&limit=' . $limit . '&page=' . $prev_page . '" class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-left text-xs"></i></a>';
                                } else {
                                    echo '<span class="w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center text-slate-300 cursor-not-allowed"><i class="fa-solid fa-chevron-left text-xs"></i></span>';
                                }

                                for ($i = 1; $i <= $total_pages; $i++) {
                                    if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)) {
                                        if ($i == $page) {
                                            echo '<span class="w-8 h-8 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center shadow-xs text-xs">' . $i . '</span>';
                                        } else {
                                            echo '<a href="?installation_id=' . urlencode($selected_installation_id) . '&limit=' . $limit . '&page=' . $i . '" class="w-8 h-8 rounded-full border border-slate-200 text-slate-700 font-semibold flex items-center justify-center hover:bg-slate-100 transition text-xs">' . $i . '</a>';
                                        }
                                    } elseif ($i == $page - 2 || $i == $page + 2) {
                                        echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                                    }
                                }

                                $next_page = $page + 1;
                                if ($page < $total_pages) {
                                    echo '<a href="?installation_id=' . urlencode($selected_installation_id) . '&limit=' . $limit . '&page=' . $next_page . '" class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-right text-xs"></i></a>';
                                } else {
                                    echo '<span class="w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center text-slate-300 cursor-not-allowed"><i class="fa-solid fa-chevron-right text-xs"></i></span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal แสดงรูปขนาดใหญ่ -->
    <div id="imageModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-3xl max-w-lg w-full shadow-2xl space-y-4 text-center">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h4 class="font-bold text-slate-900 text-sm">ตัวอย่างรูปภาพเอกสาร</h4>
                <button onclick="closeImageModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <div class="flex justify-center max-h-[70vh] overflow-auto">
                <img id="modalReceiptImg" src="" alt="Document Preview" class="rounded-2xl max-h-[60vh] object-contain border border-slate-200">
            </div>
            <div class="flex justify-end pt-2">
                <button onclick="closeImageModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold">ปิด</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        function openImageModal(imgSrc) {
            document.getElementById('modalReceiptImg').src = imgSrc;
            let modal = document.getElementById('imageModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeImageModal() {
            let modal = document.getElementById('imageModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>