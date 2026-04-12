/* jshint esversion: 8 */
/**
 * #线索墙组件
 * 这个组件可以嵌入任何网站，展示动态内容
 */
(function() {
    'use strict';
    
    // 配置参数
    const CONFIG = {
        dataFile: 'api/fanart_api.php', // API代理，负责获取数据和提供缓存访问
        useStaticData: false, // 默认不使用静态数据，优先尝试API
        enableMouseWheel: true // 默认启用鼠标滚轮滚动
    };
    
    // DOM元素
    let gallery = document.getElementById('dynamicGallery');
    const loading = document.getElementById('loading');
    let isLoading = false;
    
    // 初始化函数，确保DOM元素存在
    function initializeElements() {
        gallery = document.getElementById('dynamicGallery');
        if (!gallery) {
            console.error('找不到dynamicGallery元素，线索墙可能无法正常工作');
        }
    }
    
    // 在DOMContentLoaded事件中重新初始化元素
    document.addEventListener('DOMContentLoaded', function() {
        initializeElements();
    });

    // 获取动态数据
    async function fetchDynamics(keepExisting = false) {
        try {
            isLoading = true;
            loading.style.display = 'flex';
            
            let data;
            let errorLog = null;
            
            console.log('开始获取同人图墙数据...');
            
            // 第一级：尝试使用最新的缓存内容
            console.log('第一级：尝试获取最新的缓存内容');
            try {
                // 获取缓存文件列表
                const cacheListResponse = await fetch(`${CONFIG.dataFile}?action=list_cache&t=${new Date().getTime()}`, {
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                
                if (!cacheListResponse.ok) {
                    throw new Error(`获取缓存列表失败! status: ${cacheListResponse.status}`);
                }
                
                const cacheListData = await cacheListResponse.json();
                
                if (cacheListData.code !== 0 || !cacheListData.data || !cacheListData.data.files || cacheListData.data.files.length === 0) {
                    throw new Error('未找到可用的缓存文件');
                }
                
                // 获取最新的缓存文件
                const latestCacheFile = cacheListData.data.files[0];
                console.log(`找到最新缓存文件: ${latestCacheFile.filename}, 创建时间: ${latestCacheFile.time}`);
                
                // 检查缓存文件是否过旧(超过一天)
                const cacheTime = new Date(latestCacheFile.time).getTime();
                const currentTime = new Date().getTime();
                const oneDayMs = 24 * 60 * 60 * 1000; // 一天的毫秒数
                const cacheIsOld = (currentTime - cacheTime) > oneDayMs;
                
                // 获取缓存文件内容
                const cacheResponse = await fetch(`${CONFIG.dataFile}?action=get_cache&file=${encodeURIComponent(latestCacheFile.filename)}&t=${new Date().getTime()}`, {
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                
                if (!cacheResponse.ok) {
                    throw new Error(`加载缓存文件失败! status: ${cacheResponse.status}`);
                }
                
                const cacheText = await cacheResponse.text();
                
                try {
                    const cacheData = JSON.parse(cacheText);
                    
                    if (cacheData.code === 0) {
                        console.log(`成功加载缓存文件内容: ${latestCacheFile.filename}`);
                        data = cacheData.data;
                        
                        // 第二级：如果缓存过旧(超过一天)，尝试手动更新
                        if (cacheIsOld) {
                            console.log('第二级：缓存已过期(超过一天)，尝试手动更新');
                            try {
                                // 调用update_cache接口尝试更新缓存
                                const updateResponse = await fetch(`${CONFIG.dataFile}?action=update_cache&t=${new Date().getTime()}`, {
                                    headers: {
                                        'Cache-Control': 'no-cache',
                                        'Pragma': 'no-cache'
                                    }
                                });
                                
                                if (!updateResponse.ok) {
                                    throw new Error(`更新缓存失败! status: ${updateResponse.status}`);
                                }
                                
                                const updateResult = await updateResponse.json();
                                
                                if (updateResult.code === 0) {
                                    console.log('缓存更新成功，重新获取最新数据');
                                    
                                    // 重新获取缓存列表
                                    const newCacheListResponse = await fetch(`${CONFIG.dataFile}?action=list_cache&t=${new Date().getTime()}`, {
                                        headers: {
                                            'Cache-Control': 'no-cache',
                                            'Pragma': 'no-cache'
                                        }
                                    });
                                    
                                    if (newCacheListResponse.ok) {
                                        const newCacheListData = await newCacheListResponse.json();
                                        
                                        if (newCacheListData.code === 0 && newCacheListData.data && newCacheListData.data.files && newCacheListData.data.files.length > 0) {
                                            // 获取最新的缓存文件
                                            const newLatestCacheFile = newCacheListData.data.files[0];
                                            console.log(`找到最新更新的缓存文件: ${newLatestCacheFile.filename}`);
                                            
                                            // 获取新的缓存文件内容
                                            const newCacheResponse = await fetch(`${CONFIG.dataFile}?action=get_cache&file=${encodeURIComponent(newLatestCacheFile.filename)}&t=${new Date().getTime()}`, {
                                                headers: {
                                                    'Cache-Control': 'no-cache',
                                                    'Pragma': 'no-cache'
                                                }
                                            });
                                            
                                            if (newCacheResponse.ok) {
                                                const newCacheText = await newCacheResponse.text();
                                                const newCacheData = JSON.parse(newCacheText);
                                                
                                                if (newCacheData.code === 0) {
                                                    console.log('成功获取更新后的缓存数据');
                                                    data = newCacheData.data;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // 第三级：如果无法手动更新，记录错误
                                    errorLog = `更新缓存失败: ${updateResult.message || '未知错误'}`;
                                    console.warn(`第三级: ${errorLog}，继续使用旧缓存`);
                                    
                                    // 创建或更新错误日志文件
                                    try {
                                        const errorLogData = {
                                            time: new Date().toISOString(),
                                            type: 'cache_update_failed',
                                            message: errorLog
                                        };
                                        
                                        // 这里我们不实际记录日志，因为在前端无法直接写入文件，只输出到控制台
                                        console.error('记录错误:', errorLogData);
                                        
                                        // 显示一个小的通知给管理员
                                        const notice = document.createElement('div');
                                        notice.style.cssText = 'position:fixed; bottom:10px; right:10px; background:rgba(255,0,0,0.7); color:white; padding:10px; border-radius:5px; z-index:9999; font-size:12px;';
                                        notice.textContent = '同人图墙缓存更新失败，请管理员检查';
                                        document.body.appendChild(notice);
                                        setTimeout(() => notice.remove(), 5000);
                                        
                                    } catch (logError) {
                                        console.error('无法记录错误日志:', logError);
                                    }
                                }
                            } catch (updateError) {
                                // 记录更新错误，但继续使用旧缓存
                                errorLog = `尝试更新缓存时出错: ${updateError.message}`;
                                console.warn(`第三级: ${errorLog}，继续使用旧缓存`);
                                
                                // 显示一个小的通知给管理员
                                const notice = document.createElement('div');
                                notice.style.cssText = 'position:fixed; bottom:10px; right:10px; background:rgba(255,0,0,0.7); color:white; padding:10px; border-radius:5px; z-index:9999; font-size:12px;';
                                notice.textContent = '同人图墙缓存更新失败，请管理员检查';
                                document.body.appendChild(notice);
                                setTimeout(() => notice.remove(), 5000);
                            }
                        } else {
                            console.log('缓存尚未过期，无需更新');
                        }
                        
                        return data;
                    } else {
                        throw new Error(`缓存数据无效: ${cacheData.message || '未知错误'}`);
                    }
                } catch (jsonError) {
                    console.error('缓存JSON解析错误:', jsonError);
                    console.error('缓存文本内容预览:', cacheText.substring(0, 200) + '...');
                    console.warn('缓存解析失败，将直接尝试从API获取数据');
                    // 不抛出错误，而是直接从API获取数据
                    // 添加时间戳参数避免缓存
                    const timestamp = new Date().getTime();
                    try {
                        const response = await fetch(`${CONFIG.dataFile}?action=get_fanart&t=${timestamp}`, {
                            headers: {
                                'Cache-Control': 'no-cache',
                                'Pragma': 'no-cache'
                            }
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        const text = await response.text();
                        
                        try {
                            data = JSON.parse(text);
                        } catch (apiJsonError) {
                            console.error('API JSON解析错误:', apiJsonError, '原始响应:', text.substring(0, 200) + '...');
                            throw new Error('无效的API JSON响应');
                        }
                        
                        if (data.code === 0) {
                            console.log('成功从API获取数据（缓存解析失败后直接获取）');
                            return data.data;
                        } else {
                            throw new Error(`API请求失败: ${data.message || '未知错误'}`);
                        }
                    } catch (apiError) {
                        throw new Error(`无法解析缓存且API请求失败: ${apiError.message}`);
                    }
                }
            } catch (cacheError) {
                // 如果无法获取缓存，尝试直接从API获取（作为应急措施）
                console.error('无法获取缓存，尝试直接从API获取:', cacheError);
                
                try {
                    // 添加时间戳参数避免缓存
                    const timestamp = new Date().getTime();
                    const response = await fetch(`${CONFIG.dataFile}?action=get_fanart&t=${timestamp}`, {
                        headers: {
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const text = await response.text();
                    
                    try {
                        data = JSON.parse(text);
                    } catch (jsonError) {
                        console.error('JSON解析错误:', jsonError, '原始响应:', text.substring(0, 200) + '...');
                        throw new Error('无效的JSON响应');
                    }
                    
                    if (data.code === 0) {
                        console.log('成功从API获取数据（应急模式）');
                        return data.data;
                    } else {
                        throw new Error(`API请求失败: ${data.message}`);
                    }
                } catch (apiError) {
                    // 所有尝试都失败，显示错误
                    console.error('所有获取数据的尝试均失败:', apiError);
                    const errorMessage = document.createElement('div');
                    errorMessage.style.cssText = 'text-align: center; color: red; padding: 20px;';
                    errorMessage.textContent = `无法加载数据: ${apiError.message}`;
                    if (!keepExisting) {
                        gallery.innerHTML = '';
                    }
                    gallery.appendChild(errorMessage);
                    return null;
                }
            }
        } catch (error) {
            console.error('获取数据失败:', error);
            const errorMessage = document.createElement('div');
            errorMessage.style.cssText = 'text-align: center; color: red; padding: 20px;';
            errorMessage.textContent = `获取数据失败: ${error.message}`;
            if (!keepExisting) {
                gallery.innerHTML = '';
            }
            gallery.appendChild(errorMessage);
            return null;
        } finally {
            isLoading = false;
            loading.style.display = 'none';
        }
    }

    // 格式化时间函数
    function formatTime(timestamp) {
        if (!timestamp) return '';
        
        // 检查是否是已格式化的日期字符串(如"2025/07/06")
        if (typeof timestamp === 'string' && timestamp.includes('/')) {
            // 尝试从格式化的日期字符串中提取月和日
            const parts = timestamp.split('/');
            if (parts.length >= 3) {
                // 假设格式为"yyyy/MM/dd"
                const month = parseInt(parts[1]);
                const day = parseInt(parts[2]);
                if (!isNaN(month) && !isNaN(day)) {
                    return month + '月' + day + '日';
                }
            }
        }
        
        // 确保时间戳是数字类型
        let timestampNum = parseInt(timestamp);
        if (isNaN(timestampNum)) return '';
        
        // 如果时间戳太大（超过2亿），可能是毫秒时间戳
        // 如果时间戳太小（小于10000），可能是错误数据
        if (timestampNum > 200000000000) {
            // 已经是毫秒时间戳
        } else if (timestampNum > 10000) {
            // 转换秒级时间戳为毫秒
            timestampNum = timestampNum * 1000;
        } else {
            // 处理极小值的情况
            return '';
        }
        
        const date = new Date(timestampNum);
        if (isNaN(date.getTime())) return '';
        
        const now = new Date();
        
        // 获取当天的日期部分
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        // 获取昨天的日期
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        // 获取时间部分
        const timeStr = date.getHours().toString().padStart(2, '0') + ':' + 
                       date.getMinutes().toString().padStart(2, '0');
        
        // 获取日期部分 - 只显示月和日
        const month = date.getMonth() + 1;
        const day = date.getDate();
        const dateStr = month + '月' + day + '日';
        
        // 如果是当天的内容，只显示时间
        if (date >= today) {
            return timeStr;
        }
        
        // 如果是昨天的内容，显示"昨天"加时间
        if (date >= yesterday) {
            return `昨天 ${timeStr}`;
        }
        
        // 更早的内容显示日期（只有月日）
        return dateStr;
    }

    // 创建动态卡片
    function createDynamicCard(item) {
        try {
            const dynamic = item.dynamic_card_item;
            const author = dynamic.modules.module_author;
            const content = dynamic.modules.module_dynamic;
            
            // 检查是否包含hdslb.com的图片（包括所有子域名）
            let hasHdslbImage = false;
            
            // 检查opus类型图片
            if (content.major && content.major.opus && content.major.opus.pics) {
                hasHdslbImage = content.major.opus.pics.some(pic => pic.url && pic.url.includes('.hdslb.com'));
            }
            
            // 检查draw类型图片
            if (!hasHdslbImage && content.major && content.major.draw && content.major.draw.pics) {
                hasHdslbImage = content.major.draw.pics.some(pic => pic.url && pic.url.includes('.hdslb.com'));
            }
            
            // 检查common封面
            if (!hasHdslbImage && content.major && content.major.common && content.major.common.cover) {
                const cover = content.major.common.cover;
                if (cover.src && cover.src.includes('.hdslb.com')) {
                    hasHdslbImage = true;
                }
            }
            
            // 如果没有hdslb.com的图片，就跳过这个卡片
            if (!hasHdslbImage) {
                console.log('跳过非hdslb.com图片的卡片');
                return null;
            }
            
            const card = document.createElement('div');
            card.className = 'cluewall-dynamic-card';
            card.onclick = () => window.open(`https:${dynamic.basic.jump_url}`, '_blank');

            // 获取动态文本
            let dynamicText = '';
            
            // 使用安全的属性访问方式
            const majorContent = content.major || {};
            const opus = majorContent.opus || {};
            const title = opus.title || '';
            const summary = opus.summary || {};
            const text = summary.text || '';
            
            if (title && text) {
                // 有标题和正文，标题独立一行并加粗
                let cleanTitle = title.split('\n').map(line => line.replace(/^[\s\u3000]+/, '')).join('\n').trim();
                let lines = text.split('\n');
                lines[0] = lines[0].replace(/^[\s\u3000]+/, '');
                let cleanSummary = lines.map(line => line.replace(/^\s+/, '')).join('\n').trim();
                dynamicText = `<span class='cluewall-dynamic-title'>${cleanTitle}</span>\n${cleanSummary}`;
            } else if (text) {
                let lines = text.split('\n');
                lines[0] = lines[0].replace(/^[\s\u3000]+/, '');
                let cleanSummary = lines.map(line => line.replace(/^\s+/, '')).join('\n').trim();
                dynamicText = `<span class='cluewall-dynamic-title'>&nbsp;</span>\n${cleanSummary}`;
            } else if (title) {
                let cleanTitle = title.split('\n').map(line => line.replace(/^[\s\u3000]+/, '')).join('\n').trim();
                dynamicText = `<span class='cluewall-dynamic-title'>${cleanTitle}</span>`;
            }

            // 获取发布时间
            const pubTime = formatTime(author.pub_ts);

            card.innerHTML = `
                <div class="cluewall-card-content">
                    <div class="cluewall-card-header">
                        <div class="cluewall-author-info">
                            <span class="cluewall-author-name">${author.name}</span>
                        </div>
                        <span class="cluewall-publish-time">${pubTime}</span>
                    </div>
                    <div class="cluewall-dynamic-text">
                        ${dynamicText || '无文本内容'}
                    </div>
                </div>
            `;

            return card;
        } catch (error) {
            console.error('创建卡片失败:', error, item);
            const errorCard = document.createElement('div');
            errorCard.className = 'cluewall-dynamic-card';
            errorCard.innerHTML = `
                <div class="cluewall-card-content">
                    <div class="cluewall-card-header">
                        <div class="cluewall-author-info">
                            <span class="cluewall-author-name">加载失败</span>
                        </div>
                    </div>
                    <div class="cluewall-dynamic-text">
                        无法加载此内容
                    </div>
                </div>
            `;
            return errorCard;
        }
    }

    // 初始加载
    document.addEventListener('DOMContentLoaded', loadData);
    
    // 记录当前已加载的条目数
    let loadedItemsCount = 0;
    // 每次加载条目的数量
    const itemsPerLoad = 12;
    // 保存所有动态数据
    let allDynamicItems = [];
    
    // 向上滚动函数
    function scrollUp() {
        if (!gallery) return;
        // 增加步长使上滚动更快速
        gallery.scrollBy({
            top: -120,
            behavior: 'smooth'
        });
    }

    // 向下滚动函数
    function scrollDown() {
        if (!gallery) return;
        // 增加步长使下滚动更快速
        gallery.scrollBy({
            top: 125,
            behavior: 'smooth'
        });
    }
    
    // 停止滚动函数
    function stopScroll() {
        if (!gallery) return;
        
        // 取消所有滚动效果
        gallery.style.scrollBehavior = 'auto';
        
        // 强制停止当前的滚动
        gallery.scrollBy(0, 0);
        
        // 重置滚动行为
        setTimeout(() => {
            gallery.style.scrollBehavior = 'smooth';
        }, 50);
    }
    
    // 禁用滚轮滚动（已废弃，改为直接支持滚轮滚动）
    function disableMouseWheelScroll() {
        // 不做任何事情，保留此函数避免调用错误
        console.log('滚轮滚动已启用');
    }

    // 设置悬停区域自动滚动
    function setupHoverScrolling() {
        const upArea = document.getElementById('cluewall-hover-up');
        const downArea = document.getElementById('cluewall-hover-down');
        
        let upScrollInterval = null;
        let downScrollInterval = null;
        
        // 向上滚动区域
        if (upArea) {
            upArea.addEventListener('mouseenter', () => {
                // 清除可能存在的任何滚动定时器
                if (downScrollInterval) {
                    clearInterval(downScrollInterval);
                    downScrollInterval = null;
                }
                
                // 先停止所有滚动
                stopScroll();
                
                // 启动向上滚动定时器，缩短间隔增加速度
                upScrollInterval = setInterval(scrollUp, 10);
            });
            
            upArea.addEventListener('mouseleave', () => {
                // 清除滚动定时器
                if (upScrollInterval) {
                    clearInterval(upScrollInterval);
                    upScrollInterval = null;
                }
                
                // 停止滚动
                stopScroll();
            });
        }
        
        // 向下滚动区域
        if (downArea) {
            downArea.addEventListener('mouseenter', () => {
                // 清除可能存在的任何滚动定时器
                if (upScrollInterval) {
                    clearInterval(upScrollInterval);
                    upScrollInterval = null;
                }
                
                // 先停止所有滚动
                stopScroll();
                
                // 启动向下滚动定时器，缩短间隔增加速度
                downScrollInterval = setInterval(scrollDown, 10);
            });
            
            downArea.addEventListener('mouseleave', () => {
                // 清除滚动定时器
                if (downScrollInterval) {
                    clearInterval(downScrollInterval);
                    downScrollInterval = null;
                }
                
                // 停止滚动
                stopScroll();
            });
        }
    }
    
    // 加载更多函数
    function loadMore() {
        try {
            // 如果正在加载，不执行任何操作
            if (isLoading) {
                console.log('正在加载中，忽略此次加载请求');
                return;
            }
            
            // 检查是否有数据可加载
            if (!allDynamicItems || allDynamicItems.length === 0) {
                console.log('没有数据可加载');
                updateLoadMoreButtonStatus(true); // 禁用按钮
                return;
            }
            
            // 如果已经加载完所有数据，禁用按钮
            if (loadedItemsCount >= allDynamicItems.length) {
                console.log('所有数据已加载完毕');
                updateLoadMoreButtonStatus(true); // 禁用按钮
                return;
            }
            
            // 计算本次要加载的条目
            const endIndex = Math.min(loadedItemsCount + itemsPerLoad, allDynamicItems.length);
            const itemsToLoad = allDynamicItems.slice(loadedItemsCount, endIndex);
            
            // 记录实际加载的卡片数
            let actuallyLoaded = 0;
            
            // 获取画廊元素
            const gallery = document.getElementById('dynamicGallery');
            
            // 检查gallery是否存在
            if (!gallery) {
                console.error('画廊元素不存在，无法加载更多数据');
                return;
            }
            
            // 加载这些条目到画廊
            itemsToLoad.forEach(item => {
                const card = createDynamicCard(item);
                if (card) {  // 只添加非null的卡片（即只有hdslb.com图片的卡片）
                    gallery.appendChild(card);
                    actuallyLoaded++;
                }
            });
            
            // 更新已加载数量
            loadedItemsCount = endIndex;
            
            console.log(`加载了${actuallyLoaded}个hdslb.com图片卡片，过滤了${itemsToLoad.length - actuallyLoaded}个非hdslb.com卡片`);
            
            // 如果本次没有实际加载任何卡片，但还有更多数据，继续加载
            if (actuallyLoaded === 0 && loadedItemsCount < allDynamicItems.length) {
                console.log('本次未加载任何卡片，继续尝试加载更多');
                loadMore();
                return;
            }
            
            // 检查是否已加载所有数据
            if (loadedItemsCount >= allDynamicItems.length) {
                console.log('已加载所有可用数据');
                updateLoadMoreButtonStatus(true); // 禁用按钮
            } else {
                updateLoadMoreButtonStatus(false); // 启用按钮
            }
        } catch (error) {
            console.error('加载更多数据失败:', error);
            updateLoadMoreButtonStatus(true); // 出错时禁用按钮
        }
    }

    // 更新加载更多按钮状态
    function updateLoadMoreButtonStatus(forceDisable = false) {
        const loadMoreBtn = document.getElementById('cluewall-load-more');
        if (loadMoreBtn) {
            // 如果强制禁用或没有更多数据可加载
            if (forceDisable || !allDynamicItems || allDynamicItems.length === 0 || loadedItemsCount >= allDynamicItems.length) {
                loadMoreBtn.disabled = true;
                loadMoreBtn.style.opacity = '0.3';
                loadMoreBtn.style.cursor = 'default';
                // 更新按钮标题
                const disabledTitle = loadMoreBtn.getAttribute('data-disabled-title') || '没有更多内容可加载';
                loadMoreBtn.setAttribute('title', disabledTitle);
                console.log('加载更多按钮已禁用');
            } else {
                loadMoreBtn.disabled = false;
                loadMoreBtn.style.opacity = '0.9';
                loadMoreBtn.style.cursor = 'pointer';
                // 恢复原始标题
                loadMoreBtn.setAttribute('title', '加载更多内容');
                console.log('加载更多按钮已启用');
            }
        }
    }

    // 设置加载更多按钮事件
    function setupLoadMoreButton() {
        const loadMoreButton = document.getElementById('cluewall-load-more');
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', loadMore);
        }
    }

    // 修改加载数据函数
    async function loadData(keepExisting = false) {
        if (isLoading) return;
        
        // 显示加载指示器
        if (loading) loading.style.display = 'flex';
        
        // 禁用加载更多按钮，直到数据加载完成
        updateLoadMoreButtonStatus(true);
        
        const data = await fetchDynamics(keepExisting);
        
        // 隐藏加载指示器
        if (loading) loading.style.display = 'none';
        
        if (!data) {
            updateLoadMoreButtonStatus(true); // 没有数据，禁用按钮
            return;
        }

        try {
            // 获取画廊元素
            gallery = document.getElementById('dynamicGallery');
            
            // 检查gallery是否存在
            if (!gallery) {
                console.error('画廊元素不存在，无法加载数据');
                return;
            }
            
            // 保存所有数据
            allDynamicItems = data.topic_card_list.items;
            
            if (!keepExisting) {
                // 初始只加载部分数据
                loadedItemsCount = 0;
                gallery.innerHTML = ''; // 清空现有内容
            }
            
            // 如果有数据可加载，启用按钮
            if (allDynamicItems && allDynamicItems.length > loadedItemsCount) {
                updateLoadMoreButtonStatus(false);
            } else {
                updateLoadMoreButtonStatus(true);
            }
            
            loadMore(); // 加载下一批数据
        } catch (error) {
            console.error('加载数据失败:', error);
            const errorMessage = document.createElement('div');
            errorMessage.style.cssText = 'text-align: center; color: red; padding: 20px;';
            errorMessage.textContent = `加载数据失败: ${error.message}`;
            if (!keepExisting && gallery) {
                gallery.innerHTML = '';
            }
            if (gallery) {
                gallery.appendChild(errorMessage);
            } else {
                console.error('无法显示错误消息：画廊元素不存在');
            }
            updateLoadMoreButtonStatus(true); // 出错时禁用按钮
        }
    }

    // 初始化按钮和滚动功能
    document.addEventListener('DOMContentLoaded', function() {
        console.log('线索墙初始化开始...');
        
        // 重新获取gallery元素
        gallery = document.getElementById('dynamicGallery');
        
        // 设置gallery的overflow属性，确保滚动条正常工作
        if (gallery) {
            gallery.style.overflowY = 'auto';
            gallery.style.overflowX = 'hidden';
        }
        
        // 检查按钮是否正确添加到DOM
        const buttons = document.querySelectorAll('.cluewall-button');
        console.log('找到按钮数量:', buttons.length);
        
        // 查找每个按钮并打印
        const upBtn = document.getElementById('cluewall-scroll-up');
        const downBtn = document.getElementById('cluewall-scroll-down');
        const loadMoreBtn = document.getElementById('cluewall-load-more');
        const topBtn = document.getElementById('cluewall-top-button');
        
        console.log('上滚按钮:', upBtn ? '已找到' : '未找到');
        console.log('下滚按钮:', downBtn ? '已找到' : '未找到');
        console.log('加载更多按钮:', loadMoreBtn ? '已找到' : '未找到');
        console.log('回到顶部按钮:', topBtn ? '已找到' : '未找到');
        
        // 绑定事件
        if (upBtn && downBtn) {
            console.log('绑定上下滚动按钮事件');
            // 已在HTML中绑定，这里不需要重复绑定
        }
        
        // 不再调用禁用滚轮的函数
        // if (typeof disableMouseWheelScroll === 'function') {
        //     disableMouseWheelScroll();
        // } else {
        //     console.warn('disableMouseWheelScroll 函数未定义');
        // }
        
        if (typeof setupHoverScrolling === 'function') {
            setupHoverScrolling();
        } else {
            console.warn('setupHoverScrolling 函数未定义');
        }
        
        setupLoadMoreButton();
        updateLoadMoreButtonStatus(); // 初始化按钮状态
        
        console.log('线索墙初始化完成');
    });
    
    // 提供一个公共API，便于外部网站控制
    window.ClueWall = {
        refresh: loadData,
        setDataSource: (newSource) => {
            CONFIG.dataFile = newSource;
            if (gallery) {
                gallery.innerHTML = ''; // 清空现有内容
            }
            loadData(); // 重新加载数据
        },
        loadMore: loadMore,
        updateLoadMoreButtonStatus: updateLoadMoreButtonStatus
    };
})(); 