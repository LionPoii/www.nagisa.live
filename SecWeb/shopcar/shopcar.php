<?php
/**
 * 购物车商品二级页面 — 左系列导航 / 右商品详情
 */
$page_title = '商品';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/shopcar_helpers.php';

$db = new Database();
$conn = $db->getConnection();

$catalog = ['groups' => [], 'products_json' => []];
$tableExists = $conn && shopcarTableExists($conn, 'shopcar_products');

if ($tableExists) {
    try {
        $catalog = shopcarBuildCatalog($conn);
    } catch (PDOException $e) {
        error_log('SecWeb shopcar 加载错误: ' . $e->getMessage());
    }
}

$groups = $catalog['groups'];
$productsJson = $catalog['products_json'];
$hasContent = !empty($groups) || !empty($productsJson);

$firstExpandedGroupIndex = null;
$firstProductId = null;
if ($hasContent) {
    foreach ($groups as $gi => $g) {
        if (!empty($g['active']) && !empty($g['products'])) {
            if ($firstExpandedGroupIndex === null) {
                $firstExpandedGroupIndex = $gi;
            }
            if ($firstProductId === null) {
                $firstProductId = (int)$g['products'][0]['id'];
            }
        }
    }
    if ($firstProductId === null) {
        $ids = array_keys($productsJson);
        $firstProductId = $ids ? (int)$ids[0] : null;
    }
}

ob_start();
?>

<div class="shopcar-split<?php echo $hasContent ? '' : ' is-empty'; ?>">
    <?php if ($hasContent): ?>
    <aside class="shopcar-sidebar" id="shopcar-sidebar">
        <div class="sidebar-head">
            <span class="sidebar-title">商品列表</span>
        </div>
        <nav class="series-tree" id="series-tree" aria-label="商品列表">
            <?php foreach ($groups as $gi => $group): ?>
            <?php
                $seriesInactive = empty($group['active']);
                $isExpanded = ($firstExpandedGroupIndex !== null && $gi === $firstExpandedGroupIndex);
            ?>
            <div class="series-group<?php echo $isExpanded ? ' expanded' : ''; ?><?php echo $seriesInactive ? ' is-inactive' : ''; ?>"
                 data-group-id="<?php echo (int)$group['id']; ?>"
                 data-series-active="<?php echo $seriesInactive ? '0' : '1'; ?>">
                <button type="button"
                        class="series-major-btn"
                        aria-expanded="<?php echo $isExpanded ? 'true' : 'false'; ?>">
                    <span class="series-chevron" aria-hidden="true"></span>
                    <span class="series-major-title"><?php echo htmlspecialchars($group['title']); ?></span>
                    <span class="series-count"><?php echo count($group['products']); ?></span>
                </button>
                <div class="series-children">
                    <?php if (!empty($group['products'])): ?>
                    <ul class="product-list">
                        <?php foreach ($group['products'] as $p): ?>
                        <?php $itemUnavailable = empty($p['selectable']); ?>
                        <li>
                            <button type="button"
                                    class="product-item-btn<?php echo $itemUnavailable ? ' is-unavailable' : ''; ?><?php echo (!$itemUnavailable && (int)$p['id'] === $firstProductId) ? ' active' : ''; ?>"
                                    data-product-id="<?php echo (int)$p['id']; ?>"
                                    <?php echo $itemUnavailable ? ' disabled aria-disabled="true"' : ''; ?>>
                                <?php echo htmlspecialchars($p['title']); ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="subseries-empty">暂无商品</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <section class="shopcar-detail" id="shopcar-detail">
        <div class="detail-inner" id="detail-inner">
            <p class="detail-breadcrumb" id="detail-breadcrumb"></p>
            <h1 class="detail-title" id="detail-title"></h1>
            <div class="detail-image-wrap">
                <img src="" alt="" class="detail-image" id="detail-image" referrerpolicy="no-referrer">
            </div>
            <div class="detail-divider" aria-hidden="true"></div>
            <div class="detail-lower">
                <p class="detail-price" id="detail-price"></p>
                <div class="detail-desc" id="detail-desc"></div>
                <div class="detail-actions">
                    <a href="#" class="detail-buy-btn" id="detail-link" target="_blank" rel="noopener noreferrer">前往购买</a>
                </div>
            </div>
        </div>
        <div class="detail-placeholder" id="detail-placeholder" style="display:none;">
            <img src="/elements/shopcar/shopcar-empty.png" alt="" width="80">
            <p>请从左侧选择商品</p>
        </div>
    </section>
    <?php else: ?>
    <div class="shopcar-empty-full">
        <img src="/elements/shopcar/shopcar-empty.png" alt="" class="shopcar-empty-icon">
        <h2>暂无商品</h2>
        <p>请稍后再来查看，或联系管理员上架商品</p>
        <a href="/" class="shopcar-empty-link">返回首页</a>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$additional_styles = <<<'CSS'
