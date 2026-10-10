<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'user_requisitions';

// ดึงเฉพาะใบเบิกที่มีสถานะเป็น 'Approved' (ที่แอดมินอนุมัติส่งต่อมาให้ User)
try {
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE status = 'Approved' ORDER BY id DESC");
    $stmt->execute();
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
    <title>รายการใบเบิกที่อนุมัติแล้ว - SolarStock Pro</title>
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
    // หากมี Sidebar ฝั่ง User หรือสามารถปรับเปลี่ยนได้ตามโครงสร้างโปรเจกต์ของคุณ
    if (file_exists('sidebar.php')) {
        include 'sidebar.php';
    } elseif (file_exists('sidebar.php')) {
        include 'sidebar.php';
    }
    ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">รายการใบเบิกที่อนุมัติแล้ว</h2>
                <p class="text-sm text-slate-500 mt-0.5">ตรวจสอบรายการใบเบิกอุปกรณ์และหน้างานที่ได้รับการอนุมัติจากผู้ดูแลระบบ</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-6xl mx-auto space-y-6">

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
                                    <th class="py-3 px-4 text-center">รายละเอียด</th>
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
                                                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">อนุมัติแล้ว</span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" onclick="openDetailModal(<?= $req['id'] ?>)" class="bg-brandGreen hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                                                    <i class="fa-solid fa-eye me-1"></i> ดูรายการสินค้า
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-8 text-slate-400">ยังไม่มีรายการใบเบิกที่ได้รับการอนุมัติในขณะนี้</td>
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
                <h3 class="font-bold text-slate-900 text-base"><i class="fa-solid fa-file-lines text-emerald-600 me-2"></i> รายละเอียดสินค้าใบเบิก <span id="modalReqId"></span></h3>
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

            // เรียกใช้งานไฟล์ดึงข้อมูลรายการสินค้าเดียวกันกับฝั่งแอดมิน
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