<?php
require_once '../../includes/config.php';
include '../../partials/header.php';
$ver = '10.98.322';
if (is_file(__DIR__ . '/../../VERSION.txt')) {
    $ver = trim((string) file_get_contents(__DIR__ . '/../../VERSION.txt')) ?: $ver;
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
.wk{max-width:1100px;margin:0 auto 3rem;padding:12px;font-family:system-ui,sans-serif}
.wk nav{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0 16px}
.wk nav button{border:0;border-radius:10px;padding:8px 12px;background:#e8efe8;font-weight:600}
.wk nav button.on{background:#1f6b4a;color:#fff}
.wk h1{margin:0 0 4px;font-size:1.35rem;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.wk h1 a{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:#0369a1;color:#fff;text-decoration:none;font-size:20px}
.wk .muted{color:#667;font-size:.9rem}
.wk .row{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}
.wk input, .wk textarea, .wk select{border:1px solid #c9d2c9;border-radius:8px;padding:8px;min-width:0}
.wk table{width:100%;border-collapse:collapse;font-size:.92rem}
.wk th,.wk td{border-bottom:1px solid #e4e4e4;padding:8px;text-align:left}
.wk .card{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:12px 0}
.wk .stat{background:#ffffff;border:1px solid #d7dee8;border-radius:12px;padding:12px;color:#111827}
.wk .stat b{display:block;font-size:1.2rem;color:#003F8F}
.wk table thead th{background:#003F8F;color:#fff;border-bottom:0}
.wk td{color:#111827}
.wk .danger{background:#b42318;color:#fff;border:0;border-radius:8px;padding:6px 10px}
.wk .ok{background:#1f6b4a;color:#fff;border:0;border-radius:8px;padding:8px 12px;font-weight:700}
.wk .editor input{width:100%;min-width:5rem}
@media(max-width:700px){.wk .card{grid-template-columns:1fr}.wk .editor{display:block}}
</style>
<div class="wk">
  <h1><a class="ame-purchase-home-inline" href="/index.php" title="হোমে যান" aria-label="হোমে যান">🏠</a> ওয়ার্কশপ খাতা <small class="muted">v<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></small></h1>
  <p class="muted">গাড়ির কাজ, টাকা ভাগ, দৈনিক রিপোর্ট, পণ্য ক্রয়</p>
  <nav>
    <button type="button" data-tab="jobs" class="on">কাজ</button>
    <button type="button" data-tab="report">রিপোর্ট</button>
    <button type="button" data-tab="purchase">পণ্য ক্রয়</button>
    <button type="button" data-tab="workers">কর্মী</button>
  </nav>
  <div class="row">
    <button type="button" id="prevDay">←</button>
    <input type="date" id="theDate">
    <button type="button" id="nextDay">→</button>
  </div>
  <div id="view"></div>
</div>
<script>
const KEY="workshop-khata-v1";
const empty={workers:[],jobs:[],products:[],purchases:[]};
function load(){try{const x=JSON.parse(localStorage.getItem(KEY)||"null");return x&&Array.isArray(x.workers)?x:empty}catch{return empty}}
function save(d){localStorage.setItem(KEY,JSON.stringify(d));render()}
function uid(){return crypto.randomUUID?crypto.randomUUID():String(Date.now())+Math.random()}
function today(){return new Date().toISOString().slice(0,10)}
function taka(n){return (Number(n)||0).toLocaleString("bn-BD")+" টাকা"}
function shift(iso,d){const x=new Date(iso+"T12:00:00");x.setDate(x.getDate()+d);return x.toISOString().slice(0,10)}
function equalShares(ids,amount){if(!ids.length)return[];const a=Math.round(amount);const b=Math.floor(a/ids.length);const r=a-b*ids.length;return ids.map((id,i)=>({workerId:id,amount:b+(i<r?1:0)}))}
let data=load();
let date=today();
let tab="jobs";
let editingId=null;
document.getElementById("theDate").value=date;
document.getElementById("theDate").onchange=e=>{date=e.target.value;render()};
document.getElementById("prevDay").onclick=()=>{date=shift(date,-1);document.getElementById("theDate").value=date;render()};
document.getElementById("nextDay").onclick=()=>{date=shift(date,1);document.getElementById("theDate").value=date;render()};
document.querySelectorAll("[data-tab]").forEach(b=>b.onclick=()=>{tab=b.dataset.tab;document.querySelectorAll("[data-tab]").forEach(x=>x.classList.toggle("on",x===b));render()});
function jobsOf(){return data.jobs.filter(j=>j.date===date).sort((a,b)=>a.createdAt-b.createdAt)}
function purchasesOf(){return data.purchases.filter(p=>p.date===date).sort((a,b)=>a.createdAt-b.createdAt)}
function liveWorkers(){return (data.workers||[]).filter(w=>!w.archivedAt&&!w.purgedAt)}
function archivedWorkers(){return (data.workers||[]).filter(w=>w.archivedAt&&!w.purgedAt)}
function workerName(id){const w=(data.workers||[]).find(x=>x.id===id);return w?w.name:"—"}
function render(){
  const v=document.getElementById("view");
  if(tab==="workers"){
    const live=liveWorkers();
    const archived=archivedWorkers();
    v.innerHTML=`<div class="row"><input id="wn" placeholder="নাম"><input id="wc" placeholder="কোড"><input id="wp" placeholder="ফোন"><button class="ok" id="wa">যোগ</button></div>
    <p class="muted">সক্রিয় তালিকা। সরালে আর্কাইভে যায়। চিরতরে মুছলে দুই তালিকা থেকেই উঠে — কোড মুছে যায় না।</p>
    <table><thead><tr><th>নাম</th><th>কোড</th><th>ফোন</th><th></th></tr></thead><tbody>${live.map(w=>`<tr><td>${w.name}</td><td>${w.code||"—"}</td><td>${w.phone||""}</td><td><button class="danger" data-del="${w.id}">প্রজেক্ট থেকে সরান</button></td></tr>`).join("")||"<tr><td colspan=4>সক্রিয় কর্মী নেই</td></tr>"}</tbody></table>
    <h3>সরানো কর্মী আর্কাইভ</h3>
    <table><thead><tr><th>নাম</th><th>কোড</th><th></th></tr></thead><tbody>${archived.map(w=>`<tr><td>${w.name}</td><td>${w.code||"—"}</td><td><button class="ok" data-rs="${w.id}">ফেরত আনুন</button> <button class="danger" data-pg="${w.id}">চিরতরে মুছুন</button></td></tr>`).join("")||"<tr><td colspan=3>আর্কাইভ খালি</td></tr>"}</tbody></table>`;
    document.getElementById("wa").onclick=()=>{const name=document.getElementById("wn").value.trim();if(!name)return;data.workers.push({id:uid(),name,code:document.getElementById("wc").value.trim(),phone:document.getElementById("wp").value.trim(),archivedAt:null,purgedAt:null});save(data)};
    v.querySelectorAll("[data-del]").forEach(b=>b.onclick=()=>{data.workers=data.workers.map(w=>w.id===b.dataset.del?{...w,archivedAt:Date.now()}:w);save(data)});
    v.querySelectorAll("[data-rs]").forEach(b=>b.onclick=()=>{data.workers=data.workers.map(w=>w.id===b.dataset.rs&&!w.purgedAt?{...w,archivedAt:null}:w);save(data)});
    v.querySelectorAll("[data-pg]").forEach(b=>b.onclick=()=>{if(!confirm("চিরতরে প্রজেক্ট থেকে মুছে যাবে। কোড থাকবে। এগোবেন?"))return;if(!confirm("নিশ্চিত? ফেরত আনা যাবে না।"))return;data.workers=data.workers.map(w=>w.id===b.dataset.pg?{...w,archivedAt:w.archivedAt||Date.now(),purgedAt:Date.now()}:w);save(data)});
    return;
  }
  if(tab==="jobs"){
    const jobs=jobsOf();
    const total=jobs.reduce((s,j)=>s+Number(j.amount||0),0);
    const cars=[...new Set(jobs.map(j=>j.carNumber).filter(Boolean))];
    const totals={};
    jobs.forEach(j=>j.shares.forEach(s=>{totals[s.workerId]=(totals[s.workerId]||0)+Number(s.amount||0)}));
    v.innerHTML=`<div class="card"><div class="stat">কাজ<b>${jobs.length.toLocaleString("bn-BD")}</b></div><div class="stat">মোট<b>${taka(total)}</b></div><div class="stat">গাড়ি<b>${cars.length.toLocaleString("bn-BD")}</b></div></div>
    <p class="muted">${cars.length?("গাড়ির নাম্বার: "+cars.join(" · ")):""}</p>
    ${Object.keys(totals).map(id=>`<div class="stat">${workerName(id)}<b>${taka(totals[id])}</b></div>`).join("")}
    <h3>নতুন কাজ</h3>
    <div class="row"><input id="car" placeholder="গাড়ির নাম্বার"><input id="work" placeholder="কাজের বিবরণ"><input id="amt" type="number" placeholder="টাকা"></div>
    <div>${liveWorkers().map(w=>`<label><input type="checkbox" class="jw" value="${w.id}"> ${w.name}</label> `).join("")||"<p class='muted'>আগে কর্মী যোগ করুন</p>"}</div>
    <p><button class="ok" id="addJob">কাজ যোগ করুন</button></p>
    <table><thead><tr><th>গাড়ি</th><th>কাজ</th><th>টাকা</th><th>ভাগ</th><th></th></tr></thead><tbody>
    ${jobs.map(j=>`<tr><td>${j.carNumber}</td><td>${j.work}</td><td>${taka(j.amount)}</td><td>${j.shares.map(s=>workerName(s.workerId)+": "+taka(s.amount)).join("<br>")}</td><td><button class="danger" data-dj="${j.id}">মুছুন</button></td></tr>`).join("")||"<tr><td colspan=5>এই তারিখে কাজ নেই</td></tr>"}
    </tbody></table>`;
    document.getElementById("addJob").onclick=()=>{
      const ids=[...v.querySelectorAll(".jw:checked")].map(x=>x.value);
      const amount=Math.round(Number(document.getElementById("amt").value)||0);
      const car=document.getElementById("car").value.trim();
      const work=document.getElementById("work").value.trim();
      if(!car||!work||amount<=0||!ids.length){alert("গাড়ি, কাজ, টাকা ও কর্মী দিন");return}
      data.jobs.push({id:uid(),date,carNumber:car,work,amount,shares:equalShares(ids,amount),createdAt:Date.now()});
      save(data);
    };
    v.querySelectorAll("[data-dj]").forEach(b=>b.onclick=()=>{data.jobs=data.jobs.filter(j=>j.id!==b.dataset.dj);save(data)});
    return;
  }
  if(tab==="report"){
    const jobs=jobsOf();
    const cars=[...new Set(jobs.map(j=>j.carNumber).filter(Boolean))];
    const totals={};
    jobs.forEach(j=>j.shares.forEach(s=>{totals[s.workerId]=(totals[s.workerId]||0)+Number(s.amount||0)}));
    v.innerHTML=`<p class="muted">যে গাড়ির কাজ হয়েছে তার নাম্বার রিপোর্টে থাকবে।</p>
    <p><b>গাড়ি:</b> ${cars.join(" · ")||"—"}</p>
    <table><thead><tr><th>কর্মী</th><th>মোট</th></tr></thead><tbody>
    ${data.workers.filter(w=>!w.purgedAt||totals[w.id]).map(w=>`<tr><td>${w.name}</td><td>${taka(totals[w.id]||0)}</td></tr>`).join("")}
    </tbody></table>
    <table><thead><tr><th>গাড়ি</th><th>কাজ</th><th>টাকা</th></tr></thead><tbody>
    ${jobs.map(j=>`<tr><td>${j.carNumber}</td><td>${j.work}</td><td>${taka(j.amount)}</td></tr>`).join("")||"<tr><td colspan=3>নেই</td></tr>"}
    </tbody></table>
    <p><button class="ok" onclick="window.print()">প্রিন্ট</button></p>`;
    return;
  }
  const lines=purchasesOf();
  const total=lines.reduce((s,l)=>s+Number(l.amount||0),0);
  v.innerHTML=`<div class="card"><div class="stat">কার্টে পণ্য<b>${lines.length.toLocaleString("bn-BD")}</b></div><div class="stat">ক্রয় মোট<b>${taka(total)}</b></div></div>
  <p class="muted">এডিট বা ড্রাফটে থাকলেও «কার্টে যোগ করুন» নতুন পণ্য যোগ করে। পুরনো সারি আলাদা আপডেট।</p>
  <p><a class="ok" href="/index.php" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;width:auto;padding:8px 12px">🏠 হোমে যান</a></p>
  <table class="editor"><thead><tr><th>নাম</th><th>কোড</th><th>পরিমাণ</th><th>ক্রয়</th><th>পাইকারি</th><th>ডিলার</th><th>খুচরা</th><th></th></tr></thead>
  <tbody><tr>
    <td><input id="pn" placeholder="পণ্যের নাম"></td>
    <td><input id="pc" placeholder="কোড"></td>
    <td><input id="pq" type="number" value="1"></td>
    <td><input id="pr" type="number"></td>
    <td><input id="pw" type="number"></td>
    <td><input id="pd" type="number"></td>
    <td><input id="pt" type="number"></td>
    <td><button class="ok" id="addLine">কার্টে যোগ করুন</button>${editingId?'<button type="button" class="ok" id="updLine" style="background:#003F8F;margin-top:4px">এই সারি আপডেট</button><button type="button" id="newLine">নতুন পণ্য</button>':""}</td>
  </tr></tbody>
  <tbody>${lines.map(l=>`<tr${l.id===editingId?' style="background:#e8f1fb;color:#111827"':""}><td>${(l.names&&l.names.bn)||""}</td><td>${l.code}</td><td>${l.qty}</td><td>${l.rates.purchase}</td><td>${l.rates.wholesale}</td><td>${l.rates.dealer}</td><td>${l.rates.retail}</td><td><button data-el="${l.id}">এডিট</button> <button class="danger" data-pl="${l.id}">বাদ</button></td></tr>`).join("")}</tbody></table>
  <div id="end"></div>`;
  const readEditor=()=>{
    const names={bn:document.getElementById("pn").value.trim()};
    const code=document.getElementById("pc").value.trim();
    const qty=Math.max(1,Math.round(Number(document.getElementById("pq").value)||1));
    const rates={purchase:Math.round(Number(document.getElementById("pr").value)||0),wholesale:Math.round(Number(document.getElementById("pw").value)||0),dealer:Math.round(Number(document.getElementById("pd").value)||0),retail:Math.round(Number(document.getElementById("pt").value)||0)};
    return {names,code,qty,rates};
  };
  const applyProduct=(payload)=>{
    let product=data.products.find(p=>p.code===payload.code);
    if(!product){product={id:uid(),code:payload.code,names:payload.names,rates:payload.rates};data.products.push(product)}
    else {product.names=payload.names;product.rates=payload.rates}
    return product;
  };
  const clearEditor=()=>{editingId=null;["pn","pc","pr","pw","pd","pt"].forEach(id=>{const el=document.getElementById(id);if(el)el.value=""});const q=document.getElementById("pq");if(q)q.value="1"};
  document.getElementById("addLine").onclick=()=>{
    const payload=readEditor();
    if(!payload.names.bn||!payload.code||!payload.rates.purchase){alert("নাম, কোড ও ক্রয় রেট দিন");return}
    const product=applyProduct(payload);
    data.purchases.push({id:uid(),date,productId:product.id,code:payload.code,names:payload.names,qty:payload.qty,rates:payload.rates,amount:payload.qty*payload.rates.purchase,createdAt:Date.now()});
    clearEditor();
    save(data);
    document.getElementById("end").scrollIntoView({behavior:"smooth"});
  };
  const ul=document.getElementById("updLine");
  if(ul) ul.onclick=()=>{
    const payload=readEditor();
    if(!payload.names.bn||!payload.code||!payload.rates.purchase){alert("নাম, কোড ও ক্রয় রেট দিন");return}
    if(!editingId)return;
    const product=applyProduct(payload);
    data.purchases=data.purchases.map(x=>x.id===editingId?{...x,productId:product.id,code:payload.code,names:payload.names,qty:payload.qty,rates:payload.rates,amount:payload.qty*payload.rates.purchase}:x);
    clearEditor();
    save(data);
  };
  const nl=document.getElementById("newLine");
  if(nl) nl.onclick=()=>{clearEditor();render()};
  v.querySelectorAll("[data-pl]").forEach(b=>b.onclick=()=>{data.purchases=data.purchases.filter(x=>x.id!==b.dataset.pl);if(editingId===b.dataset.pl)editingId=null;save(data)});
  v.querySelectorAll("[data-el]").forEach(b=>b.onclick=()=>{
    const l=data.purchases.find(x=>x.id===b.dataset.el); if(!l)return;
    editingId=l.id;
    document.getElementById("pn").value=(l.names&&l.names.bn)||"";
    document.getElementById("pc").value=l.code;
    document.getElementById("pq").value=l.qty;
    document.getElementById("pr").value=l.rates.purchase;
    document.getElementById("pw").value=l.rates.wholesale;
    document.getElementById("pd").value=l.rates.dealer;
    document.getElementById("pt").value=l.rates.retail;
    window.scrollTo({top:0,behavior:"smooth"});
    render();
    document.getElementById("pn").value=(l.names&&l.names.bn)||"";
    document.getElementById("pc").value=l.code;
    document.getElementById("pq").value=l.qty;
    document.getElementById("pr").value=l.rates.purchase;
    document.getElementById("pw").value=l.rates.wholesale;
    document.getElementById("pd").value=l.rates.dealer;
    document.getElementById("pt").value=l.rates.retail;
  });
}
render();
</script>
<?php if (is_file(__DIR__ . '/../../partials/footer.php')) { include '../../partials/footer.php'; }
