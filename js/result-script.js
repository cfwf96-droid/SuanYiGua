// 结果页面专用脚本  
document.addEventListener('DOMContentLoaded', function() {  
    console.log('易经结果页面加载完成');  
      
    // 获取当前卦号  
    const currentGua = <?= $gua_number ?>;  
    console.log('当前卦号：', currentGua);  
      
    // 按钮事件绑定  
    bindEventListeners();  
      
    // 自动显示第一个面板  
    showContentPanel('gua_image');  
      
    // 添加页面交互效果  
    addInteractiveEffects();  
      
    // 键盘导航  
    setupKeyboardNavigation();  
});  
  
function bindEventListeners() {  
    // 内容面板切换  
    const buttons = document.querySelectorAll('.action-btn[data-type]');  
    const panels = document.querySelectorAll('.content-panel');  
      
    buttons.forEach(button => {  
        button.addEventListener('click', function(e) {  
            e.preventDefault();  
            const type = this.dataset.type;  
            showContentPanel(type);  
        });  
    });  
      
    // 重算按钮  
    const reDivineBtn = document.getElementById('reDivineBtn');  
    if (reDivineBtn) {  
        reDivineBtn.addEventListener('click', function(e) {  
            e.preventDefault();  
            if (confirm('一日一卦，不要沉迷。\n\n确认要重新占卜吗？（这将清除当前卦象）')) {  
                // 清除session数据  
                fetch('clear_session.php', {  
                    method: 'POST',  
                    headers: { 'Content-Type': 'application/json' }  
                }).then(() => {  
                    showMessage('准备重新占卜', '正在返回首页...', 'info');  
                    setTimeout(() => {  
                        window.location.href = 'index.php';  
                    }, 1500);  
                });  
            }  
        });  
    }  
      
    // 生成报告按钮  
    const reportBtn = document.getElementById('reportBtn');  
    if (reportBtn) {  
        reportBtn.addEventListener('click', function(e) {  
            e.preventDefault();  
            if (confirm('生成详细报告需要发送密码到指定邮箱，\n确认要生成完整的易经智慧报告吗？')) {  
                showLoading('正在生成报告...');  
                window.location.href = 'report.php';  
            }  
        });  
    }  
}  
  
function showContentPanel(type) {  
    // 移除所有激活状态  
    document.querySelectorAll('.action-btn').forEach(btn => {  
        btn.classList.remove('active');  
    });  
      
    document.querySelectorAll('.content-panel').forEach(panel => {  
        panel.classList.remove('active');  
    });  
      
    // 激活对应按钮和面板  
    const targetButton = document.querySelector(`[data-type="${type}"]`);  
    const targetPanel = document.getElementById(`${type}Content`);  
      
    if (targetButton) targetButton.classList.add('active');  
    if (targetPanel) targetPanel.classList.add('active');  
      
    // 平滑滚动到面板  
    if (targetPanel) {  
        targetPanel.scrollIntoView({   
            behavior: 'smooth',   
            block: 'start'   
        });  
    }  
      
    // 更新URL hash  
    if (history.pushState) {  
        history.pushState(null, null, `#${type}`);  
    }  
      
    console.log(`显示面板：${type}`);  
}  
  
function addInteractiveEffects() {  
    // 卦象符号悬停效果  
    const hexagram = document.querySelector('.large-hexagram');  
    if (hexagram) {  
        hexagram.addEventListener('mouseenter', function() {  
            this.style.transform = 'scale(1.1) rotate(5deg)';  
        });  
          
        hexagram.addEventListener('mouseleave', function() {  
            this.style.transform = 'scale(1) rotate(0deg)';  
        });  
    }  
      
    // 爻辞项交互  
    const yaoItems = document.querySelectorAll('.yao-item');  
    yaoItems.forEach(item => {  
        item.addEventListener('click', function() {  
            this.style.transform = 'scale(1.02)';  
            setTimeout(() => {  
                this.style.transform = 'scale(1)';  
            }, 200);  
        });  
    });  
      
    // 运势部分渐变效果  
    const interpretationSections = document.querySelectorAll('.interpretation-section');  
    interpretationSections.forEach((section, index) => {  
        section.style.opacity = '0';  
        section.style.transform = 'translateY(20px)';  
        setTimeout(() => {  
            section.style.transition = 'all 0.6s ease';  
            section.style.opacity = '1';  
            section.style.transform = 'translateY(0)';  
        }, index * 200);  
    });  
}  
  
