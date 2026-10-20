<?php
// ตรวจสอบชื่อไฟล์ปัจจุบันเพื่อใช้ไฮไลต์เมนูที่กำลังใช้งานอยู่
$current_page = basename($_SERVER['PHP_SELF'], ".php");

$display_name = $_SESSION['name'] ?? '';
$display_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Admin';

// แปลงค่าสิทธิ์เป็นตัวพิมพ์เล็กเพื่อตรวจสอบ
$role_lower = strtolower(trim($display_role));
$isAdmin    = ($role_lower === 'admin' || strpos($role_lower, 'admin') !== false);
$isEngineer = ($role_lower === 'engineer' || strpos($role_lower, 'engineer') !== false);

// นับจำนวนผู้ใช้งานทั้งหมดในตาราง users
try {
    $stmt_user_count = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt_user_count->fetchColumn();
} catch (PDOException $e) {
    $user_count = 0;
}

// นับจำนวนใบเบิกที่รอตรวจสอบ (Pending) เพื่อนำมาแสดงแจ้งเตือนที่ Sidebar
try {
    $stmt_req_count = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending'");
    $pending_requisitions_count = $stmt_req_count->fetchColumn();
} catch (PDOException $e) {
    $pending_requisitions_count = 0;
}

// ตรวจสอบสถานะหน้าปัจจุบัน เพื่อเปิด Dropdown หมวดหมู่นั้นๆ ค้างไว้โดยอัตโนมัติ (ไม่ให้กะพริบ)
$is_cat_inventory = in_array($current_page, ['inventory_admin', 'stockin_admin', 'stockout_admin', 'addproduct_admin', 'admin_requisitions', 'admin_procurement']);
$is_cat_location  = in_array($current_page, ['add_installation_admin', 'project_list_admin', 'view_installation_admin', 'work_progress_admin', 'requisition_form_admin']);
$is_cat_finance   = in_array($current_page, ['expenses_admin', 'expenses_summary_admin', 'billing_admin']);
$is_cat_users     = in_array($current_page, ['manage_users', 'add_user']);
?>
<!-- 🌐 ส่วนเมนูด้านซ้าย (Sidebar) -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/SW.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: { darkSidebar: '#000000', brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        
        /* สไตล์เปิดปิด Dropdown แบบสมูท */
        .submenu-container {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.25s ease-out;
        }
        .submenu-container.open {
            grid-template-rows: 1fr;
        }
        .submenu-content {
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar (ปรับให้ความสูงเต็มจอพอดีด้วย h-screen sticky top-0) -->
    <aside id="appSidebar" class="w-64 bg-darkSidebar text-slate-300 flex flex-col h-screen sticky top-0 shrink-0 border-r border-slate-800 transition-colors duration-200">

        <!-- Header โลโก้ -->
        <div id="sidebarHeader" class="h-20 flex items-center px-6 border-b border-slate-800/80 gap-3 transition-colors duration-200 shrink-0">
            <img src="../Pic/SW.png" alt="SW.png" class="w-8 h-8 object-contain">
            <h1 id="sidebarTitle" class="text-white font-bold text-lg tracking-tight">SolarWing <span class="text-emerald-500">Management</span></h1>
        </div>

        <!-- รายการเมนูหลัก -->
        <div class="flex-1 overflow-y-auto py-4 px-3.5 space-y-2 text-xs font-medium">

            <!-- เมนูหน้าหลัก (Dashboard & Calendar) -->
            <div class="space-y-1">
                <a href="dashboard_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'dashboard_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                    <i class="fas fa-layer-group w-5 text-center text-sm"></i> ข้อมูล Dashboard
                </a>
                <a href="calendar_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'calendar_admin') ? 'bg-brandGreen text-white shadow-md shadow-emerald-900/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                    <i class="fas fa-calendar-alt w-5 text-center text-sm"></i> Calendar (ปฏิทินงาน)
                </a>
            </div>

            <hr class="border-slate-800/80">

            <!-- 📦 หมวดที่ 1: คลังสินค้า & จัดซื้อ (Dropdown) -->
            <div class="space-y-1">
                <button onclick="toggleMenuCategory('catInventory')" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800/40 transition">
                    <span class="text-[11px] font-bold uppercase tracking-wider"><i class="fas fa-boxes me-2"></i> คลังสินค้า & จัดซื้อ</span>
                    <i id="icon_catInventory" class="fas fa-chevron-down text-[10px] transition-transform duration-200 <?= $is_cat_inventory ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="catInventory" class="submenu-container <?= $is_cat_inventory ? 'open' : '' ?>">
                    <div class="submenu-content space-y-1 pl-2 pt-1">
                        <a href="inventory_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'inventory_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-boxes w-5 text-center text-sm"></i> สต็อกคงเหลือ
                        </a>
                        <a href="stockin_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'stockin_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-cart-arrow-down w-5 text-center text-sm"></i> รับเข้าสินค้า
                        </a>
                        <a href="stockout_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'stockout_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-truck-loading w-5 text-center text-sm"></i> จ่ายออกสินค้า
                        </a>
                        <a href="addproduct_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'addproduct_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-plus-circle w-5 text-center text-sm"></i> เพิ่มสินค้าใหม่
                        </a>
                        <a href="admin_requisitions.php" class="menu-item w-full flex items-center justify-between px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'admin_requisitions') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <div class="flex items-center gap-3.5"><i class="fas fa-circle-check w-5 text-center text-sm"></i> ตรวจสอบใบเบิกของ</div>
                            <?php if ($pending_requisitions_count > 0): ?>
                                <span class="badge-count bg-rose-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full animate-pulse shadow-sm">
                                    <?= number_format($pending_requisitions_count) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge-count bg-slate-800 text-slate-400 text-[10px] font-bold px-2 py-0.5 rounded-full">0</span>
                            <?php endif; ?>
                        </a>
                        <a href="admin_procurement.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'admin_procurement') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-cart-shopping w-5 text-center text-sm"></i> จัดซื้อสินค้า
                        </a>
                    </div>
                </div>
            </div>

            <!-- 📍 หมวดที่ 2: สถานที่ติดตั้ง & โครงการ (Dropdown) -->
            <div class="space-y-1">
                <button onclick="toggleMenuCategory('catLocation')" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800/40 transition">
                    <span class="text-[11px] font-bold uppercase tracking-wider"><i class="fas fa-map-marker-alt me-2"></i> สถานที่ติดตั้ง & โครงการ</span>
                    <i id="icon_catLocation" class="fas fa-chevron-down text-[10px] transition-transform duration-200 <?= $is_cat_location ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="catLocation" class="submenu-container <?= $is_cat_location ? 'open' : '' ?>">
                    <div class="submenu-content space-y-1 pl-2 pt-1">
                        <a href="add_installation_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'add_installation_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-list-ul w-5 text-center text-sm"></i> จัดการสถานที่ติดตั้ง
                        </a>
                        <a href="project_list_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'project_list_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-file-invoice-dollar w-5 text-center text-sm"></i> รายการโครงการและต้นทุน
                        </a>
                        <a href="view_installation_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'view_installation_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-drafting-compass w-5 text-center text-sm"></i> PROJECT DESIGN
                        </a>
                        <a href="work_progress_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'work_progress_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-chart-line w-5 text-center text-sm"></i> Work Progress
                        </a>
                        <a href="requisition_form_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'requisition_form_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-file-alt w-5 text-center text-sm"></i> ฟอร์มเบิกของ
                        </a>
                    </div>
                </div>
            </div>

            <!-- 💰 หมวดที่ 3: การเงิน & บัญชี (Dropdown) -->
            <div class="space-y-1">
                <button onclick="toggleMenuCategory('catFinance')" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800/40 transition">
                    <span class="text-[11px] font-bold uppercase tracking-wider"><i class="fas fa-receipt me-2"></i> การเงิน & บัญชี</span>
                    <i id="icon_catFinance" class="fas fa-chevron-down text-[10px] transition-transform duration-200 <?= $is_cat_finance ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="catFinance" class="submenu-container <?= $is_cat_finance ? 'open' : '' ?>">
                    <div class="submenu-content space-y-1 pl-2 pt-1">
                        <a href="expenses_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'expenses_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-receipt w-5 text-center text-sm"></i> ค่าใช้จ่ายอื่นๆ
                        </a>
                        <a href="expenses_summary_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'expenses_summary_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-file-invoice w-5 text-center text-sm"></i> สรุปค่าใช้จ่าย
                        </a>
                        <a href="billing_admin.php" class="menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'billing_admin') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <i class="fas fa-paperclip w-5 text-center text-sm"></i> จัดการเอกสารบัญชี
                        </a>
                    </div>
                </div>
            </div>

            <!-- 👥 หมวดที่ 4: จัดการผู้ใช้งาน (Dropdown) -->
            <div class="space-y-1">
                <button onclick="toggleMenuCategory('catUsers')" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800/40 transition">
                    <span class="text-[11px] font-bold uppercase tracking-wider"><i class="fas fa-users me-2"></i> ระบบสมาชิก</span>
                    <i id="icon_catUsers" class="fas fa-chevron-down text-[10px] transition-transform duration-200 <?= $is_cat_users ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="catUsers" class="submenu-container <?= $is_cat_users ? 'open' : '' ?>">
                    <div class="submenu-content space-y-1 pl-2 pt-1">
                        <a href="manage_users.php" class="menu-item w-full flex items-center justify-between px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'manage_users') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <div class="flex items-center gap-3.5"><i class="fas fa-circle-user w-5 text-center text-sm"></i> จัดการผู้ใช้งาน</div>
                            <span class="badge-count bg-emerald-950/80 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-800/50">
                                <?= number_format($user_count) ?>
                            </span>
                        </a>
                        <a href="add_user.php" class="menu-item w-full flex items-center justify-between px-4 py-2.5 rounded-xl transition-all <?= ($current_page == 'add_user') ? 'bg-brandGreen text-white shadow-md' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' ?>">
                            <div class="flex items-center gap-3.5"><i class="fas fa-user-plus w-5 text-center text-sm"></i> เพิ่มผู้ใช้งาน</div>
                            <span class="badge-count bg-emerald-950/80 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-800/50">0</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="pt-2 pb-1">
                <hr id="sidebarDivider" class="border-slate-800/80 transition-colors duration-200">
            </div>
        </div>

        <!-- 👤 ส่วนข้อมูลผู้ใช้, ปุ่มเปลี่ยนธีม Sidebar และปุ่มออกจากระบบ -->
        <div id="sidebarFooter" class="p-4 border-t border-slate-800/80 bg-black/40 space-y-3 transition-colors duration-200 shrink-0">
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

        // ฟังก์ชันเปิด/ปิดหมวดหมู่ Dropdown และจดจำสถานะผ่าน localStorage
        function toggleMenuCategory(categoryId) {
            const menu = document.getElementById(categoryId);
            const icon = document.getElementById('icon_' + categoryId);

            if (menu.classList.contains('open')) {
                menu.classList.remove('open');
                icon.style.transform = "rotate(0deg)";
                localStorage.setItem('menu_' + categoryId, 'closed');
            } else {
                menu.classList.add('open');
                icon.style.transform = "rotate(180deg)";
                localStorage.setItem('menu_' + categoryId, 'open');
            }
        }

        // คืนค่าสถานะ Dropdown ที่เปิดไว้ล่าสุดตอนโหลดหน้า
        document.addEventListener('DOMContentLoaded', () => {
            const categories = ['catInventory', 'catLocation', 'catFinance', 'catUsers'];
            
            categories.forEach(catId => {
                const menu = document.getElementById(catId);
                const icon = document.getElementById('icon_' + catId);
                const savedState = localStorage.getItem('menu_' + catId);

                if (savedState === 'open') {
                    menu.classList.add('open');
                    icon.style.transform = "rotate(180deg)";
                } else if (savedState === 'closed') {
                    menu.classList.remove('open');
                    icon.style.transform = "rotate(0deg)";
                }
            });
        });

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
            sidebar.className = "w-64 bg-white text-slate-700 flex flex-col h-screen sticky top-0 shrink-0 border-r border-slate-200 transition-colors duration-200";
            sidebarHeader.className = "h-20 flex items-center px-6 border-b border-slate-200 gap-3 transition-colors duration-200 shrink-0";
            sidebarTitle.className = "text-slate-900 font-bold text-lg tracking-tight";
            sidebarDivider.className = "border-slate-200 transition-colors duration-200";
            sidebarFooter.className = "p-4 border-t border-slate-200 bg-slate-50 space-y-3 transition-colors duration-200 shrink-0";
            userNameText.className = "text-xs font-bold text-slate-900 truncate";

            themeToggleBtn.className = "w-9 h-9 rounded-xl bg-slate-200 hover:bg-slate-300 text-amber-500 flex items-center justify-center transition border border-slate-300 shrink-0";
            themeIcon.className = "fas fa-moon text-xs text-slate-700";

            menuItems.forEach(item => {
                if (!item.classList.contains('bg-brandGreen')) {
                    item.className = "menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100 hover:text-slate-900";
                }
            });
        }

        function applyDarkSidebar() {
            sidebar.className = "w-64 bg-darkSidebar text-slate-300 flex flex-col h-screen sticky top-0 shrink-0 border-r border-slate-800 transition-colors duration-200";
            sidebarHeader.className = "h-20 flex items-center px-6 border-b border-slate-800/80 gap-3 transition-colors duration-200 shrink-0";
            sidebarTitle.className = "text-white font-bold text-lg tracking-tight";
            sidebarDivider.className = "border-slate-800/80 transition-colors duration-200";
            sidebarFooter.className = "p-4 border-t border-slate-800/80 bg-black/40 space-y-3 transition-colors duration-200 shrink-0";
            userNameText.className = "text-xs font-bold text-white truncate";

            themeToggleBtn.className = "w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 flex items-center justify-center transition border border-slate-700/60 shrink-0";
            themeIcon.className = "fas fa-sun text-xs";

            menuItems.forEach(item => {
                if (!item.classList.contains('bg-brandGreen')) {
                    item.className = "menu-item w-full flex items-center gap-3.5 px-4 py-2.5 rounded-xl transition-all text-slate-300 hover:bg-slate-800/60 hover:text-white";
                }
            });
        }
    </script>
</body>

</html>