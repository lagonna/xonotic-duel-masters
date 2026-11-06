<?php
$prevFolder = "";
include("_intro.php");
$PAGE_NAME = "Wallet - ";
include("assets/_header.php");

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $result = $mysqli->query("SELECT member_id FROM members WHERE member_id = '$user_id'");
    if ($result->num_rows == 0) {
        echo "<script>window.location = '".MAIN_ROOT."'</script>";
        exit();
    }
    if (!defined("MAIN_ROOT")) { exit(); }

    function timestampToUtcIso(string $timestr): string {
        $timestr = trim($timestr);
        if ($timestr === '') return '';

        if (preg_match('/[Zz]|[+\-]\d{2}:\d{2}$/', $timestr)) {
            $dt = @date_create($timestr);
            if ($dt !== false) {
                $dt->setTimezone(new DateTimeZone('UTC'));
                return $dt->format(DATE_ATOM);
            }
        }

        $serverTzName = date_default_timezone_get() ?: 'UTC';
        try {
            $serverTz = new DateTimeZone($serverTzName);
        } catch (Exception $e) {
            $serverTz = new DateTimeZone('UTC');
        }

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestr, $serverTz);
        if ($dt === false) {
            $dt = @date_create($timestr, $serverTz);
        }
        if ($dt === false) return '';

        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format(DATE_ATOM);
    }
