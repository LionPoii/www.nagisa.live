# 移动设备适配模块

本目录包含用于处理移动设备访问的相关文件和工具。由于主站已移除内联的移动设备适配代码，这些文件提供了一种独立的方式来处理移动设备访问。

## 文件说明

- `mobile_device.php` - 移动设备检测与处理模块，提供检测设备类型和相关功能的函数
- `mobile_css.php` - 移动设备CSS样式文件，包含所有移动设备的样式定义
- `mobile_template.php` - 移动设备页面模板，提供构建移动设备友好页面的函数

## 使用方法

### 1. 检测移动设备

```php
// 引入移动设备检测模块
require_once 'includes/mobile_device.php';

// 检测是否为移动设备
if (is_mobile_device()) {
    // 移动设备访问的处理逻辑
} else {
    // 桌面设备访问的处理逻辑
}

// 获取具体设备类型
$device_type = get_device_type(); // 返回 'mobile', 'tablet' 或 'desktop'
```

### 2. 重定向移动设备到专用页面

```php
require_once 'includes/mobile_device.php';

// 将移动设备重定向到移动版页面
redirect_mobile_users('mobile/index.php');
```

### 3. 使用移动设备模板创建页面

```php
require_once 'includes/mobile_template.php';

// 输出页面头部
mobile_header('页面标题', ['custom.css'], ['custom.js']);

// 输出页面内容头部
mobile_page_header('页面标题', 'back_url.php');

// 输出卡片
mobile_card_start('卡片标题');
echo '<p>卡片内容</p>';
mobile_card_end();

// 输出按钮
mobile_button('点击我', 'action.php', 'fas fa-check', 'primary');

// 输出页面内容尾部
mobile_page_footer();

// 输出页面尾部
mobile_footer('© ' . date('Y') . ' Nagisa Live');
```

### 4. 加载设备特定的CSS

```php
require_once 'includes/mobile_device.php';

// 根据设备类型加载不同的CSS
echo load_device_css('desktop.css', 'mobile.css');
```

### 5. 获取适合当前设备的viewport meta标签

```php
require_once 'includes/mobile_device.php';

// 在<head>标签中输出适合当前设备的viewport设置
echo get_viewport_meta();
```

## 注意事项

1. 确保在使用任何模板函数前先引入相应的文件
2. 移动设备CSS样式文件(`mobile_css.php`)可以直接通过`<link>`标签引入
3. 所有函数都已经进行了XSS防护处理，输出的内容会自动进行HTML转义
4. 如需添加新的移动设备特定样式，请在`mobile_css.php`中添加 