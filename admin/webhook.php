<?php
// อ่านข้อมูลที่ LINE ส่งมา
$content = file_get_contents('php://input');
$events = json_decode($content, true);

// ถ้าเปิดผ่านเบราว์เซอร์ปกติ จะแสดง User ID ล่าสุดจากไฟล์ log
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists('debug_log.txt')) {
        echo "<h2 style='color: green;'>User ID ล่าสุดของคุณคือ:</h2>";
        echo "<pre style='font-size: 18px; font-weight: bold; background: #f0f0f0; padding: 15px; border-radius: 8px;'>" . file_get_contents('debug_log.txt') . "</pre>";
    } else {
        echo "<h2>ยังไม่มีข้อมูลส่งมา กรุณาพิมพ์ข้อความหาบอทใน LINE 1 ครั้ง แล้วรีเฟรชหน้านี้ครับ</h2>";
    }
    exit();
}

// ถ้า LINE ส่ง Webhook มา (POST)
if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if (isset($event['source']['userId'])) {
            $userId = $event['source']['userId'];
            file_put_contents('debug_log.txt', $userId);
        }
    }
}

http_response_code(200);
echo "OK";
?>