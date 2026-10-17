<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'expenses'; // กำหนดหน้าปัจจุบันสำหรับ Sidebar

// 1. ดึงข้อมูลค่าใช้จ่ายทั่วไป
try {
    $stmt_exp = $pdo->query("
        SELECT e.*, i.name as project_name 
        FROM expenses e 
        LEFT JOIN installations i ON e.installation_id = i.id 
        ORDER BY e.created_at DESC LIMIT 50
    ");
    $expensesList = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

    $stmt_total_exp = $pdo->query("SELECT SUM(amount) as total FROM expenses");
    $totalGeneralExpense = $stmt_total_exp->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $expensesList = [];
    $totalGeneralExpense = 0;
}

// 2. ดึงรายชื่อโครงการสำหรับใส่ใน Dropdown
try {
    $stmt_inst_list = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsDropdown = $stmt_inst_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $installationsDropdown = [];
}

// 3. คำนวณต้นทุนอุปกรณ์แต่ละโครงการจาก stock_transactions (เบิกออก - รับคืน) x ราคาต้นทุน
try {
    $stmt_proj = $pdo->query("
        SELECT 
            i.id,
            i.name as project_name,
            SUM(CASE WHEN st.type = 'out' THEN st.quantity * p.cost_price ELSE 0 END) as total_out,
            SUM(CASE WHEN st.type = 'in' OR st.type = 'return' THEN st.quantity * p.cost_price ELSE 0 END) as total_return
        FROM installations i
        LEFT JOIN stock_transactions st ON i.id = st.installation_id
        LEFT JOIN products p ON st.product_id = p.id
        GROUP BY i.id, i.name
        ORDER BY i.id DESC
    ");
    $projectsCost = $stmt_proj->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projectsCost = [];
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการค่าใช้จ่ายและต้นทุนโครงการ - SolarStock Pro</title>
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
                    colors: {
                        darkSidebar: '#000007',
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

        .font-sarabun {
            font-family: 'Sarabun', sans-serif !important;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            body {
                background: white !important;
                color: #0f172a !important;
                font-family: 'Sarabun', sans-serif !important;
            }

            #sidebarWrapper,
            #sidebarBackdrop,
            header,
            .no-print {
                display: none !important;
            }

            body * {
                visibility: hidden;
            }

            #printableExpenseReport,
            #printableExpenseReport * {
                visibility: visible;
            }

            #printableExpenseReport {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white !important;
                z-index: 99999;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับมือถือและเดสก์ท็อป -->
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
                <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">จัดการค่าใช้จ่ายและต้นทุนโครงการ</h2>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openPrintModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shadow-sm transition-all">
                    <i class="fa-solid fa-file-pdf"></i> <span class="hidden sm:inline">ออกรายงาน PDF</span>
                </button>
                <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">

            <!-- แจ้งเตือนสถานะ -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>✨ บันทึกรายการค่าใช้จ่ายและสลิปโอนเงินเรียบร้อยแล้ว</span>
                        <a href="expenses_admin.php" class="text-emerald-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง</span>
                        <a href="expenses_admin.php" class="text-rose-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ส่วนที่ 1: สรุปต้นทุนวัสดุอุปกรณ์แต่ละโครงการ -->
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
                                        <td class="py-3.5 px-4 text-right text-slate-600"><?= number_format($proj['total_out'], 2) ?> บาท</td>
                                        <td class="py-3.5 px-4 text-right text-emerald-600">-<?= number_format($proj['total_return'], 2) ?> บาท</td>
                                        <td class="py-3.5 px-4 text-right font-bold text-indigo-600 text-sm"><?= number_format($net_cost, 2) ?> บาท</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-400">ยังไม่มีข้อมูลโครงการหรือการเบิกจ่ายอุปกรณ์</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ส่วนที่ 2: ฟอร์มบันทึกค่าใช้จ่ายทั่วไป (รองรับเพิ่มหมวดหมู่ และแนบสลิป) -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div>
                    <h3 class="text-base md:text-lg font-bold text-slate-900">เพิ่มรายการค่าใช้จ่ายทั่วไป</h3>
                    <p class="text-xs text-slate-500 mt-1">บันทึกค่าใช้จ่ายอื่นๆ พร้อมเลือกหรือพิมพ์หมวดหมู่ใหม่ และแนบรูปสลิปโอนเงินได้ทันที</p>
                </div>

                <form action="expenses_process_admin.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <!-- โครงการ (Dropdown) -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน (ระบุหรือไม่ก็ได้)</label>
                        <select name="installation_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="">-- ส่วนกลาง / ไม่ระบุโครงการ --</option>
                            <?php foreach ($installationsDropdown as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- รายละเอียดค่าใช้จ่าย -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">รายการ / รายละเอียดค่าใช้จ่าย</label>
                        <input type="text" name="title" required placeholder="เช่น ค่าขนส่งแผงโซล่าเซลล์..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- จำนวนเงิน -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนเงิน (บาท)</label>
                        <input type="number" step="0.01" name="amount" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- หมวดหมู่ (เลือกหรือพิมพ์เพิ่ม) -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">หมวดหมู่ค่าใช้จ่าย</label>
                        <input type="text" name="category" list="categoryList" required placeholder="เลือกหรือพิมพ์หมวดหมู่..." value="ทั่วไป" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                        <datalist id="categoryList">
                            <option value="ทั่วไป">
                            <option value="เดินทาง/ขนส่ง">
                            <option value="อุปกรณ์/เครื่องมือ">
                            <option value="ค่าแรง/เงินเดือน">
                            <option value="ค่าอาหาร/รับรอง">
                            <option value="ค่าสาธารณูปโภค">
                            <option value="เบ็ดเตล็ด">
                        </datalist>
                    </div>

                    <!-- แนบรูปสลิป / หลักฐานการโอนเงิน -->
                    <div class="space-y-1.5 md:col-span-6">
                        <label class="block text-xs font-semibold text-slate-600">แนบรูปสลิปโอนเงิน / หลักฐานการจ่ายเงิน (ถ้ามี)</label>
                        <input type="file" name="receipt_image" accept="image/*" class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>

                    <div class="md:col-span-6 flex justify-end pt-2">
                        <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all cursor-pointer">
                            บันทึกค่าใช้จ่าย
                        </button>
                    </div>
                </form>
            </div>

            <!-- ส่วนที่ 3: ตารางประวัติค่าใช้จ่ายทั่วไปพร้อมแสดงภาพสลิป -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-900 px-2">ประวัติค่าใช้จ่ายทั่วไปล่าสุด</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">วันที่ / เวลา</th>
                                <th class="py-3 px-4 font-semibold">โครงการ</th>
                                <th class="py-3 px-4 font-semibold">รายการ</th>
                                <th class="py-3 px-4 font-semibold">หมวดหมู่</th>
                                <th class="py-3 px-4 font-semibold text-center">สลิปหลักฐาน</th>
                                <th class="py-3 px-4 font-semibold text-right">จำนวนเงิน (บาท)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($expensesList)): ?>
                                <?php foreach ($expensesList as $row): ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-slate-600 font-semibold">
                                            <?= htmlspecialchars($row['project_name'] ?? 'ส่วนกลาง / ไม่ระบุ') ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($row['title'] ?? $row['description'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4">
                                            <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-[11px] font-semibold">
                                                <?= htmlspecialchars($row['category'] ?? 'ทั่วไป') ?>
                                            </span>
                                        </td>
                                        <!-- คอลัมน์แสดงรูปสลิป -->
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if (!empty($row['receipt_image'])): ?>
                                                <div class="flex items-center justify-center gap-2">
                                                    <img src="../<?= htmlspecialchars($row['receipt_image']) ?>" alt="สลิป" onclick="openImageModal('../<?= htmlspecialchars($row['receipt_image']) ?>')" class="w-10 h-10 object-cover rounded-xl border border-slate-200 cursor-pointer hover:opacity-80 transition-opacity shadow-xs">
                                                    <button onclick="openImageModal('../<?= htmlspecialchars($row['receipt_image']) ?>')" class="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-2.5 py-1 rounded-xl text-[11px] font-bold inline-flex items-center gap-1">
                                                        <i class="fa-solid fa-expand"></i> ดูสลิป
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-bold text-rose-600"><?= number_format($row['amount'] ?? 0, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-slate-400 font-normal">ยังไม่มีรายการค่าใช้จ่ายทั่วไปในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal แสดงรูปสลิปขนาดใหญ่ -->
    <div id="imageModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-3xl max-w-lg w-full shadow-2xl space-y-4 text-center">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h4 class="font-bold text-slate-900 text-sm">หลักฐานการโอนเงิน / สลิป</h4>
                <button onclick="closeImageModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <div class="flex justify-center max-h-[70vh] overflow-auto">
                <img id="modalReceiptImg" src="" alt="Receipt Slip" class="rounded-2xl max-h-[60vh] object-contain border border-slate-200">
            </div>
            <div class="flex justify-end pt-2">
                <button onclick="closeImageModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Modal ตัวอย่างพิมพ์รายงาน PDF (TH Sarabun) -->
    <div id="printReportModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-10 shadow-2xl border border-slate-200 space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5 no-print">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center font-bold"><i class="fa-solid fa-file-pdf text-lg"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">ตัวอย่างรายงานค่าใช้จ่ายและต้นทุน</h3>
                        <!-- <p class="text-xs text-slate-500 font-medium">รูปแบบฟอนต์: TH Sarabun</p> -->
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
                            <h1 class="text-2xl font-bold uppercase tracking-wide text-slate-900 leading-tight">SOLARSTOCK</h1>
                            <p class="text-sm font-semibold text-slate-600">รายงานสรุปค่าใช้จ่ายและต้นทุนโครงการ (EXPENSE & COST REPORT)</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded text-xs font-bold uppercase mb-1">OFFICIAL REPORT</span>
                        <p class="text-xs font-semibold text-slate-600">วันที่พิมพ์: <?= date('d/m/Y') ?></p>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-bold text-slate-800 uppercase text-base border-b border-slate-200 pb-1">1. ประวัติค่าใช้จ่ายทั่วไป</h4>
                    <table class="w-full text-left border-collapse border border-slate-300 text-sm">
                        <thead>
                            <tr class="bg-slate-800 text-white font-bold text-sm">
                                <th class="py-2 px-3 border border-slate-700">วันที่</th>
                                <th class="py-2 px-3 border border-slate-700">โครงการ</th>
                                <th class="py-2 px-3 border border-slate-700">รายการ</th>
                                <th class="py-2 px-3 border border-slate-700">หมวดหมู่</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">จำนวนเงิน (บาท)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (!empty($expensesList)): foreach ($expensesList as $row): ?>
                                    <tr>
                                        <td class="py-2 px-3 border border-slate-300 text-xs"><?= htmlspecialchars($row['created_at']) ?></td>
                                        <td class="py-2 px-3 border border-slate-300 font-semibold"><?= htmlspecialchars($row['project_name'] ?? 'ส่วนกลาง') ?></td>
                                        <td class="py-2 px-3 border border-slate-300"><?= htmlspecialchars($row['title']) ?></td>
                                        <td class="py-2 px-3 border border-slate-300"><?= htmlspecialchars($row['category']) ?></td>
                                        <td class="py-2 px-3 border border-slate-300 text-right font-bold text-rose-700"><?= number_format($row['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="5" class="py-3 text-center text-slate-500 border border-slate-300">ไม่มีข้อมูลค่าใช้จ่าย</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-8 pt-8 page-break-inside-avoid">
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-10">
                        <p class="font-bold text-slate-800">ผู้จัดทำรายงาน</p>
                        <div class="space-y-1 text-xs">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-10">
                        <p class="font-bold text-slate-800">ผู้ตรวจสอบ / ผู้อนุมัติ</p>
                        <div class="space-y-1 text-xs">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script สำหรับควบคุม Sidebar และ Modal -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        }

        function openImageModal(imgSrc) {
            document.getElementById('modalReceiptImg').src = imgSrc;
            let modal = document.getElementById('imageModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeImageModal() {
            let modal = document.getElementById('imageModal');
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