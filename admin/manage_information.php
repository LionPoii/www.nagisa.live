<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 检查表是否存在，如果不存在则创建（site_content表应该已存在）
$stmt = $conn->prepare("SHOW TABLES LIKE 'site_content'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE site_content (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        content_key VARCHAR(50) NOT NULL UNIQUE,
        content_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// 初始化默认文件袋文本
$stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'filebag_text'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $defaultFilebagText = '文件资料袋';
    $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('filebag_text', :filebag_text)");
    $stmt->bindParam(':filebag_text', $defaultFilebagText);
    $stmt->execute();
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_filebag_text'])) {
        $filebagText = trim($_POST['filebag_text']);
        
        if (!empty($filebagText)) {
            // 允许部分安全的HTML标签
            $allowedTags = '<b><i><u><strong><em><span><div><font><h1><h2><h3><h4><h5><h6><p><br><hr>';
            $filebagText = strip_tags($filebagText, $allowedTags);
            
            // 尝试更新，如果记录不存在则插入
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) 
                                    VALUES ('filebag_text', :filebag_text)
                                    ON DUPLICATE KEY UPDATE content_value = :filebag_text");
            $stmt->bindParam(':filebag_text', $filebagText);
            
            if ($stmt->execute()) {
                showToast('文件袋文本更新成功！');
            } else {
                showToast('更新失败，请稍后重试！', 'error');
            }
        } else {
            showToast('文件袋文本不能为空！', 'warning');
        }
    }
}

// 获取当前文件袋文本
$filebagText = '文件资料袋'; // 默认值
$stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'filebag_text'");
$stmt->execute();
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $filebagText = $row['content_value'];
}

// 设置页面标题
$page_title = "信息页面管理";

// 设置页面特定样式
$extra_styles = '
/* Nagisa主题样式 */
.nagisa-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(204, 148, 113, 0.1);
    overflow: hidden;
    border: 1px solid rgba(204, 148, 113, 0.2);
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.nagisa-card:hover {
    box-shadow: 0 6px 20px rgba(204, 148, 113, 0.2);
    transform: translateY(-2px);
}

.nagisa-card-header {
    background: linear-gradient(45deg, #cc9471, #f3b4a4);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
    font-size: 1.1rem;
    border-bottom: 1px solid rgba(204, 148, 113, 0.2);
}

.nagisa-input, .nagisa-textarea {
    border: 2px solid rgba(204, 148, 113, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
}

.nagisa-input:focus, .nagisa-textarea:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.2);
    outline: none;
}

.nagisa-btn {
    background: linear-gradient(45deg, #cc9471, #f3b4a4);
    border: none;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(204, 148, 113, 0.2);
    cursor: pointer;
}

.nagisa-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(204, 148, 113, 0.3);
    background: linear-gradient(45deg, #d49c78, #f8c1b1);
}

.nagisa-btn-secondary {
    background: #e2e8f0;
    color: #64748b;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    cursor: pointer;
}

.nagisa-btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    background: #f1f5f9;
}

.nagisa-btn-mini {
    padding: 5px 10px;
    font-size: 0.85rem;
}

.nagisa-section-title {
    color: #cc9471;
    font-weight: 600;
    padding-bottom: 8px;
    border-bottom: 2px solid rgba(204, 148, 113, 0.2);
    margin-bottom: 16px;
}

.nagisa-form-group {
    margin-bottom: 20px;
}

.nagisa-label {
    display: block;
    font-weight: 500;
    color: #704c38;
    margin-bottom: 8px;
}

.nagisa-nav-link {
    display: block;
    padding: 10px 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin-bottom: 4px;
    font-weight: 500;
}

.nagisa-nav-link.active {
    background: rgba(204, 148, 113, 0.1);
    color: #cc9471;
    border-left: 3px solid #cc9471;
}

.nagisa-nav-link:hover {
    background: rgba(204, 148, 113, 0.05);
    color: #cc9471;
}

