<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'expenses'; // กำหนดหน้าปัจจุบันสำหรับ Sidebar

// รับค่าโครงการที่ถูกเลือกจากตัวกรอง Dropdown (รองรับ 'all' ได้อย่างถูกต้อง)
$selected_installation_id = isset($_GET['installation_id']) && $_GET['installation_id'] !== '' ? $_GET['installation_id'] : 'all';

// รับค่าจำนวนแถวต่อหน้าของตารางประวัติ (ค่าเริ่มต้น 5 แถว)
$limit_inc = isset($_GET['limit_inc']) ? intval($_GET['limit_inc']) : 5;
if (!in_array($limit_inc, [5, 10, 25, 50, 100])) { $limit_inc = 5; }

$limit_exp = isset($_GET['limit_exp']) ? intval($_GET['limit_exp']) : 5;
if (!in_array($limit_exp, [5, 10, 25, 50, 100])) { $limit_exp = 5; }

// รับค่าหมายเลขหน้าปัจจุบัน (ค่าเริ่มต้นหน้า 1)
$page_inc = isset($_GET['page_inc']) ? intval($_GET['page_inc']) : 1;
if ($page_inc < 1) $page_inc = 1;
$offset_inc = ($page_inc - 1) * $limit_inc;

$page_exp = isset($_GET['page_exp']) ? intval($_GET['page_exp']) : 1;
if ($page_exp < 1) $page_exp = 1;
$offset_exp = ($page_exp - 1) * $limit_exp;

// ดึงรายชื่อโครงการทั้งหมดสำหรับใส่ใน Dropdown ตัวเลือก
try {
    $stmt_inst_list = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsDropdown = $stmt_inst_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $installationsDropdown = [];
}

// เงื่อนไขการกรองตามโครงการ
$where_clause_exp = "";
$where_clause_inc = "";
$params_exp = [];
$params_inc = [];

if ($selected_installation_id !== 'all' && intval($selected_installation_id) > 0) {
    $where_clause_exp = " WHERE e.installation_id = ? ";
    $where_clause_inc = " WHERE inc.installation_id = ? ";
    $params_exp[] = intval($selected_installation_id);
    $params_inc[] = intval($selected_installation_id);
}

// 1. ดึงข้อมูลค่าใช้จ่าย (พร้อมระบบแบ่งหน้า)
try {
    $sql_count_exp = "SELECT COUNT(*) as total FROM expenses e " . $where_clause_exp;
    $stmt_c_exp = $pdo->prepare($sql_count_exp);
    $stmt_c_exp->execute($params_exp);
    $total_expenses_rows = $stmt_c_exp->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages_exp = ceil($total_expenses_rows / $limit_exp);
    if ($total_pages_exp < 1) $total_pages_exp = 1;
    if ($page_exp > $total_pages_exp) $page_exp = $total_pages_exp;
    $offset_exp = ($page_exp - 1) * $limit_exp;

    $sql_exp = "
        SELECT e.*, i.name as project_name 
        FROM expenses e 
        LEFT JOIN installations i ON e.installation_id = i.id 
        $where_clause_exp
        ORDER BY e.created_at DESC LIMIT $limit_exp OFFSET $offset_exp
    ";
    $stmt_exp = $pdo->prepare($sql_exp);
    $stmt_exp->execute($params_exp);
    $expensesList = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
    
    $sql_total_exp = "SELECT SUM(amount) as total FROM expenses e " . $where_clause_exp;
    $stmt_total_exp = $pdo->prepare($sql_total_exp);
    $stmt_total_exp->execute($params_exp);
    $totalGeneralExpense = $stmt_total_exp->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $expensesList = [];
    $totalGeneralExpense = 0;
    $total_pages_exp = 1;
    $total_expenses_rows = 0;
}

