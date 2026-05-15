# 直播状态 API — `check_live_status.php`

首页「推理案件中 / 暂时离开」、开播系统通知、`index.php` 整页刷新逻辑均使用本接口。

## 基本信息

| 项目 | 说明 |
|------|------|
| 路径 | `/api/check_live_status.php` |
| 完整 URL | `https://www.nagisa.live/api/check_live_status.php` |
| 方法 | `GET` |
| 响应格式 | `application/json` |
| 鉴权 | 无（公开接口） |
| 跨域 | 同源调用即可；未设置 `Access-Control-Allow-Origin` |
| 服务端缓存 | **15 秒**（`includes/bilibili_live.php` → `BilibiliLive::$cacheTime`） |
| 数据来源 | B 站 `https://api.live.bilibili.com/room/v1/Room/get_info?room_id={room_id}` |
| 直播间 ID | 数据库 `site_config.bilibili_room_id`，默认 `31368705` |

## 请求

无必填参数。建议加时间戳避免浏览器缓存：

```
GET /api/check_live_status.php?_={timestamp}
```

### 示例

```bash
curl -s 'https://www.nagisa.live/api/check_live_status.php?_='$(date +%s)
```

```javascript
const res = await fetch('/api/check_live_status.php?_=' + Date.now());
const data = await res.json();
```

```php
$json = file_get_contents('https://www.nagisa.live/api/check_live_status.php?_=' . time());
$data = json_decode($json, true);
```

## 成功响应

HTTP `200`

```json
{
  "success": true,
  "room_id": "31368705",
  "is_living": false,
  "title": "周五的歌杂",
  "cover_url": "https://i0.hdslb.com/bfs/live/new_room_cover/xxx.jpg",
  "timestamp": 1778870398
}
```

### 字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `success` | `boolean` | 固定为 `true` |
| `room_id` | `string` \| `number` | B 站直播间 ID |
| `is_living` | `boolean` | **`true`** = 正在直播；**`false`** = 未开播 |
| `title` | `string` | 直播标题；未开播时多为房间预设/上次标题 |
| `cover_url` | `string` | 封面图 HTTPS URL；优先 `user_cover`，否则 `keyframe`；均无则为 `""` |
| `timestamp` | `number` | 服务端生成响应时的 Unix 时间戳（秒） |

### `is_living` 判定规则

底层 B 站字段 `live_status === 1` 时返回 `true`，否则 `false`。

## 失败响应

HTTP `200`（异常仍输出 JSON，非 5xx）

```json
{
  "success": false,
  "error": "获取直播状态失败",
  "message": "具体异常信息",
  "timestamp": 1778870398
}
```

## 轮询建议

| 场景 | 建议间隔 | 说明 |
|------|---------|------|
| 状态文字实时更新 | 1～5 秒 | 前端可高频请求，服务端最多 15 秒更新一次 B 站数据 |
| 后台定时任务 | ≥ 15 秒 | 与缓存对齐，避免无效重复拉取 |
| 开播通知 | 监听状态变化 | 对比前后两次 `is_living`，`false → true` 时触发 |

## 在本站中的使用位置

| 文件 | 用途 |
|------|------|
| `components/bilibili_live_status.php` | 每 1 秒轮询，更新「推理案件中 / 暂时离开」 |
| `components/notification_button.php` | 监听 `liveStatusChanged` 事件，开播弹系统通知 |
| `index.php` | 每 60 秒检查；直播中暂停 1 小时整页自动刷新 |

## 相关接口

| 路径 | 站点 | 说明 |
|------|------|------|
| `/api/live_status_duration.php` | **songdata.nagisa.live** | 仅返回 `is_living` + `live_duration`（见该站点 `api/live_status_duration.md`） |

若需要观看人数、分区、开播时间等完整 B 站字段，可使用：

| 路径 | 说明 |
|------|------|
| `/api/live_status_notice.php` | 返回 B 站 `get_info` 解析后的完整 `data` 对象 |
| `/api/live_status_api.php` | 按 UID 查询（依赖 `config.php` 中 `BILIBILI_UID`） |

`live_status_notice.php` 原始字段示例（未在本接口暴露）：

- `live_status` — 0/1，与 `is_living` 对应
- `online` — 当前观看人数
- `area_name` / `parent_area_name` — 分区
- `live_time` — 开播时间
- `uid` — 主播 UID
- `user_cover` / `keyframe` / `background` — 图片 URL

## 实现文件

- 接口：`api/check_live_status.php`
- 核心类：`includes/bilibili_live.php`
- 缓存文件：`api/api_cache/live_status_api/bilibili_live_{room_id}.json`
- 日志：`logs/live_status_api/bilibili_live_{日期}.log`

## 扩展说明

当前接口为精简版。若新功能需要 `online`、`area_name` 等字段，可在 `check_live_status.php` 中从 `BilibiliLive::getLiveInfo()` 追加输出，或在前端/后端直接调用 `live_status_notice.php`。