<style>
    .shopcar-split {
        display: flex;
        gap: 0;
        min-height: calc(100vh - 120px);
        margin: 0 -0.5rem;
        opacity: 0;
        animation: shopcarFadeIn 0.5s ease forwards;
    }

    .shopcar-split.is-empty {
        justify-content: center;
        align-items: center;
    }

    /* 左侧系列栏 */
    .shopcar-sidebar {
        flex: 0 0 32%;
        max-width: 380px;
        min-width: 260px;
        background: var(--color-card);
        border-radius: 14px 0 0 14px;
        box-shadow: 4px 0 20px var(--color-shadow);
        border: 1px solid var(--color-border);
        border-right: none;
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 120px);
        overflow: hidden;
    }

    .sidebar-head {
        padding: 1rem 1.25rem;
        border-bottom: 2px solid var(--color-border);
        background: linear-gradient(135deg, rgba(77, 64, 48, 0.06), rgba(204, 148, 113, 0.12));
    }

    .sidebar-title {
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 1.15rem;
        color: var(--color-primary);
        letter-spacing: 0.15em;
    }

    .series-tree {
        flex: 1;
        overflow-y: auto;
        padding: 0.75rem 0;
        scrollbar-width: thin;
        scrollbar-color: var(--color-accent) transparent;
        font-family: 'QiantuHouhei', sans-serif;
    }

    .series-group {
        border-bottom: 1px solid var(--color-border);
    }

    .series-group:last-child {
        border-bottom: none;
    }

    .series-major-btn {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.9rem 1.25rem;
        border: none;
        background: transparent;
        cursor: pointer;
        text-align: left;
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 1.05rem;
        color: var(--color-primary);
        transition: background 0.2s ease;
    }

    .series-major-btn:hover {
        background: rgba(204, 148, 113, 0.12);
    }

    .series-chevron {
        width: 8px;
        height: 8px;
        border-right: 2px solid var(--color-accent);
        border-bottom: 2px solid var(--color-accent);
        transform: rotate(-45deg);
        transition: transform 0.25s ease;
        flex-shrink: 0;
    }

    .series-group.expanded .series-chevron {
        transform: rotate(45deg);
    }

    .series-major-title {
        flex: 1;
        line-height: 1.3;
    }

    .series-count {
        font-size: 0.75rem;
        color: var(--color-accent);
        background: rgba(204, 148, 113, 0.15);
        padding: 0.15rem 0.5rem;
        border-radius: 10px;
        font-family: inherit;
    }

    .series-group.is-inactive .series-major-btn {
        cursor: pointer;
        opacity: 0.85;
    }

    .series-group.is-inactive .series-major-btn:hover {
        background: rgba(148, 163, 184, 0.08);
    }

    .series-group.is-inactive .series-count {
        color: #94a3b8;
        background: #f1f5f9;
    }

    .series-group.is-inactive .series-chevron {
        border-color: #cbd5e1;
        opacity: 0.5;
    }

    .series-group.is-inactive .subseries-empty {
        color: #94a3b8;
    }

    .product-item-btn.is-unavailable,
    .product-item-btn:disabled {
        text-decoration: line-through;
        text-decoration-color: #94a3b8;
        color: #94a3b8 !important;
        cursor: not-allowed;
        opacity: 0.75;
    }

    .product-item-btn.is-unavailable:hover,
    .product-item-btn:disabled:hover {
        background: transparent;
        padding-left: 2.25rem;
        border-left-color: transparent;
    }

    .series-children {
        display: none;
        padding: 0 0 0.75rem 0;
        background: rgba(0, 0, 0, 0.02);
    }

    .series-group.expanded .series-children {
        display: block;
    }

    .subseries-block {
        padding: 0.25rem 0 0.5rem;
    }

    .subseries-label {
        padding: 0.35rem 1.25rem 0.35rem 2rem;
        font-size: 0.8rem;
        color: var(--color-accent);
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: none;
    }

    .product-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .product-item-btn {
        width: 100%;
        text-align: left;
        padding: 0.55rem 1.25rem 0.55rem 2.25rem;
        border: none;
        background: transparent;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--color-text);
        transition: background 0.2s ease, color 0.2s ease, padding-left 0.2s ease;
        border-left: 3px solid transparent;
    }

    .product-item-btn:hover {
        background: rgba(204, 148, 113, 0.1);
        padding-left: 2.4rem;
    }

    .product-item-btn.active {
        background: rgba(204, 148, 113, 0.2);
        color: var(--color-primary);
        border-left-color: var(--color-accent);
        font-weight: 500;
    }

    .subseries-empty {
        padding: 0.25rem 1.25rem 0.5rem 2rem;
        font-size: 0.8rem;
        font-family: inherit;
        color: var(--color-text);
        opacity: 0.5;
    }

    /* 右侧详情 */
    .shopcar-detail {
        flex: 1;
        min-width: 0;
        background: var(--color-card);
        border-radius: 0 14px 14px 0;
        box-shadow: 0 4px 24px var(--color-shadow);
        border: 1px solid var(--color-border);
        padding: 2rem 2.5rem;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        position: relative;
    }

    .shopcar-detail::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(to right, var(--color-primary), var(--color-accent));
        border-radius: 0 14px 0 0;
    }

    .detail-breadcrumb {
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 0.85rem;
        color: var(--color-accent);
        margin-bottom: 0.5rem;
        min-height: 1.2em;
    }

    .detail-title {
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 1.75rem;
        font-weight: normal;
        color: var(--color-primary);
        margin-bottom: 1.25rem;
        line-height: 1.35;
    }

    .detail-inner {
        display: flex;
        flex-direction: column;
    }

    .detail-image-wrap {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 240px;
        margin-bottom: 0;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 12px;
        padding: 1rem;
        flex-shrink: 0;
    }

    .detail-divider {
        flex-shrink: 0;
        height: 1px;
        margin: 1.35rem 0 1.5rem;
        border: none;
        background: linear-gradient(
            90deg,
            transparent 0%,
            rgba(204, 148, 113, 0.15) 12%,
            rgba(204, 148, 113, 0.45) 50%,
            rgba(204, 148, 113, 0.15) 88%,
            transparent 100%
        );
    }

    .detail-image {
        max-width: 100%;
        max-height: 320px;
        object-fit: contain;
        border-radius: 8px;
        transition: opacity 0.3s ease;
    }

    .detail-image.fading {
        opacity: 0;
    }

    .detail-lower {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        text-align: right;
        width: 100%;
    }

    .detail-price {
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 1.6rem;
        color: var(--color-accent);
        margin: 0 0 1rem;
        width: 100%;
        text-align: right;
    }

    .detail-desc {
        font-size: 1rem;
        line-height: 1.75;
        color: var(--color-text);
        opacity: 0.9;
        white-space: pre-line;
        margin: 0 0 1.5rem;
        max-width: 720px;
        width: 100%;
        text-align: right;
    }

    .detail-desc:empty::before {
        content: "暂无详细介绍";
        opacity: 0.45;
        font-style: italic;
    }

    .detail-actions {
        width: 100%;
        max-width: 720px;
        display: flex;
        justify-content: flex-end;
        margin: 0 0 0.25rem;
    }

    .detail-buy-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.65rem 1.75rem;
        background: linear-gradient(135deg, var(--color-primary) 0%, #6b5344 100%);
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 0.95rem;
        letter-spacing: 0.06em;
        box-shadow: 0 2px 10px rgba(77, 64, 48, 0.18);
        transition: background 0.25s ease, transform 0.2s ease, box-shadow 0.25s ease;
    }

    .detail-buy-btn::after {
        content: "→";
        font-size: 1em;
        line-height: 1;
        opacity: 0.9;
        transition: transform 0.2s ease;
    }

    .detail-buy-btn:hover:not(.disabled) {
        background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-primary) 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(204, 148, 113, 0.35);
    }

    .detail-buy-btn:hover:not(.disabled)::after {
        transform: translateX(3px);
    }

    .detail-buy-btn.disabled {
        background: #e2e8f0;
        color: #94a3b8;
        border-color: transparent;
        box-shadow: none;
        opacity: 1;
        pointer-events: none;
        cursor: not-allowed;
    }

    .detail-buy-btn.disabled::after {
        display: none;
    }

    .detail-placeholder {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--color-text);
        opacity: 0.6;
    }

    .shopcar-empty-full {
        text-align: center;
        padding: 4rem 2rem;
        width: 100%;
    }

    .shopcar-empty-icon {
        width: 100px;
        margin-bottom: 1.5rem;
        opacity: 0.7;
    }

    .shopcar-empty-full h2 {
        font-family: 'QiantuHouhei', sans-serif;
        font-size: 1.5rem;
        color: var(--color-primary);
        margin-bottom: 0.5rem;
        font-weight: normal;
    }

    .shopcar-empty-full p {
        opacity: 0.7;
        margin-bottom: 1.5rem;
    }

    .shopcar-empty-link {
        display: inline-block;
        padding: 0.6rem 1.5rem;
        background: var(--color-primary);
        color: #fff;
        text-decoration: none;
        border-radius: 24px;
    }

    .shopcar-empty-link:hover {
        background: var(--color-accent);
    }

    @keyframes shopcarFadeIn {
        to { opacity: 1; }
    }

    @media (max-width: 900px) {
        .shopcar-split {
            flex-direction: column;
        }
        .shopcar-sidebar {
            flex: none;
            max-width: none;
            width: 100%;
            border-radius: 14px 14px 0 0;
            border-right: 1px solid var(--color-border);
            max-height: 42vh;
        }
        .shopcar-detail {
            border-radius: 0 0 14px 14px;
            max-height: none;
            padding: 1.5rem;
        }
    }
