<?php  
session_start();  
// 核心：用户返回/刷新主页时，立即清除卦数相关SESSION，强制重新生成新卦数
unset($_SESSION['current_hex_number']);
unset($_SESSION['last_hex_number']);
unset($_SESSION['hex_duplicate_tip']);
unset($_SESSION['report_data']); // 额外清除报告数据，确保状态重置

// 配置项（若有外部配置可保留，此处简化）
define('SITE_NAME', '易经智慧占卜');
?>  
<!DOCTYPE html>  
<html lang="zh-CN">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <meta name="description" content="易经智慧占卜，天地之道，阴阳平衡">
    <meta name="author" content="易经占卜系统">
    <title><?= SITE_NAME ?></title>  
    <!-- 引入外部字体（可选，增强字体表现） -->
    <link rel="stylesheet" href="https://cdn.staticfile.org/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========== 全局样式重置 ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Noto Serif SC", "思源宋体", "Microsoft YaHei", SimHei, serif;
        }

        /* ========== 全局变量定义 ========== */
        :root {
            --color-primary: #2c5e78; /* 主色：青灰（易经天青） */
            --color-secondary: #9e5a22; /* 辅助色：赭石（易经地赭） */
            --color-accent: #d32f2f; /* 强调色：朱红 */
            --color-bg: #f9f6f0; /* 背景色：米白 */
            --color-bg-panel: #ffffff; /* 面板背景 */
            --color-text: #333333; /* 主文本色 */
            --color-text-light: #666666; /* 浅文本色 */
            --border-radius: 12px; /* 统一圆角 */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease; /* 统一过渡效果 */
        }

        /* ========== 页面基础样式 ========== */
        body {
            background-color: var(--color-bg);
            color: var(--color-text);
            line-height: 1.8;
            padding: 20px 0;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* ========== 头部样式 ========== */
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px 0;
        }

        .header .title {
            font-size: clamp(2rem, 5vw, 3.5rem); /* 响应式字体 */
            color: var(--color-primary);
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .header .subtitle {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: var(--color-secondary);
            font-style: italic;
            letter-spacing: 1px;
        }

        /* ========== 主要内容样式 ========== */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* 占卜须知面板 */
        .instructions {
            background-color: var(--color-bg-panel);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(44, 94, 120, 0.1);
        }

        .instructions h2 {
            color: var(--color-primary);
            margin-bottom: 20px;
            font-size: clamp(1.25rem, 3vw, 1.5rem);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--color-primary);
            display: inline-block;
        }

        .instruction-text p {
            margin-bottom: 15px;
            font-size: clamp(1rem, 2vw, 1.1rem);
        }

        .reminder {
            margin-top: 20px;
            padding: 20px;
            background-color: rgba(211, 47, 47, 0.05);
            border-radius: 8px;
            border-left: 4px solid var(--color-accent);
        }

        .reminder p:first-child {
            color: var(--color-accent);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .reminder .warning-text {
            font-size: clamp(1.25rem, 4vw, 1.8rem);
            color: var(--color-accent);
            font-weight: 700;
            line-height: 1.5;
            text-align: center;
            margin: 10px 0;
        }

        /* 按钮区域 */
        .action-section {
            text-align: center;
            margin: 10px 0 30px;
        }

        .divine-button {
            padding: 18px 60px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            background: linear-gradient(135deg, var(--color-primary), #1a455d);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-md);
            letter-spacing: 1px;
        }

        .divine-button:hover {
            background: linear-gradient(135deg, #1a455d, var(--color-primary));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .divine-button:disabled {
            background: #999999;
            cursor: not-allowed;
            transform: none;
            box-shadow: var(--shadow-sm);
        }

        .button-icon {
            font-size: 1.5rem;
        }

        /* 页脚说明 */
        .footer-note {
            text-align: center;
            color: var(--color-text-light);
            font-size: clamp(0.9rem, 2vw, 1rem);
            margin-top: 20px;
        }

        /* ========== 加载弹窗样式 ========== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(4px); /* 毛玻璃效果 */
        }

        .modal.hidden {
            display: none;
        }

        .modal-content {
            background-color: var(--color-bg-panel);
            padding: 40px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 90%;
            width: 400px;
        }

        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--color-primary);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .modal-content p {
            font-size: clamp(1rem, 2vw, 1.1rem);
            color: var(--color-primary);
            letter-spacing: 1px;
        }

        /* ========== 响应式适配（移动端） ========== */
        @media (max-width: 768px) {
            .instructions {
                padding: 20px;
            }

            .divine-button {
                padding: 15px 40px;
                width: 100%;
                max-width: 300px;
            }

            .modal-content {
                padding: 30px 20px;
            }

            .spinner {
                width: 50px;
                height: 50px;
            }

            .header {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px 0;
            }

            .reminder .warning-text {
                font-size: 1.25rem;
            }

            .divine-button {
                max-width: 100%;
            }
        }
    </style>
</head>  
<body>  
    <div class="container">  
        <header class="header">  
            <h1 class="title">易经智慧占卜</h1>  
            <div class="subtitle">天地之道，阴阳平衡</div>  
        </header>  

        <main class="main-content">  
            <div class="instructions">  
                <h2>占卜须知</h2>  
                <div class="instruction-text">  
                    <p><strong>请聚拢心思，想着要测算的事情</strong></p>  
                    <p>感觉无其他杂念后，点击"算1卦"按钮</p>  
                    <p><strong>静请期待测算结果</strong></p>  
                    
                    <div class="reminder">  
                        <p><strong>友情提醒：</strong></p>  
                        <p class="warning-text">万法皆空，因果不空；吉凶在己，不要痴迷</p>  
                    </div>  
                </div>  
            </div>  

            <div class="action-section">  
                <form id="divinationForm" method="POST" action="result.php">  
                    <button type="submit" id="divineBtn" class="divine-button" name="calculate_gua">  
                        <span class="button-icon">☰</span>  
                        <span class="button-text">算1卦</span>  
                    </button>  
                </form>  
            </div>  

            <div class="footer-note">  
                <p>易经占卜乃传统文化，供参考娱乐之用</p>  
            </div>  
        </main>  
    </div>  

    <!-- 加载动画 -->  
    <div id="loadingModal" class="modal hidden">  
        <div class="modal-content">  
            <div class="spinner"></div>  
            <p>天人感应，卦象生成中...</p>  
        </div>  
    </div>  

    <script>  
        // 页面加载完成后绑定事件  
        document.addEventListener('DOMContentLoaded', function() {  
            const form = document.getElementById('divinationForm');  
            const btn = document.getElementById('divineBtn');  
            const loadingModal = document.getElementById('loadingModal');  
              
            form.addEventListener('submit', function(e) {  
                // 阻止默认提交，显示加载动画  
                e.preventDefault();  
                
                // 禁用按钮并修改文案  
                btn.disabled = true;  
                btn.innerHTML = '<span class="button-icon">☰</span><span class="button-text">占卜中...</span>';  
                
                // 显示加载弹窗，禁止页面滚动  
                loadingModal.classList.remove('hidden');  
                document.body.style.overflow = 'hidden';  
                  
                // 模拟占卜时间（1-3秒），增加随机性  
                const randomDelay = Math.random() * 2000 + 1000;  
                setTimeout(function() {  
                    form.submit(); // 提交表单  
                }, randomDelay);  
            });  
        });  
    </script>  
</body>  
</html>
