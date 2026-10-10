<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'add_installation_admin';
$message = '';
$message_type = '';

// บันทึกข้อมูล Kick Off Form ลงฐานข้อมูล installations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_sale_no      = trim($_POST['project_sale_no'] ?? '');
    $lat_long             = trim($_POST['lat_long'] ?? '');
    $project_no           = trim($_POST['project_no'] ?? '');
    $installation_address = trim($_POST['installation_address'] ?? '');
    $name                 = trim($_POST['name'] ?? '');
    $billing_address      = trim($_POST['billing_address'] ?? '');
    $customer_name        = trim($_POST['customer_name'] ?? '');
    $sales_rep            = trim($_POST['sales_rep'] ?? '');
    $customer_phone       = trim($_POST['customer_phone'] ?? '');
    $system_size          = trim($_POST['system_size'] ?? '');
    
    // จัดการข้อมูลแยกงวดชำระเงิน
    $installments = $_POST['installment'] ?? [];
    $payment_terms_array = [];
    $payment_terms_text_parts = [];

    foreach ($installments as $item) {
        $pct  = trim($item['pct'] ?? '');
        $desc = trim($item['desc'] ?? '');
        $amt  = trim($item['amt'] ?? '');

        if ($pct !== '' || $desc !== '' || $amt !== '') {
            $payment_terms_array[] = [
                'pct' => $pct,
                'desc' => $desc,
                'amt' => $amt
            ];
            $payment_terms_text_parts[] = "งวด ($pct%): $desc " . ($amt ? "($amt บาท)" : "");
        }
    }

    // แปลงข้อมูส่งวดชำระเป็นข้อความเพื่อเก็บในฟิลด์ payment_terms
    $payment_terms = implode(" | ", $payment_terms_text_parts);

    // รวบรวมข้อมูลรายละเอียดปลีกย่อยเป็น JSON สำหรับเก็บในฟิลด์ description
    $extra_details = [
        "project_no" => $project_no,
        "installation_address" => $installation_address,
        "customer_name" => $customer_name,
        "customer_phone" => $customer_phone,
        "sales_rep" => $sales_rep,
        "payment_terms_list" => $payment_terms_array,
        "system_type" => $_POST['system_type'] ?? '',
        "panel_spec" => $_POST['panel_spec'] ?? '',
        "inverter_spec" => $_POST['inverter_spec'] ?? '',
        "mounting" => $_POST['mounting'] ?? ''
    ];
    $description = json_encode($extra_details, JSON_UNESCAPED_UNICODE);
    
    $system_details = "Panel: " . ($_POST['panel_spec'] ?? '-') . " | Inverter: " . ($_POST['inverter_spec'] ?? '-');
    $map_url        = trim($_POST['map_url'] ?? '');
    $status         = 'In Progress';

    if (empty($name)) {
        $message = 'กรุณากรอกชื่อโครงการ (Project Name)';
        $message_type = 'error';
    } else {
        try {
            $stmt_insert = $pdo->prepare("INSERT INTO installations (project_sale_no, name, system_size, description, billing_address, payment_terms, system_details, lat_long, map_url, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt_insert->execute([$project_sale_no, $name, $system_size, $description, $billing_address, $payment_terms, $system_details, $lat_long, $map_url, $status]);

            $new_id = $pdo->lastInsertId();
            
            // บันทึกสำเร็จ พาไปหน้าจัดการแผนงานย่อยต่อทันที
            header("Location: edit_installation.php?id=" . $new_id);
            exit();
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
    <title>Project Design / Kick Off Form - SolarStock Pro</title>
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

    <?php 
    if (file_exists('sidebar_admin.php')) {
        include 'sidebar_admin.php';
    }
    ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">PROJECT DESIGN / KICK OFF FORM</h2>
                <p class="text-sm text-slate-500 mt-0.5">กรอกข้อมูลรายละเอียดโครงการและแยกงวดการชำระเงิน</p>
            </div>
            <div>
                <a href="work_progress.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> กลับหน้ารายการ
                </a>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-8 w-full">
            <div class="max-w-4xl mx-auto space-y-6">

                <?php if (!empty($message)): ?>
                    <div class="px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2 <?= $message_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?>"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 space-y-6">
                    <div class="flex items-center gap-3 pb-6 border-b border-slate-100">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg shadow-inner">
                            <i class="fa-solid fa-file-contract"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">ฟอร์มบันทึกข้อมูลโครงการ (Kick Off Form)</h3>
                            <p class="text-xs text-slate-500">กรอกข้อมูลตามแบบฟอร์มเอกสารโครงการ</p>
                        </div>
                    </div>

                    <form action="add_installation_engineer.php" method="POST" class="space-y-6">
                        
                        <!-- ส่วนข้อมูลหัวกระดาษ -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Project Sale No.</label>
                                <input type="text" name="project_sale_no" placeholder="เช่น SW-26090012" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Location (Lat/Long)</label>
                                <input type="text" name="lat_long" placeholder="เช่น 17.882837,102.748563" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Project No.</label>
                                <input type="text" name="project_no" placeholder="รหัสโครงการ..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Installation Address</label>
                                <input type="text" name="installation_address" placeholder="สถานที่ติดตั้งหน้างาน..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Project Name *</label>
                                <input type="text" name="name" required placeholder="ชื่อโครงการ..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Billing Address</label>
                                <input type="text" name="billing_address" placeholder="ที่อยู่ใบกำกับภาษี..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Customer Name</label>
                                <input type="text" name="customer_name" placeholder="ชื่อลูกค้า..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Sales Representative</label>
                                <input type="text" name="sales_rep" placeholder="ชื่อผู้ขาย..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Customer Phone No.</label>
                                <input type="text" name="customer_phone" placeholder="เบอร์โทรศัพท์ลูกค้า..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">System Capacity (ขนาดติดตั้ง)</label>
                                <input type="text" name="system_size" placeholder="เช่น 108.36 kWp" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <!-- ส่วนแยกงวดชำระเงิน (Payment Terms / งวดที่, เปอร์เซ็นต์, จำนวนเงิน) -->
                        <div class="space-y-3 pt-4 border-t border-slate-100">
                            <label class="block text-xs font-bold text-slate-700 uppercase">เงื่อนไขการชำระเงิน (Payment Terms - แยกงวด)</label>
                            
                            <div class="space-y-2">
                                <div class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 uppercase px-1">
                                    <div class="col-span-2 text-center">งวดที่</div>
                                    <div class="col-span-3">เปอร์เซ็นต์ (%)</div>
                                    <div class="col-span-4">เงื่อนไข / รายละเอียด</div>
                                    <div class="col-span-3">จำนวนเงิน (บาท)</div>
                                </div>

                                <!-- งวดที่ 1 -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-2 text-center font-bold text-xs text-slate-600">งวด 1</div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[0][pct]" value="30%" placeholder="30%" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="text" name="installment[0][desc]" value="เมื่อตกลงราคา" placeholder="รายละเอียด..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[0][amt]" placeholder="465,000" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                </div>

                                <!-- งวดที่ 2 -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-2 text-center font-bold text-xs text-slate-600">งวด 2</div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[1][pct]" value="30%" placeholder="30%" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="text" name="installment[1][desc]" value="เมื่อส่งแผงโซล่าร์ถึงหน้างานพร้อมติดตั้ง" placeholder="รายละเอียด..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[1][amt]" placeholder="465,000" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                </div>

                                <!-- งวดที่ 3 -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-2 text-center font-bold text-xs text-slate-600">งวด 3</div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[2][pct]" value="30%" placeholder="30%" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="text" name="installment[2][desc]" value="เมื่อติดตั้งระบบแล้วเสร็จและจ่ายไฟเข้าระบบ" placeholder="รายละเอียด..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[2][amt]" placeholder="465,000" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                </div>

                                <!-- งวดที่ 4 -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-2 text-center font-bold text-xs text-slate-600">งวด 4</div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[3][pct]" value="10%" placeholder="10%" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="text" name="installment[3][desc]" value="เมื่อจ่ายไฟเข้าระบบครบ 15 วัน" placeholder="รายละเอียด..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="text" name="installment[3][amt]" placeholder="155,000" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ส่วนข้อมูลอุปกรณ์และสเปก -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">System Type</label>
                                <input type="text" name="system_type" placeholder="On-grid / Off-grid / Hybrid" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Panel Spec</label>
                                <input type="text" name="panel_spec" placeholder="รุ่นแผงโซล่าเซลล์..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase">Inverter Spec</label>
                                <input type="text" name="inverter_spec" placeholder="รุ่นอินเวอร์เตอร์..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 uppercase">ลิงก์แผนที่ Google Maps (Map URL)</label>
                            <input type="text" name="map_url" placeholder="https://maps.google.com/?q=..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-brandGreen">
                        </div>

                        <div class="flex justify-end pt-4 border-t border-slate-100 gap-3">
                            <a href="work_progress.php" class="px-6 py-2.5 rounded-2xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-100 transition-all text-center">
                                ยกเลิก
                            </a>
                            <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-2.5 rounded-2xl text-sm font-bold shadow-sm transition-all flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk text-xs"></i> บันทึกและไปเพิ่มแผนงานย่อย (Task)
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </main>

</body>
</html>