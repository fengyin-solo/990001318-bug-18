<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = '社区便民留言板 - 首页';
$currentPage = 'home';
$cssPath = 'assets/css/style.css';
$jsPath = 'assets/js/main.js';

$db = getDB();

// 合法的排序与分类
$validSorts = ['time', 'hot'];
$validTypes = ['help', 'suggest', 'lost'];

// 获取并校验筛选参数；非法参数一律规范化后重定向，避免静默失效
$sort = $_GET['sort'] ?? 'time';
$type = $_GET['type'] ?? '';
$canonical = ['sort' => null, 'type' => null, 'page' => null];
$needRedirect = false;

if (!in_array($sort, $validSorts, true)) {
    $sort = 'time';
    $needRedirect = true;
}
if ($type !== '' && !in_array($type, $validTypes, true)) {
    $type = '';
    $needRedirect = true;
}
$canonical['sort'] = $sort === 'time' ? null : $sort;
$canonical['type'] = $type ?: null;

// 页码：非正整数视为非法
$rawPage = $_GET['page'] ?? null;
$page = ($rawPage !== null && $rawPage !== '') ? intval($rawPage) : 1;
if ($rawPage !== null && $rawPage !== '' && (!ctype_digit((string)$rawPage) || $page < 1)) {
    $page = 1;
    $needRedirect = true;
}
$pageSize = 10;

// 构建查询
$where = "WHERE status = 1";
$params = [];

if ($type) {
    $where .= " AND type = ?";
    $params[] = $type;
}

// 排序
$orderBy = ($sort === 'hot') ? "views DESC, created_at DESC" : "created_at DESC";

// 总数（与列表查询同一筛选口径）
$countStmt = $db->prepare("SELECT COUNT(*) FROM messages $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $pageSize);

// 页码越界：有数据时重定向到最后一页；无数据时回到第1页
$targetPage = min($page, max(1, $totalPages));
if ($targetPage !== $page) {
    $needRedirect = true;
}
if ($needRedirect) {
    $canonical['page'] = $targetPage > 1 ? $targetPage : null;
    redirectTo('index.php' . buildQueryString($canonical));
}
$page = $targetPage;
$offset = ($page - 1) * $pageSize;

// 列表（分页参数同样使用绑定，避免拼接风险）
$sql = "SELECT id, nickname, type, title, content, image, views, created_at FROM messages $where ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$listParams = $params;
$listParams[] = $pageSize;
$listParams[] = $offset;
$stmt->execute($listParams);
$messages = $stmt->fetchAll();

// 获取当前用户已收藏的留言ID
$favoritedIds = getFavoritedMessageIds();
$favoritedIds = array_flip($favoritedIds);

// 滚动数据（最新8条）
$scrollStmt = $db->query("SELECT id, type, title, created_at FROM messages WHERE status = 1 ORDER BY created_at DESC LIMIT 8");
$scrollMessages = $scrollStmt->fetchAll();

// 统计
$statsStmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type='help' THEN 1 ELSE 0 END) as help_count,
    SUM(CASE WHEN type='suggest' THEN 1 ELSE 0 END) as suggest_count,
    SUM(CASE WHEN type='lost' THEN 1 ELSE 0 END) as lost_count
    FROM messages WHERE status = 1");