function setupKeyboardNavigation() {  
    document.addEventListener('keydown', function(e) {  
        const buttons = document.querySelectorAll('.action-btn[data-type]');  
        const activeButton = document.querySelector('.action-btn.active');  
        const currentIndex = Array.from(buttons).indexOf(activeButton);  
          
        switch(e.key) {  
            case '1':  
            case 'ArrowLeft':  
                e.preventDefault();  
                navigateToPanel(0); // 卦象  
                break;  
            case '2':  
            case 'ArrowUp':  
                e.preventDefault();  
                navigateToPanel(1); // 爻辞  
                break;  
            case '3':  
            case 'ArrowRight':  
                e.preventDefault();  
                navigateToPanel(2); // 运势  
                break;  
            case 'r':  
            case 'R':  
                e.preventDefault();  
                document.getElementById('reDivineBtn').click();  
                break;  
            case 'p':  
            case 'P':  
                e.preventDefault();  
                document.getElementById('reportBtn').click();  
                break;  
            case 'Escape':  
                e.preventDefault();  
                showContentPanel('gua_image'); // 返回默认面板  
                break;  
        }  
    });  
      
    function navigateToPanel(index) {  
        if (index >= 0 && index < 3) {  
            const buttons = document.querySelectorAll('.action-btn[data-type]');  
            const button = buttons[index];  
            if (button) {  
                button.click();  
            }  
        }  
    }  
}  
  
function showLoading(message = '加载中...') {  
    const loadingDiv = document.createElement('div');  
    loadingDiv.id = 'temp-loading';  
    loadingDiv.className = 'modal';  
    loadingDiv.innerHTML = `  
        <div class="modal-content">  
            <div class="spinner"></div>  
            <p>${message}</p>  
        </div>  
    `;  
    document.body.appendChild(loadingDiv);  
      
    setTimeout(() => {  
        const modal = document.getElementById('temp-loading');  
        if (modal) {  
            modal.remove();  
        }  
    }, 2000);  
}  
  
function showMessage(title, message, type = 'info') {  
    // 创建消息模态框  
    const messageDiv = document.createElement('div');  
    messageDiv.className = `message-modal ${type}`;  
    messageDiv.innerHTML = `  
        <div class="message-content">  
            <div class="message-header">  
                <h3>${title}</h3>  
                <button class="close-message">&times;</button>  
            </div>  
            <div class="message-body">  
                <p>${message}</p>  
            </div>  
            <div class="message-actions">  
                <button class="confirm-btn">确定</button>  
                ${type === 'success' ? '<button class="action-btn-secondary">好的</button>' : ''}  
            </div>  
        </div>  
    `;  
      
    document.body.appendChild(messageDiv);  
      
    // 绑定关闭事件  
    const closeBtn = messageDiv.querySelector('.close-message');  
    const confirmBtn = messageDiv.querySelector('.confirm-btn');  
      
    function closeMessage() {  
        messageDiv.style.opacity = '0';  
        messageDiv.style.transform = 'scale(0.9)';  
        setTimeout(() => {  
            document.body.removeChild(messageDiv);  
        }, 300);  
    }  
      
    if (closeBtn) closeBtn.addEventListener('click', closeMessage);  
    if (confirmBtn) confirmBtn.addEventListener('click', closeMessage);  
      
    // 点击背景关闭  
    messageDiv.addEventListener('click', function(e) {  
        if (e.target === messageDiv) {  
            closeMessage();  
        }  
    });  
      
    // ESC键关闭  
    const escHandler = (e) => {  
        if (e.key === 'Escape') {  
            closeMessage();  
            document.removeEventListener('keydown', escHandler);  
        }  
    };  
    document.addEventListener('keydown', escHandler);  
}  
  
