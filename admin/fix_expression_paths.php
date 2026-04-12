<?php
// 引入必要的文件
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 初始化变量
$fixed_expressions = 0;
$fixed_audios = 0;
$errors = [];

// 修复表情图片路径
try {
    // 获取所有不以/开头的表情图片路径
    $stmt = $conn->prepare("SELECT id, image_path FROM expression_images WHERE image_path NOT LIKE '/%'");
    $stmt->execute();
    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 逐个修复路径
    foreach ($expressions as $expression) {
        $id = $expression['id'];
        $old_path = $expression['image_path'];
        $new_path = '/' . $old_path;
        
        // 更新数据库
        $update_stmt = $conn->prepare("UPDATE expression_images SET image_path = ? WHERE id = ?");
        $result = $update_stmt->execute([$new_path, $id]);
        
        if ($result) {
            $fixed_expressions++;
        } else {
            $errors[] = "无法更新表情ID: $id, 路径: $old_path";
        }
    }
} catch (PDOException $e) {
    $errors[] = "表情路径修复错误: " . $e->getMessage();
}

// 修复语音文件路径
try {
    // 获取所有不以/开头的语音文件路径
    $stmt = $conn->prepare("SELECT id, audio_path FROM expression_audios WHERE audio_path NOT LIKE '/%'");
    $stmt->execute();
    $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 逐个修复路径
    foreach ($audios as $audio) {
        $id = $audio['id'];
        $old_path = $audio['audio_path'];
        $new_path = '/' . $old_path;
        
        // 更新数据库
        $update_stmt = $conn->prepare("UPDATE expression_audios SET audio_path = ? WHERE id = ?");
        $result = $update_stmt->execute([$new_path, $id]);
        
        if ($result) {
            $fixed_audios++;
        } else {
            $errors[] = "无法更新语音ID: $id, 路径: $old_path";
        }
    }
} catch (PDOException $e) {
    $errors[] = "语音路径修复错误: " . $e->getMessage();
}

// 设置页面标题
$page_title = "路径修复工具";

// 包含统一页眉
include 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white shadow-lg rounded-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">表情和语音路径修复工具</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <h3 class="font-bold">处理过程中出现错误:</h3>
                <ul class="list-disc ml-5 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p class="font-bold">处理完成!</p>
            <p>已修复 <?php echo $fixed_expressions; ?> 个表情路径</p>
            <p>已修复 <?php echo $fixed_audios; ?> 个语音路径</p>
        </div>
        
        <div class="mt-6">
            <a href="manage_shop_expressions_unified.php" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                返回表情管理
            </a>
        </div>
    </div>
</div>

<?php
// 包含统一页脚
include 'admin_footer.php';
?> 