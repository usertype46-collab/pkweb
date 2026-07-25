import os
import time
import threading
import json
from flask import Flask, render_template, send_from_directory
from flask_socketio import SocketIO, emit
from supabase import create_client, Client

app = Flask(__name__)
app.config['SECRET_KEY'] = 'secret!'
socketio = SocketIO(app, cors_allowed_origins="*")

# ==========================================
# 1. Supabase 初始化設定
# ==========================================
SUPABASE_URL = "https://cnkxsxhgdtuxknrzufhv.supabase.co"
SUPABASE_KEY = "sb_publishable_QEoX_f9G_Gf9kaaDZpaH-g_ageY5WFK"

try:
    supabase: Client = create_client(SUPABASE_URL, SUPABASE_KEY)
    supabase_connected = True
    print("✅ Supabase 連線成功")
except Exception as e:
    supabase_connected = False
    print(f"❌ Supabase 連線失敗: {e}")

# 全域狀態 (維持記憶體快取以確保 Socket 效能)
sys_state = {
    'line_speed': 1100,
    'cards': {},
    'active_card_id': None
}

def sync_from_supabase():
    if not supabase_connected: return
    try:
        # 同步卡片
        cards_res = supabase.table('cards').select("*").execute()
        sys_state['cards'] = {c['id']: c for c in cards_res.data}
        
        # 同步速度
        speed_res = supabase.table('sys_settings').select("*").eq('key', 'line_speed').execute()
        if speed_res.data:
            sys_state['line_speed'] = int(speed_res.data[0]['value'])
    except Exception as e:
        print(f"Sync error: {e}")

sync_from_supabase()

active_card_lock = threading.Lock()
current_active_card_template = None
clone_counter = 0

# ==========================================
# 2. 前端共用語言包模組
# ==========================================
I18N_SCRIPT = """
<style>
    body { padding-top: 60px !important; }
    .lang-btn { position: fixed; top: 10px; right: 10px; z-index: 1000; background: #34495e; color: white; border: none; padding: 10px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; font-size: 13px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: 0.3s; }
    .lang-btn:hover { background: #2c3e50; }
    .settings-btn { position: fixed; top: 10px; right: 170px; z-index: 1000; background: #e67e22; color: white; border: none; padding: 10px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; font-size: 13px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: 0.3s; }
    .settings-btn:hover { background: #d35400; }
</style>
<button class="settings-btn" id="settingsBtn" onclick="toggleSettings()">⚙️ 客製化設定 / Cài đặt</button>
<button class="lang-btn" id="langBtn" onclick="toggleLang()">🌐 切換語言 / Ngôn ngữ</button>
<script>
    const i18n = {
        'zh': {
            'sim_title': '🏭 粉體塗裝流水線模擬器', 'speed': '流水線轉速: ', 'time_lbl': '跑完全程所需時間: ', 'mins': ' 分鐘',
            'wait_title': '📦 現場待料區', 'part_ph': '手動輸入或掃描料號', 'name_ph': '手動輸入或掃描品名',
            'scan_btn': '📷 掃描', 'add_btn': '➕ 新增待上線構件', 'list_wait': '待處理清單 (順序排列)',
            'ali_sync': '[待上料_阿利] 同步接收區', 'btn_send_ali': '➕ 傳送阿利', 'btn_del': '刪除',
            'ocr_title': '📸 拍照系統', 'ocr_tip': '請將目標對準綠色框框內', 'btn_pic': '📸 拍照儲存', 'btn_close': '關閉',
            'ali_title': '🏗️ 待上料_阿利', 'line_sync': '⬇️ [流水線_上線] 同步傳送區 ⬇️',
            'lbl_hang': '掛:', 'lbl_empty': '空:', 'lbl_space': '間隔:', 'lbl_hook': '接勾:',
            'btn_to_line': '🚀 上線並開始持續滿線插入', 'calcing': '計算上線時間...',
            'unload_title': '✅ 下料與完成紀錄區', 'unload_wait': '待下料區 (模擬器跑完自動傳送至此)',
            'done_list': '歷史完成紀錄', 'btn_done': '✅ 點擊完成',
            'lang_btn': '🇻🇳 切換為越文 (Việt)', 'part_no': '料號:', 'part_name': '品名:', 'qty': '數量:', 'color': '顏色:',
            'est_time': '預估佔線時間: ', 'hrs': ' 小時 ', 'done_time': '完成時間: ', 'alert_del': '⚠️ 確定要刪除這筆資料嗎？',
            'comp_lbl': '構件', 'full_line_status': '⚡ 滿線模式持續上料中: ', 'none_loading': '暫無 (等待上料)',
            'settings_title': '⚙️ 軌道與卡片客製化設定', 'save_settings': '儲存並套用', 'reset_settings': '重設預設值',
            'card_width': '卡片寬度 (px):', 'card_height': '卡片高度 (px):', 'card_font': '卡片字體大小 (px):'
        },
        'vi': {
            'sim_title': '🏭 Trình mô phỏng chuyền sơn', 'speed': 'Tốc độ chuyền: ', 'time_lbl': 'Thời gian 1 vòng: ', 'mins': ' Phút',
            'wait_title': '📦 Khu vực chờ vật liệu', 'part_ph': 'Nhập / Quét mã LK', 'name_ph': 'Nhập / Quét tên LK',
            'scan_btn': '📷 Quét', 'add_btn': '➕ Thêm vào hàng chờ', 'list_wait': 'Danh sách chờ (Theo thứ tự)',
            'ali_sync': 'Khu vực đồng bộ [Chờ lên hàng_Ali]', 'btn_send_ali': '➕ Chuyển cho Ali', 'btn_del': 'Xóa',
            'ocr_title': '📸 Hệ thống chụp', 'ocr_tip': 'Căn chỉnh văn bản vào khung xanh', 'btn_pic': '📸 Chụp & Lưu', 'btn_close': 'Đóng',
            'ali_title': '🏗️ Chờ lên hàng_Ali', 'line_sync': '⬇️ [Dây chuyền] Truyền đồng bộ ⬇️',
            'lbl_hang': 'Treo:', 'lbl_empty': 'Trống:', 'lbl_space': 'Cách:', 'lbl_hook': 'Móc:',
            'btn_to_line': '🚀 Lên chuyền & Nạp liên tục', 'calcing': 'Đang tính...',
            'unload_title': '✅ Khu vực xuống hàng & Lịch sử', 'unload_wait': 'Khu vực chờ xuống hàng',
            'done_list': 'Lịch sử hoàn thành', 'btn_done': '✅ Hoàn thành',
            'lang_btn': '🇹🇼 Đổi ngôn ngữ (中文)', 'part_no': 'Mã LK:', 'part_name': 'Tên LK:', 'qty': 'SL:', 'color': 'Màu:',
            'est_time': 'TG dự kiến: ', 'hrs': ' Giờ ', 'done_time': 'Thời gian HT: ', 'alert_del': 'Xác nhận xóa dữ liệu này?',
            'comp_lbl': 'Cấu kiện', 'full_line_status': '⚡ Đang nạp hàng liên tục: ', 'none_loading': 'Chưa có',
            'settings_title': '⚙️ Cài đặt tùy chỉnh đường đi & Thẻ', 'save_settings': 'Lưu & Áp dụng', 'reset_settings': 'Mặc định',
            'card_width': 'Chiều rộng thẻ (px):', 'card_height': 'Chiều cao thẻ (px):', 'card_font': 'Kích thước chữ thẻ (px):'
        }
    };
    let currentLang = localStorage.getItem('appLang') || 'zh';
    function t(key) { return i18n[currentLang][key] || key; }
    function toggleLang() {
        currentLang = currentLang === 'zh' ? 'vi' : 'zh';
        localStorage.setItem('appLang', currentLang);
        applyLang();
        if(typeof renderDynamic === 'function') renderDynamic(); 
    }
    function applyLang() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if(el.tagName === 'INPUT') el.placeholder = t(key);
            else el.innerHTML = t(key);
        });
        const langBtn = document.getElementById('langBtn');
        if(langBtn) langBtn.innerHTML = t('lang_btn');
    }
    function renderField(val) {
        if (typeof val === 'string' && val.startsWith('data:image')) {
            return `<img src="${val}" style="max-height: 40px; vertical-align: middle; border-radius: 4px; border: 1px solid #ccc; margin: 2px;">`;
        }
        return val;
    }
    window.addEventListener('DOMContentLoaded', applyLang);
</script>
"""

