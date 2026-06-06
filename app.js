// --- 系統設定與狀態資料庫 ---
const PASS = "M0282";
let currentRole = "viewer"; // 全域權限狀態: admin / viewer

let db = { 
    powder: {}, 
    inventory: {}, 
    purchase: [], 
    usage: [], 
    waste: [], 
    p7_reset_time: null, 
    hide_status: { p1: true, p2: true } 
};

// 編輯索引暫存器
let p1_editing_id = null; 
let p3_edit_idx = -1; 
let p4_edit_idx = -1; 
let p7_edit_idx = -1;

// --- 獨立模組化資料庫序列化存取區 ---
function saveDB_Powder() { localStorage.setItem("db_paint_powder", JSON.stringify(db.powder)); }
function saveDB_Inventory() { localStorage.setItem("db_paint_inventory", JSON.stringify(db.inventory)); }
function saveDB_Purchase() { localStorage.setItem("db_paint_purchase", JSON.stringify(db.purchase)); }
function saveDB_Usage() { localStorage.setItem("db_paint_usage", JSON.stringify(db.usage)); }
function saveDB_Waste() { localStorage.setItem("db_paint_waste", JSON.stringify(db.waste)); }
function saveDB_Settings() { localStorage.setItem("db_paint_settings", JSON.stringify({ p7_reset_time: db.p7_reset_time, hide_status: db.hide_status })); }

function saveAllDB() {
    saveDB_Powder(); saveDB_Inventory(); saveDB_Purchase(); saveDB_Usage(); saveDB_Waste(); saveDB_Settings();
}

// --- 初始化載入機制 ---
function initDB() {
    try {
        const lsPowder = localStorage.getItem("db_paint_powder"); if (lsPowder) db.powder = JSON.parse(lsPowder);
        const lsInventory = localStorage.getItem("db_paint_inventory"); if (lsInventory) db.inventory = JSON.parse(lsInventory);
        const lsPurchase = localStorage.getItem("db_paint_purchase"); if (lsPurchase) db.purchase = JSON.parse(lsPurchase);
        const lsUsage = localStorage.getItem("db_paint_usage"); if (lsUsage) db.usage = JSON.parse(lsUsage);
        const lsWaste = localStorage.getItem("db_paint_waste"); if (lsWaste) db.waste = JSON.parse(lsWaste);
        const lsSettings = localStorage.getItem("db_paint_settings"); 
        if (lsSettings) {
            let settings = JSON.parse(lsSettings);
            db.p7_reset_time = settings.p7_reset_time;
            if(settings.hide_status) db.hide_status = settings.hide_status;
        }

        // 舊版兼容重構機制
        const oldLocal = localStorage.getItem("paint_system_db");
        if (oldLocal && !lsPowder && !lsInventory) {
            let oldData = JSON.parse(oldLocal);
            db = Object.assign(db, oldData);
            saveAllDB(); 
            localStorage.removeItem("paint_system_db"); 
        }
    } catch(e) { console.error("資料庫解耦異常", e); }

    if(!db.waste) db.waste = []; 
    if(!db.purchase) db.purchase = []; 
    if(!db.usage) db.usage = [];

    // 自動填寫預設日期
    const today = new Date().toISOString().split('T')[0];
    if(document.getElementById('p3_date')) document.getElementById('p3_date').value = today;
    if(document.getElementById('p4_date')) document.getElementById('p4_date').value = today;
    if(document.getElementById('p7_date')) document.getElementById('p7_date').value = today;
    
    refreshAllDropdowns(); renderP1Table(); renderP2Table(); renderP3Table(); renderP4Table(); renderP7Table();
}

// --- 全域一鍵驗證機制 ---
function loginAsAdmin() {
    const pwInput = document.getElementById("authPassword").value;
    if (pwInput === PASS) {
        currentRole = "admin";
        document.body.classList.add("admin-mode");
        document.body.classList.remove("viewer-mode");
        document.getElementById("globalRoleBadge").innerText = "目前權限：系統管理員 (全功能開啟)";
        enterSystem();
    } else {
        alert("管理密碼錯誤！請重新輸入或以檢視者身分進入。");
    }
}

function loginAsViewer() {
    currentRole = "viewer";
    document.body.classList.add("viewer-mode");
    document.body.classList.remove("admin-mode");
    document.getElementById("globalRoleBadge").innerText = "目前權限：現場檢視者 (唯讀模式)";
    enterSystem();
}