// 2. ดึงข้อมูลรายรับ (พร้อมระบบแบ่งหน้า)
try {
    $sql_count_inc = "SELECT COUNT(*) as total FROM incomes inc " . $where_clause_inc;
    $stmt_c_inc = $pdo->prepare($sql_count_inc);
    $stmt_c_inc->execute($params_inc);
    $total_incomes_rows = $stmt_c_inc->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages_inc = ceil($total_incomes_rows / $limit_inc);
    if ($total_pages_inc < 1) $total_pages_inc = 1;
    if ($page_inc > $total_pages_inc) $page_inc = $total_pages_inc;
    $offset_inc = ($page_inc - 1) * $limit_inc;

    $sql_inc = "
        SELECT inc.*, i.name as project_name 
        FROM incomes inc 
        LEFT JOIN installations i ON inc.installation_id = i.id 
        $where_clause_inc
        ORDER BY inc.created_at DESC LIMIT $limit_inc OFFSET $offset_inc
    ";
    $stmt_inc = $pdo->prepare($sql_inc);
    $stmt_inc->execute($params_inc);
    $incomesList = $stmt_inc->fetchAll(PDO::FETCH_ASSOC);

    $sql_total_inc = "SELECT SUM(amount) as total FROM incomes inc " . $where_clause_inc;
    $stmt_total_inc = $pdo->prepare($sql_total_inc);
    $stmt_total_inc->execute($params_inc);
    $totalGeneralIncome = $stmt_total_inc->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $incomesList = [];
    $totalGeneralIncome = 0;
    $total_pages_inc = 1;
    $total_incomes_rows = 0;
}

// 3. คำนวณต้นทุนอุปกรณ์แต่ละโครงการจาก stock_transactions
try {
    $sql_proj = "
        SELECT 
            i.id,
            i.name as project_name,
            SUM(CASE WHEN st.type = 'out' THEN st.quantity * p.cost_price ELSE 0 END) as total_out,
            SUM(CASE WHEN st.type = 'in' OR st.type = 'return' THEN st.quantity * p.cost_price ELSE 0 END) as total_return
        FROM installations i
        LEFT JOIN stock_transactions st ON i.id = st.installation_id
        LEFT JOIN products p ON st.product_id = p.id
    ";
    $params_proj = [];
    if ($selected_installation_id !== 'all' && intval($selected_installation_id) > 0) {
        $sql_proj .= " WHERE i.id = ? ";
        $params_proj[] = intval($selected_installation_id);
    }
    $sql_proj .= " GROUP BY i.id, i.name ORDER BY i.id DESC ";

    $stmt_proj = $pdo->prepare($sql_proj);
    $stmt_proj->execute($params_proj);
    $projectsCost = $stmt_proj->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projectsCost = [];
}

// คำนวณมูลค่าวัสดุอุปกรณ์สุทธิรวมทั้งหมด
$totalMaterialCost = 0;
foreach ($projectsCost as $proj) {
    $totalMaterialCost += ($proj['total_out'] - $proj['total_return']);
}

// คำนวณกำไรสุทธิ = รายรับรวม - มูลค่าวัสดุสุทธิ - ค่าใช้จ่ายรวม
$netProfit = $totalGeneralIncome - $totalMaterialCost - $totalGeneralExpense;

function formatDateTH($datetime_str) {
    if (empty($datetime_str)) return '-';
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) return htmlspecialchars($datetime_str);
    return date('d/m/Y H:i', $timestamp);
}

