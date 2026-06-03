<?php
// ==================== 後端資料處理邏輯 ====================
define('DATA_FILE', 'powder_data.json');
define('PASSWORD', 'M0282');

// 初始化資料結構
function get_initial_data() {
    return [
        'registry' => [],
        'inventory' => [],
        'purchase_logs' => [],
        'usage_logs' => [],
        'waste_logs' => []
    ];
}

// 讀取資料
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode(get_initial_data(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
$data = json_decode(file_get_contents(DATA_FILE), true);

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'save_all') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            file_put_contents(DATA_FILE, json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '無效的資料']);
        }
        exit;
    }
    if ($action === 'clear_all') {
        file_put_contents(DATA_FILE, json_encode(get_initial_data(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>烤漆部粉體系統</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #3498db;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --bg-color: #f5f7fa;
            --card-bg: #ffffff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--bg-color); color: #333; padding: 10px; padding-bottom: 30px; }

        /* 首頁美化 */
        .home-title {
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 25px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(45deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .menu-list {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            max-width: 500px;
            margin: 0 auto;
        }

        .menu-item {
            width: 100%;
            font-size: 26px;
            padding: 15px;
            text-align: center;
            background: var(--card-bg);
            border: 2px solid var(--primary-color);
            border-radius: 12px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .menu-item:active { transform: scale(0.98); background-color: #eaedd1; }
        .menu-item.danger { border-color: var(--danger-color); color: var(--danger-color); }

        /* 區塊框架 */
        .page-section { display: none; background: var(--card-bg); border-radius: 16px; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); max-width: 600px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .page-header h2 { font-size: 22px; color: var(--primary-color); }
        
        /* 表單元素手機優化 */
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .form-group label { font-size: 16px; font-weight: bold; margin-bottom: 5px; color: #555; }
        .form-control { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 8px; background-color: #fff; -webkit-appearance: none; }
        .form-control:disabled { background-color: #e9ecef; color: #495057; }
        
        /* 按鈕優化 */
        .btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .btn { padding: 12px; font-size: 16px; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; text-align: center; color: white; }
        .btn-primary { background-color: var(--accent-color); }
        .btn-success { background-color: var(--success-color); }
        .btn-danger { background-color: var(--danger-color); }
        .btn-secondary { background-color: #7f8c8d; }
        .btn-full { grid-column: span 2; }

        /* 檔案與CSV功能區 */
        .csv-zone { background: #f8f9fa; border: 1px dashed #ccc; border-radius: 8px; padding: 12px; margin-top: 20px; }
        .csv-zone h4 { font-size: 14px; margin-bottom: 8px; color: #666; }
        .file-input-wrapper { margin-bottom: 10px; }

        /* 狀態樣式 */
        .safety-alert { background-color: var(--danger-color) !important; color: white !important; font-weight: bold; }
        .bold-red { color: var(--danger-color); font-weight: bold; }
        .hidden-row { display: none !important; }

        /* 獨立框架/查詢結果樣式 */
        .query-result { font-size: 18px; margin-top: 15px; padding: 15px; background: #f0f4f8; border-radius: 8px; line-height: 1.6; }
        .history-list { margin-top: 15px; max-height: 250px; overflow-y: auto; font-size: 14px; }
        .history-item { padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        
        /* 表格響應式 */
        .table-container { width: 100%; overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        tr.selected { background-color: #d1ecf1; }
    </style>
</head>
<body>

    <div id="home-page">
        <h1 class="home-title">烤漆部粉體系統</h1>
        <div class="menu-list">
            <div class="menu-item" onclick="enterSection(1)">1. 烤漆粉號清冊</div>
            <div class="menu-item" onclick="enterSection(2)">2. 庫存監控</div>
            <div class="menu-item" onclick="enterSection(3)">3. 採購入庫</div>
            <div class="menu-item" onclick="enterSection(4)">4. 用量紀錄</div>
            <div class="menu-item" onclick="enterSection(5, true)">5. 用量紀錄查詢</div>
            <div class="menu-item" onclick="enterSection(6, true)">6. 庫存查詢</div>
            <div class="menu-item" onclick="enterSection(7, false, true)">7. 廢粉統計</div>
            <div class="menu-item danger" onclick="clearAllData()">8. 清除數據</div>
        </div>
    </div>

    <div id="section-1" class="page-section">
        <div class="page-header">
            <h2>1. 烤漆粉號清冊</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div class="form-group">
            <label>粉號：</label>
            <input type="text" id="r-powder-no" class="form-control">
        </div>
        <div class="form-group">
            <label>代號：</label>
            <input type="text" id="r-code" class="form-control">
        </div>
        <div class="form-group">
            <label>色系：</label>
            <input type="text" id="r-color" class="form-control">
        </div>
        <div class="form-group">
            <label>淨重：</label>
            <select id="r-net-weight" class="form-control">
                <option value="20">20</option>
                <option value="25">25</option>
            </select>
        </div>
        <div class="form-group">
            <label>廠商：</label>
            <select id="r-vendor" class="form-control">
                <option value="老虎">老虎</option>
                <option value="國麗">國麗</option>
                <option value="國邦">國邦</option>
                <option value="台粉">台粉</option>
                <option value="長誠">長誠</option>
                <option value="高頻">高頻</option>
                <option value="南寶">南寶</option>
            </select>
        </div>
        <div class="btn-group">
            <button class="btn btn-success" onclick="addRegistry()">新增/修改紀錄</button>
            <button class="btn btn-danger" onclick="deleteRegistry()">下架/刪除紀錄</button>
            <button class="btn btn-secondary btn-full" id="btn-toggle-archive-1" onclick="toggleArchive(1)">顯示已下架紀錄</button>
        </div>

        <div class="table-container">
            <table id="registry-table">
                <thead>
                    <tr><th>粉號</th><th>代號</th><th>色系</th><th>淨重</th><th>廠商</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="csv-zone">
            <h4>CSV 匯入/匯出</h4>
            <div class="file-input-wrapper">
                <input type="file" id="csv-file-1" accept=".csv">
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="importCSV(1)">匯入紀錄csv</button>
                <button class="btn btn-primary" onclick="exportCSV(1)">匯出紀錄csv</button>
            </div>
        </div>
    </div>

    <div id="section-2" class="page-section">
        <div class="page-header">
            <h2>2. 庫存監控</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div class="form-group">
            <label>粉號：</label>
            <select id="m-powder-no" class="form-control" onchange="syncMonitorFields()"></select>
        </div>
        <div class="form-group">
            <label>代號：</label>
            <input type="text" id="m-code" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>淨重：</label>
            <input type="number" id="m-net-weight" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>箱數 (盤點寫入)：</label>
            <input type="number" id="m-boxes" class="form-control" oninput="calcMonitorTotal()">
        </div>
        <div class="form-group">
            <label>散粉 (盤點寫入)：</label>
            <input type="number" id="m-loose" class="form-control" oninput="calcMonitorTotal()">
        </div>
        <div class="form-group">
            <label>總數量 (公斤)：</label>
            <input type="text" id="m-total" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>安全庫存量設定：</label>
            <input type="number" id="m-safety" class="form-control" value="100" oninput="calcMonitorTotal()">
        </div>
        <div class="btn-group">
            <button class="btn btn-success" onclick="saveMonitor()">儲存更新盤點</button>
            <button class="btn btn-danger" onclick="clearMonitorForm()">清除資料</button>
            <button class="btn btn-secondary btn-full" id="btn-toggle-archive-2" onclick="toggleArchive(2)">顯示已下架紀錄</button>
        </div>

        <div class="csv-zone">
            <h4>CSV 匯入/匯出</h4>
            <div class="file-input-wrapper">
                <input type="file" id="csv-file-2" accept=".csv">
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="importCSV(2)">匯入紀錄csv</button>
                <button class="btn btn-primary" onclick="exportCSV(2)">匯出紀錄csv</button>
            </div>
        </div>
    </div>

    <div id="section-3" class="page-section">
        <div class="page-header">
            <h2>3. 採購入庫</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div class="form-group">
            <label>日期：</label>
            <input type="date" id="p-date" class="form-control">
        </div>
        <div class="form-group">
            <label>粉號：</label>
            <select id="p-powder-no" class="form-control" onchange="syncPurchaseFields()"></select>
        </div>
        <div class="form-group">
            <label>代號：</label>
            <input type="text" id="p-code" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>色系：</label>
            <input type="text" id="p-color" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>淨重：</label>
            <input type="number" id="p-net-weight" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>入庫箱數 (加回)：</label>
            <input type="number" id="p-boxes" class="form-control" oninput="calcPurchaseTotal()" value="0">
        </div>
        <div class="form-group">
            <label>入庫散粉 (加回)：</label>
            <input type="number" id="p-loose" class="form-control" oninput="calcPurchaseTotal()" value="0">
        </div>
        <div class="form-group">
            <label>總數量：</label>
            <input type="text" id="p-total" class="form-control" disabled>
        </div>
        <button class="btn btn-success btn-full" onclick="savePurchase()">確認入庫變更</button>

        <div class="history-list" id="purchase-history"></div>

        <div class="csv-zone">
            <h4>CSV 匯入/匯出</h4>
            <div class="file-input-wrapper">
                <input type="file" id="csv-file-3" accept=".csv">
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="importCSV(3)">匯入紀錄csv</button>
                <button class="btn btn-primary" onclick="exportCSV(3)">匯出紀錄csv</button>
            </div>
        </div>
    </div>

    <div id="section-4" class="page-section">
        <div class="page-header">
            <h2>4. 用量紀錄</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div class="form-group">
            <label>日期：</label>
            <input type="date" id="u-date" class="form-control">
        </div>
        <div class="form-group">
            <label>粉號：</label>
            <select id="u-powder-no" class="form-control" onchange="syncUsageFields()"></select>
        </div>
        <div class="form-group">
            <label>代號：</label>
            <input type="text" id="u-code" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>色系：</label>
            <input type="text" id="u-color" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>淨重：</label>
            <input type="number" id="u-net-weight" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>目前現有庫存 (參考)：</label>
            <input type="text" id="u-current-stock" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>領用箱數 (扣除)：</label>
            <input type="number" id="u-boxes" class="form-control" oninput="calcUsageTotal()" value="0">
        </div>
        <div class="form-group">
            <label>領用散粉 (扣除)：</label>
            <input type="number" id="u-loose" class="form-control" oninput="calcUsageTotal()" value="0">
        </div>
        <div class="form-group">
            <label>出庫總重量 (淨重×箱數+散粉)：</label>
            <input type="text" id="u-total-weight" class="form-control" disabled>
        </div>
        <div class="form-group">
            <label>回庫散粉 (自動加回庫存)：</label>
            <input type="number" id="u-return" class="form-control" oninput="calcUsageTotal()" value="0">
        </div>
        <div class="form-group">
            <label>實際用量 (出庫總重 - 回庫)：</label>
            <div id="u-usage-display" class="bold-red" style="font-size: 22px; padding: 5px 0;">0</div>
        </div>
        <button class="btn btn-success btn-full" onclick="saveUsage()">確認領用扣庫</button>

        <div class="history-list" id="usage-history"></div>

        <div class="csv-zone">
            <h4>CSV 匯入/匯出</h4>
            <div class="file-input-wrapper">
                <input type="file" id="csv-file-4" accept=".csv">
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="importCSV(4)">匯入紀錄csv</button>
                <button class="btn btn-primary" onclick="exportCSV(4)">匯出紀錄csv</button>
            </div>
        </div>
    </div>

    <div id="section-5" class="page-section">
        <div class="page-header">
            <h2>5. 用量紀錄查詢</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div class="form-group">
            <label>選擇查詢日期：</label>
            <select id="q5-date" class="form-control" onchange="queryUsageByDate()"></select>
        </div>
        <div id="q5-result-container" class="table-container">
            <table>
                <thead>
                    <tr><th>粉號</th><th>代號</th><th>色系</th><th>出庫重</th><th>回庫</th><th>實際用量</th></tr>
                </thead>
                <tbody id="q5-table-body"></tbody>
            </table>
        </div>
    </div>

    <div id="section-6" class="page-section">
        <div class="page-header">
            <h2>6. 庫存查詢</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div class="form-group">
            <label>選取粉號＆代號查詢：</label>
            <select id="q6-select" class="form-control" onchange="queryInventoryStock()"></select>
        </div>
        <div id="q6-result" class="query-result">
            請選擇上方品項進行即時查詢。
        </div>
    </div>

    <div id="section-7" class="page-section">
        <div class="page-header">
            <h2>7. 廢粉統計</h2>
            <button class="btn btn-secondary" onclick="goHome()">返回</button>
        </div>
        <div id="w-write-fields">
            <div class="form-group">
                <label>日期：</label>
                <input type="date" id="w-date" class="form-control">
            </div>
            <div class="form-group">
                <label>數量 (桶/箱)：</label>
                <input type="number" id="w-qty" class="form-control" oninput="calcWasteTotal()">
            </div>
            <div class="form-group">
                <label>單位淨重 (固定25)：</label>
                <input type="number" id="w-net" class="form-control" value="25" disabled>
            </div>
            <div class="form-group">
                <label>當日總重：</label>
                <input type="text" id="w-total" class="form-control" disabled>
            </div>
            <button class="btn btn-success btn-full" id="w-save-btn" onclick="saveWaste()">紀錄流水帳</button>
        </div>

        <hr style="margin: 20px 0; border: 1px solid #eee;">
        <h3>統計與預測平均分析</h3>
        <div class="query-result" style="font-size: 16px;">
            <div>天平均重量：<span id="avg-day">0</span> kg</div>
            <div>周平均重量：<span id="avg-week">0</span> kg</div>
            <div>月平均重量：<span id="avg-month">0</span> kg</div>
            <div>年平均重量：<span id="avg-year">0</span> kg</div>
            <hr style="margin: 10px 0; border-style: dashed; border-color: #ccc;">
            <div id="waste-index-box">
                廢粉回收指數：<br>
                進度百分比：<span id="waste-percent">0%</span><br>
                目前總噸數：<span id="waste-tons">0</span> 噸
                <div id="waste-alert-msg" style="margin-top: 5px;"></div>
            </div>
        </div>
        <button class="btn btn-danger btn-full" style="margin-top:10px;" onclick="resetWasteIndex()">重置統計計算</button>
    </div>

    <script>
        // 從 PHP 讀取初始化資料
        let appData = <?php echo json_encode($data); ?>;
        const DEFAULT_PWD = "<?php echo PASSWORD; ?>";
        let isViewOnly = false;
        let showArchivedRegistry = false;
        let showArchivedMonitor = false;
        let selectedRegistryIndex = null;

        // 頁面切換控制與密碼驗證循環
        function enterSection(sectionNum, ignorePassword = false, isWastePage = false) {
            if (ignorePassword) {
                // 獨立框架，直接進入
                isViewOnly = true;
                openSectionView(sectionNum);
                return;
            }

            if (isWastePage) {
                // 廢粉統計特殊密碼驗證
                let actionChoice = confirm("按「確定」輸入密碼解鎖寫入權限；\n按「取消」直接忽略密碼進入檢視模式。");
                if (actionChoice) {
                    if (verifyPasswordLoop()) {
                        isViewOnly = false;
                        document.getElementById('w-write-fields').style.display = "block";
                        openSectionView(sectionNum);
                    }
                } else {
                    isViewOnly = true;
                    document.getElementById('w-write-fields').style.display = "none";
                    openSectionView(sectionNum);
                }
                return;
            }

            // 一般限定密碼頁面
            if (verifyPasswordLoop()) {
                isViewOnly = false;
                openSectionView(sectionNum);
            }
        }

        function verifyPasswordLoop() {
            let pwd = "";
            while (pwd !== DEFAULT_PWD) {
                pwd = prompt("請輸入操作權限密碼：");
                if (pwd === null) return false; // 使用者按取消
                if (pwd !== DEFAULT_PWD) {
                    alert("密碼錯誤！請重新輸入。");
                }
            }
            return true;
        }

        function openSectionView(num) {
            document.getElementById('home-page').style.display = 'none';
            // 隱藏所有區塊
            for(let i=1; i<=7; i++) {
                document.getElementById(`section-${i}`).style.display = 'none';
            }
            // 顯示目標區塊
            document.getElementById(`section-${num}`).style.display = 'block';
            
            // 觸發各區塊初始化渲染
            initSectionData(num);
            window.scrollTo(0,0);
        }

        function goHome() {
            document.getElementById('home-page').style.display = 'block';
            for(let i=1; i<=7; i++) {
                document.getElementById(`section-${i}`).style.display = 'none';
            }
        }

        // 後端資料同步
        function sysBackendSave() {
            fetch('?action=save_all', {
                method: 'POST',
                headers: { 'Content-Type:': 'application/json' },
                body: JSON.stringify(appData)
            })
            .then(res => res.json())
            .then(data => {
                if(data.status !== 'success') alert('資料同步伺服器失敗！');
            });
        }

        function clearAllData() {
            if(verifyPasswordLoop()) {
                if(confirm("確定要清除整套系統所有模組的數據嗎？此操作無法還原！")) {
                    fetch('?action=clear_all', { method: 'POST' })
                    .then(res => res.json())
                    .then(resData => {
                        if(resData.status === 'success') {
                            alert('所有數據已完全清空重置。');
                            location.reload();
                        }
                    });
                }
            }
        }

        // ==================== 各單元核心渲染與連動商業邏輯 ====================
        
        function initSectionData(num) {
            // 自動初始化目前各欄位的日期預設值
            let today = new Date().toISOString().split('T')[0];
            if(document.getElementById('p-date')) document.getElementById('p-date').value = today;
            if(document.getElementById('u-date')) document.getElementById('u-date').value = today;
            if(document.getElementById('w-date')) document.getElementById('w-date').value = today;

            if (num === 1) renderRegistryTable();
            if (num === 2) updateMonitorSelectOptions();
            if (num === 3) updatePurchaseSelectOptions();
            if (num === 4) updateUsageSelectOptions();
            if (num === 5) updateQuery5SelectOptions();
            if (num === 6) updateQuery6SelectOptions();
            if (num === 7) calculateWasteStats();
        }

        // ---------- 1. 烤漆粉號清冊模組 ----------
        function renderRegistryTable() {
            const tbody = document.querySelector('#registry-table tbody');
            tbody.innerHTML = '';
            appData.registry.forEach((item, index) => {
                if (!showArchivedRegistry && item.is_archived) return;
                
                let tr = document.createElement('tr');
                if(item.is_archived) tr.style.opacity = '0.4';
                tr.innerHTML = `<td>${item.powder_no}</td><td>${item.code}</td><td>${item.color}</td><td>${item.net_weight}</td><td>${item.vendor}</td>`;
                tr.onclick = () => selectRegistryRow(index, tr);
                tbody.appendChild(tr);
            });
        }

        function selectRegistryRow(index, trElement) {
            selectedRegistryIndex = index;
            let item = appData.registry[index];
            document.getElementById('r-powder-no').value = item.powder_no;
            document.getElementById('r-code').value = item.code;
            document.getElementById('r-color').value = item.color;
            document.getElementById('r-net-weight').value = item.net_weight;
            document.getElementById('r-vendor').value = item.vendor;

            document.querySelectorAll('#registry-table tr').forEach(r => r.classList.remove('selected'));
            trElement.classList.add('selected');
        }

        function addRegistry() {
            let pNo = document.getElementById('r-powder-no').value.trim();
            let code = document.getElementById('r-code').value.trim();
            let color = document.getElementById('r-color').value.trim();
            let net = parseInt(document.getElementById('r-net-weight').value);
            let vendor = document.getElementById('r-vendor').value;

            if(!pNo || !code) { alert('請填寫粉號與代號'); return; }

            let existingIdx = appData.registry.findIndex(i => i.powder_no === pNo);
            if (existingIdx > -1) {
                // 修改現有紀錄
                appData.registry[existingIdx] = { powder_no: pNo, code: code, color: color, net_weight: net, vendor: vendor, is_archived: false };
            } else {
                // 新增紀錄
                appData.registry.push({ powder_no: pNo, code: code, color: color, net_weight: net, vendor: vendor, is_archived: false });
                // 同步自動在監控模組建立對應空庫存
                if(!appData.inventory[pNo]) {
                    appData.inventory[pNo] = { loose: 0, boxes: 0, safety_stock: 100 };
                }
            }
            sysBackendSave();
            renderRegistryTable();
            clearRegistryForm();
        }

        function deleteRegistry() {
            let pNo = document.getElementById('r-powder-no').value.trim();
            if(!pNo) { alert('請選擇或輸入欲下架刪除的粉號項目'); return; }
            
            let idx = appData.registry.findIndex(i => i.powder_no === pNo);
            if(idx > -1) {
                if(confirm(`是否確認將粉號 [${pNo}] 標記為下架隱藏？`)) {
                    appData.registry[idx].is_archived = true;
                    sysBackendSave();
                    renderRegistryTable();
                    clearRegistryForm();
                }
            }
        }

        function toggleArchive(section) {
            if(section === 1) {
                showArchivedRegistry = !showArchivedRegistry;
                document.getElementById('btn-toggle-archive-1').innerText = showArchivedRegistry ? "隱藏已下架紀錄" : "顯示已下架紀錄";
                renderRegistryTable();
            } else if(section === 2) {
                showArchivedMonitor = !showArchivedMonitor;
                document.getElementById('btn-toggle-archive-2').innerText = showArchivedMonitor ? "隱藏已下架紀錄" : "顯示已下架紀錄";
                updateMonitorSelectOptions();
            }
        }

        function clearRegistryForm() {
            document.getElementById('r-powder-no').value = '';
            document.getElementById('r-code').value = '';
            document.getElementById('r-color').value = '';
            selectedRegistryIndex = null;
        }

        // ---------- 2. 庫存監控模組 ----------
        function updateMonitorSelectOptions() {
            let select = document.getElementById('m-powder-no');
            select.innerHTML = '<option value="">--請選擇粉號--</option>';
            appData.registry.forEach(item => {
                if (!showArchivedMonitor && item.is_archived) return;
                let opt = document.createElement('option');
                opt.value = item.powder_no;
                opt.innerText = item.powder_no + (item.is_archived ? ' (已下架)' : '');
                select.appendChild(opt);
            });
            clearMonitorForm();
        }

        function syncMonitorFields() {
            let pNo = document.getElementById('m-powder-no').value;
            if(!pNo) { clearMonitorForm(); return; }
            
            let regItem = appData.registry.find(i => i.powder_no === pNo);
            let invItem = appData.inventory[pNo] || { loose: 0, boxes: 0, safety_stock: 100 };

            document.getElementById('m-code').value = regItem ? regItem.code : '';
            document.getElementById('m-net-weight').value = regItem ? regItem.net_weight : 25;
            document.getElementById('m-boxes').value = invItem.boxes;
            document.getElementById('m-loose').value = invItem.loose;
            document.getElementById('m-safety').value = invItem.safety_stock;
            
            calcMonitorTotal();
        }

        function calcMonitorTotal() {
            let net = parseFloat(document.getElementById('m-net-weight').value) || 0;
            let boxes = parseFloat(document.getElementById('m-boxes').value) || 0;
            let loose = parseFloat(document.getElementById('m-loose').value) || 0;
            let safety = parseFloat(document.getElementById('m-safety').value) || 0;

            let total = (net * boxes) + loose;
            let totalInput = document.getElementById('m-total');
            totalInput.value = total.toFixed(2);

            // 安全庫存檢驗：低於預設值以紅底白字標示
            if (total < safety) {
                totalInput.classList.add('safety-alert');
            } else {
                totalInput.classList.remove('safety-alert');
            }
        }

        function saveMonitor() {
            let pNo = document.getElementById('m-powder-no').value;
            if(!pNo) { alert('請先選擇規格粉號'); return; }

            appData.inventory[pNo] = {
                boxes: parseFloat(document.getElementById('m-boxes').value) || 0,
                loose: parseFloat(document.getElementById('m-loose').value) || 0,
                safety_stock: parseFloat(document.getElementById('m-safety').value) || 0
            };

            sysBackendSave();
            alert('盤點監控庫存更新成功');
        }

        function clearMonitorForm() {
            document.getElementById('m-code').value = '';
            document.getElementById('m-net-weight').value = '';
            document.getElementById('m-boxes').value = '';
            document.getElementById('m-loose').value = '';
            document.getElementById('m-total').value = '';
            document.getElementById('m-total').classList.remove('safety-alert');
        }

        // ---------- 3. 採購入庫模組 ----------
        function updatePurchaseSelectOptions() {
            let select = document.getElementById('p-powder-no');
            select.innerHTML = '<option value="">--請選擇粉號--</option>';
            appData.registry.forEach(item => {
                if(item.is_archived) return; // 入庫過濾已下架
                let opt = document.createElement('option');
                opt.value = item.powder_no;
                opt.innerText = item.powder_no;
                select.appendChild(opt);
            });
            renderPurchaseHistory();
        }

        function syncPurchaseFields() {
            let pNo = document.getElementById('p-powder-no').value;
            let regItem = appData.registry.find(i => i.powder_no === pNo);
            if(!regItem) return;

            document.getElementById('p-code').value = regItem.code;
            document.getElementById('p-color').value = regItem.color;
            document.getElementById('p-net-weight').value = regItem.net_weight;
            calcPurchaseTotal();
        }

        function calcPurchaseTotal() {
            let net = parseFloat(document.getElementById('p-net-weight').value) || 0;
            let boxes = parseFloat(document.getElementById('p-boxes').value) || 0;
            let loose = parseFloat(document.getElementById('p-loose').value) || 0;
            document.getElementById('p-total').value = ((net * boxes) + loose).toFixed(2);
        }

        function savePurchase() {
            let pNo = document.getElementById('p-powder-no').value;
            let date = document.getElementById('p-date').value;
            let boxes = parseFloat(document.getElementById('p-boxes').value) || 0;
            let loose = parseFloat(document.getElementById('p-loose').value) || 0;
            let total = parseFloat(document.getElementById('p-total').value) || 0;

            if(!pNo || !date || total <= 0) { alert('請填寫完整正確的入庫單資訊！'); return; }

            // 1. 寫入入庫流水帳
            appData.purchase_logs.push({ date, powder_no: pNo, boxes, loose, total });

            // 2. 自動更新庫存監控
            if(!appData.inventory[pNo]) appData.inventory[pNo] = { boxes: 0, loose: 0, safety_stock: 100 };
            appData.inventory[pNo].boxes += boxes;
            appData.inventory[pNo].loose += loose;

            sysBackendSave();
            alert('入庫流水帳紀錄成功，庫存已即時加回更新！');
            
            document.getElementById('p-boxes').value = 0;
            document.getElementById('p-loose').value = 0;
            document.getElementById('p-total').value = 0;
            renderPurchaseHistory();
        }

        function renderPurchaseHistory() {
            let html = '<h4>最近5筆入庫流水帳：</h4>';
            let logs = appData.purchase_logs.slice(-5).reverse();
            logs.forEach(l => {
                html += `<div class="history-item"><span>${l.date} - ${l.powder_no}</span><span>+${l.boxes}箱 / +${l.loose}kg (共${l.total}kg)</span></div>`;
            });
            document.getElementById('purchase-history').innerHTML = html;
        }

        // ---------- 4. 用量紀錄模組 ----------
        function updateUsageSelectOptions() {
            let select = document.getElementById('u-powder-no');
            select.innerHTML = '<option value="">--請選擇粉號--</option>';
            appData.registry.forEach(item => {
                if(item.is_archived) return;
                let opt = document.createElement('option');
                opt.value = item.powder_no;
                opt.innerText = item.powder_no;
                select.appendChild(opt);
            });
            renderUsageHistory();
        }

        function syncUsageFields() {
            let pNo = document.getElementById('u-powder-no').value;
            let regItem = appData.registry.find(i => i.powder_no === pNo);
            if(!regItem) return;

            document.getElementById('u-code').value = regItem.code;
            document.getElementById('u-color').value = regItem.color;
            document.getElementById('u-net-weight').value = regItem.net_weight;

            // 帶出現有庫存量顯示
            let inv = appData.inventory[pNo] || { boxes: 0, loose: 0 };
            document.getElementById('u-current-stock').value = `${inv.boxes} 箱 / ${inv.loose} 公斤`;

            calcUsageTotal();
        }

        function calcUsageTotal() {
            let net = parseFloat(document.getElementById('u-net-weight').value) || 0;
            let boxes = parseFloat(document.getElementById('u-boxes').value) || 0;
            let loose = parseFloat(document.getElementById('u-loose').value) || 0;
            let ret = parseFloat(document.getElementById('u-return').value) || 0;

            let totalWeight = (net * boxes) + loose;
            document.getElementById('u-total-weight').value = totalWeight.toFixed(2);

            let realUsage = totalWeight - ret;
            document.getElementById('u-usage-display').innerText = realUsage.toFixed(2) + " 公斤";
        }

        function saveUsage() {
            let pNo = document.getElementById('u-powder-no').value;
            let date = document.getElementById('u-date').value;
            let boxes = parseFloat(document.getElementById('u-boxes').value) || 0;
            let loose = parseFloat(document.getElementById('u-loose').value) || 0;
            let ret = parseFloat(document.getElementById('u-return').value) || 0;
            
            let net = parseFloat(document.getElementById('u-net-weight').value) || 0;
            let totalWeight = (net * boxes) + loose;
            let usage = totalWeight - ret;

            if(!pNo || !date || totalWeight <= 0) { alert('請填寫完整正確的領用用量資訊！'); return; }

            // 檢查庫存量是否足夠扣除
            let inv = appData.inventory[pNo] || { boxes: 0, loose: 0, safety_stock: 100 };
            if(inv.boxes < boxes || inv.loose < loose) {
                if(!confirm("警告：扣除箱數/散粉超出目前庫存水位，是否仍要強行執行出庫？")) return;
            }

            // 1. 寫入流水帳
            appData.usage_logs.push({ date, powder_no: pNo, boxes, loose, return_stock: ret, usage });

            // 2. 自動更新扣除與加回庫存監控
            inv.boxes -= boxes;
            inv.loose = inv.loose - loose + ret; // 扣除散粉並自動加回回庫散粉
            appData.inventory[pNo] = inv;

            sysBackendSave();
            alert('用量扣庫與回庫數據處理成功！');

            document.getElementById('u-boxes').value = 0;
            document.getElementById('u-loose').value = 0;
            document.getElementById('u-return').value = 0;
            syncUsageFields();
            renderUsageHistory();
        }

        function renderUsageHistory() {
            let html = '<h4>最近5筆用量流水帳：</h4>';
            let logs = appData.usage_logs.slice(-5).reverse();
            logs.forEach(l => {
                html += `<div class="history-item"><span>${l.date} - ${l.powder_no}</span><span>扣${l.boxes}箱/扣${l.loose}kg (回庫${l.return_stock}kg) 用量:<b class="bold-red">${l.usage}kg</b></span></div>`;
            });
            document.getElementById('usage-history').innerHTML = html;
        }

        // ---------- 5. 用量紀錄查詢 (獨立框架) ----------
        function updateQuery5SelectOptions() {
            let select = document.getElementById('q5-date');
            select.innerHTML = '<option value="">--選擇日期--</option>';
            
            // 取出所有用量紀錄的不重複日期
            let dates = [...new Set(appData.usage_logs.map(l => l.date))].sort().reverse();
            dates.forEach(d => {
                let opt = document.createElement('option');
                opt.value = d;
                opt.innerText = d;
                select.appendChild(opt);
            });
            document.getElementById('q5-table-body').innerHTML = '';
        }

        function queryUsageByDate() {
            let targetDate = document.getElementById('q5-date').value;
            let tbody = document.getElementById('q5-table-body');
            tbody.innerHTML = '';

            if(!targetDate) return;

            let filtered = appData.usage_logs.filter(l => l.date === targetDate);
            filtered.forEach(l => {
                let reg = appData.registry.find(i => i.powder_no === l.powder_no) || { code: '-', color: '-' };
                let tr = document.createElement('tr');
                let outWeight = ((l.usage + l.return_stock)).toFixed(1);
                tr.innerHTML = `<td>${l.powder_no}</td><td>${reg.code}</td><td>${reg.color}</td><td>${outWeight}</td><td>${l.return_stock}</td><td class="bold-red">${l.usage}</td>`;
                tbody.appendChild(tr);
            });
        }

        // ---------- 6. 庫存查詢 (獨立框架) ----------
        function updateQuery6SelectOptions() {
            let select = document.getElementById('q6-select');
            select.innerHTML = '<option value="">--選擇品項代號--</option>';
            appData.registry.forEach(item => {
                let opt = document.createElement('option');
                opt.value = item.powder_no;
                opt.innerText = `代號:${item.code} ｜ 粉號:${item.powder_no}`;
                select.appendChild(opt);
            });
            document.getElementById('q6-result').innerHTML = '請選擇上方品項進行即時查詢。';
        }

        function queryInventoryStock() {
            let pNo = document.getElementById('q6-select').value;
            let div = document.getElementById('q6-result');
            if(!pNo) { div.innerHTML = ''; return; }

            let reg = appData.registry.find(i => i.powder_no === pNo);
            let inv = appData.inventory[pNo] || { boxes: 0, loose: 0, safety_stock: 100 };
            let totalWeight = ((reg.net_weight * inv.boxes) + inv.loose).toFixed(2);

            div.style.fontSize = "18px";
            div.innerHTML = `
                <div><strong>查詢粉號：</strong>${pNo}</div>
                <div><strong>工廠代號：</strong>${reg.code}</div>
                <div><strong>色彩規格：</strong>${reg.color}</div>
                <div><strong>現有狀態：</strong>${inv.boxes} 箱 零 └ ${inv.loose} 公斤</div>
                <div style="margin-top: 10px;"><strong>系統現存總庫存：</strong><span class="bold-red" style="font-size:24px;">${totalWeight}</span> kg</div>
            `;
        }

        // ---------- 7. 廢粉統計模組 ----------
        function calcWasteTotal() {
            let qty = parseFloat(document.getElementById('w-qty').value) || 0;
            document.getElementById('w-total').value = (qty * 25).toFixed(2);
        }

        function saveWaste() {
            let date = document.getElementById('w-date').value;
            let qty = parseFloat(document.getElementById('w-qty').value) || 0;
            let total = qty * 25;

            if(!date || qty <= 0) { alert('請填寫廢粉採集箱數量！'); return; }

            appData.waste_logs.push({ date, quantity: qty, total_weight: total });
            sysBackendSave();
            alert('廢粉回收量已登錄流水帳。');

            document.getElementById('w-qty').value = '';
            document.getElementById('w-total').value = '';
            calculateWasteStats();
        }

        function calculateWasteStats() {
            if(appData.waste_logs.length === 0) {
                clearWasteStatsDisplay();
                return;
            }

            // 計算加總
            let totalKg = appData.waste_logs.reduce((sum, log) => sum + log.total_weight, 0);
            
            // 計算天數跨度範圍
            let dates = appData.waste_logs.map(l => new Date(l.date).getTime());
            let minDate = Math.min(...dates);
            let maxDate = Math.max(...dates);
            let diffTime = Math.abs(maxDate - minDate);
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1; // 至少1天

            let avgDay = totalKg / diffDays;
            
            document.getElementById('avg-day').innerText = avgDay.toFixed(1);
            document.getElementById('avg-week').innerText = (avgDay * 7).toFixed(1);
            document.getElementById('avg-month').innerText = (avgDay * 30).toFixed(1);
            document.getElementById('avg-year').innerText = (avgDay * 365).toFixed(1);

            // 廢粉回收指數計算 (目標設定為 10 噸 = 10000 kg)
            const TARGET_KG = 10000;
            let currentTons = totalKg / 1000;
            let percent = (totalKg / TARGET_KG) * 100;

            document.getElementById('waste-percent').innerText = percent.toFixed(1) + "%";
            document.getElementById('waste-tons').innerText = currentTons.toFixed(3);

            // 預估一星期後的總重量是否達10噸
            let projectedOneWeekTotal = totalKg + (avgDay * 7);
            let alertBox = document.getElementById('waste-alert-msg');
            
            if (projectedOneWeekTotal >= TARGET_KG) {
                alertBox.innerHTML = `<span class="bold-red" style="font-size:18px;">⚠️ 警告：預估1週後廢粉累積將達 ${ (projectedOneWeekTotal/1000).toFixed(2) } 噸，請提前安排回收處置！</span>`;
            } else {
                alertBox.innerHTML = `<span style="color:green;">目前累積狀態平穩，預期一週後總重約為 ${(projectedOneWeekTotal/1000).toFixed(2)} 噸。</span>`;
            }
        }

        function resetWasteIndex() {
            if(confirm("確定要重置廢粉回收指數計算嗎？這將會清空所有歷史廢粉流水帳。")) {
                appData.waste_logs = [];
                sysBackendSave();
                clearWasteStatsDisplay();
                alert("廢粉指數已重新歸零計算。");
            }
        }

        function clearWasteStatsDisplay() {
            document.getElementById('avg-day').innerText = '0';
            document.getElementById('avg-week').innerText = '0';
            document.getElementById('avg-month').innerText = '0';
            document.getElementById('avg-year').innerText = '0';
            document.getElementById('waste-percent').innerText = '0%';
            document.getElementById('waste-tons').innerText = '0';
            document.getElementById('waste-alert-msg').innerText = '';
        }

        // ==================== CSV 導入 / 匯出通用邏輯庫 ====================
        function exportCSV(sectionNum) {
            let csvContent = "\uFEFF"; // 避免 Excel 開啟亂碼的 UTF-8 BOM
            let fileName = "";
            
            if (sectionNum === 1) {
                csvContent += "粉號,代號,色系,淨重,廠商,是否下架\n";
                appData.registry.forEach(r => {
                    csvContent += `"${r.powder_no}","${r.code}","${r.color}",${r.net_weight},"${r.vendor}",${r.is_archived?1:0}\n`;
                });
                fileName = "烤漆粉號清冊.csv";
            } else if (sectionNum === 2) {
                csvContent += "粉號,箱數,散粉,安全庫存\n";
                for (let key in appData.inventory) {
                    let item = appData.inventory[key];
                    csvContent += `"${key}",${item.boxes},${item.loose},${item.safety_stock}\n`;
                }
                fileName = "庫存監控數據.csv";
            } else if (sectionNum === 3) {
                csvContent += "日期,粉號,箱數,散粉,總數量\n";
                appData.purchase_logs.forEach(l => {
                    csvContent += `"${l.date}","${l.powder_no}",${l.boxes},${l.loose},${l.total}\n`;
                });
                fileName = "採購入庫流水帳.csv";
            } else if (sectionNum === 4) {
                csvContent += "日期,粉號,箱數,散粉,回庫散粉,實際用量\n";
                appData.usage_logs.forEach(l => {
                    csvContent += `"${l.date}","${l.powder_no}",${l.boxes},${l.loose},${l.return_stock},${l.usage}\n`;
                });
                fileName = "用量紀錄流水帳.csv";
            }

            let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.setAttribute("download", fileName);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function importCSV(sectionNum) {
            let fileInput = document.getElementById(`csv-file-${sectionNum}`);
            if (!fileInput.files.length) { alert("請先選取要匯入的 CSV 檔案"); return; }
            
            let file = fileInput.files[0];
            let reader = new FileReader();
            reader.readAsText(file);
            reader.onload = function (e) {
                let text = e.target.result;
                let lines = text.split("\n").map(line => line.trim()).filter(line => line.length > 0);
                if (lines.length <= 1) { alert("此檔案無有效資料列！"); return; }
                
                // 跳過第一行表頭
                let count = 0;
                for (let i = 1; i < lines.length; i++) {
                    // 簡易 CSV 逗號拆分 (無複雜引號特殊處理)
                    let row = lines[i].split(",").map(cell => cell.replace(/^"|"$/g, ''));
                    if(row.length < 2) continue;

                    if (sectionNum === 1) {
                        appData.registry.push({
                            powder_no: row[0], code: row[1], color: row[2]||'', 
                            net_weight: parseInt(row[3])||25, vendor: row[4]||'', 
                            is_archived: parseInt(row[5])===1
                        });
                        count++;
                    } else if (sectionNum === 2) {
                        appData.inventory[row[0]] = {
                            boxes: parseFloat(row[1])||0, loose: parseFloat(row[2])||0, safety_stock: parseFloat(row[3])||100
                        };
                        count++;
                    } else if (sectionNum === 3) {
                        appData.purchase_logs.push({
                            date: row[0], powder_no: row[1], boxes: parseFloat(row[2])||0, loose: parseFloat(row[3])||0, total: parseFloat(row[4])||0
                        });
                        count++;
                    } else if (sectionNum === 4) {
                        appData.usage_logs.push({
                            date: row[0], powder_no: row[1], boxes: parseFloat(row[2])||0, loose: parseFloat(row[3])||0, return_stock: parseFloat(row[4])||0, usage: parseFloat(row[5])||0
                        });
                        count++;
                    }
                }
                
                sysBackendSave();
                alert(`成功讀取並覆蓋匯入 ${count} 筆紀錄。`);
                initSectionData(sectionNum);
            };
        }
    </script>
</body>
</html>
