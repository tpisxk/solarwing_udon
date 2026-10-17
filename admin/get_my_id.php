<?php
// กำหนด Channel Access Token ที่ได้จาก LINE Developers
$access_token = "pKse9FestSF5QdY9Hgnb7GtbcJqtXwucA50VBjLmWySwUbwM5JamQDMvlJflPQqTbibghoPRCGrGRnvehBxSooN0xjX6/3I4CF0nSeqbRgtKIBkE1mkxzPgmDKh+Io70jW7o548tAqX67bsJ3JQTBwdB04t89/1O/w1cDnyilFU=";
$ch = curl_init('https://api.line.me/v2/bot/followers/ids');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
$response = curl_exec($ch);
curl_close($ch);

echo "<h3>รายชื่อ User ID ของคนที่เพิ่มเพื่อนกับบอท:</h3>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>