$stats = $statsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<!-- 滚动信息栏 -->
<div class="scroll-bar">
    <div class="container">
        <span class="scroll-label">📢 最新动态</span>
        <div class="scroll-wrapper">
            <div class="scroll-content" id="scrollContent">
                <?php foreach ($scrollMessages as $msg): ?>
                <a href="detail.php?id=<?= $msg['id'] ?>" class="scroll-item">
                    <span class="scroll-type"><?= getTypeIcon($msg['type']) ?></span>
                    <span class="scroll-title"><?= cleanInput($msg['title']) ?></span>
                    <span class="scroll-time"><?= timeAgo($msg['created_at']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 统计卡片 -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">全部留言</div>
            </div>
            <div class="stat-card stat-help">
                <div class="stat-number"><?= $stats['help_count'] ?? 0 ?></div>
                <div class="stat-label">🆘 居民求助</div>
            </div>
            <div class="stat-card stat-suggest">
                <div class="stat-number"><?= $stats['suggest_count'] ?? 0 ?></div>
                <div class="stat-label">💡 意见建议</div>
            </div>
            <div class="stat-card stat-lost">
                <div class="stat-number"><?= $stats['lost_count'] ?? 0 ?></div>
                <div class="stat-label">🔍 失物招领</div>
            </div>
        </div>
    </div>
</section>

<!-- 筛选和排序 -->
<section class="filter-section">
    <div class="container">
        <div class="filter-bar">
            <div class="filter-types">
                <a href="index.php<?= buildQueryString(['sort' => $sort === 'time' ? null : $sort]) ?>" class="filter-tag <?= !$type ? 'active' : '' ?>">全部</a>
                <a href="index.php<?= buildQueryString(['sort' => $sort === 'time' ? null : $sort, 'type' => 'help']) ?>" class="filter-tag <?= $type === 'help' ? 'active' : '' ?>">🆘 求助</a>
                <a href="index.php<?= buildQueryString(['sort' => $sort === 'time' ? null : $sort, 'type' => 'suggest']) ?>" class="filter-tag <?= $type === 'suggest' ? 'active' : '' ?>">💡 建议</a>
                <a href="index.php<?= buildQueryString(['sort' => $sort === 'time' ? null : $sort, 'type' => 'lost']) ?>" class="filter-tag <?= $type === 'lost' ? 'active' : '' ?>">🔍 失物招领</a>
            </div>
            <div class="filter-sort">
                <a href="index.php<?= buildQueryString(['type' => $type ?: null]) ?>" class="sort-btn <?= $sort === 'time' ? 'active' : '' ?>">🕐 按时间</a>
                <a href="index.php<?= buildQueryString(['sort' => 'hot', 'type' => $type ?: null]) ?>" class="sort-btn <?= $sort === 'hot' ? 'active' : '' ?>">🔥 按热度</a>
            </div>
        </div>
    </div>
</section>

<!-- 留言列表 -->
<section class="message-list-section">
    <div class="container">
        <?php if (empty($messages)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <p>暂无留言信息</p>
            <a href="submit.php" class="btn btn-primary">发布第一条留言</a>
        </div>
        <?php else: ?>
        <p class="list-summary">共找到 <strong><?= $total ?></strong> 条符合条件的留言</p>
        <div class="message-list">
            <?php foreach ($messages as $msg): ?>
            <div class="message-card">
                <a href="detail.php<?= buildQueryString(['id' => $msg['id'], 'from' => 'index', 'sort' => $sort === 'time' ? null : $sort, 'type' => $type ?: null, 'page' => $page > 1 ? $page : null]) ?>" class="card-link">
                    <div class="card-header">
                        <span class="card-type type-<?= $msg['type'] ?>"><?= getTypeIcon($msg['type']) ?> <?= getTypeLabel($msg['type']) ?></span>
                        <span class="card-time"><?= timeAgo($msg['created_at']) ?></span>
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
                <button class="favorite-btn <?= isset($favoritedIds[$msg['id']]) ? 'favorited' : '' ?>" data-message-id="<?= $msg['id'] ?>" data-type="<?= $msg['type'] ?>" onclick="toggleFavorite(event, this)">
                    <span class="favorite-icon"><?= isset($favoritedIds[$msg['id']]) ? '⭐' : '☆' ?></span>
                    <span class="favorite-text"><?= isset($favoritedIds[$msg['id']]) ? '已收藏' : '收藏' ?></span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="index.php<?= buildQueryString(['page' => $page - 1, 'sort' => $sort === 'time' ? null : $sort, 'type' => $type ?: null]) ?>" class="page-btn">上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="index.php<?= buildQueryString(['page' => $i, 'sort' => $sort === 'time' ? null : $sort, 'type' => $type ?: null]) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="index.php<?= buildQueryString(['page' => $page + 1, 'sort' => $sort === 'time' ? null : $sort, 'type' => $type ?: null]) ?>" class="page-btn">下一页</a>
            <?php endif; ?>
            <span class="page-info">共 <?= $total ?> 条 / 第 <?= $page ?>/<?= $totalPages ?> 页</span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
