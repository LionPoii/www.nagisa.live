<?php

/**
 * 获取当前自然周周一 00:00:00（Asia/Shanghai）
 */
function schedule_get_current_week_monday(): DateTimeImmutable
{
    $tz = new DateTimeZone('Asia/Shanghai');
    $now = new DateTimeImmutable('now', $tz);
    $weekday = (int) $now->format('N');

    if ($weekday === 1) {
        return $now->setTime(0, 0, 0);
    }

    return $now->modify('-' . ($weekday - 1) . ' days')->setTime(0, 0, 0);
}

/**
 * 判断周表是否在本自然周内上传（周一 00:00 起算）
 */
function schedule_is_uploaded_in_current_week(?string $createdAt): bool
{
    if ($createdAt === null || $createdAt === '') {
        return false;
    }

    $tz = new DateTimeZone('Asia/Shanghai');

    try {
        $uploaded = new DateTimeImmutable($createdAt, $tz);
    } catch (Exception $e) {
        return false;
    }

    return $uploaded >= schedule_get_current_week_monday();
}

function schedule_get_last_auto_close_week(PDO $conn): ?string
{
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'schedule_last_auto_close_week'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['config_value'] === '') {
        return null;
    }

    return (string) $row['config_value'];
}

function schedule_set_last_auto_close_week(PDO $conn, string $weekKey): void
{
    $stmt = $conn->prepare(
        "INSERT INTO site_config (config_key, config_value) VALUES ('schedule_last_auto_close_week', ?)
         ON DUPLICATE KEY UPDATE config_value = ?"
    );
    $stmt->execute([$weekKey, $weekKey]);
}

/**
 * 每周一首次访问时自动关闭非本周上传的周表；同一自然周内不再重复执行，手动开关可覆盖。
 *
 * @return bool 本次是否执行了自动关闭
 */
function schedule_run_weekly_auto_close(PDO $conn): bool
{
    $weekMonday = schedule_get_current_week_monday();
    $weekKey = $weekMonday->format('Y-m-d');

    if (schedule_get_last_auto_close_week($conn) === $weekKey) {
        return false;
    }

    $stmt = $conn->prepare('UPDATE schedule_image SET is_visible = 0 WHERE is_visible = 1 AND created_at < ?');
    $stmt->execute([$weekMonday->format('Y-m-d H:i:s')]);

    schedule_set_last_auto_close_week($conn, $weekKey);

    return $stmt->rowCount() > 0;
}
