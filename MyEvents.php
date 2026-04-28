<?php

include "db_connect.php";

$user_id = 1; // مؤقت


/* ================= GET ================= */

if($_GET['action']=="get"){


$sql = "

SELECT 

  e.event_id,

  e.title,

  e.event_date,

  e.location,

  e.image_path,

  e.points,

  e.attendance_code,

  (e.attendance_code IS NOT NULL AND e.attendance_code != '') AS code_active,

  r.attendance_status,

  u.full_name,

  c.certificate_id

FROM registrations r

JOIN events e ON e.event_id = r.event_id

JOIN users u ON u.user_id = r.user_id

LEFT JOIN certificates c ON c.registration_id = r.registration_id

WHERE r.user_id = $user_id

";



$res = $conn->query($sql);



$data = [];

$user="";



while($row = $res->fetch_assoc()){

  $user = $row['full_name'];

  $data[] = $row;

}



echo json_encode([

  "user"=>$user,

  "events"=>$data

]);

exit;

}



/* ================= CONFIRM ================= */

if($_GET['action']=="confirm"){



$event_id = $_POST['event_id'];

$code = $_POST['code'];



$res = $conn->query("SELECT attendance_code, points FROM events WHERE event_id=$event_id");

$event = $res->fetch_assoc();



if($event['attendance_code'] != $code){

  echo json_encode(["status"=>"wrong"]);

  exit;

}



$conn->query("

UPDATE registrations 

SET attendance_status='Attended', points_awarded=".$event['points']."

WHERE user_id=$user_id AND event_id=$event_id

");



/* إضافة شهادة */

$res2 = $conn->query("

SELECT registration_id FROM registrations 

WHERE user_id=$user_id AND event_id=$event_id

");

$r = $res2->fetch_assoc();



$conn->query("

INSERT INTO certificates (registration_id, issue_date)

VALUES (".$r['registration_id'].", CURDATE())

");



echo json_encode(["status"=>"ok"]);

exit;

}

?>
