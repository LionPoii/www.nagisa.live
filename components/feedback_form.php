<style>
/* 反馈表单主题样式 */
#feedbackModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0,0,0,0.7);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    overflow: auto;
    backdrop-filter: blur(2px);
}
#feedbackModal.show {
    display: flex !important;
}
#feedbackModal .footer-modal-content {
    position: relative;
    margin: 0;
    top: auto;
    left: auto;
    transform: none;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid #cc9471;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(204, 148, 113, 0.18), 0 2px 8px rgba(77, 64, 48, 0.08);
    font-family: 'QiantuHouhei', 'Microsoft YaHei', sans-serif;
    color: #4D4030;
    backdrop-filter: blur(6px);
    padding: 32px 28px 24px 28px;
}

#feedbackModal .footer-modal-title {
    font-size: 1.5rem;
    color: #4C526B;
    font-weight: normal; /* 修改: 从bold改为normal，移除粗体 */
    letter-spacing: 2px;
    text-align: center;
    margin-bottom: 18px;
    font-family: 'QiantuHouhei', 'Microsoft YaHei', sans-serif;
}

#feedbackModal label {
    color: #4D4030;
    font-weight: 500;
    margin-bottom: 4px;
    display: block;
}

#feedbackModal input[type="text"],
#feedbackModal select,
#feedbackModal textarea {
    width: 100%;
    border: 1.5px solid #e8d6c3;
    border-radius: 8px;
    padding: 8px 10px;
    margin-top: 2px;
    margin-bottom: 8px;
    background: #fff8f3;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-family: inherit;
}

#feedbackModal input[type="text"]:focus,
#feedbackModal select:focus,
#feedbackModal textarea:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 2px #f3e3d3;
    outline: none;
}

#feedbackModal button[type="submit"] {
    background: linear-gradient(90deg, #cc9471 60%, #e8a274 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: normal; /* 修改: 从bold改为normal，移除粗体 */
    padding: 10px 0;
    margin-top: 8px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(204, 148, 113, 0.12);
    transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
}

#feedbackModal button[type="submit"]:hover {
    background: linear-gradient(90deg, #b97a4a 60%, #cc9471 100%);
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 4px 16px rgba(204, 148, 113, 0.18);
}

#feedbackModal .footer-modal-close {
    color: #cc9471;
    font-size: 2rem;
    position: absolute;
    top: 18px;
    right: 28px;
    cursor: pointer;
    transition: color 0.2s;
}
#feedbackModal .footer-modal-close:hover {
    color: #b97a4a;
}
/* 已复制按钮样式：保持灰底白字，悬停不改变颜色 */
.copied {
    background: #6B7280 !important;
    color: #fff !important;
}
.copied:hover {
    background: #6B7280 !important;
    color: #fff !important;
    cursor: default;
}
</style>

<!-- 反馈表单模态窗口 -->
<div id="feedbackModal" class="footer-modal">
    <div class="footer-modal-content" style="max-width:420px;">
        <span class="footer-modal-close" onclick="closeFeedbackModal()">&times;</span>
        <div class="footer-modal-title">网站反馈</div>
        <form id="feedbackForm" enctype="multipart/form-data" autocomplete="off">
            <div style="margin-bottom:12px;">
                <label>反馈类型：</label>
                <select name="type" required style="width:100%;padding:6px;">
                    <option value="建议">建议</option>
                    <option value="BUG">BUG</option>
                    <option value="内容补充">内容补充</option>
                    <option value="其他">其他</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label>ID（可选）：</label>
                <input type="text" name="name" maxlength="100" style="width:100%;padding:6px;">
            </div>
            <div style="margin-bottom:12px;">
                <label>反馈内容：</label>
                <textarea name="message" required maxlength="500" style="width:100%;height:80px;padding:6px;"></textarea>
            </div>
            <div style="margin-bottom:12px;">
                <label>图片上传（可选，最多3张）：</label>
                <input type="file" name="images[]" accept="image/*" multiple style="width:100%;">
            </div>
            <button type="submit" style="width:100%;padding:8px;background:#cc9471;color:#fff;border:none;border-radius:5px;font-weight:normal;">提交反馈</button>
        </form>
        <!-- 单号查询区域 -->
        <div style="margin-top:12px;border-top:1px dashed #e8d6c3;padding-top:12px;">
            <label style="display:block;margin-bottom:6px;color:#4D4030;font-weight:500;">按单号查询进度：</label>
            <div style="display:flex;gap:8px;">
                <input type="text" id="queryTicketInput" placeholder="" style="flex:1;border:1.5px solid #e8d6c3;border-radius:8px;padding:8px 10px;background:#fff8f3;font-size:1rem;">
                <button type="button" id="queryTicketBtn" style="padding:8px 12px;background:linear-gradient(90deg,#cc9471 60%,#e8a274 100%);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:normal;">查询进度</button>
            </div>
        </div>

        <div id="feedbackResult" style="margin-top:10px;font-weight:500;white-space:pre-wrap;"></div>
    </div>
