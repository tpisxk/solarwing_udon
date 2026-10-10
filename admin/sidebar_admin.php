<?php
// ตรวจสอบชื่อไฟล์ปัจจุบันเพื่อใช้ไฮไลต์เมนูที่กำลังใช้งานอยู่
$current_page = basename($_SERVER['PHP_SELF'], ".php");

$display_name = $_SESSION['name'] ?? '';
$display_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Admin';
// นับจำนวนผู้ใช้งานทั้งหมดในตาราง users
try {
    $stmt_user_count = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt_user_count->fetchColumn();
} catch (PDOException $e) {
    $user_count = 0; // เผื่อกรณีเกิด Error ให้แสดงเป็น 0
}
?>
<!-- 🌐 ส่วนเมนูด้านซ้าย (Sidebar) -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รับเข้าสินค้า - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/SW.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Prompt', 'Inter', 'sans-serif']
                    },
                    colors: {
                        darkSidebar: '#000000',
                        brandGreen: '#059669'
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar (กำหนด id สำหรับควบคุมเฉพาะส่วนนี้) -->
    <aside id="appSidebar" class="w-64 bg-darkSidebar text-slate-300 flex flex-col h-full shrink-0 border-r border-slate-800 transition-colors duration-200">

        <!-- Header โลโก้ -->
        <div id="sidebarHeader" class="h-20 flex items-center px-6 border-b border-slate-800/80 gap-3 transition-colors duration-200">
            <img src="../Pic/SW.png" alt="SW.png" class="w-8 h-8 object-contain">
            <h1 id="sidebarTitle" class="text-white font-bold text-lg tracking-tight">SolarWing <span class="text-emerald-500">Stock</span></h1>
        </div>

        <!-- รายการเมนู -->
        <div class="flex-1 overflow-y-auto py-5 px-3.5 space-y-1.5 text-xs font-medium">
            <a href="dashboard_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'dashboard_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-layer-group w-5 text-center text-sm"></i> ข้อมูล Dashboard
            </a>
            <a href="calendar_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'calendar_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-calendar-alt w-5 text-center text-sm"></i> Calendar (ปฏิทินงาน)
            </a>
            <a href="inventory_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'inventory_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-boxes w-5 text-center text-sm"></i> สต็อกคงเหลือ
            </a>
            <a href="stockin_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'stockin_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-cart-arrow-down w-5 text-center text-sm"></i> รับเข้าสินค้า
            </a>
            <a href="stockout_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'stockout_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-truck-loading w-5 text-center text-sm"></i> จ่ายออกสินค้า
            </a>
            <a href="expenses_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'expenses_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-receipt w-5 text-center text-sm"></i> ค่าใช้จ่ายอื่นๆ
            </a>
            <a href="addproduct_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'addproduct_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-plus-circle w-5 text-center text-sm"></i> เพิ่มสินค้าใหม่
            </a>
            
            <a href="admin_requisitions.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'admin_requisitions') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-circle-check w-5 text-center text-sm"></i> ตรวจสอบและอนุมัติใบเบิกของ
            </a>


            <?php
            // ตรวจสอบว่าอยู่หน้าในกลุ่มสถานที่ติดตั้งหรือไม่
            $is_location_active = ($current_page == 'add_installation_admin' || $current_page == 'project_list_admin');
            ?>
            <!-- 📂 เมนูสถานที่ติดตั้ง (กางค้างไว้ทันทีเมื่ออยู่หน้าย่อย) -->
            <div class="space-y-1">
                <button onclick="toggleLocationsDropdown()" class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-2xl transition-all <?= $is_location_active ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i class="fas fa-map-marker-alt w-5 text-center text-sm"></i>
                        <span>สถานที่ติดตั้ง</span>
                    </div>
                    <i id="locChevron" class="fa-solid fa-chevron-down text-xs transition-transform duration-500 <?= $is_location_active ? 'rotate-180' : '' ?>"></i>
                </button>

                <!-- เมนูย่อย: กำหนด max-h ทันทีใน PHP ถ้าอยู่ในหน้าย่อย -->
                <div id="locationsSubmenu" style="max-height: <?= $is_location_active ? '120px' : '0px' ?>;" class="overflow-hidden transition-all duration-500 ease-in-out pl-9 pr-2 space-y-1">
                    <a href="add_installation_admin.php" class="block px-3 py-2 rounded-xl text-xs font-medium transition-all <?= ($current_page == 'add_installation_admin') ? 'bg-emerald-800/60 text-white font-bold' : 'text-slate-400 hover:text-white hover:bg-slate-800/40' ?>">
                        <i class="fa-solid fa-list-ul mr-2"></i> จัดการสถานที่ติดตั้ง
                    </a>
                    <a href="project_list_admin.php" class="block px-3 py-2 rounded-xl text-xs font-medium transition-all <?= ($current_page == 'project_list_admin') ? 'bg-emerald-800/60 text-white font-bold' : 'text-slate-400 hover:text-white hover:bg-slate-800/40' ?>">
                        <i class="fa-solid fa-file-invoice-dollar mr-2"></i> รายการโครงการและต้นทุน
                    </a>
                </div>
            </div>

            <a href="expenses_summary_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'expenses_summary_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <i class="fas fa-file-invoice w-5 text-center text-sm"></i> สรุปค่าใช้จ่าย
            </a>

            <div class="pt-3 pb-2">
                <hr id="sidebarDivider" class="border-slate-800/80 transition-colors duration-200">
            </div>

            <!-- <a href="orders_admin.php" class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'orders_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <div class="flex items-center gap-3.5"><i class="fas fa-shopping-bag w-5 text-center text-sm"></i> สั่งซื้อสินค้า</div>
                <span class="badge-count bg-slate-800 text-slate-400 text-[10px] font-bold px-2 py-0.5 rounded-full">0</span>
            </a>
            <a href="pending_admin.php" class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'pending_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <div class="flex items-center gap-3.5"><i class="fas fa-shipping-fast w-5 text-center text-sm"></i> รอรับสินค้า</div>
                <span class="bg-emerald-950/80 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-800/50">0</span>
            </a> -->
            <a href="manage_users.php" class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'manage_users') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <div class="flex items-center gap-3.5">
                    <i class="fas fa-circle-user w-5 text-center text-sm"></i> จัดการผู้ใช้งาน
                </div>
                <span class="badge-count bg-emerald-950/80 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-800/50">
                    <?= number_format($user_count) ?>
                </span>
            </a>
            <a href="add_user.php" class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'add_user') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                <div class="flex items-center gap-3.5"><i class="fas fa-user-plus w-5 text-center text-sm"></i> เพิ่มผู้ใช้งาน</div>
                <span class="badge-count bg-emerald-950/80 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-800/50">0</span>
            </a>
        </div>

        <!-- 👤 ส่วนข้อมูลผู้ใช้, ปุ่มเปลี่ยนธีม Sidebar และปุ่มออกจากระบบ -->
        <div id="sidebarFooter" class="p-4 border-t border-slate-800/80 bg-black/40 space-y-3 transition-colors duration-200">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                        <i class="fas fa-user-shield text-sm"></i>
                    </div>
                    <div class="overflow-hidden">
                        <h4 id="userNameText" class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars($display_name); ?></h4>
                        <span class="inline-block mt-0.5 text-[10px] bg-emerald-900 text-emerald-400 px-2 py-0.5 rounded-md border border-emerald-800/40 font-medium">
                            <?php echo htmlspecialchars($display_role); ?>
                        </span>
                    </div>
                </div>

                <!-- 🌓 ปุ่มสลับโหมด Sidebar -->
                <button onclick="toggleSidebarTheme()" id="themeToggleBtn" title="สลับโหมดแถบเมนู"
                    class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 flex items-center justify-center transition border border-slate-700/60 shrink-0">
                    <i id="themeIcon" class="fas fa-sun text-xs"></i>
                </button>
            </div>

            <a href="../logout.php" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');"
                class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 text-xs font-semibold transition border border-rose-500/20">
                <i class="fas fa-sign-out-alt text-xs"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <!-- สคริปต์สลับสีเฉพาะ Sidebar และบันทึกค่าผ่าน localStorage -->
    <script>
        const sidebar = document.getElementById('appSidebar');
        const sidebarHeader = document.getElementById('sidebarHeader');
        const sidebarTitle = document.getElementById('sidebarTitle');
        const sidebarDivider = document.getElementById('sidebarDivider');
        const sidebarFooter = document.getElementById('sidebarFooter');
        const userNameText = document.getElementById('userNameText');
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        const menuItems = document.querySelectorAll('.menu-item');
        const badgeCounts = document.querySelectorAll('.badge-count');

        // ฟังก์ชันเปิด/ปิด Dropdown ของสถานที่ติดตั้ง (ความเร็ว 500ms)
        function toggleLocationsDropdown() {
            const submenu = document.getElementById('locationsSubmenu');
            const chevron = document.getElementById('locChevron');

            if (submenu.style.maxHeight && submenu.style.maxHeight !== "0px") {
                submenu.style.maxHeight = "0px";
                chevron.classList.remove('rotate-180');
            } else {
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                chevron.classList.add('rotate-180');
            }
        }

        // ตรวจสอบค่าที่บันทึกไว้ในเบราว์เซอร์
        if (localStorage.getItem('sidebar_theme') === 'light') {
            applyLightSidebar();
        } else {
            applyDarkSidebar();
        }

        function toggleSidebarTheme() {
            if (sidebar.classList.contains('bg-darkSidebar')) {
                applyLightSidebar();
                localStorage.setItem('sidebar_theme', 'light');
            } else {
                applyDarkSidebar();
                localStorage.setItem('sidebar_theme', 'dark');
            }
        }

        function applyLightSidebar() {
            // เปลี่ยนเป็นธีม Sidebar สว่าง
            sidebar.className = "w-64 bg-white text-slate-700 flex flex-col h-full shrink-0 border-r border-slate-200 transition-colors duration-200";
            sidebarHeader.className = "h-20 flex items-center px-6 border-b border-slate-200 gap-3 transition-colors duration-200";
            sidebarTitle.className = "text-slate-900 font-bold text-lg tracking-tight";
            sidebarDivider.className = "border-slate-200 transition-colors duration-200";
            sidebarFooter.className = "p-4 border-t border-slate-200 bg-slate-50 space-y-3 transition-colors duration-200";
            userNameText.className = "text-xs font-bold text-slate-900 truncate";

            themeToggleBtn.className = "w-9 h-9 rounded-xl bg-slate-200 hover:bg-slate-300 text-amber-500 flex items-center justify-center transition border border-slate-300 shrink-0";
            themeIcon.className = "fas fa-moon text-xs text-slate-700";

            menuItems.forEach(item => {
                if (!item.classList.contains('bg-brandGreen')) {
                    item.className = "menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all text-slate-600 hover:bg-slate-100 hover:text-slate-900";
                }
            });

            badgeCounts.forEach(badge => {
                badge.className = "badge-count bg-slate-200 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full";
            });
        }

        function applyDarkSidebar() {
            // คืนค่าเป็นธีม Sidebar มืด (ค่าเริ่มต้น)
            sidebar.className = "w-64 bg-darkSidebar text-slate-300 flex flex-col h-full shrink-0 border-r border-slate-800 transition-colors duration-200";
            sidebarHeader.className = "h-20 flex items-center px-6 border-b border-slate-800/80 gap-3 transition-colors duration-200";
            sidebarTitle.className = "text-white font-bold text-lg tracking-tight";
            sidebarDivider.className = "border-slate-800/80 transition-colors duration-200";
            sidebarFooter.className = "p-4 border-t border-slate-800/80 bg-black/40 space-y-3 transition-colors duration-200";
            userNameText.className = "text-xs font-bold text-white truncate";

            themeToggleBtn.className = "w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 flex items-center justify-center transition border border-slate-700/60 shrink-0";
            themeIcon.className = "fas fa-sun text-xs";

            menuItems.forEach(item => {
                if (!item.classList.contains('bg-brandGreen')) {
                    item.className = "menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all text-slate-300 hover:bg-slate-800/60 hover:text-white";
                }
            });

            badgeCounts.forEach(badge => {
                badge.className = "badge-count bg-slate-800 text-slate-400 text-[10px] font-bold px-2 py-0.5 rounded-full";
            });
        }
        // ตรวจสอบและบังคับใช้ธีม Sidebar ทันทีที่โหลดหน้า เพื่อป้องกันการกะพริบหรือสลับเอง
        (function() {
            if (localStorage.getItem('sidebar_theme') === 'light') {
                document.addEventListener('DOMContentLoaded', () => {
                    if (typeof applyLightSidebar === 'function') {
                        applyLightSidebar();
                    }
                });
            }
        })();
    </script>
</body>

</html>