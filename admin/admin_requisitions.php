<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'admin_requisitions';
$success_msg = '';$error_msg = '';

function sendLineMessageToMultiple($message, $recipient_ids,$access_token) {
    if (empty($recipient_ids) or !is_array($recipient_ids)) return;
    $url = 'https://api.line.me/v2/bot/message/push';
    
    foreach ($recipient_ids as$to_id) {
        if (empty($to_id) or strpos($to_id, 'ใส่_User_ID') !== false) continue;
        $data = ['to' =>$to_id, 'messages' => [['type' => 'text', 'text' => $message]]];$options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                             "Authorization: Bearer " . $access_token . "\r\n",
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ];
        
        $context = stream_context_create($options);
        @file_get_contents($url, false,$context);
    }
}

// จัดการการอนุมัติ / ไม่อนุมัติ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $req_id = intval($_POST['req_id']);
    $action =$_POST['action_type'];

    try {
        if ($action === 'approve') {
            $stmt_update =$pdo->prepare("UPDATE requisitions SET status = 'Approved' WHERE id = ?");
            $stmt_update->execute([$req_id]);

            $stmt_info =$pdo->prepare("SELECT project_name, engineer_name FROM requisitions WHERE id = ?");
            $stmt_info->execute([$req_id]);
            $req_data =$stmt_info->fetch(PDO::FETCH_ASSOC);

            if ($req_data) {$access_token = "pKse9FestSF5QdY9Hgnb7GtbcJqtXwucA50VBjLmWySwUbwM5JamQDMvlJflPQqTbibghoPRCGrGRnvehBxSooN0xjX6/3I4CF0nSeqbRgtKIBkE1mkxzPgmDKh+Io70jW7o548tAqX67bsJ3JQTBwdB04t89/1O/w1cDnyilFU=";
                $admin_line_ids = [
                    "U61cff0977ce8c9de7bc8a5f949e44a43",
                    "U0b88acf06842b3899ee341a0fce58ba1",
                ];

                $message = "✅ แอดมินอนุมัติใบเบิกเรียบร้อยแล้ว!\n" .
                           "📌 โครงการ: " . ($req_data['project_name'] ?? '-') . "\n" .
                           "👷 ผู้ขอ: " . ($req_data['engineer_name'] ?? '-') . "\n" .
                           "📦 กรุณาให้ผู้ใช้งานมารับสินค้าและกดยืนยันการรับเพื่อตัดสต็อกครับ";

                sendLineMessageToMultiple($message, $admin_line_ids,$access_token);
            }

            $success_msg = "อนุมัติใบเบิกเรียบร้อยแล้ว (รอผู้ใช้งานมารับสินค้าและกดยืนยันรับ)";
        } elseif ($action === 'reject') {
            $stmt_update =$pdo->prepare("UPDATE requisitions SET status = 'Rejected' WHERE id = ?");
            $stmt_update->execute([$req_id]);$success_msg = "ปฏิเสธใบเบิกเรียบร้อยแล้ว";
        }
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

try {
    $stmt_count =$pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending'");
    $pending_count =$stmt_count->fetchColumn();
} catch (PDOException $e) {$pending_count = 0;
}

$status_filter =$_GET['status'] ?? 'Pending';
try {
    $stmt =$pdo->prepare("SELECT * FROM requisitions WHERE status = ? ORDER BY id DESC");
    $stmt->execute([$status_filter]);
    $requisitions =$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$requisitions = [];
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
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto">
        <div class="flex-1 overflow-y-auto">
            <?php if (file_exists('sidebar_admin.php')) { include 'sidebar_admin.php'; } ?>
        </div>
    </div>
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
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
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">อนุมัติใบเบิกเพื่อให้ผู้ใช้งานมาลงชื่อรับและยืนยันการตัดสต็อก หากสินค้าหมดสามารถกดไปหน้าจัดซื้อได้ทันที</p>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-6">

                <?php if (!empty($success_msg)): ?>
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: '<?= $success_msg ?>',
                            confirmButtonText: 'ตกลง',
                            confirmButtonColor: '#059669',
                            timer: 3000,
                            timerProgressBar: true
                        });
                    </script>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: '<?= $error_msg ?>',
                            confirmButtonText: 'ตกลง',
                            confirmButtonColor: '#e11d48'
                        });
                    </script>
                <?php endif; ?>

                <div class="flex flex-wrap gap-2">
                    <a href="admin_requisitions.php?status=Pending" class="relative px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Pending' ? 'bg-amber-500 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-clock me-1"></i> รอตรวจสอบ (Pending)
                    </a>
                    <a href="admin_requisitions.php?status=Approved" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Approved' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-check-circle me-1"></i> อนุมัติแล้ว/รอรับของ (Approved)
                    </a>
                    <a href="admin_requisitions.php?status=Completed" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Completed' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-boxes-packing me-1"></i> รับของและตัดสต็อกแล้ว (Completed)
                    </a>
                    <a href="admin_requisitions.php?status=Rejected" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $status_filter === 'Rejected' ? 'bg-rose-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-times-circle me-1"></i> ไม่อนุมัติ (Rejected)
                    </a>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[700px]">
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
                                    <?php foreach ($requisitions as$req): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-3 px-4 font-semibold text-slate-900"><?= htmlspecialchars($req['project_name'] ?? '-') ?></td>
                                            <td class="py-3 px-4">
                                                <?= (!empty($req['req_date']) && $req['req_date'] !== '-') ? date('d/m/Y', strtotime($req['req_date'])) : '-' ?>
                                            </td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($req['engineer_name'] ?? '-') ?></td>
                                            <td class="py-3 px-4 text-center">
                                                <?php if($req['status'] === 'Pending'): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-bold">รอตรวจสอบ</span>
                                                <?php elseif($req['status'] === 'Approved'): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 font-bold">อนุมัติแล้ว (รอรับของ)</span>
                                                <?php elseif($req['status'] === 'Completed'): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">รับของและตัดสต็อกแล้ว</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold">ไม่อนุมัติ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-center space-x-1 whitespace-nowrap">
                                                <button type="button" onclick="openDetailModal(<?= $req['id'] ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl font-bold transition-all">
                                                    <i class="fa-solid fa-eye me-1"></i> ดูรายการ
                                                </button>

                                                <?php if($req['status'] === 'Pending'): ?>
                                                    <a href="admin_procurement.php?ref_req=<?= $req['id'] ?>&project=<?= urlencode($req['project_name']) ?>" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-1.5 rounded-xl font-bold transition-all inline-flex items-center">
                                                        <i class="fa-solid fa-cart-shopping me-1"></i> สินค้าหมด/จัดซื้อ
                                                    </a>

                                                    <form id="approveForm-<?= $req['id'] ?>" action="admin_requisitions.php?status=Pending" method="POST" class="inline">
                                                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="action_type" value="approve">
                                                        <button type="button" onclick="confirmAction(<?= $req['id'] ?>, 'approve')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                                                            <i class="fa-solid fa-check me-1"></i> อนุมัติ
                                                        </button>
                                                    </form>

                                                    <form id="rejectForm-<?= $req['id'] ?>" action="admin_requisitions.php?status=Pending" method="POST" class="inline">
                                                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="action_type" value="reject">
                                                        <button type="button" onclick="confirmAction(<?= $req['id'] ?>, 'reject')" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-1.5 rounded-xl font-bold transition-all">
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
            <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                <div id="modalProcurementBtn"></div>
                <button type="button" onclick="closeDetailModal()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl font-bold text-xs">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function confirmAction(reqId, type) {
            let isApprove = type === 'approve';
            Swal.fire({
                title: isApprove ? 'ยืนยันอนุมัติใบเบิก?' : 'ต้องการปฏิเสธรายการนี้?',
                text: isApprove ? "ระบบจะทำการอัปเดตสถานะเป็นอนุมัติและแจ้งเตือนไปยังไลน์กลุ่ม" : "รายการนี้จะถูกเปลี่ยนสถานะเป็นไม่อนุมัติ",
                icon: isApprove ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: isApprove ? '#059669' : '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: isApprove ? 'ยืนยัน, อนุมัติ' : 'ใช่, ปฏิเสธ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(isApprove ? 'approveForm-' + reqId : 'rejectForm-' + reqId).submit();
                }
            });
        }

        function openDetailModal(reqId) {
            document.getElementById('modalReqId').innerText = '#' + reqId;
            document.getElementById('detailModal').classList.remove('hidden');
            document.getElementById('detailModal').classList.add('flex');
            document.getElementById('modalContent').innerHTML = '<div class="text-center py-6 text-slate-400">กำลังโหลดข้อมูล...</div>';
            document.getElementById('modalProcurementBtn').innerHTML = '';

            fetch('get_requisition_detail.php?id=' + reqId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                    document.getElementById('modalProcurementBtn').innerHTML = `
                        <a href="admin_procurement.php?ref_req=` + reqId + `" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-xl font-bold text-xs transition inline-flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-cart-shopping"></i> ไปหน้าจัดซื้อ (สินค้าหมด/ต้องสั่งเพิ่ม)
                        </a>
                    `;
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