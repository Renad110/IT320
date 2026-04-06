const STORAGE_KEY = "myEvents";
const CURRENT_TAB_KEY = "myEventsTab";

const USER_NAME = "سارة الغامدي";
const DEMO_MODE = true;
const DEMO_TODAY_EVENT_ID = 1;

/* أكواد الحضور الثابتة في هالفيز */
const EVENT_CODES = {
1: "9247"
};

const allEvents = [
{
id: 1,
title: 'حملة تنظيف منتزه الملك عبدالله',
category: 'تنظيف',
date: '2026-04-15',
location: 'الرياض، منتزه الملك عبدالله',
participants: 45,
points: 10,
image: 'https://images.unsplash.com/photo-1618477461853-cf6ed80faba5?w=900&q=80',
description: 'انضم إلينا في حملة تنظيف شاملة لمنتزه الملك عبدالله. سنقوم بجمع النفايات وتصنيفها وإعادة تدويرها. جميع الأدوات والمعدات سيتم توفيرها.'
},
{
id: 2,
title: 'ورشة زراعة الأشجار في وادي حنيفة',
category: 'زراعة',
date: '2026-04-20',
location: 'الرياض، وادي حنيفة',
participants: 32,
points: 15,
image: 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?w=900&q=80',
description: 'شارك في زراعة 100 شجرة محلية في وادي حنيفة. سنتعلم كيفية زراعة الأشجار والعناية بها بشكل صحيح.'
},
{
id: 3,
title: 'ورشة إعادة تدوير المخلفات',
category: 'إعادة تدوير',
date: '2026-04-25',
location: 'الرياض، مركز الملك عبدالعزيز الثقافي',
participants: 28,
points: 12,
image: 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=900&q=80',
description: 'تعلم كيفية إعادة تدوير المواد المختلفة وصنع منتجات مفيدة من المخلفات. ورشة عملية تفاعلية.'
},
{
id: 4,
title: 'حملة توعية بالطاقة المتجددة',
category: 'توعية',
date: '2026-04-28',
location: 'الرياض، جامعة الملك سعود',
participants: 60,
points: 8,
image: 'https://images.unsplash.com/photo-1509391366360-2e959784a276?w=900&q=80',
description: 'محاضرات وورش عمل حول الطاقة الشمسية والطاقة المتجددة وكيفية تطبيقها في المنازل.'
},
{
id: 5,
title: 'يوم نظافة حي السفارات',
category: 'تنظيف',
date: '2026-05-05',
location: 'الرياض، حي السفارات',
participants: 38,
points: 10,
image: 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=900&q=80',
description: 'مبادرة مجتمعية لتنظيف وتجميل حي السفارات بمشاركة السكان والمتطوعين.'
},
{
id: 6,
title: 'معرض الاستدامة البيئية',
category: 'معرض',
date: '2026-05-10',
location: 'الرياض، مركز المعارض',
participants: 120,
points: 5,
image: 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=900&q=80',
description: 'معرض شامل للحلول البيئية المستدامة والمنتجات الصديقة للبيئة مع ورش عمل متنوعة.'
}
];

let currentTab = localStorage.getItem(CURRENT_TAB_KEY) || "upcoming";

/* =========================
STORAGE
========================= */
function getStoredMyEvents() {
return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
}

