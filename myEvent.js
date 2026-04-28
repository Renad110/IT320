let eventsData = [];
let userName = "";
let totalPoints = 0;
let currentTab = "upcoming";

/* =========================
FETCH DATA
========================= */
async function fetchEvents() {
const response = await fetch("myEvents.php?action=get");
const data = await response.json();

if (data.status !== "ok") {
showToast("حدث خطأ في تحميل البيانات", "error");
return;
}

eventsData = data.events;
userName = data.user;
totalPoints = Number(data.total_points || 0);

updateUserHeader();
renderEvents();
}

/* =========================
AUTO REGISTER FROM HOME
========================= */
async function autoRegisterFromQuery() {
const params = new URLSearchParams(window.location.search);
const eventId = params.get("id");

if (!eventId) return;

const response = await fetch("myEvents.php?action=register", {
method: "POST",
headers: { "Content-Type": "application/x-www-form-urlencoded" },
body: `event_id=${eventId}`
});

const data = await response.json();

if (data.status === "ok") {
showToast("تم التسجيل في الفعالية بنجاح", "success");
} else {
showToast("تعذر التسجيل في الفعالية", "error");
}

window.history.replaceState({}, document.title, "myEvent.html");
}

/* =========================
USER HEADER
========================= */
function updateUserHeader() {
const userNameElement = document.querySelector(".user-name");
const avatarElement = document.querySelector(".user-avatar");
const badgeName = document.querySelector(".badge-name");
const badgePts = document.querySelector(".badge-pts");
const progressBar = document.querySelector(".progress-bar");

if (userNameElement) userNameElement.textContent = userName || "مستخدم";
if (avatarElement) avatarElement.textContent = userName ? userName.charAt(0) : "م";

let badge = "برونزي";
let emoji = "🥉";

if (totalPoints >= 100) {
badge = "ذهبي";
emoji = "🥇";
} else if (totalPoints >= 50) {
badge = "فضي";
emoji = "🥈";
}

if (badgeName) badgeName.textContent = `${emoji} ${badge}`;
if (badgePts) badgePts.textContent = `${totalPoints} / 100 نقطة`;
if (progressBar) progressBar.style.width = `${Math.min(totalPoints, 100)}%`;
}

/* =========================
DATE HELPERS
========================= */
function getToday() {
const today = new Date();
today.setHours(0, 0, 0, 0);
return today;
}

function getEventDate(date) {
const eventDate = new Date(date);
eventDate.setHours(0, 0, 0, 0);
return eventDate;
}

function getStatus(event) {
const today = getToday();
const eventDate = getEventDate(event.event_date);

if (event.attendance_status === "Attended") return "completed";
if (eventDate < today) return "past";
if (eventDate.getTime() === today.getTime()) return "today";

return "upcoming";
}

function getStatusLabel(event) {
const status = getStatus(event);

if (status === "completed") return "مكتملة";
if (status === "past") return "سابقة";
if (status === "today") return "جارية";

return "قادمة";
}

/* =========================
FILTER
========================= */
function filterEvents() {
return eventsData.filter(event => {
const status = getStatus(event);

if (currentTab === "completed") return status === "completed";
if (currentTab === "past") return status === "past";

return status === "upcoming" || status === "today";
});
}

/* =========================
RENDER ACTIONS
========================= */
function renderActions(event) {
const status = getStatus(event);

if (status === "completed") {
if (event.certificate_id && event.certificate_available == 1) {
return `
<div class="actions">
<button class="btn btn-soft" onclick="openCertificate(${event.event_id})">
عرض الشهادة
</button>
</div>
`;
}

return `
<div class="code-box">
<p>الحضور مؤكد، لكن الشهادة غير متاحة لهذه الفعالية</p>
</div>
`;
}

if (status === "today") {
if (!event.attendance_code) {
return `
<div class="code-waiting">
<div class="pulse"></div>
<p>بانتظار تفعيل كود الحضور من المنظم...</p>
</div>
<div class="actions">
<button class="btn btn-danger" onclick="cancelRegistration(${event.event_id})">
إلغاء التسجيل
</button>
</div>
`;
}

return `
<div class="code-box">
<h4>تأكيد الحضور</h4>
<input
type="text"
id="code-${event.event_id}"
class="code-input"
placeholder="أدخل كود الحضور"
oninput="enableConfirmButton(${event.event_id})"
/>
<button
id="confirm-${event.event_id}"
class="btn btn-primary"
onclick="confirmAttendance(${event.event_id})"
disabled
>
تأكيد الحضور
</button>
</div>
<div class="actions">
<button class="btn btn-danger" onclick="cancelRegistration(${event.event_id})">
إلغاء التسجيل
</button>
</div>
`;
}

if (status === "upcoming") {
return `
<div class="code-box">
<h4>الحضور</h4>
<p>لم تبدأ الفعالية بعد</p>
</div>
<div class="actions">
<button class="btn btn-danger" onclick="cancelRegistration(${event.event_id})">
إلغاء التسجيل
</button>
</div>
`;
}

return `
<div class="code-box">
<h4>الحضور</h4>
<p style="color:#dc2626;">انتهت الفعالية ولم يتم تأكيد الحضور</p>
</div>
`;
}