# ==========================================
# 3. HTML 樣板生成 (使用字串替換避免 f-string 解析問題)
# ==========================================
def create_templates():
    if not os.path.exists('templates'):
        os.makedirs('templates')

    html_files = {}

    # --- 模擬器主畫面 (掛載 React+D3 與 Web Worker) ---
    sim_html = """
    <!DOCTYPE html>
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>粉體塗裝流水線模擬器</title>
        <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
        <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
        <script src="https://unpkg.com/babel-standalone@6/babel.min.js"></script>
        <script src="https://d3js.org/d3.v7.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.7.2/socket.io.js"></script>
        
        <style>
            body { font-family: '微軟正黑體', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
            .dashboard { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .speed-ctrl button { font-size: 18px; padding: 5px 15px; margin: 0 10px; cursor: pointer; }
            .status-banner { background: #27ae60; color: white; padding: 10px; border-radius: 6px; font-weight: bold; margin-top: 10px; display: flex; align-items: center; justify-content: space-between; }
            
            .factory-map { 
                position: relative; width: 100%; max-width: 768px; 
                aspect-ratio: 768 / 1024;
                background: url('/14436.png') no-repeat center center;
                background-size: cover;
                border-radius: 10px; border: 2px solid #ccc; margin: 0 auto;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                overflow: hidden;
            }
            .factory-map svg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: auto; }
            
            .chain-track {
                stroke-dasharray: 10, 8;
                animation: moveChain 1.5s linear infinite;
                opacity: 0.85;
            }
            @keyframes moveChain { from { stroke-dashoffset: 18; } to { stroke-dashoffset: 0; } }

            .mini-card { 
                position: absolute; 
                width: var(--card-w, 50px); 
                height: var(--card-h, 26px); 
                border-radius: 4px; 
                border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.5); 
                transform: translate(-50%, -50%); cursor: pointer; 
                display: flex; align-items: center; justify-content: center; 
                font-size: var(--card-font-sz, 12px); 
                color: white; text-shadow: 1px 1px 2px black; font-weight: bold; 
                z-index: 10; padding: 0 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                will-change: left, top; /* 提升動畫效能 */
            }
            .mini-card .details { display: none; }
            
            #overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9990; }
            .mini-card.expanded {
                position: fixed !important; top: 50% !important; left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 85vw; max-width: 350px; height: auto; border-radius: 12px; padding: 20px;
                z-index: 9999; text-align: left; box-shadow: 0 10px 25px rgba(0,0,0,0.5);
                cursor: default; display: flex; flex-direction: column; white-space: normal; background-color: #34495e !important;
            }
            .mini-card.expanded .short-id { display: none; }
            .mini-card.expanded .details { display: block; font-size: 16px; line-height: 1.8; width: 100%; position: relative; color: white; }
            
            .close-btn { 
                position: absolute; top: -10px; right: -10px; font-size: 24px; 
                cursor: pointer; font-weight: bold; background: rgba(0,0,0,0.7);
                border-radius: 50%; width: 32px; height: 32px; text-align: center; line-height: 28px; border: 2px solid white; color: white;
            }

            /* Settings Modal */
            #settingsModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10000; align-items: center; justify-content: center; }
            .settings-content { background: white; width: 90%; max-width: 600px; padding: 25px; border-radius: 10px; max-height: 90vh; overflow-y: auto; }
            
            .d3-node { cursor: grab; }
            .d3-node:active { cursor: grabbing; }
        </style>
        __I18N_SCRIPT__
    </head>
    <body onclick="closeAllCards()">
        <div id="overlay"></div>
        
        <div id="settingsModal" onclick="event.stopPropagation()">
            <div class="settings-content" id="react-settings-root">
                </div>
        </div>

        <div class="dashboard">
            <h2 data-i18n="sim_title">🏭 粉體塗裝流水線模擬器</h2>
            <div class="speed-ctrl">
                <span data-i18n="speed">流水線轉速: </span><span id="current-speed" style="font-weight:bold; color:red; font-size:20px;">1100</span>
                <button onclick="changeSpeed(-100); event.stopPropagation();">-100</button>
                <button onclick="changeSpeed(100); event.stopPropagation();">+100</button>
                <br><small><span data-i18n="time_lbl">跑完全程所需時間: </span><span id="total-time" style="font-weight:bold;"></span><span data-i18n="mins"> 分鐘</span></small>
            </div>
            <div class="status-banner" id="active-banner">
                <span><span data-i18n="full_line_status">⚡ 滿線模式持續上料中: </span><span id="active-card-name" style="text-decoration:underline;">暫無</span></span>
            </div>
        </div>
        
        <div class="factory-map" id="map">
            <svg viewBox="0 0 1000 1333" preserveAspectRatio="none" id="main-svg">
                <path id="track" d="" fill="none" stroke="#e74c3c" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" class="chain-track"/>
            </svg>
            <div id="cards-container"></div>
        </div>

        <script type="text/babel">
            const defaultPathD = "M 461 1180 L 461 280 L 91 280 L 91 98 L 931 98 L 931 185 L 580 186 L 580 271 L 930 271 L 930 352 L 580 352 L 580 432 L 930 432 L 930 510 L 580 510 L 580 470 L 930 1180 L 461 1180 Z";
            
            function SettingsEditor() {
                const [cardW, setCardW] = React.useState(localStorage.getItem('cardW') || '60');
                const [cardH, setCardH] = React.useState(localStorage.getItem('cardH') || '30');
                const [cardFont, setCardFont] = React.useState(localStorage.getItem('cardFont') || '14');
                const [pathStr, setPathStr] = React.useState(localStorage.getItem('trackPathD') || defaultPathD);
                const [isEditingPath, setIsEditingPath] = React.useState(false);

                // 解析 SVG Path
                const parsePath = (d) => {
                    const regex = /([MLZ])\s*(\d+)?\s*(\d+)?/g;
                    let match;
                    let pts = [];
                    let id = 0;
                    while ((match = regex.exec(d)) !== null) {
                        pts.push({
                            id: id++,
                            type: match[1],
                            x: match[2] ? parseFloat(match[2]) : null,
                            y: match[3] ? parseFloat(match[3]) : null
                        });
                    }
                    return pts;
                };

                const buildPath = (pts) => {
                    return pts.map(p => p.type === 'Z' ? 'Z' : `${p.type} ${p.x} ${p.y}`).join(' ');
                };

                React.useEffect(() => {
                    applyStyles(cardW, cardH, cardFont);
                    document.getElementById('track').setAttribute('d', pathStr);
                }, []);

                React.useEffect(() => {
                    const svg = d3.select("#main-svg");
                    if (isEditingPath) {
                        let points = parsePath(pathStr).filter(p => p.type !== 'Z');
                        
                        const drag = d3.drag()
                            .on("drag", function(event, d) {
                                d.x = event.x;
                                d.y = event.y;
                                d3.select(this).attr("cx", d.x).attr("cy", d.y);
                                
                                // 即時更新底層路徑
                                const allPts = parsePath(pathStr);
                                allPts[d.id].x = Math.round(d.x);
                                allPts[d.id].y = Math.round(d.y);
                                const newPath = buildPath(allPts);
                                document.getElementById('track').setAttribute('d', newPath);
                            })
                            .on("end", function(event, d) {
                                setPathStr(document.getElementById('track').getAttribute('d'));
                            });

                        const circles = svg.selectAll(".d3-node").data(points, d => d.id);
                        
                        circles.enter().append("circle")
                            .attr("class", "d3-node")
                            .attr("r", 12).attr("fill", "#3498db").attr("stroke", "#fff").attr("stroke-width", 2)
                            .call(drag)
                            .merge(circles)
                            .attr("cx", d => d.x).attr("cy", d => d.y);
                            
                        circles.exit().remove();
                    } else {
                        svg.selectAll(".d3-node").remove();
                    }
                }, [isEditingPath, pathStr]);

                const save = () => {
                    localStorage.setItem('cardW', cardW);
                    localStorage.setItem('cardH', cardH);
                    localStorage.setItem('cardFont', cardFont);
                    localStorage.setItem('trackPathD', pathStr);
                    applyStyles(cardW, cardH, cardFont);
                    setIsEditingPath(false);
                    toggleSettings();
                };

                const reset = () => {
                    setCardW('60'); setCardH('30'); setCardFont('14'); setPathStr(defaultPathD);
                };

                return (
                    <div>
                        <h2 data-i18n="settings_title">⚙️ 軌道與卡片客製化設定</h2>
                        
                        <div style={{display:'flex', gap:'10px', marginTop:'15px'}}>
                            <div style={{flex:1}}>
                                <label>卡片寬度 (px):</label>
                                <input type="number" value={cardW} onChange={e=>setCardW(e.target.value)} style={{width:'100%', padding:'8px'}} />
                            </div>
                            <div style={{flex:1}}>
                                <label>卡片高度 (px):</label>
                                <input type="number" value={cardH} onChange={e=>setCardH(e.target.value)} style={{width:'100%', padding:'8px'}} />
                            </div>
                            <div style={{flex:1}}>
                                <label>卡片字體大小 (px):</label>
                                <input type="number" value={cardFont} onChange={e=>setCardFont(e.target.value)} style={{width:'100%', padding:'8px'}} />
                            </div>
                        </div>

                        <div style={{marginTop: '20px', padding: '15px', background: '#f8f9fa', borderRadius: '8px'}}>
                            <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                <strong>SVG 軌道節點編輯器</strong>
                                <button 
                                    onClick={() => setIsEditingPath(!isEditingPath)}
                                    style={{padding: '8px 15px', background: isEditingPath ? '#e74c3c' : '#3498db', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer', fontWeight: 'bold'}}>
                                    {isEditingPath ? "📍 關閉節點拖曳" : "📍 開啟節點拖曳"}
                                </button>
                            </div>
                            {isEditingPath && <p style={{color: '#e67e22', fontSize:'13px', marginTop:'10px'}}>👉 提示: 請直接在畫面上的藍色圓點進行拖曳，放開後自動修正路徑。</p>}
                            <textarea value={pathStr} readOnly style={{width: '100%', height: '80px', marginTop: '10px', fontSize: '11px', color:'#7f8c8d'}} />
                        </div>
                        
                        <div style={{marginTop: '20px', display: 'flex', gap: '10px', justifyContent: 'flex-end'}}>
                            <button onClick={reset} style={{padding:'10px 20px', background:'#95a5a6', color:'white', border:'none', borderRadius:'5px', cursor:'pointer'}}>重設預設值</button>
                            <button onClick={save} style={{padding:'10px 20px', background:'#27ae60', color:'white', border:'none', borderRadius:'5px', cursor:'pointer', fontWeight:'bold'}}>儲存並套用</button>
                            <button onClick={toggleSettings} style={{padding:'10px 20px', background:'#c0392b', color:'white', border:'none', borderRadius:'5px', cursor:'pointer'}}>關閉</button>
                        </div>
                    </div>
                );
            }

            const root = ReactDOM.createRoot(document.getElementById('react-settings-root'));
            root.render(<SettingsEditor />);

            function applyStyles(w, h, f) {
                document.documentElement.style.setProperty('--card-w', w + 'px');
                document.documentElement.style.setProperty('--card-h', h + 'px');
                document.documentElement.style.setProperty('--card-font-sz', f + 'px');
            }
        </script>

        <script>
            const socket = io();
            const track = document.getElementById('track');
            const overlay = document.getElementById('overlay');
            const mapContainer = document.getElementById('cards-container');
            
            function toggleSettings() {
                const modal = document.getElementById('settingsModal');
                modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
            }

            let speedIndex = 11;
            let activeCardId = null; 
            let sys_state_cache = {cards:{}, active_card_id: null};
            let trackLength = 0;
            
            socket.on('update_state', (state) => {
                document.getElementById('current-speed').innerText = state.line_speed;
                speedIndex = state.line_speed / 100;
                document.getElementById('total-time').innerText = Math.round(1320 / speedIndex);
                
                sys_state_cache = state;
                updateActiveBanner();
                syncDOMCards();
                
                // 通知 Web Worker 最新狀態
                if (animWorker) {
                    animWorker.postMessage({
                        type: 'sync', 
                        cards: state.cards, 
                        speedIndex: speedIndex
                    });
                }
            });

            function changeSpeed(val) { socket.emit('change_speed', val); }
            
            function toggleCard(id, event) { 
                event.stopPropagation(); 
                activeCardId = activeCardId === id ? null : id;
                overlay.style.display = activeCardId ? 'block' : 'none';
                syncDOMCards(); 
            }
            
            function closeAllCards() { 
                activeCardId = null; 
                overlay.style.display = 'none';
                syncDOMCards(); 
            }

            function updateActiveBanner() {
                const activeId = sys_state_cache.active_card_id;
                const el = document.getElementById('active-card-name');
                if (activeId && sys_state_cache.cards[activeId]) {
                    const c = sys_state_cache.cards[activeId];
                    let name = c.part_name && !c.part_name.startsWith('data:image') ? c.part_name : c.part_no;
                    el.innerHTML = `${renderField(name)} (${c.color})`;
                } else {
                    el.innerHTML = t('none_loading');
                }
            }

            // --- 建立 Web Worker 動畫引擎 ---
            const workerCode = `
                let cards = {};
                let speedIndex = 11;
                
                self.onmessage = function(e) {
                    if(e.data.type === 'sync') {
                        cards = e.data.cards;
                        speedIndex = e.data.speedIndex;
                    }
                };

                // 60FPS 運算迴圈
                setInterval(() => {
                    const now = Date.now();
                    const fullTimeMs = (1320 / speedIndex) * 60000;
                    let updates = {};
                    let needAutoUnload = [];
                    
                    for(let id in cards) {
                        let c = cards[id];
                        if(c.status === 'on_line') {
                            let progress = (now - c.line_start_time) / fullTimeMs;
                            if (progress >= 1) {
                                progress = 1;
                                needAutoUnload.push(id);
                            }
                            updates[id] = progress;
                        }
                    }
                    self.postMessage({ type: 'tick', updates: updates, needAutoUnload: needAutoUnload });
                }, 16); // ~60fps
            `;
            
            const blob = new Blob([workerCode], {type: 'application/javascript'});
            const animWorker = new Worker(URL.createObjectURL(blob));
            
            animWorker.onmessage = function(e) {
                if (e.data.type === 'tick') {
                    // 主執行緒只負責透過 D3/DOM API 抓取路徑點並賦予座標 (Worker 無法存取 DOM)
                    trackLength = track.getTotalLength();
                    const updates = e.data.updates;
                    
                    for (let id in updates) {
                        const progress = updates[id];
                        const point = track.getPointAtLength(progress * trackLength);
                        const cardEl = document.getElementById('card-' + id);
                        
                        if (cardEl && activeCardId !== id) {
                            const xPercent = (point.x / 1000) * 100;
                            const yPercent = (point.y / 1333) * 100;
                            cardEl.style.left = xPercent + '%';
                            cardEl.style.top = yPercent + '%';
                        }
                    }

                    // 處理跑完自動下料
                    e.data.needAutoUnload.forEach(id => {
                        socket.emit('auto_move_to_unload', id);
                    });
                }
            };

            // 建立與移除 DOM 元素 (避免每幀重新建立)
            function syncDOMCards() {
                const currentIds = new Set(Object.values(sys_state_cache.cards).filter(c => c.status === 'on_line').map(c => c.id));
                
                // 移除不在線上的卡片
                document.querySelectorAll('.mini-card').forEach(el => {
                    const id = el.id.replace('card-', '');
                    if (!currentIds.has(id)) el.remove();
                });

                // 新增或更新卡片
                Object.values(sys_state_cache.cards).forEach(card => {
                    if (card.status === 'on_line') {
                        let div = document.getElementById('card-' + card.id);
                        if (!div) {
                            div = document.createElement('div');
                            div.id = 'card-' + card.id;
                            div.className = 'mini-card';
                            div.onclick = (e) => toggleCard(card.id, e);
                            mapContainer.appendChild(div);
                        }

                        if (activeCardId === card.id) {
                            div.classList.add('expanded');
                            div.style.left = '50%';
                            div.style.top = '50%';
                        } else {
                            div.classList.remove('expanded');
                        }

                        div.style.backgroundColor = card.colorCode || '#333';
                        let shortLabel = t('comp_lbl');
                        if (card.part_name && !card.part_name.startsWith('data:image') && card.part_name !== '未填寫') {
                            shortLabel = card.part_name;
                        } else if (card.part_no && !card.part_no.startsWith('data:image') && card.part_no !== '未填寫') {
                            shortLabel = card.part_no;
                        }
                        
                        div.innerHTML = `
                            <span class="short-id">${shortLabel}</span>
                            <div class="details">
                                <div class="close-btn" onclick="toggleCard('${card.id}', event)">×</div>
                                <b>${t('part_no')}</b> ${renderField(card.part_no)}<br>
                                <b>${t('part_name')}</b> ${renderField(card.part_name)}<br>
                                <b>機/櫃:</b> ${renderField(card.model_no) || '未填寫'}<br>
                                <b>${t('qty')}</b> ${card.qty}<br>
                                <b>${t('color')}</b> ${card.color}
                            </div>
                        `;
                    }
                });
            }

            setInterval(() => { socket.emit('request_sync'); }, 2000);
        </script>
    </body>
    </html>
    """

    # --- 其他頁面保持簡潔替換 ---
    wait_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>現場待料區</title>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.7.2/socket.io.js"></script>
            <style>
                body { font-family: '微軟正黑體', sans-serif; background: #e9ecef; padding: 20px; margin: 0; padding-bottom: 150px;}
                .form-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
                .input-group { display: flex; gap: 5px; margin: 5px 0; align-items: center; }
                select, input, button { padding: 10px; width: 100%; box-sizing: border-box; border-radius: 4px; border: 1px solid #ccc; font-size: 16px; }
                .btn-scan { background: #6c757d; color: white; width: 80px; flex-shrink: 0; font-weight: bold; cursor: pointer; padding: 5px;}
                .btn-add { background: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; margin-top:10px; }
                .data-card { background: white; padding: 10px; border-radius: 8px; margin-bottom: 10px; border-left: 10px solid #ccc; display: flex; justify-content: space-between; align-items: center;}
                .btn-send { background: #007bff; color: white; width: auto; padding: 8px; font-weight:bold;}
                .btn-del { background: #dc3545; color: white; width: auto; padding: 8px; font-weight:bold;}
                .fixed-bottom { position: fixed; bottom: 0; left: 0; right: 0; height: 120px; background: #343a40; color: white; padding: 10px; overflow-y: auto; z-index: 50;}
                
                #scannerModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 999; flex-direction: column; align-items: center; justify-content: center; }
                .video-container { position: relative; width: 90%; max-width: 500px; aspect-ratio: 4/3; overflow: hidden; border: 2px solid #fff; border-radius: 10px;}
                video { width: 100%; height: 100%; object-fit: cover; }
                .scan-box { position: absolute; top: 35%; left: 10%; width: 80%; height: 30%; border: 2px solid #00ff00; box-shadow: 0 0 0 9999px rgba(0,0,0,0.6); pointer-events: none; }
                .scan-controls { margin-top: 20px; display: flex; gap: 15px; }
                .scan-controls button { width: auto; padding: 12px 25px; font-weight: bold; font-size: 16px; border: none;}
            </style>
            __I18N_SCRIPT__
        </head>
        <body>
            <h2 data-i18n="wait_title">📦 現場待料區</h2>
            <div class="form-card" id="inputForm">
                <select id="colorSelect" onchange="previewColor()">
                    <option value="WE白色,#FFFFFF">WE白色</option>
                    <option value="BK黑平光,#333333">BK黑平光</option>
                    <option value="RD紅色,#FF0000">RD紅色</option>
                    <option value="GN綠色,#008000">GN綠色</option>
                    <option value="BE藍色,#0000FF">BE藍色</option>
                    <option value="IG灰色,#808080">IG灰色</option>
                    <option value="GSAT黃金色,#FFD700">GSAT黃金色</option>
                </select>
                <div class="input-group" id="partNoGroup">
                    <input type="text" id="partNo" data-i18n="part_ph" placeholder="手動輸入或掃描料號"> 
                    <button type="button" class="btn-scan" data-i18n="scan_btn" onclick="openScanner('partNo')">📷 掃描</button>
                </div>
                <div class="input-group" id="partNameGroup">
                    <input type="text" id="partName" data-i18n="name_ph" placeholder="手動輸入或掃描品名">
                    <button type="button" class="btn-scan" data-i18n="scan_btn" onclick="openScanner('partName')">📷 掃描</button>
                </div>
                <div class="input-group" id="modelNoGroup">
                    <input type="text" id="modelNo" placeholder="機種第幾櫃 (手動輸入 / Nhập model)">
                </div>
                <input type="number" id="qty" placeholder="數量 / Số lượng">
                <button class="btn-add" data-i18n="add_btn" onclick="createCard()">➕ 新增待上線構件</button>
            </div>
            <hr>
            <h3 data-i18n="list_wait">待處理清單 (順序排列)</h3>
            <div id="waiting-list"></div>
            <div class="fixed-bottom">
                <h4 data-i18n="ali_sync" style="margin-top:5px;">[待上料_阿利] 同步接收區</h4>
                <div id="ali-list"></div>
            </div>

            <script>
                const socket = io();
                let sys_state_cache = {cards:{}};
                let scanData = { partNo: '', partName: '', modelNo: '' }; 
                
                function previewColor() {
                    const val = document.getElementById('colorSelect').value.split(',');
                    document.getElementById('inputForm').style.backgroundColor = val[1];
                }

                function createCard() {
                    const colorData = document.getElementById('colorSelect').value.split(',');
                    const data = {
                        id: Date.now().toString(),
                        color: colorData[0],
                        colorCode: colorData[1],
                        part_no: scanData.partNo || document.getElementById('partNo').value || '未填寫',
                        part_name: scanData.partName || document.getElementById('partName').value || '未填寫',
                        model_no: scanData.modelNo || document.getElementById('modelNo').value || '', 
                        qty: document.getElementById('qty').value || 0,
                        status: 'waiting'
                    };
                    socket.emit('add_card', data);
                    
                    document.getElementById('partNo').value = '';
                    document.getElementById('partName').value = '';
                    document.getElementById('qty').value = '';
                }

                function renderDynamic() {
                    const waitList = document.getElementById('waiting-list');
                    const aliList = document.getElementById('ali-list');
                    waitList.innerHTML = ''; aliList.innerHTML = '';

                    Object.values(sys_state_cache.cards).forEach(card => {
                        const div = document.createElement('div');
                        div.className = 'data-card';
                        div.style.borderColor = card.colorCode;
                        let modelInfo = card.model_no ? ` [${renderField(card.model_no)}]` : '';
                        div.innerHTML = `
                            <div><b>${renderField(card.part_no)}</b> (${renderField(card.part_name)})${modelInfo} - ${card.color} x ${card.qty}</div>
                            <div style="display:flex; gap:5px;">
                                ${card.status === 'waiting' ? 
                                    `<button class="btn-send" onclick="sendToAli('${card.id}')">${t('btn_send_ali')}</button>
                                     <button class="btn-del" onclick="delCard('${card.id}')">${t('btn_del')}</button>` : ''}
                            </div>
                        `;
                        if(card.status === 'waiting') { waitList.appendChild(div); }
                        if(card.status === 'loading') { aliList.appendChild(div.cloneNode(true)); }
                    });
                }

                socket.on('update_state', (state) => { sys_state_cache = state; renderDynamic(); });
                function sendToAli(id) { socket.emit('change_status', {id: id, status: 'loading'}); }
                function delCard(id) { if(confirm(t('alert_del'))) socket.emit('delete_card', id); }
            </script>
        </body>
        </html>
    """

    load_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>待上料_阿利</title>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.7.2/socket.io.js"></script>
            <style>
                body { font-family: '微軟正黑體', sans-serif; background: #e2e8f0; padding: 20px; padding-bottom: 150px;}
                .card { background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 10px solid #ccc;}
                .grid-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 10px; }
                select, button { padding: 8px; width: 100%; box-sizing: border-box; }
                .btn-line { background: #ff9800; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-weight:bold;}
                .fixed-bottom { position: fixed; bottom: 0; left: 0; right: 0; height: 80px; background: #2c3e50; color: white; padding: 15px; text-align: center; font-size: 18px; font-weight: bold; z-index:50;}
            </style>
            __I18N_SCRIPT__
        </head>
        <body>
            <h2 data-i18n="ali_title">🏗️ 待上料_阿利</h2>
            <div id="loading-list"></div>
            <div class="fixed-bottom" data-i18n="line_sync">⬇️ [流水線_上線] 同步傳送區 ⬇️</div>
            <script>
                const socket = io();
                let currentSpeed = 1100;
                let sys_state_cache = {cards:{}};

                function renderDynamic() {
                    const list = document.getElementById('loading-list');
                    list.innerHTML = '';
                    Object.values(sys_state_cache.cards).forEach(card => {
                        if(card.status === 'loading') {
                            const div = document.createElement('div');
                            div.className = 'card'; div.style.borderColor = card.colorCode;
                            div.innerHTML = `
                                <strong>${t('part_no')} ${renderField(card.part_no)} | ${t('qty')} ${card.qty} | ${t('color')} ${card.color}</strong>
                                <div class="grid-form">
                                    <label>${t('lbl_hang')} <select id="hang_${card.id}"><option value="1">1</option><option value="2">2</option></select></label>
                                    <label>${t('lbl_empty')} <select id="empty_${card.id}">${genOptions(10)}</select></label>
                                    <label>${t('lbl_space')} <select id="interval_${card.id}">${genOptions(10)}</select></label>
                                    <label>${t('lbl_hook')} <select id="hook_${card.id}">${genOptions(10)}</select></label>
                                </div>
                                <button class="btn-line" onclick="sendToLine('${card.id}')">${t('btn_to_line')}</button>
                            `;
                            list.appendChild(div);
                        }
                    });
                }
                socket.on('update_state', (state) => { currentSpeed = state.line_speed; sys_state_cache = state; renderDynamic(); });
                function genOptions(max) { let h = ''; for(let i=0; i<=max; i++) h += `<option value="${i}">${i}</option>`; return h; }
                function sendToLine(id) {
                    socket.emit('send_to_line', { 
                        id: id, 
                        hang: parseInt(document.getElementById('hang_'+id).value)||1, 
                        empty: parseInt(document.getElementById('empty_'+id).value)||0, 
                        interval: parseInt(document.getElementById('interval_'+id).value)||0, 
                        hook: parseInt(document.getElementById('hook_'+id).value)||0 
                    }); 
                }
            </script>
        </body>
        </html>
    """

    unload_html = """
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>下料_完成</title>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.7.2/socket.io.js"></script>
            <style>
                body { font-family: '微軟正黑體', sans-serif; background: #fdf2e9; padding: 20px;}
                .card { background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 10px solid #ccc; display: flex; justify-content: space-between; align-items: center;}
                .btn-done { background: #27ae60; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; font-weight:bold;}
                .done-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 14px; background: white;}
                .done-table th, .done-table td { border: 1px solid #dee2e6; padding: 12px 8px; }
                .done-table th { background: #34495e; color: white;}
            </style>
            __I18N_SCRIPT__
        </head>
        <body>
            <h2 data-i18n="unload_title">✅ 下料與完成紀錄區</h2>
            <h3 data-i18n="unload_wait">待下料區</h3>
            <div id="unload-list"></div>
            <hr>
            <h3 data-i18n="done_list">歷史完成紀錄</h3>
            <table class="done-table">
                <thead><tr><th>料號</th><th>品名</th><th>數量</th><th>顏色</th><th>完成時間</th></tr></thead>
                <tbody id="done-list"></tbody>
            </table>
            <script>
                const socket = io();
                socket.on('update_state', (state) => {
                    document.getElementById('unload-list').innerHTML = ''; 
                    document.getElementById('done-list').innerHTML = '';
                    Object.values(state.cards).forEach(card => {
                        if(card.status === 'unloading') {
                            const div = document.createElement('div');
                            div.className = 'card'; div.style.borderColor = card.colorCode;
                            div.innerHTML = `<div><b>${renderField(card.part_no)}</b> - ${card.color} x ${card.qty}</div><button class="btn-done" onclick="finishCard('${card.id}')">${t('btn_done')}</button>`;
                            document.getElementById('unload-list').appendChild(div);
                        } else if(card.status === 'completed') {
                            document.getElementById('done-list').innerHTML += `<tr><td>${renderField(card.part_no)}</td><td>${renderField(card.part_name)}</td><td>${card.qty}</td><td>${card.color}</td><td>${card.finish_time}</td></tr>`;
                        }
                    });
                });
                function finishCard(id) { socket.emit('finish_card', id); }
            </script>
        </body>
        </html>
    """

    html_files['simulator.html'] = sim_html.replace('__I18N_SCRIPT__', I18N_SCRIPT)
    html_files['waiting.html'] = wait_html.replace('__I18N_SCRIPT__', I18N_SCRIPT)
    html_files['loading.html'] = load_html.replace('__I18N_SCRIPT__', I18N_SCRIPT)
    html_files['unloading.html'] = unload_html.replace('__I18N_SCRIPT__', I18N_SCRIPT)

    for filename, content in html_files.items():
        with open(os.path.join('templates', filename), 'w', encoding='utf-8') as f:
            f.write(content)

@app.route('/')
def index(): return render_template('simulator.html')
@app.route('/wait')
def wait(): return render_template('waiting.html')
@app.route('/load')
def load(): return render_template('loading.html')
@app.route('/unload')
def unload(): return render_template('unloading.html')

@app.route('/14436.png')
def serve_image(): return send_from_directory('.', '14436.png')

def broadcast_state():
    socketio.emit('update_state', sys_state)

def upsert_card_to_db(card_data):
    if supabase_connected:
        try:
            supabase.table('cards').upsert(card_data).execute()
        except Exception as e:
            print(f"DB Error: {e}")

@socketio.on('connect')
def handle_connect():
    emit('update_state', sys_state)

@socketio.on('request_sync')
def handle_sync():
    emit('update_state', sys_state)

@socketio.on('change_speed')
def handle_speed(val):
    sys_state['line_speed'] = max(900, min(1400, sys_state['line_speed'] + val))
    if supabase_connected:
        try:
            supabase.table('sys_settings').upsert({'key': 'line_speed', 'value': str(sys_state['line_speed'])}).execute()
        except: pass
    broadcast_state()

@socketio.on('add_card')
def add_card(data):
    sys_state['cards'][data['id']] = data
    upsert_card_to_db(data)
    broadcast_state()

@socketio.on('delete_card')
def delete_card(card_id):
    if card_id in sys_state['cards']:
        del sys_state['cards'][card_id]
        if supabase_connected:
            try: supabase.table('cards').delete().eq('id', card_id).execute()
            except: pass
        broadcast_state()

@socketio.on('change_status')
def change_status(data):
    card_id = data['id']
    if card_id in sys_state['cards']:
        sys_state['cards'][card_id]['status'] = data['status']
        upsert_card_to_db(sys_state['cards'][card_id])
        broadcast_state()

@socketio.on('send_to_line')
def send_to_line(data):
    global current_active_card_template
    card_id = data.get('id')
    if card_id in sys_state['cards']:
        card = sys_state['cards'][card_id]
        card.update({
            'status': 'on_line',
            'line_start_time': int(time.time() * 1000),
            'hang': data.get('hang', 1),
            'empty': data.get('empty', 0),
            'interval': data.get('interval', 0),
            'hook': data.get('hook', 0)
        })
        sys_state['active_card_id'] = card_id
        upsert_card_to_db(card)
        with active_card_lock:
            current_active_card_template = card.copy()
        broadcast_state()

@socketio.on('auto_move_to_unload')
def auto_unload(card_id):
    if card_id in sys_state['cards'] and sys_state['cards'][card_id]['status'] == 'on_line':
        sys_state['cards'][card_id]['status'] = 'unloading'
        upsert_card_to_db(sys_state['cards'][card_id])
        broadcast_state()

@socketio.on('finish_card')
def finish_card(card_id):
    if card_id in sys_state['cards']:
        sys_state['cards'][card_id]['status'] = 'completed'
        tw_time = time.gmtime(time.time() + 8 * 3600)
        sys_state['cards'][card_id]['finish_time'] = time.strftime("%Y-%m-%d %H:%M:%S", tw_time)
        upsert_card_to_db(sys_state['cards'][card_id])
        broadcast_state()

def continuous_line_inserter():
    global clone_counter, current_active_card_template
    while True:
        time.sleep(1)
        if current_active_card_template:
            with active_card_lock:
                card_template = current_active_card_template.copy()
            
            speed_index = max(1.0, sys_state.get('line_speed', 1100) / 100.0)
            total_hooks = max(1, card_template.get('hang', 1) + card_template.get('empty', 0) + card_template.get('interval', 0))
            
            base_line_time_min = 1320.0 / speed_index
            visual_gap_sec = base_line_time_min * 60.0 * 0.012
            hook_time_sec = total_hooks * (60.0 / speed_index)
            delay_sec = max(visual_gap_sec, hook_time_sec)
            
            slept = 0.0
            target_card_id = card_template.get('id')
            while slept < delay_sec:
                time.sleep(0.5)
                slept += 0.5
                if not current_active_card_template or current_active_card_template.get('id') != target_card_id:
                    break
                    
            if current_active_card_template and current_active_card_template.get('id') == target_card_id:
                clone_counter += 1
                now_ms = int(time.time() * 1000)
                clone_id = f"{target_card_id}_clone_{clone_counter}_{now_ms}"
                
                clone_card = card_template.copy()
                clone_card['id'] = clone_id
                clone_card['status'] = 'on_line'
                clone_card['line_start_time'] = now_ms
                clone_card['is_clone'] = True
                
                sys_state['cards'][clone_id] = clone_card
                upsert_card_to_db(clone_card)
                broadcast_state()

inserter_thread = threading.Thread(target=continuous_line_inserter, daemon=True)
inserter_thread.start()

if __name__ == '__main__':
    import shutil
    if os.path.exists('templates'):
        shutil.rmtree('templates')
    
    create_templates()
    port = int(os.environ.get('PORT', 5000))
    socketio.run(app, host='0.0.0.0', port=port, allow_unsafe_werkzeug=True)