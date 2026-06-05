<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>到期提醒系统 - Expiration Reminder</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⏰</text></svg>">
</head>
<body>
    <!-- 鼠标粒子画布 -->
    <canvas id="particleCanvas"></canvas>

    <!-- 背景网格 -->
    <div class="bg-grid"></div>

    <!-- ========== 登录/注册遮罩 ========== -->
    <div class="auth-overlay" id="authOverlay">
        <div class="auth-card" id="authCard">
            <!-- 设置密码界面 -->
            <div class="auth-panel" id="authSetup">
                <div class="auth-icon">🔐</div>
                <h2 class="auth-title">初始设置</h2>
                <p class="auth-desc">首次使用，请设置登录账号和密码</p>
                <div class="auth-form">
                    <div class="form-group">
                        <label class="form-label">账号</label>
                        <input class="form-input" id="setupUsername" placeholder="输入管理账号" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label class="form-label">密码</label>
                        <input class="form-input" type="password" id="setupPassword" placeholder="输入登录密码（至少4位）" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">确认密码</label>
                        <input class="form-input" type="password" id="setupPassword2" placeholder="再次输入密码" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary auth-btn" onclick="Auth.setup()">🔒 确认设置</button>
                </div>
            </div>

            <!-- 登录界面 -->
            <div class="auth-panel" id="authLogin" style="display:none;">
                <div class="auth-icon">⏰</div>
                <h2 class="auth-title">到期提醒系统</h2>
                <p class="auth-desc">请输入账号密码登录</p>
                <div class="auth-form">
                    <div class="form-group">
                        <label class="form-label">账号</label>
                        <input class="form-input" id="loginUsername" placeholder="输入账号" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label class="form-label">密码</label>
                        <input class="form-input" type="password" id="loginPassword" placeholder="输入密码" autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary auth-btn" onclick="Auth.login()">🔓 登录</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== 主应用（初始隐藏） ========== -->
    <div id="appContainer" style="display:none;">
        <!-- 侧边栏 -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="logo-icon">⏰</span>
                    <span class="logo-text">到期提醒</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-page="dashboard">
                    <span class="nav-icon">📊</span>
                    <span class="nav-label">仪表盘</span>
                </a>
                <a href="#" class="nav-item" data-page="server">
                    <span class="nav-icon">🖥️</span>
                    <span class="nav-label">云服务器</span>
                </a>
                <a href="#" class="nav-item" data-page="certificate">
                    <span class="nav-icon">🔒</span>
                    <span class="nav-label">SSL证书</span>
                </a>
                <a href="#" class="nav-item" data-page="icp">
                    <span class="nav-icon">📄</span>
                    <span class="nav-label">ICP备案</span>
                </a>
                <a href="#" class="nav-item" data-page="birthday">
                    <span class="nav-icon">🎂</span>
                    <span class="nav-label">生日提醒</span>
                </a>
                <a href="#" class="nav-item" data-page="other">
                    <span class="nav-icon">📋</span>
                    <span class="nav-label">其他提醒</span>
                </a>
                <div class="nav-divider"></div>
                <a href="#" class="nav-item" data-page="settings">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-label">系统设置</span>
                </a>
                <a href="#" class="nav-item" data-page="logs">
                    <span class="nav-icon">📝</span>
                    <span class="nav-label">发送日志</span>
                </a>
                <div class="nav-divider"></div>
                <a href="#" class="nav-item" onclick="Auth.logout()" style="color:var(--text-muted);">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-label">退出登录</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <span class="version">v1.0.0</span>
            </div>
        </aside>

        <!-- 移动端菜单按钮 -->
        <button class="menu-toggle" id="menuToggle">☰</button>

        <!-- 主内容区 -->
        <main class="main-content" id="mainContent">
            <div id="pageContainer"></div>
        </main>
    </div>

    <!-- 模态框 -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-content" id="modalContent">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">标题</h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- 动态内容 -->
            </div>
            <div class="modal-footer" id="modalFooter">
                <button class="btn btn-secondary" id="modalCancel">取消</button>
                <button class="btn btn-primary" id="modalConfirm">确认</button>
            </div>
        </div>
    </div>

    <!-- Toast 通知 -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- 加载动画 -->
    <div class="loader-overlay" id="loaderOverlay" style="display:none;">
        <div class="loader">
            <div class="loader-spinner"></div>
            <div class="loader-text">加载中...</div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
