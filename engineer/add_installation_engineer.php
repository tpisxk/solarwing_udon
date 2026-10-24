<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'add_installation_engineer';
$message = '';
$message_type = '';
$swal_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name                 = trim($_POST['name'] ?? '');
    $system_size          = trim($_POST['system_size'] ?? '');
    $installation_address = trim($_POST['installation_address'] ?? '');
    $map_url              = trim($_POST['map_url'] ?? '');
    $status               = 'In Progress';

    // นำสถานที่ติดตั้งหน้างานเก็บลงในคอลัมน์ description
    $description = $installation_address;

    if (empty($name)) {
        $message = 'กรุณากรอกชื่อโครงการ (Project Name)';
        $message_type = 'error';
    } else {
        try {
            // บันทึกข้อมูลลงตาราง installations ครบถ้วนตามโครงสร้างจริง
            $stmt = $pdo->prepare("INSERT INTO installations (name, system_size, description, map_url, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $system_size, $description, $map_url, $status]);

            $swal_success = true;
        } catch (PDOException $e) {
            $message = 'เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Site Form - Solarwing Engineer</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar สำหรับ Desktop และ Mobile -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto">
        <?php 
        if (file_exists('sidebar_engineer.php')) {
            include 'sidebar_engineer.php';
        } elseif (file_exists('sidebar.php')) {
            include 'sidebar.php';
        }
        ?>
    </div>

    <!-- Backdrop สำหรับมือถือ -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">ENGINEER SITE FORM</h2>
                    <p class="text-[11px] md:text-xs text-slate-500 mt-0.5">ฟอร์มบันทึกสถานที่หน้างานสำหรับวิศวกร</p>
                </div>
            </div>
            <div>
                <a href="work_progress.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">กลับหน้ารายการ</span>
                </a>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-2xl mx-auto space-y-6">

                <?php if (!empty($message)): ?>
                    <div class="px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2 <?= $message_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?>"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6">
                    <div class="flex items-center gap-3 pb-6 border-b border-slate-100">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg shadow-inner shrink-0">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">บันทึกข้อมูลสถานที่หน้างาน</h3>
                            <p class="text-xs text-slate-500">กรอกข้อมูลเฉพาะสถานที่และพิกัดโครงการ</p>
                        </div>
                    </div>

                    <form action="" method="POST" class="space-y-6">
                        
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase">ชื่อโครงการ (Project Name) *</label>
                            <input type="text" name="name" required placeholder="ระบุชื่อโครงการ..." class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase">ขนาดติดตั้ง (System Capacity)</label>
                            <input type="text" name="system_size" placeholder="เช่น 108.36 kWp" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase">สถานที่ติดตั้งหน้างาน (Installation Address)</label>
                            <textarea name="installation_address" rows="3" placeholder="ระบุรายละเอียดสถานที่ติดตั้งหน้างาน..." class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen"></textarea>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-600 uppercase">ลิงก์แผนที่ Google Maps (Map URL)</label>
                                <a href="https://www.google.com/maps" target="_blank" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                                    <i class="fa-solid fa-external-link-alt text-[10px]"></i> เปิด Google Maps เพื่อหาพิกัด
                                </a>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <i class="fa-solid fa-link text-xs"></i>
                                </span>
                                <input type="text" name="map_url" id="map_url" placeholder="วางลิงก์ Google Maps ที่นี่ (เช่น https://maps.app.goo.gl/... หรือ https://goo.gl/maps/...)" class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <p class="text-[11px] text-slate-400">วิธีใส่: ไปที่ Google Maps > ค้นหาหรือปักหมุดสถานที่หน้างาน > กดปุ่ม <b>"แชร์ (Share)"</b> > กด <b>"คัดลอกลิงก์ (Copy Link)"</b> แล้วนำมาวางในช่องนี้</p>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-end pt-4 border-t border-slate-100 gap-3">
                            <a href="work_progress.php" class="px-6 py-3 rounded-2xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-100 transition-all text-center">
                                ยกเลิก
                            </a>
                            <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-floppy-disk text-xs"></i> บันทึกข้อมูลสถานที่
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        <?php if ($swal_success): ?>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ!',
            text: 'ข้อมูลสถานที่หน้างานถูกบันทึกลงระบบเรียบร้อยแล้ว',
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#059669',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = window.location.pathname; // รีเฟรชอยู่หน้าเดิม
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>