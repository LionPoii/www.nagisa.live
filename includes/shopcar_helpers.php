<?php
/**
 * 购物车商品 / 系列 公共逻辑
 */

function shopcarResolveImageUrl($image) {
    if (empty($image)) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/images/default-product.jpg')) {
            return '/assets/images/default-product.jpg';
        }
        return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150"><rect width="200" height="150" fill="#f8f8f8"/><text x="50%" y="50%" font-family="Arial" font-size="14" text-anchor="middle" fill="#999">暂无图片</text></svg>');
    }
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return $image;
    }
    $path = '/' . ltrim($image, '/');
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
        return $path;
    }
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/images/default-product.jpg')) {
        return '/assets/images/default-product.jpg';
    }
    return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150"><rect width="200" height="150" fill="#f8f8f8"/><text x="50%" y="50%" font-family="Arial" font-size="14" text-anchor="middle" fill="#999">图片加载失败</text></svg>');
}

function shopcarFormatPrice($price) {
    $p = trim((string)$price);
    if ($p === '') {
        return '';
    }
    if (preg_match('/^[¥￥]/u', $p)) {
        return $p;
    }
    return '¥' . $p;
}

function shopcarTableExists($conn, $table) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    $stmt = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $stmt && $stmt->rowCount() > 0;
}

function shopcarColumnExists($conn, $table, $column) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $stmt && $stmt->rowCount() > 0;
}

/**
 * 自动创建系列表并为商品表添加 series_id
 */
