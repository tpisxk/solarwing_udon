<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'work_progress';
$id = intval($_GET['id'] ?? 0);
$work = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM installations WHERE id = ?");
    $stmt->execute([$id]);
    $work = $stmt->fetch(PDO::FETCH_ASSOC);
}

// จัดการคำขอ POST ทั้งหมด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $id > 0) {
    $action = $_POST['action'];
    try {
        if ($action === 'save_all_progress') {
            $pdo->beginTransaction();
            foreach ($_POST['plan_progress'] ?? [] as $t_id => $p_val) {
                $pdo->prepare("UPDATE installation_tasks SET plan_progress = ?, progress = ?, remark = ? WHERE id = ? AND installation_id = ?")
                    ->execute([max(0, min(100, $p_val)), max(0, min(100, $_POST['progress'][$t_id] ?? 0)), trim($_POST['remark'][$t_id] ?? ''), intval($t_id), $id]);
            }
            foreach ($_POST['new_task_name'] ?? [] as $idx => $n_name) {
                if (trim($n_name) !== '') {
                    $pdo->prepare("INSERT INTO installation_tasks (installation_id, task_name, plan_progress, progress, start_date, end_date, duration_days, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$id, trim($n_name), max(0, min(100, $_POST['new_plan_progress'][$idx] ?? 0)), max(0, min(100, $_POST['new_progress'][$idx] ?? 0)), $_POST['new_start_date'][$idx] ?? date('Y-m-d'), $_POST['new_end_date'][$idx] ?? date('Y-m-d'), max(1, $_POST['new_duration_days'][$idx] ?? 1), trim($_POST['new_remark'][$idx] ?? '')]);
                }
            }
            $pdo->commit();
        } elseif ($action === 'inline_edit_task' && intval($_POST['task_id']) > 0) {
            $pdo->prepare("UPDATE installation_tasks SET task_name = ?, plan_progress = ?, progress = ?, start_date = ?, end_date = ?, duration_days = ?, remark = ? WHERE id = ? AND installation_id = ?")
                ->execute([trim($_POST['task_name']), intval($_POST['task_plan']), intval($_POST['task_progress']), $_POST['task_start'], $_POST['task_end'], intval($_POST['task_day']), trim($_POST['task_remark']), intval($_POST['task_id']), $id]);
        }
        header("Location: installation_detail.php?id={$id}&status=success");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: installation_detail.php?id={$id}&status=error");
        exit();
    }
}

// ลบงานย่อย
if (isset($_GET['delete_task_id']) && $id > 0) {
    $pdo->prepare("DELETE FROM installation_tasks WHERE id = ? AND installation_id = ?")->execute([intval($_GET['delete_task_id']), $id]);
    header("Location: installation_detail.php?id={$id}&status=deleted");
    exit();
}

$sub_tasks = [];$task_names_js = $task_plans_js =$task_actuals_js = [];
$avg_actual =$avg_plan = 0;

