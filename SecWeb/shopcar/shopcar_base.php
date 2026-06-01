<?php
/**
 * 购物车（商品）二级页面基础模板
 * 样式与 SecWeb/expression/expression_base.php 保持一致
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';

header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');

function shopcarGetAvatarUrl($imageUrl) {
    if (empty($imageUrl)) {
        return '/elements/logo.png';
    }
    if (strpos($imageUrl, 'http') !== 0) {
        return $imageUrl;
    }
    if (strpos($imageUrl, 'bilibili.com') !== false || strpos($imageUrl, 'hdslb.com') !== false) {
        return strpos($imageUrl, '//') === 0 ? 'https:' . $imageUrl : $imageUrl;
    }
    return $imageUrl;
}

$db = new Database();
$conn = $db->getConnection();

$avatar = '/elements/logo.png';
try {
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_avatar'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['config_value'])) {
        $avatar = shopcarGetAvatarUrl($result['config_value']);
    }
} catch (PDOException $e) {
    // 使用默认头像
}

$page_title = isset($page_title) ? $page_title : '商品';
$content = isset($content) ? $content : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'QiantuHouhei';
            src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
            font-display: swap;
        }

        :root {
            --color-primary: #4d4030;
            --color-accent: #cc9471;
            --color-secondary: #e8a274;
            --color-tertiary: #f2c9b5;
            --color-bg: #f9f3ee;
            --color-text: #333;
            --color-card: #ffffff;
            --color-border: rgba(204, 148, 113, 0.2);
            --color-shadow: rgba(0, 0, 0, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --color-bg: #222;
                --color-text: #e0e0e0;
                --color-card: #333;
                --color-border: rgba(204, 148, 113, 0.4);
                --color-shadow: rgba(0, 0, 0, 0.4);
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'QiantuHouhei', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: var(--color-text);
            background-color: var(--color-bg);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .bg-decoration {
            position: fixed;
            pointer-events: none;
            z-index: -1;
        }

        .bg-decoration-1 {
            top: 10%;
            left: 5%;
            width: 250px;
            height: 250px;
            background-color: var(--color-tertiary);
            opacity: 0.3;
            border-radius: 50%;
            filter: blur(80px);
            animation: shopcarFloat 20s infinite alternate ease-in-out;
        }

        .bg-decoration-2 {
            bottom: 10%;
            right: 5%;
            width: 300px;
            height: 300px;
            background-color: var(--color-accent);
            opacity: 0.2;
            border-radius: 50%;
            filter: blur(100px);
            animation: shopcarFloat 15s infinite alternate-reverse ease-in-out;
        }

        @keyframes shopcarFloat {
            0% { transform: translate(0, 0); }
            100% { transform: translate(30px, 30px); }
        }

        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 10px 20px;
            background: #303d4d;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        .header-circle {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            overflow: hidden;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
        }

        .header-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .header-text {
            font-size: 2.4rem;
            font-family: 'QiantuHouhei', sans-serif;
            font-weight: normal;
            letter-spacing: 5px;
        }

        .header-home {
            margin-left: auto;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-size: 0.95rem;
            padding: 0.4rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .header-home:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .container {
            width: 96vw;
            max-width: 1600px;
            min-width: 320px;
            margin: 0 auto;
        }

        .main-content {
            padding-top: 90px;
            padding-bottom: 1.5rem;
            min-height: calc(100vh - 90px);
        }

        @media (max-width: 768px) {
            .header-text {
                font-size: 1.5rem;
                letter-spacing: 3px;
            }
        }
    </style>
    <?php if (isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <div class="bg-decoration bg-decoration-1"></div>
    <div class="bg-decoration bg-decoration-2"></div>

    <header class="fixed-header">
        <div class="header-circle">
            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Nagisa" referrerpolicy="no-referrer">
        </div>
        <span class="header-text"><?php echo htmlspecialchars($page_title); ?></span>
        <a href="/" class="header-home">返回首页</a>
    </header>

    <div class="container">
        <main class="main-content">
            <?php echo $content; ?>
        </main>
    </div>

    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
</body>
</html>