/* =========================
RENDER EVENTS
========================= */
function renderEvents() {
const grid = document.getElementById("eventsGrid");
const emptyState = document.getElementById("emptyState");

const list = filterEvents();

grid.innerHTML = "";

if (!list.length) {
emptyState.style.display = "block";
return;
}

emptyState.style.display = "none";

list.forEach(event => {
const card = document.createElement("div");
card.className = "event-card";

card.innerHTML = `
<img class="event-img" src="${event.image_path}" alt="${event.title}" />

<div class="event-body">
<div class="event-meta">
<span class="status-tag">${getStatusLabel(event)}</span>
<span class="pts-badge">${event.points} نقطة</span>
</div>

<div class="event-title">${event.title}</div>

<div class="event-details">
<div>📅 ${event.event_date}</div>
<div>🕒 ${event.start_time} - ${event.end_time}</div>
<div>📍 ${event.location}</div>
<div>⏱ ${event.volunteer_hours} ساعات تطوعية</div>
</div>

${renderActions(event)}
</div>
`;

grid.appendChild(card);
});
}

/* =========================
BUTTON ENABLE
========================= */
function enableConfirmButton(eventId) {
const input = document.getElementById(`code-${eventId}`);
const button = document.getElementById(`confirm-${eventId}`);

if (!input || !button) return;

button.disabled = input.value.trim() === "";
}

/* =========================
CONFIRM ATTENDANCE
========================= */
async function confirmAttendance(eventId) {
const input = document.getElementById(`code-${eventId}`);
const button = document.getElementById(`confirm-${eventId}`);

if (!input) return;

button.textContent = "جاري التحقق...";
button.disabled = true;

const response = await fetch("myEvents.php?action=confirm", {
method: "POST",
headers: { "Content-Type": "application/x-www-form-urlencoded" },
body: `event_id=${eventId}&code=${encodeURIComponent(input.value.trim())}`
});

const data = await response.json();

if (data.status === "wrong") {
showToast("كود الحضور غير صحيح", "error");
button.textContent = "تأكيد الحضور";
button.disabled = false;
return;
}

if (data.status === "no_code") {
showToast("لم يتم تفعيل الكود بعد", "error");
button.textContent = "تأكيد الحضور";
button.disabled = false;
return;
}

if (data.status === "already") {
showToast("تم تأكيد حضورك مسبقًا", "success");
fetchEvents();
return;
}

if (data.status === "ok") {
showToast("تم تأكيد الحضور بنجاح", "success");
fetchEvents();
return;
}

showToast("حدث خطأ أثناء تأكيد الحضور", "error");
button.textContent = "تأكيد الحضور";
button.disabled = false;
}

/* =========================
CANCEL REGISTRATION
========================= */
async function cancelRegistration(eventId) {
const response = await fetch("myEvents.php?action=cancel", {
method: "POST",
headers: { "Content-Type": "application/x-www-form-urlencoded" },
body: `event_id=${eventId}`
});

const data = await response.json();

if (data.status === "attended") {
showToast("لا يمكن إلغاء التسجيل بعد تأكيد الحضور", "error");
return;
}

if (data.status === "ok") {
showToast("تم إلغاء التسجيل", "success");
fetchEvents();
return;
}

showToast("تعذر إلغاء التسجيل", "error");
}

