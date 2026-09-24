<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = '我的收藏 - 社区便民留言板';
$currentPage = 'favorites';
$cssPath = 'assets/css/style.css';
$jsPath = 'assets/js/main.js';

$db = getDB();
$visitorId = getVisitorId();

$validTypes = ['help', 'suggest', 'lost'];

$type = $_GET['type'] ?? '';
$needRedirect = false;
if ($type !== '' && !in_array($type, $validTypes, true)) {
    $type = '';
    $needRedirect = true;
}

// 页码校验：非正整数规范化
$rawPage = $_GET['page'] ?? null;
$page = ($rawPage !== null && $rawPage !== '') ? intval($rawPage) : 1;
if ($rawPage !== null && $rawPage !== '' && (!ctype_digit((string)$rawPage) || $page < 1)) {
    $page = 1;
    $needRedirect = true;
}
$pageSize = 10;

$where = "WHERE f.visitor_id = ? AND m.status = 1";
$params = [$visitorId];

if ($type) {
    $where .= " AND m.type = ?";
    $params[] = $type;
}

$countSql = "SELECT COUNT(*) FROM favorites f INNER JOIN messages m ON f.message_id = m.id $where";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $pageSize);

// 页码越界：有数据时重定向到最后一页，无数据时回到第1页
$targetPage = min($page, max(1, $totalPages));
if ($targetPage !== $page) {
    $needRedirect = true;
    $page = $targetPage;
}
if ($needRedirect) {
    redirectTo('favorites.php' . buildQueryString(['type' => $type ?: null, 'page' => $page > 1 ? $page : null]));
}
$offset = ($page - 1) * $pageSize;

$sql = "SELECT m.id, m.nickname, m.type, m.title, m.content, m.image, m.views, m.created_at, f.created_at as favorited_at
        FROM favorites f
        INNER JOIN messages m ON f.message_id = m.id
        $where
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$listParams = $params;
$listParams[] = $pageSize;
$listParams[] = $offset;
$stmt->execute($listParams);
$favorites = $stmt->fetchAll();

$favoritedIds = getFavoritedMessageIds();
$favoritedIds = array_flip($favoritedIds);

$statsStmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN m.type='help' THEN 1 ELSE 0 END) as help_count,
    SUM(CASE WHEN m.type='suggest' THEN 1 ELSE 0 END) as suggest_count,
    SUM(CASE WHEN m.type='lost' THEN 1 ELSE 0 END) as lost_count
    FROM favorites f INNER JOIN messages m ON f.message_id = m.id 
    WHERE f.visitor_id = ? AND m.status = 1");
$statsStmt->execute([$visitorId]);
$stats = $statsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<section class="favorites-section">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">⭐ 我的收藏</h1>
            <p class="page-subtitle">共收藏 <?= $stats['total'] ?? 0 ?> 条留言</p>
        </div>

        <div class="favorites-stats">
            <div class="stat-card" data-type="total">
                <div class="stat-number" data-count="total"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">全部收藏</div>
            </div>
            <div class="stat-card stat-help" data-type="help">
                <div class="stat-number" data-count="help"><?= $stats['help_count'] ?? 0 ?></div>
                <div class="stat-label">🆘 求助</div>
            </div>
            <div class="stat-card stat-suggest" data-type="suggest">
                <div class="stat-number" data-count="suggest"><?= $stats['suggest_count'] ?? 0 ?></div>
                <div class="stat-label">💡 建议</div>
            </div>
            <div class="stat-card stat-lost" data-type="lost">
                <div class="stat-number" data-count="lost"><?= $stats['lost_count'] ?? 0 ?></div>
                <div class="stat-label">🔍 失物</div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-types">
                <a href="favorites.php" class="filter-tag <?= !$type ? 'active' : '' ?>">全部</a>
                <a href="favorites.php<?= buildQueryString(['type' => 'help']) ?>" class="filter-tag <?= $type === 'help' ? 'active' : '' ?>">🆘 求助</a>
                <a href="favorites.php<?= buildQueryString(['type' => 'suggest']) ?>" class="filter-tag <?= $type === 'suggest' ? 'active' : '' ?>">💡 建议</a>
                <a href="favorites.php<?= buildQueryString(['type' => 'lost']) ?>" class="filter-tag <?= $type === 'lost' ? 'active' : '' ?>">🔍 失物招领</a>
            </div>
        </div>
    </div>
</section>

<section class="message-list-section">
    <div class="container">
        <?php if (empty($favorites)): ?>
        <div class="empty-state">
            <div class="empty-icon">⭐</div>
            <p>暂无收藏的留言</p>
            <a href="index.php" class="btn btn-primary">去浏览留言</a>
        </div>
        <?php else: ?>
        <p class="list-summary">共收藏 <strong><?= $total ?></strong> 条符合条件的留言</p>
        <div class="message-list">
            <?php foreach ($favorites as $msg): ?>
            <div class="message-card" data-type="<?= $msg['type'] ?>">
                <a href="detail.php<?= buildQueryString(['id' => $msg['id'], 'from' => 'favorites', 'type' => $type ?: null, 'page' => $page > 1 ? $page : null]) ?>" class="card-link">
                    <div class="card-header">
                        <span class="card-type type-<?= $msg['type'] ?>"><?= getTypeIcon($msg['type']) ?> <?= getTypeLabel($msg['type']) ?></span>
                        <span class="card-time">收藏于 <?= timeAgo($msg['favorited_at']) ?></span>
                    </div>
                    <h3 class="card-title"><?= cleanInput($msg['title']) ?></h3>
                    <p class="card-content"><?= cleanInput(mb_substr($msg['content'], 0, 80)) ?><?= mb_strlen($msg['content']) > 80 ? '...' : '' ?></p>
                    <div class="card-footer">
                        <span class="card-author">👤 <?= cleanInput($msg['nickname']) ?></span>
                        <?php if ($msg['image']): ?>
                        <span class="card-image">📷 有图</span>
                        <?php endif; ?>
                        <span class="card-views">👁 <?= $msg['views'] ?></span>
                    </div>
                </a>
                <button class="favorite-btn favorited" data-message-id="<?= $msg['id'] ?>" data-type="<?= $msg['type'] ?>" onclick="toggleFavorite(event, this)">
                    <span class="favorite-icon">⭐</span>
                    <span class="favorite-text">已收藏</span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="favorites.php<?= buildQueryString(['page' => $page - 1, 'type' => $type ?: null]) ?>" class="page-btn">上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="favorites.php<?= buildQueryString(['page' => $i, 'type' => $type ?: null]) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="favorites.php<?= buildQueryString(['page' => $page + 1, 'type' => $type ?: null]) ?>" class="page-btn">下一页</a>
            <?php endif; ?>
            <span class="page-info">共 <?= $total ?> 条 / 第 <?= $page ?>/<?= $totalPages ?> 页</span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
