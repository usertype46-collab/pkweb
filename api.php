<?php
header('Content-Type: application/json; charset=utf-8');
// 若前後端分離，可調整 CORS 設定；若在同網域可保留或移除
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? '';

// ==========================================
// 🔒 私密金鑰與配置管理區
// ==========================================
$admin_password = 'M0282'; // 管理員授權碼

$firebase_config = [
    'apiKey' => "AIzaSyDTIMGBYqAqOpFcCqEMjWWUwC_nPAfQ9ko",
    'authDomain' => "paint-system-21867.firebaseapp.com",
    'databaseURL' => "https://paint-system-21867-default-rtdb.firebaseio.com",
    'projectId' => "paint-system-21867",
    'storageBucket' => "paint-system-21867.firebasestorage.app",
    'messagingSenderId' => "855614444799",
    'appId' => "1:855614444799:web:db649669302532ea4aa8ca"
];
// ==========================================

if ($action === 'getConfig') {
    // 回傳 Firebase 配置
    echo json_encode([
        'status' => 'success',
        'data' => $firebase_config
    ]);
    exit;
}

if ($action === 'login') {
    // 處理登入驗證
    $input = json_decode(file_get_contents('php://input'), true);
    $pwd = $input['password'] ?? '';
    
    if ($pwd === $admin_password) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '驗證失敗：授權碼錯誤！']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => '無效的請求']);
?>