function shopcarEnsureSchema($conn) {
    if (!$conn || !shopcarTableExists($conn, 'shopcar_products')) {
        return false;
    }

    if (!shopcarTableExists($conn, 'shopcar_series')) {
        $conn->exec("CREATE TABLE `shopcar_series` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `parent_id` int(11) DEFAULT NULL COMMENT 'NULL=顶级商品系列，有值=上级系列ID',
            `title` varchar(255) NOT NULL,
            `description` text,
            `position` int(11) NOT NULL DEFAULT 0,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `parent_id` (`parent_id`),
            KEY `active_position` (`active`, `position`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品系列'");
    }

    if (!shopcarColumnExists($conn, 'shopcar_products', 'series_id')) {
        $conn->exec('ALTER TABLE `shopcar_products` ADD COLUMN `series_id` int(11) DEFAULT NULL COMMENT \'商品系列ID\' AFTER `link`');
    }

    // 统一为单层商品系列
    if (shopcarTableExists($conn, 'shopcar_series') && shopcarColumnExists($conn, 'shopcar_series', 'parent_id')) {
        $conn->exec('UPDATE `shopcar_series` SET `parent_id` = NULL WHERE `parent_id` IS NOT NULL');
    }

    return true;
}

/**
 * 构建前台目录：商品系列 → 商品
 * @return array{groups: array, products_json: array}
 */
function shopcarBuildCatalog($conn) {
    shopcarEnsureSchema($conn);

    $allProducts = [];
    $stmt = $conn->prepare('SELECT * FROM shopcar_products ORDER BY position ASC, id ASC');
    $stmt->execute();
    $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $seriesList = [];
    if (shopcarTableExists($conn, 'shopcar_series')) {
        $stmt = $conn->prepare('SELECT * FROM shopcar_series ORDER BY position ASC, id ASC');
        $stmt->execute();
        $seriesList = shopcarSortSeriesList($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $seriesById = [];
    foreach ($seriesList as $s) {
        $seriesById[(int)$s['id']] = $s;
    }

    $listProducts = [];
    foreach ($allProducts as $p) {
        $sid = isset($p['series_id']) && $p['series_id'] ? (int)$p['series_id'] : 0;
        $productActive = (int)($p['active'] ?? 1) === 1;
        $seriesActive = ($sid === 0 || !isset($seriesById[$sid]))
            ? true
            : ((int)($seriesById[$sid]['active'] ?? 1) === 1);
        $selectable = $productActive && $seriesActive;

        $listProducts[] = [
            'id' => (int)$p['id'],
            'title' => $p['title'],
            'series_id' => $sid,
            'selectable' => $selectable,
        ];
    }

    $productsBySeries = [];
    $uncategorized = [];
    foreach ($listProducts as $p) {
        $sid = (int)$p['series_id'];
        if ($sid > 0 && isset($seriesById[$sid])) {
            $productsBySeries[$sid][] = $p;
        } else {
            $uncategorized[] = $p;
        }
    }

    $groups = [];
    foreach ($seriesList as $series) {
        $sid = (int)$series['id'];
        $seriesActive = (int)($series['active'] ?? 1) === 1;
        $prods = $productsBySeries[$sid] ?? [];
        if (empty($prods) && $seriesActive) {
            continue;
        }
        $groups[] = [
            'id' => $sid,
            'title' => $series['title'],
            'description' => $series['description'] ?? '',
            'active' => $seriesActive,
            'products' => $prods,
        ];
    }

    if (!empty($uncategorized)) {
        $groups[] = [
            'id' => 0,
            'title' => '其他商品',
            'description' => '',
            'active' => true,
            'products' => $uncategorized,
        ];
    }

    $productsJson = [];
    foreach ($allProducts as $p) {
        $sid = (int)($p['series_id'] ?? 0);
        $productActive = (int)($p['active'] ?? 1) === 1;
        $seriesActive = ($sid === 0 || !isset($seriesById[$sid]))
            ? true
            : ((int)($seriesById[$sid]['active'] ?? 1) === 1);
        if (!$productActive || !$seriesActive) {
            continue;
        }
        $seriesTitle = ($sid && isset($seriesById[$sid])) ? $seriesById[$sid]['title'] : '';

        $productsJson[(int)$p['id']] = [
            'id' => (int)$p['id'],
            'title' => $p['title'],
            'description' => $p['description'] ?? '',
            'price' => shopcarFormatPrice($p['price']),
            'price_raw' => $p['price'],
            'link' => $p['link'] ?? '',
            'image' => shopcarResolveImageUrl($p['image'] ?? ''),
            'series_label' => $seriesTitle,
        ];
    }

    return [
        'groups' => $groups,
        'products_json' => $productsJson,
    ];
}

/**
 * 按显示顺序 position 升序排列系列列表
 */
function shopcarSortSeriesList(array $list) {
    usort($list, function ($a, $b) {
        $posA = (int)($a['position'] ?? 0);
        $posB = (int)($b['position'] ?? 0);
        if ($posA !== $posB) {
            return $posA <=> $posB;
        }
        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });
    return $list;
}

/**
 * 后台：获取全部系列（含禁用）用于下拉
 */
function shopcarGetAllSeriesFlat($conn) {
    if (!$conn || !shopcarTableExists($conn, 'shopcar_series')) {
        return [];
    }
    $stmt = $conn->prepare('SELECT * FROM shopcar_series ORDER BY position ASC, id ASC');
    $stmt->execute();
    return shopcarSortSeriesList($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * 后台：按商品系列分组商品（系列按 position 升序，未分类在最后）
 */
function shopcarGroupProductsBySeries(array $products, array $shopcarSeries) {
    $byId = [];
    foreach (shopcarSortSeriesList($shopcarSeries) as $s) {
        $byId[(int)$s['id']] = [
            'id' => (int)$s['id'],
            'title' => $s['title'],
            'position' => (int)($s['position'] ?? 0),
            'active' => (int)($s['active'] ?? 1) === 1,
            'products' => [],
        ];
    }
    $uncategorized = [
        'id' => 0,
        'title' => '未分类',
        'position' => PHP_INT_MAX,
        'active' => true,
        'products' => [],
    ];
    foreach ($products as $p) {
        $sid = !empty($p['series_id']) ? (int)$p['series_id'] : 0;
        if ($sid > 0 && isset($byId[$sid])) {
            $byId[$sid]['products'][] = $p;
        } else {
            $uncategorized['products'][] = $p;
        }
    }
    $groups = [];
    foreach (shopcarSortSeriesList($shopcarSeries) as $s) {
        $g = $byId[(int)$s['id']] ?? null;
        if (!$g) {
            continue;
        }
        if (empty($g['products']) && !empty($g['active'])) {
            continue;
        }
        $groups[] = $g;
    }
    if (!empty($uncategorized['products'])) {
        $groups[] = $uncategorized;
    }
    return $groups;
}

/**
 * 从 POST 解析所属系列 ID
 */
function shopcarParseSeriesIdFromPost() {
    $raw = $_POST['edit_product_series_id'] ?? $_POST['product_series_id'] ?? '';
    if ($raw === '' || $raw === '0') {
        return null;
    }
    $id = intval($raw);
    return $id > 0 ? $id : null;
}

/**
 * 商品系列下拉选项 HTML
 */
function shopcarSeriesSelectOptions($seriesList, $selectedId = null) {
    $seriesList = shopcarSortSeriesList($seriesList);
    $selectedId = $selectedId ? (int)$selectedId : null;
    $html = '<option value="">— 未分类 —</option>';
    foreach ($seriesList as $s) {
        $id = (int)$s['id'];
        $sel = ($selectedId === $id) ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>' . htmlspecialchars($s['title']) . '</option>';
    }
    return $html;
}

/**
 * 根据 series_id 返回展示用系列名称
 */
function shopcarGetSeriesDisplayName($seriesId, $seriesList) {
    if (!$seriesId || empty($seriesList)) {
        return '未分类';
    }

    $byId = [];
    foreach ($seriesList as $s) {
        $byId[(int)$s['id']] = $s;
    }

    $sid = (int)$seriesId;
    if (!isset($byId[$sid])) {
        return '未分类';
    }

    return $byId[$sid]['title'];
}

/**
 * 商品 INSERT 字段（含 series_id 时）
 */
function shopcarProductInsertSql($conn) {
    if (shopcarColumnExists($conn, 'shopcar_products', 'series_id')) {
        return 'INSERT INTO shopcar_products (title, description, price, image, link, series_id, position, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    }
    return 'INSERT INTO shopcar_products (title, description, price, image, link, position, active) VALUES (?, ?, ?, ?, ?, ?, ?)';
}

/**
 * 商品 UPDATE 字段（含 series_id / updated_at 时）
 */
function shopcarProductUpdateSql($conn) {
    $hasSeries = shopcarColumnExists($conn, 'shopcar_products', 'series_id');
    $hasUpdated = shopcarColumnExists($conn, 'shopcar_products', 'updated_at');

    if ($hasSeries && $hasUpdated) {
        return 'UPDATE shopcar_products SET title = ?, description = ?, price = ?, image = ?, link = ?, series_id = ?, position = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
    }
    if ($hasSeries) {
        return 'UPDATE shopcar_products SET title = ?, description = ?, price = ?, image = ?, link = ?, series_id = ?, position = ?, active = ? WHERE id = ?';
    }
    if ($hasUpdated) {
        return 'UPDATE shopcar_products SET title = ?, description = ?, price = ?, image = ?, link = ?, position = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
    }
    return 'UPDATE shopcar_products SET title = ?, description = ?, price = ?, image = ?, link = ?, position = ?, active = ? WHERE id = ?';
}

function shopcarProductInsertParams($conn, $title, $description, $price, $imagePath, $link, $seriesId, $position, $active) {
    if (shopcarColumnExists($conn, 'shopcar_products', 'series_id')) {
        return [$title, $description, $price, $imagePath, $link, $seriesId, $position, $active];
    }
    return [$title, $description, $price, $imagePath, $link, $position, $active];
}

function shopcarProductUpdateParams($conn, $title, $description, $price, $imagePath, $link, $seriesId, $position, $active, $id) {
    if (shopcarColumnExists($conn, 'shopcar_products', 'series_id')) {
        return [$title, $description, $price, $imagePath, $link, $seriesId, $position, $active, $id];
    }
    return [$title, $description, $price, $imagePath, $link, $position, $active, $id];
}