function enterSystem() {
    document.getElementById("authModal").style.display = "none";
    document.getElementById("mainContainer").style.display = "block";
}

// --- 路由樹與頁面切換控制 ---
function goPage(pageId) { 
    document.getElementById("homePage").style.display = "none"; 
    for (let i = 1; i <= 7; i++) {
        const pg = document.getElementById("page" + i);
        if(pg) pg.style.display = (i === pageId) ? "block" : "none"; 
    }
    if(pageId === 5) refreshP5Dates(); 
    if(pageId === 7) calcWasteStats(); 
}

function goHome() { 
    cancelP1Edit(); cancelP3Edit(); cancelP4Edit(); cancelP7Edit(); 
    for (let i = 1; i <= 7; i++) {
        const pg = document.getElementById("page" + i);
        if(pg) pg.style.display = "none";
    }
    document.getElementById("homePage").style.display = "flex"; 
}

function refreshAllDropdowns() {
    const p2_sel = document.getElementById("p2_id"); 
    const p3_sel = document.getElementById("p3_id"); 
    const p4_sel = document.getElementById("p4_id"); 
    const p6_sel = document.getElementById("p6_select");
    
    let idsHtml = '<option value="">請選擇粉號...</option>'; 
    let p6Html = '<option value="">請選擇代號/粉號...</option>';
    
    for (let id in db.powder) { 
        let item = db.powder[id]; 
        let hideClass = !item.active ? " (已下架)" : ""; 
        idsHtml += `<option value="${id}">${id}${hideClass}</option>`; 
        p6Html += `<option value="${id}">${item.code} / ${id}${hideClass}</option>`; 
    }
    if(p2_sel) p2_sel.innerHTML = idsHtml; 
    if(p3_sel) p3_sel.innerHTML = idsHtml; 
    if(p4_sel) p4_sel.innerHTML = idsHtml; 
    if(p6_sel) p6_sel.innerHTML = p6Html;
}

// --- 1. 粉號清冊管理 ---
function addP1Record() {
    const id = document.getElementById("p1_id").value.trim(); 
    const code = document.getElementById("p1_code").value.trim(); 
    const color = document.getElementById("p1_color").value.trim(); 
    const weight = parseInt(document.getElementById("p1_weight").value); 
    const vendor = document.getElementById("p1_vendor").value;
    
    if(!id || !code) return alert("粉號與代號為必填項目！");
    if(p1_editing_id && p1_editing_id !== id) { db.powder[p1_editing_id].active = false; }
    
    db.powder[id] = { code, color, weight, vendor, active: true };
    if (!db.inventory[id]) db.inventory[id] = { loose: 0, box: 0, safe: 100 };
    
    saveDB_Powder(); saveDB_Inventory(); cancelP1Edit(); refreshAllDropdowns(); renderP1Table(); renderP2Table(); 
    alert("資料儲存成功！");
}

function editP1(id) { 
    let item = db.powder[id]; 
    p1_editing_id = id; 
    document.getElementById("p1_id").value = id; 
    document.getElementById("p1_code").value = item.code; 
    document.getElementById("p1_color").value = item.color; 
    document.getElementById("p1_weight").value = item.weight; 
    document.getElementById("p1_vendor").value = item.vendor; 
    document.getElementById("p1_save_btn").innerText = "更新修改紀錄"; 
    document.getElementById("p1_cancel_btn").style.display = "inline-block"; 
}

function cancelP1Edit() { 
    p1_editing_id = null; 
    document.getElementById("p1_id").value = ""; 
    document.getElementById("p1_code").value = ""; 
    document.getElementById("p1_color").value = ""; 
    document.getElementById("p1_save_btn").innerText = "儲存粉號資料"; 
    document.getElementById("p1_cancel_btn").style.display = "none"; 
}

function deleteP1Record() {
    const id = document.getElementById("p1_id").value.trim(); 
    if(!id || !db.powder[id]) return alert("找不到指定粉號！");
    if (confirm(`確定要將粉號 (${id}) 徹底下架清除嗎？`)) { 
        delete db.powder[id]; 
        delete db.inventory[id]; 
        saveDB_Powder(); saveDB_Inventory(); cancelP1Edit(); refreshAllDropdowns(); renderP1Table(); renderP2Table(); 
    }
}

function toggleHideStatus(module) { 
    db.hide_status[module] = !db.hide_status[module]; 
    document.getElementById(`toggle${module.toUpperCase()}HiddenBtn`).innerText = db.hide_status[module] ? "顯示已下架紀錄" : "隱藏已下架紀錄"; 
    saveDB_Settings(); 
    if(module === 'p1') renderP1Table(); 
    if(module === 'p2') renderP2Table(); 
}

