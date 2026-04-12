<?php
// 设置页面标题
$page_title = "表情 - 日常";
$active_tab = 'emotes';

// 启用输出缓冲
ob_start();

// 获取数据库连接
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// 从数据库获取表情包数据
$expressions = [];

try {
    // 检查表情包表是否存在
    $stmt = $conn->query("SHOW TABLES LIKE 'expression_images'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // 获取所有表情包
        $stmt = $conn->prepare("SELECT * FROM expression_images WHERE status = 1");
        $stmt->execute();
        $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // 出错时不显示错误，只在日志中记录
    error_log("表情页面数据库错误: " . $e->getMessage());
}

// 获取可用的表情包分类（支持多标签）
$expression_categories = [];
foreach ($expressions as $expression) {
    if (!empty($expression['category'])) {
        // 支持逗号分隔的多个标签
        $categories = array_map('trim', explode(',', $expression['category']));
        foreach ($categories as $category) {
            if (!empty($category) && !in_array($category, $expression_categories)) {
                $expression_categories[] = $category;
            }
        }
    }
}
// 排序标签
sort($expression_categories);
?>

<!-- 表情内容区 -->
<div class="tab-content active" id="expressions-content">
    <!-- 大展示区 -->
    <div class="showcase">
        <?php if(!empty($expressions)): ?>
        <div class="showcase-title" id="showcase-expression-info">
            <?php echo !empty($expressions) ? htmlspecialchars($expressions[0]['title']) : '暂无表情'; ?>
        </div>
        <div class="showcase-content">
            <img src="<?php echo htmlspecialchars($expressions[0]['image_path']); ?>" alt="表情展示" class="showcase-image" id="showcase-expression">
        </div>
        <div class="showcase-info" id="showcase-expression-desc">
            <?php echo !empty($expressions) && !empty($expressions[0]['description']) ? htmlspecialchars($expressions[0]['description']) : '&nbsp;'; ?>
        </div>
        <?php else: ?>
        <div style="color:var(--color-text);opacity:0.7;">暂无表情数据</div>
        <?php endif; ?>
    </div>

    <!-- 过滤器 -->
    <div class="filters">
        <div class="filter-header">
            <div class="filter-label">按分类筛选：</div>
            <div class="filter-right">
                <button class="showcase-button" id="random-expression-btn" <?php echo empty($expressions) ? 'disabled' : ''; ?>>
                    <img src="/elements/express/reflash.png" alt="随机切换" class="refresh-icon">
                </button>
            </div>
        </div>
        <div class="filter-container" id="expression-filters">
            <button class="filter-button active" data-category="all">全部</button>
            <?php foreach($expression_categories as $category): ?>
            <button class="filter-button" data-category="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 卡片网格 -->
    <div class="card-grid" id="expression-grid">
        <?php if(!empty($expressions)): ?>
        <?php foreach($expressions as $index => $expression): ?>
        <div class="expression-card" data-categories="<?php echo htmlspecialchars($expression['category']); ?>" data-index="<?php echo $index; ?>">
            <div class="expression-card-inner">
                <div class="expression-card-front">
                    <div class="expression-title" style="font-size:1.2rem;line-height:1.6;word-break:break-all;"><?php echo htmlspecialchars($expression['title']); ?></div>
                </div>
                <div class="expression-card-back">
                    <img src="<?php 
                        // 处理图片路径，确保路径格式正确
                        $image_path = $expression['image_path'];
                        // 如果路径不是以/开头，添加/
                        if (substr($image_path, 0, 1) !== '/') {
                            $image_path = '/' . $image_path;
                        }
                        echo htmlspecialchars($image_path); 
                    ?>" alt="<?php echo htmlspecialchars($expression['title']); ?>" class="expression-image">
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-message" style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--color-text); opacity: 0.7;">
            暂无表情包数据
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// 表情组件需要的额外CSS样式
$additional_styles = '
<style>
    /* 隐藏链接悬停时的URL预览 */
    a {
        pointer-events: auto;
    }
    a[href] {
        cursor: pointer;
    }
    a[href]:hover::after {
        display: none !important;
    }
    
    /* 表情卡片样式 - 按行排列 */
    .expression-card {
        perspective: 1000px;
        height: 200px;
        cursor: pointer;
        position: relative;
        opacity: 0;
        transform: scale(0.8) translateY(20px);
        transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1), 
                    transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: opacity, transform;
        /* 确保卡片在其网格单元格内居中 */
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    /* 只在初次加载时应用入场动画 */
    .expression-card.initial-load {
        animation: cardPopIn 0.6s ease forwards;
    }

    .expression-card-inner {
        position: relative;
        width: 100%;
        height: 100%;
        transition: transform 0.8s;
        transform-style: preserve-3d;
        box-shadow: 0 4px 15px var(--color-shadow);
        border-radius: 15px;
        /* 确保内容在卡片内居中 */
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .expression-card:hover .expression-card-inner {
        transform: rotateY(180deg);
    }

    .expression-card-front, .expression-card-back {
        position: absolute;
        width: 100%;
        height: 100%;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        border-radius: 15px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 1rem;
    }
    
    .expression-card-back {
        padding: 0.5rem;
    }

    .expression-card-front {
        background-color: var(--color-card);
    }

    .expression-card-back {
        background-color: var(--color-primary);
        color: white;
        transform: rotateY(180deg);
        transition: background-color 0.3s ease;
    }

    .expression-image {
        max-width: 100%;
        max-height: 95%;
        object-fit: contain;
    }

    .expression-title {
        margin-top: 1rem;
        text-align: center;
        font-weight: normal;
        font-family: "QiantuHouhei", sans-serif;
    }
    
    /* 内容区域 */
    .tab-content {
        display: block;
        animation: fadeIn 0.5s ease forwards;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* 大展示区动画 */
    .showcase {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
        animation: showcaseExpand 0.8s ease forwards;
        animation-delay: 0.2s;
    }
    
    /* 过滤器动画 */
    .filters {
        opacity: 0;
        transform: translateY(20px);
        animation: slideUp 0.6s ease forwards;
        animation-delay: 0.4s;
    }
    

    
    /* 使用行和列的组合来设置卡片延迟，支持大量卡片 */
    /* 第一行卡片 */
    .expression-card:nth-child(4n+1) { animation-delay: 0.8s; }
    .expression-card:nth-child(4n+2) { animation-delay: 0.9s; }
    .expression-card:nth-child(4n+3) { animation-delay: 1.0s; }
    .expression-card:nth-child(4n+4) { animation-delay: 1.1s; }
    
    /* 第二行卡片 */
    .expression-card:nth-child(n+5):nth-child(-n+8) { animation-delay: 1.2s; }
    
    /* 第三行卡片 */
    .expression-card:nth-child(n+9):nth-child(-n+12) { animation-delay: 1.4s; }
    
    /* 第四行及以后的卡片 */
    .expression-card:nth-child(n+13) { animation-delay: 1.6s; }
    
    /* 动画关键帧 */
    @keyframes showcaseExpand {
        from {
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes gridFadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes cardPopIn {
        from {
            opacity: 0;
            transform: scale(0.8) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    /* 卡片网格 - 按行展示 */
    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        grid-auto-rows: 200px;
        gap: 1.5rem;
        margin-bottom: 3rem;
        opacity: 0;
        transform: translateY(30px);
        animation: gridFadeIn 0.8s ease forwards;
        animation-delay: 0.6s;
    }

    /* 过滤器 */
    .filters {
        margin-bottom: 2rem;
        text-align: left;
        opacity: 0;
        transform: translateY(20px);
        animation: slideUp 0.6s ease forwards;
        animation-delay: 0.4s;
    }

    .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .filter-right {
        margin-left: auto;
    }
    
    .filter-label {
        display: inline-block;
        margin-right: 0.5rem;
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--color-primary);
    }

    .filter-container {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-start;
        margin: 0.5rem 0;
    }

    .filter-button {
        padding: 0.4rem 1rem;
        background-color: transparent;
        border: 1px solid var(--color-border);
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .filter-button:hover {
        background-color: rgba(204, 148, 113, 0.1);
    }

    .filter-button.active {
        background-color: var(--color-accent);
        color: white;
        border-color: var(--color-accent);
    }
    
    /* 禁用状态的标签按钮 */
    .filter-button {
        transition: opacity 0.25s ease, background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease;
    }
    
    .filter-button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #e2e2e2;
        border-color: #d0d0d0;
        color: #888;
        pointer-events: none;
    }
    
    /* 卡片显示/隐藏状态 */
    .expression-card.card-hidden {
        opacity: 0;
        transform: scale(0.8);
        pointer-events: none;
    }
    
    .expression-card.card-visible {
        opacity: 1;
        transform: scale(1);
    }
    
    .expression-card.card-faded {
        opacity: 0.5;
        transform: scale(0.95);
    }
    
    /* 大展示区 */
    .showcase {
        background-color: var(--color-card);
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 4px 15px var(--color-shadow);
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
        animation: showcaseExpand 0.8s ease forwards;
        animation-delay: 0.2s;
    }

    .showcase::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(to right, var(--color-primary), var(--color-accent));
    }

    .showcase-title {
        font-size: 1.5rem;
        font-weight: normal;
        margin-bottom: 1.5rem;
        color: var(--color-primary);
        font-family: "QiantuHouhei", sans-serif;
    }

    .showcase-content {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 300px;
        margin-bottom: 1.5rem;
    }

    .showcase-image {
        max-width: 90%;
        max-height: 300px;
        object-fit: contain;
        border-radius: 5px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        transition: transform 0.5s ease;
    }

    .showcase-image.animate {
        animation: pulse 1s;
    }

    .showcase-info {
        font-size: 1.2rem;
        color: var(--color-accent);
        font-weight: normal;
        margin-bottom: 1.5rem;
        font-family: "QiantuHouhei", sans-serif;
    }
    
    /* 过滤区域中的按钮样式调整 */
    .filter-right .showcase-button {
        padding: 0;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: unset;
    }
    
    .showcase-button {
        padding: 0.75rem 2rem;
        background-color: var(--color-primary);
        color: white;
        border: none;
        border-radius: 30px;
        cursor: pointer;
        font-family: "QiantuHouhei", sans-serif;
        font-size: 1rem;
        font-weight: normal;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .showcase-button:hover {
        background-color: var(--color-accent);
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(204, 148, 113, 0.4);
    }
    
    /* 刷新按钮样式 */
    .refresh-icon {
        width: 24px;
        height: 24px;
        transition: transform 0.3s ease;
    }
    
    .showcase-button:hover .refresh-icon {
        transform: scale(1.2);
    }
    
    .showcase-button.rotating .refresh-icon {
        animation: rotate360 1s linear;
    }
    
    /* 动画效果 */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes rotate360 {
        0% { transform: scale(1) rotate(0deg); }
        50% { transform: scale(1.2) rotate(180deg); }
        100% { transform: scale(1) rotate(360deg); }
    }
    
    @media (max-width: 480px) {
        .expression-card {
            height: 180px;
        }
    }
</style>
';

// 表情组件需要的JavaScript
$additional_scripts = '
<script>
    // 存储数据
    const expressions = ' . json_encode($expressions, JSON_UNESCAPED_UNICODE) . ';
    
    // 当前过滤器状态（支持多标签）
    let selectedCategories = [];
    
    // 标记是否是初次加载
    let isInitialLoad = true;
    
    // 跟踪当前选中的表情卡片
    let currentSelectedCard = null;
    
    // 标记是否是随机选择
    let isRandomSelection = false;
    
    // DOM元素引用
    const expressionFilters = document.querySelectorAll("#expression-filters .filter-button");
    const expressionCards = document.querySelectorAll(".expression-card");
    
    // 过滤功能（支持多标签）
    function setupFilters() {
        expressionFilters.forEach(filter => {
            filter.addEventListener("click", () => {
                const category = filter.getAttribute("data-category");
                
                // 清空当前选中的卡片
                if (currentSelectedCard) {
                    currentSelectedCard.querySelector(".expression-card-inner").style.transform = "";
                    currentSelectedCard = null;
                }
                
                // 清空展示区
                clearShowcaseArea();
                
                if (category === "all") {
                    // 点击"全部"按钮，清除所有选择
                    selectedCategories = [];
                    expressionFilters.forEach(btn => btn.classList.remove("active"));
                    filter.classList.add("active");
                    
                    // 重置卡片顺序
                    resetCardOrder();
                } else {
                    // 移除"全部"按钮的激活状态
                    document.querySelector(\'.filter-button[data-category="all"]\').classList.remove("active");
                    
                    // 切换当前标签的选择状态
                    if (filter.classList.contains("active")) {
                        // 如果已选中，则取消选择
                        filter.classList.remove("active");
                        selectedCategories = selectedCategories.filter(cat => cat !== category);
                    } else {
                        // 如果未选中，则添加选择
                        filter.classList.add("active");
                        selectedCategories.push(category);
                    }
                    
                    // 如果没有选中任何标签，激活"全部"按钮
                    if (selectedCategories.length === 0) {
                        document.querySelector(\'.filter-button[data-category="all"]\').classList.add("active");
                    }
                }
                
                // 立即更新标签按钮状态，不等待过滤完成
                updateTagButtonsState();
                
                // 应用过滤
                applyFilters();
            });
        });
    }
    
    // 应用过滤逻辑
    function applyFilters() {
        // 获取表情卡片的容器
        const cardGrid = document.getElementById("expression-grid");
        
        // 如果不是初次加载，移除所有卡片的初始加载类
        if (!isInitialLoad) {
            expressionCards.forEach(card => {
                card.classList.remove("initial-load");
            });
        } else {
            // 首次过滤后，将初次加载标志设置为false
            isInitialLoad = false;
            
            // 对于大量卡片，分批处理以提高性能
            if (expressionCards.length > 20) {
                console.log("大量卡片检测: " + expressionCards.length + "张卡片");
                
                // 先显示前20张卡片
                for (let i = 20; i < expressionCards.length; i++) {
                    // 延迟显示后面的卡片，避免一次性处理太多DOM操作
                    setTimeout(() => {
                        if (expressionCards[i]) {
                            expressionCards[i].style.opacity = "1";
                            expressionCards[i].style.transform = "scale(1) translateY(0)";
                        }
                    }, 2000 + (i - 20) * 10); // 2秒后开始，每张卡片间隔10ms
                }
            }
        }
        
        // 创建一个数组来存储所有卡片，并按照可见性和原始顺序排序
        const cardArray = Array.from(expressionCards);
        const visibleCards = [];
        const hiddenCards = [];
        const newlyVisibleCards = [];
        
        // 首先应用当前过滤器，确定哪些卡片应该显示，哪些应该隐藏
        cardArray.forEach(item => {
            const itemCategories = item.getAttribute("data-categories");
            const wasVisible = item.style.display !== "none" && item.style.opacity !== "0";
            
            let shouldBeVisible = false;
            
            if (selectedCategories.length === 0) {
                // 没有选择任何标签，显示所有
                shouldBeVisible = true;
            } else {
                // 检查项目是否包含所有选中的标签
                const itemCategoryArray = itemCategories ? itemCategories.split(",").map(cat => cat.trim()) : [];
                shouldBeVisible = selectedCategories.every(selectedCat => 
                    itemCategoryArray.includes(selectedCat)
                );
            }
            
            // 根据可见性状态分类
            if (shouldBeVisible) {
                if (wasVisible) {
                    // 已经可见的卡片保持原位置
                    visibleCards.push(item);
                } else {
                    // 新变为可见的卡片
                    newlyVisibleCards.push(item);
                }
            } else {
                // 需要隐藏的卡片
                hiddenCards.push(item);
                
                // 设置隐藏动画
                item.classList.add("card-hidden");
                item.classList.remove("card-visible");
                setTimeout(() => {
                    item.style.display = "none";
                }, 250); // 0.25秒后隐藏
            }
        });
        
        // 显示已经可见的卡片
        visibleCards.forEach(item => {
            item.classList.remove("card-hidden");
            item.classList.add("card-visible");
            item.style.display = "block";
        });
        
        // 先准备所有新卡片，但保持隐藏状态
        newlyVisibleCards.forEach(item => {
            // 完全隐藏，但保持占位
            item.classList.add("card-hidden");
            // 确保移除初始加载类，防止触发初次进场动画
            item.classList.remove("initial-load");
            item.style.display = "block";
            
            // 将元素移动到网格的末尾
            cardGrid.appendChild(item);
        });
        
        // 使用RAF确保浏览器已经处理了DOM更新
        requestAnimationFrame(() => {
            // 然后按行显示卡片，每行最多显示4个卡片
            const cardsPerRow = 4;
            newlyVisibleCards.forEach((item, index) => {
                // 计算卡片所在的行
                const row = Math.floor(index / cardsPerRow);
                // 计算卡片在行中的位置
                const colPosition = index % cardsPerRow;
                
                // 延迟显示，创造波浪效果，行与行之间有更大延迟
                setTimeout(() => {
                    // 移除隐藏类，添加可见类
                    item.classList.remove("card-hidden");
                    item.classList.add("card-visible");
                }, 50 + (row * 100) + (colPosition * 30)); // 基础延迟50ms，每行额外增加100ms，每列增加30ms
            });
        });
    }
    
    // 更新标签按钮状态
    function updateTagButtonsState() {
        // 如果没有选择任何标签，所有标签都可用
        if (selectedCategories.length === 0) {
            expressionFilters.forEach(filter => {
                if (filter.getAttribute("data-category") !== "all") {
                    filter.classList.remove("disabled");
                    filter.disabled = false;
                }
            });
            return;
        }
        
        // 计算每个标签与当前已选标签的兼容性
        const compatibleCategories = new Set();
        
        // 预先创建一个映射，存储每个表情的分类数组，避免重复解析
        const expressionCategoriesMap = new Map();
        expressionCards.forEach(card => {
            const cardCategories = card.getAttribute("data-categories");
            if (cardCategories) {
                expressionCategoriesMap.set(
                    card, 
                    cardCategories.split(",").map(cat => cat.trim())
                );
            }
        });
        
        // 遍历所有表情卡片
        expressionCategoriesMap.forEach((cardCategoryArray, card) => {
            // 检查这个卡片是否包含所有已选标签
            const hasAllSelectedCategories = selectedCategories.every(selectedCat => 
                cardCategoryArray.includes(selectedCat)
            );
            
            // 如果包含所有已选标签，那么这个卡片的所有标签都是兼容的
            if (hasAllSelectedCategories) {
                cardCategoryArray.forEach(cat => {
                    compatibleCategories.add(cat);
                });
            }
        });
        
        // 更新每个标签按钮的状态 - 立即应用视觉效果
        expressionFilters.forEach(filter => {
            const category = filter.getAttribute("data-category");
            if (category !== "all") {
                // 如果该标签不在兼容分类中且未被选中，则禁用
                if (!compatibleCategories.has(category) && !selectedCategories.includes(category)) {
                    filter.classList.add("disabled");
                    filter.disabled = true;
                } else {
                    filter.classList.remove("disabled");
                    filter.disabled = false;
                }
            }
        });
    }
    
    // 表情选择功能
    function selectExpression(index, cardElement = null) {
        const expression = expressions[index];
        if (!expression) return;
        
        const showcaseExpression = document.getElementById("showcase-expression");
        if (!showcaseExpression) return;
        
        // 如果有之前选中的卡片，恢复其状态
        if (currentSelectedCard && currentSelectedCard !== cardElement) {
            // 重置之前卡片的翻转状态
            currentSelectedCard.querySelector(".expression-card-inner").style.transform = "";
        }
        
        // 更新当前选中的卡片
        currentSelectedCard = cardElement;
        
        // 如果提供了卡片元素，保持其翻转状态
        if (cardElement) {
            cardElement.querySelector(".expression-card-inner").style.transform = "rotateY(180deg)";
        }
        
        // 确保图片元素可见
        showcaseExpression.style.display = "block";
        
        // 添加动画类
        showcaseExpression.classList.add("animate");
        
        // 更新展示区
        showcaseExpression.src = expression.image_path;
        
        // 更新标题和描述
        document.getElementById("showcase-expression-info").textContent = expression.title || "未命名表情";
        document.getElementById("showcase-expression-desc").innerHTML = expression.description || "&nbsp;";
        
        // 移除动画类
        setTimeout(() => {
            showcaseExpression.classList.remove("animate");
        }, 1000);
        
        // 用户点击卡片时滚动到顶部展示区，随机选择时保持当前位置
        if (!isRandomSelection) {
            // 滚动到页面顶部，确保展示区可见
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    }
    
    // 随机表情功能
    function randomExpression() {
        // 设置随机选择标志
        isRandomSelection = true;
        
        // 添加旋转效果
        const btn = document.getElementById("random-expression-btn");
        btn.classList.add("rotating");
        
        // 动画结束后移除类
        setTimeout(() => {
            btn.classList.remove("rotating");
        }, 1000);
        
        // 获取当前过滤后的表情
        const filteredExpressions = expressions.filter(expr => {
            if (selectedCategories.length === 0) {
                return true; // 显示所有
            }
            
            // 检查表情是否包含所有选中的标签
            const exprCategories = expr.category ? expr.category.split(",").map(cat => cat.trim()) : [];
            return selectedCategories.every(selectedCat => exprCategories.includes(selectedCat));
        });
        
        if (filteredExpressions.length === 0) return;
        
        // 如果只有一个表情，则直接返回
        if (filteredExpressions.length === 1) {
            const originalIndex = expressions.findIndex(expr => expr.id === filteredExpressions[0].id);
            // 找到对应的卡片元素
            const correspondingCard = document.querySelector(`.expression-card[data-index="${originalIndex}"]`);
            selectExpression(originalIndex, correspondingCard);
            return;
        }
        
        // 获取当前显示的表情ID
        const currentExpressionSrc = document.getElementById("showcase-expression").src;
        const currentExpressionName = document.getElementById("showcase-expression-info").textContent;
        
        // 排除当前表情后再随机选择
        const availableExpressions = filteredExpressions.filter(expr => 
            expr.image_path !== currentExpressionSrc && expr.title !== currentExpressionName);
            
        // 如果过滤后没有表情了，则使用原来的方法
        if (availableExpressions.length === 0) {
            const randomIndex = Math.floor(Math.random() * filteredExpressions.length);
            const randomExpr = filteredExpressions[randomIndex];
            const originalIndex = expressions.findIndex(expr => expr.id === randomExpr.id);
            // 找到对应的卡片元素
            const correspondingCard = document.querySelector(`.expression-card[data-index="${originalIndex}"]`);
            selectExpression(originalIndex, correspondingCard);
            return;
        }
        
        // 从可用表情中随机选择一个
        const randomIndex = Math.floor(Math.random() * availableExpressions.length);
        const randomExpr = availableExpressions[randomIndex];
        
        // 找到对应原始索引
        const originalIndex = expressions.findIndex(expr => expr.id === randomExpr.id);
        
        // 找到对应的卡片元素
        const correspondingCard = document.querySelector(`.expression-card[data-index="${originalIndex}"]`);
        
        // 选择该表情，并传递卡片元素
        selectExpression(originalIndex, correspondingCard);
    }
    
    // 初始化
    document.addEventListener("DOMContentLoaded", function() {
        // 设置过滤器
        setupFilters();
        
        // 初始化标签按钮状态
        updateTagButtonsState();
        
        // 为每个表情卡片添加点击事件
        expressionCards.forEach((card, index) => {
            // 保存原始索引，用于恢复顺序
            card.setAttribute("data-original-index", index);
            
            // 计算卡片所在的行和列，用于控制初始加载动画
            const row = Math.floor(index / 4); // 假设每行4个卡片
            const col = index % 4;
            
            // 只对前20个卡片应用初始加载动画，避免大量卡片同时动画导致性能问题
            if (index < 20) {
                card.classList.add("initial-load");
            } else {
                // 对于更多的卡片，直接显示，不应用动画
                card.style.opacity = "1";
                card.style.transform = "scale(1) translateY(0)";
            }
            
            // 卡片背面元素
            const cardBack = card.querySelector(".expression-card-back");
            
            // 点击事件
            card.addEventListener("click", () => {
                // 重置随机选择标志
                isRandomSelection = false;
                
                const index = parseInt(card.getAttribute("data-index"));
                
                // 背景色变为浅色
                if(cardBack) {
                    cardBack.style.backgroundColor = "var(--color-accent)";
                }
                
                // 选择表情，并传递卡片元素
                selectExpression(index, card);
            });
            
            // 鼠标离开事件
            card.addEventListener("mouseleave", () => {
                // 如果不是当前选中的卡片，才恢复背景色
                if(cardBack && card !== currentSelectedCard) {
                    cardBack.style.backgroundColor = "var(--color-primary)";
                }
            });
        });
        
        // 绑定随机按钮事件
        const randomBtn = document.getElementById("random-expression-btn");
        if (randomBtn) {
            randomBtn.addEventListener("click", randomExpression);
        }
        
        // 标签同步更新
        updateTabStatus();
        
        // 初始隐藏展示区图片
        const showcaseExpression = document.getElementById("showcase-expression");
        if (showcaseExpression) {
            showcaseExpression.style.display = "none";
        }
        
        // 页面加载时显示随机表情
        if (expressions && expressions.length > 0) {
            // 设置随机选择标志
            isRandomSelection = true;
            
            // 随机选择一个表情
            const randomIndex = Math.floor(Math.random() * expressions.length);
            
            // 找到对应的卡片元素
            const correspondingCard = document.querySelector(`.expression-card[data-index="${randomIndex}"]`);
            
            // 选择该表情
            selectExpression(randomIndex, correspondingCard);
            
            // 重置随机选择标志（为下一次点击做准备）
            isRandomSelection = false;
        }
    });
    
    // 重置卡片顺序函数
    function resetCardOrder() {
        // 获取卡片容器
        const cardGrid = document.getElementById("expression-grid");
        
        // 创建一个数组，按照原始索引排序
        const sortedCards = Array.from(expressionCards).sort((a, b) => {
            const indexA = parseInt(a.getAttribute("data-original-index")) || 0;
            const indexB = parseInt(b.getAttribute("data-original-index")) || 0;
            return indexA - indexB;
        });
        
        // 先将所有卡片设置为半透明状态
        sortedCards.forEach(card => {
            // 只对可见卡片应用过渡效果
            if (card.style.display !== "none") {
                // 添加自定义类实现半透明效果
                card.classList.add("card-faded");
                card.classList.remove("card-visible");
            }
        });
        
        // 短暂延迟后重新排序并恢复透明度
        setTimeout(() => {
            // 重新添加卡片到容器中，恢复原始顺序
            sortedCards.forEach((card, index) => {
                cardGrid.appendChild(card);
            });
            
            // 使用RAF确保DOM已更新
            requestAnimationFrame(() => {
                // 然后应用恢复动画
                sortedCards.forEach((card, index) => {
                    // 只对可见卡片应用过渡效果
                    if (card.style.display !== "none") {
                        // 错开恢复动画
                        setTimeout(() => {
                            card.classList.remove("card-faded");
                            card.classList.add("card-visible");
                        }, index * 20); // 每个卡片错开20ms
                    }
                });
            });
        }, 50); // 等待50ms再重排
    }
    
    // 清空展示区函数
    function clearShowcaseArea() {
        const showcaseExpression = document.getElementById("showcase-expression");
        const showcaseInfo = document.getElementById("showcase-expression-info");
        const showcaseDesc = document.getElementById("showcase-expression-desc");
        
        if (showcaseExpression) {
            // 隐藏图片元素
            showcaseExpression.style.display = "none";
        }
        
        if (showcaseInfo) {
            showcaseInfo.textContent = "请选择表情";
        }
        
        if (showcaseDesc) {
            showcaseDesc.innerHTML = "&nbsp;";
        }
    }
    
    // 标签状态同步函数
    function updateTabStatus() {
        // 移除所有标签的active类
        document.querySelectorAll(\'.tab-button\').forEach(tab => {
            tab.classList.remove(\'active\');
        });
        
        // 为当前页面对应的标签添加active类
        const currentTab = document.querySelector(\'.tab-button[data-tab="emotes"]\');
        if (currentTab) {
            currentTab.classList.add(\'active\');
        }
    }
</script>
';

// 获取缓冲区内容
$content = ob_get_clean();

// 包含基础模板
require_once 'expression_base.php';
?> 