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

// =========================================================================
// ฟังก์ชันสำหรับส่งข้อความผ่าน LINE Messaging API (รองรับส่งหาหลายคน)
// =========================================================================
function sendLineMessageToMultiple($message, $recipient_ids, $access_token) {
    if (empty($recipient_ids) || !is_array($recipient_ids)) {
        return;
    }

    $url = 'https://api.line.me/v2/bot/message/push';

    foreach ($recipient_ids as $to_id) {
        // ข้ามรายการที่เว้นว่างหรือยังไม่ได้ตั้งค่า
        if (empty($to_id) || strpos($to_id, 'ใส่_User_ID') !== false) {
            continue;
        }

        $data = [
            'to' => $to_id,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
    }
}

// จัดการการอนุมัติ / ไม่อนุมัติ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $req_id = intval($_POST['req_id']);
    $action = $_POST['action_type']; // 'approve' หรือ 'reject'

    try {
        if ($action === 'approve') {
            $stmt_update = $pdo->prepare("UPDATE requisitions SET status = 'Approved' WHERE id = ?");
            $stmt_update->execute([$req_id]);

            // ดึงข้อมูลโครงการและผู้ขอเพื่อส่ง LINE แจ้งเตือน
            $stmt_info = $pdo->prepare("SELECT project_name, engineer_name FROM requisitions WHERE id = ?");
            $stmt_info->execute([$req_id]);
            $req_data = $stmt_info->fetch(PDO::FETCH_ASSOC);

            if ($req_data) {
                // Channel Access Token ของ LINE Messaging API
                $access_token = "pKse9FestSF5QdY9Hgnb7GtbcJqtXwucA50VBjLmWySwUbwM5JamQDMvlJflPQqTbibghoPRCGrGRnvehBxSooN0xjX6/3I4CF0nSeqbRgtKIBkE1mkxzPgmDKh+Io70jW7o548tAqX67bsJ3JQTBwdB04t89/1O/w1cDnyilFU=";
                
                // รายชื่อ LINE User ID ของคุณและเพื่อนๆ แอดมินทุกคน
                $admin_line_ids = [
                    "U61cff0977ce8c9de7bc8a5f949e44a43", // User ID ของทิว
                    "U0b88acf06842b3899ee341a0fce58ba1", // สามารถนำ User ID ของเพื่อนมาเพิ่มต่อในอาเรย์นี้ได้เลย
                ];

                $message = "✅ แอดมินอนุมัติใบเบิกแล้ว!\n" .
                           "📌 โครงการ: " . ($req_data['project_name'] ?? '-') . "\n" .
                           "👷 ผู้ขอ: " . ($req_data['engineer_name'] ?? '-') . "\n" .
                           "📦 สามารถตรวจสอบและดำเนินการจ่ายสินค้าได้เลยครับ";

                sendLineMessageToMultiple($message, $admin_line_ids, $access_token);
            }

            $success_msg = "อนุมัติใบเบิก ส่งข้อมูลไปยังฝั่ง User และแจ้งเตือนเข้า LINE เรียบร้อยแล้ว";
        } elseif ($action === 'reject') {
            $stmt_update = $pdo->prepare("UPDATE requisitions SET status = 'Rejected' WHERE id = ?");
            $stmt_update->execute([$req_id]);
            $success_msg = "ปฏิเสธใบเบิกเรียบร้อยแล้ว";
        }
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// นับจำนวนใบเบิกที่รอตรวจสอบ (Pending) เพื่อนำไปแสดงแจ้งเตือน
try {
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending'");
    $pending_count = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    $pending_count = 0;
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
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar สำหรับมือถือ (สไลด์เปิด-ปิด) และคอมพิวเตอร์ (แสดงปกติ) -->
    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto">
        <div class="flex-1 overflow-y-auto">
            <?php 
            if (file_exists('sidebar_admin.php')) {
                include 'sidebar_admin.php';
            }
            ?>
        </div>
    </div>

    <!-- Background Overlay เมื่อเปิด Sidebar บนมือถือ -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight truncate">ตรวจสอบและอนุมัติใบเบิกของ (Admin Approval)</h2>
                        <?php if ($pending_count > 0): ?>
                            <span class="animate-pulse bg-rose-500 text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-full">
                                ใหม่ <?= $pending_count ?> รายการ
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">ตรวจสอบรายละเอียดและอนุมัติใบเบิกเพื่อส่งต่อให้ฝั่ง User แสดงผลและแจ้งเตือนเข้า LINE</p>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-6">

                <!-- Alert แจ้งเตือนเมื่อมีใบเบิกใหม่ค้างตรวจ -->
                <?php if ($pending_count > 0 && $status_filter === 'Pending'): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-5 py-4 rounded-3xl text-xs font-semibold flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-bell text-base animate-bounce"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-amber-900">มีใบเบิกอุปกรณ์ใหม่จากวิศวกร!</p>
                                <p class="text-amber-700 text-xs">ขณะนี้มีรายการที่ยังไม่ได้ตรวจสอบจำนวน <span class="font-extrabold underline"><?= $pending_count ?> รายการ</span> กรุณาตรวจสอบและดำเนินการ</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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
                <div class="flex flex-wrap gap-2">
                    <a href="admin_requisitions.php?status=Pending" class="relative px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Pending' ? 'bg-amber-500 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-clock me-1"></i> รอตรวจสอบ (Pending)
                        <?php if ($pending_count > 0): ?>
                            <span class="absolute -top-1.5 -right-1.5 bg-rose-600 text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center font-extrabold border-2 border-white shadow-sm">
                                <?= $pending_count ?>
                            </span>
                        <?php endif; ?>
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
                        <table class="w-full text-left border-collapse text-xs min-w-[600px]">
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
                                                    <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-bold whitespace-nowrap">รอตรวจสอบ</span>
                                                <?php elseif($req['status'] === 'Approved'): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold whitespace-nowrap">อนุมัติแล้ว (ส่งไป User)</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold whitespace-nowrap">ไม่อนุมัติ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-center space-x-1 whitespace-nowrap">
                                                <!-- ปุ่มดูรายละเอียด -->
                                                <button type="button" onclick="openDetailModal(<?= $req['id'] ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl font-bold transition-all">
                                                    <i class="fa-solid fa-eye me-1"></i> ดูรายการ
                                                </button>

                                                <?php if($req['status'] === 'Pending'): ?>
                                                    <form action="admin_requisitions.php?status=Pending" method="POST" class="inline">
                                                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="action_type" value="approve">
                                                        <button type="submit" onclick="return confirm('ยืนยันอนุมัติ ส่งฟอร์มไปแสดงฝั่ง User และส่ง LINE แจ้งเตือน?')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl font-bold transition-all shadow-sm">
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
                                        <td colspan="5" class="text-center py-8 text-slate-400">ไม่พบรายการใบเบิกในสถานะนี้</td>
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
        // ฟังก์ชันควบคุมเปิด/ปิด Sidebar บนมือถือ
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function openDetailModal(reqId) {
            document.getElementById('modalReqId').innerText = '#' + reqId;
            document.getElementById('detailModal').classList.remove('hidden');
            document.getElementById('detailModal').classList.add('flex');
            document.getElementById('modalContent').innerHTML = '<div class="text-center py-6 text-slate-400">กำลังโหลดข้อมูล...</div>';

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