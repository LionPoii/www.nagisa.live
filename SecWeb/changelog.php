<?php
// 添加页面头部
$page_title = "更新日志 - 粉丝站主站";

// 连接数据库
require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// 从数据库获取所有版本，按发布日期倒序排列
$releases_data = [];
$stmt = $conn->prepare("SELECT * FROM changelog_releases ORDER BY release_date DESC");
$stmt->execute();
$releases_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 遍历版本，获取每个版本的提交记录
foreach ($releases_db as $release) {
    $release_id = $release['id'];
    
    $stmt = $conn->prepare("SELECT * FROM changelog_commits WHERE release_id = ?");
    $stmt->execute([$release_id]);
    $commits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $releases_data[] = [
        'version' => $release['version'],
        'date' => date('Y年m月d日', strtotime($release['release_date'])),
        'description' => $release['description'],
        'commits' => $commits
    ];
}

// 辅助函数：根据类型返回对应的中文文本
function getCommitTypeText($type) {
    $typeTexts = [
        'feature' => '新功能',
        'fix' => '修复',
        'improve' => '改进',
        'docs' => '文档',
        'other' => '其他'
    ];
    return isset($typeTexts[$type]) ? $typeTexts[$type] : '其他';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --color-bg: #ffffff;
            --color-text: #24292e;
            --color-text-secondary: #586069;
            --color-border: #e1e4e8;
            --color-border-secondary: #ebeef0;
            --color-header-bg: #f6f8fa;
            --color-link: #0366d6;
            --color-tag-feature: #2da44e;
            --color-tag-fix: #f85149;
            --color-tag-improve: #9e6a03;
            --color-tag-docs: #8250df;
            --color-tag-other: #6e7781;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --color-bg: #0d1117;
                --color-text: #c9d1d9;
                --color-text-secondary: #8b949e;
                --color-border: #30363d;
                --color-border-secondary: #21262d;
                --color-header-bg: #161b22;
                --color-link: #58a6ff;
                --color-tag-feature: #238636;
                --color-tag-fix: #da3633;
                --color-tag-improve: #bb8009;
                --color-tag-docs: #8957e5;
                --color-tag-other: #6e7681;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: var(--color-text);
            background-color: var(--color-bg);
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        header p {
            color: var(--color-text-secondary);
            font-size: 1.1rem;
        }

        .back-to-site {
            display: inline-block;
            margin-top: 1rem;
            color: var(--color-link);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-to-site:hover {
            text-decoration: underline;
        }

        .release {
            margin-bottom: 3rem;
            position: relative;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .release:last-child {
            margin-bottom: 0;
        }

        .release::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 95px;
            width: 2px;
            background-color: var(--color-border-secondary);
            z-index: -1;
        }

        .release-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .release-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: var(--color-link);
            color: white;
            margin-right: 1rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            margin-left: 80px;
        }

        .release-icon svg {
            width: 16px;
            height: 16px;
        }

        .release-info {
            flex-grow: 1;
        }

        .release-title {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .release-version {
            font-size: 1.4rem;
            font-weight: 600;
            margin-right: 0.5rem;
            color: var(--color-link);
        }

        .release-date {
            font-size: 0.9rem;
            color: var(--color-text-secondary);
        }

        .release-description {
            color: var(--color-text-secondary);
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .commit-list {
            margin-left: 7.5rem;
        }

        .commit {
            margin-bottom: 1.25rem;
            position: relative;
            padding-left: 1.25rem;
        }

        .commit:last-child {
            margin-bottom: 0;
        }

        .commit-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .commit-bullet {
            position: absolute;
            left: -8px;
            top: 8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--color-bg);
        }

        .commit-bullet.feature {
            background-color: var(--color-tag-feature);
        }

        .commit-bullet.fix {
            background-color: var(--color-tag-fix);
        }

        .commit-bullet.improve {
            background-color: var(--color-tag-improve);
        }

        .commit-bullet.docs {
            background-color: var(--color-tag-docs);
        }

        .commit-bullet.other {
            background-color: var(--color-tag-other);
        }

        .commit-tag {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
            color: white;
        }

        .commit-tag.feature {
            background-color: var(--color-tag-feature);
        }

        .commit-tag.fix {
            background-color: var(--color-tag-fix);
        }

        .commit-tag.improve {
            background-color: var(--color-tag-improve);
        }

        .commit-tag.docs {
            background-color: var(--color-tag-docs);
        }

        .commit-tag.other {
            background-color: var(--color-tag-other);
        }

        .commit-message {
            font-weight: 500;
            flex-grow: 1;
        }

        .commit-detail {
            margin-left: 3.5rem;
            font-size: 0.9rem;
            color: var(--color-text-secondary);
        }

        .commit-sha {
            color: var(--color-text-secondary);
            font-family: SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        .commit-sha a {
            color: inherit;
            text-decoration: none;
        }

        .commit-sha a:hover {
            color: var(--color-link);
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem 1rem;
            }

            header h1 {
                font-size: 2rem;
            }

            .release-icon {
                margin-left: 30px;
            }

            .release::before {
                left: 45px;
            }

            .commit-list {
                margin-left: 4.5rem;
            }

            .commit-detail {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 1.75rem;
            }

            .release-icon {
                margin-left: 15px;
            }

            .release::before {
                left: 30px;
            }

            .release-version {
                font-size: 1.2rem;
            }

            .commit-list {
                margin-left: 3.5rem;
            }

            .commit-tag {
                padding: 0.1rem 0.4rem;
                font-size: 0.7rem;
            }

            .commit-header {
                flex-direction: column;
            }

            .commit-sha {
                margin-left: 0;
                margin-top: 0.25rem;
            }
        }

        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: var(--color-header-bg);
            color: var(--color-text);
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
            border: 1px solid var(--color-border);
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>主站更新日志</h1>
            <p>记录主站的所有更新、修复和改进</p>
        </header>

        <main>
            <?php
            // 如果没有版本记录，显示提示信息
            if (empty($releases_data)) {
                echo '<div class="text-center" style="color: var(--color-text-secondary); padding: 2rem 0;">暂无更新记录</div>';
            } else {
                // 遍历所有版本
                foreach ($releases_data as $release) {
                    ?>
                    <div class="release">
                        <div class="release-header">
                            <div class="release-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 9.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"></path>
                                    <path fill-rule="evenodd" d="M8 0a8 8 0 100 16A8 8 0 008 0zM1.5 8a6.5 6.5 0 1113 0 6.5 6.5 0 01-13 0z"></path>
                                </svg>
                            </div>
                            <div class="release-info">
                                <div class="release-title">
                                    <span class="release-version"><?php echo htmlspecialchars($release['version']); ?></span>
                                    <span class="release-date"><?php echo htmlspecialchars($release['date']); ?></span>
                                </div>
                                <div class="release-description">
                                    <?php echo htmlspecialchars($release['description']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="commit-list">
                            <?php foreach ($release['commits'] as $commit) { ?>
                                <div class="commit">
                                    <div class="commit-bullet <?php echo htmlspecialchars($commit['commit_type']); ?>"></div>
                                    <div class="commit-header">
                                        <span class="commit-tag <?php echo htmlspecialchars($commit['commit_type']); ?>"><?php echo getCommitTypeText($commit['commit_type']); ?></span>
                                        <span class="commit-message"><?php echo htmlspecialchars($commit['message']); ?></span>
                                        <span class="commit-sha">
                                            <a href="#" class="tooltip">
                                                <?php echo htmlspecialchars($commit['commit_sha']); ?>
                                                <span class="tooltiptext">查看详情</span>
                                            </a>
                                        </span>
                                    </div>
                                    <div class="commit-detail">
                                        <?php echo htmlspecialchars($commit['detail']); ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </main>
    </div>

    <script>
        // 页面加载后显示动画
        document.addEventListener('DOMContentLoaded', function() {
            const releases = document.querySelectorAll('.release');
            releases.forEach((release, index) => {
                setTimeout(() => {
                    release.style.opacity = '1';
                }, 100 * index);
            });
        });
    </script>
</body>
</html> 