?>
<style>
    .input-error { border: 1px solid #ff4d4d !important; box-shadow: 0 0 5px rgba(255,77,77,0.3); }
    .error-text { color: #ff4d4d; font-size: 0.75rem; margin-top:3px; display:none; font-weight:500; }
    .wallet-input-group { transition: all 0.3s ease; }

    .tx-container { margin-top:30px; border-radius:12px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.05); border:1px solid #333840; background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.02)); }
    .tx-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #333840; padding-bottom:10px; }
    .tx-header h2 { font-size:1.2rem; margin:0; color:#e6eef8; }
    .tx-table-wrapper { overflow-x:auto; }
    .tx-table { width:100%; border-collapse:collapse; min-width:520px; }
    .tx-table th { text-align:center; padding:12px; font-size:0.85rem; color:#9aa3b2; text-transform:uppercase; border-bottom:1px solid #333840; }
    .tx-table td { padding:12px; border-bottom:1px solid #333840; font-size:0.92rem; text-align:center; color:#e6eef8; }
    .tx-method { color:#9aa3b2; font-size:0.72rem; margin-top:2px; }

    .badge { padding:6px 12px; border-radius:10px; font-size:0.75rem; font-weight:700; text-transform:uppercase; display:inline-block; }
    .status-pending { background: linear-gradient(90deg,#fff8e1,#fff3cd); color:#8a6d00; }
    .status-completed { background: linear-gradient(90deg,#dff7e6,#bfeccf); color:#0b6b2d; }
    .status-cancelled { background: linear-gradient(90deg,#fdecea,#f8d7da); color:#7a191f; }

    .withdraw-info { display:flex; gap:7px; align-items:center; justify-content: center; flex-direction: column; font-size:14px; color:#e6eef8; }
    .withdraw-info .item { display:flex; gap:6px; align-items:center; }
    .withdraw-info .label { color:#9aa3b2; font-size:0.85rem; }
    .withdraw-info .value { font-weight:600; }
    .withdraw-info .min .value { color:#f7931a; }  
    .withdraw-info .fee .value { color:#c62828; }  

    .balance-summary { margin-top:10px; display:flex; flex-direction:column; gap:4px; }
    .balance-label { color:#9aa3b2; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
    .balance-amount { font-size:2rem; font-weight:700; color:#e6eef8; }
    .balance-amount span:first-child { color:#9aa3b2; font-weight:600; margin-right:2px; }

    .method-note { color:#9aa3b2; font-size:0.78rem; margin-top:8px; text-align:center; }
    #currency-value-main .unit { color:#9aa3b2; font-weight:500; margin-left:4px; font-size:0.85em; }

    .input-prefix { color:#9aa3b2; font-weight:600; padding:0 4px; }

    @media (max-width:720px) {
        .withdraw-info { gap:12px; }
        .tx-table th, .tx-table td { font-size:0.82rem; padding:10px; }
        .balance-amount { font-size:1.6rem; }
    }
</style>

<main id="app-root" class="container" role="main" tabindex="-1">
    <div class="wallet-container">
        <div class="wallet-header">
            <h1>Wallet</h1>
            <div class="balance-summary" id="balance-summary">
                <span class="balance-label">Available Balance</span>
                <div class="balance-amount"><span>$</span><span id="balance-usd-amount">0.00</span></div>
            </div>
        </div>

        <div class="wallet-content">
            <div id="withdraw-tab" class="tab-content active">

                <div class="section amount-section">
                    <span class="section-label">Amount (USD) *</span>
                    <div class="wallet-input-group" id="amount-input-group">
                        <span class="input-prefix">$</span>
                        <input type="text" id="amount-input" placeholder="0.00" autocomplete="off">
                        <div class="wallet-input-group-right">
                            <div class="price"><span id="crypto-output">0.00000000</span><span class="crypto-symbol"> BTC</span></div>
                            <button class="btn" id="max-btn" type="button">Max</button>
                        </div>
                    </div>
                    <div id="amount-error" class="error-text">Please enter a valid amount.</div>
                </div>

                <div class="section method-section">
                    <span class="section-label">Withdraw Method</span>
                    <div class="selector currency-selector" id="currency-selector">
                        <div id="currency-icon-main">
                            <img class="icon" src="<?php echo MAIN_ROOT; ?>assets/images/crypto/btc.png" alt="BTC">
                        </div>
                        <div class="info">
                            <div class="main" id="currency-name">BTC</div>
                            <div class="sub" id="currency-fullname">Bitcoin</div>
                        </div>
                        <div class="value">
                            <div class="main" id="currency-value-main">0.00000000<span class="unit crypto-symbol"> BTC</span></div>
                        </div>
                        <div class="loader-overlay" style="display:none"><div class="loader-spinner"></div></div>
                    </div>
                    <div class="method-note">More withdrawal methods will be added soon.</div>
                </div>

                <div class="section address-section">
                    <span class="section-label">Address *</span>
                    <div class="wallet-input-group" id="address-input-group">
                        <input type="text" id="address-input" placeholder="Paste address here..." autocomplete="off">
                        <button class="paste-btn" id="paste-btn" type="button">Paste</button>
                    </div>
                    <div id="address-error" class="error-text">Please enter a valid address.</div>
                </div>

                <div class="withdraw-info" aria-hidden="false">
                    <div class="item fee">
                        <div class="label">Transaction Fee</div>
                        <div class="value" id="transactionFee">- <span class="crypto-symbol">BTC</span></div>
                    </div>
                </div>

                <div class="withdraw-btn-wrapper" style="margin-top:1.5rem;">
                    <button class="withdraw-btn" id="withdraw-btn" type="button">Withdraw</button>
                </div>
            </div>
        </div>
    </div>

    <div class="tx-container" id="history-section">
        <div class="tx-header"><h2>Recent Transactions</h2></div>
        <div class="tx-table-wrapper">
            <table class="tx-table" role="table" aria-label="Recent transactions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="tx-tbody">
                    <?php
                    $tx_query = "SELECT * FROM withdrawal_requests WHERE member_id = ? ORDER BY requested_at DESC LIMIT 10";
                    if ($stmt = $mysqli->prepare($tx_query)) {
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $requested_at = $row['requested_at'] ?? '';
                                $created_iso = timestampToUtcIso($requested_at);
                                $fallback = $requested_at ? date("M d, H:i", strtotime($requested_at)) : '';
                                $date = htmlspecialchars($fallback);
                                
                                $amt = htmlspecialchars(number_format((float)($row['amount'] ?? 0), 2));
                                $curr = htmlspecialchars($row['currency'] ?? $row['payment_method'] ?? 'BTC');
                                
                                $crypto_raw = $row['amount_crypto'] ?? $row['amount_btc'] ?? 0;
                                $crypto_amt = htmlspecialchars(number_format((float)$crypto_raw, 8));
                                $status = htmlspecialchars($row['status'] ?? 'pending');

                                $data_attr = $created_iso ? ' data-created-utc="' . htmlspecialchars($created_iso, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
                                echo "<tr{$data_attr}>";
                                echo "<td class='tx-date'>{$date}</td>";
                                echo "<td><strong>\${$amt}</strong><div class='tx-method'>{$crypto_amt} {$curr}</div></td>";
                                echo "<td><span class='badge status-{$status}'>{$status}</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' align='center'>No transactions found.</td></tr>";
                        }
                        $res->free();
                        $stmt->close();
                    } else {
                        echo "<tr><td colspan='3' align='center'>Unable to load transactions.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php $js_csrf = json_encode($_SESSION['csrftokken'] ?? ''); ?>
<script>
const CSRF_TOKEN = <?php echo $js_csrf; ?>;

const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));

function safeFloat(v, def = 0) {
    if (v === null || v === undefined) return def;
    v = String(v).replace(/[^0-9\.\-]/g, '');
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : def;
}

function showError(group, textEl, msg) {
    if (group) group.classList.add('input-error');
    if (textEl) { textEl.textContent = msg; textEl.style.display = 'block'; }
}

function clearErrors() {
    [$('#amount-input-group'), $('#address-input-group')].forEach(e => { if (e) e.classList.remove('input-error'); });
    [$('#amount-error'), $('#address-error')].forEach(e => { if (e) e.style.display = 'none'; });
}

let state = { 
    balanceUsd: 0, 
    balanceCrypto: 0, 
    price: 0, 
    minWithdrawUsd: 0, 
    fee: 0, 
    processing: false, 
    currentCoin: 'BTC' 
};

const el = {
    balanceUsdAmount: $('#balance-usd-amount'),
    amountIn: $('#amount-input'),
    amountGrp: $('#amount-input-group'),
    amountErr: $('#amount-error'),
    cryptoOut: $('#crypto-output'),
    cryptoSymbols: $$('.crypto-symbol'),
    addressIn: $('#address-input'),
    addressGrp: $('#address-input-group'),
    addressErr: $('#address-error'),
    withdrawBtn: $('#withdraw-btn'),
    methodValue: $('#currency-value-main'),
    feeSpan: $('#transactionFee'),
    txTbody: $('#tx-tbody'),
    history: $('#history-section')
};

async function fetchWalletInfo(coin = state.currentCoin) {
    try {
        const resp = await fetch(`backend/get_balance.php?coin=${coin}`, { cache: 'no-store' });
        if (!resp.ok) throw new Error('Network response not ok');
        const d = await resp.json();
        if (!d || d.status !== 'success') return;

        state.currentCoin = coin;
        state.balanceUsd = safeFloat(d.balance_usd, 0);
        state.balanceCrypto = safeFloat(d.balance_crypto ?? d.balance_btc, 0); 
        state.minWithdrawUsd = safeFloat(d.min_wd_usd, 0);
        
        const net_fee = (d.network_fee === "Pending" || d.network_fee === null) ? null : safeFloat(d.network_fee, 0);
        state.fee = net_fee !== null ? net_fee : 0;
        
        const priceFromBackend = (d.price !== undefined && d.price !== null) ? safeFloat(d.price, 0) : 0;
        if (priceFromBackend > 0) state.price = priceFromBackend;

        if (el.balanceUsdAmount) el.balanceUsdAmount.textContent = state.balanceUsd.toFixed(2);
        
        el.cryptoSymbols.forEach(span => { span.textContent = ` ${state.currentCoin}`; });

        if (el.methodValue) {
            el.methodValue.innerHTML = `${state.balanceCrypto.toFixed(8)}<span class="unit"> ${state.currentCoin}</span>`;
        }
        if (el.feeSpan) {
            el.feeSpan.textContent = `${(net_fee !== null ? net_fee.toFixed(8) : 'Pending')} ${state.currentCoin}`;
        }

        if (el.amountIn) el.amountIn.dispatchEvent(new Event('input'));
    } catch (err) {
        console.error('fetchWalletInfo error', err);
    }
}

function prependTransactionRow({ iso, dateText, amountUsd, amountCrypto, method, status = 'pending' }) {
    const tbody = el.txTbody;
    if (!tbody) return;

    const first = tbody.querySelector('td');
    if (first && first.colSpan && parseInt(first.colSpan, 10) === 3) tbody.innerHTML = '';

    const tr = document.createElement('tr');
    if (iso) tr.setAttribute('data-created-utc', iso);

    const tdDate = document.createElement('td');
    tdDate.className = 'tx-date';
    tdDate.textContent = dateText || '';

    const tdAmt = document.createElement('td');
    tdAmt.innerHTML = `<strong>$${parseFloat(amountUsd).toFixed(2)}</strong><div class="tx-method">${amountCrypto} ${method}</div>`;

    const tdStatus = document.createElement('td');
    tdStatus.innerHTML = `<span class='badge status-${status}'>${status}</span>`;

    tr.appendChild(tdDate);
    tr.appendChild(tdAmt);
    tr.appendChild(tdStatus);

    tbody.insertBefore(tr, tbody.firstChild);
}

function timeAgoFromDate(d) {
    if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '';
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 10) return 'just now';
    if (diff < 60) return diff + ' second' + (diff > 1 ? 's' : '') + ' ago';
    if (diff < 3600) {
        const m = Math.floor(diff / 60);
        return m + ' minute' + (m > 1 ? 's' : '') + ' ago';
    }
    if (diff < 86400) {
        const h = Math.floor(diff / 3600);
        return h + ' hour' + (h > 1 ? 's' : '') + ' ago';
    }
    if (diff < 2592000) {
        const days = Math.floor(diff / 86400);
        return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    }
    return d.toLocaleString();
}

function formatTransactionTimeAgo(root) {
    try {
        const rows = (root || document).querySelectorAll('#tx-tbody tr[data-created-utc]');
        if (!rows) return;
        rows.forEach(row => {
            const iso = row.getAttribute('data-created-utc') || '';
            const dateCell = row.querySelector('.tx-date');
            if (!dateCell || !iso) return;
            const d = new Date(iso);
            if (isNaN(d.getTime())) return;
            dateCell.textContent = timeAgoFromDate(d);
        });
    } catch (e) {
        console.warn('formatTransactionTimeAgo error', e);
    }
}

function validate() {
    clearErrors();
    let ok = true;
    const amount = safeFloat(el.amountIn.value, NaN);
    const address = (el.addressIn.value || '').trim();

    if (Number.isNaN(amount) || amount <= 0) { showError(el.amountGrp, el.amountErr, "Enter a valid positive amount."); ok = false; }
    else if (state.minWithdrawUsd > 0 && amount < state.minWithdrawUsd) { showError(el.amountGrp, el.amountErr, `Minimum withdrawal: $${state.minWithdrawUsd.toFixed(2)}`); ok = false; }
    else if (amount > state.balanceUsd) { showError(el.amountGrp, el.amountErr, "Insufficient funds."); ok = false; }

    if (address.length < 10) { showError(el.addressGrp, el.addressErr, "Invalid address."); ok = false; }
    return ok;
}

if (el.amountIn) {
    el.amountIn.addEventListener('input', () => {
        const amtUsd = safeFloat(el.amountIn.value, 0);
        const price = state.price || 0;
        const cryptoEquivalent = price > 0 ? (amtUsd / price) : 0;
        if (el.cryptoOut) el.cryptoOut.textContent = cryptoEquivalent.toFixed(8);
    });
}

const maxBtn = $('#max-btn');
if (maxBtn) {
    maxBtn.addEventListener('click', () => {
        if (el.amountIn) {
            el.amountIn.value = state.balanceUsd.toFixed(2);
            el.amountIn.dispatchEvent(new Event('input'));
        }
    });
}

const pasteBtn = $('#paste-btn');
if (pasteBtn) {
    pasteBtn.addEventListener('click', async () => {
        try {
            if (navigator.clipboard && navigator.clipboard.readText) {
                const t = await navigator.clipboard.readText();
                if (t && el.addressIn) el.addressIn.value = t;
                else if (el.addressIn) el.addressIn.focus();
            } else {
                const t = prompt('Paste address here:') || '';
                if (el.addressIn) el.addressIn.value = t;
            }
        } catch (e) {
            const t = prompt('Paste address here:') || '';
            if (el.addressIn) el.addressIn.value = t;
        }
    });
}

if (el.withdrawBtn) {
    el.withdrawBtn.addEventListener('click', async () => {
        if (state.processing) return;
        if (!validate()) return;

        state.processing = true;
        el.withdrawBtn.textContent = 'Processing...';

        const amountUsd = safeFloat(el.amountIn.value, 0);
        const amountCrypto = state.price > 0 ? (amountUsd / state.price) : 0;
        const amountCryptoStr = amountCrypto.toFixed(8);

        const payload = {
            CSRF_TOKKEN: CSRF_TOKEN,
            currency: state.currentCoin,
            amount: amountUsd.toFixed(2),
            amount_crypto: amountCryptoStr,
            amount_btc: amountCryptoStr,
            address: el.addressIn.value
        };

        try {
            const resp = await fetch('backend/withdraw.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                cache: 'no-store'
            });
            const data = await resp.json();

            if (data && data.status === 'success') {
                await fetchWalletInfo();

                const now = new Date();
                const iso = now.toISOString();
                const dateText = timeAgoFromDate(now);
                
                prependTransactionRow({ 
                    iso, 
                    dateText, 
                    amountUsd: amountUsd,
                    amountCrypto: amountCryptoStr,
                    method: state.currentCoin, 
                    status: 'pending' 
                });

                if (el.history) el.history.scrollIntoView({ behavior: 'smooth' });

                if (el.amountIn) el.amountIn.value = '';
                if (el.addressIn) el.addressIn.value = '';
                if (el.amountIn) el.amountIn.dispatchEvent(new Event('input'));
            } else {
                const msg = (data && data.msg) ? data.msg : 'Withdrawal failed';
                showError(el.amountGrp, el.amountErr, msg);
            }
        } catch (err) {
            console.error('withdraw error', err);
            showError(el.amountGrp, el.amountErr, 'Network or server error. Please try again.');
        } finally {
            state.processing = false;
            el.withdrawBtn.textContent = 'Withdraw';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    fetchWalletInfo();
    formatTransactionTimeAgo(document);
    setInterval(() => formatTransactionTimeAgo(document), 60000);

    (function observe() {
        const container = document.getElementById('tx-tbody');
        if (!container || !window.MutationObserver) return;
        const mo = new MutationObserver(mutations => {
            mutations.forEach(m => {
                m.addedNodes.forEach(node => {
                    if (!(node instanceof Element)) return;
                    if (node.tagName === 'TR') formatTransactionTimeAgo(node.parentNode || document);
                    else formatTransactionTimeAgo(node);
                });
            });
        });
        mo.observe(container, { childList: true, subtree: false });
    })();

    if (window.location.hash === "#history-section") {
        const h = document.querySelector(window.location.hash);
        if (h) h.scrollIntoView({ behavior: 'smooth' });
    }
});
</script>

<?php
} else {
?>

<main id="app-root" class="container" role="main" tabindex="-1">
<div style="
    background-color: rgb(16 33 50); 
    border-left: 5px solid #03a9f4;
    padding: 16px 20px; 
    border-radius: 8px; 
    display: flex; 
    align-items: center; 
    justify-content: space-between;
    max-width: 450px;
    margin: 20px auto;
    color: #ffffff;
">
    <div style="display: flex; align-items: center; gap: 12px;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#03a9f4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        
        <div>
            <p style="margin: 0; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;">
                YOU ARE NOT LOGGED IN!
            </p>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #b9bbbe;">
                You need to authenticate to access this page.
            </p>
        </div>
    </div>
</div>
</main>

<?php
}
include("assets/_footer.php");
?>
