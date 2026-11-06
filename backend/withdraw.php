<?php
$DEBUG = true; 

if ($DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

include_once(__DIR__ . '/../_intro.php'); 
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

function jsonRes($status, $msg, $debug = null) {
    echo json_encode(['status' => $status, 'msg' => $msg, 'debug' => $debug]);
    exit();
}

function getCryptoUsdPrice($currency = 'BTC') {
    $coinMap = [
        'BTC' => 'bitcoin',
        'ETH' => 'ethereum',
        'LTC' => 'litecoin',
        'USDT' => 'tether',
        'XMR' => 'monero'
    ];
    
    $currency = strtoupper($currency);
    $coinId = $coinMap[$currency] ?? 'bitcoin';

    $cacheDir = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/' . strtolower($currency) . '_price.json';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 180)) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if ($decoded !== null) return $decoded;
        }
    }

    $opts = ["http" => ["method" => "GET", "header" => "User-Agent: PHP-Balance-Script/1.1\r\n", "timeout" => 5]];
    $context = stream_context_create($opts);
    $json = @file_get_contents("https://api.coingecko.com/api/v3/simple/price?ids={$coinId}&vs_currencies=usd", false, $context);
    if (!$json) return null;

    $data = json_decode($json, true);
    $price = isset($data[$coinId]['usd']) ? floatval($data[$coinId]['usd']) : null;
    if ($price !== null) @file_put_contents($cacheFile, json_encode($price));
    return $price;
}

const MIN_CRYPTO_DUST = [
    'BTC' => 0.0001,
    'ETH' => 0.005,
    'LTC' => 0.01,
    'DEFAULT' => 0.0001
];

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) $input = [];

$post_csrf = $input['CSRF_TOKKEN'] ?? '';
$session_csrf = $_SESSION['csrftokken'] ?? '';

if (empty($session_csrf) || empty($post_csrf) || !is_string($post_csrf) || !hash_equals($session_csrf, $post_csrf)) {
    jsonRes('error', 'Invalid or missing CSRF token.');
}

if (!isset($_SESSION['user_id'])) {
    jsonRes('error', 'Please login to continue.');
}
$user_id = (int)$_SESSION['user_id'];

$amount       = filter_var($input['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$amountCrypto = filter_var($input['amount_crypto'] ?? $input['amount_btc'] ?? null, FILTER_VALIDATE_FLOAT);
$address      = trim($input['address'] ?? '');
$currency     = strtoupper($input['currency'] ?? 'BTC');

$price = getCryptoUsdPrice($currency);
$dustThreshold = MIN_CRYPTO_DUST[$currency] ?? MIN_CRYPTO_DUST['DEFAULT'];
$minWdUsd = ($price !== null && $price > 0) ? round($dustThreshold * $price, 2) : 1.00;

if (!$amount || $amount < $minWdUsd) {
    jsonRes('error', 'Invalid amount. Minimum withdrawal is $' . number_format($minWdUsd, 2) . '.');
}

if (strlen($address) < 20) { 
    jsonRes('error', 'Invalid wallet address format.');
}

$mysqli->begin_transaction();

$lockName = "withdraw_lock_user_" . $user_id;

try {
    $stmtLock = $mysqli->prepare("SELECT GET_LOCK(?, 5)");
    $stmtLock->bind_param("s", $lockName);
    $stmtLock->execute();
    $lockAcquired = $stmtLock->get_result()->fetch_row()[0];
    $stmtLock->close();

    if ($lockAcquired !== 1) {
        throw new Exception("Another transaction is currently processing. Please try again in a moment.");
    }

    $stmt = $mysqli->prepare("SELECT SUM(amount) as total FROM wallet_transactions WHERE member_id = ? AND currency = 'USD' AND status = 'completed'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $balance = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    if ($amount > $balance) {
        throw new Exception("Insufficient balance. You have $" . number_format($balance, 2) . ".");
    }

    $sqlReq = "INSERT INTO withdrawal_requests (member_id, amount, amount_crypto, currency, payment_method, payment_details, status) 
               VALUES (?, ?, ?, ?, 'Crypto', ?, 'pending')";
    $stmtReq = $mysqli->prepare($sqlReq);
    
    $stmtReq->bind_param("iddss", $user_id, $amount, $amountCrypto, $currency, $address);
    
    if (!$stmtReq->execute()) {
        throw new Exception("Failed to create request: " . $mysqli->error);
    }
    $withdrawal_id = $mysqli->insert_id;
    $stmtReq->close();

    $negativeAmount = -$amount;
    
    $cryptoNote = $amountCrypto ? (' (~' . number_format($amountCrypto, 8) . ' ' . $currency . ')') : '';
    $desc = "Withdrawal Request #$withdrawal_id" . $cryptoNote;
    
    $sqlDebit = "INSERT INTO wallet_transactions (member_id, transaction_type, amount, currency, status, description) 
                 VALUES (?, 'withdrawal', ?, 'USD', 'completed', ?)";
    $stmtDebit = $mysqli->prepare($sqlDebit);
    $stmtDebit->bind_param("ids", $user_id, $negativeAmount, $desc);
    
    if (!$stmtDebit->execute()) {
        throw new Exception("Failed to update wallet: " . $mysqli->error);
    }
    $stmtDebit->close();

    $mysqli->commit();
    jsonRes('success', 'Withdrawal requested successfully!');

} catch (Exception $e) {
    $mysqli->rollback();
    jsonRes('error', $e->getMessage());
} finally {
    $stmtUnlock = $mysqli->prepare("SELECT RELEASE_LOCK(?)");
    $stmtUnlock->bind_param("s", $lockName);
    $stmtUnlock->execute();
    $stmtUnlock->close();
}