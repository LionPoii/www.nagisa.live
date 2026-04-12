<?php
// 启用错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 设置响应头
header('Content-Type: application/json');

require_once '/www/wwwroot/Nagisa_Uploads/includes/schedule_operations_log.php';

try {
    // 检查是否已登录
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('未授权访问');
    }
    $adminLogUser = isset($_SESSION['admin_username']) ? (string) $_SESSION['admin_username'] : '-';

    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方法不正确');
    }

    // 检查必要参数
    if (!isset($_POST['image_url']) || !isset($_POST['file_name']) || !isset($_POST['action']) || $_POST['action'] !== 'save_schedule_image') {
        throw new Exception('参数不完整: ' . json_encode($_POST));
    }

    // 获取参数
    $image_url = $_POST['image_url'];
    $file_name = $_POST['file_name'];

    // 确保文件名安全
    $file_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $file_name);

    // 确保目标文件夹存在
    $target_dir = '/www/wwwroot/Nagisa_Uploads/Schedule/';
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            throw new Exception('无法创建目标文件夹');
        }
    }

    // 检查目录权限
    if (!is_writable($target_dir)) {
        throw new Exception('目标文件夹没有写入权限');
    }

    // 构建完整的目标路径
    $target_file = $target_dir . $file_name;

    // 处理图片URL
    // 如果是相对路径，转换为绝对路径
    if (strpos($image_url, 'http') !== 0) {
        // 是相对路径，需要转换为服务器上的完整路径
        if (strpos($image_url, '/') === 0) {
            // 以/开头的相对路径
            $server_image_path = '/www/wwwroot/www.nagisa.live' . $image_url;
        } else {
            // 不以/开头的相对路径
            $server_image_path = '/www/wwwroot/www.nagisa.live/' . $image_url;
        }
        
        // 如果是代理脚本，需要直接获取原始图片
        if (strpos($image_url, 'bili_img_proxy.php') !== false) {
            // 从URL中提取原始图片URL
            $parsed_url = parse_url($image_url);
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_params);
                if (isset($query_params['url'])) {
                    $original_url = urldecode($query_params['url']);
                    $image_url = $original_url; // 使用原始URL
                }
            }
        } else {
            // 直接使用服务器路径
            $image_content = @file_get_contents($server_image_path);
            if ($image_content === false) {
                throw new Exception('无法获取图片内容（本地文件）: ' . $server_image_path);
            }
            
            $hadFile = is_file($target_file);
            // 保存图片到目标路径
            $result = file_put_contents($target_file, $image_content);
            if ($result === false) {
                throw new Exception('保存图片失败（本地文件）');
            }
            
            // 设置文件权限
            chmod($target_file, 0644);
            @chown($target_file, 'www');
            @chgrp($target_file, 'www');

            schedule_operations_append_log(
                ($hadFile ? '覆盖(www后台档案馆储存) ' : '添加(www后台档案馆储存) ') . $file_name . ' size=' . $result,
                $adminLogUser
            );
            
            // 返回成功响应
            echo json_encode([
                'success' => true, 
                'message' => '图片已成功保存（本地文件）', 
                'path' => '/Nagisa_Uploads/Schedule/' . $file_name,
                'size' => $result
            ]);
            exit;
        }
    }

    // 获取图片内容（网络URL）
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                'Referer: https://www.nagisa.live/'
            ]
        ]
    ]);

    $image_content = @file_get_contents($image_url, false, $context);
    if ($image_content === false) {
        // 尝试使用curl获取
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $image_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $image_content = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($image_content === false || empty($image_content)) {
                throw new Exception('无法获取图片内容（cURL失败）: ' . $curl_error . ', HTTP Code: ' . $http_code);
            }
        } else {
            throw new Exception('无法获取图片内容（file_get_contents失败）');
        }
    }

    $hadFile = is_file($target_file);
    // 保存图片到目标路径
    $result = file_put_contents($target_file, $image_content);
    if ($result === false) {
        throw new Exception('保存图片失败（网络文件）');
    }

    // 设置文件权限
    chmod($target_file, 0644);
    @chown($target_file, 'www');
    @chgrp($target_file, 'www');

    schedule_operations_append_log(
        ($hadFile ? '覆盖(www后台档案馆储存) ' : '添加(www后台档案馆储存) ') . $file_name . ' size=' . $result,
        $adminLogUser
    );

    // 返回成功响应
    echo json_encode([
        'success' => true, 
        'message' => '图片已成功保存（网络文件）', 
        'path' => '/Nagisa_Uploads/Schedule/' . $file_name,
        'size' => $result
    ]);

} catch (Exception $e) {
    // 返回错误响应
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 