</style>
CSS;

$additional_scripts = '';
if ($hasContent) {
    $productsJsonEncoded = json_encode($productsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    $firstId = $firstProductId ?? 'null';
    $additional_scripts = <<<JS
<script>
(function() {
    const PRODUCTS = {$productsJsonEncoded};
    const firstId = {$firstId};

    const detailTitle = document.getElementById('detail-title');
    const detailImage = document.getElementById('detail-image');
    const detailPrice = document.getElementById('detail-price');
    const detailDesc = document.getElementById('detail-desc');
    const detailLink = document.getElementById('detail-link');
    const detailBreadcrumb = document.getElementById('detail-breadcrumb');
    const detailInner = document.getElementById('detail-inner');
    const detailPlaceholder = document.getElementById('detail-placeholder');

    function setActiveProductBtn(id) {
        document.querySelectorAll('.product-item-btn').forEach(function(btn) {
            btn.classList.toggle('active', parseInt(btn.dataset.productId, 10) === id);
        });
    }

    const SERIES_EXPANDED_KEY = 'shopcar_series_expanded';

    function getSeriesExpandedState() {
        try {
            const raw = localStorage.getItem(SERIES_EXPANDED_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function saveSeriesExpandedState() {
        const state = {};
        document.querySelectorAll('.series-group').forEach(function(group) {
            const id = group.getAttribute('data-group-id');
            if (id !== null) {
                state[id] = group.classList.contains('expanded');
            }
        });
        try {
            localStorage.setItem(SERIES_EXPANDED_KEY, JSON.stringify(state));
        } catch (e) { /* ignore */ }
    }

    function applySeriesExpandedState() {
        const state = getSeriesExpandedState();
        if (!Object.keys(state).length) return;
        document.querySelectorAll('.series-group').forEach(function(group) {
            const id = group.getAttribute('data-group-id');
            if (id === null || !Object.prototype.hasOwnProperty.call(state, id)) return;
            const btn = group.querySelector('.series-major-btn');
            const expanded = !!state[id];
            group.classList.toggle('expanded', expanded);
            if (btn) btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    function renderProduct(id) {
        const p = PRODUCTS[id];
        if (!p) return;

        setActiveProductBtn(id);

        detailBreadcrumb.textContent = p.series_label || '';

        detailTitle.textContent = p.title || '';
        detailPrice.textContent = p.price || '';
        detailDesc.textContent = p.description || '';

        detailImage.classList.add('fading');
        setTimeout(function() {
            detailImage.src = p.image || '';
            detailImage.alt = p.title || '';
            detailImage.classList.remove('fading');
        }, 150);

        if (p.link) {
            detailLink.href = p.link;
            detailLink.textContent = '前往购买';
            detailLink.classList.remove('disabled');
            detailLink.style.pointerEvents = '';
        } else {
            detailLink.href = '#';
            detailLink.textContent = '暂无购买链接';
            detailLink.classList.add('disabled');
            detailLink.style.pointerEvents = 'none';
        }

        detailInner.style.display = '';
        if (detailPlaceholder) detailPlaceholder.style.display = 'none';
    }

    document.querySelectorAll('.series-major-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const group = btn.closest('.series-group');
            if (!group) return;
            const expanded = group.classList.toggle('expanded');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            saveSeriesExpandedState();
        });
    });

    applySeriesExpandedState();

    document.querySelectorAll('.product-item-btn:not(:disabled)').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = parseInt(btn.dataset.productId, 10);
            renderProduct(id);
        });
    });

    if (firstId && PRODUCTS[firstId]) {
        renderProduct(firstId);
    } else if (detailInner && detailPlaceholder) {
        detailInner.style.display = 'none';
        detailPlaceholder.style.display = '';
    }
})();
</script>
JS;
}

require_once __DIR__ . '/shopcar_base.php';