function renderP1Table() {
    const tbody = document.getElementById("p1_table").querySelector("tbody"); tbody.innerHTML = "";
    for (let id in db.powder) { 
        let item = db.powder[id]; 
        if (db.hide_status.p1 && !item.active) continue; 
        let tr = document.createElement("tr"); 
        if(!item.active) tr.style.background = "#f1f5f9"; 
        
        let actionTd = currentRole === 'admin' ? `<td><button class="action-btn act-edit" onclick="editP1('${id}')">改</button></td>` : '';
        tr.innerHTML = `<td>${id}</td><td>${item.code}</td><td>${item.weight}kg</td>${actionTd}`; 
        tbody.appendChild(tr); 
    }
}

// --- 2. 庫存監控模組 ---
function linkP2Fields() { 
    const id = document.getElementById("p2_id").value; 
    if(!id || !db.powder[id]) return; 
    document.getElementById("p2_code").value = db.powder[id].code; 
    document.getElementById("p2_weight").value = db.powder[id].weight; 
    const inv = db.inventory[id] || { loose: 0, box: 0, safe: 100 }; 
    document.getElementById("p2_box").value = inv.box; 
    document.getElementById("p2_loose").value = inv.loose; 
    document.getElementById("p2_safe").value = inv.safe; 
    calcP2Total(); 
}

function calcP2Total() { 
    const w = parseFloat(document.getElementById("p2_weight").value) || 0; 
    const b = parseFloat(document.getElementById("p2_box").value) || 0; 
    const l = parseFloat(document.getElementById("p2_loose").value) || 0; 
    document.getElementById("p2_total").value = (w * b + l).toFixed(2); 
}

function saveP2Record() {
    const id = document.getElementById("p2_id").value; if(!id) return alert("請選擇粉號");
    const box = parseFloat(document.getElementById("p2_box").value) || 0; 
    const loose = parseFloat(document.getElementById("p2_loose").value) || 0; 
    const safe = parseFloat(document.getElementById("p2_safe").value) || 0;
    db.inventory[id] = { loose, box, safe }; 
    saveDB_Inventory(); renderP2Table(); alert("盤點寫入成功！");
}

function deleteP2Record(id) { 
    if(confirm(`確定清除 ${id} 的全部庫存數值嗎？`)) { 
        if(db.inventory[id]) { 
            db.inventory[id].box = 0; db.inventory[id].loose = 0; 
            saveDB_Inventory(); renderP2Table(); 
        } 
    } 
}

function clearP2Data() { 
    document.getElementById("p2_box").value = ""; 
    document.getElementById("p2_loose").value = ""; 
    document.getElementById("p2_total").value = ""; 
}

function renderP2Table() {
    const tbody = document.getElementById("p2_table").querySelector("tbody"); tbody.innerHTML = "";
    for (let id in db.inventory) { 
        let p_info = db.powder[id]; 
        if (!p_info || (db.hide_status.p2 && !p_info.active)) continue; 
        let inv = db.inventory[id]; 
        let total = (p_info.weight * inv.box + inv.loose); 
        let tr = document.createElement("tr"); 
        if (total < inv.safe) tr.classList.add("alert-stock"); 
        
        let actionTd = currentRole === 'admin' ? `<td><button class="action-btn act-del" onclick="deleteP2Record('${id}')">清</button></td>` : '';
        tr.innerHTML = `<td>${id}</td><td>${total.toFixed(1)}</td><td>${inv.safe}</td>${actionTd}`; 
        tbody.appendChild(tr); 
    }
}

// --- 3. 採購入庫流水帳 ---
function linkP3Fields() { 
    const id = document.getElementById("p3_id").value; 
    if(!id || !db.powder[id]) return; 
    document.getElementById("p3_code").value = db.powder[id].code; 
    calcP3Total(); 
}

function calcP3Total() { 
    const w = db.powder[document.getElementById("p3_id").value]?.weight || 25; 
    const b = parseFloat(document.getElementById("p3_box").value) || 0; 
    const l = parseFloat(document.getElementById("p3_loose").value) || 0; 
    document.getElementById("p3_total").value = (w * b + l).toFixed(2); 
}

