<?php
define("LEADERBOARD_LINK", true);
$prevFolder = "";

include($prevFolder . "_intro.php");
include_once($prevFolder . "classes/game.php");

$gameObj = new Game($mysqli);

$PAGE_NAME = "Leaderboard - ";
include($prevFolder . "assets/_header.php");

function s($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function bindParamsDynamic($stmt, string $types, array &$params): bool {
    if ($types === '') {
        return true;
    }

    $bindNames = [];
    $bindNames[] = $types;

    foreach ($params as $i => $v) {
        $bindNames[] = &$params[$i];
    }

    return call_user_func_array([$stmt, 'bind_param'], $bindNames);
}

$perPage = 100;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterGameId = isset($_GET['game_id']) && is_numeric($_GET['game_id']) ? intval($_GET['game_id']) : 0;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$gameMap = $gameObj->getAllGames();

$isGameFiltered = $filterGameId > 0;
$statsTable = $isGameFiltered ? "{$dbprefix}member_game_stats" : "{$dbprefix}member_stats";
$statsAlias = $isGameFiltered ? "mgs" : "ms";

$whereParts = [];
$params = [];
$types = '';

$whereParts[] = "COALESCE(m.deleted, 0) != 1";

if ($isGameFiltered) {
    $whereParts[] = "{$statsAlias}.gamesplayed_id = ?";
    $params[] = $filterGameId;
    $types .= 'i';
}

if ($q !== '') {
    $whereParts[] = "(m.username LIKE ? OR m.username_html LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$whereSql = !empty($whereParts) ? "WHERE " . implode(" AND ", $whereParts) : "";
$total = 0;
$countSql = "
    SELECT COUNT(*)
    FROM {$statsTable} {$statsAlias}
    JOIN {$dbprefix}members m ON m.member_id = {$statsAlias}.member_id
    {$whereSql}
";

if ($stmt = $mysqli->prepare($countSql)) {
    if (!empty($params)) {
        bindParamsDynamic($stmt, $types, $params);
    }

    $stmt->execute();
    $stmt->bind_result($cnt);
    if ($stmt->fetch()) {
        $total = (int)$cnt;
    }
    $stmt->close();
} else {
    echo "<div style='padding:20px;color:#c0392b;'>Database error preparing leaderboard count.</div>";
    include($prevFolder . "assets/_footer.php");
    exit;
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
      m.member_id,
      m.username,
      m.username_style,
      m.profilepic,
      m.avatar,
      COALESCE({$statsAlias}.tournaments_won,0) AS tournaments_won,
      COALESCE({$statsAlias}.tournaments_participated,0) AS tournaments_participated,
      COALESCE({$statsAlias}.match_wins,0) AS match_wins,
      COALESCE({$statsAlias}.match_losses,0) AS match_losses,
      COALESCE({$statsAlias}.second_place,0) AS second_place
    FROM {$statsTable} {$statsAlias}
    JOIN {$dbprefix}members m ON m.member_id = {$statsAlias}.member_id
    {$whereSql}
    ORDER BY
      COALESCE({$statsAlias}.match_wins,0) DESC,
      (COALESCE({$statsAlias}.match_wins,0) + COALESCE({$statsAlias}.match_losses,0)) DESC,
      COALESCE({$statsAlias}.match_losses,0) ASC,
      COALESCE({$statsAlias}.tournaments_won,0) DESC,
      COALESCE({$statsAlias}.second_place,0) DESC,
      m.username ASC,
      m.member_id ASC
    LIMIT ? OFFSET ?
";

$rows = [];
if ($stmt = $mysqli->prepare($sql)) {
    $bindParams = $params;
    $bindTypes = $types . 'ii';
    $bindParams[] = $perPage;
    $bindParams[] = $offset;

    bindParamsDynamic($stmt, $bindTypes, $bindParams);

    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    echo "<div style='padding:20px;color:#c0392b;'>Database error preparing leaderboard.</div>";
    include($prevFolder . "assets/_footer.php");
    exit;
}
?>

<style>
.leaderboard { max-width:900px; margin:28px auto; color:#e8eef6; }

.lb-table { width:100%; border-collapse:collapse; }
.lb-table th, .lb-table td {
    padding:10px 12px;
    text-align:left;
    border-bottom:1px solid rgba(255,255,255,0.03);
    font-size:14px;
    color:#dbeafe;
}
.lb-table th {
    font-weight:700;
    color:#9aa4b2;
    text-transform:uppercase;
    font-size:12px;
}

.lb-avatar { width:40px; height:40px; border-radius:10px; object-fit:cover; display:inline-block; vertical-align:middle; }
.rank { font-weight:700; width:60px; text-align:center; }
.name-col a { text-decoration:none; font-weight:700; }
.empty { color:#9aa4b2; padding:20px; text-align:center; }

.tournaments-stats { text-align:center; display:flex; flex-direction:column; align-items:center; gap:6px; }
.tournaments-stats .gold { font-size:20px; color:#fbbf24; display:flex; align-items:center; gap:6px; font-weight:bold; }
.tournaments-stats .silver { font-size:14px; color:#c0c0c0; display:flex; align-items:center; gap:6px; font-weight:bold; }

.matches-stats { text-align:center; display:flex; flex-direction:column; gap:6px; align-items:center; }
.match-row { line-height:1.1; display:flex; align-items:center; gap:5px; }
.match-row .label { display:block; font-weight:400; font-size:13px; }
.match-row .count { display:block; font-weight:700; font-size:20px; }

.win .label, .win .count { color:#0bf115; }
.lose .label, .lose .count { color:#ff6b6b; }

.icon-trophy { font-size:16px; }

.lb-controls {
    display:flex;
    gap:15px;
    align-items:center;
    margin-bottom:30px;
    flex-wrap:wrap;
}

.search-form { flex:0 0 50%; min-width:220px; max-width:60%; display:block; }

.search-input-wrap { position:relative; width:100%; }
.search-input-wrap input[type="search"] {
    width:100%;
    padding:8px 12px;
    border-radius:8px;
    background:transparent;
    border:1px solid #2a2f2c;
    color:#e6eef8;
    font-size:15px;
    line-height:1.5;
    box-sizing:border-box;
}
.search-input-wrap input[type="search"]:focus {
    border-color:#00FF41;
    outline:none;
}
.search-input-wrap input[type="search"]::-webkit-search-cancel-button,
.search-input-wrap input[type="search"]::-webkit-search-decoration {
    -webkit-appearance:none;
    appearance:none;
    display:none;
}
.search-input-wrap input[type="search"]::-ms-clear,
.search-input-wrap input[type="search"]::-ms-reveal {
    display:none;
    width:0;
    height:0;
}

.custom-select { position:relative; min-width:220px; max-width:230px; flex:0 0 auto; }
.custom-select .select-btn {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:8px 12px;
    border-radius:8px;
    background:transparent;
    border:1px solid #2a2f2c;
    color:#e6eef8;
    cursor:pointer;
}
.custom-select .options {
    position:absolute;
    top:calc(100% + 8px);
    left:0;
    right:0;
    background:#0d1116;
    border-radius:8px;
    box-shadow:0 8px 24px rgba(0,0,0,0.6);
    z-index:50;
    display:none;
    max-height:220px;
    overflow:auto;
}
.custom-select.open .options { display:block; }
.custom-select .options .opt {
    padding:10px 12px;
    cursor:pointer;
    border-bottom:1px solid rgba(255,255,255,0.02);
    color:#cfe6ff;
}
.custom-select .options .opt:last-child { border-bottom:0; }
.custom-select .options .opt:hover { background:rgba(255,255,255,0.02); }
.custom-select .arrow { margin-left:12px; transform:rotate(0deg); transition:transform .15s ease; }
.custom-select.open .arrow { transform:rotate(180deg); }

.clear-link {
    color:#9fb0c8;
    text-decoration:underline;
    font-size:0.95rem;
    margin-left:6px;
    display:inline-block;
    white-space:nowrap;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin: 8px 0 0;
    padding: 10px 12px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22);
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 7px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
    user-select: none;
}

.pagination a {
    background: #0b1220;
    color: #dbeafe;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.pagination a:hover {
    background: #111b2f;
    border-color: rgba(159, 176, 200, 0.22);
    transform: translateY(-1px);
}

.pagination a:focus-visible {
    outline: 2px solid rgba(159, 176, 200, 0.4);
    outline-offset: 2px;
}

.pagination .current {
    background: #ffeb3b;
    color: #000;
    font-weight: 900;
    border: 1px solid #2a2f2c;
}

.pagination .disabled {
    background: transparent;
    color: #7b8797;
    border: 1px solid rgba(255, 255, 255, 0.04);
    opacity: 0.7;
    cursor: default;
}

@media (max-width:800px) {
    .lb-controls { flex-direction:column; align-items:stretch; gap:15px; }
    .search-form { width:100%; min-width:0; max-width:none; }
    .custom-select { width:100%; min-width:0; }
    .lb-table th, .lb-table td { font-size:13px; padding:8px 10px; }
    .pagination { justify-content: center; gap: 6px; padding: 8px; }

    .pagination a, .pagination span { min-width: 34px; height: 34px; padding: 0 10px; font-size: 13px; }
}
</style>

<main id="app-root" class="container" role="main" tabindex="-1">
<div class="leaderboard" role="main">

    <div class="lb-controls" role="region" aria-label="Leaderboard controls">
        <form method="get" id="search-form" class="search-form" style="margin:0;">
            <div class="search-input-wrap">
                <input type="search" name="q" placeholder="Search members..." value="<?php echo s($q); ?>" aria-label="Search members">
            </div>
            <input type="hidden" name="game_id" value="<?php echo intval($filterGameId); ?>">
        </form>

        <form method="get" id="game-filter-form" style="margin:0; display:flex; align-items:center; gap:6px;">
            <div class="custom-select" id="game-select" role="listbox" aria-label="Game selector">
                <div class="select-btn" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
                    <span class="label"><?php echo ($filterGameId === 0) ? 'All games' : htmlspecialchars($gameMap[$filterGameId]['name'] ?? 'All games'); ?></span>
                    <svg class="arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" style="opacity:.9">
                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="options" role="list">
                    <div class="opt" data-value="0" tabindex="0">All games</div>
                    <?php foreach ($gameMap as $gid => $info): ?>
                        <div class="opt" data-value="<?php echo intval($gid); ?>" tabindex="0"><?php echo htmlspecialchars($info['name'] ?? ('Game #' . $gid)); ?></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="game_id" value="<?php echo intval($filterGameId); ?>">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">
            </div>

            <?php if ($filterGameId > 0 || $q !== ''): ?>
                <a class="clear-link" href="<?php echo htmlspecialchars(MAIN_ROOT . 'leaderboard.php'); ?>">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty">No leaderboard data available.</div>
    <?php else: ?>
        <table class="lb-table" role="table" aria-label="Leaderboard">
            <thead>
                <tr>
                    <th class="rank">#</th>
                    <th>Player</th>
                    <th style="width:160px;text-align:center">Matches Stats</th>
                    <th style="width:160px;text-align:center">Tournaments Stats</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 0; foreach ($rows as $r): $i++;
                    $rank = $offset + $i;

                    $avatar = '';
                    $pic = $r['avatar'] ?: $r['profilepic'] ?: '';
                    if ($pic !== '') {
                        if (strpos($pic, 'http') !== 0 && strpos($pic, '/') !== 0) {
                            $pic = $MAIN_ROOT . ltrim($pic, '/');
                        }
                        $avatar = "<img src=\"" . s($pic) . "\" alt=\"" . s($r['username']) . " avatar\" class=\"lb-avatar\">";
                    } else {
                        $avatar = "<img src=\"" . s($MAIN_ROOT . "assets/images/default_avatar.png") . "\" alt=\"default avatar\" class=\"lb-avatar\">";
                    }

                    $displayName = renderStyledUsername($r['username'] ?? '', $r['username_style'] ?? '');
                	

                    $won = (int)$r['tournaments_won'];
                    $second = (int)$r['second_place'];
                    $wins = (int)$r['match_wins'];
                    $losses = (int)$r['match_losses'];
                ?>
                <tr>
                    <td class="rank" aria-label="Rank"><?php echo $rank; ?></td>
                    <td class="name-col">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div><?php echo $avatar; ?></div>
                            <div>
                                <a href="<?php echo s($MAIN_ROOT . 'member.php?mID=' . (int)$r['member_id']); ?>">
                                    <?php echo $displayName; ?>
                                </a>
                            </div>
                        </div>
                    </td>

                    <td style="text-align:center;">
                        <div class="matches-stats" role="group" aria-label="Matches stats">
                            <div class="match-row win" aria-label="<?php echo s(number_format($wins)); ?> wins">
                                <span class="count"><?php echo s(number_format($wins)); ?></span>
                                <span class="label">Won</span>
                            </div>
                            <div class="match-row lose" aria-label="<?php echo s(number_format($losses)); ?> losses">
                                <span class="count" style="font-size:14px;"><?php echo s(number_format($losses)); ?></span>
                                <span class="label">Lost</span>
                            </div>
                        </div>
                    </td>

                    <td style="text-align:center;">
                        <div class="tournaments-stats" role="group" aria-label="Tournaments stats">
                            <div class="gold" aria-label="Tournaments won">
                                <span><?php echo s(number_format($won)); ?></span>
                                <span class="icon-trophy" aria-hidden="true">🏆</span>
                            </div>

                            <div class="silver" aria-label="Second place finishes">
                                <span><?php echo s(number_format($second)); ?></span>
                                <span aria-hidden="true">🏆</span>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:16px; display:flex; justify-content:center;">
            <nav class="pagination" role="navigation" aria-label="Leaderboard pagination">
                <?php
                $base = basename($_SERVER['PHP_SELF']);
                $qp = [];
                if ($q !== '') $qp['q'] = $q;
                if ($filterGameId > 0) $qp['game_id'] = $filterGameId;

                $page_link = function($p) use ($base, $qp) {
                    $query = $qp;
                    $query['page'] = $p;
                    return $base . '?' . http_build_query($query);
                };

                if ($page > 1) {
                    echo '<a href="' . s($page_link($page - 1)) . '" aria-label="Previous page">&laquo; Prev</a>';
                } else {
                    echo '<span class="disabled">&laquo; Prev</span>';
                }

                $window = 5;
                $start = max(1, $page - $window);
                $end = min($totalPages, $page + $window);

                if ($start > 1) {
                    echo '<a href="' . s($page_link(1)) . '">1</a>';
                    if ($start > 2) echo '<span class="disabled">…</span>';
                }

                for ($p = $start; $p <= $end; $p++) {
                    if ($p == $page) {
                        echo '<span class="current" aria-current="page">' . $p . '</span>';
                    } else {
                        echo '<a href="' . s($page_link($p)) . '">' . $p . '</a>';
                    }
                }

                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="disabled">…</span>';
                    echo '<a href="' . s($page_link($totalPages)) . '">' . $totalPages . '</a>';
                }

                if ($page < $totalPages) {
                    echo '<a href="' . s($page_link($page + 1)) . '" aria-label="Next page">Next &raquo;</a>';
                } else {
                    echo '<span class="disabled">Next &raquo;</span>';
                }
                ?>
            </nav>
        </div>
    <?php endif; ?>

</div>
</main>

<script>
(function() {
    "use strict";

    (function initGameSelect() {
        var cs = document.getElementById('game-select');
        if (!cs) return;
        var form = cs.closest('form');
        var btn = cs.querySelector('.select-btn');
        var opts = cs.querySelector('.options');
        var hidden = cs.querySelector('input[name="game_id"]');

        function closeAll() {
            document.querySelectorAll('.custom-select.open').forEach(function(c) {
                c.classList.remove('open');
                var sbtn = c.querySelector('.select-btn');
                if (sbtn) sbtn.setAttribute('aria-expanded', 'false');
            });
        }

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var open = cs.classList.toggle('open');
            btn.setAttribute('aria-expanded', !!open);
        });

        btn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                cs.classList.toggle('open');
            } else if (e.key === 'Escape') {
                cs.classList.remove('open');
            }
        });

        opts.querySelectorAll('.opt').forEach(function(o) {
            o.addEventListener('click', function() {
                var val = this.getAttribute('data-value') || '';
                var text = this.textContent || '';
                if (hidden) hidden.value = val;

                var label = cs.querySelector('.label');
                if (label) label.textContent = text;

                cs.classList.remove('open');
                if (form) form.submit();
            });

            o.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        document.addEventListener('click', function() {
            closeAll();
        });
    })();
})();
</script>

<?php
include($prevFolder . "assets/_footer.php");
?>