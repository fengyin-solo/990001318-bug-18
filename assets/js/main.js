/**
 * 社区便民留言板 - 前端脚本
 */
/**
 * 复制一份滚动内容以实现无缝滚动。
 * 用标记防止重复执行；bfcache 往返恢复时也不会再次追加，
 * 避免同一条留言被重复显示、旧项残留。
 */
function initScrollContent() {
    const scrollContent = document.getElementById('scrollContent');
    if (!scrollContent || scrollContent.dataset.cloned === '1') return;
    scrollContent.dataset.cloned = '1';
    scrollContent.innerHTML += scrollContent.innerHTML;
}

document.addEventListener('DOMContentLoaded', initScrollContent);
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        const scrollContent = document.getElementById('scrollContent');
        if (scrollContent && scrollContent.dataset.cloned !== '1') {
            initScrollContent();
        }
    }
});

/**
 * 切换收藏状态
 */
function toggleFavorite(event, btn) {
    event.preventDefault();
    event.stopPropagation();

    const messageId = btn.dataset.messageId;
    if (!messageId) return;

    const icon = btn.querySelector('.favorite-icon');
    const text = btn.querySelector('.favorite-text');
    const originalIcon = icon.textContent;
    const originalText = text.textContent;
    const originalClass = btn.className;

    btn.disabled = true;

    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('action', 'toggle');

    fetch('api/favorite.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            if (result.data.favorited) {
                btn.classList.add('favorited');
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-warning');
                icon.textContent = '⭐';
                text.textContent = '已收藏';
                showToast(result.msg, 'success');
            } else {
                btn.classList.remove('favorited');
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-secondary');
                icon.textContent = '☆';
                text.textContent = '收藏';
                showToast(result.msg, 'info');

                if (window.location.pathname.includes('favorites.php')) {
                    const card = btn.closest('.message-card');
                    if (card) {
                        const messageType = btn.dataset.type || card.dataset.type || '';
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-100px)';
                        setTimeout(() => {
                            card.remove();
                            updateFavoritesStats(messageType);
                            checkEmptyState();
                        }, 300);
                    }
                }
            }
        } else {
            showToast(result.msg || '操作失败', 'error');
            icon.textContent = originalIcon;
            text.textContent = originalText;
            btn.className = originalClass;
        }
    })
    .catch(error => {
        console.error('收藏操作失败:', error);
        showToast('网络错误，请稍后重试', 'error');
        icon.textContent = originalIcon;
        text.textContent = originalText;
        btn.className = originalClass;
    })
    .finally(() => {
        btn.disabled = false;
    });
}

/**
 * 更新收藏页面统计数据
 * 仅递减"全部收藏"总数与被取消留言所属分类的数量，保证摘要与总数同一口径
 */
function updateFavoritesStats(messageType) {
    const decrement = function(selector) {
        const el = document.querySelector(selector);
        if (!el) return;
        const current = parseInt(el.textContent) || 0;
        el.textContent = Math.max(0, current - 1);
    };

    decrement('.favorites-stats [data-count="total"]');
    if (messageType) {
        decrement('.favorites-stats [data-count="' + messageType + '"]');
    }

    const subtitle = document.querySelector('.page-subtitle');
    if (subtitle) {
        const match = subtitle.textContent.match(/\d+/);
        if (match) {
            const current = parseInt(match[0]) || 0;
            subtitle.textContent = `共收藏 ${Math.max(0, current - 1)} 条留言`;
        }
    }
}

/**
 * 检查收藏页面是否为空
 * 当前页删光时：若不在第1页则回退页码重新加载（服务端会再次纠正越界页）；
 * 在第1页时只替换列表区域，避免误删分页等兄弟节点造成错位
 */
function checkEmptyState() {
    const list = document.querySelector('.message-list');
    if (!list) return;

    const cards = list.querySelectorAll('.message-card');
    if (cards.length !== 0) return;

    const params = new URLSearchParams(window.location.search);
    const currentPage = parseInt(params.get('page')) || 1;
    if (currentPage > 1) {
        params.set('page', String(currentPage - 1));
        window.location.search = params.toString();
        return;
    }

    const section = document.querySelector('.message-list-section .container');
    if (!section) return;
    const pagination = section.querySelector('.pagination');
    if (pagination) pagination.remove();
    const summary = section.querySelector('.list-summary');
    if (summary) summary.remove();
    list.outerHTML = `
        <div class="empty-state">
            <div class="empty-icon">⭐</div>
            <p>暂无收藏的留言</p>
            <a href="index.php" class="btn btn-primary">去浏览留言</a>
        </div>
    `;
}

/**
 * 显示提示消息
 */
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-message');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = `toast-message toast-${type}`;
    toast.textContent = message;

    const styles = {
        position: 'fixed',
        top: '80px',
        left: '50%',
        transform: 'translateX(-50%)',
        padding: '12px 24px',
        borderRadius: '8px',
        color: '#fff',
        fontSize: '0.9rem',
        zIndex: '9999',
        boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
        animation: 'slideDown 0.3s ease',
        maxWidth: '90%',
        textAlign: 'center'
    };

    const typeColors = {
        success: 'background: #10b981',
        error: 'background: #ef4444',
        info: 'background: #3b82f6',
        warning: 'background: #f59e0b'
    };

    Object.assign(toast.style, styles);
    toast.style.cssText += ';' + (typeColors[type] || typeColors.info);

    const styleSheet = document.createElement('style');
    styleSheet.textContent = `
        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(styleSheet);

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 2000);
}

/**
 * 打开举报弹窗
 */
function openReportModal(messageId) {
    const modal = document.getElementById('reportModal');
    if (!modal) return;

    document.getElementById('reportMessageId').value = messageId;
    document.getElementById('reportForm').reset();
    document.getElementById('reportDescCount').textContent = '0';
    modal.style.display = 'flex';
}

/**
 * 关闭举报弹窗
 */
function closeReportModal() {
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * 初始化举报表单
 */
function initReportForm() {
    const form = document.getElementById('reportForm');
    if (!form) return;

    const descInput = document.getElementById('reportDescription');
    const descCount = document.getElementById('reportDescCount');

    if (descInput && descCount) {
        descInput.addEventListener('input', function() {
            descCount.textContent = this.value.length;
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitReport();
    });

    document.getElementById('reportModal').addEventListener('click', function(e) {
        if (e.target === this) closeReportModal();
    });
}

/**
 * 提交举报
 */
function submitReport() {
    const form = document.getElementById('reportForm');
    if (!form) return;

    const submitBtn = document.getElementById('reportSubmitBtn');
    const messageId = document.getElementById('reportMessageId').value;
    const reportType = form.querySelector('input[name="report_type"]:checked');
    const description = document.getElementById('reportDescription').value;

    if (!reportType) {
        showToast('请选择举报类型', 'warning');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '提交中...';

    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('report_type', reportType.value);
    formData.append('description', description);
    formData.append('action', 'submit');

    fetch('api/report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 0) {
            showToast(result.msg, 'success');
            closeReportModal();

            const reportBtn = document.querySelector('.report-btn[data-message-id="' + messageId + '"]');
            if (reportBtn) {
                reportBtn.disabled = true;
                reportBtn.classList.remove('btn-danger');
                reportBtn.classList.add('btn-secondary');
                const reportText = reportBtn.querySelector('.report-text');
                if (reportText) {
                    reportText.textContent = '已举报';
                }
            }
        } else {
            showToast(result.msg || '举报失败', 'error');
        }
    })
    .catch(error => {
        console.error('举报提交失败:', error);
        showToast('网络错误，请稍后重试', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = '提交举报';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initReportForm();
});