function saveP3Record() {
    const date = document.getElementById("p3_date").value; 
    const id = document.getElementById("p3_id").value; 
    const box = parseFloat(document.getElementById("p3_box").value) || 0; 
    const loose = parseFloat(document.getElementById("p3_loose").value) || 0;
    
    if(!date || !id) return alert("請填妥日期與粉號！");
    let p_info = db.powder[id]; let total = p_info.weight * box + loose;
    if(!db.inventory[id]) db.inventory[id] = { loose: 0, box: 0, safe: 100 };

    if (p3_edit_idx > -1) {
        let old = db.purchase[p3_edit_idx];
        if(db.inventory[old.id]) { db.inventory[old.id].box -= old.box; db.inventory[old.id].loose -= old.loose; }
        db.purchase[p3_edit_idx] = { date, id, code: p_info.code, color: p_info.color, weight: p_info.weight, box, loose, total };
        alert("入庫紀錄修改成功！");
    } else { 
        db.purchase.push({ date, id, code: p_info.code, color: p_info.color, weight: p_info.weight, box, loose, total }); 
    }
    
    db.inventory[id].box += box; db.inventory[id].loose += loose;
    saveDB_Purchase(); saveDB_Inventory(); cancelP3Edit(); renderP2Table(); renderP3Table();
}

function editP3(idx) { 
    p3_edit_idx = idx; let r = db.purchase[idx]; 
    document.getElementById("p3_date").value = r.date; 
    document.getElementById("p3_id").value = r.id; 
    document.getElementById("p3_code").value = r.code; 
    document.getElementById("p3_box").value = r.box; 
    document.getElementById("p3_loose").value = r.loose; 
    document.getElementById("p3_total").value = r.total; 
    document.getElementById("p3_save_btn").innerText = "儲存此筆修改"; 
    document.getElementById("p3_cancel_btn").style.display = "inline-block"; 
}

function cancelP3Edit() { 
    p3_edit_idx = -1; 
    document.getElementById("p3_box").value = "0"; 
    document.getElementById("p3_loose").value = "0"; 
    document.getElementById("p3_total").value = "0"; 
    document.getElementById("p3_save_btn").innerText = "確認入庫"; 
    document.getElementById("p3_cancel_btn").style.display = "none"; 
}

function deleteP3(idx) {
    if(confirm("確定刪除這筆入庫紀錄嗎？對應增加的庫存將會被自動扣除！")) {
        let r = db.purchase[idx];
        if(db.inventory[r.id]) { 
            db.inventory[r.id].box -= r.box; db.inventory[r.id].loose -= r.loose; 
            if(db.inventory[r.id].box < 0) db.inventory[r.id].box = 0; 
            if(db.inventory[r.id].loose < 0) db.inventory[r.id].loose = 0; 
        }
        db.purchase.splice(idx, 1); saveDB_Purchase(); saveDB_Inventory(); renderP2Table(); renderP3Table();
    }
}

function renderP3Table() {
    const tbody = document.getElementById("p3_table").querySelector("tbody"); tbody.innerHTML = "";
    for(let i = db.purchase.length-1; i >= 0; i--) { 
        let r = db.purchase[i]; let tr = document.createElement("tr"); 
        let actionTd = currentRole === 'admin' ? `<td><button class="action-btn act-edit" onclick="editP3(${i})">改</button><button class="action-btn act-del" onclick="deleteP3(${i})">刪</button></td>` : '';
        tr.innerHTML = `<td>${r.date}</td><td>${r.id}</td><td>+${r.total}kg</td>${actionTd}`; 
        tbody.appendChild(tr); 
    }
}

// --- 4. 出庫用量紀錄 (核心修復連動區塊) ---
function linkP4Fields() { 
    const id = document.getElementById("p4_id").value; 
    if(!id || !db.powder[id]) {
        document.getElementById("p4_code").value = "";
        document.getElementById("p4_stock").value = "無數據";
        return;
    } 
    // 精準抓取工廠代號
    document.getElementById("p4_code").value = db.powder[id].code; 
    
    // 【完美連動】核心對接 db.inventory 庫存監控數據
    let inv = db.inventory[id] || { box: 0, loose: 0 }; 
    document.getElementById("p4_stock").value = `${inv.box} 箱 + ${inv.loose} kg`; 
    
    calcP4Usage(); 
}

function calcP4Usage() { 
    const id = document.getElementById("p4_id").value; 
    const w = db.powder[id]?.weight || 25; 
    const b = parseFloat(document.getElementById("p4_box").value) || 0; 
    const l = parseFloat(document.getElementById("p4_loose").value) || 0; 
    const ret = parseFloat(document.getElementById("p4_return").value) || 0; 
    let totalWeight = w * b + l; 
    document.getElementById("p4_usage_show").innerText = (totalWeight - ret).toFixed(2) + " kg"; 
}

