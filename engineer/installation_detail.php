<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'work_progress';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$work = null;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM installations WHERE id = ?");
        $stmt->execute([$id]);
        $work = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $work = null;
    }
}

// 1. บันทึกการแก้ไข Plan และ Actual ทั้งหมด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $id > 0) {
    $action = $_POST['action'];

    try {
        if ($action === 'save_all_progress') {
            $pdo->beginTransaction();
            
            $plans   = $_POST['plan_progress'] ?? [];
            $actuals = $_POST['progress'] ?? [];
            $remarks = $_POST['remark'] ?? [];

            if (!empty($plans) && is_array($plans)) {
                $stmt_update = $pdo->prepare("UPDATE installation_tasks SET plan_progress = ?, progress = ?, remark = ? WHERE id = ? AND installation_id = ?");
                foreach ($plans as $task_id => $plan_val) {
                    $t_id = intval($task_id);
                    $p_val = max(0, min(100, intval($plan_val)));
                    $a_val = max(0, min(100, intval($actuals[$t_id] ?? 0)));
                    $r_val = trim($remarks[$t_id] ?? '');
                    $stmt_update->execute([$p_val, $a_val, $r_val, $t_id, $id]);
                }
            }

            $new_names   = $_POST['new_task_name'] ?? [];
            $new_plans   = $_POST['new_plan_progress'] ?? [];
            $new_actuals = $_POST['new_progress'] ?? [];
            $new_starts  = $_POST['new_start_date'] ?? [];
            $new_ends    = $_POST['new_end_date'] ?? [];
            $new_days    = $_POST['new_duration_days'] ?? [];
            $new_remarks = $_POST['new_remark'] ?? [];

            if (!empty($new_names) && is_array($new_names)) {
                $stmt_insert = $pdo->prepare("INSERT INTO installation_tasks (installation_id, task_name, plan_progress, progress, start_date, end_date, duration_days, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($new_names as $idx => $n_name) {
                    $n_name = trim($n_name);
                    if ($n_name !== '') {
                        $stmt_insert->execute([
                            $id,
                            $n_name,
                            max(0, min(100, intval($new_plans[$idx] ?? 0))),
                            max(0, min(100, intval($new_actuals[$idx] ?? 0))),
                            !empty($new_starts[$idx]) ? $new_starts[$idx] : date('Y-m-d'),
                            !empty($new_ends[$idx]) ? $new_ends[$idx] : date('Y-m-d'),
                            max(1, intval($new_days[$idx] ?? 1)),
                            trim($new_remarks[$idx] ?? '')
                        ]);
                    }
                }
            }
            $pdo->commit();
        } elseif ($action === 'inline_edit_task') {
            $task_id = intval($_POST['task_id'] ?? 0);
            $task_name = trim($_POST['task_name'] ?? '');

            if ($task_id > 0 && $task_name !== '') {
                $stmt = $pdo->prepare("UPDATE installation_tasks SET task_name = ?, plan_progress = ?, progress = ?, start_date = ?, end_date = ?, duration_days = ?, remark = ? WHERE id = ? AND installation_id = ?");
                $stmt->execute([
                    $task_name,
                    intval($_POST['task_plan'] ?? 0),
                    intval($_POST['task_progress'] ?? 0),
                    $_POST['task_start'] ?? date('Y-m-d'),
                    $_POST['task_end'] ?? date('Y-m-d'),
                    intval($_POST['task_day'] ?? 1),
                    trim($_POST['task_remark'] ?? ''),
                    $task_id,
                    $id
                ]);
            }
        } elseif ($action === 'paste_all_tasks') {
            $tasks_array = json_decode($_POST['tasks_data'] ?? '[]', true);
            if (!empty($tasks_array) && is_array($tasks_array)) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO installation_tasks (installation_id, task_name, plan_progress, progress, start_date, end_date, duration_days, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($tasks_array as $t) {
                    $stmt->execute([
                        $id,
                        $t['task_name'] ?? '',
                        intval($t['plan_progress'] ?? 0),
                        intval($t['progress'] ?? 0),
                        $t['start_date'] ?? date('Y-m-d'),
                        $t['end_date'] ?? date('Y-m-d'),
                        intval($t['duration_days'] ?? 1),
                        $t['remark'] ?? ''
                    ]);
                }
                $pdo->commit();
            }
        }
        header("Location: installation_detail.php?id={$id}&status=success");
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: installation_detail.php?id={$id}&status=error");
        exit();
    }
}

