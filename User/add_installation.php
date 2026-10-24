<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'add_installation';
$message = '';
$message_type = '';
$swal_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_sale_no      = trim($_POST['project_sale_no'] ?? '');
    $project_no           = trim($_POST['project_no'] ?? '');
    $name                 = trim($_POST['name'] ?? '');
    $customer_name        = trim($_POST['customer_name'] ?? '');
    $customer_phone       = trim($_POST['customer_phone'] ?? '');
    $contract_person      = trim($_POST['contract_person'] ?? '');
    $sales_rep            = trim($_POST['sales_rep'] ?? '');
    $lat_long             = trim($_POST['lat_long'] ?? '');
    $system_size          = trim($_POST['system_size'] ?? '');
    $total_price          = floatval(str_replace(',', '', $_POST['total_price'] ?? '0'));
    $billing_address      = trim($_POST['billing_address'] ?? '');
    $installation_address = trim($_POST['installation_address'] ?? '');
    
    // ข้อมูลเชิงเทคนิคตามแบบฟอร์ม
    $meter_no             = trim($_POST['meter_no'] ?? '');
    $meter_type           = trim($_POST['meter_type'] ?? '');
    $system_type          = trim($_POST['system_type'] ?? '');
    $mea_pea_connection   = trim($_POST['mea_pea_connection'] ?? '');
    $warranty             = trim($_POST['warranty'] ?? '');
    $om                   = trim($_POST['om'] ?? '');
    $roof_change          = trim($_POST['roof_change'] ?? '');
    $structure_addition   = trim($_POST['structure_addition'] ?? '');
    
    $panel_spec           = trim($_POST['panel_spec'] ?? '');
    $inverter_spec        = trim($_POST['inverter_spec'] ?? '');
    $mounting             = trim($_POST['mounting'] ?? '');
    $connection_points    = trim($_POST['connection_points'] ?? '');
    $walk_way             = trim($_POST['walk_way'] ?? '');
    $installation_req     = trim($_POST['installation_req'] ?? '');
    $work_safety_req      = trim($_POST['work_safety_req'] ?? '');
    
    $zero_export          = trim($_POST['zero_export'] ?? '');
    $string_monitoring    = trim($_POST['string_monitoring'] ?? '');
    $other_components     = trim($_POST['other_components'] ?? '');
    
    // จัดการข้อมูลแยกงวดชำระเงิน (Payment Terms)
    $installments = $_POST['installment'] ?? [];
    $payment_terms_array = [];

    foreach ($installments as $item) {
        $pct     = trim($item['pct'] ?? '');
        $desc    = trim($item['desc'] ?? '');
        $amt_raw = trim($item['amt'] ?? '');

        if ($pct !== '' || $desc !== '' || $amt_raw !== '') {
            $payment_terms_array[] = [
                'pct' => $pct,
                'desc' => $desc,
                'amt' => $amt_raw
            ];
        }
    }

    $payment_terms_json = json_encode($payment_terms_array, JSON_UNESCAPED_UNICODE);

    if (empty($project_sale_no)) {
        $message = 'กรุณากรอก Project Sale No.';
        $message_type = 'error';
    } else {
        try {
            $stmt_design = $pdo->prepare("INSERT INTO project_designs (
                project_sale_no, installation_id, project_no, name, customer_name, customer_phone, contract_person, sales_rep, 
                lat_long, system_size, total_price, billing_address, installation_address, 
                meter_no, meter_type, system_type, mea_pea_connection, warranty, om, roof_change, structure_addition,
                panel_spec, inverter_spec, mounting, connection_points, walk_way, installation_req, work_safety_req,
                zero_export, string_monitoring, other_components, payment_terms_json, created_at
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            $stmt_design->execute([
                $project_sale_no, 
                $project_no, 
                $name, 
                $customer_name, 
                $customer_phone, 
                $contract_person,
                $sales_rep, 
                $lat_long, 
                $system_size, 
                $total_price, 
                $billing_address, 
                $installation_address,
                $meter_no,
                $meter_type,
                $system_type,
                $mea_pea_connection,$warranty,
                $om,$roof_change,
                $structure_addition,$panel_spec, 
                $inverter_spec,$mounting, 
                $connection_points,$walk_way,
                $installation_req,$work_safety_req,
                $zero_export,$string_monitoring,
                $other_components,$payment_terms_json
            ]);

            $swal_success = true;
        } catch (PDOException $e) {$message = 'เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: ' . $e->getMessage();$message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Design / Kick Off Form - SolarStock Pro</title>
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

    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto">
        <div class="flex-1 overflow-y-auto">
            <?php 
            if (file_exists('sidebar.php')) {
                include 'sidebar.php';
            }
            ?>
        </div>
    </div>

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight truncate">PROJECT DESIGN / KICK OFF FORM</h2>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">ฟอร์มบันทึกข้อมูลโครงการตามแบบฟอร์มบริษัท</p>
                </div>
            </div>
            <div>
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">กลับหน้ารายการ</span>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 lg:p-8 w-full">
            <div class="max-w-4xl mx-auto space-y-6">

                <?php if (!empty($message)): ?>
                    <div class="px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2 <?= $message_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?>"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 lg:p-8 space-y-6">
                    <div class="flex items-center gap-3 pb-6 border-b border-slate-100">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg shadow-inner shrink-0">
                            <i class="fa-solid fa-file-contract"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">PROJECT DESIGN / KICK OFF FORM</h3>
                            <p class="text-xs text-slate-500">บันทึกข้อมูลตรงสู่ตาราง project_designs</p>
                        </div>
                    </div>

                    <form action="add_installation.php" method="POST" class="space-y-6">
                        
                        <!-- ข้อมูลหลักส่วนที่ 1 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Project Sale No. *</label>
                                <input type="text" name="project_sale_no" value="" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Location (Lat/Long)</label>
                                <input type="text" name="lat_long" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Project No.</label>
                                <input type="text" name="project_no" placeholder="รหัสโครงการ..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Installation Address</label>
                                <input type="text" name="installation_address" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Project Name *</label>
                                <input type="text" name="name" value="" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Billing Address</label>
                                <input type="text" name="billing_address" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Customer Name</label>
                                <input type="text" name="customer_name" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Contract Person</label>
                                <input type="text" name="contract_person" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Sales Representative</label>
                                <input type="text" name="sales_rep" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Customer Phone No.</label>
                                <input type="text" name="customer_phone" placeholder="เบอร์โทรศัพท์..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">System Capacity (ขนาดติดตั้ง)</label>
                                <input type="text" name="system_size" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <!-- ราคารวม -->
                        <div class="bg-emerald-50/60 p-4 rounded-2xl border border-emerald-200 space-y-1">
                            <label class="block text-xs font-bold text-emerald-800 uppercase"><i class="fa-solid fa-coins me-1"></i> มูลค่าระบบ (รวม VAT) *</label>
                            <input type="text" id="total_price_input" name="total_price" value="1,550,000" oninput="calculateInstallments()" class="w-full px-4 py-2.5 rounded-xl border border-emerald-300 bg-white text-sm font-bold text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <!-- เงื่อนไขการชำระเงิน (Payment Terms) -->
                        <div class="space-y-3 pt-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase">เงื่อนไขการชำระเงิน (Payment Terms)</label>
                                    <span id="averagePctDisplay" class="text-[11px] font-semibold text-emerald-600 mt-0.5 block"></span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="distributeEvenly()" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 border border-blue-200">
                                        <i class="fa-solid fa-calculator text-[10px]"></i> เฉลี่ย % เท่ากัน
                                    </button>
                                    <button type="button" onclick="addInstallmentRow()" class="bg-emerald-50 hover:bg-emerald-100 text-brandGreen px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 border border-emerald-200">
                                        <i class="fa-solid fa-plus text-[10px]"></i> เพิ่มงวด
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2 overflow-x-auto">
                                <div class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 uppercase px-1 min-w-[550px]">
                                    <div class="col-span-2 text-center">งวดที่</div>
                                    <div class="col-span-3">เปอร์เซ็นต์ (%)</div>
                                    <div class="col-span-4">เงื่อนไข / รายละเอียด</div>
                                    <div class="col-span-2">จำนวนเงิน (บาท)</div>
                                    <div class="col-span-1 text-center">จัดการ</div>
                                </div>

                                <div id="installmentContainer" class="space-y-2 min-w-[550px]">
                                    <!-- งวดที่ 1 -->
                                    <div class="grid grid-cols-12 gap-2 items-center installment-row">
                                        <div class="col-span-2 text-center font-bold text-xs text-slate-600 installment-label">งวด 1</div>
                                        <div class="col-span-3"><input type="text" name="installment[0][pct]" value="25" oninput="calculateInstallments()" class="pct-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-4"><input type="text" name="installment[0][desc]" value="มัดจำทำสัญญา" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-2"><input type="text" name="installment[0][amt]" value="" class="amt-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold"></div>
                                        <div class="col-span-1 text-center">
                                            <button type="button" onclick="removeInstallmentRow(this)" class="text-rose-500 hover:text-rose-700 p-2 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>

                                    <!-- งวดที่ 2 -->
                                    <div class="grid grid-cols-12 gap-2 items-center installment-row">
                                        <div class="col-span-2 text-center font-bold text-xs text-slate-600 installment-label">งวด 2</div>
                                        <div class="col-span-3"><input type="text" name="installment[1][pct]" value="25" oninput="calculateInstallments()" class="pct-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-4"><input type="text" name="installment[1][desc]" value="จัดส่งอุปกรณ์เข้าหน้างาน" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-2"><input type="text" name="installment[1][amt]" value="" class="amt-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold"></div>
                                        <div class="col-span-1 text-center">
                                            <button type="button" onclick="removeInstallmentRow(this)" class="text-rose-500 hover:text-rose-700 p-2 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>

                                    <!-- งวดที่ 3 -->
                                    <div class="grid grid-cols-12 gap-2 items-center installment-row">
                                        <div class="col-span-2 text-center font-bold text-xs text-slate-600 installment-label">งวด 3</div>
                                        <div class="col-span-3"><input type="text" name="installment[2][pct]" value="25" oninput="calculateInstallments()" class="pct-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-4"><input type="text" name="installment[2][desc]" value="ติดตั้งงานโครงสร้างและอุปกรณ์" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-2"><input type="text" name="installment[2][amt]" value="" class="amt-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold"></div>
                                        <div class="col-span-1 text-center">
                                            <button type="button" onclick="removeInstallmentRow(this)" class="text-rose-500 hover:text-rose-700 p-2 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>

                                    <!-- งวดที่ 4 -->
                                    <div class="grid grid-cols-12 gap-2 items-center installment-row">
                                        <div class="col-span-2 text-center font-bold text-xs text-slate-600 installment-label">งวด 4</div>
                                        <div class="col-span-3"><input type="text" name="installment[3][pct]" value="25" oninput="calculateInstallments()" class="pct-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-4"><input type="text" name="installment[3][desc]" value="ทดสอบระบบและส่งมอบงาน" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                                        <div class="col-span-2"><input type="text" name="installment[3][amt]" value="" class="amt-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold"></div>
                                        <div class="col-span-1 text-center">
                                            <button type="button" onclick="removeInstallmentRow(this)" class="text-rose-500 hover:text-rose-700 p-2 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ข้อมูลทางเทคนิคส่วนที่ 2 (Usage & Roof Area / Commercial Offer) -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-bold text-emerald-700 uppercase"><i class="fa-solid fa-sliders me-1"></i> Usage & Roof Area / Commercial Offer</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Meter #</label>
                                    <input type="text" name="meter_no" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Meter Type</label>
                                    <input type="text" name="meter_type" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">System Type</label>
                                    <input type="text" name="system_type" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">MEA / PEA Connection</label>
                                    <input type="text" name="mea_pea_connection" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Warranty</label>
                                    <input type="text" name="warranty" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">O&M</label>
                                    <input type="text" name="om" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Roof Change</label>
                                    <input type="text" name="roof_change" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Structure Addition</label>
                                    <input type="text" name="structure_addition" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                            </div>
                        </div>

                        <!-- ข้อมูลระบบ (System Requirements) -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-bold text-emerald-700 uppercase"><i class="fa-solid fa-solar-panel me-1"></i> System Requirements</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Panel</label>
                                    <input type="text" name="panel_spec" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Inverter</label>
                                    <input type="text" name="inverter_spec" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Mounting</label>
                                    <input type="text" name="mounting" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Connection Points</label>
                                    <input type="text" name="connection_points" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Walk way</label>
                                    <input type="text" name="walk_way" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Installation</label>
                                    <input type="text" name="installation_req" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Work Safety Requirement</label>
                                <input type="text" name="work_safety_req" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                            </div>
                        </div>

                        <!-- ระบบเพิ่มเติม (Additional Systems) -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-bold text-emerald-700 uppercase"><i class="fa-solid fa-cogs me-1"></i> Additional Systems</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Zero Export</label>
                                    <input type="text" name="zero_export" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">String Monitoring</label>
                                    <input type="text" name="string_monitoring" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold text-slate-600 uppercase">Other special components</label>
                                    <input type="text" name="other_components" value="" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-end pt-4 border-t border-slate-100 gap-3">
                            <a href="work_progress.php" class="px-6 py-2.5 rounded-2xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-100 transition-all text-center">
                                ยกเลิก
                            </a>
                            <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-2.5 rounded-2xl text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-floppy-disk text-xs"></i> บันทึกข้อมูล Project Design
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
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // คำนวณยอดเงินตามเปอร์เซ็นต์ และคำนวณ % รวม/เฉลี่ย
        function calculateInstallments() {
            const totalPriceInput = document.getElementById('total_price_input');
            const totalPrice = parseFloat(totalPriceInput.value.replace(/,/g, '')) || 0;
            const rows = document.querySelectorAll('.installment-row');
            
            let totalPct = 0;
            let countPct = 0;

            rows.forEach((row) => {
                const pctInput = row.querySelector('.pct-input');
                const amtInput = row.querySelector('.amt-input');
                
                if (pctInput && amtInput) {
                    const pct = parseFloat(pctInput.value) || 0;
                    if (pct > 0) {
                        totalPct += pct;
                        countPct++;
                    }
                    if (totalPrice > 0) {
                        const calculatedAmount = (totalPrice * pct) / 100;
                        amtInput.value = calculatedAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
            });

            // คำนวณ % เฉลี่ยจากงวดที่มีการกรอกข้อมูล
            const avgPct = countPct > 0 ? (totalPct / countPct).toFixed(2) : 0;
            const avgDisplay = document.getElementById('averagePctDisplay');
            if (avgDisplay) {
                avgDisplay.innerText = `รวม: ${totalPct}% (เฉลี่ยต่องวด: ${avgPct}%)`;
                if (totalPct === 100) {
                    avgDisplay.className = "text-[11px] font-bold text-emerald-600 mt-0.5 block";
                } else {
                    avgDisplay.className = "text-[11px] font-bold text-amber-600 mt-0.5 block";
                }
            }
        }

        // เฉลี่ย % เท่ากันทุกงวด
        function distributeEvenly() {
            const rows = document.querySelectorAll('.installment-row');
            const count = rows.length;
            if (count === 0) return;

            const evenPct = (100 / count).toFixed(2);
            rows.forEach((row) => {
                const pctInput = row.querySelector('.pct-input');
                if (pctInput) {
                    pctInput.value = evenPct;
                }
            });
            calculateInstallments();
        }

        function addInstallmentRow() {
            const container = document.getElementById('installmentContainer');
            const rowCount = container.querySelectorAll('.installment-row').length;
            const newIndex = rowCount;

            const newRow = document.createElement('div');
            newRow.className = 'grid grid-cols-12 gap-2 items-center installment-row';
            newRow.innerHTML = `
                <div class="col-span-2 text-center font-bold text-xs text-slate-600 installment-label">งวด ${newIndex + 1}</div>
                <div class="col-span-3"><input type="text" name="installment[${newIndex}][pct]" value="" oninput="calculateInstallments()" class="pct-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                <div class="col-span-4"><input type="text" name="installment[${newIndex}][desc]" value="" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs"></div>
                <div class="col-span-2"><input type="text" name="installment[${newIndex}][amt]" value="" class="amt-input w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold"></div>
                <div class="col-span-1 text-center">
                    <button type="button" onclick="removeInstallmentRow(this)" class="text-rose-500 hover:text-rose-700 p-2 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                </div>
            `;
            container.appendChild(newRow);
            updateInstallmentLabels();
            calculateInstallments();
        }

        function removeInstallmentRow(button) {
            const row = button.closest('.installment-row');
            const container = document.getElementById('installmentContainer');
            
            if (container.querySelectorAll('.installment-row').length > 1) {
                row.remove();
                updateInstallmentLabels();
                calculateInstallments();
            } else {
                alert('ต้องมีเงื่อนไขการชำระเงินอย่างน้อย 1 งวด');
            }
        }

        function updateInstallmentLabels() {
            const rows = document.querySelectorAll('.installment-row');
            rows.forEach((row, index) => {
                const label = row.querySelector('.installment-label');
                if (label) {
                    label.innerText = `งวด ${index + 1}`;
                }
                const inputs = row.querySelectorAll('input');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name) {
                        const newName = name.replace(/\[\d+\]/, `[${index}]`);
                        input.setAttribute('name', newName);
                    }
                });
            });
        }

        // คำนวณครั้งแรกทันทีเมื่อโหลดหน้าเว็บ
        document.addEventListener('DOMContentLoaded', () => {
            calculateInstallments();
        });

        <?php if ($swal_success): ?>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ!',
            text: 'ข้อมูล Project Design ถูกบันทึกลงระบบเรียบร้อยแล้ว',
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#059669',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'dashboard.php';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>