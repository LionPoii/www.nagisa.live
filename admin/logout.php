<?php
require_once '../includes/auth.php';
require_once '../includes/account_service.php';

// 使用logoutAdmin函数注销用户
logoutAdmin();

// 重定向到登录页面
header('Location: login.php');
exit; 
 
 