<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'profile';
$user_id = $_SESSION['user_id'] ?? null;
$success_msg = $error_msg = '';

// ดึงข้อมูลผู้ใช้ปัจจุบันจากฐานข้อมูล
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $user = ['name' => $_SESSION['name'] ?? '', 'username' => $_SESSION['username'] ?? '', 'profile_img' => ''];
    }
} catch (PDOException $e) {
    $user = ['name' => $_SESSION['name'] ?? '', 'profile_img' => ''];
}

// เมื่อมีการกดบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_img_path = $user['profile_img'] ?? '';

    if (empty($name)) {
        $error_msg = "กรุณากรอกชื่อ-นามสกุล";
    } else {
        try {
            // จัดการการอัปโหลดรูปโปรไฟล์
            if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_img']['tmp_name'];
                $file_name = $_FILES['profile_img']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($file_ext, $allowed_exts)) {
                    $new_file_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                    $upload_dir = '../uploads/avatars/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $destination = $upload_dir . $new_file_name;
                    if (move_uploaded_file($file_tmp, $destination)) {
                        // ลบรูปเก่าทิ้งถ้ามี
                        if (!empty($user['profile_img']) && file_exists('../' . $user['profile_img'])) {
                            @unlink('../' . $user['profile_img']);
                        }
                        $profile_img_path = 'uploads/avatars/' . $new_file_name;
                        $_SESSION['profile_img'] = $profile_img_path;
                    }
                } else {
                    throw new Exception("รองรับเฉพาะไฟล์รูปภาพ JPG, JPEG, PNG และ WEBP เท่านั้น");
                }
            }

            // อัปเดตรหัสผ่านและข้อมูลลงฐานข้อมูล
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    throw new Exception("รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน");
                }
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET name = ?, password = ?, profile_img = ? WHERE id = ?");
                $update_stmt->execute([$name, $hashed_password, $profile_img_path, $user_id]);
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET name = ?, profile_img = ? WHERE id = ?");
                $update_stmt->execute([$name, $profile_img_path, $user_id]);
            }

            $_SESSION['name'] = $name;
            $success_msg = "อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว!";
            
            // โหลดข้อมูลล่าสุดมาแสดง
            $user['name'] = $name;
            $user['profile_img'] = $profile_img_path;

        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโปรไฟล์ - Solarwing Engineer</title>
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

    <!-- ดึง Sidebar ให้ถูกต้องตามโครงสร้างโฟลเดอร์ -->
    <?php 
    if (file_exists('sidebar_engineer.php')) {
        include 'sidebar_engineer.php';
    } elseif (file_exists('../engineer/sidebar_engineer.php')) {
        include '../engineer/sidebar_engineer.php';
    }
    ?>

    <!-- ส่วนเนื้อหาหลัก -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 shadow-sm">
            <h2 class="text-base md:text-xl font-bold text-slate-900">
                <i class="fa-solid fa-user-gear text-emerald-600 me-2"></i> จัดการข้อมูลส่วนตัว (Profile)
            </h2>
        </header>

        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <div class="max-w-xl mx-auto space-y-6">

                <?php if ($success_msg): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-xs font-bold shadow-sm">
                        <i class="fa-solid fa-circle-check me-1"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-xs font-bold shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 lg:p-8">
                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                        
                        <!-- พรีวิวและส่วนเปลี่ยนรูปโปรไฟล์ -->
                        <div class="flex flex-col sm:flex-row items-center gap-5 pb-5 border-b border-slate-100">
                            <div class="relative group">
                                <?php if (!empty($user['profile_img']) && file_exists('../' . $user['profile_img'])): ?>
                                    <img id="avatarPreview" src="../<?= htmlspecialchars($user['profile_img']) ?>" 
                                        class="w-20 h-20 rounded-2xl object-cover border-2 border-emerald-500 shadow-sm cursor-pointer hover:opacity-90 transition"
                                        onclick="openImageModal('../<?= htmlspecialchars($user['profile_img']) ?>')"
                                        title="คลิกเพื่อดูรูปขนาดใหญ่">
                                <?php else: ?>
                                    <div id="avatarDefault" class="w-20 h-20 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl font-bold border-2 border-emerald-500/30">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                    <img id="avatarPreview" src="" class="w-20 h-20 rounded-2xl object-cover border-2 border-emerald-500 shadow-sm hidden cursor-pointer" onclick="openImageModal(this.src)">
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-black/30 rounded-2xl flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer pointer-events-none">
                                    <i class="fa-solid fa-magnifying-glass-plus text-white text-sm"></i>
                                </div>
                            </div>

                            <div class="flex-1 text-center sm:text-left space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-700">เปลี่ยนรูปโปรไฟล์</label>
                                <input type="file" name="profile_img" id="imgInput" accept="image/*" onchange="previewImage(this)"
                                    class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                                <p class="text-[10px] text-slate-400">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 2MB (คลิกที่รูปเพื่อดูภาพใหญ่)</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600">ชื่อ - นามสกุล <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:border-emerald-500 font-medium transition">
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <h4 class="text-xs font-bold text-slate-700 mb-3"><i class="fa-solid fa-lock text-amber-500 me-1"></i> เปลี่ยนรหัสผ่าน (หากไม่ต้องการเปลี่ยนให้เว้นว่างไว้)</h4>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" placeholder="••••••••" 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:border-emerald-500 font-medium transition">
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="confirm_password" placeholder="••••••••" 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:border-emerald-500 font-medium transition">
                        </div>

                        <div class="pt-4 flex justify-end gap-3">
                            <a href="dashboard.php" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-bold transition">
                                <i class="fa-solid fa-arrow-left me-1"></i> กลับหน้าหลัก
                            </a>
                            <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-emerald-600/20 transition">
                                <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal สำหรับแสดงรูปภาพขนาดใหญ่ -->
    <div id="imageModal" class="fixed inset-0 bg-slate-950/80 z-50 hidden flex items-center justify-center p-4 backdrop-blur-xs" onclick="closeImageModal()">
        <div class="relative max-w-lg w-full bg-white rounded-3xl p-3 shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
            <button onclick="closeImageModal()" class="absolute top-4 right-4 w-9 h-9 bg-slate-100 hover:bg-rose-50 hover:text-rose-600 rounded-full flex items-center justify-center text-slate-600 transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
            <div class="p-2 text-center">
                <h3 class="text-xs font-bold text-slate-700 mb-3">รูปภาพโปรไฟล์</h3>
                <img id="modalImg" src="" class="w-full max-h-[70vh] object-contain rounded-2xl border border-slate-100">
            </div>
        </div>
    </div>

    <!-- Script ควบคุมพรีวิวและ Modal ดูรูป -->
    <script>
        function previewImage(input) {
            const preview = document.getElementById('avatarPreview');
            const defaultBg = document.getElementById('avatarDefault');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    preview.setAttribute('onclick', `openImageModal('${e.target.result}')`);
                    if (defaultBg) defaultBg.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openImageModal(imgSrc) {
            if (!imgSrc) return;
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImg');
            modalImg.src = imgSrc;
            modal.classList.remove('hidden');
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>