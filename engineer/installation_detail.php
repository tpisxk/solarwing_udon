<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

// เพิ่มคอลัมน์ plan_progress อัตโนมัติหากยังไม่มีในฐานข้อมูล
try {
    $pdo->exec("ALTER TABLE installation_tasks ADD COLUMN plan_progress INT DEFAULT 0");
} catch (PDOException $e) {
    // ข้ามหากมีอยู่แล้ว
}

$current_page = 'work_progress';
$message = '';
$message_type = '';

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

// 1. บันทึกการเพิ่ม Task ใหม่หลายแถวพร้อมกัน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_multiple_tasks' && $id > 0) {
    $new_names   = $_POST['new_task_name'] ?? [];
    $new_plans   = $_POST['new_plan_progress'] ?? [];
    $new_actuals = $_POST['new_progress'] ?? [];
    $new_starts  = $_POST['new_start_date'] ?? [];
    $new_ends    = $_POST['new_end_date'] ?? [];
    $new_days    = $_POST['new_duration_days'] ?? [];

    if (!empty($new_names) && is_array($new_names)) {
        try {
            $pdo->beginTransaction();
            $stmt_insert = $pdo->prepare("INSERT INTO installation_tasks (installation_id, task_name, plan_progress, progress, start_date, end_date, duration_days) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($new_names as $idx => $n_name) {
                $n_name = trim($n_name);
                if (!empty($n_name)) {
                    $np_val = max(0, min(100, intval($new_plans[$idx] ?? 0)));
                    $na_val = max(0, min(100, intval($new_actuals[$idx] ?? 0)));
                    $ns_val = !empty($new_starts[$idx]) ? $new_starts[$idx] : date('Y-m-d');
                    $ne_val = !empty($new_ends[$idx]) ? $new_ends[$idx] : date('Y-m-d');
                    $nd_val = max(1, intval($new_days[$idx] ?? 1));

                    $stmt_insert->execute([$id, $n_name, $np_val, $na_val, $ns_val, $ne_val, $nd_val]);
                }
            }

            $pdo->commit();
            $message = 'เพิ่มงานย่อยใหม่เรียบร้อยแล้ว!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 2. บันทึกการแก้ไข Task รายตัวผ่าน Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inline_edit_task' && $id > 0) {
    $task_id       = intval($_POST['task_id'] ?? 0);
    $task_name     = trim($_POST['task_name'] ?? '');
    $task_plan     = intval($_POST['task_plan'] ?? 0);
    $task_progress = intval($_POST['task_progress'] ?? 0);
    $task_start    = $_POST['task_start'] ?? date('Y-m-d');
    $task_end      = $_POST['task_end'] ?? date('Y-m-d');
    $task_day      = intval($_POST['task_day'] ?? 1);
    $task_remark   = trim($_POST['task_remark'] ?? '');

    if ($task_id > 0 && !empty($task_name)) {
        try {
            $stmt_update = $pdo->prepare("UPDATE installation_tasks SET task_name = ?, plan_progress = ?, progress = ?, start_date = ?, end_date = ?, duration_days = ?, remark = ? WHERE id = ? AND installation_id = ?");
            $stmt_update->execute([$task_name, $task_plan, $task_progress, $task_start, $task_end, $task_day, $task_remark, $task_id, $id]);
            
            $message = 'อัปเดตรายละเอียดงานย่อยเรียบร้อยแล้ว!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 3. วาง Task ทั้งหมดจากการ Copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'paste_all_tasks' && $id > 0) {
    $tasks_json = $_POST['tasks_data'] ?? '[]';
    $tasks_array = json_decode($tasks_json, true);
    if (!empty($tasks_array) && is_array($tasks_array)) {
        try {
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
            $message = 'วางและบันทึก Task ทั้งหมดเรียบร้อยแล้ว!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 4. ลบงานย่อย
if (isset($_GET['delete_task_id']) && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM installation_tasks WHERE id = ? AND installation_id = ?");
        $stmt->execute([intval($_GET['delete_task_id']), $id]);
        $message = 'ลบงานย่อยเรียบร้อยแล้ว!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// ดึงรายการงานย่อยทั้งหมด
$sub_tasks = [];
$min_date = null;
$max_date = null;

if ($id > 0) {
    try {
        $stmt_tasks = $pdo->prepare("SELECT * FROM installation_tasks WHERE installation_id = ? ORDER BY start_date ASC, id ASC");
        $stmt_tasks->execute([$id]);
        $sub_tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sub_tasks as $t) {
            if (!empty($t['start_date'])) {
                if (!$min_date || $t['start_date'] < $min_date) $min_date = $t['start_date'];
            }
            if (!empty($t['end_date'])) {
                if (!$max_date || $t['end_date'] > $max_date) $max_date = $t['end_date'];
            }
        }
    } catch (PDOException $e) {
        $sub_tasks = [];
    }
}

// คำนวณกราฟ
$timeline_labels = [];
$plan_cumulative = [];
$actual_cumulative = [];

if ($min_date && $max_date) {
    $period = new DatePeriod(
        new DateTime($min_date),
        new DateInterval('P1D'),
        (new DateTime($max_date))->modify('+1 day')
    );

    $total_tasks = count($sub_tasks);
    $weight_per_task = $total_tasks > 0 ? (100 / $total_tasks) : 0;

    $running_plan = 0;
    $running_actual = 0;

    foreach ($period as $date) {
        $current_day = $date->format('Y-m-d');
        $timeline_labels[] = $date->format('d/m/y');

        $day_plan_add = 0;
        $day_actual_add = 0;

        foreach ($sub_tasks as $t) {
            $start = $t['start_date'] ?? '';
            $end = $t['end_date'] ?? '';
            $days = max(1, intval($t['duration_days'] ?? 1));
            $task_plan = floatval($t['plan_progress'] ?? 0);
            $task_prog = floatval($t['progress'] ?? 0);

            if ($start && $end && $current_day >= $start && $current_day <= $end) {
                $day_plan_add += (($weight_per_task * ($task_plan / 100)) / $days);
                $day_actual_add += (($weight_per_task * ($task_prog / 100)) / $days);
            }
        }

        $running_plan = min(100,$running_plan + $day_plan_add);$running_actual = min(100, $running_actual +$day_actual_add);

        $plan_cumulative[] = round($running_plan, 2);$actual_cumulative[] = round($running_actual, 2);
    }
} else {$timeline_labels = ['-'];
    $plan_cumulative = [0];$actual_cumulative = [0];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Plan & Details - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">Progress Plan (แผนงานโครงการ)</h2>
                    <p class="text-[11px] md:text-xs text-slate-500 mt-0.5 truncate max-w-[200px] sm:max-w-md">โครงการ: <?= htmlspecialchars($work['name'] ?? '-') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="work_progress.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">กลับ</span>
                </a>
            </div>
        </header>

        <!-- Main Content (2 Columns Layout) -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-7xl mx-auto space-y-6">

                <?php if (!$work): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
                        <p class="text-slate-500">ไม่พบข้อมูลโครงการ</p>
                    </div>
                <?php else: ?>

                    <?php if (!empty($message)): ?>
                        <div class="px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2 <?= $message_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                            <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?>"></i>
                            <span><?= htmlspecialchars($message) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Grid Container -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                        <!-- ฝั่งซ้าย: ตารางแสดงผล + คัดลอกวาง + เพิ่มแถว Task ใหม่ (Col Span 7) -->
                        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-4 md:p-6 flex flex-col justify-between">
                            <form action="installation_detail.php?id=<?= $id ?>" method="POST" class="flex-1 flex flex-col justify-between space-y-4">
                                <input type="hidden" name="action" value="add_multiple_tasks">
                                
                                <div>
                                    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
                                        <h3 class="text-xs md:text-sm font-bold text-slate-800 uppercase tracking-wider">ตารางแผนงาน (Task Progress Plan)</h3>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <?php if (!empty($sub_tasks)): ?>
                                                <button type="button" onclick='copyAllTasks(<?= json_encode($sub_tasks) ?>)' class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-xl font-semibold hover:bg-indigo-100 transition-all flex items-center gap-1.5 shadow-sm">
                                                    <i class="fa-solid fa-copy text-xs"></i> คัดลอกทั้งหมด
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" id="pasteAllBtn" onclick="pasteAllTasks()" class="hidden text-xs bg-purple-50 text-purple-700 border border-purple-200 px-3 py-1.5 rounded-xl font-semibold hover:bg-purple-100 transition-all items-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-paste text-xs"></i> วางทั้งหมด
                                            </button>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left border-collapse min-w-[700px]">
                                            <thead>
                                                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600 uppercase">
                                                    <th class="py-3 px-2 text-center w-10">No.</th>
                                                    <th class="py-3 px-3">Task Description</th>
                                                    <th class="py-3 px-2 text-center w-16">Plan</th>
                                                    <th class="py-3 px-2 text-center w-16">Actual</th>
                                                    <th class="py-3 px-3 text-center w-28">Start / End</th>
                                                    <th class="py-3 px-2 text-center w-12">Day</th>
                                                    <th class="py-3 px-2 text-center w-24">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 text-xs">
                                                <?php if (empty($sub_tasks)): ?>
                                                    <tr><td colspan="7" class="py-6 text-center text-slate-400">ยังไม่มีรายการงานย่อย</td></tr>
                                                <?php else: foreach ($sub_tasks as $index =>$t): ?>
                                                <tr class="hover:bg-slate-50/50">
                                                    <td class="py-3 px-2 text-center font-bold text-slate-500"><?= $index + 1 ?></td>
                                                    <td class="py-3 px-3 font-semibold text-slate-800"><?= htmlspecialchars($t['task_name']) ?></td>
                                                    <td class="py-3 px-2 text-center font-bold text-blue-600"><?= intval($t['plan_progress'] ?? 0) ?>%</td>
                                                    <td class="py-3 px-2 text-center">
                                                        <span class="px-2 py-0.5 rounded-full font-bold <?= intval($t['progress'] ?? 0) >= 100 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                                                            <?= intval($t['progress'] ?? 0) ?>%
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-3 text-center text-slate-500 text-[11px]">
                                                        <?= !empty($t['start_date']) ? date('d/m/y', strtotime($t['start_date'])) : '-' ?> <br>
                                                        ถึง <?= !empty($t['end_date']) ? date('d/m/y', strtotime($t['end_date'])) : '-' ?>
                                                    </td>
                                                    <td class="py-3 px-2 text-center text-slate-600"><?= $t['duration_days'] ?></td>
                                                    <td class="py-3 px-2 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <button type="button" onclick='copyTask(<?= json_encode($t) ?>)' class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center transition-all" title="คัดลอก Task นี้">
                                                                <i class="fa-solid fa-copy text-xs"></i>
                                                            </button>
                                                            <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 inline-flex items-center justify-center transition-all shadow-sm" title="แก้ไข">
                                                                <i class="fa-solid fa-pen text-xs"></i>
                                                            </button>
                                                            <a href="installation_detail.php?id=<?= $id ?>&delete_task_id=<?= $t['id'] ?>" onclick="return confirm('ต้องการลบงานย่อยนี้ใช่หรือไม่?');" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 inline-flex items-center justify-center transition-all shadow-sm" title="ลบ">
                                                                <i class="fa-solid fa-trash text-xs"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>

                                            <!-- พื้นที่สำหรับเพิ่มแถว Task ใหม่ต่อลงมา -->
                                            <tbody id="newTaskTableBody" class="divide-y divide-slate-100 text-xs bg-emerald-50/20">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
                                    <button type="button" onclick="addNewRow()" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                                        <i class="fa-solid fa-plus text-xs"></i> เพิ่มแถว Task ใหม่
                                    </button>
                                    <button type="submit" id="saveNewTasksBtn" class="hidden bg-brandGreen hover:bg-emerald-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition-all shadow-md items-center gap-1.5">
                                        <i class="fa-solid fa-floppy-disk text-xs"></i> บันทึกงานใหม่ที่เพิ่ม
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- ฝั่งขวา: กราฟ Line Chart (Col Span 5) -->
                        <div class="lg:col-span-5 bg-slate-900 text-white rounded-3xl border border-slate-800 shadow-sm p-4 md:p-6 flex flex-col justify-between">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-xs md:text-sm font-bold text-slate-200">กราฟเปรียบเทียบ (Plan vs Actual)</h3>
                                    <p class="text-[11px] text-slate-400">แสดงความคืบหน้าสะสมตามช่วงเวลา</p>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-300">
                                    <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Plan</div>
                                    <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span> Actual</div>
                                </div>
                            </div>
                            <div class="h-72 md:h-80 relative flex-1">
                                <canvas id="progressLineChart"></canvas>
                            </div>
                        </div>

                    </div>

                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Form ซ่อนสำหรับส่งข้อมูลวาง Task ทั้งหมด -->
    <form id="pasteAllForm" action="installation_detail.php?id=<?= $id ?>" method="POST" class="hidden">
        <input type="hidden" name="action" value="paste_all_tasks">
        <input type="hidden" name="tasks_data" id="tasksDataInput">
    </form>

    <!-- Modal สำหรับแก้ไข Task รายตัว -->
    <div id="editTaskModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl border border-slate-200 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-pen-to-square text-amber-600 me-2"></i>แก้ไขรายละเอียด Task</h3>
                <button type="button" onclick="closeEditModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="installation_detail.php?id=<?= $id ?>" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="inline_edit_task">
                <input type="hidden" name="task_id" id="modalTaskId">

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase">Task Description (ชื่องาน)</label>
                    <input type="text" name="task_name" id="modalTaskName" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen bg-slate-50 font-semibold">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-blue-700 uppercase">Plan Progress (%)</label>
                        <input type="number" name="task_plan" id="modalTaskPlan" min="0" max="100" required class="w-full px-3 py-2 rounded-xl border border-blue-200 text-xs focus:outline-none focus:border-blue-600 bg-blue-50/50 font-bold text-blue-600">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-emerald-700 uppercase">Actual Progress (%)</label>
                        <input type="number" name="task_progress" id="modalTaskProgress" min="0" max="100" required class="w-full px-3 py-2 rounded-xl border border-emerald-200 text-xs focus:outline-none focus:border-brandGreen bg-emerald-50/50 font-bold text-emerald-600">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Start Date</label>
                        <input type="date" name="task_start" id="modalTaskStart" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen bg-slate-50">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase">End Date</label>
                        <input type="date" name="task_end" id="modalTaskEnd" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen bg-slate-50">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Duration (Days)</label>
                        <input type="number" name="task_day" id="modalTaskDay" min="1" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen bg-slate-50">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Remark</label>
                        <input type="text" name="task_remark" id="modalTaskRemark" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen bg-slate-50">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-md">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        function openEditModal(task) {
            document.getElementById('modalTaskId').value = task.id;
            document.getElementById('modalTaskName').value = task.task_name;
            document.getElementById('modalTaskPlan').value = task.plan_progress || 0;
            document.getElementById('modalTaskProgress').value = task.progress || 0;
            document.getElementById('modalTaskStart').value = task.start_date;
            document.getElementById('modalTaskEnd').value = task.end_date;
            document.getElementById('modalTaskDay').value = task.duration_days;
            document.getElementById('modalTaskRemark').value = task.remark || '';

            document.getElementById('editTaskModal').classList.remove('hidden');
            document.getElementById('editTaskModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editTaskModal').classList.remove('flex');
            document.getElementById('editTaskModal').classList.add('hidden');
        }

        // ฟังก์ชันกดเพิ่มแถว Task ใหม่ต่อลงมาในตาราง
        function addNewRow(taskData = null) {
            const tbody = document.getElementById('newTaskTableBody');
            const today = new Date().toISOString().split('T')[0];

            const name = taskData ? taskData.task_name : '';
            const plan = taskData ? (taskData.plan_progress || 0) : 0;
            const actual = taskData ? (taskData.progress || 0) : 0;
            const start = taskData ? taskData.start_date : today;
            const end = taskData ? taskData.end_date : today;
            const days = taskData ? taskData.duration_days : 1;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/50';
            tr.innerHTML = `
                <td class="py-2 px-2 text-center font-bold text-emerald-600"><i class="fa-solid fa-plus text-xs"></i></td>
                <td class="py-2 px-2">
                    <input type="text" name="new_task_name[]" value="${name}" placeholder="ชื่องานย่อยใหม่..." required class="w-full px-2 py-1 rounded-lg border border-emerald-300 text-xs focus:outline-none focus:border-brandGreen bg-white font-semibold">
                </td>
                <td class="py-2 px-1 text-center">
                    <input type="number" min="0" max="100" name="new_plan_progress[]" value="${plan}" class="w-14 px-1 py-1 text-center rounded-lg border border-blue-200 bg-blue-50/50 font-bold text-blue-700 text-xs focus:outline-none focus:border-blue-600">
                </td>
                <td class="py-2 px-1 text-center">
                    <input type="number" min="0" max="100" name="new_progress[]" value="${actual}" class="w-14 px-1 py-1 text-center rounded-lg border border-emerald-200 bg-emerald-50/50 font-bold text-emerald-700 text-xs focus:outline-none focus:border-brandGreen">
                </td>
                <td class="py-2 px-1 text-center">
                    <input type="date" name="new_start_date[]" value="${start}" class="w-full px-1 py-1 text-center rounded-lg border border-slate-200 text-[11px] focus:outline-none focus:border-brandGreen bg-white">
                </td>
                <td class="py-2 px-1 text-center">
                    <input type="date" name="new_end_date[]" value="${end}" class="w-full px-1 py-1 text-center rounded-lg border border-slate-200 text-[11px] focus:outline-none focus:border-brandGreen bg-white">
                </td>
                <td class="py-2 px-1 text-center">
                    <input type="number" min="1" name="new_duration_days[]" value="${days}" class="w-12 px-1 py-1 text-center rounded-lg border border-slate-200 text-xs focus:outline-none focus:border-brandGreen bg-white">
                </td>
                <td class="py-2 px-1 text-center">
                    <button type="button" onclick="removeNewRow(this)" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 inline-flex items-center justify-center transition-all" title="ลบแถวนี้">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            document.getElementById('saveNewTasksBtn').classList.remove('hidden');
            document.getElementById('saveNewTasksBtn').classList.add('flex');
        }

        function removeNewRow(btn) {
            const tr = btn.closest('tr');
            tr.remove();
            const tbody = document.getElementById('newTaskTableBody');
            if (tbody.children.length === 0) {
                document.getElementById('saveNewTasksBtn').classList.remove('flex');
                document.getElementById('saveNewTasksBtn').classList.add('hidden');
            }
        }

        // คัดลอก Task เดี่ยว
        function copyTask(taskData) {
            localStorage.setItem('copied_installation_task', JSON.stringify(taskData));
            alert('คัดลอก Task "' + taskData.task_name + '" เรียบร้อยแล้ว!');
        }

        // คัดลอก Task ทั้งหมด
        function copyAllTasks(tasksArray) {
            localStorage.setItem('copied_all_installation_tasks', JSON.stringify(tasksArray));
            checkCopiedTask();
            alert('คัดลอก Task ทั้งหมด (' + tasksArray.length + ' รายการ) เรียบร้อยแล้ว!');
        }

        // วาง Task ทั้งหมด
        function pasteAllTasks() {
            const dataStr = localStorage.getItem('copied_all_installation_tasks');
            if (!dataStr) return;

            if (confirm('คุณต้องการวาง Task ทั้งหมดลงในโครงการนี้ใช่หรือไม่?')) {
                document.getElementById('tasksDataInput').value = dataStr;
                document.getElementById('pasteAllForm').submit();
            }
        }

        function checkCopiedTask() {
            const pasteAllBtn = document.getElementById('pasteAllBtn');
            if (localStorage.getItem('copied_all_installation_tasks')) {
                pasteAllBtn.classList.remove('hidden');
                pasteAllBtn.classList.add('flex');
            } else {
                pasteAllBtn.classList.add('hidden');
                pasteAllBtn.classList.remove('flex');
            }
        }
        window.addEventListener('DOMContentLoaded', checkCopiedTask);

        // กราฟ Line Chart
        const ctxLine = document.getElementById('progressLineChart').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?= json_encode($timeline_labels) ?>,
                datasets: [
                    {
                        label: 'Plan (%)',
                        data: <?= json_encode($plan_cumulative) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1
                    },
                    {
                        label: 'Actual (%)',
                        data: <?= json_encode($actual_cumulative) ?>,
                        borderColor: '#f43f5e',
                        backgroundColor: 'rgba(244, 63, 94, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        max: 100, 
                        ticks: { color: '#94a3b8', font: { family: 'Prompt', size: 11 } }, 
                        grid: { color: '#334155' } 
                    },
                    x: { 
                        ticks: { color: '#94a3b8', font: { family: 'Prompt', size: 10 }, maxTicksLimit: 12 }, 
                        grid: { color: '#334155' } 
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>