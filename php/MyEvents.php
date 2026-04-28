<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["user_id"])) {
echo json_encode(["status" => "error", "message" => "not_logged_in"]);
exit;
}

$user_id = $_SESSION["user_id"];

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['action'])) {
echo json_encode(["status" => "error", "message" => "No action"]);
exit;
}

/* =========================
GET USER EVENTS
========================= */
if ($_GET['action'] == "get") {

$sql = "
SELECT
u.full_name,
u.total_points,
e.event_id,
e.title,
e.description,
e.category,
e.event_date,
e.start_time,
e.end_time,
e.location,
e.volunteer_hours,
e.points,
e.image_path,
e.attendance_code,
e.certificate_available,
r.registration_id,
r.attendance_status,
r.points_awarded,
c.certificate_id,
c.issue_date
FROM registrations r
JOIN events e ON e.event_id = r.event_id
JOIN users u ON u.user_id = r.user_id
LEFT JOIN certificates c ON c.registration_id = r.registration_id
WHERE r.user_id = $user_id
ORDER BY e.event_date ASC
";

$result = $conn->query($sql);

$events = [];
$user = "";
$total_points = 0;

while ($row = $result->fetch_assoc()) {
$user = $row["full_name"];
$total_points = $row["total_points"];
$events[] = $row;
}

echo json_encode([
"status" => "ok",
"user" => $user,
"total_points" => $total_points,
"events" => $events
]);
exit;
}

/* =========================
REGISTER EVENT FROM HOME
========================= */
if ($_GET['action'] == "register") {

$event_id = intval($_POST['event_id']);

$checkEvent = $conn->query("
SELECT event_id
FROM events
WHERE event_id = $event_id AND status = 'Approved'
");

if ($checkEvent->num_rows == 0) {
echo json_encode(["status" => "error", "message" => "Event not found"]);
exit;
}

$checkRegistration = $conn->query("
SELECT registration_id
FROM registrations
WHERE user_id = $user_id AND event_id = $event_id
");

if ($checkRegistration->num_rows == 0) {
$conn->query("
INSERT INTO registrations
(user_id, event_id, registration_date, attendance_status, points_awarded)
VALUES ($user_id, $event_id, CURDATE(), 'Registered', 0)
");
}

echo json_encode(["status" => "ok"]);
exit;
}

/* =========================
CANCEL REGISTRATION
========================= */
if ($_GET['action'] == "cancel") {

$event_id = intval($_POST['event_id']);

$check = $conn->query("
SELECT attendance_status
FROM registrations
WHERE user_id = $user_id AND event_id = $event_id
");

if ($check->num_rows == 0) {
echo json_encode(["status" => "error"]);
exit;
}

$row = $check->fetch_assoc();

if ($row["attendance_status"] == "Attended") {
echo json_encode(["status" => "attended"]);
exit;
}

$conn->query("
DELETE FROM registrations
WHERE user_id = $user_id AND event_id = $event_id
");

echo json_encode(["status" => "ok"]);
exit;
}

/* =========================
CONFIRM ATTENDANCE
========================= */
if ($_GET['action'] == "confirm") {

$event_id = intval($_POST['event_id']);
$code = trim($_POST['code']);

$sql = "
SELECT
e.attendance_code,
e.points,
r.registration_id,
r.attendance_status
FROM registrations r
JOIN events e ON e.event_id = r.event_id
WHERE r.user_id = $user_id AND e.event_id = $event_id
";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
echo json_encode(["status" => "error", "message" => "Not registered"]);
exit;
}

$row = $result->fetch_assoc();

if ($row["attendance_status"] == "Attended") {
echo json_encode(["status" => "already"]);
exit;
}

if ($row["attendance_code"] == NULL || $row["attendance_code"] == "") {
echo json_encode(["status" => "no_code"]);
exit;
}

if ($row["attendance_code"] != $code) {
echo json_encode(["status" => "wrong"]);
exit;
}

$points = intval($row["points"]);
$registration_id = intval($row["registration_id"]);

$conn->query("
UPDATE registrations
SET attendance_status = 'Attended',
points_awarded = $points
WHERE registration_id = $registration_id
");

$conn->query("
UPDATE users
SET total_points = total_points + $points
WHERE user_id = $user_id
");

$checkCert = $conn->query("
SELECT certificate_id
FROM certificates
WHERE registration_id = $registration_id
");

if ($checkCert->num_rows == 0) {
$conn->query("
INSERT INTO certificates (registration_id, issue_date)
VALUES ($registration_id, CURDATE())
");
}

echo json_encode(["status" => "ok"]);
exit;
}
?>