function saveP4Record() {
    const date = document.getElementById("p4_date").value; 
    const id = document.getElementById("p4_id").value; 
    const box = parseFloat(document.getElementById("p4_box").value) || 0; 
    const loose = parseFloat(document.getElementById("p4_loose").value) || 0; 
    const returned = parseFloat(document.getElementById("p4_return").value) || 0;
    
    if(!date || !id) return alert("請填妥日期與粉號！");
    let p_info = db.powder[id]; let total = p_info.weight * box + loose; let usageVal = total - returned; 
    let inv = db.inventory[id] || { box: 0, loose: 0, safe: 100 };
    
    if (p4_edit_idx > -1) {
        let old = db.usage[p4_edit_idx];
        if(db.inventory[old.id]) { db.inventory[old.id].box += old.box; db.inventory[old.id].loose = db.inventory[old.id].loose + old.loose - old.returned; }
        db.usage[p4_edit_idx] = { date, id, code: p_info.code, color: p_info.color, weight: p_info.weight, box, loose, total, returned, usage: usageVal }; 
        alert("出庫用量修改成功！");
    } else { 
        db.usage.push({ date, id, code: p_info.code, color: p_info.color, weight: p_info.weight, box, loose, total, returned, usage: usageVal }); 
    }
    
    inv.box -= box; inv.loose = inv.loose - loose + returned; 
    if(inv.loose < 0) inv.loose = 0; 
    db.inventory[id] = inv;
    
    saveDB_Usage(); saveDB_Inventory(); cancelP4Edit(); renderP2Table(); renderP4Table();
}

function editP4(idx) { 
    p4_edit_idx = idx; let r = db.usage[idx]; 
    document.getElementById("p4_date").value = r.date; 
    document.getElementById("p4_id").value = r.id; 
    document.getElementById("p4_code").value = r.code; 
    document.getElementById("p4_box").value = r.box; 
    document.getElementById("p4_loose").value = r.loose; 
    document.getElementById("p4_return").value = r.returned; 
    document.getElementById("p4_save_btn").innerText = "儲存此筆修改"; 
    document.getElementById("p4_cancel_btn").style.display = "inline-block"; 
    linkP4Fields(); // 同步刷庫存字樣
}

function cancelP4Edit() { 
    p4_edit_idx = -1; 
    document.getElementById("p4_box").value = "0"; 
    document.getElementById("p4_loose").value = "0"; 
    document.getElementById("p4_return").value = "0"; 
    document.getElementById("p4_save_btn").innerText = "確認出庫"; 
    document.getElementById("p4_cancel_btn").style.display = "none"; 
    document.getElementById("p4_usage_show").innerText = "0"; 
}

function deleteP4(idx) {
    if(confirm("確定刪除這筆出庫用量紀錄嗎？系統會將原本扣除的庫存數量自動歸還補回！")) {
        let r = db.usage[idx];
        if(db.inventory[r.id]) { 
            db.inventory[r.id].box += r.box; db.inventory[r.id].loose = db.inventory[r.id].loose + r.loose - r.returned; 
            if(db.inventory[r.id].loose < 0) db.inventory[r.id].loose = 0; 
        }
        db.usage.splice(idx, 1); saveDB_Usage(); saveDB_Inventory(); renderP2Table(); renderP4Table();
    }
}

function renderP4Table() {
    const tbody = document.getElementById("p4_table").querySelector("tbody"); tbody.innerHTML = "";
    for(let i = db.usage.length-1; i >= 0; i--) { 
        let r = db.usage[i]; let tr = document.createElement("tr"); 
        let actionTd = currentRole === 'admin' ? `<td><button class="action-btn act-edit" onclick="editP4(${i})">改</button><button class="action-btn act-del" onclick="deleteP4(${i})">刪</button></td>` : '';
        tr.innerHTML = `<td>${r.date}</td><td>${r.id}</td><td class="red-bold">${r.usage.toFixed(1)}kg</td>${actionTd}`; 
        tbody.appendChild(tr); 
    }
}

// --- 5. 用量紀錄查詢模組 ---
function refreshP5Dates() { 
    const sel = document.getElementById("p5_date_select"); 
    let dates = [...new Set(db.usage.map(r => r.date))].sort().reverse(); 
    let html = '<option value="">請選擇日期...</option>'; 
    dates.forEach(d => { html += `<option value="${d}">${d}</option>`; }); 
    sel.innerHTML = html; 
    document.getElementById("p5_table").querySelector("tbody").innerHTML = ""; 
}

