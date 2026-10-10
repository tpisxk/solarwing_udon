<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'admin_requisitions';
$success_msg = '';
$error_msg = '';

// จัดการการอนุมัติ / ไม่อนุมัติ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $req_id = intval($_POST['req_id']);
    $action = $_POST['action_type']; // 'approve' หรือ 'reject'

    try {
        if ($action === 'approve') {
            $stmt_update = $pdo->prepare("UPDATE requisitions SET status = 'Approved' WHERE id = ?");
            $stmt_update->execute([$req_id]);
            $success_msg = "อนุมัติใบเบิกและส่งข้อมูลไปยังฝั่ง User เรียบร้อยแล้ว (Stock คงเดิม)";
        } elseif ($action === 'reject') {
            $stmt_update = $pdo->prepare("UPDATE requisitions SET status = 'Rejected' WHERE id = ?");
            $stmt_update->execute([$req_id]);
            $success_msg = "ปฏิเสธใบเบิกเรียบร้อยแล้ว";
        }
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ดึงรายการใบเบิกตามสถานะ
$status_filter = $_GET['status'] ?? 'Pending';
try {
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE status = ? ORDER BY id DESC");
    $stmt->execute([$status_filter]);
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requisitions = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบใบเบิกของ - Admin SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'sans-serif'] },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <?php 
    if (file_exists('sidebar_admin.php')) {
        include 'sidebar_admin.php';
    }
    ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">ตรวจสอบและอนุมัติใบเบิกของ (Admin Approval)</h2>
                <p class="text-sm text-slate-500 mt-0.5">ตรวจสอบรายละเอียดและอนุมัติใบเบิกเพื่อส่งต่อให้ฝั่ง User แสดงผล</p>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-6xl mx-auto space-y-6">

                <!-- Alert Messages -->
                <?php if (!empty($success_msg)): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-semibold">
                        <?= $success_msg ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-semibold">
                        <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Tabs -->
                <div class="flex gap-2">
                    <a href="admin_requisitions.php?status=Pending" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Pending' ? 'bg-amber-500 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-clock me-1"></i> รอตรวจสอบ (Pending)
                    </a>
                    <a href="admin_requisitions.php?status=Approved" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Approved' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-check-circle me-1"></i> อนุมัติแล้ว / แสดงฝั่ง User (Approved)
                    </a>
                    <a href="admin_requisitions.php?status=Rejected" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Rejected' ? 'bg-rose-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-times-circle me-1"></i> ไม่อนุมัติ (Rejected)
                    </a>
                </div>

                <!-- Table Section -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase">
                                    
                                    <th class="py-3 px-4">ชื่อโครงการ / หน้างาน</th>
                                    <th class="py-3 px-4">วันที่เบิก</th>
                                    <th class="py-3 px-4">วิศวกรผู้ขอ</th>
                                    <th class="py-3 px-4 text-center">สถานะ</th>
                                    <th class="py-3 px-4 text-center">จัดการ / รายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($requisitions)): ?>
                                    <?php foreach ($requisitions as $req): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            
                                            <td class="py-3 px-4 font-semibold text-slate-900"><?= htmlspecialchars($req['project_name'] ?? '-') ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($req['req_date'] ?? '-') ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($req['engineer_name'] ?? '-') ?></td>
                                            <td class="py-3 px-4 text-center">
                                                <?php if($req['status'] === 'Pending'): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-bold">รอตรวจสอบ</span>
                                                <?php elseif($req['status'] === 'Approved'): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">อนุมัติแล้ว (ส่งไป User)</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold">ไม่อนุมัติ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-center space-x-1">
                                                <!-- ปุ่มดูรายละเอียด -->
                                                <button type="button" onclick="openDetailModal(<?= $req['id'] ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl font-bold transition-all">
                                                    <i class="fa-solid fa-eye me-1"></i> ดูรายการ
                                                </button>

                                                <?php if($req['status'] === 'Pending'): ?>
                                                    <form action="admin_requisitions.php?status=Pending" method="POST" class="inline">
                                                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="action_type" value="approve">
                                                        <button type="submit" onclick="return confirm('ยืนยันอนุมัติและส่งฟอร์มไปแสดงฝั่ง User?')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                                                            <i class="fa-solid fa-check me-1"></i> อนุมัติ
                                                        </button>
                                                    </form>

                                                    <form action="admin_requisitions.php?status=Pending" method="POST" class="inline">
                                                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="action_type" value="reject">
                                                        <button type="submit" onclick="return confirm('ต้องการปฏิเสธรายการนี้ใช่หรือไม่?')" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-1.5 rounded-xl font-bold transition-all">
                                                            <i class="fa-solid fa-xmark me-1"></i> ปฏิเสธ
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-8 text-slate-400">ไม่พบรายการใบเบิกในสถานะนี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal สำหรับแสดงรายละเอียดสินค้าในใบเบิก -->
    <div id="detailModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-xl">
            <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                <h3 class="font-bold text-slate-900 text-base"><i class="fa-solid fa-file-lines text-emerald-600 me-2"></i> รายละเอียดสินค้าในใบเบิก <span id="modalReqId"></span></h3>
                <button type="button" onclick="closeDetailModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="modalContent" class="space-y-3 max-h-96 overflow-y-auto">
                <div class="text-center py-6 text-slate-400">กำลังโหลดข้อมูล...</div>
            </div>
            <div class="flex justify-end pt-2 border-t border-slate-100">
                <button type="button" onclick="closeDetailModal()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl font-bold text-xs">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        function openDetailModal(reqId) {
            document.getElementById('modalReqId').innerText = '#' + reqId;
            document.getElementById('detailModal').classList.remove('hidden');
            document.getElementById('detailModal').classList.add('flex');
            document.getElementById('modalContent').innerHTML = '<div class="text-center py-6 text-slate-400">กำลังโหลดข้อมูล...</div>';

            // ดึงข้อมูลรายละเอียดผ่าน AJAX (สร้างไฟล์ get_requisition_detail.php หรือดึงผ่าน API ในตัว)
            fetch('get_requisition_detail.php?id=' + reqId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = '<div class="text-center py-6 text-rose-500">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                });
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.remove('flex');
            document.getElementById('detailModal').classList.add('hidden');
        }
    </script>
</body>
</html>