</div>
<script>
function showFeedbackModal() {
    var modal = document.getElementById('feedbackModal');
    modal.style.display = 'flex';
    modal.classList.add('show');
    document.getElementById('feedbackResult').innerText = '';
}
function closeFeedbackModal() {
    var modal = document.getElementById('feedbackModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
}
// ESC关闭
window.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeFeedbackModal();
});
// 表单提交
const feedbackForm = document.getElementById('feedbackForm');
// 缓存最近一次提交的 message（用于下载文件，即使表单被 reset）
let lastSubmittedMessage = '';
if(feedbackForm){
    feedbackForm.onsubmit = function(e){
        e.preventDefault();
        // 缓存提交时的反馈内容
        const msgField = feedbackForm.querySelector('textarea[name=message]');
        lastSubmittedMessage = msgField ? msgField.value.trim() : '';
        const formData = new FormData(feedbackForm);
        const btn = feedbackForm.querySelector('button[type=submit]');
        btn.disabled = true;
        btn.innerText = '提交中...';
        fetch('/api/feedback_submit.php', {
            method: 'POST',
            body: formData
        }).then(r=>r.json()).then(res=>{
            btn.disabled = false;
            btn.innerText = '提交反馈';
            const resultDiv = document.getElementById('feedbackResult');
            // 显示消息
            resultDiv.innerText = res.msg || '';
            
            // 根据成功或失败设置不同的颜色
            if(res.success){
                resultDiv.style.color = '#2F855A'; // 绿色
                // 显示单号（如果后端返回）
                if (res.ticket) {
                    // 显示单号和操作按钮（复制 / 用此单号查询）
                    const ticketWrap = document.createElement('div');
                    ticketWrap.style.marginTop = '8px';
                    ticketWrap.style.display = 'flex';
                    ticketWrap.style.alignItems = 'center';
                    ticketWrap.style.gap = '8px';

                    // 一行显示：左侧单号标签，右侧复制按钮（样式与提交按钮保持一致的橙色白字）
                    const ticketRow = document.createElement('div');
                    ticketRow.style.display = 'flex';
                    ticketRow.style.justifyContent = 'space-between';
                    ticketRow.style.alignItems = 'center';
                    ticketRow.style.marginTop = '8px';

                    const ticketLabel = document.createElement('div');
                    // 标签颜色为 #cc9471，票号文本为 #4C526B
                    const labelSpan = document.createElement('span');
                    labelSpan.style.color = '#cc9471';
                    labelSpan.textContent = '单号：';
                    ticketLabel.appendChild(labelSpan);

                    const numSpan = document.createElement('span');
                    numSpan.style.color = '#4C526B';
                    numSpan.style.fontWeight = '600';
                    numSpan.style.marginLeft = '6px';
                    numSpan.textContent = res.ticket;
                    ticketLabel.appendChild(numSpan);

                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.textContent = '复制编号';
                    copyBtn.style.padding = '6px 10px';
                    copyBtn.style.borderRadius = '8px';
                    copyBtn.style.border = 'none';
                    copyBtn.style.cursor = 'pointer';
                    copyBtn.style.background = 'linear-gradient(90deg,#cc9471 60%,#e8a274 100%)';
                    copyBtn.style.color = '#fff';
                    copyBtn.style.fontWeight = 'normal';
                    copyBtn.style.fontSize = '0.95rem';
                    copyBtn.addEventListener('click', function() {
                        const toCopy = res.ticket;
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(toCopy).then(() => {
                                // 成功后按钮自身变为灰色白字并显示已复制，大小不变
                                copyBtn.style.background = '#6B7280';
                                copyBtn.style.color = '#fff';
                                copyBtn.textContent = '已复制';
                                copyBtn.classList.add('copied');
                            }).catch(() => {
                                fallbackCopy();
                            });
                        } else {
                            fallbackCopy();
                        }

                        function fallbackCopy() {
                            const ta = document.createElement('textarea');
                            ta.value = toCopy;
                            document.body.appendChild(ta);
                            ta.select();
                            try {
                                document.execCommand('copy');
                                copyBtn.style.background = '#6B7280';
                                copyBtn.style.color = '#fff';
                                copyBtn.textContent = '已复制';
                                copyBtn.classList.add('copied');
                            } catch (e) {
                                alert('复制失败，请手动复制：' + toCopy);
                            }
                            ta.remove();
                        }
                    });

                    ticketRow.appendChild(ticketLabel);
                    // 下载按钮（生成包含编号、反馈内容与感谢语的 TXT 文件）
                    const downloadBtn = document.createElement('button');
                    downloadBtn.type = 'button';
                    downloadBtn.textContent = '下载';
                    downloadBtn.style.marginLeft = '8px';
                    downloadBtn.style.padding = '6px 10px';
                    downloadBtn.style.borderRadius = '8px';
                    downloadBtn.style.border = 'none';
                    downloadBtn.style.cursor = 'pointer';
                    // 下载按钮样式与复制按钮一致（橙底白字）
                    downloadBtn.style.background = 'linear-gradient(90deg,#cc9471 60%,#e8a274 100%)';
                    downloadBtn.style.color = '#fff';
                    downloadBtn.style.fontWeight = 'normal';
                    downloadBtn.style.fontSize = '0.95rem';
                    downloadBtn.addEventListener('click', function() {
                        // 使用提交时缓存的反馈内容
                        const feedbackContent = lastSubmittedMessage || '';
                        const date = new Date();
                        const yyyy = date.getFullYear();
                        const mm = String(date.getMonth() + 1).padStart(2, '0');
                        const dd = String(date.getDate()).padStart(2, '0');
                        const filename = `Nagisa${yyyy}${mm}${dd}.txt`;
                        const fileContent = `反馈编号：${res.ticket}\n\n反馈内容：\n${feedbackContent}\n\n感谢反馈，以及为粉丝站建设出力！`;
                        const blob = new Blob([fileContent], { type: 'text/plain;charset=utf-8' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        setTimeout(() => URL.revokeObjectURL(url), 1000);
                    });

                    // 按钮顺序：下载 按钮在复制按钮左侧
                    ticketRow.appendChild(downloadBtn);
                    ticketRow.appendChild(copyBtn);
                    resultDiv.appendChild(ticketRow);
                }
                feedbackForm.reset();
                // 不自动关闭模态，保留以便用户复制或查询单号
            } else {
                resultDiv.style.color = '#E53E3E'; // 红色
            }
        }).catch(()=>{
            btn.disabled = false;
            btn.innerText = '提交反馈';
            const resultDiv = document.getElementById('feedbackResult');
            resultDiv.innerText = '提交失败，请稍后再试';
            resultDiv.style.color = '#E53E3E'; // 红色
        });
    }
}

// 查询单号进度（在模态外部也可调用）
function queryFeedbackStatus(ticket) {
    if (!ticket || ticket.trim() === '') return;
    const resultDiv = document.getElementById('feedbackResult');
    resultDiv.style.color = '';
    resultDiv.innerText = '查询中...';
    return fetch('/api/feedback_status.php?ticket=' + encodeURIComponent(ticket))
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                resultDiv.style.color = '#E53E3E';
                resultDiv.innerText = res.msg || '未找到单号';
                return res;
            }
            // 不重复显示单号，按要求显示 状态/回复时间/回复内容
            resultDiv.innerHTML = '';
            const isProcessed = parseInt(res.status) === 1;
            const labelColor = '#cc9471';

            // 状态行
            const statusLabel = document.createElement('span');
            statusLabel.style.color = labelColor;
            statusLabel.textContent = '状态：';
            resultDiv.appendChild(statusLabel);

            const statusText = document.createElement('span');
            statusText.style.color = isProcessed ? '#2da44e' : '#b06000';
            statusText.style.marginRight = '6px';
            statusText.textContent = isProcessed ? '已处理' : '未处理';
            resultDiv.appendChild(statusText);
            resultDiv.appendChild(document.createElement('br'));

            // 时间（如果存在）
            if (res.reply_at) {
                const timeLabel = document.createElement('span');
                timeLabel.style.color = labelColor;
                timeLabel.textContent = '时间：';
                resultDiv.appendChild(timeLabel);

                const timeText = document.createElement('span');
                timeText.style.color = '#000';
                timeText.textContent = res.reply_at;
                resultDiv.appendChild(timeText);
                resultDiv.appendChild(document.createElement('br'));
            }

            // 内容（如果存在）
            if (res.reply) {
                const replyLabel = document.createElement('span');
                replyLabel.style.color = labelColor;
                replyLabel.textContent = '内容：';
                resultDiv.appendChild(replyLabel);

                const replyText = document.createElement('div');
                replyText.style.color = '#000';
                replyText.style.marginTop = '6px';
                replyText.style.whiteSpace = 'normal';
                replyText.textContent = res.reply;
                resultDiv.appendChild(replyText);
            }

            return res;
        }).catch(() => {
            resultDiv.style.color = '#E53E3E';
            resultDiv.innerText = '查询失败，请稍后再试';
            return { success: false };
        });
}

// 绑定查询按钮与回车事件
const queryBtn = document.getElementById('queryTicketBtn');
const queryInput = document.getElementById('queryTicketInput');
if (queryBtn && queryInput) {
    queryBtn.addEventListener('click', function(){
        const t = queryInput.value.trim();
        if (!t) return;
        queryBtn.disabled = true;
        const origText = queryBtn.innerText;
        queryBtn.innerText = '查询中...';
        queryFeedbackStatus(t).finally(() => {
            queryBtn.disabled = false;
            queryBtn.innerText = origText;
        });
    });
    queryInput.addEventListener('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            const t = queryInput.value.trim();
            if (!t) return;
            queryBtn.disabled = true;
            const origText = queryBtn.innerText;
            queryBtn.innerText = '查询中...';
            queryFeedbackStatus(t).finally(() => {
                queryBtn.disabled = false;
                queryBtn.innerText = origText;
            });
        }
    });
}
// 订单查询大按钮绑定（与查询按钮行为一致，样式与提交按钮相似）
// (已移除大型订单查询按钮，故无需额外绑定)
</script> 