function queryP5Data() { 
    const date = document.getElementById("p5_date_select").value; 
    const tbody = document.getElementById("p5_table").querySelector("tbody"); tbody.innerHTML = ""; 
    if(!date) return; 
    db.usage.filter(r => r.date === date).forEach(r => { 
        let tr = document.createElement("tr"); 
        tr.innerHTML = `<td>${r.id}</td><td>${r.code}</td><td>${r.weight}</td><td class="red-bold">${r.usage.toFixed(1)}</td>`; 
        tbody.appendChild(tr); 
    }); 
}

// --- 6. 庫存快速查詢模組 ---
function queryP6Data() {
    const id = document.getElementById("p6_select").value; 
    const resBox = document.getElementById("p6_result"); 
    if(!id) { resBox.style.display = "none"; return; }
    let p_info = db.powder[id]; 
    let inv = db.inventory[id] || { box: 0, loose: 0, safe: 100 }; 
    let total = p_info.weight * inv.box + inv.loose;
    resBox.style.display = "block"; 
    resBox.innerHTML = `<p><strong>粉號:</strong> ${id} (${p_info.active ? '現役上架':'歷史下架'})</p><p><strong>廠商代號:</strong> ${p_info.code} / 廠商: ${p_info.vendor}</p><p><strong>現存規格:</strong> ${inv.box} 箱 + 散粉 ${inv.loose} kg</p><p style="margin-top:8px; font-size:18px;"><strong>即時總庫存:</strong> <span class="red-bold">${total.toFixed(2)} kg</span></p>`;
}

// --- 7. 廢粉統計模組 ---
function calcP7Total() { 
    const q = parseFloat(document.getElementById("p7_qty").value) || 0; 
    document.getElementById("p7_total").value = (q * 25).toFixed(1); 
}

function saveP7Record() {
    const date = document.getElementById("p7_date").value; 
    const qty = parseFloat(document.getElementById("p7_qty").value) || 0; 
    if(!date || qty <= 0) return alert("請填寫正確日期與數量！");
    
    if (p7_edit_idx > -1) { 
        let oldTimestamp = db.waste[p7_edit_idx].timestamp || new Date(date).getTime(); 
        db.waste[p7_edit_idx] = { date, qty, weight: 25, total: qty * 25, timestamp: oldTimestamp }; 
        alert("廢粉紀錄更新成功！"); 
    } else { 
        db.waste.push({ date, qty, weight: 25, total: qty * 25, timestamp: new Date().getTime() }); 
    }
    saveDB_Waste(); cancelP7Edit(); renderP7Table(); calcWasteStats();
}

function editP7(idx) { 
    p7_edit_idx = idx; let r = db.waste[idx]; 
    document.getElementById("p7_date").value = r.date; 
    document.getElementById("p7_qty").value = r.qty; 
    document.getElementById("p7_total").value = r.total; 
    document.getElementById("p7_save_btn").innerText = "儲存此筆修改"; 
    document.getElementById("p7_cancel_btn").style.display = "inline-block"; 
}

function cancelP7Edit() { 
    p7_edit_idx = -1; 
    document.getElementById("p7_qty").value = ""; 
    document.getElementById("p7_total").value = ""; 
    document.getElementById("p7_save_btn").innerText = "紀錄流水帳"; 
    document.getElementById("p7_cancel_btn").style.display = "none"; 
}

function deleteP7(idx) { 
    if(confirm("確定刪除此筆廢粉紀錄嗎？回收預測指數將重新動態估算。")) { 
        db.waste.splice(idx, 1); saveDB_Waste(); renderP7Table(); calcWasteStats(); 
    } 
}

function renderP7Table() { 
    const tbody = document.getElementById("p7_table").querySelector("tbody"); tbody.innerHTML = ""; 
    for(let i = db.waste.length-1; i >= 0; i--) { 
        let r = db.waste[i]; let tr = document.createElement("tr"); 
        let actionTd = currentRole === 'admin' ? `<td><button class="action-btn act-edit" onclick="editP7(${i})">改</button><button class="action-btn act-del" onclick="deleteP7(${i})">刪</button></td>` : '';
        tr.innerHTML = `<td>${r.date}</td><td>${r.qty} 桶</td><td>${r.total}kg</td>${actionTd}`; 
        tbody.appendChild(tr); 
    } 
}