// 2. ลบงานย่อย
if (isset($_GET['delete_task_id']) && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM installation_tasks WHERE id = ? AND installation_id = ?");
        $stmt->execute([intval($_GET['delete_task_id']), $id]);
        header("Location: installation_detail.php?id={$id}&status=deleted");
        exit();
    } catch (PDOException $e) {
        header("Location: installation_detail.php?id={$id}&status=error");
        exit();
    }
}

// 3. ดึงรายการงานย่อยทั้งหมดและคำนวณตามสูตร Work Progress (%)
$sub_tasks = [];
$task_names_js = [];
$task_plans_js = [];
$task_actuals_js = [];
$avg_actual = 0;
$avg_plan = 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM installation_tasks WHERE installation_id = ? ORDER BY start_date ASC, id ASC");
        $stmt->execute([$id]);
        $sub_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($sub_tasks) > 0) {
            $total_act = 0;
            $total_pln = 0;
            foreach ($sub_tasks as $t) {
                $task_names_js[]   = $t['task_name'];
                $task_plans_js[]   = intval($t['plan_progress'] ?? 0);
                $task_actuals_js[] = intval($t['progress'] ?? 0);
                
                // คำนวณตามสูตร Work Progress (%) = (ผลรวมงานที่ทำเสร็จ / จำนวนงานทั้งหมด)[cite: 3]
                $total_act += floatval($t['progress'] ?? 0);
                $total_pln += floatval($t['plan_progress'] ?? 0);
            }
            $avg_actual = round($total_act / count($sub_tasks), 1);
            $avg_plan   = round($total_pln / count($sub_tasks), 1);
        }
    } catch (PDOException $e) {
        $sub_tasks = [];
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
                    fontFamily: { 
                        sans: ['Prompt', 'sans-serif'],
                        sarabun: ['Sarabun', 'sans-serif']
                    },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        .font-sarabun { font-family: 'Sarabun', sans-serif !important; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: white !important; color: #0f172a !important; font-family: 'Sarabun', sans-serif !important; }
            #sidebarWrapper, #sidebarBackdrop, header, .no-print { display: none !important; }
            body * { visibility: hidden; }
            #printableDailyReport, #printableDailyReport * { visibility: visible; }
            #printableDailyReport {
                position: absolute; left: 0; top: 0; width: 100%;
                background: white !important; z-index: 99999; padding: 0 !important;
                margin: 0 !important; border: none !important; box-shadow: none !important;
            }
            .page-break-inside-avoid { page-break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-slate-100/80 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100/60 w-full">
        <!-- Header -->
        <header class="h-20 bg-white/90 border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 p-2.5 rounded-xl bg-slate-100 border border-slate-200">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <h2 class="text-base md:text-xl font-bold text-slate-900">Progress Plan & Daily Report</h2>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5 truncate max-w-md font-medium">โครงการ: <span class="text-slate-700 font-semibold"><?= htmlspecialchars($work['name'] ?? '-') ?></span></p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button onclick="openDailyReportModal()" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 text-white px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-file-pdf"></i> <span>Daily Report PDF</span>
                </button>
                <a href="work_progress.php" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">กลับ</span>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-7xl mx-auto space-y-6">
                <?php if (!$work): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 p-12 text-center text-slate-500">ไม่พบข้อมูลโครงการ</div>
                <?php else: ?>

                    <!-- Grid Layout -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <!-- ฝั่งซ้าย: ตารางแผนงาน -->
                        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200 shadow-xs p-5 md:p-7 flex flex-col justify-between">
                            <form action="installation_detail.php?id=<?= $id ?>" method="POST" class="flex-1 flex flex-col justify-between space-y-5">
                                <input type="hidden" name="action" value="save_all_progress">
                                <div>
                                    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-list-check"></i></div>
                                            <h3 class="text-xs md:text-sm font-bold text-slate-800 uppercase">ตารางแผนงาน (Task Progress Plan)</h3>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if (!empty($sub_tasks)): ?>
                                                <button type="button" onclick='copyAllTasks(<?= json_encode($sub_tasks) ?>)' class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-xl font-semibold flex items-center gap-1.5"><i class="fa-solid fa-copy"></i> คัดลอกทั้งหมด</button>
                                            <?php endif; ?>
                                            <button type="button" id="pasteAllBtn" onclick="pasteAllTasks()" class="hidden text-xs bg-purple-50 text-purple-700 border border-purple-200 px-3 py-1.5 rounded-xl font-semibold items-center gap-1.5"><i class="fa-solid fa-paste"></i> วางทั้งหมด</button>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto rounded-2xl border border-slate-100">
                                        <table class="w-full text-left border-collapse min-w-[650px]">
                                            <thead>
                                                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600 uppercase">
                                                    <th class="py-3 px-3 text-center w-10">No.</th>
                                                    <th class="py-3 px-4">Task Description</th>
                                                    <th class="py-3 px-2 text-center w-20">Plan (%)</th>
                                                    <th class="py-3 px-2 text-center w-20">Actual (%)</th>
                                                    <th class="py-3 px-3 text-center w-28">Start / End</th>
                                                    <th class="py-3 px-2 text-center w-12">Day</th>
                                                    <th class="py-3 px-3 text-center w-28">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 text-xs">
                                                <?php if (empty($sub_tasks)): ?>
                                                    <tr><td colspan="7" class="py-8 text-center text-slate-400">ยังไม่มีรายการงานย่อย</td></tr>
                                                <?php else: foreach ($sub_tasks as $index => $t): $t_id = $t['id']; ?>
                                                <tr class="hover:bg-slate-50/60">
                                                    <td class="py-3 px-3 text-center font-bold text-slate-400"><?= $index + 1 ?></td>
                                                    <td class="py-3 px-4 font-semibold text-slate-800"><?= htmlspecialchars($t['task_name']) ?></td>
                                                    <td class="py-2 px-2 text-center"><input type="number" min="0" max="100" name="plan_progress[<?= $t_id ?>]" value="<?= intval($t['plan_progress'] ?? 0) ?>" class="w-16 px-2 py-1 text-center rounded-xl border border-blue-200 bg-blue-50/40 font-bold text-blue-700 text-xs focus:outline-none"></td>
                                                    <td class="py-2 px-2 text-center"><input type="number" min="0" max="100" name="progress[<?= $t_id ?>]" value="<?= intval($t['progress'] ?? 0) ?>" class="w-16 px-2 py-1 text-center rounded-xl border border-emerald-200 bg-emerald-50/40 font-bold text-emerald-700 text-xs focus:outline-none"></td>
                                                    <td class="py-3 px-3 text-center text-slate-500 text-[11px] font-medium"><?= !empty($t['start_date']) ? date('d/m/y', strtotime($t['start_date'])) : '-' ?><br>ถึง <?= !empty($t['end_date']) ? date('d/m/y', strtotime($t['end_date'])) : '-' ?></td>
                                                    <td class="py-3 px-2 text-center font-semibold text-slate-600"><?= $t['duration_days'] ?></td>
                                                    <td class="py-3 px-3 text-center">
                                                        <div class="flex items-center justify-center gap-1.5">
                                                            <button type="button" onclick='copyTask(<?= json_encode($t) ?>)' class="w-7 h-7 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center" title="คัดลอก"><i class="fa-solid fa-copy text-xs"></i></button>
                                                            <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center" title="แก้ไข"><i class="fa-solid fa-pen text-xs"></i></button>
                                                            <a href="installation_detail.php?id=<?= $id ?>&delete_task_id=<?= $t_id ?>" onclick="return confirm('ต้องการลบงานย่อยนี้ใช่หรือไม่?');" class="w-7 h-7 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center" title="ลบ"><i class="fa-solid fa-trash text-xs"></i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                            <tbody id="newTaskTableBody" class="divide-y divide-slate-100 text-xs bg-emerald-50/10"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                                    <button type="button" onclick="addNewRow()" class="bg-blue-50 text-blue-700 border border-blue-200/80 px-4 py-2.5 rounded-2xl text-xs font-bold flex items-center gap-2"><i class="fa-solid fa-plus"></i> เพิ่มแถว Task ใหม่</button>
                                    <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-6 py-2.5 rounded-2xl text-xs font-bold flex items-center gap-2"><i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูลทั้งหมด</button>
                                </div>
                            </form>
                        </div>

                        <!-- ฝั่งขวา: กราฟเส้น -->
                        <div class="lg:col-span-5 bg-slate-900 text-white rounded-3xl border border-slate-800 shadow-sm p-5 md:p-7 flex flex-col justify-between">
                            <div class="flex items-center justify-between gap-3 mb-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-2xl bg-slate-800 border border-slate-700 text-emerald-400 flex items-center justify-center text-sm font-bold"><i class="fa-solid fa-chart-line"></i></div>
                                    <div>
                                        <h3 class="text-xs md:text-sm font-bold text-slate-100">กราฟเปรียบเทียบ (Plan vs Actual)</h3>
                                        <p class="text-[11px] text-slate-400">แสดงความคืบหน้าเทียบรายชื่องาน</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 text-xs bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-700">
                                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Plan</div>
                                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span> Actual</div>
                                </div>
                            </div>
                            <div class="h-72 md:h-80 relative flex-1 w-full pt-2">
                                <canvas id="taskLineChart"></canvas>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Form ซ่อนสำหรับวาง Task ทั้งหมด -->
    <form id="pasteAllForm" action="installation_detail.php?id=<?= $id ?>" method="POST" class="hidden">
        <input type="hidden" name="action" value="paste_all_tasks">
        <input type="hidden" name="tasks_data" id="tasksDataInput">
    </form>

    <!-- Modal: Daily Report PDF (TH Sarabun) ไม่มีกรอบซ้อน -->
    <div id="dailyReportModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-10 shadow-2xl border border-slate-200 space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5 no-print">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center font-bold"><i class="fa-solid fa-file-pdf text-lg"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">ตัวอย่างรายงานประจำวัน (Daily Progress Report)</h3>
                        <p class="text-xs text-slate-500 font-medium">รูปแบบฟอนต์: TH Sarabun</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-xs md:text-sm font-bold flex items-center gap-2"><i class="fa-solid fa-print"></i> พิมพ์ / ออกรายงาน PDF</button>
                    <button type="button" onclick="closeDailyReportModal()" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <!-- เอกสารรายงาน PDF ฟอนต์ TH Sarabun (Clean Layout ไม่มีกรอบซ้อน) -->
            <div id="printableDailyReport" class="p-6 bg-white text-slate-900 space-y-5 font-sarabun text-base">
                <div class="flex items-center justify-between border-b-2 border-slate-800 pb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold"><i class="fa-solid fa-solar-panel"></i></div>
                        <div>
                            <h1 class="text-2xl font-bold uppercase tracking-wide text-slate-900 leading-tight">SOLARSTOCK PRO</h1>
                            <p class="text-sm font-semibold text-slate-600">รายงานความคืบหน้างานติดตั้งและแผนงานประจำวัน (DAILY PROGRESS REPORT)</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded text-xs font-bold uppercase mb-1">DAILY REPORT</span>
                        <p class="text-xs font-semibold text-slate-600">วันที่: <?= date('d/m/Y') ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-sm">
                    <div class="col-span-7 space-y-1">
                        <p><span class="font-bold text-slate-600">ชื่อโครงการ:</span> <strong class="text-base text-slate-900"><?= htmlspecialchars($work['name'] ?? '-') ?></strong></p>
                        <p><span class="font-bold text-slate-600">ลูกค้า / สถานที่ติดตั้ง:</span> <span class="font-semibold text-slate-800"><?= htmlspecialchars($work['customer_name'] ?? '-') ?></span></p>
                    </div>
                    <div class="col-span-5 text-right space-y-1 border-l border-slate-200 pl-4">
                        <p><span class="font-bold text-slate-600">แผนงานรวม (Plan Progress):</span> <strong class="text-blue-700 text-base ml-1"><?= $avg_plan ?>%</strong></p>
                        <p><span class="font-bold text-slate-600">ผลงานที่ทำได้จริง (Actual Progress):</span> <strong class="text-emerald-700 text-base ml-1"><?= $avg_actual ?>%</strong></p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-slate-800 uppercase flex items-center gap-1.5 text-base"><i class="fa-solid fa-list-check text-emerald-600"></i> รายการงานประจำวัน (Tasks Progress Summary)</h4>
                    <table class="w-full text-left border-collapse border border-slate-300 text-sm">
                        <thead>
                            <tr class="bg-slate-800 text-white font-bold text-sm">
                                <th class="py-2 px-3 border border-slate-700 text-center w-12">ลำดับ</th>
                                <th class="py-2 px-3 border border-slate-700">รายการงาน (Task Description)</th>
                                <th class="py-2 px-2 border border-slate-700 text-center w-20">Plan (%)</th>
                                <th class="py-2 px-2 border border-slate-700 text-center w-20">Actual (%)</th>
                                <th class="py-2 px-3 border border-slate-700 text-center w-32">ระยะเวลา (Start-End)</th>
                                <th class="py-2 px-3 border border-slate-700">หมายเหตุ / ปัญหาที่พบ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (empty($sub_tasks)): ?>
                                <tr><td colspan="6" class="py-4 text-center text-slate-400">ไม่มีข้อมูลงานย่อย</td></tr>
                            <?php else: foreach ($sub_tasks as $idx =>$t): ?>
                            <tr>
                                <td class="py-2 px-3 border border-slate-300 text-center font-bold text-slate-600"><?= $idx + 1 ?></td>
                                <td class="py-2 px-3 border border-slate-300 font-semibold text-slate-900"><?= htmlspecialchars($t['task_name']) ?></td>
                                <td class="py-2 px-2 border border-slate-300 text-center font-bold text-blue-700 bg-blue-50/20"><?= intval($t['plan_progress'] ?? 0) ?>%</td>
                                <td class="py-2 px-2 border border-slate-300 text-center font-bold text-emerald-700 bg-emerald-50/20"><?= intval($t['progress'] ?? 0) ?>%</td>
                                <td class="py-2 px-3 border border-slate-300 text-center text-xs text-slate-700 font-medium"><?= !empty($t['start_date']) ? date('d/m/Y', strtotime($t['start_date'])) : '-' ?> ถึง <?= !empty($t['end_date']) ? date('d/m/Y', strtotime($t['end_date'])) : '-' ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-slate-700 text-xs"><?= htmlspecialchars($t['remark'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-8 pt-6 page-break-inside-avoid">
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-10">
                        <p class="font-bold text-slate-800">ผู้รายงาน / หัวหน้าทีมช่าง</p>
                        <div class="space-y-1 text-xs">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-10">
                        <p class="font-bold text-slate-800">ผู้ตรวจสอบ / ผู้จัดการโครงการ</p>
                        <div class="space-y-1 text-xs">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับแก้ไขรายละเอียด Task -->
    <div id="editTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl border border-slate-200 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xs"><i class="fa-solid fa-pen"></i></div>
                    <h3 class="text-base font-bold text-slate-900">แก้ไขรายละเอียด Task</h3>
                </div>
                <button type="button" onclick="closeEditModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="installation_detail.php?id=<?= $id ?>" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="inline_edit_task">
                <input type="hidden" name="task_id" id="modalTaskId">

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase">Task Description (ชื่องาน)</label>
                    <input type="text" name="task_name" id="modalTaskName" required class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-xs font-semibold text-slate-800">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-blue-700 uppercase">Plan Progress (%)</label>
                        <input type="number" name="task_plan" id="modalTaskPlan" min="0" max="100" required class="w-full px-3.5 py-2.5 rounded-2xl border border-blue-200 text-xs font-bold text-blue-700">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-emerald-700 uppercase">Actual Progress (%)</label>
                        <input type="number" name="task_progress" id="modalTaskProgress" min="0" max="100" required class="w-full px-3.5 py-2.5 rounded-2xl border border-emerald-200 text-xs font-bold text-emerald-700">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Start Date</label>
                        <input type="date" name="task_start" id="modalTaskStart" required class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-xs">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase">End Date</label>
                        <input type="date" name="task_end" id="modalTaskEnd" required class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Duration (วัน)</label>
                        <input type="number" name="task_day" id="modalTaskDay" min="1" required class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-xs">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Remark</label>
                        <input type="text" name="task_remark" id="modalTaskRemark" class="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-xs">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeEditModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-bold">ยกเลิก</button>
                    <button type="submit" class="bg-emerald-600 text-white px-5 py-2 rounded-xl text-xs font-bold">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        }

        function openDailyReportModal() { document.getElementById('dailyReportModal').classList.remove('hidden'); }
        function closeDailyReportModal() { document.getElementById('dailyReportModal').classList.add('hidden'); }

        function openEditModal(task) {
            document.getElementById('modalTaskId').value = task.id;
            document.getElementById('modalTaskName').value = task.task_name;
            document.getElementById('modalTaskPlan').value = task.plan_progress;
            document.getElementById('modalTaskProgress').value = task.progress;
            document.getElementById('modalTaskStart').value = task.start_date;
            document.getElementById('modalTaskEnd').value = task.end_date;
            document.getElementById('modalTaskDay').value = task.duration_days;
            document.getElementById('modalTaskRemark').value = task.remark || '';
            document.getElementById('editTaskModal').classList.remove('hidden');
        }
        function closeEditModal() { document.getElementById('editTaskModal').classList.add('hidden'); }

        function addNewRow() {
            const tbody = document.getElementById('newTaskTableBody');
            const rowCount = tbody.rows.length + <?= count($sub_tasks) ?>;
            const today = new Date().toISOString().split('T')[0];

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/60';
            tr.innerHTML = `
                <td class="py-3 px-3 text-center font-bold text-emerald-600">${rowCount + 1}</td>
                <td class="py-2.5 px-3"><input type="text" name="new_task_name[]" placeholder="ระบุชื่องานใหม่..." required class="w-full px-3 py-1.5 rounded-xl border border-emerald-300 text-xs font-semibold bg-white"></td>
                <td class="py-2 px-2 text-center"><input type="number" min="0" max="100" name="new_plan_progress[]" value="0" class="w-16 px-2 py-1 text-center rounded-xl border border-blue-200 bg-blue-50/40 font-bold text-blue-700 text-xs"></td>
                <td class="py-2 px-2 text-center"><input type="number" min="0" max="100" name="new_progress[]" value="0" class="w-16 px-2 py-1 text-center rounded-xl border border-emerald-200 bg-emerald-50/40 font-bold text-emerald-700 text-xs"></td>
                <td class="py-2 px-2 text-center"><div class="flex gap-1"><input type="date" name="new_start_date[]" value="${today}" class="w-full px-1 py-1 text-[10px] rounded-lg border border-slate-200"><input type="date" name="new_end_date[]" value="${today}" class="w-full px-1 py-1 text-[10px] rounded-lg border border-slate-200"></div></td>
                <td class="py-2 px-2 text-center"><input type="number" min="1" name="new_duration_days[]" value="1" class="w-12 px-1 py-1 text-center rounded-xl border border-slate-200 text-xs"></td>
                <td class="py-2.5 px-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="w-7 h-7 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto"><i class="fa-solid fa-trash text-xs"></i></button></td>
            `;
            tbody.appendChild(tr);
        }

        function copyTask(task) {
            navigator.clipboard.writeText(JSON.stringify([task])).then(() => {
                alert('คัดลอก Task นี้เรียบร้อยแล้ว!');
                document.getElementById('pasteAllBtn').classList.remove('hidden');
                document.getElementById('pasteAllBtn').classList.add('flex');
            });
        }
        function copyAllTasks(tasks) {
            navigator.clipboard.writeText(JSON.stringify(tasks)).then(() => {
                alert('คัดลอกรายการงานทั้งหมดเรียบร้อยแล้ว!');
                document.getElementById('pasteAllBtn').classList.remove('hidden');
                document.getElementById('pasteAllBtn').classList.add('flex');
            });
        }
        function pasteAllTasks() {
            navigator.clipboard.readText().then(text => {
                try {
                    let data = JSON.parse(text);
                    if (Array.isArray(data) && data.length > 0) {
                        document.getElementById('tasksDataInput').value = text;
                        document.getElementById('pasteAllForm').submit();
                    } else {
                        alert('ข้อมูลใน Clipboard ไม่ถูกต้อง');
                    }
                } catch (e) {
                    alert('ไม่สามารถอ่านข้อมูลจาก Clipboard ได้');
                }
            });
        }

        // Chart.js Setup
        const taskNames = <?= json_encode($task_names_js) ?>;
        const taskPlans = <?= json_encode($task_plans_js) ?>;
        const taskActuals = <?= json_encode($task_actuals_js) ?>;

        if (taskNames.length > 0) {
            const ctx = document.getElementById('taskLineChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: taskNames,
                    datasets: [
                        { label: 'Plan (%)', data: taskPlans, borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', borderWidth: 3, tension: 0.3, fill: true },
                        { label: 'Actual (%)', data: taskActuals, borderColor: '#ff0a0a', backgroundColor: 'rgba(16, 185, 129, 0.1)', borderWidth: 3, tension: 0.3, fill: true }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 100, ticks: { color: '#fefefe', font: { size: 10 } }, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                        x: { ticks: { color: '#ffffff', font: { size: 10 } }, grid: { display: false } }
                    }
                }
            });
        }
    </script>
</body>
</html>