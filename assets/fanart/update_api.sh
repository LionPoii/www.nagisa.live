#!/bin/bash

# 设置日志文件
LOG_FILE="/www/wwwroot/www.nagisa.live/assets/fanart/update_log.txt"

# 记录开始时间
echo "$(date): 开始更新API数据" >> $LOG_FILE

# 使用curl获取API数据
curl -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36" \
  "https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic?topic_id=1134905&sort_by=3&offset=&page_size=20&source=Web&features=itemOpusStyle%2ClistOnlyfans%2CopusBigCover%2ConlyfansVote%2CdecorationCard" \
  -o /www/wwwroot/www.nagisa.live/assets/fanart/api_response.json

# 检查是否成功
if [ $? -eq 0 ]; then
  echo "$(date): API数据更新成功" >> $LOG_FILE
else
  echo "$(date): API数据更新失败" >> $LOG_FILE
fi 