if ($id > 0) {
    $stmt =$pdo->prepare("SELECT * FROM installation_tasks WHERE installation_id = ? ORDER BY start_date ASC, id ASC");
    $stmt->execute([$id]);
    $sub_tasks =$stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($sub_tasks) > 0) {
        $tot_act =$tot_pln = 0;
        foreach ($sub_tasks as$t) {
            $task_names_js[] =$t['task_name'];
            $task_plans_js[] = intval($t['plan_progress']);
            $task_actuals_js[] = intval($t['progress']);
            $tot_act +=$t['progress'];
            $tot_pln +=$t['plan_progress'];
        }
        $avg_actual = round($tot_act / count($sub_tasks), 1);$avg_plan = round($tot_pln / count($sub_tasks), 1);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Plan & Daily Report - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'sans-serif'], sarabun: ['Sarabun', 'sans-serif'] },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        .font-sarabun { font-family: 'Sarabun', sans-serif !important; }
        @media print {
            body * { visibility: hidden; }
            #printableDailyReport, #printableDailyReport * { visibility: visible; }
            #printableDailyReport { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-slate-100/80 text-slate-800 font-sans h-screen flex overflow-hidden">
    
    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform">
        <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden w-full bg-slate-100/50">
        <!-- Header -->
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 p-2 rounded-xl bg-slate-50 border border-slate-200"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h2 class="text-base md:text-lg font-bold text-slate-900 tracking-tight">แผนงานและความคืบหน้า (Progress Plan)</h2>
                    <p class="text-xs text-slate-500 font-medium">โครงการ: <span class="text-emerald-600 font-semibold"><?= htmlspecialchars($work['name'] ?? '-') ?></span></p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button onclick="openModal('dailyReportModal')" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 text-white px-4 py-2.5 rounded-2xl text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-file-pdf"></i> Daily Report PDF
                </button>
                <a href="work_progress.php" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-1.5 shadow-xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i> กลับ
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- ตาราง Task ฝั่งซ้าย -->
                <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 flex flex-col justify-between">
                    <form action="" method="POST" class="flex flex-col justify-between h-full space-y-4">
                        <input type="hidden" name="action" value="save_all_progress">
                        <div>
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs font-bold"><i class="fa-solid fa-list-check"></i></div>
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">ตารางงาน (Task Progress Plan)</h3>
                                </div>
                                <div class="flex gap-2">
                                    <?php if (!empty($sub_tasks)): ?>
                                        <button type="button" onclick="copyData('copied_all_tasks', <?= htmlspecialchars(json_encode($sub_tasks), ENT_QUOTES) ?>)" class="text-[11px] bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold px-3 py-1.5 rounded-xl border border-indigo-100 transition-all flex items-center gap-1.5"><i class="fa-solid fa-copy"></i> คัดลอกทั้งหมด</button>
                                    <?php endif; ?>
                                    <button type="button" id="pasteBtn" onclick="pasteData()" class="hidden text-[11px] bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold px-3 py-1.5 rounded-xl border border-purple-100 transition-all items-center gap-1.5"><i class="fa-solid fa-paste"></i> วาง Task</button>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto border border-slate-100 rounded-2xl shadow-xs">
                                <table class="w-full text-left text-xs min-w-[620px]">
                                    <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider border-b border-slate-100">
                                        <tr>
                                            <th class="p-3 text-center w-10">No.</th>
                                            <th class="p-3">Task Name</th>
                                            <th class="p-2 text-center w-20">Plan (%)</th>
                                            <th class="p-2 text-center w-20">Actual (%)</th>
                                            <th class="p-3 text-center w-28">Start-End</th>
                                            <th class="p-3 text-center w-24">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                        <?php if (empty($sub_tasks)): ?>
                                            <tr><td colspan="6" class="p-8 text-center text-slate-400">ยังไม่มีรายการงานย่อย</td></tr>
                                        <?php else: foreach ($sub_tasks as $i =>$t): ?>
                                        <tr class="hover:bg-slate-50/60 transition-all">
                                            <td class="p-3 text-center font-bold text-slate-400"><?= $i + 1 ?></td>
                                            <td class="p-3 font-semibold text-slate-900"><?= htmlspecialchars($t['task_name']) ?></td>
                                            <td class="p-2 text-center"><input type="number" min="0" max="100" name="plan_progress[<?= $t['id'] ?>]" value="<?= $t['plan_progress'] ?>" class="w-16 p-1.5 text-center border border-blue-200 bg-blue-50/30 text-blue-700 font-bold rounded-xl focus:outline-none text-xs"></td>
                                            <td class="p-2 text-center"><input type="number" min="0" max="100" name="progress[<?= $t['id'] ?>]" value="<?= $t['progress'] ?>" class="w-16 p-1.5 text-center border border-emerald-200 bg-emerald-50/30 text-emerald-700 font-bold rounded-xl focus:outline-none text-xs"></td>
                                            <td class="p-3 text-center text-[10px] text-slate-500"><?= $t['start_date'] ?><br><?=$t['end_date'] ?></td>
                                            <td class="p-3 text-center">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button type="button" onclick="copyData('copied_single_task', <?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)" class="w-7 h-7 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center transition-all" title="คัดลอก"><i class="fa-solid fa-copy text-[11px]"></i></button>
                                                    <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)" class="w-7 h-7 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center transition-all" title="แก้ไข"><i class="fa-solid fa-pen text-[11px]"></i></button>
                                                    <a href="?id=<?= $id ?>&delete_task_id=<?= $t['id'] ?>" onclick="return confirm('ยืนยันลบงานนี้?')" class="w-7 h-7 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg flex items-center justify-center transition-all" title="ลบ"><i class="fa-solid fa-trash text-[11px]"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                        <tbody id="newTaskBody"></tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <button type="button" onclick="addRow()" class="bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-2.5 rounded-2xl text-xs font-bold transition-all flex items-center gap-1.5"><i class="fa-solid fa-plus text-[11px]"></i> เพิ่มแถว Task ใหม่</button>
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-2xl text-xs font-bold shadow-md shadow-emerald-600/20 transition-all flex items-center gap-2"><i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูลทั้งหมด</button>
                        </div>
                    </form>
                </div>

                <!-- กราฟเปรียบเทียบ Plan vs Actual ฝั่งขวา -->
                <div class="lg:col-span-5 bg-slate-900 text-white rounded-3xl p-6 shadow-sm flex flex-col justify-between border border-slate-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-slate-800 text-emerald-400 flex items-center justify-center text-xs font-bold border border-slate-700"><i class="fa-solid fa-chart-line"></i></div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-100">กราฟเปรียบเทียบ</h3>
                                <p class="text-[10px] text-slate-400">Plan vs Actual Progress</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] bg-slate-800 px-2.5 py-1 rounded-xl border border-slate-700">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span> Plan 
                            <span class="w-2 h-2 rounded-full bg-rose-500 ml-1"></span> Actual
                        </div>
                    </div>
                    <div class="h-72 md:h-80 relative flex-1 w-full">
                        <canvas id="taskLineChart"></canvas>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal Daily Report PDF -->
    <div id="dailyReportModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-8 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold"><i class="fa-solid fa-file-pdf"></i></div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">ตัวอย่างรายงานประจำวัน (Daily Report)</h3>
                        <p class="text-[11px] text-slate-500">รูปแบบฟอนต์ TH Sarabun สำหรับพิมพ์หรือบันทึก PDF</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all"><i class="fa-solid fa-print"></i> พิมพ์ / PDF</button>
                    <button onclick="closeModal('dailyReportModal')" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center justify-center">✕</button>
                </div>
            </div>

            <div id="printableDailyReport" class="p-6 font-sarabun text-base space-y-4 bg-white text-slate-900">
                <div class="flex justify-between items-center border-b-2 border-slate-800 pb-3">
                    <div>
                        <h1 class="text-xl font-bold uppercase">SOLARSTOCK PRO - DAILY REPORT</h1>
                        <p class="text-xs text-slate-600">โครงการ: <?= htmlspecialchars($work['name'] ?? '-') ?></p>
                    </div>
                    <div class="text-right text-xs">
                        <p><b>วันที่:</b> <?= date('d/m/Y') ?></p>
                        <p><b>Plan รวม:</b> <span class="text-blue-700 font-bold"><?= $avg_plan ?>%</span> | <b>Actual รวม:</b> <span class="text-emerald-700 font-bold"><?= $avg_actual ?>%</span></p>
                    </div>
                </div>
                
                <table class="w-full border-collapse border border-slate-300 text-sm">
                    <thead>
                        <tr class="bg-slate-800 text-white">
                            <th class="p-2 border border-slate-700 text-center w-12">ลำดับ</th>
                            <th class="p-2 border border-slate-700">รายการงาน (Task)</th>
                            <th class="p-2 border border-slate-700 text-center w-20">Plan</th>
                            <th class="p-2 border border-slate-700 text-center w-20">Actual</th>
                            <th class="p-2 border border-slate-700 text-center w-32">ช่วงเวลา</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sub_tasks as $i =>$t): ?>
                        <tr>
                            <td class="p-2 border text-center font-bold"><?= $i + 1 ?></td>
                            <td class="p-2 border font-semibold"><?= htmlspecialchars($t['task_name']) ?></td>
                            <td class="p-2 border text-center font-bold text-blue-700"><?= $t['plan_progress'] ?>%</td>
                            <td class="p-2 border text-center font-bold text-emerald-700"><?= $t['progress'] ?>%</td>
                            <td class="p-2 border text-center text-xs"><?= $t['start_date'] ?> ถึง <?= $t['end_date'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Edit Task -->
    <div id="editTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-900">แก้ไขรายละเอียด Task</h3>
                <button onclick="closeModal('editTaskModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form action="" method="POST" class="space-y-3">
                <input type="hidden" name="action" value="inline_edit_task">
                <input type="hidden" name="task_id" id="mId">
                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-slate-600 uppercase">ชื่องาน</label>
                    <input type="text" name="task_name" id="mName" required class="w-full p-2.5 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-emerald-600">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1"><label class="text-[11px] font-bold text-blue-700 uppercase">Plan (%)</label><input type="number" name="task_plan" id="mPlan" class="w-full p-2.5 border border-blue-200 rounded-xl text-xs font-bold text-blue-700"></div>
                    <div class="space-y-1"><label class="text-[11px] font-bold text-emerald-700 uppercase">Actual (%)</label><input type="number" name="task_progress" id="mAct" class="w-full p-2.5 border border-emerald-200 rounded-xl text-xs font-bold text-emerald-700"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1"><label class="text-[11px] font-bold text-slate-600 uppercase">วันที่เริ่ม</label><input type="date" name="task_start" id="mStart" class="w-full p-2.5 border border-slate-200 rounded-xl text-xs"></div>
                    <div class="space-y-1"><label class="text-[11px] font-bold text-slate-600 uppercase">วันที่สิ้นสุด</label><input type="date" name="task_end" id="mEnd" class="w-full p-2.5 border border-slate-200 rounded-xl text-xs"></div>
                </div>
                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-slate-600 uppercase">จำนวนวัน</label>
                    <input type="number" name="task_day" id="mDay" class="w-full p-2.5 border border-slate-200 rounded-xl text-xs">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal('editTaskModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition-all">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-emerald-600/20 transition-all">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const toggleSidebar = () => document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
        const openModal = id => document.getElementById(id).classList.remove('hidden');
        const closeModal = id => document.getElementById(id).classList.add('hidden');

        // กราฟ Chart.js
        if (<?= count($task_names_js) ?> > 0) {
            new Chart(document.getElementById('taskLineChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($task_names_js) ?>,
                    datasets: [
                        { label: 'Plan', data: <?= json_encode($task_plans_js) ?>, borderColor: '#3b82f6', fill: true, backgroundColor: 'rgba(59,130,246,0.08)', borderWidth: 2.5, tension: 0.3 },
                        { label: 'Actual', data: <?= json_encode($task_actuals_js) ?>, borderColor: '#f43f5e', fill: true, backgroundColor: 'rgba(244,63,94,0.08)', borderWidth: 2.5, tension: 0.3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 100, ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } }
                    }
                }
            });
        }

        let rIdx = 0;
        function addRow(data = {}) {
            document.getElementById('newTaskBody').insertAdjacentHTML('beforeend', `
                <tr class="hover:bg-slate-50/60 transition-all">
                    <td class="p-3 text-center text-emerald-600 font-bold"><i class="fa-solid fa-plus text-xs"></i></td>
                    <td class="p-2"><input type="text" name="new_task_name[${rIdx}]" value="${data.task_name || ''}" placeholder="ชื่องานใหม่..." required class="w-full p-2 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-emerald-600"></td>
                    <td class="p-2 text-center"><input type="number" min="0" max="100" name="new_plan_progress[${rIdx}]" value="${data.plan_progress || 0}" class="w-16 p-1.5 text-center border border-blue-200 bg-blue-50/30 text-blue-700 font-bold rounded-xl text-xs"></td>
                    <td class="p-2 text-center"><input type="number" min="0" max="100" name="new_progress[${rIdx}]" value="${data.progress || 0}" class="w-16 p-1.5 text-center border border-emerald-200 bg-emerald-50/30 text-emerald-700 font-bold rounded-xl text-xs"></td>
                    <td class="p-2 text-center"><div class="flex gap-1"><input type="date" name="new_start_date[${rIdx}]" value="${data.start_date || '<?= date('Y-m-d') ?>'}" class="text-[10px] w-full p-1 border rounded-lg"><input type="date" name="new_end_date[${rIdx}]" value="${data.end_date || '<?= date('Y-m-d') ?>'}" class="text-[10px] w-full p-1 border rounded-lg"></div></td>
                    <td class="p-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="w-7 h-7 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg flex items-center justify-center mx-auto transition-all"><i class="fa-solid fa-trash text-[11px]"></i></button></td>
                </tr>
            `);
            rIdx++;
        }

        function copyData(key, val) {
            localStorage.setItem(key, JSON.stringify(val));
            localStorage.removeItem(key === 'copied_all_tasks' ? 'copied_single_task' : 'copied_all_tasks');
            document.getElementById('pasteBtn').classList.remove('hidden');
            document.getElementById('pasteBtn').classList.add('flex');
            alert('คัดลอก Task สำเร็จ!');
        }

        function pasteData() {
            let items = JSON.parse(localStorage.getItem('copied_all_tasks') || '[]');
            let single = JSON.parse(localStorage.getItem('copied_single_task') || 'null');
            if (items.length === 0 && single) items = [single];
            items.forEach(t => addRow(t));
        }

        window.onload = () => {
            if (localStorage.getItem('copied_all_tasks') || localStorage.getItem('copied_single_task')) {
                const pBtn = document.getElementById('pasteBtn');
                if(pBtn) { pBtn.classList.remove('hidden'); pBtn.classList.add('flex'); }
            }
        };

        function openEditModal(t) {
            ['mId', 'mName', 'mPlan', 'mAct', 'mStart', 'mEnd', 'mDay'].forEach((id, idx) => {
                document.getElementById(id).value = [t.id, t.task_name, t.plan_progress, t.progress, t.start_date, t.end_date, t.duration_days][idx];
            });
            openModal('editTaskModal');
        }
    </script>
</body>
</html>