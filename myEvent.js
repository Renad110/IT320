let eventsData = [];

let userName = "";

let currentTab = "upcoming";



/* ================= FETCH ================= */

async function fetchEvents(){

  const res = await fetch("myEvents.php?action=get");

  const data = await res.json();



  eventsData = data.events;

  userName = data.user;



  document.querySelector(".user-name").innerText = userName;



  renderEvents();

}



/* ================= STATUS ================= */

function getStatus(e){

  const today = new Date();

  const d = new Date(e.event_date);



  today.setHours(0,0,0,0);

  d.setHours(0,0,0,0);



  if(e.attendance_status === "Attended") return "completed";

  if(d < today) return "past";

  if(d.getTime() === today.getTime()) return "today";



  return "upcoming";

}



/* ================= FILTER ================= */

function filterEvents(){

  return eventsData.filter(e=>{

    let s = getStatus(e);

    if(currentTab=="completed") return s=="completed";

    if(currentTab=="past") return s=="past";

    return s=="upcoming" || s=="today";

  });

}



/* ================= RENDER ================= */

function renderEvents(){



  const grid = document.getElementById("eventsGrid");

  const empty = document.getElementById("emptyState");



  let list = filterEvents();



  grid.innerHTML = "";



  if(list.length==0){

    empty.style.display="block";

    return;

  }



  empty.style.display="none";



  list.forEach(e=>{



    let status = getStatus(e);

    let actions = "";



    /* مكتملة */

    if(status=="completed"){

      if(e.certificate_id){

        actions = `<button onclick="openCert('${e.title}','${e.event_date}')">عرض الشهادة</button>`;

      }else{

        actions = `<p>لا توجد شهادة</p>`;

      }

    }



    /* اليوم */

    else if(status=="today"){



      if(!e.code_active){

        actions = `

          <div class="code-waiting">

            <div class="pulse"></div>

            <p>بانتظار تفعيل الكود...</p>

          </div>

        `;

      }else{

        actions = `

          <input id="code-${e.event_id}" oninput="enableBtn(${e.event_id})" placeholder="ادخل الكود">

          <button id="btn-${e.event_id}" disabled onclick="confirmAttendance(${e.event_id})">تأكيد</button>

        `;

      }

    }



    else if(status=="upcoming"){

      actions = `<p>لم تبدأ</p>`;

    }



    else{

      actions = `<p style="color:red">انتهت</p>`;

    }



    grid.innerHTML += `

      <div class="event-card">

        <img src="${e.image_path}">

        <h3>${e.title}</h3>

        <p>${e.location}</p>

        <p>${e.event_date}</p>

        <p>${e.points} نقطة</p>

        ${actions}

      </div>

    `;

  });

}



/* ================= ENABLE BUTTON ================= */

function enableBtn(id){

  const input = document.getElementById(`code-${id}`);

  const btn = document.getElementById(`btn-${id}`);



  btn.disabled = input.value.trim()==="";

}



/* ================= CONFIRM ================= */

async function confirmAttendance(id){



  const input = document.getElementById(`code-${id}`);

  const btn = document.getElementById(`btn-${id}`);



  btn.innerText = "جاري التحقق...";

  btn.disabled = true;



  const res = await fetch("myEvents.php?action=confirm",{

    method:"POST",

    headers:{"Content-Type":"application/x-www-form-urlencoded"},

    body:`event_id=${id}&code=${input.value}`

  });



  const data = await res.json();



  if(data.status=="wrong"){

    showToast("❌ الكود غلط","error");

    btn.innerText = "تأكيد";

    btn.disabled = false;

  }else{

    showToast("✅ تم الحضور");

    fetchEvents();

  }

}



/* ================= CERT ================= */

function openCert(title,date){



  const win = window.open();



  win.document.write(`

  <html dir="rtl">

  <body style="text-align:center;font-family:Tajawal">

    <h1>شهادة مشاركة</h1>

    <h2>${userName}</h2>

    <p>شاركت في</p>

    <h3>${title}</h3>

    <p>${date}</p>

  </body>

  </html>

  `);

}



/* ================= TOAST ================= */

function showToast(msg,type="success"){

  const t = document.getElementById("toast");

  t.innerText = msg;

  t.className = "toast show "+type;



  setTimeout(()=>t.className="toast",2500);

}



/* ================= TABS ================= */

document.querySelectorAll(".tab-btn").forEach(btn=>{

  btn.onclick=()=>{

    document.querySelectorAll(".tab-btn").forEach(b=>b.classList.remove("active"));

    btn.classList.add("active");

    currentTab = btn.dataset.tab;

    renderEvents();

  }

});



/* ================= INIT ================= */

fetchEvents();
