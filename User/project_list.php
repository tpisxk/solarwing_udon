<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'project_list.php'; 

$success_msg = '';$error_msg = '';

// จัดการอัปเดตข้อมูลโครงการผ่าน AJAX หรือ POST ในหน้าเดียวกัน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'update_project') {
    header('Content-Type: application/json; charset=utf-8');
    $proj_id = intval($_POST['project_id'] ?? 0);
    $proj_name = trim($_POST['project_name'] ?? '');
    $sys_size = trim($_POST['system_size'] ?? '');

    if ($proj_id > 0 && !empty($proj_name)) {
        try {
            $stmt_up =$pdo->prepare("UPDATE installations SET name = ?, system_size = ? WHERE id = ?");
            $stmt_up->execute([$proj_name, $sys_size,$proj_id]);
            echo json_encode(['status' => 'success', 'message' => 'อัปเดตข้อมูลโครงการเรียบร้อยแล้ว']);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล']);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกชื่อโครงการ']);
    exit();
}

// ดึงข้อมูลโครงการ พร้อมคำนวณต้นทุนอุปกรณ์สุทธิจาก stock_transactions ที่ผูกกับ installation_id
try {
    $stmt =$pdo->query("
        SELECT 
            i.*,
            SUM(CASE WHEN st.type = 'out' THEN st.quantity * p.cost_price ELSE 0 END) as total_out,
            SUM(CASE WHEN st.type = 'in' OR st.type = 'return' THEN st.quantity * p.cost_price ELSE 0 END) as total_return
        FROM installations i
        LEFT JOIN stock_transactions st ON i.id = st.installation_id
        LEFT JOIN products p ON st.product_id = p.id
        GROUP BY i.id
        ORDER BY i.id DESC
    ");
    $locationsList =$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt_fallback =$pdo->query("SELECT * FROM installations ORDER BY id DESC");
        $locationsList =$stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {$locationsList = [];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการโครงการและต้นทุนอุปกรณ์ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
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
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar -->
    <div id="sidebar" class="fixed lg:static inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out bg-white border-r border-slate-200 w-64 flex-shrink-0 flex flex-col">
        <div class="flex-1 overflow-y-auto">
            <?php include 'sidebar.php'; ?>
        </div>
    </div>

    <!-- Background Overlay -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-20 hidden lg:hidden"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-6 lg:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-base lg:text-xl font-bold text-slate-900 tracking-tight">รายการโครงการและต้นทุนอุปกรณ์สุทธิ</h2>
                </div>
            </div>
            <div class="flex items-center gap-2 lg:gap-3 shrink-0">
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 lg:px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-10 space-y-6">

            <!-- Alert แจ้งเตือนสถานะ -->
            <div id="alertBox" class="hidden px-6 py-4 rounded-2xl text-sm font-medium items-center justify-between shadow-xs"></div>
            
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden p-4 lg:p-6 space-y-4">
                <div class="flex items-center justify-between px-2">
                    <h3 class="text-base font-bold text-slate-900">ตารางสรุปโครงการและต้นทุนทั้งหมด</h3>
                    <span class="text-xs text-slate-400 font-medium">จำนวนทั้งหมด: <?= count($locationsList) ?> โครงการ</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">ชื่อโครงการ / หน้างาน</th>
                                <th class="py-3 px-4 font-semibold">ขนาดติดตั้ง</th>
                                <th class="py-3 px-4 font-semibold">รายละเอียด / ที่อยู่</th>
                                <th class="py-3 px-4 font-semibold text-center">แผนที่หน้างาน</th>
                                <th class="py-3 px-4 font-semibold text-right">มูลค่าเบิก (+) / รับคืน (-)</th>
                                <th class="py-3 px-4 font-semibold text-right">ต้นทุนอุปกรณ์สุทธิ</th>
                                <th class="py-3 px-4 font-semibold text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($locationsList)): ?>
                                <?php foreach ($locationsList as$loc): 
                                    $total_out =$loc['total_out'] ?? 0;
                                    $total_return =$loc['total_return'] ?? 0;
                                    $net_cost = $total_out -$total_return;
                                    $map_url =$loc['map_url'] ?? '';
                                    $sys_size = trim($loc['system_size'] ?? '');
                                    $proj_name =$loc['name'] ?? $loc['project_name'] ?? '-';$raw_desc = $loc['description'] ?? '';$is_json = (json_decode($raw_desc, true) !== null);$display_desc = $is_json ? '-' : $raw_desc;
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 font-bold text-slate-900">
                                            <?= htmlspecialchars($proj_name) ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if (!empty($sys_size)): ?>
                                                <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-lg text-xs font-bold border border-emerald-200/80 inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-solar-panel"></i> <?= htmlspecialchars($sys_size) ?><?= (stripos($sys_size, 'kw') === false) ? ' kWp' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($display_desc !== '' ?$display_desc : '-') ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if (!empty($map_url)): ?>
                                                <a href="<?= htmlspecialchars($map_url) ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-sky-50 text-sky-700 border border-sky-200/60 px-3 py-1.5 rounded-xl font-semibold hover:bg-sky-100 transition-all">
                                                    <i class="fa-solid fa-map-location-dot"></i> เปิดแผนที่
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-300">- ไม่มีพิกัด -</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-slate-600">
                                            <div>เบิก: <?= number_format($total_out, 2) ?></div>
                                            <div class="text-emerald-600">คืน: -<?= number_format($total_return, 2) ?></div>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-bold text-indigo-600 text-sm">
                                            <?= number_format($net_cost, 2) ?> บาท
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <!-- ปุ่มเปิด Modal แก้ไขชื่อโครงการ -->
                                            <button onclick="openEditModal('<?= $loc['id'] ?>', '<?= htmlspecialchars($proj_name, ENT_QUOTES) ?>', '<?= htmlspecialchars($sys_size, ENT_QUOTES) ?>')" class="bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 p-2 rounded-xl transition-all inline-block" title="แก้ไขชื่อโครงการ">
                                                <i class="fa-solid fa-pen text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-normal">ยังไม่มีข้อมูลโครงการในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal สำหรับแก้ไขชื่อโครงการ -->
    <div id="editModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 md:p-8 rounded-3xl w-full max-w-md shadow-xl border border-slate-200 space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">แก้ไขชื่อโครงการ / ขนาดติดตั้ง</h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form id="editForm" onsubmit="submitEditProject(event)" class="space-y-4">
                <input type="hidden" id="editProjectId" name="project_id">

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-600">ชื่อโครงการ / หน้างาน</label>
                    <input type="text" id="editProjectName" name="project_name" required class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-bold">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-600">ขนาดติดตั้ง (เช่น 5kW หรือ 10 kWp)</label>
                    <input type="text" id="editSystemSize" name="system_size" class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600">
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-all">บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function openEditModal(id, name, size) {
            document.getElementById('editProjectId').value = id;
            document.getElementById('editProjectName').value = name;
            document.getElementById('editSystemSize').value = size;
            let modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditModal() {
            let modal = document.getElementById('editModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function submitEditProject(event) {
            event.preventDefault();
            let formData = new FormData(document.getElementById('editForm'));
            formData.append('action', 'update_project');

            fetch('project_list.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeEditModal();
                let alertBox = document.getElementById('alertBox');
                alertBox.classList.remove('hidden');
                alertBox.classList.add('flex');

                if (data.status === 'success') {
                    alertBox.className = 'flex px-6 py-4 rounded-2xl text-sm font-medium items-center justify-between shadow-xs bg-emerald-50 text-emerald-800 border border-emerald-200';
                    alertBox.innerHTML = `<span>✨ ${data.message}</span>`;
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alertBox.className = 'flex px-6 py-4 rounded-2xl text-sm font-medium items-center justify-between shadow-xs bg-rose-50 text-rose-800 border border-rose-200';
                    alertBox.innerHTML = `<span>❌ ${data.message}</span>`;
                }
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเพื่อบันทึกข้อมูล');
            });
        }
    </script>
</body>
</html>