/* =========================
CERTIFICATE
========================= */
function openCertificate(eventId) {
const event = eventsData.find(e => Number(e.event_id) === Number(eventId));
if (!event) return;

const certificateWindow = window.open("", "_blank");

certificateWindow.document.write(`
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>شهادة مشاركة - ${event.title}</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<style>
body {
margin: 0;
font-family: 'Tajawal', sans-serif;
background:
radial-gradient(circle at top right, rgba(45,106,53,0.10), transparent 22%),
radial-gradient(circle at bottom left, rgba(185,154,58,0.12), transparent 24%),
linear-gradient(180deg, #f7faf8 0%, #eef6f0 100%);
min-height: 100vh;
padding: 32px;
color: #1f2937;
}

.certificate-page {
min-height: calc(100vh - 64px);
display: flex;
align-items: center;
justify-content: center;
}

.certificate-shell {
width: 100%;
max-width: 1100px;
background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
border: 4px solid #cfe5d4;
border-radius: 32px;
box-shadow: 0 24px 60px rgba(25, 58, 37, 0.14);
overflow: hidden;
}

.cert-top-strip {
height: 18px;
background: linear-gradient(90deg, #1e4d25, #4d8f60, #b99a3a, #4d8f60, #1e4d25);
}

.cert-header {
text-align: center;
padding: 42px 42px 15px;
}

.cert-logo {
width: 115px;
height: auto;
margin-bottom: 10px;
}

.cert-platform {
color: #6b7280;
margin: 0 0 8px;
font-size: 1rem;
}

.cert-title {
margin: 0;
font-size: 2.8rem;
color: #1e4d25;
font-weight: 800;
}

.cert-subtitle {
color: #8a948f;
margin-top: 8px;
}

.cert-body {
text-align: center;
padding: 10px 60px 30px;
}

.cert-intro, .cert-mid {
font-size: 1.15rem;
color: #58635f;
line-height: 1.9;
margin: 14px 0;
}

.cert-name {
font-size: 3rem;
color: #1f5c32;
font-weight: 800;
margin: 18px auto;
display: inline-block;
padding: 0 18px 8px;
border-bottom: 4px solid #d8b44c;
}

.cert-event {
font-size: 1.8rem;
color: #1f2937;
margin: 15px 0 25px;
}

.cert-info-grid {
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: 18px;
margin: 28px 0 24px;
}

.cert-info-box {
background: #f6faf7;
border: 1px solid #d9e8dc;
border-radius: 18px;
padding: 18px;
}

.cert-info-label {
display: block;
color: #7b8580;
font-size: .9rem;
margin-bottom: 8px;
}

.cert-info-value {
color: #1f2937;
font-weight: 700;
}

.cert-message {
background: linear-gradient(135deg, #f2f9f4, #f9fcfa);
border: 1px dashed #b8d8bf;
border-radius: 22px;
padding: 22px;
font-size: 1rem;
line-height: 2;
color: #42514a;
}

.cert-footer {
display: flex;
align-items: end;
justify-content: space-between;
gap: 20px;
padding: 0 60px 42px;
flex-wrap: wrap;
}

.signature {
text-align: center;
}

.signature-line {
width: 220px;
height: 2px;
background: #1e4d25;
margin: 0 auto 10px;
}

.cert-badge {
background: linear-gradient(135deg, #edf7ef, #f8fcf8);
color: #245331;
border: 1px solid #cfe5d4;
border-radius: 999px;
padding: 12px 18px;
font-weight: 700;
}

.cert-actions {
display: flex;
justify-content: center;
gap: 14px;
padding: 0 24px 34px;
}

.cert-btn {
border: none;
border-radius: 14px;
padding: 14px 24px;
font-family: inherit;
font-size: 1rem;
font-weight: 700;
cursor: pointer;
}

.print {
background: linear-gradient(135deg, #2d6a35, #1e4d25);
color: white;
}

.close {
background: #f3f4f6;
color: #374151;
}

@media print {
body {
background: #fff;
padding: 0;
}

.cert-actions {
display: none;
}

.certificate-shell {
box-shadow: none;
max-width: 100%;
}
}
</style>
</head>
<body>
<div class="certificate-page">
<div class="certificate-shell">
<div class="cert-top-strip"></div>

<div class="cert-header">
<img src="logo.jpg" class="cert-logo" alt="نفل">
<p class="cert-platform">منصة نفل البيئية</p>
<h1 class="cert-title">شهادة مشاركة</h1>
<p class="cert-subtitle">Environmental Participation Certificate</p>
</div>

<div class="cert-body">
<p class="cert-intro">تتشرف منصة نفل البيئية بمنح هذه الشهادة إلى</p>
<h2 class="cert-name">${userName}</h2>
<p class="cert-mid">تقديرًا لمشاركتها الفاعلة في فعالية</p>
<h3 class="cert-event">${event.title}</h3>

<div class="cert-info-grid">
<div class="cert-info-box">
<span class="cert-info-label">التاريخ</span>
<span class="cert-info-value">${event.event_date}</span>
</div>

<div class="cert-info-box">
<span class="cert-info-label">الساعات التطوعية</span>
<span class="cert-info-value">${event.volunteer_hours} ساعات</span>
</div>

<div class="cert-info-box">
<span class="cert-info-label">النقاط المكتسبة</span>
<span class="cert-info-value">${event.points} نقطة</span>
</div>
</div>

<div class="cert-message">
نشكرك على مساهمتك في دعم المبادرات البيئية والمشاركة في بناء مجتمع أكثر وعيًا واستدامة.
</div>
</div>

<div class="cert-footer">
<div class="signature">
<div class="signature-line"></div>
<span>إدارة منصة نفل</span>
</div>

<div class="cert-badge">
🌿 مشاركة بيئية معتمدة
</div>
</div>

<div class="cert-actions">
<button class="cert-btn print" onclick="window.print()">طباعة الشهادة</button>
<button class="cert-btn close" onclick="window.close()">إغلاق</button>
</div>
</div>
</div>
</body>
</html>
`);

certificateWindow.document.close();
}

/* =========================
TOAST
========================= */
function showToast(message, type = "success") {
const toast = document.getElementById("toast");
if (!toast) return;

toast.textContent = message;
toast.className = `toast show ${type}`;

setTimeout(() => {
toast.className = "toast";
}, 2500);
}

/* =========================
TABS
========================= */
function setupTabs() {
document.querySelectorAll(".tab-btn").forEach(button => {
button.addEventListener("click", () => {
document.querySelectorAll(".tab-btn").forEach(btn => btn.classList.remove("active"));
button.classList.add("active");
currentTab = button.dataset.tab;
renderEvents();
});
});
}

/* =========================
INIT
========================= */
document.addEventListener("DOMContentLoaded", async () => {
setupTabs();
await autoRegisterFromQuery();
fetchEvents();
});
