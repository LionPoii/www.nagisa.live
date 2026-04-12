<?php
// 检查组件是否启用
$show_spacevideos = true; // 默认显示
try {
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 获取组件显示配置
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'show_spacevideos'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result !== false) {
        $show_spacevideos = (bool)$result['config_value'];
    }
} catch (Exception $e) {
    // 出错时使用默认值
    error_log("获取视频组件显示状态失败: " . $e->getMessage());
}

// 如果组件被禁用，则不继续执行
if (!$show_spacevideos) {
    return;
}

// 从API获取数据
$ch = curl_init();
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// 获取B站UID
$stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_uid'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$current_uid = $result ? $result['config_value'] : '';

// 获取B站Cookie
$stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_cookie'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$bili_cookie = $result ? $result['config_value'] : '';

$space_videos = [];
$has_fresh_data = false;

// 尝试从API获取新数据
if (!empty($current_uid)) {
    $url = $base_url . "/api/bili_spacevideos_api.php?mid=" . urlencode($current_uid) . "&action=videos&page=1&page_size=18";
    
    // 调试信息
    error_log("B站视频API请求URL: " . $url);
    error_log("B站UID: " . $current_uid);
    error_log("B站Cookie长度: " . strlen($bili_cookie));
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-BiliCookie: ' . $bili_cookie,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 设置超时时间为3秒
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 调试信息
    error_log("API响应状态码: " . $httpCode);
    if ($error) {
        error_log("CURL错误: " . $error);
    }
    error_log("API响应数据: " . $response);
    
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        // 适应新API格式
        if ($data && $data['code'] === 0) {
            // 检查新API格式
            if (isset($data['data']['videos']) && !empty($data['data']['videos'])) {
                $space_videos = $data['data']['videos'];
                $has_fresh_data = true;
                error_log("成功获取视频数量(新格式): " . count($space_videos));
            } 
            // 检查旧API格式
            else if (isset($data['data']['list']['vlist']) && !empty($data['data']['list']['vlist'])) {
                $space_videos = $data['data']['list']['vlist'];
                $has_fresh_data = true;
                error_log("成功获取视频数量(旧格式): " . count($space_videos));
            }
            else {
                error_log("API返回数据格式不正确或为空: " . print_r($data, true));
            }
        } else {
            error_log("API返回数据错误码: " . ($data['code'] ?? 'unknown') . ", 信息: " . ($data['message'] ?? 'unknown'));
        }
    } else {
        error_log("API请求失败或超时");
    }
    
    // 如果从API获取数据失败，尝试从缓存读取
    if (empty($space_videos)) {
        error_log("尝试从缓存获取视频数据");
        $cache_dir = __DIR__ . '/../api/api_cache/spacevideos/';
        $cache_pattern = $cache_dir . "*_{$current_uid}_*.json";
        $cache_files = glob($cache_pattern);
        
        if (!empty($cache_files)) {
            // 按修改时间排序，获取最新的缓存文件
            usort($cache_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $latest_cache = $cache_files[0];
            $cache_content = file_get_contents($latest_cache);
            if ($cache_content) {
                $cache_data = json_decode($cache_content, true);
                // 检查缓存数据格式 - 新格式
                if ($cache_data && isset($cache_data['data']['list']['vlist']) && !empty($cache_data['data']['list']['vlist'])) {
                    $space_videos = $cache_data['data']['list']['vlist'];
                    error_log("成功从缓存获取视频数据(原始格式): " . count($space_videos) . "，缓存文件: " . basename($latest_cache));
                } else if ($cache_data && isset($cache_data['data']['videos']) && !empty($cache_data['data']['videos'])) {
                    $space_videos = $cache_data['data']['videos'];
                    error_log("成功从缓存获取视频数据(新格式): " . count($space_videos) . "，缓存文件: " . basename($latest_cache));
                }
            }
        }
    }
}
?>
<style>
.spacevideos-scroll {
    display: flex;
    gap: 1.5%; /* 增加卡片间距 */
    padding-bottom: 0.5%;
    margin-bottom: 1%;
    scrollbar-width: none;
    position: relative;
    z-index: 100;
    overflow-x: auto;
    overflow-y: visible;
    scroll-behavior: smooth;
    padding-left: 1.5%;
    padding-right: 1.5%;
    /* animation: spacevideos-autoscroll 30s linear infinite; */
}
.spacevideos-scroll::-webkit-scrollbar {
    display: none;
}
@keyframes spacevideos-autoscroll {
    0% { scroll-behavior: smooth; }
    0% { scroll-left: 0; }
    100% { scroll-left: 100%; }
}
.spacevideos-card {
    min-width: 18%; /* 增大卡片最小宽度 */
    max-width: 22%; /* 增大卡片最大宽度 */
    background: #fff;
    border-radius: 3%;
    box-shadow: 0 0.2% 0.8% rgba(204,148,113,0.10), 0 0.1% 0.3% rgba(0,0,0,0.06);
    overflow: hidden;
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
}
.spacevideos-cover-container {
    width: 100%;
    position: relative;
    padding-top: 56.25%; /* 保持16:9比例 (9/16 = 0.5625 = 56.25%) */
    overflow: hidden;
}
.spacevideos-cover {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.spacevideos-title {
    font-size: 0.9rem; /* 增大字体大小 */
    font-weight: bold;
    margin: 5% 5% 3% 5%; /* 增大内边距 */
    color: #222;
    text-align: left;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 2.4em;
    max-height: 2.4em;
}
.spacevideos-title .highlight {
    color: #cc9471;
    font-weight: 900;
}
.spacevideos-desc {
    font-size: 0.75rem;
    color: #4c526b;
    margin: 0 3% 3% 3%;
    text-align: left;
    min-height: 24px;
}
.spacevideos-card:hover {
    transform: scale(1.02);
    box-shadow: 0 0.4% 1.2% rgba(204,148,113,0.18), 0 0.15% 0.6% rgba(0,0,0,0.10);
}
@media (max-width: 600px) {
    .spacevideos-card {
        min-width: 45vw;
        max-width: 48vw;
    }
    .spacevideos-cover-container {
        padding-top: 56.25%; /* 保持16:9比例 */
    }
    .spacevideos-title {
        font-size: 0.8rem;
        min-height: 2.2em;
        max-height: 2.2em;
    }
}
</style>
<!-- 修改为动态计算位置 -->
<div id="spacevideos-container" style="position: absolute; top: 12vh; right: 0; z-index: 2; overflow: visible;">
  <div style="font-size:1.2rem;font-weight:900;color:#4c526b;margin-bottom:1%;text-align:left;letter-spacing:1px;">主播作品</div>
  <div class="spacevideos-scroll" id="spacevideos-scroll">
    <?php if (!empty($space_videos)): ?>
      <?php foreach($space_videos as $video): ?>
        <div class="spacevideos-card" onclick="window.open('https://www.bilibili.com/video/<?php echo htmlspecialchars($video['bvid']); ?>', '_blank')">
          <div class="spacevideos-cover-container">
            <img class="spacevideos-cover" src="<?php echo htmlspecialchars(strpos($video['pic'], '//') === 0 ? 'https:' . $video['pic'] : $video['pic']); ?>" alt="cover" referrerpolicy="no-referrer">
          </div>
          <div class="spacevideos-title">
            <?php echo htmlspecialchars($video['title']); ?><!--  -->
          </div>
        </div>
      <?php endforeach; ?>
      <div style="width: 1%; flex-shrink: 0;"></div>
    <?php else: ?>
      <div class="spacevideos-card" style="min-width: 100%; text-align: center; padding: 2% 1%; background: rgba(255, 255, 255, 0.9);">
        <div style="color: #666; font-size: 0.9rem; display: flex; flex-direction: column; align-items: center; gap: 0.5%;">
          <span style="font-size: 1.5rem;">📝</span>
          <span>请刷新显示数据</span>
          <?php if (!empty($current_uid)): ?><!--  -->
            <span style="font-size: 0.8rem; color: #999;">(UID: <?php echo htmlspecialchars($current_uid); ?>)</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// 自动横向滚动
const scrollContainer = document.getElementById('spacevideos-scroll');
let autoScrollInterval = null;
let isResetting = false;

// 添加动态调整位置的脚本
function adjustSpaceVideosPosition() {
  const cluewall = document.querySelector('.cluewall-container');
  const spacevideos = document.getElementById('spacevideos-container');
  
  if (cluewall && spacevideos) {
    // 获取线索墙的位置和尺寸
    const cluewallRect = cluewall.getBoundingClientRect();
    const rightEdge = cluewallRect.right;
    
    // 设置视频容器的左边距为线索墙的右边缘 + 一定间距(2%)
    const leftMargin = (rightEdge / window.innerWidth * 100) + 2;
    const width = 98 - leftMargin;  // 98% 而不是 100% 留一点右边距
    
    // 设置位置
    spacevideos.style.left = leftMargin + 'vw';
    spacevideos.style.width = width + 'vw';
    spacevideos.style.bottom = '6vh'; // 确保距离底部始终为7vh
  }
}

// 确保定期检查并调整位置，防止其他脚本修改
setInterval(adjustSpaceVideosPosition, 1000);

// 页面加载和窗口大小变化时调整位置
document.addEventListener('DOMContentLoaded', adjustSpaceVideosPosition);
window.addEventListener('resize', adjustSpaceVideosPosition);

// 确保在线索墙加载完成后也调整位置
function checkClueWallAndAdjust() {
  if (document.querySelector('.cluewall-container')) {
    adjustSpaceVideosPosition();
  } else {
    setTimeout(checkClueWallAndAdjust, 100);
  }
}
checkClueWallAndAdjust();

function startAutoScroll() {
  if (!scrollContainer) return;
  let scrollStep = 1;
  
  autoScrollInterval = setInterval(() => {
    // 检测是否滚动到末尾
    if (scrollContainer.scrollLeft + scrollContainer.offsetWidth >= scrollContainer.scrollWidth - 2) {
      if (!isResetting) {
        isResetting = true;
        // 使用平滑过渡回到开始位置
        scrollContainer.style.scrollBehavior = 'auto';
        scrollContainer.scrollLeft = 0;
        
        // 短暂延迟后恢复平滑滚动
        setTimeout(() => {
          scrollContainer.style.scrollBehavior = 'smooth';
          isResetting = false;
        }, 50);
      }
    } else {
      scrollContainer.scrollLeft += scrollStep;
    }
  }, 15);
}

function stopAutoScroll() {
  if (autoScrollInterval) clearInterval(autoScrollInterval);
}

// 鼠标悬停时停止滚动，移出时恢复滚动
scrollContainer && scrollContainer.addEventListener('mouseenter', stopAutoScroll);
scrollContainer && scrollContainer.addEventListener('mouseleave', startAutoScroll);

// 页面加载完成后自动开始滚动
document.addEventListener('DOMContentLoaded', startAutoScroll);
startAutoScroll();

// 立即调整位置并保证7vh的底部距离
(function initPosition() {
  const spacevideos = document.getElementById('spacevideos-container');
  if (spacevideos) {
    spacevideos.style.bottom = '6vh';
  }
  adjustSpaceVideosPosition();
})();
</script>
<!-- 原有内容结束 --> 