function saveMyEvents(events) {
localStorage.setItem(STORAGE_KEY, JSON.stringify(events));
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
DATE HELPERS
========================= */
function isPast(dateStr) {
const today = new Date();
const eventDate = new Date(dateStr);

today.setHours(0, 0, 0, 0);
eventDate.setHours(0, 0, 0, 0);

return eventDate < today;
}

function isToday(dateStr) {
const today = new Date();
const eventDate = new Date(dateStr);

today.setHours(0, 0, 0, 0);
eventDate.setHours(0, 0, 0, 0);

return eventDate.getTime() === today.getTime();
}

function isEventActiveToday(event) {
if (DEMO_MODE && event.id === DEMO_TODAY_EVENT_ID) {
return true;
}
return isToday(event.date);
}

/* =========================
AUTO REGISTER FROM QUERY
========================= */
function autoRegisterFromQuery() {
const params = new URLSearchParams(window.location.search);
const eventId = parseInt(params.get("id"));

if (!eventId) return;

const selectedEvent = allEvents.find(event => event.id === eventId);
if (!selectedEvent) return;

const myEvents = getStoredMyEvents();
const alreadyExists = myEvents.find(event => event.id === eventId);

if (!alreadyExists) {
myEvents.push({
...selectedEvent,
attended: false
});
saveMyEvents(myEvents);
showToast("تم التسجيل في الفعالية بنجاح", "success");
}

window.history.replaceState({}, document.title, "myEvent.html");
}

/* =========================
STATIC CODE
========================= */
function getGeneratedCodeForEvent(eventId) {
return EVENT_CODES[eventId] || null;
}

/* =========================
STATUS / TABS
========================= */
function getStatusLabel(event) {
if (event.attended) return "مكتملة";
if (isPast(event.date) && !isEventActiveToday(event)) return "سابقة";
return "قادمة";
}

function getFilteredEvents(events) {
if (currentTab === "completed") {
return events.filter(event => event.attended);
}

if (currentTab === "past") {
return events.filter(event => isPast(event.date) && !event.attended && !isEventActiveToday(event));
}

return events.filter(event => (!isPast(event.date) || isEventActiveToday(event)) && !event.attended);
}

/* =========================
CERTIFICATE
========================= */
function getCertificateHTML(event) {
return `
<div class="certificate">
<div class="cert-border">
<div class="cert-top-pattern"></div>

<img src="logo.jpg" class="cert-logo" alt="نفل"/>

<p class="cert-small">منصة الفعاليات البيئية</p>

<h2 class="cert-title">شهادة مشاركة</h2>

<p class="cert-text">يسر منصة نفل البيئية أن تشهد بأن</p>

<h1 class="cert-name">${USER_NAME}</h1>

<p class="cert-text">قد شاركت في فعالية</p>

<h3 class="cert-event">${event.title}</h3>

<p class="cert-date">بتاريخ ${event.date}</p>

<div class="cert-divider"></div>

<div class="cert-footer-row">
<div class="signature">
<div class="line"></div>
<span>إدارة منصة نفل</span>
</div>

<div class="cert-badge">
🌿 مشاركة بيئية معتمدة
</div>
</div>

<button onclick="printCertificate(this)" class="btn-print">
طباعة الشهادة
</button>
</div>
</div>
`;
}

function printCertificate(btn) {
const content = btn.closest(".certificate").innerHTML;
const win = window.open("", "", "width=1000,height=800");

win.document.write(`
<html dir="rtl" lang="ar">
<head>
<title>شهادة مشاركة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<style>
body {
font-family: 'Tajawal', sans-serif;
background: #ffffff;
padding: 30px;
text-align: center;
}

.certificate {
display: flex;
justify-content: center;
}

.cert-border {
position: relative;
background: linear-gradient(180deg, #ffffff 0%, #f8fcf9 100%);
border: 4px solid #b8d8bf;
border-radius: 24px;
padding: 2.6rem 2rem 2rem;
width: 100%;
max-width: 650px;
text-align: center;
overflow: hidden;
}

.cert-top-pattern {
position: absolute;
top: 0;
right: 0;
left: 0;
height: 14px;
background: linear-gradient(90deg, #2d6a35, #7db88a, #2d6a35);
}

.cert-logo {
height: 90px;
margin-bottom: .8rem;
}

.cert-small {
color: #6b7280;
font-size: .88rem;
margin-bottom: .5rem;
}

.cert-title {
color: #1e4d25;
font-size: 2rem;
font-weight: 800;
margin-bottom: .8rem;
}

.cert-text {
color: #5f6f67;
margin-bottom: .45rem;
font-size: 1rem;
}

.cert-name {
font-size: 2.2rem;
color: #245331;
font-weight: 800;
margin: .8rem 0;
display: inline-block;
padding: 0 .8rem .2rem;
border-bottom: 3px solid #d8b44c;
}

.cert-event {
font-size: 1.25rem;
margin: .5rem 0 .7rem;
color: #1f2937;
font-weight: 700;
}

.cert-date {
color: #7c8a83;
margin-bottom: 1.2rem;
font-size: .95rem;
}

.cert-divider {
width: 100%;
height: 1px;
background: linear-gradient(90deg, transparent, #d9e8dc, transparent);
margin: 1.4rem 0 1rem;
}

.cert-footer-row {
display: flex;
justify-content: space-between;
align-items: end;
gap: 1rem;
flex-wrap: wrap;
}

.signature {
text-align: center;
}

.signature .line {
width: 160px;
height: 2px;
background: #2d6a35;
margin: 10px auto;
}

.signature span {
font-size: .9rem;
color: #374151;
}

.cert-badge {
background: #eef8f1;
color: #245331;
border: 1px solid #cfe5d4;
padding: .6rem .9rem;
border-radius: 999px;
font-size: .85rem;
font-weight: 700;
}

.btn-print {
display: none !important;
}
</style>
</head>
<body>${content}</body>
</html>
`);

win.document.close();
win.focus();
win.print();
}

/* =========================
RENDER ACTIONS
========================= */
function renderActions(event) {
if (event.attended) {
return `
<div class="actions">
<button class="btn btn-soft" onclick="toggleCertificate(${event.id})">عرض الشهادة</button>
<div class="certificate-box" id="certificate-${event.id}" style="display:none;">
${getCertificateHTML(event)}
</div>
</div>
`;
}

const attendanceCode = getGeneratedCodeForEvent(event.id);

/* قبل الفعالية */
if (!isEventActiveToday(event) && !isPast(event.date)) {
return `
<div class="code-box">
<h4>الحضور</h4>
<p>لم تبدأ الفعالية بعد</p>
</div>
<div class="actions">
<button class="btn btn-danger" onclick="cancelRegistration(${event.id})">إلغاء التسجيل</button>
</div>
`;
}

/* يوم الفعالية */
if (isEventActiveToday(event)) {
if (!attendanceCode) {
return `
<div class="code-box">
<h4>الحضور</h4>
<p>لم يتم تفعيل كود الحضور بعد</p>
</div>
<div class="actions">
<button class="btn btn-danger" onclick="cancelRegistration(${event.id})">إلغاء التسجيل</button>
</div>
`;
}

return `
<div class="code-box">
<h4>تأكيد الحضور</h4>
<input type="text" id="code-${event.id}" class="code-input" placeholder="أدخل كود الحضور" />
<button class="btn btn-primary" onclick="confirmAttendance(${event.id})">تأكيد الحضور</button>
</div>
<div class="actions">
<button class="btn btn-danger" onclick="cancelRegistration(${event.id})">إلغاء التسجيل</button>
</div>
`;
}

/* بعد الفعالية */
return `
<div class="code-box">
<h4>الحضور</h4>
<p style="color:#dc2626;">انتهت الفعالية</p>
</div>
`;
}

/* =========================
RENDER EVENTS
========================= */
function renderEvents() {
const eventsGrid = document.getElementById("eventsGrid");
const emptyState = document.getElementById("emptyState");

const myEvents = getStoredMyEvents();
const filteredEvents = getFilteredEvents(myEvents);

eventsGrid.innerHTML = "";

if (!filteredEvents.length) {
emptyState.style.display = "block";
return;
}

emptyState.style.display = "none";

filteredEvents.forEach(event => {
const card = document.createElement("div");
card.className = "event-card";

card.innerHTML = `
<img class="event-img" src="${event.image}" alt="${event.title}" />
<div class="event-body">
<div class="event-meta">
<span class="status-tag">${getStatusLabel(event)}</span>
<span class="pts-badge">${event.points} نقطة</span>
</div>

<div class="event-title">${event.title}</div>

<div class="event-details">
<div>📅 ${event.date}</div>
<div>📍 ${event.location}</div>
<div>👥 ${event.participants} مشارك</div>
</div>

${renderActions(event)}
</div>
`;

eventsGrid.appendChild(card);
});
}

/* =========================
USER ACTIONS
========================= */
function cancelRegistration(eventId) {
const myEvents = getStoredMyEvents();
const target = myEvents.find(event => event.id === eventId);

if (!target) return;

if (target.attended) {
showToast("لا يمكن إلغاء التسجيل بعد تأكيد الحضور", "error");
return;
}

const updated = myEvents.filter(event => event.id !== eventId);
saveMyEvents(updated);
renderEvents();
showToast("تم إلغاء التسجيل", "success");
}

function confirmAttendance(eventId) {
const input = document.getElementById(`code-${eventId}`);
if (!input) return;

const enteredCode = input.value.trim();
const correctCode = getGeneratedCodeForEvent(eventId);

if (!correctCode) {
showToast("كود الحضور غير متاح حالياً", "error");
return;
}

if (enteredCode !== String(correctCode)) {
showToast("كود الحضور غير صحيح", "error");
return;
}

const myEvents = getStoredMyEvents();
const eventIndex = myEvents.findIndex(event => event.id === eventId);

if (eventIndex === -1) return;

myEvents[eventIndex].attended = true;
saveMyEvents(myEvents);

renderEvents();
showToast("تم تأكيد الحضور بنجاح", "success");
}

function toggleCertificate(eventId) {
const certificateBox = document.getElementById(`certificate-${eventId}`);
if (!certificateBox) return;

certificateBox.style.display =
certificateBox.style.display === "none" ? "block" : "none";
}

/* =========================
TABS
========================= */
function setupTabs() {
const tabButtons = document.querySelectorAll(".tab-btn");

tabButtons.forEach(button => {
if (button.dataset.tab === currentTab) {
button.classList.add("active");
} else {
button.classList.remove("active");
}

button.addEventListener("click", () => {
tabButtons.forEach(btn => btn.classList.remove("active"));
button.classList.add("active");
currentTab = button.dataset.tab;
localStorage.setItem(CURRENT_TAB_KEY, currentTab);
renderEvents();
});
});
}

/* =========================
INIT
========================= */
document.addEventListener("DOMContentLoaded", () => {
autoRegisterFromQuery();
setupTabs();
renderEvents();
});
