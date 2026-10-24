<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'work_progress';
$message = '';
$message_type = '';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$work = null;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM installations WHERE id = ?");
        $stmt->execute([$id]);
        $work = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $work = null;
    }
}

// 1. เพิ่มหรือแก้ไข Task งานย่อยรายตัว
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $id > 0) {
    $action = $_POST['action'];

    if ($action === 'paste_all_tasks') {
        $tasks_json = $_POST['tasks_data'] ?? '[]';
        $tasks_array = json_decode($tasks_json, true);
        if (!empty($tasks_array) && is_array($tasks_array)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO installation_tasks (installation_id, task_name, progress, start_date, end_date, duration_days, remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($tasks_array as $t) {
                    $stmt->execute([
                        $id,
                        $t['task_name'] ?? '',
                        0, // รีเซต Progress เป็น 0 สำหรับโปรเจกต์ใหม่
                        $t['start_date'] ?? date('Y-m-d'),
                        $t['end_date'] ?? date('Y-m-d'),
                        $t['duration_days'] ?? 1,
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
    } else {
        $task_id      = intval($_POST['task_id'] ?? 0);
        $task_name    = trim($_POST['task_name'] ?? '');
        $task_progress = intval($_POST['task_progress'] ?? 0);
        $task_start   = $_POST['task_start'] ?? date('Y-m-d');
        $task_end     = $_POST['task_end'] ?? date('Y-m-d');
        $task_day     = intval($_POST['task_day'] ?? 1);
        $task_remark  = trim($_POST['task_remark'] ?? '');

        if (!empty($task_name)) {
            try {
                if ($action === 'edit_task' && $task_id > 0) {
                    $stmt = $pdo->prepare("UPDATE installation_tasks SET task_name = ?, progress = ?, start_date = ?, end_date = ?, duration_days = ?, remark = ? WHERE id = ? AND installation_id = ?");
                    $stmt->execute([$task_name, $task_progress, $task_start, $task_end, $task_day, $task_remark, $task_id, $id]);
                    $message = 'แก้ไขงานย่อยเรียบร้อยแล้ว!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO installation_tasks (installation_id, task_name, progress, start_date, end_date, duration_days, remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $task_name, $task_progress, $task_start, $task_end, $task_day, $task_remark]);
                    $message = 'เพิ่มงานย่อยสำเร็จ!';
                }
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// 2. ลบงานย่อย
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

// ดึงข้อมูล Task ที่ต้องการแก้ไข
$edit_task = null;
if (isset($_GET['edit_task_id']) && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM installation_tasks WHERE id = ? AND installation_id = ?");
    $stmt->execute([intval($_GET['edit_task_id']), $id]);
    $edit_task = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ดึงรายการงานย่อยทั้งหมด
$sub_tasks = [];
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM installation_tasks WHERE installation_id = ? ORDER BY start_date ASC, id ASC");
    $stmt->execute([$id]);
    $sub_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแผนงานย่อย - Solarwing Engineer</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

    <!-- Sidebar สำหรับ Desktop และ Mobile Drawer -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
    </div>

    <!-- Backdrop สำหรับปิด Sidebar บนมือถือ -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">จัดการแผนงานย่อย (Task Progress Plan)</h2>
                    <p class="text-[11px] md:text-xs text-slate-500 mt-0.5 truncate max-w-[200px] sm:max-w-md">โครงการ: <?= htmlspecialchars($work['name'] ?? '-') ?></p>
                </div>
            </div>
            <div>
                <a href="installation_detail.php?id=<?= $id ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">ดูหน้า Progress Plan</span>
                </a>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-6xl mx-auto space-y-6">

                <?php if (!$work): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
                        <p class="text-slate-500">ไม่พบข้อมูลโครงการ</p>
                        <a href="work_progress.php" class="inline-block mt-4 bg-brandGreen text-white px-6 py-2 rounded-xl text-sm font-semibold">กลับหน้าหลัก</a>
                    </div>
                <?php else: ?>

                    <?php if (!empty($message)): ?>
                        <div class="px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2 <?= $message_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                            <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?>"></i>
                            <span><?= htmlspecialchars($message) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- ตารางแสดงรายการงานย่อย -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-4 md:p-6 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <h3 class="text-xs md:text-sm font-bold text-slate-800 uppercase tracking-wider">รายการงานย่อย (Task List)</h3>
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- ปุ่มคัดลอก Task ทั้งหมด -->
                                <?php if (!empty($sub_tasks)): ?>
                                    <button type="button" onclick='copyAllTasks(<?= json_encode($sub_tasks) ?>)' class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-xl font-semibold hover:bg-indigo-100 transition-all flex items-center gap-1.5 shadow-sm">
                                        <i class="fa-solid fa-copy text-xs"></i> คัดลอก Task ทั้งหมด (<?= count($sub_tasks) ?>)
                                    </button>
                                <?php endif; ?>

                                <!-- ปุ่มวาง Task ทั้งหมด (ซ่อนไว้ถ้ายังไม่มีการคัดลอก) -->
                                <button type="button" id="pasteAllBtn" onclick="pasteAllTasks()" class="hidden text-xs bg-purple-50 text-purple-700 border border-purple-200 px-3 py-1.5 rounded-xl font-semibold hover:bg-purple-100 transition-all items-center gap-1.5 shadow-sm">
                                    <i class="fa-solid fa-paste text-xs"></i> วาง Task ทั้งหมดลงที่นี่
                                </button>

                                <!-- ปุ่มวาง Task เดี่ยว -->
                                <button type="button" id="pasteBtn" onclick="pasteTask()" class="hidden text-xs bg-slate-50 text-slate-700 border border-slate-200 px-3 py-1.5 rounded-xl font-semibold hover:bg-slate-100 transition-all items-center gap-1.5 shadow-sm">
                                    <i class="fa-solid fa-paste text-xs"></i> วาง Task เดี่ยว
                                </button>
                                <!-- แสดงการแก้ไข Task id -->
                                <?php if ($edit_task): ?>
                                    <a href="edit_installation.php?id=<?= $id ?>" class="text-xs text-rose-600 font-bold hover:underline">กำลังแก้ไข ID: #<?= $edit_task['id'] ?> (คลิกเพื่อยกเลิก)</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ฟอร์มสำหรับส่งข้อมูลวาง Task ทั้งหมด -->
                        <form id="pasteAllForm" action="edit_installation.php?id=<?= $id ?>" method="POST" class="hidden">
                            <input type="hidden" name="action" value="paste_all_tasks">
                            <input type="hidden" name="tasks_data" id="tasksDataInput">
                        </form>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[750px]">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600 uppercase">
                                        <th class="py-3 px-2 text-center w-12">No.</th>
                                        <th class="py-3 px-3">Task Description</th>
                                        <th class="py-3 px-2 text-center w-20">Progress</th>
                                        <th class="py-3 px-3 text-center w-36">Start Date</th>
                                        <th class="py-3 px-3 text-center w-36">End Date</th>
                                        <th class="py-3 px-2 text-center w-16">Day</th>
                                        <th class="py-3 px-3">Remark</th>
                                        <th class="py-3 px-2 text-center w-28">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs">
                                    <?php if (!empty($sub_tasks)): ?>
                                        <?php foreach ($sub_tasks as $index => $t): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-3 px-2 text-center font-bold text-slate-500"><?= $index + 1 ?></td>
                                            <td class="py-3 px-3 font-semibold text-slate-800"><?= htmlspecialchars($t['task_name']) ?></td>
                                            <td class="py-3 px-2 text-center">
                                                <span class="px-2 py-0.5 rounded-full font-bold <?= $t['progress'] == 100 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                                                    <?= $t['progress'] ?>%
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-center text-slate-600"><?= !empty($t['start_date']) ? date('d/m/Y', strtotime($t['start_date'])) : '-' ?></td>
                                            <td class="py-3 px-3 text-center text-slate-600"><?= !empty($t['end_date']) ? date('d/m/Y', strtotime($t['end_date'])) : '-' ?></td>
                                            <td class="py-3 px-2 text-center text-slate-600"><?= $t['duration_days'] ?></td>
                                            <td class="py-3 px-3 text-slate-500 italic"><?= htmlspecialchars($t['remark']) ?></td>
                                            <td class="py-3 px-2 text-center">
                                                <div class="flex items-center justify-center gap-1">
                                                    <!-- ปุ่มคัดลอก Task เดี่ยว -->
                                                    <button type="button" onclick='copyTask(<?= json_encode($t) ?>)' class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center transition-all" title="คัดลอก Task นี้">
                                                        <i class="fa-solid fa-copy text-xs"></i>
                                                    </button>
                                                    <a href="edit_installation.php?id=<?= $id ?>&edit_task_id=<?= $t['id'] ?>" class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center transition-all" title="แก้ไข">
                                                        <i class="fa-solid fa-pen text-xs"></i>
                                                    </a>
                                                    <a href="edit_installation.php?id=<?= $id ?>&delete_task_id=<?= $t['id'] ?>" onclick="return confirm('ต้องการลบงานย่อยนี้ใช่หรือไม่?');" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-all" title="ลบ">
                                                        <i class="fa-solid fa-trash text-xs"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- แถวสำหรับกรอกข้อมูลเพิ่ม/แก้ไข (แถวสุดท้ายต่อลงมา) -->
                                    <form action="edit_installation.php?id=<?= $id ?>" method="POST">
                                        <input type="hidden" name="action" value="<?= $edit_task ? 'edit_task' : 'add_task' ?>">
                                        <?php if ($edit_task): ?>
                                            <input type="hidden" name="task_id" value="<?= $edit_task['id'] ?>">
                                        <?php endif; ?>

                                        <tr class="bg-emerald-50/50 border-t-2 border-brandGreen">
                                            <td class="py-3 px-2 text-center font-bold text-brandGreen">
                                                <i class="fa-solid fa-arrow-right"></i>
                                            </td>
                                            <td class="py-3 px-3">
                                                <input type="text" name="task_name" required value="<?= htmlspecialchars($edit_task['task_name'] ?? '') ?>" placeholder="พิมพ์ชื่องานย่อยใหม่ที่นี่..." class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-brandGreen bg-white">
                                            </td>
                                            <td class="py-3 px-2 text-center">
                                                <input type="number" name="task_progress" min="0" max="100" value="<?= $edit_task['progress'] ?? 0 ?>" class="w-full px-1 py-1.5 text-center rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-brandGreen bg-white">
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <input type="date" name="task_start" value="<?= $edit_task['start_date'] ?? date('Y-m-d') ?>" class="w-full px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-brandGreen bg-white">
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <input type="date" name="task_end" value="<?= $edit_task['end_date'] ?? date('Y-m-d') ?>" class="w-full px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-brandGreen bg-white">
                                            </td>
                                            <td class="py-3 px-2 text-center">
                                                <input type="number" name="task_day" min="1" value="<?= $edit_task['duration_days'] ?? 1 ?>" class="w-full px-1 py-1.5 text-center rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-brandGreen bg-white">
                                            </td>
                                            <td class="py-3 px-3">
                                                <input type="text" name="task_remark" value="<?= htmlspecialchars($edit_task['remark'] ?? '') ?>" placeholder="หมายเหตุ..." class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:border-brandGreen bg-white">
                                            </td>
                                            <td class="py-3 px-2 text-center">
                                                <button type="submit" class="<?= $edit_task ? 'bg-amber-600 hover:bg-amber-700' : 'bg-brandGreen hover:bg-emerald-700' ?> text-white w-8 h-8 rounded-lg shadow-sm transition-all flex items-center justify-center mx-auto" title="<?= $edit_task ? 'บันทึกการแก้ไข' : 'เพิ่ม Task' ?>">
                                                    <i class="fa-solid <?= $edit_task ? 'fa-check' : 'fa-plus' ?> text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </form>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        // ฟังก์ชันเปิด-ปิด Sidebar สำหรับมือถือ
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        // ฟังก์ชันคัดลอก Task เดี่ยว
        function copyTask(taskData) {
            localStorage.setItem('copied_installation_task', JSON.stringify(taskData));
            checkCopiedTask();
            alert('คัดลอก Task "' + taskData.task_name + '" เรียบร้อยแล้ว!');
        }

        // ฟังก์ชันคัดลอก Task ทั้งหมด
        function copyAllTasks(tasksArray) {
            localStorage.setItem('copied_all_installation_tasks', JSON.stringify(tasksArray));
            checkCopiedTask();
            alert('คัดลอก Task ทั้งหมด (' + tasksArray.length + ' รายการ) เรียบร้อยแล้ว!');
        }

        // ฟังก์ชันวาง Task ทั้งหมด
        function pasteAllTasks() {
            const dataStr = localStorage.getItem('copied_all_installation_tasks');
            if (!dataStr) return;

            if (confirm('คุณต้องการวาง Task ทั้งหมดลงในโครงการนี้ใช่หรือไม่?')) {
                document.getElementById('tasksDataInput').value = dataStr;
                document.getElementById('pasteAllForm').submit();
            }
        }

        // ฟังก์ชันวาง Task เดี่ยวลงในฟอร์มด้านล่าง
        function pasteTask() {
            const dataStr = localStorage.getItem('copied_installation_task');
            if (!dataStr) return;
            const task = JSON.parse(dataStr);

            document.querySelector('input[name="task_name"]').value = task.task_name || '';
            document.querySelector('input[name="task_progress"]').value = task.progress || 0;
            if(task.start_date) document.querySelector('input[name="task_start"]').value = task.start_date;
            if(task.end_date) document.querySelector('input[name="task_end"]').value = task.end_date;
            document.querySelector('input[name="task_day"]').value = task.duration_days || 1;
            document.querySelector('input[name="task_remark"]').value = task.remark || '';

            const nameInput = document.querySelector('input[name="task_name"]');
            nameInput.focus();
            nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // ตรวจสอบข้อมูลใน localStorage เพื่อแสดงปุ่ม Paste
        function checkCopiedTask() {
            const pasteBtn = document.getElementById('pasteBtn');
            const pasteAllBtn = document.getElementById('pasteAllBtn');

            if (localStorage.getItem('copied_installation_task')) {
                pasteBtn.classList.remove('hidden');
                pasteBtn.classList.add('flex');
            } else {
                pasteBtn.classList.add('hidden');
                pasteBtn.classList.remove('flex');
            }

            if (localStorage.getItem('copied_all_installation_tasks')) {
                pasteAllBtn.classList.remove('hidden');
                pasteAllBtn.classList.add('flex');
            } else {
                pasteAllBtn.classList.add('hidden');
                pasteAllBtn.classList.remove('flex');
            }
        }

        window.addEventListener('DOMContentLoaded', checkCopiedTask);
    </script>
</body>
</html>