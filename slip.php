
**การใส่ รหัส clientid:clientsecret ครับ**
///////////////////////////////////////////////////////////////////////////////

basic auth คือเอา clientid:clientsecret รวมกันแล้ว encode base64

**หากไม่ encode จะไม่สามารถเข้ารหัสได้**
<!-- ตัวอย่างที่1 ลง clientid:clientsecret เอง -->
curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://suba.rdcw.co.th/v1/inquiry',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
          "payload": "' . $qrcode . '"
 }',
<!-- หลัง json ไห้ทำการ encode ต่อจาก basic auth -->
<!-- ทำการรวมรหัส แล้ว encode base64 ต่อ -->

<!-- ตรวจสอบ ID เข้ามา -->
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_SESSION['id'])) {
        $plr = dd_q("SELECT * FROM users WHERE id = ?", [$_SESSION['id']])->fetch(PDO::FETCH_ASSOC);
        if ($_POST['qrcode'] != '') {
            $sc = json_decode($ys->slip_check($_POST['qrcode']));
            if ($sc->valid == true) {
                $recv_name = explode(" ", $sc->data->receiver->displayName);
                if ($config_bank['fname'] == $recv_name[1]) {
                    $info = $sc->data;
                    $amount = $info->amount;
                    $ref =  $info->transRef;
                    
                    <!-- เก็บประวัติการทำรายการ -->
                    $q1 = dd_q("SELECT * FROM kbank_trans WHERE ref = ?", [$ref]);
                    $q2 = dd_q("SELECT * FROM kbank_trans WHERE qr = ?", [$_POST['qrcode']]);
                    if ($q1->rowCount() == 0 || $q2->rowCount() == 0) {
                        $ha = dd_q(
                            "INSERT INTO `topup_his` (`id`, `link`, `amount`, `date`, `uid`, `uname`) VALUES (NULL, ? ,  ? , NOW() , ? , ? )",
                            [
                                "สลิปบัญชีชื่อ : " . $info->sender->displayName,
                                $amount,
                                $_SESSION['id'],
                                $plr['username']
                            ]
                        );
                        
                        <!-- SQL เพิ่มพอยท์ หรือ ยอดเงินเข้า ID -->
                        $insert_ref = dd_q("INSERT INTO `kbank_trans`(`qr`, `ref`, `sender`, `date`) VALUES(?, ?, ?, ?)", [$_POST['qrcode'], $ref, $info->sender->displayName, date("Y-m-d h:i:s")]);
                        $update_user = dd_q("UPDATE users SET point = point + ?, total = total + ? WHERE id = ?", [$amount, $amount,$_SESSION['id']]);
                        if ($ha and $insert_ref and $update_user) {
                            dd_return(true, "คุณเติมเงินสำเร็จ " . $amount . " บาท");
                        } else {
                            dd_return(false, "SQL ผิดพลาด");
                        }
                    } else {
                        dd_return(false, "สลิปนี้ใช้แล้ว");
                    }
                } else {
                    dd_return(false, "หมายเลขบัญชีหรือธนาคารไม่ตรงกับทางร้าน");
                }
            } else {
                dd_return(false, "Qr code ไม่ถูกต้อง"); <!-- คือล็อกอินไม่สำเร็จ -->
            }
        } else {
            dd_return(false, "กรุณาส่งข้อมูลให้ครบ"); <!-- คือส่งข้อมูลไม่ครบ -->
        }
    } else {
        dd_return(false, "เข้าสู่ระบบก่อนดำเนินการ"); <!-- คือการล็อกอินเว็บ -->
    }
} else {
    dd_return(false, "Method '{$_SERVER['REQUEST_METHOD']}' not allowed!");
}