.nagisa-preview-container {
    background: rgba(204, 148, 113, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(204, 148, 113, 0.2);
    padding: 16px;
    margin-top: 20px;
}

.nagisa-preview-title {
    color: #cc9471;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 10px;
}

.nagisa-preview-display {
    background: white;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid rgba(204, 148, 113, 0.1);
    min-height: 60px;
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.nagisa-toolbar {
    background: rgba(204, 148, 113, 0.05);
    border: 1px solid rgba(204, 148, 113, 0.2);
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 16px;
}

.nagisa-toolbar-btn {
    padding: 5px 10px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    margin-right: 4px;
    margin-bottom: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.2s ease;
}

.nagisa-toolbar-btn:hover {
    background: rgba(204, 148, 113, 0.1);
    border-color: rgba(204, 148, 113, 0.5);
}

.nagisa-toolbar-select {
    padding: 4px 8px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    margin-right: 4px;
    font-size: 12px;
}

.nagisa-toolbar-input {
    padding: 4px 8px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    margin-right: 4px;
    font-size: 12px;
}

@font-face {
    font-family: "STXINWEI";
    src: url("/assets/webfonts/STXINWEI.TTF") format("truetype");
    font-display: swap;
}
';

// 包含统一页眉
include 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- 侧边导航 -->
        <div class="md:col-span-3">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">内容列表</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="#filebag" class="nagisa-nav-link active">
                                <i class="fas fa-folder-open mr-2"></i>文件袋文本
                            </a>
                        </li>
                        <!-- 未来可以在这里添加更多信息页面相关的选项 -->
                    </ul>
                </div>
            </div>
            
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">使用说明</h2>
                <div class="p-4">
                    <ul class="space-y-2 text-gray-600 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>修改信息页面上文件袋上显示的文本</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>文本将显示在文件袋的左下方位置</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>可以使用文本编辑工具设置格式</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>支持字体大小、粗体、斜体等格式</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- 主要内容区域 -->
        <div class="md:col-span-9">
            <div id="filebag" class="nagisa-card">
                <h2 class="nagisa-card-header">文件袋文本管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        修改信息页面中文件袋上显示的文本内容。
                    </p>
                    
                    <form method="POST">
                        <!-- 文本编辑工具栏 -->
                        <div class="nagisa-toolbar">
                            <div class="flex flex-wrap items-center">
                                <button type="button" class="nagisa-toolbar-btn" onclick="insertTag('b')"><i class="fas fa-bold"></i></button>
                                <button type="button" class="nagisa-toolbar-btn" onclick="insertTag('i')"><i class="fas fa-italic"></i></button>
                                <button type="button" class="nagisa-toolbar-btn" onclick="insertTag('u')"><i class="fas fa-underline"></i></button>
                                <span class="mx-1 text-gray-300">|</span>
                                
                                <div class="flex items-center mr-2">
                                    <span class="text-xs mr-1">字号:</span>
                                    <input type="text" id="custom-size" class="nagisa-toolbar-input w-12" placeholder="1-7">
                                    <button type="button" class="nagisa-toolbar-btn ml-1" onclick="insertCustomFontSize()">应用</button>
                                </div>
                                
                                <div class="flex items-center mr-2">
                                    <span class="text-xs mr-1">颜色:</span>
                                    <input type="text" id="custom-color" class="nagisa-toolbar-input w-20" placeholder="#RRGGBB">
                                    <input type="color" id="color-picker" class="h-6 w-6 p-0 mx-1 cursor-pointer" onchange="document.getElementById('custom-color').value = this.value">
                                    <button type="button" class="nagisa-toolbar-btn ml-1" onclick="insertCustomFontColor()">应用</button>
                                </div>
                                
                                <span class="mx-1 text-gray-300">|</span>
                                
                                <div class="flex items-center">
                                    <span class="text-xs mr-1">字体:</span>
                                    <select id="font-family" class="nagisa-toolbar-select" onchange="insertFontFamily(this.value); this.selectedIndex = 0;">
                                        <option value="" selected disabled>选择字体</option>
                                        <option value="STXINWEI">华文新魏</option>
                                        <option value="SimSun">宋体</option>
                                        <option value="KaiTi">楷体</option>
                                        <option value="Microsoft YaHei">微软雅黑</option>
                                        <option value="SimHei">黑体</option>
                                        <option value="Arial">Arial</option>
                                        <option value="Times New Roman">Times New Roman</option>
                                        <option value="Courier New">Courier New</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">选择文本后点击按钮来应用格式，或直接在文本中插入HTML标签</div>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label" for="filebag_text">
                                文件袋文本:
                            </label>
                            <textarea 
                                name="filebag_text" 
                                id="filebag_text"
                                rows="12"
                                class="nagisa-textarea"
                            ><?php echo htmlspecialchars($filebagText); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">支持多行输入和HTML标签</p>
                        </div>

                        <div class="nagisa-preview-container">
                            <h3 class="nagisa-preview-title">文本预览</h3>
                            <div class="nagisa-preview-display">
                                <div class="font-medium text-lg" id="preview" style="font-family: 'STXINWEI', serif;"><?php echo $filebagText; ?></div>
                            </div>
                            <button type="button" class="nagisa-btn-secondary nagisa-btn-mini" onclick="updatePreview()">
                                <i class="fas fa-sync-alt mr-1"></i>刷新预览
                            </button>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button 
                                type="submit" 
                                name="update_filebag_text" 
                                class="nagisa-btn"
                            >
                                <i class="fas fa-save mr-2"></i>保存文本
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 获取文本区域和预览区域
    const textarea = document.getElementById('filebag_text');
    const preview = document.getElementById('preview');
    
    // 在文本区域中插入标签
    function insertTag(tag) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const replacement = '<' + tag + '>' + selectedText + '</' + tag + '>';
        
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        updatePreview();
        
        // 重新定位光标
        textarea.focus();
        textarea.setSelectionRange(start + 2 + tag.length + selectedText.length, start + 2 + tag.length + selectedText.length);
    }
    
    // 插入自定义字体大小
    function insertCustomFontSize() {
        const size = document.getElementById('custom-size').value.trim();
        if (!size || isNaN(size) || size < 1 || size > 7) {
            alert('请输入1-7之间的数字');
            return;
        }
        
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const replacement = '<font size="' + size + '">' + selectedText + '</font>';
        
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        updatePreview();
        
        textarea.focus();
    }
    
    // 插入自定义字体颜色
    function insertCustomFontColor() {
        const color = document.getElementById('custom-color').value.trim();
        if (!color) {
            alert('请输入有效的颜色值');
            return;
        }
        
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const replacement = '<font color="' + color + '">' + selectedText + '</font>';
        
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        updatePreview();
        
        textarea.focus();
    }
    
    // 插入字体族
    function insertFontFamily(family) {
        if (!family) return;
        
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const replacement = '<span style="font-family: \'' + family + '\';">' + selectedText + '</span>';
        
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        updatePreview();
        
        textarea.focus();
    }
    
    // 更新预览区域
    function updatePreview() {
        preview.innerHTML = textarea.value;
    }
    
    // 处理Enter键
    textarea.addEventListener('keydown', function(e) {
        // 检查是否按下了Enter键
        if (e.key === 'Enter') {
            // 阻止默认行为（插入换行符）
            e.preventDefault();
            
            // 在当前光标位置插入<br>标签
            const start = this.selectionStart;
            const end = this.selectionEnd;
            
            // 插入<br>标签
            this.value = this.value.substring(0, start) + '<br>' + this.value.substring(end);
            
            // 更新预览
            updatePreview();
            
            // 将光标移到<br>后面
            this.selectionStart = this.selectionEnd = start + 4;
        }
    });
    
    // 监听粘贴事件，处理粘贴的文本中的换行符
    textarea.addEventListener('paste', function(e) {
        // 让粘贴操作先完成
        setTimeout(() => {
            // 自动转换文本中的换行符为<br>标签
            convertNewlinesToBr();
        }, 0);
    });
    
    // 监听输入事件，处理可能输入的换行符
    textarea.addEventListener('input', function(e) {
        // 自动转换文本中的换行符为<br>标签
        convertNewlinesToBr();
    });
    
    // 将文本中的\n换行符转换为<br>标签
    function convertNewlinesToBr() {
        const currentText = textarea.value;
        if (currentText.includes('\n')) {
            // 保存当前光标位置
            const currentPos = textarea.selectionStart;
            
            // 计算新增的<br>标签数量（用于调整光标位置）
            const newlines = currentText.split('\n').length - 1;
            const brTagLength = 4; // <br> 的长度
            const additionalLength = newlines * (brTagLength - 1); // 每个\n被替换为<br>后增加的长度
            
            // 替换\n为<br>
            textarea.value = currentText.replace(/\n/g, '<br>');
            
            // 调整光标位置
            textarea.selectionStart = textarea.selectionEnd = currentPos + additionalLength;
            
            // 更新预览
            updatePreview();
        }
    }
    
    // 页面加载时处理现有的换行符并更新预览
    document.addEventListener('DOMContentLoaded', function() {
        convertNewlinesToBr();
        updatePreview();
    });
</script>

<?php
// 包含统一页脚
include 'admin_footer.php';
?> 