// 添加样式  
const style = document.createElement('style');  
style.textContent = `  
    .message-modal {  
        position: fixed;  
        top: 0;  
        left: 0;  
        width: 100%;  
        height: 100%;  
        background: rgba(0, 0, 0, 0.6);  
        display: flex;  
        justify-content: center;  
        align-items: center;  
        z-index: 10001;  
        backdrop-filter: blur(5px);  
        opacity: 1;  
        transition: all 0.3s ease;  
    }  
      
    .message-modal.info { background: rgba(23, 162, 184, 0.8); }  
    .message-modal.success { background: rgba(40, 167, 69, 0.8); }  
    .message-modal.error { background: rgba(220, 53, 69, 0.8); }  
    .message-modal.warning { background: rgba(255, 193, 7, 0.8); }  
      
    .message-content {  
        background: white;  
        border-radius: 15px;  
        max-width: 450px;  
        width: 90%;  
        max-height: 80vh;  
        overflow-y: auto;  
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);  
        transform: scale(1);  
        animation: messageSlideIn 0.3s ease-out;  
    }  
      
    @keyframes messageSlideIn {  
        from { opacity: 0; transform: scale(0.8) translateY(-20px); }  
        to { opacity: 1; transform: scale(1) translateY(0); }  
    }  
      
    .message-header {  
        display: flex;  
        justify-content: space-between;  
        align-items: center;  
        padding: 25px 30px 20px;  
        border-bottom: 1px solid #eee;  
        border-radius: 15px 15px 0 0;  
    }  
      
    .message-header h3 {  
        margin: 0;  
        color: #2c3e50;  
        font-size: 1.4em;  
    }  
      
    .close-message {  
        background: none;  
        border: none;  
        font-size: 28px;  
        cursor: pointer;  
        color: #999;  
        padding: 0;  
        width: 35px;  
        height: 35px;  
        display: flex;  
        align-items: center;  
        justify-content: center;  
        border-radius: 50%;  
        transition: all 0.2s ease;  
    }  
      
    .close-message:hover {  
        background: #f8f9fa;  
        color: #333;  
        transform: rotate(90deg);  
    }  
      
    .message-body {  
        padding: 25px 30px;  
        color: #555;  
        line-height: 1.7;  
        font-size: 1.1em;  
    }  
      
    .message-actions {  
        padding: 20px 30px 25px;  
        text-align: right;  
        border-top: 1px solid #eee;  
        display: flex;  
        gap: 15px;  
        justify-content: flex-end;  
        flex-wrap: wrap;  
    }  
      
    .confirm-btn, .action-btn-secondary {  
        background: #667eea;  
        color: white;  
        border: none;  
        padding: 12px 25px;  
        border-radius: 8px;  
        cursor: pointer;  
        font-size: 1em;  
        font-weight: 500;  
        transition: all 0.2s ease;  
        min-width: 100px;  
    }  
      
    .confirm-btn:hover, .action-btn-secondary:hover {  
        background: #5a67d8;  
        transform: translateY(-1px);  
    }  
      
    .action-btn-secondary {  
        background: #6c757d;  
    }  
      
    .action-btn-secondary:hover {  
        background: #5a6268;  
    }  
      
    .message-modal .spinner {  
        margin: 20px auto;  
    }  
`;  
document.head.appendChild(style);  
  
// 页面性能优化  
if ('serviceWorker' in navigator) {  
    window.addEventListener('load', function() {  
        navigator.serviceWorker.register('/sw.js').then(function(registration) {  
            console.log('SW registered: ', registration);  
        }).catch(function(registrationError) {  
            console.log('SW registration failed: ', registrationError);  
        });  
    });  
}  
  
// 错误处理  
window.onerror = function(msg, url, lineNo, columnNo, error) {  
    console.error('易经占卜系统错误:', {  
        message: msg,  
        url: url,  
        line: lineNo,  
        column: columnNo,  
        error: error  
    });  
    return false;  
};  