function calcWasteStats() {
    let filterTime = db.p7_reset_time || 0; 
    let activeRecords = db.waste.filter(r => { let rTime = r.timestamp || new Date(r.date).getTime(); return rTime >= filterTime; });
    
    if (activeRecords.length === 0) { 
        document.getElementById("avg_day").innerText = "0.0"; document.getElementById("avg_week").innerText = "0.0"; 
        document.getElementById("avg_month").innerText = "0.0"; document.getElementById("avg_year").innerText = "0.0"; 
        document.getElementById("p7_cum_ton").innerText = "0.000"; document.getElementById("p7_forecast_val").innerHTML = "尚無資料計算"; return; 
    }
    let totalWeight = activeRecords.reduce((sum, r) => sum + r.total, 0); 
    let dates = activeRecords.map(r => new Date(r.date).getTime()); 
    let minDate = new Date(Math.min(...dates)); let maxDate = new Date(); 
    let dayDiff = Math.ceil((maxDate - minDate) / (1000 * 60 * 60 * 24)) + 1; 
    if (dayDiff < 1) dayDiff = 1;
    
    let avgDay = totalWeight / dayDiff; 
    document.getElementById("avg_day").innerText = avgDay.toFixed(1); 
    document.getElementById("avg_week").innerText = (avgDay * 7).toFixed(1); 
    document.getElementById("avg_month").innerText = (avgDay * 30).toFixed(1); 
    document.getElementById("avg_year").innerText = (avgDay * 365).toFixed(1);
    
    let cumTon = totalWeight / 1000; 
    document.getElementById("p7_cum_ton").innerText = cumTon.toFixed(3);
    
    const alertBox = document.getElementById("p7_alert_box"); let targetWeight = 10000; let remainingWeight = targetWeight - totalWeight;
    if (remainingWeight <= 0) { 
        alertBox.style.background = "#fee2e2"; alertBox.style.color = "#b91c1c"; 
        document.getElementById("p7_forecast_val").innerHTML = `⚠️ 警告！已達 10 噸標準！`; 
    } else { 
        if (avgDay > 0) { 
            let remainingDays = Math.ceil(remainingWeight / avgDay); 
            if (remainingDays <= 7) { 
                alertBox.style.background = "#fef3c7"; alertBox.style.color = "#92400e"; 
                document.getElementById("p7_forecast_val").innerHTML = `⚠️ 預估再 <strong>${remainingDays}</strong> 天將達到 10 噸！`; 
            } else { 
                alertBox.style.background = "#e0f2fe"; alertBox.style.color = "#0369a1"; 
                document.getElementById("p7_forecast_val").innerHTML = `預估還有 <strong>${remainingDays}</strong> 天達到 10 噸`; 
            } 
        } else { 
            document.getElementById("p7_forecast_val").innerHTML = "尚無足夠每日平均值計算預測"; 
        } 
    }
}

function resetP7Index() { 
    if(confirm("確定要重置廢粉回收指數嗎？\n(歷史流水帳資料會妥善保留，但天數與倒數計時將從此刻重新計算)")) { 
        db.p7_reset_time = new Date().getTime(); saveDB_Settings(); calcWasteStats(); 
    } 
}

