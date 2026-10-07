<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'add_user';

$message = '';
$message_type = '';

// บันทึกข้อมูลเมื่อกดปุ่ม Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? 'Staff';
    $user_type_id = intval($_POST['user_type_id'] ?? 3);

    if (empty($username) || empty($password) || empty($name)) {
        $message = 'กรุณากรอกข้อมูลที่จำเป็น (*) ให้ครบถ้วน';
        $message_type = 'error';
    } else {
        try {
            // ตรวจสอบชื่อผู้ใช้ซ้ำ
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_check->execute([$username]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $message = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาใช้ชื่อผู้ใช้อื่น';
                $message_type = 'error';
            } else {
                // บันทึกข้อมูลเข้าตาราง users
                $stmt_insert = $pdo->prepare("INSERT INTO users (username, password, user_type_id, name, email, phone, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt_insert->execute([$username, $password, $user_type_id, $name, $email, $phone, $role]);

                $message = 'บันทึกข้อมูลผู้ใช้งานใหม่เรียบร้อยแล้ว!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล: ' . $e->getMessage();
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
    <title>เพิ่มผู้ใช้งานใหม่ - SolarStock Pro</title>
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
    <style>::-webkit-scrollbar { width: 6px; height: 6px; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <?php 
    if (file_exists('sidebar_admin.php')) {
        include 'sidebar_admin.php'; 
    } elseif (file_exists('sidebar.php')) {
        include 'sidebar.php';
    }
    ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">เพิ่มผู้ใช้งานใหม่</h2>
                <p class="text-sm text-slate-500 mt-0.5">สร้างบัญชีผู้ใช้งานใหม่สำหรับเข้าใช้งานระบบจัดการสต็อก</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="manage_users.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> กลับหน้ารายการผู้ใช้
                </a>
            </div>
        </header>

        <!-- Main Content Area (จัดให้อยู่ตรงกลาง) -->
        <div class="flex-1 overflow-y-auto p-8 w-full">
            <div class="max-w-3xl mx-auto space-y-6">
            
                <!-- แจ้งเตือนข้อความ Success / Error -->
                <?php if (!empty($message)): ?>
                    <div class="px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2 <?= $message_type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-700' ?>">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?>"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <!-- ฟอร์มกรอกข้อมูล -->
                <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm p-8">
                    <div class="flex items-center gap-3 pb-6 border-b border-slate-100 mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg shadow-inner">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">ข้อมูลบัญชีผู้ใช้งาน</h3>
                            <p class="text-xs text-slate-500">กรอกข้อมูลรายละเอียดด้านล่างเพื่อทำการลงทะเบียนผู้ใช้ใหม่</p>
                        </div>
                    </div>

                    <form action="add_user.php" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- ชื่อผู้ใช้ (username) -->
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    ชื่อผู้ใช้ (Username) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                        <i class="fa-solid fa-user text-sm"></i>
                                    </span>
                                    <input type="text" name="username" required placeholder="เช่น Tpisxk"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-slate-200 focus:outline-none focus:border-brandGreen focus:ring-2 focus:ring-brandGreen/20 text-sm transition-all">
                                </div>
                            </div>

                            <!-- รหัสผ่าน (password) -->
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    รหัสผ่าน (Password) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                        <i class="fa-solid fa-lock text-sm"></i>
                                    </span>
                                    <input type="password" name="password" required placeholder="กรอกรหัสผ่าน"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-slate-200 focus:outline-none focus:border-brandGreen focus:ring-2 focus:ring-brandGreen/20 text-sm transition-all">
                                </div>
                            </div>

                            <!-- ชื่อ - นามสกุล (name) -->
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    ชื่อ - นามสกุล (Name) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                        <i class="fa-solid fa-id-card text-sm"></i>
                                    </span>
                                    <input type="text" name="name" required placeholder="เช่น Apisak doolsorn"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-slate-200 focus:outline-none focus:border-brandGreen focus:ring-2 focus:ring-brandGreen/20 text-sm transition-all">
                                </div>
                            </div>

                            <!-- เบอร์โทรศัพท์ (phone) -->
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    เบอร์โทรศัพท์ (Phone)
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                        <i class="fa-solid fa-phone text-sm"></i>
                                    </span>
                                    <input type="text" name="phone" placeholder="เช่น 0656486319"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-slate-200 focus:outline-none focus:border-brandGreen focus:ring-2 focus:ring-brandGreen/20 text-sm transition-all">
                                </div>
                            </div>

                            <!-- อีเมล (email) -->
                            <div class="space-y-2 md:col-span-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    อีเมล (Email)
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                                        <i class="fa-solid fa-envelope text-sm"></i>
                                    </span>
                                    <input type="email" name="email" placeholder="example@domain.com"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-slate-200 focus:outline-none focus:border-brandGreen focus:ring-2 focus:ring-brandGreen/20 text-sm transition-all">
                                </div>
                            </div>

                            <!-- ตำแหน่ง/สิทธิ์การใช้งาน (role / user_type_id) -->
                            <div class="space-y-2 md:col-span-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    ตำแหน่ง / สิทธิ์การใช้งาน (Role) <span class="text-rose-500">*</span>
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="flex items-center gap-3 p-4 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-all">
                                        <input type="radio" name="role" value="Staff" checked onclick="document.getElementById('user_type_id').value=3" class="w-4 h-4 text-brandGreen focus:ring-brandGreen">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">พนักงาน (Staff)</p>
                                            
                                        </div>
                                    </label>
                                    <label class="flex items-center gap-3 p-4 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-all">
                                        <input type="radio" name="role" value="admin" onclick="document.getElementById('user_type_id').value=1" class="w-4 h-4 text-brandGreen focus:ring-brandGreen">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">ผู้ดูแลระบบ (Admin)</p>
                                            
                                        </div>
                                    </label>
                                </div>
                                <input type="hidden" name="user_type_id" id="user_type_id" value="3">
                            </div>

                        </div>

                        <!-- ปุ่มดำเนินการ -->
                        <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button type="reset" class="px-6 py-2.5 rounded-2xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-100 transition-all">
                                ล้างข้อมูล
                            </button>
                            <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-2.5 rounded-2xl text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk text-xs"></i> บันทึกผู้ใช้งาน
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </main>

</body>
</html>