function renderTableFooter($total_rows, $offset, $limit, $current_page_num, $total_pages, $selected_inst, $l_inc, $l_exp, $p_inc, $p_exp, $target_type) {
    $start = ($total_rows > 0) ? ($offset + 1) : 0;
    $end = min($offset + $limit, $total_rows);
    
    $base_link_params = "installation_id=" . urlencode($selected_inst) . "&limit_inc={$l_inc}&limit_exp={$l_exp}";
    if ($target_type == 'inc') {
        $base_link_params .= "&page_exp={$p_exp}";
    } else {
        $base_link_params .= "&page_inc={$p_inc}";
    }
    
    $html = '<div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4 text-xs text-slate-500">';
    $html .= '<span>กำลังแสดง ' . $start . ' ถึง ' . $end . ' จากทั้งหมด ' . $total_rows . ' รายการ</span>';
    $html .= '<div class="flex flex-wrap items-center gap-4">';
    $html .= '<div class="flex items-center gap-2">';
    $html .= '<span class="font-medium">แสดง:</span>';
    $html .= '<form method="GET" action="" class="inline-block">';
    $html .= '<input type="hidden" name="installation_id" value="' . htmlspecialchars($selected_inst) . '">';
    $html .= '<input type="hidden" name="limit_inc" value="' . $l_inc . '">';
    $html .= '<input type="hidden" name="limit_exp" value="' . $l_exp . '">';
    $html .= '<input type="hidden" name="page_inc" value="' . $p_inc . '">';
    $html .= '<input type="hidden" name="page_exp" value="' . $p_exp . '">';
    
    $select_name = ($target_type == 'inc') ? 'limit_inc' : 'limit_exp';
    $html .= '<select name="' . $select_name . '" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-200 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-semibold text-slate-700 cursor-pointer">';
    foreach ([5, 10, 25, 50, 100] as $opt) {
        $sel = ($limit == $opt) ? 'selected' : '';
        $html .= '<option value="' . $opt . '" ' . $sel . '>' . $opt . ' แถว</option>';
    }
    $html .= '</select>';
    $html .= '</form>';
    $html .= '</div>';
    
    if ($total_pages > 1) {
        $html .= '<div class="flex items-center gap-1.5">';
        $prev_page = $current_page_num - 1;
        if ($current_page_num > 1) {
            $html .= '<a href="?' . $base_link_params . '&page_' . $target_type . '=' . $prev_page . '" class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-left text-xs"></i></a>';
        } else {
            $html .= '<span class="w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center text-slate-300 cursor-not-allowed"><i class="fa-solid fa-chevron-left text-xs"></i></span>';
        }
        
        $delta = 1;
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == 1 || $i == $total_pages || ($i >= $current_page_num - $delta && $i <= $current_page_num + $delta)) {
                if ($i == $current_page_num) {
                    $html .= '<span class="w-8 h-8 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center shadow-xs text-xs">' . $i . '</span>';
                } else {
                    $html .= '<a href="?' . $base_link_params . '&page_' . $target_type . '=' . $i . '" class="w-8 h-8 rounded-full border border-slate-200 text-slate-700 font-semibold flex items-center justify-center hover:bg-slate-100 transition text-xs">' . $i . '</a>';
                }
            } elseif ($i == $current_page_num - $delta - 1 || $i == $current_page_num + $delta + 1) {
                $html .= '<span class="px-1 text-slate-400 text-xs">...</span>';
            }
        }
        
        $next_page = $current_page_num + 1;
        if ($current_page_num < $total_pages) {
            $html .= '<a href="?' . $base_link_params . '&page_' . $target_type . '=' . $next_page . '" class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-right text-xs"></i></a>';
        } else {
            $html .= '<span class="w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center text-slate-300 cursor-not-allowed"><i class="fa-solid fa-chevron-right text-xs"></i></span>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายรับ ค่าใช้จ่าย และต้นทุนโครงการ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Prompt', 'Inter', 'sans-serif'],
                        sarabun: ['Sarabun', 'sans-serif']
                    },
                    colors: { darkSidebar: '#000007', brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        .font-sarabun { font-family: 'Sarabun', sans-serif !important; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: white !important; color: #0f172a !important; font-family: 'Sarabun', sans-serif !important; }
            #sidebarWrapper, #sidebarBackdrop, header, .no-print { display: none !important; }
            body * { visibility: hidden; }
            #printableExpenseReport, #printableExpenseReport * { visibility: visible; }
            #printableExpenseReport {
                position: absolute; left: 0; top: 0; width: 100%;
                background: white !important; z-index: 99999; padding: 0 !important;
                margin: 0 !important; border: none !important; box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_admin.php')) include 'sidebar_admin.php'; ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">จัดการรายรับ ค่าใช้จ่าย และต้นทุนโครงการ</h2>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openPrintModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                    <i class="fa-solid fa-file-pdf"></i> <span class="hidden sm:inline">ออกรายงาน PDF</span>
                </button>
                <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6">
            
            <!-- แจ้งเตือนสถานะ -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>✨ บันทึกข้อมูลเรียบร้อยแล้ว</span>
                        <a href="expenses_admin.php" class="text-emerald-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'updated'): ?>
                    <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>✏️ แก้ไข/สลับประเภทรายการเรียบร้อยแล้ว</span>
                        <a href="expenses_admin.php" class="text-indigo-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>❌ เกิดข้อผิดพลาดในการทำรายการ กรุณาลองใหม่อีกครั้ง</span>
                        <a href="expenses_admin.php" class="text-rose-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- แถบเลือกโครงการ (Filter Bar) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-filter"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">กรองข้อมูลตามโครงการ</h3>
                        <p class="text-xs text-slate-500">เลือกโครงการที่ต้องการเรียกดูและสรุปผลการดำเนินงาน</p>
                    </div>
                </div>

                <form method="GET" action="" class="flex items-center gap-3 w-full md:w-auto">
                    <select name="installation_id" onchange="this.form.submit()" class="w-full md:w-72 px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-semibold text-slate-700 cursor-pointer">
                        <option value="all" <?= ($selected_installation_id === 'all') ? 'selected' : '' ?>>-- แสดงทุกโครงการทั้งหมด --</option>
                        <?php foreach ($installationsDropdown as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= ($selected_installation_id == $inst['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="limit_inc" value="<?= $limit_inc ?>">
                    <input type="hidden" name="limit_exp" value="<?= $limit_exp ?>">
                </form>
            </div>

            <!-- สรุปภาพรวมทางการเงิน -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs space-y-1">
                    <p class="text-xs font-semibold text-slate-400">รายรับรวมทั้งหมด</p>
                    <h3 class="text-xl font-bold text-emerald-600">+<?= number_format($totalGeneralIncome, 2) ?> <span class="text-xs font-normal">บาท</span></h3>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs space-y-1">
                    <p class="text-xs font-semibold text-slate-400">มูลค่าวัสดุอุปกรณ์สุทธิ</p>
                    <h3 class="text-xl font-bold text-indigo-600">-<?= number_format($totalMaterialCost, 2) ?> <span class="text-xs font-normal">บาท</span></h3>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs space-y-1">
                    <p class="text-xs font-semibold text-slate-400">ค่าใช้จ่ายทั่วไปรวม</p>
                    <h3 class="text-xl font-bold text-rose-600">-<?= number_format($totalGeneralExpense, 2) ?> <span class="text-xs font-normal">บาท</span></h3>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs space-y-1">
                    <p class="text-xs font-semibold text-slate-400">กำไรสุทธิภาพรวม</p>
                    <h3 class="text-xl font-bold <?= $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                        <?= number_format($netProfit, 2) ?> <span class="text-xs font-normal">บาท</span>
                    </h3>
                </div>
            </div>

            <!-- ส่วนที่ 1: ฟอร์มเพิ่มรายการ -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base md:text-lg font-bold text-slate-900">บันทึกรายการทางการเงิน</h3>
                        <p class="text-xs text-slate-500 mt-0.5">เลือกประเภทรายการที่ต้องการบันทึกลงในระบบ</p>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-2xl w-full sm:w-auto">
                        <button type="button" onclick="switchForm('income')" id="tabIncomeBtn" class="flex-1 sm:flex-none px-5 py-2 rounded-xl text-xs font-bold transition-all bg-emerald-600 text-white shadow-xs flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-plus-circle"></i> บันทึกรายรับ
                        </button>
                        <button type="button" onclick="switchForm('expense')" id="tabExpenseBtn" class="flex-1 sm:flex-none px-5 py-2 rounded-xl text-xs font-bold transition-all text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-minus-circle"></i> บันทึกค่าใช้จ่าย
                        </button>
                    </div>
                </div>

                <!-- ฟอร์มเพิ่มรายรับ -->
                <form id="incomeForm" action="income_process_admin.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน <span class="text-rose-500">*</span></label>
                        <select name="installation_id" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($installationsDropdown as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">รายการ / รายละเอียดรายรับ <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" required placeholder="เช่น ชำระค่างวดที่ 1, ค่ามัดจำสัญญา..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนเงินรับ (บาท) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="amount" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-bold text-emerald-600">
                    </div>

                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">หมวดหมู่รายรับ</label>
                        <select id="incCategorySelect" onchange="toggleCustomCategory('incCategorySelect', 'incCustomCategoryInput', 'incCategoryHidden')" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium cursor-pointer">
                            <option value="รายรับโครงการ">รายรับโครงการ</option>
                            <option value="เงินงวด/มัดจำ">เงินงวด/มัดจำ</option>
                            <option value="เงินเพิ่มพิเศษ/บริการเพิ่มเติม">เงินเพิ่มพิเศษ/บริการเพิ่มเติม</option>
                            <option value="รายรับส่วนกลาง">รายรับส่วนกลาง</option>
                            <option value="เบ็ดเตล็ด">เบ็ดเตล็ด</option>
                            <option value="__custom__">➕ พิมพ์หมวดหมู่ใหม่...</option>
                        </select>
                        <input type="text" id="incCustomCategoryInput" oninput="updateHiddenCategory('incCustomCategoryInput', 'incCategoryHidden')" placeholder="พิมพ์หมวดหมู่ใหม่..." class="w-full px-4 py-2.5 border border-emerald-300 rounded-xl text-xs bg-emerald-50/50 focus:outline-none focus:border-emerald-600 font-medium mt-1 hidden">
                        <input type="hidden" name="category" id="incCategoryHidden" value="รายรับโครงการ">
                    </div>

                    <div class="space-y-1.5 md:col-span-6">
                        <label class="block text-xs font-semibold text-slate-600">แนบรูปหลักฐานการรับเงิน / ใบเสร็จ / สลิปโอนเงิน (ถ้ามี)</label>
                        <input type="file" name="receipt_image" accept="image/*,application/pdf" class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>

                    <div class="md:col-span-6 flex justify-end pt-2">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all cursor-pointer flex items-center gap-2">
                            <i class="fa-solid fa-save"></i> บันทึกรายการรายรับ
                        </button>
                    </div>
                </form>

                <!-- ฟอร์มเพิ่มค่าใช้จ่าย -->
                <form id="expenseForm" action="expenses_process.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-4 hidden">
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน (ระบุหรือไม่ก็ได้)</label>
                        <select name="installation_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="">-- ส่วนกลาง / ไม่ระบุโครงการ --</option>
                            <?php foreach ($installationsDropdown as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">รายการ / รายละเอียดค่าใช้จ่าย <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" required placeholder="เช่น ค่าขนส่งแผงโซล่าเซลล์..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนเงินจ่าย (บาท) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="amount" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-bold text-rose-600">
                    </div>

                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">หมวดหมู่ค่าใช้จ่าย</label>
                        <select id="expCategorySelect" onchange="toggleCustomCategory('expCategorySelect', 'expCustomCategoryInput', 'expCategoryHidden')" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium cursor-pointer">
                            <option value="ทั่วไป">ทั่วไป</option>
                            <option value="เดินทาง/ขนส่ง">เดินทาง/ขนส่ง</option>
                            <option value="อุปกรณ์/เครื่องมือ">อุปกรณ์/เครื่องมือ</option>
                            <option value="ค่าแรง/เงินเดือน">ค่าแรง/เงินเดือน</option>
                            <option value="ค่าอาหาร/รับรอง">ค่าอาหาร/รับรอง</option>
                            <option value="ค่าสาธารณูปโภค">ค่าสาธารณูปโภค</option>
                            <option value="เบ็ดเตล็ด">เบ็ดเตล็ด</option>
                            <option value="__custom__">➕ พิมพ์หมวดหมู่ใหม่...</option>
                        </select>
                        <input type="text" id="expCustomCategoryInput" oninput="updateHiddenCategory('expCustomCategoryInput', 'expCategoryHidden')" placeholder="พิมพ์หมวดหมู่ใหม่..." class="w-full px-4 py-2.5 border border-rose-300 rounded-xl text-xs bg-rose-50/50 focus:outline-none focus:border-rose-600 font-medium mt-1 hidden">
                        <input type="hidden" name="category" id="expCategoryHidden" value="ทั่วไป">
                    </div>

                    <div class="space-y-1.5 md:col-span-6">
                        <label class="block text-xs font-semibold text-slate-600">แนบรูปสลิปโอนเงิน / หลักฐานการจ่ายเงิน (ถ้ามี)</label>
                        <input type="file" name="receipt_image" accept="image/*" class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>

                    <div class="md:col-span-6 flex justify-end pt-2">
                        <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-rose-600/25 transition-all cursor-pointer flex items-center gap-2">
                            <i class="fa-solid fa-save"></i> บันทึกค่าใช้จ่าย
                        </button>
                    </div>
                </form>
            </div>

            <!-- 1. ตารางประวัติรายการรายรับล่าสุด -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 md:p-8 space-y-6 shadow-xs">
                <div class="flex items-center gap-2 text-emerald-700 font-bold text-base md:text-lg">
                    <i class="fa-solid fa-list-check"></i> ประวัติรายการรายรับล่าสุด
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[850px]">
                        <thead>
                            <tr class="text-slate-400 font-bold border-b border-slate-100">
                                <th class="py-3 px-4">วันที่ / เวลา</th>
                                <th class="py-3 px-4">โครงการ</th>
                                <th class="py-3 px-4">รายการรับ</th>
                                <th class="py-3 px-4">หมวดหมู่</th>
                                <th class="py-3 px-4 text-center">หลักฐาน</th>
                                <th class="py-3 px-4 text-right">จำนวนเงินรับ (บาท)</th>
                                <th class="py-3 px-4 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($incomesList)): ?>
                                <?php foreach ($incomesList as $inc): ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-4 px-4 text-slate-600"><?= formatDateTH($inc['created_at']) ?></td>
                                        <td class="py-4 px-4 font-bold text-slate-900"><?= htmlspecialchars($inc['project_name'] ?? 'ไม่ระบุโครงการ') ?></td>
                                        <td class="py-4 px-4 font-bold text-slate-900"><?= htmlspecialchars($inc['title']) ?></td>
                                        <td class="py-4 px-4">
                                            <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full text-[11px] font-bold border border-emerald-100">
                                                <?= htmlspecialchars($inc['category']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php if (!empty($inc['receipt_image'])): ?>
                                                <div class="flex items-center justify-center gap-2">
                                                    <!-- รูปตัวอย่างแบบสี่เหลี่ยมตามเดิม พร้อมคลิกแสดง Popup -->
                                                    <img src="<?= htmlspecialchars($inc['receipt_image']) ?>" 
                                                         onclick="showImagePopup('<?= htmlspecialchars($inc['receipt_image']) ?>')"
                                                         class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-xs cursor-pointer hover:opacity-80 transition" alt="สลิป">
                                                    <button type="button" 
                                                            onclick="showImagePopup('<?= htmlspecialchars($inc['receipt_image']) ?>')" 
                                                            class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1 cursor-pointer">
                                                        <i class="fa-solid fa-image"></i> ดูหลักฐาน
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-400 font-normal">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4 text-right font-extrabold text-emerald-600 text-sm">+<?= number_format($inc['amount'], 2) ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <a href="expenses_edit_admin.php?type=income&id=<?= $inc['id'] ?>" class="bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/80 px-3 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-normal">ไม่มีข้อมูลประวัติรายรับในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer แบ่งหน้าตารางรายรับ -->
                <?= renderTableFooter($total_incomes_rows, $offset_inc, $limit_inc, $page_inc, $total_pages_inc, $selected_installation_id, $limit_inc, $limit_exp, $page_inc, $page_exp, 'inc') ?>
            </div>

            <!-- 2. ตารางประวัติรายการค่าใช้จ่ายทั่วไปล่าสุด -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 md:p-8 space-y-6 shadow-xs">
                <div class="flex items-center gap-2 text-rose-600 font-bold text-base md:text-lg">
                    <i class="fa-solid fa-list-check"></i> ประวัติรายการค่าใช้จ่ายทั่วไปล่าสุด
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[850px]">
                        <thead>
                            <tr class="text-slate-400 font-bold border-b border-slate-100">
                                <th class="py-3 px-4">วันที่ / เวลา</th>
                                <th class="py-3 px-4">โครงการ</th>
                                <th class="py-3 px-4">รายการค่าใช้จ่าย</th>
                                <th class="py-3 px-4">หมวดหมู่</th>
                                <th class="py-3 px-4 text-center">หลักฐาน</th>
                                <th class="py-3 px-4 text-right">จำนวนเงินจ่าย (บาท)</th>
                                <th class="py-3 px-4 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($expensesList)): ?>
                                <?php foreach ($expensesList as $exp): ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-4 px-4 text-slate-600"><?= formatDateTH($exp['created_at']) ?></td>
                                        <td class="py-4 px-4 font-bold text-slate-900"><?= htmlspecialchars($exp['project_name'] ?? 'ส่วนกลาง / ไม่ระบุโครงการ') ?></td>
                                        <td class="py-4 px-4 font-bold text-slate-900"><?= htmlspecialchars($exp['title']) ?></td>
                                        <td class="py-4 px-4">
                                            <span class="bg-slate-100 text-slate-700 px-3 py-1.5 rounded-full text-[11px] font-bold border border-slate-200">
                                                <?= htmlspecialchars($exp['category']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <?php if (!empty($exp['receipt_image'])): ?>
                                                <div class="flex items-center justify-center gap-2">
                                                    <!-- รูปตัวอย่างแบบสี่เหลี่ยมตามเดิม พร้อมคลิกแสดง Popup -->
                                                    <img src="<?= htmlspecialchars($exp['receipt_image']) ?>" 
                                                         onclick="showImagePopup('<?= htmlspecialchars($exp['receipt_image']) ?>')"
                                                         class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-xs cursor-pointer hover:opacity-80 transition" alt="สลิป">
                                                    <button type="button" 
                                                            onclick="showImagePopup('<?= htmlspecialchars($exp['receipt_image']) ?>')" 
                                                            class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1 cursor-pointer">
                                                        <i class="fa-solid fa-image"></i> ดูสลิป
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-400 font-normal">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4 text-right font-extrabold text-rose-600 text-sm">-<?= number_format($exp['amount'], 2) ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <a href="expenses_edit_admin.php?type=expense&id=<?= $exp['id'] ?>" class="bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/80 px-3 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-normal">ไม่มีข้อมูลประวัติค่าใช้จ่ายในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer แบ่งหน้าตารางค่าใช้จ่าย -->
                <?= renderTableFooter($total_expenses_rows, $offset_exp, $limit_exp, $page_exp, $total_pages_exp, $selected_installation_id, $limit_inc, $limit_exp, $page_inc, $page_exp, 'exp') ?>
            </div>

            <!-- 3. สรุปต้นทุนวัสดุอุปกรณ์ -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 md:p-8 space-y-6 shadow-xs">
                <div>
                    <h3 class="text-base md:text-lg font-bold text-slate-900">สรุปต้นทุนอุปกรณ์แต่ละโครงการ</h3>
                    <p class="text-xs text-slate-500 mt-1">คำนวณอัตโนมัติจากมูลค่าอุปกรณ์ที่เบิกออก หักลบด้วยอุปกรณ์ที่รับคืนคลัง คูณด้วยราคาต้นทุนจริง</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[650px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">ชื่อโครงการ / หน้างาน</th>
                                <th class="py-3 px-4 font-semibold text-right">มูลค่าเบิกออก (+)</th>
                                <th class="py-3 px-4 font-semibold text-right">มูลค่ารับคืน (-)</th>
                                <th class="py-3 px-4 font-semibold text-right">ต้นทุนอุปกรณ์สุทธิ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($projectsCost)): ?>
                                <?php foreach ($projectsCost as $proj): 
                                    $net_cost = $proj['total_out'] - $proj['total_return'];
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($proj['project_name']) ?></td>
                                        <td class="py-3.5 px-4 text-right text-rose-600 font-semibold"><?= number_format($proj['total_out'], 2) ?></td>
                                        <td class="py-3.5 px-4 text-right text-purple-600 font-semibold"><?= number_format($proj['total_return'], 2) ?></td>
                                        <td class="py-3.5 px-4 text-right font-extrabold text-indigo-600"><?= number_format($net_cost, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-10 text-center text-slate-400 font-normal">ไม่มีข้อมูลต้นทุนโครงการในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Lightbox สำหรับแสดงรูปสลิป / หลักฐานขนาดใหญ่ (Popup) -->
    <div id="imageLightboxModal" onclick="closeImagePopup()" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm hidden items-center justify-center z-50 p-4 transition-all duration-200">
        <div class="relative max-w-3xl max-h-[90vh] bg-white rounded-3xl p-3 shadow-2xl overflow-hidden flex flex-col items-center justify-center" onclick="event.stopPropagation()">
            <button type="button" onclick="closeImagePopup()" class="absolute top-4 right-4 w-9 h-9 bg-slate-900/60 hover:bg-slate-900 text-white rounded-full flex items-center justify-center transition cursor-pointer z-10">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
            <img id="lightboxImage" src="" class="max-w-full max-h-[82vh] rounded-2xl object-contain" alt="รูปหลักฐานขยายใหญ่">
            <div class="mt-3 w-full flex justify-between items-center px-2">
                <span class="text-xs text-slate-500 font-medium">ภาพหลักฐาน / สลิปโอนเงิน</span>
                <a id="lightboxDownloadBtn" href="" target="_blank" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> เปิดรูปในแท็บใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- Modal พิมพ์รายงาน PDF (TH Sarabun) -->
    <div id="printReportModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-10 shadow-2xl border border-slate-200 space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5 no-print">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center font-bold"><i class="fa-solid fa-file-pdf text-lg"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">ตัวอย่างรายงานสรุปการเงินและต้นทุนโครงการ</h3>
                        <p class="text-xs text-slate-500 font-medium">รูปแบบฟอนต์ทางการ: TH Sarabun</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-xs md:text-sm font-bold flex items-center gap-2"><i class="fa-solid fa-print"></i> พิมพ์ / บันทึก PDF</button>
                    <button type="button" onclick="closePrintModal()" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <div id="printableExpenseReport" class="p-6 bg-white text-slate-900 space-y-5 font-sarabun text-base">
                <div class="flex items-center justify-between border-b-2 border-slate-800 pb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold"><i class="fa-solid fa-solar-panel"></i></div>
                        <div>
                            <h1 class="text-2xl font-bold uppercase tracking-wide text-slate-900 leading-tight">SOLARSTOCK PRO</h1>
                            <p class="text-sm font-semibold text-slate-600">รายงานสรุปรายรับ ค่าใช้จ่าย และต้นทุนโครงการ</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded text-xs font-bold uppercase mb-1">OFFICIAL REPORT</span>
                        <p class="text-xs font-semibold text-slate-600">วันที่พิมพ์: <?= date('d/m/Y') ?></p>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-bold text-slate-800 uppercase text-sm border-b border-slate-300 pb-1">สรุปภาพรวมทางการเงิน</h4>
                    <div class="grid grid-cols-4 gap-3 text-xs font-semibold">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">รายรับรวมทั้งหมด</span>
                            <span class="text-emerald-700 font-bold">+<?= number_format($totalGeneralIncome, 2) ?> บ.</span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">มูลค่าวัสดุสุทธิ</span>
                            <span class="text-indigo-700 font-bold">-<?= number_format($totalMaterialCost, 2) ?> บ.</span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">ค่าใช้จ่ายรวม</span>
                            <span class="text-rose-600 font-bold">-<?= number_format($totalGeneralExpense, 2) ?> บ.</span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">กำไรสุทธิรวม</span>
                            <span class="<?= $netProfit >= 0 ? 'text-emerald-800' : 'text-rose-800' ?> font-extrabold"><?= number_format($netProfit, 2) ?> บ.</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-4">
                    <h4 class="font-bold text-slate-800 uppercase text-sm border-b border-slate-300 pb-1">สรุปต้นทุนวัสดุอุปกรณ์แต่ละโครงการ</h4>
                    <table class="w-full text-left border-collapse border border-slate-300 text-xs">
                        <thead>
                            <tr class="bg-slate-800 text-white font-bold">
                                <th class="py-2 px-3 border border-slate-700">ชื่อโครงการ / หน้างาน</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">มูลค่าเบิกออก (+)</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">มูลค่ารับคืน (-)</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">ต้นทุนอุปกรณ์สุทธิ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (!empty($projectsCost)): foreach ($projectsCost as $proj): $net_cost = $proj['total_out'] - $proj['total_return']; ?>
                            <tr>
                                <td class="py-2 px-3 border border-slate-300 font-semibold"><?= htmlspecialchars($proj['project_name']) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right text-rose-600"><?= number_format($proj['total_out'], 2) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right text-purple-700"><?= number_format($proj['total_return'], 2) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right font-bold text-indigo-700"><?= number_format($net_cost, 2) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="4" class="py-4 text-center text-slate-500 border border-slate-300">ไม่มีข้อมูลต้นทุนโครงการ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-8 pt-10 page-break-inside-avoid text-xs">
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-12">
                        <p class="font-bold text-slate-800">ผู้จัดทำรายงาน</p>
                        <div class="space-y-1">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-12">
                        <p class="font-bold text-slate-800">ผู้ตรวจสอบ / ผู้อนุมัติ</p>
                        <div class="space-y-1">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script สำหรับควบคุมฟอร์ม, Pop-up รูปสลิป, Sidebar และ Modal พิมพ์ -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        function switchForm(type) {
            const incomeForm = document.getElementById('incomeForm');
            const expenseForm = document.getElementById('expenseForm');
            const tabIncomeBtn = document.getElementById('tabIncomeBtn');
            const tabExpenseBtn = document.getElementById('tabExpenseBtn');

            if (type === 'income') {
                incomeForm.classList.remove('hidden');
                expenseForm.classList.add('hidden');
                tabIncomeBtn.className = "flex-1 sm:flex-none px-5 py-2 rounded-xl text-xs font-bold transition-all bg-emerald-600 text-white shadow-xs flex items-center justify-center gap-1.5";
                tabExpenseBtn.className = "flex-1 sm:flex-none px-5 py-2 rounded-xl text-xs font-bold transition-all text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5";
            } else {
                incomeForm.classList.add('hidden');
                expenseForm.classList.remove('hidden');
                tabExpenseBtn.className = "flex-1 sm:flex-none px-5 py-2 rounded-xl text-xs font-bold transition-all bg-rose-600 text-white shadow-xs flex items-center justify-center gap-1.5";
                tabIncomeBtn.className = "flex-1 sm:flex-none px-5 py-2 rounded-xl text-xs font-bold transition-all text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5";
            }
        }

        function toggleCustomCategory(selectId, inputId, hiddenId) {
            const sel = document.getElementById(selectId);
            const input = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);

            if (sel.value === '__custom__') {
                input.classList.remove('hidden');
                input.focus();
                hidden.value = input.value;
            } else {
                input.classList.add('hidden');
                hidden.value = sel.value;
            }
        }

        function updateHiddenCategory(inputId, hiddenId) {
            const input = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);
            hidden.value = input.value;
        }

        /* ฟังก์ชันแสดง Pop-up รูปสลิป/หลักฐานขนาดใหญ่ */
        function showImagePopup(src) {
            const modal = document.getElementById('imageLightboxModal');
            const img = document.getElementById('lightboxImage');
            const dlBtn = document.getElementById('lightboxDownloadBtn');
            img.src = src;
            dlBtn.href = src;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeImagePopup() {
            const modal = document.getElementById('imageLightboxModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function openPrintModal() {
            let modal = document.getElementById('printReportModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closePrintModal() {
            let modal = document.getElementById('printReportModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>