// --- 🗃️ CSV 通用雙向擴充驅動模組 (1-7全覆蓋) ---
function exportCSV(moduleNum) {
    let csvContent = "\uFEFF"; let filename = "";
    if (moduleNum === 1) { 
        csvContent += "粉號,代號,色系,淨重,廠商,是否上架\n"; 
        for(let id in db.powder) { let r = db.powder[id]; csvContent += `"${id}","${r.code}","${r.color}","${r.weight}","${r.vendor}","${r.active}"\n`; } 
        filename = "1_烤漆粉號清冊.csv"; 
    } else if (moduleNum === 2) { 
        csvContent += "粉號,箱數,散粉,安全庫存\n"; 
        for(let id in db.inventory) { let r = db.inventory[id]; csvContent += `"${id}","${r.box}","${r.loose}","${r.safe}"\n`; } 
        filename = "2_庫存監控表.csv"; 
    } else if (moduleNum === 3) { 
        csvContent += "日期,粉號,代號,色系,淨重,箱數,散粉,總數量\n"; 
        db.purchase.forEach(r => { csvContent += `"${r.date}","${r.id}","${r.code}","${r.color}","${r.weight}","${r.box}","${r.loose}","${r.total}"\n`; }); 
        filename = "3_採購入庫紀錄.csv"; 
    } else if (moduleNum === 4) { 
        csvContent += "日期,粉號,代號,色系,淨重,扣除箱數,扣除散粉,重量,回庫散粉,實際用量\n"; 
        db.usage.forEach(r => { csvContent += `"${r.date}","${r.id}","${r.code}","${r.color}","${r.weight}","${r.box}","${r.loose}","${r.total}","${r.returned}","${r.usage}"\n`; }); 
        filename = "4_用量歷史總紀錄.csv"; 
    } else if (moduleNum === 5) {
        const date = document.getElementById("p5_date_select").value;
        csvContent += `日期(${date||'全份'}),粉號,代號,淨重,用量(kg)\n`;
        let dataset = date ? db.usage.filter(r => r.date === date) : db.usage;
        dataset.forEach(r => { csvContent += `"${r.date}","${r.id}","${r.code}","${r.weight}","${r.usage}"\n`; });
        filename = `5_用量篩選紀錄_${date || '全部'}.csv`;
    } else if (moduleNum === 6) {
        csvContent += "廠內即時總盤點報表\n粉號,廠內代號,廠商,當前箱數,當前散粉(kg),即時庫存總重(kg)\n";
        for (let id in db.powder) {
            let p = db.powder[id]; let inv = db.inventory[id] || {box:0, loose:0};
            let total = (p.weight * inv.box + inv.loose);
            csvContent += `"${id}","${p.code}","${p.vendor}","${inv.box}","${inv.loose}","${total.toFixed(2)}"\n`;
        }
        filename = "6_全廠即時總庫存報表.csv";
    } else if (moduleNum === 7) {
        csvContent += "日期,廢粉數量(桶),單桶重,總計重量(kg)\n";
        db.waste.forEach(r => { csvContent += `"${r.date}","${r.qty}","${r.weight}","${r.total}"\n`; });
        filename = "7_廢粉回收歷史紀錄流水帳.csv";
    }
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' }); 
    let link = document.createElement("a"); link.href = URL.createObjectURL(blob); 
    link.setAttribute("download", filename); document.body.appendChild(link); link.click(); document.body.removeChild(link);
}

function importCSV(moduleNum) {
    const fileInput = document.getElementById(`p${moduleNum}_file`); 
    if (!fileInput || !fileInput.files.length) return alert("請選取要上傳的 CSV 檔案！");
    const reader = new FileReader();
    reader.onload = function(e) {
        let lines = e.target.result.split("\n").map(l => l.trim()).filter(l => l.length > 0);
        for(let i = 1; i < lines.length; i++) {
            let row = lines[i].split(",").map(cell => cell.replace(/^"|"$/g, ''));
            if (moduleNum === 1 && row.length >= 5) { 
                db.powder[row[0]] = { code: row[1], color: row[2], weight: parseInt(row[3])||25, vendor: row[4], active: row[5]==="false"?false:true }; 
            } else if (moduleNum === 2 && row.length >= 4) { 
                db.inventory[row[0]] = { box: parseFloat(row[1])||0, loose: parseFloat(row[2])||0, safe: parseFloat(row[3])||100 }; 
            } else if (moduleNum === 3 && row.length >= 8) { 
                db.purchase.push({ date:row[0], id:row[1], code:row[2], color:row[3], weight:parseFloat(row[4]), box:parseFloat(row[5]), loose:parseFloat(row[6]), total:parseFloat(row[7]) }); 
            } else if (moduleNum === 4 && row.length >= 10) { 
                db.usage.push({ date:row[0], id:row[1], code:row[2], color:row[3], weight:parseFloat(row[4]), box:parseFloat(row[5]), loose:parseFloat(row[6]), total:parseFloat(row[7]), returned:parseFloat(row[8]), usage:parseFloat(row[9]) }); 
            } else if (moduleNum === 5 && row.length >= 5) {
                db.usage.push({ date:row[0], id:row[1], code:row[2], color: '', weight:parseFloat(row[3]), box:0, loose:0, total:parseFloat(row[4]), returned:0, usage:parseFloat(row[4]) });
            } else if (moduleNum === 7 && row.length >= 4) {
                db.waste.push({ date:row[0], qty:parseFloat(row[1]), weight:parseFloat(row[2]), total:parseFloat(row[3]), timestamp: new Date(row[0]).getTime() });
            }
        }
        saveAllDB(); initDB(); alert("CSV 數據成功同步並導入系統核心資料庫！");
    };
    reader.readAsText(fileInput.files[0], "UTF-8");
}

window.onload = initDB;
