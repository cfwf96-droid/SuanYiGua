// 全局变量  
let currentGua = null;  
let isDivining = false;  
  
// DOM元素  
const divineBtn = document.getElementById('divineBtn');  
const loadingModal = document.getElementById('loadingModal');  
const reDivineBtn = document.getElementById('reDivineBtn');  
const reportBtn = document.getElementById('reportBtn');  
  
// 页面初始化  
document.addEventListener('DOMContentLoaded', function() {  
    initializePage();  
      
    // 绑定事件监听器  
    if (divineBtn) {  
        divineBtn.addEventListener('click', handleDivination);  
    }  
      
    if (reDivineBtn) {  
        reDivineBtn.addEventListener('click', handleReDivination);  
    }  
      
    if (reportBtn) {  
        reportBtn.addEventListener('click', handleReportGeneration);  
    }  
      
    // 绑定卦象按钮事件  
    bindGuaButtons();  
      
    // 键盘事件  
    document.addEventListener('keydown', function(e) {  
        // 按Enter键触发占卜  
        if (e.key === 'Enter' && divineBtn && !isDivining) {  
            e.preventDefault();  
            handleDivination();  
        }  
    });  
});  
  
function initializePage() {  
    // 设置当前卦号（如果存在）  
    if (window.currentGua) {  
        currentGua = window.currentGua;  
    }  
      
    // 添加页面过渡效果  
    document.body.style.opacity = '0';  
    document.body.style.transition = 'opacity 0.5s ease-in';  
    setTimeout(() => {  
        document.body.style.opacity = '1';  
    }, 100);  
      
    console.log('易经占卜系统初始化完成');  
}  
  
function handleDivination() {  
    if (isDivining) return;  
      
    isDivining = true;  
    divineBtn.disabled = true;  
    divineBtn.innerHTML = '<span class="loading"></span>占卜中...';  
      
    // 显示加载模态框  
    showLoadingModal();  
      
    // 模拟占卜过程（1-3秒随机延迟）  
    const divinationTime = Math.random() * 2000 + 1000;  
      
    setTimeout(() => {  
        // 生成随机卦号 (1-64)  
        const randomGua = Math.floor(Math.random() * 64) + 1;  
        currentGua = randomGua;  
          
        // 存储到sessionStorage（临时存储）  
        sessionStorage.setItem('currentGua', randomGua);  
          
        // 隐藏加载模态框  
        hideLoadingModal();  
          
        // 跳转到结果页面  
        window.location.href = 'result.php';  
          
    }, divinationTime);  
}  
  
function handleReDivination() {  
    if (confirm('一日一卦，不要沉迷。\n\n确认要重新占卜吗？')) {  
        // 清除当前卦象数据  
        sessionStorage.removeItem('currentGua');  
        if (window.currentGua) {  
            window.currentGua = null;  
        }  
          
        // 显示确认消息  
        showMessage('重新占卜', '您可以返回首页重新开始占卜之旅', 'info');  
          
        // 2秒后跳转首页  
        setTimeout(() => {  
            window.location.href = 'index.php';  
        }, 2000);  
    }  
}  
  
function handleReportGeneration() {  
    if (!currentGua) {  
        showMessage('错误', '请先完成占卜', 'error');  
        return;  
    }  
      
    // 显示确认对话框  
    if (confirm('生成报告需要发送密码到指定邮箱，\n确认要生成详细报告吗？')) {  
        // 显示加载状态  
        reportBtn.disabled = true;  
        reportBtn.innerHTML = '<span class="loading"></span>生成中...';  
          
        // 跳转到报告页面  
        window.location.href = 'report.php';  
    }  
}  
  
function bindGuaButtons() {  
    const guaButtons = document.querySelectorAll('.action-btn[data-type]');  
      
    guaButtons.forEach(button => {  
        button.addEventListener('click', function() {  
            const type = this.dataset.type;  
            showGuaContent(type);  
        });  
    });  
}  
  
function showGuaContent(contentType) {  
    // 隐藏所有内容面板  
    const allPanels = document.querySelectorAll('.content-panel');  
    allPanels.forEach(panel => {  
        panel.classList.add('hidden');  
    });  
      
    // 移除所有按钮的激活状态  
    const allButtons = document.querySelectorAll('.action-btn');  
    allButtons.forEach(btn => {  
        btn.classList.remove('active');  
    });  
      
    // 显示指定内容  
    let targetPanel = null;  
      
    switch(contentType) {  
        case 'gua_image':  
            targetPanel = document.getElementById('guaImageContent');  
            this.classList.add('active');  
            break;  
              
        case 'yao_dict':  
            targetPanel = document.getElementById('yaoDictContent');  
            this.classList.add('active');  
            break;  
              
        case 'interpretation':  
            targetPanel = document.getElementById('interpretationContent');  
            this.classList.add('active');  
            break;  
    }  
      
    if (targetPanel) {  
        targetPanel.classList.remove('hidden');  
          
        // 添加滚动到内容的动画  
        targetPanel.scrollIntoView({   
            behavior: 'smooth',   
            block: 'start'   
        });  
          
        // 添加激活样式  
        this.classList.add('active');  
    }  
}  
  
