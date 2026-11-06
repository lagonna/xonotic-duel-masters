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


function jsonResponse($status, $data) {
    echo json_encode(array_merge(['status' => $status], $data));
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

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP-Balance-Script/1.1\r\n",
            "timeout" => 5
        ]
    ];
    $context = stream_context_create($opts);
    $json = @file_get_contents("https://api.coingecko.com/api/v3/simple/price?ids={$coinId}&vs_currencies=usd", false, $context);

    if (!$json) return null;

    $data = json_decode($json, true);
    $price = isset($data[$coinId]['usd']) ? floatval($data[$coinId]['usd']) : null;

    if ($price !== null) {
        @file_put_contents($cacheFile, json_encode($price));
    }

    return $price;
}

function getNetworkFee($currency = 'BTC') {
    $currency = strtoupper($currency);
    
    if ($currency !== 'BTC') return null;

    $cacheDir = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/btc_fee.json';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if ($decoded !== null) return $decoded;
        }
    }

    $opts = ["http" => ["header" => "User-Agent: PHP-Balance-Script/1.1\r\n", "timeout" => 5]];
    $context = stream_context_create($opts);
    $json = @file_get_contents('https://mempool.space/api/v1/fees/recommended', false, $context);

    if (!$json) return null;

    $data = json_decode($json, true);
    $feeSat = isset($data['fastestFee']) ? intval($data['fastestFee']) : null;
    if ($feeSat === null) return null;

    $feeBtc = ($feeSat * 250) / 1e8;
    @file_put_contents($cacheFile, json_encode($feeBtc));
    return $feeBtc;
}

const MIN_CRYPTO_DUST = [
    'BTC' => 0.0001,
    'ETH' => 0.005,
    'LTC' => 0.01,
    'DEFAULT' => 0.0001
];

try {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse('error', ['msg' => 'Session expired']);
    }
    $user_id = (int)$_SESSION['user_id'];
    $method = strtoupper($_GET['coin'] ?? 'BTC');

    $stmt = $mysqli->prepare("SELECT SUM(amount) as total FROM wallet_transactions WHERE member_id = ? AND currency = 'USD' AND status = 'completed'");
    if (!$stmt) {
        jsonResponse('error', ['msg' => 'DB prepare failed']);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $balanceUsd = round((float)($res['total'] ?? 0), 2);

    $cryptoPrice = getCryptoUsdPrice($method);
    $price_per_unit = ($cryptoPrice !== null) ? (float)$cryptoPrice : null;
    $balanceCrypto = ($price_per_unit !== null && $price_per_unit > 0)
        ? round($balanceUsd / $price_per_unit, 8)
        : null;

    $networkFee = getNetworkFee($method);
    $networkFeeDisplay = ($networkFee !== null) ? number_format($networkFee, 8, '.', '') : "Pending";

    $dustThreshold = MIN_CRYPTO_DUST[$method] ?? MIN_CRYPTO_DUST['DEFAULT'];
    $minWdUsd = ($price_per_unit !== null)
        ? round($dustThreshold * $price_per_unit, 2)
        : null;

    jsonResponse('success', [
        'currency'       => $method,
        'balance_usd'    => number_format($balanceUsd, 2, '.', ''),
        'balance_crypto' => $balanceCrypto !== null ? number_format($balanceCrypto, 8, '.', '') : null,
        'balance_btc'    => $balanceCrypto !== null ? number_format($balanceCrypto, 8, '.', '') : null,
        'price'          => $price_per_unit !== null ? number_format($price_per_unit, 2, '.', '') : null,
        'network_fee'    => $networkFeeDisplay,
        'min_wd_usd'     => $minWdUsd !== null ? number_format($minWdUsd, 2, '.', '') : null,
        'debug'          => $DEBUG ? "Price: " . ($price_per_unit ?? 'null') . " | User: $user_id | Coin: $method" : null
    ]);

} catch (Exception $e) {
    jsonResponse('error', ['msg' => 'Server Error', 'debug' => $e->getMessage()]);
}