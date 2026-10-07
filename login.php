<?php
session_start();

require_once 'db/conn.php'; // ไฟล์เชื่อมต่อฐานข้อมูลของคุณ

$login_success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            // ดึงข้อมูลผู้ใช้ พร้อม JOIN กับตาราง user_type
            $stmt = $pdo->prepare("
                SELECT u.*, ut.type_name 
                FROM users u 
                JOIN user_type ut ON u.user_type_id = ut.id 
                WHERE u.username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $is_valid = false;

            if ($user) {
                // 1. ตรวจสอบรหัสผ่านที่ถูก Hash ไว้ในฐานข้อมูล
                if (password_verify($password, $user['password'])) {
                    $is_valid = true;
                } 
                // 2. รองรับผู้ใช้เก่าที่รหัสผ่านยังเป็น Plaintext (เปรียบเทียบตรงๆ)
                else if ($password === $user['password']) {
                    $is_valid = true;

                    // 🔒 อัปเดตรหัสผ่านธรรมดาให้กลายเป็น Hash ทันทีเพื่อความปลอดภัย
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$new_hash, $user['id']]);
                }
            }

            if ($is_valid) {
                // บันทึกข้อมูลลงใน Session
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['name']         = $user['name']; 
                $_SESSION['role']         = strtolower($user['type_name']); 
                $_SESSION['user_type_id'] = $user['user_type_id']; // รหัสสิทธิ์ (1=Admin, 2=CEO, 3=User)
                
                $login_success = true; 
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล: ' . $e->getMessage();
        }
    } else {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Pic/456456.png">
    <title>เข้าสู่ระบบ - Solarwing Management</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 สำหรับกล่องแจ้งเตือนสำเร็จ -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 bg-[url('Pic/bg3.png')] bg-cover bg-center bg-no-repeat">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden p-8 space-y-8">
        <!-- Header Logo / Title -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-20 h-20">
                <img src="Pic/SW.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Solarwing Stock Management</h1>
            <p class="text-xs text-slate-500">กรุณาเข้าสู่ระบบเพื่อจัดการอุปกรณ์และคลังสินค้า</p>
        </div>

        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs flex items-center space-x-2 animate-shake">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="loginForm" action="login.php" method="POST" class="space-y-5">
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 block">ชื่อผู้ใช้งาน (Username)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                        <i class="fa-regular fa-user"></i>
                    </span>
                    <input type="text" name="username" required 
                           class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition"
                           placeholder="ระบุชื่อผู้ใช้งาน"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 block">รหัสผ่าน (Password)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                        <i class="fa-regular fa-lock"></i>
                    </span>
                    <input type="password" name="password" required 
                           class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition"
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" id="submitBtn"
                    class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-2xl shadow-lg shadow-emerald-600/30 transition-all text-sm flex items-center justify-center space-x-2">
                <span id="btnText">เข้าสู่ระบบ</span>
                <i id="btnIcon" class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>
        </form>

        <!-- Footer Note -->
        <div class="text-center pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400">Solar Cell Equipment Management &copy; 2026</p>
        </div>
    </div>

    <!-- JavaScript สำหรับจัดการ Spinner และ SweetAlert2 -->
    <script>
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');

        loginForm.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
            btnText.innerText = 'กำลังตรวจสอบ...';
            btnIcon.className = 'fa-solid fa-circle-notch fa-spin';
        });

        <?php if ($login_success): ?>
        Swal.fire({
            icon: 'success',
            title: 'เข้าสู่ระบบสำเร็จ!',
            text: 'กำลังพาท่านเข้าสู่ระบบ...',
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: false,
            didClose: () => {
                redirectUser();
            }
        });

        setTimeout(() => {
            redirectUser();
        }, 1500);

        function redirectUser() {
            const userTypeId = "<?php echo $_SESSION['user_type_id']; ?>";
            
            if (userTypeId == '1') {
                window.location.href = 'admin/dashboard_admin.php'; // สิทธิ์ Admin
            } else if (userTypeId == '2') {
                window.location.href = 'ceo/dashboard.php';         // สิทธิ์ CEO
            } else {
                window.location.href = 'User/dashboard.php';        // สิทธิ์ User ทั่วไป
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>