function showLoadingModal() {  
    if (loadingModal) {  
        loadingModal.classList.remove('hidden');  
        document.body.style.overflow = 'hidden';  
    }  
}  
  
function hideLoadingModal() {  
    if (loadingModal) {  
        loadingModal.classList.add('hidden');  
        document.body.style.overflow = 'auto';  
    }  
}  
  
function showMessage(title, message, type = 'info') {  
    // 创建消息元素  
    const messageEl = document.createElement('div');  
    messageEl.className = `message-overlay ${type}`;  
    messageEl.innerHTML = `  
        <div class="message-content">  
            <div class="message-header">  
                <h3>${title}</h3>  
                <button class="close-message">&times;</button>  
            </div>  
            <div class="message-body">  
                <p>${message}</p>  
            </div>  
            <div class="message-footer">  
                <button class="confirm-btn">确定</button>  
            </div>  
        </div>  
    `;  
      
    // 添加到页面  
    document.body.appendChild(messageEl);  
      
    // 绑定关闭事件  
    const closeBtn = messageEl.querySelector('.close-message');  
    const confirmBtn = messageEl.querySelector('.confirm-btn');  
      
    function closeMessage() {  
        messageEl.classList.add('fade-out');  
        setTimeout(() => {  
            document.body.removeChild(messageEl);  
        }, 300);  
    }  
      
    closeBtn.addEventListener('click', closeMessage);  
    confirmBtn.addEventListener('click', closeMessage);  
      
    // 点击背景关闭  
    messageEl.addEventListener('click', function(e) {  
        if (e.target === messageEl) {  
            closeMessage();  
        }  
    });  
      
    // 键盘ESC关闭  
    document.addEventListener('keydown', function escHandler(e) {  
        if (e.key === 'Escape') {  
            closeMessage();  
            document.removeEventListener('keydown', escHandler);  
        }  
    });  
}  
  
// 添加激活按钮样式  
const style = document.createElement('style');  
style.textContent = `  
    .action-btn.active {  
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%) !important;  
        transform: scale(1.05);  
        box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4) !important;  
    }  
      
    .message-overlay {  
        position: fixed;  
        top: 0;  
        left: 0;  
        width: 100%;  
        height: 100%;  
        background: rgba(0, 0, 0, 0.5);  
        display: flex;  
        justify-content: center;  
        align-items: center;  
        z-index: 10000;  
        backdrop-filter: blur(3px);  
    }  
      
    .message-overlay.error { background: rgba(220, 53, 69, 0.8); }  
    .message-overlay.success { background: rgba(40, 167, 69, 0.8); }  
    .message-overlay.info { background: rgba(23, 162, 184, 0.8); }  
      
    .message-content {  
        background: white;  
        border-radius: 12px;  
        max-width: 400px;  
        width: 90%;  
        max-height: 80vh;  
        overflow-y: auto;  
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);  
        animation: modalSlideIn 0.3s ease-out;  
    }  
      
    .message-header {  
        display: flex;  
        justify-content: space-between;  
        align-items: center;  
        padding: 20px 25px 15px;  
        border-bottom: 1px solid #eee;  
    }  
      
    .message-header h3 {  
        margin: 0;  
        color: #2c3e50;  
        font-size: 1.3em;  
    }  
      
    .close-message {  
        background: none;  
        border: none;  
        font-size: 24px;  
        cursor: pointer;  
        color: #999;  
        padding: 0;  
        width: 30px;  
        height: 30px;  
        display: flex;  
        align-items: center;  
        justify-content: center;  
        border-radius: 50%;  
        transition: all 0.2s ease;  
    }  
      
    .close-message:hover {  
        background: #f8f9fa;  
        color: #333;  
    }  
      
    .message-body {  
        padding: 20px 25px;  
        color: #555;  
        line-height: 1.6;  
    }  
      
    .message-footer {  
        padding: 15px 25px 25px;  
        text-align: right;  
        border-top: 1px solid #eee;  
    }  
      
    .confirm-btn {  
        background: #667eea;  
        color: white;  
        border: none;  
        padding: 10px 20px;  
        border-radius: 6px;  
        cursor: pointer;  
        font-size: 1em;  
        transition: background 0.2s ease;  
    }  
      
    .confirm-btn:hover {  
        background: #5a67d8;  
    }  
      
    .message-overlay.fade-out {  
        opacity: 0;  
        transform: scale(0.9);  
    }  
`;  
document.head.appendChild(style);  
  
// 页面卸载清理  
window.addEventListener('beforeunload', function() {  
    // 清理临时数据  
    if (sessionStorage.getItem('currentGua')) {  
        sessionStorage.removeItem('currentGua');  
    }  
});  
  
// 错误处理  
window.addEventListener('error', function(e) {  
    console.error('占卜系统错误:', e.error);  
    showMessage('系统提示', '出现了一些小问题，请刷新页面重试', 'error');  
});  
  
// 网络状态监控  
window.addEventListener('online', function() {  
    console.log('网络连接恢复');  
});  
  
window.addEventListener('offline', function() {  
    showMessage('网络提示', '当前网络不稳定，占卜结果可能需要稍等片刻', 'info');  
});  

