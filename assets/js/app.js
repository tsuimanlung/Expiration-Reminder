/**
 * Expiration Reminder - 到期提醒系统
 * 前端 SPA 主逻辑
 */

// =====================================
// 粒子鼠标跟随效果
// =====================================
class ParticleEffect {
    constructor() {
        this.canvas = document.getElementById('particleCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.particles = [];
        this.mouse = { x: -1000, y: -1000 };
        this.init();
    }

    init() {
        this.resize();
        window.addEventListener('resize', () => this.resize());
        document.addEventListener('mousemove', (e) => {
            this.mouse.x = e.clientX;
            this.mouse.y = e.clientY;
        });
        document.addEventListener('touchmove', (e) => {
            const touch = e.touches[0];
            this.mouse.x = touch.clientX;
            this.mouse.y = touch.clientY;
        }, { passive: true });
        document.addEventListener('touchend', () => {
            this.mouse.x = -1000;
            this.mouse.y = -1000;
        });
        this.createParticles();
        this.animate();
    }

    resize() {
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }

    createParticles() {
        const count = Math.min(80, Math.floor(window.innerWidth * 0.05));
        this.particles = [];
        for (let i = 0; i < count; i++) {
            this.particles.push({
                x: Math.random() * this.canvas.width,
                y: Math.random() * this.canvas.height,
                vx: (Math.random() - 0.5) * 0.8,
                vy: (Math.random() - 0.5) * 0.8,
                size: Math.random() * 2.5 + 1,
                alpha: Math.random() * 0.5 + 0.2,
                hue: 190 + Math.random() * 40, // 蓝色系
            });
        }
    }

    animate() {
        const ctx = this.ctx;
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // 更新和绘制粒子
        for (const p of this.particles) {
            // 鼠标吸引
            const dx = this.mouse.x - p.x;
            const dy = this.mouse.y - p.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 200) {
                const force = (200 - dist) / 200 * 0.02;
                p.vx += dx * force;
                p.vy += dy * force;
            }

            // 阻尼
            p.vx *= 0.98;
            p.vy *= 0.98;

            // 边界回弹
            if (p.x < 0 || p.x > this.canvas.width) p.vx *= -0.5;
            if (p.y < 0 || p.y > this.canvas.height) p.vy *= -0.5;

            p.x += p.vx;
            p.y += p.vy;

            // 绘制粒子
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            ctx.fillStyle = `hsla(${p.hue}, 80%, 70%, ${p.alpha})`;
            ctx.fill();

            // 发光效果
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size * 3, 0, Math.PI * 2);
            ctx.fillStyle = `hsla(${p.hue}, 80%, 70%, ${p.alpha * 0.08})`;
            ctx.fill();
        }

        // 粒子连线（鼠标附近的粒子之间）
        for (let i = 0; i < this.particles.length; i++) {
            for (let j = i + 1; j < this.particles.length; j++) {
                const a = this.particles[i];
                const b = this.particles[j];
                const dx = a.x - b.x;
                const dy = a.y - b.y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < 120) {
                    const alpha = (1 - dist / 120) * 0.15;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.strokeStyle = `rgba(79, 195, 247, ${alpha})`;
                    ctx.lineWidth = 0.6;
                    ctx.stroke();
                }
            }
        }

        // 从鼠标发射的光线
        if (this.mouse.x > 0 && this.mouse.y > 0) {
            const grad = ctx.createRadialGradient(
                this.mouse.x, this.mouse.y, 0,
                this.mouse.x, this.mouse.y, 150
            );
            grad.addColorStop(0, 'rgba(79, 195, 247, 0.06)');
            grad.addColorStop(0.5, 'rgba(79, 195, 247, 0.02)');
            grad.addColorStop(1, 'rgba(79, 195, 247, 0)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        }

        requestAnimationFrame(() => this.animate());
    }
}

// =====================================
// 工具函数
// =====================================
const Utils = {
    api: async (action, data = null, method = null) => {
        let url = `api.php?action=${action}`;
        // 自动判断：传了 data 且未指定 method 时默认 POST，否则 GET
        if (method === null) {
            method = data ? 'POST' : 'GET';
        }
        const options = { method };
        if (data && method === 'GET') {
            const params = new URLSearchParams(data);
            url += '&' + params.toString();
        } else if (data) {
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(data);
        }
        const res = await fetch(url, options);
        const json = await res.json();
        if (!json.success) {
            // 401 未登录 - 显示登录页
            if (res.status === 401) {
                Auth.showLogin();
                throw new Error('请先登录');
            }
            throw new Error(json.message || '请求失败');
        }
        return json.data || json;
    },

    daysLeft: (dateStr) => {
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const target = new Date(dateStr);
        target.setHours(0, 0, 0, 0);
        const diff = Math.floor((target - now) / (1000 * 60 * 60 * 24));
        return diff;
    },

    formatDate: (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    },

    formatDateCN: (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日`;
    },

    daysClass: (days) => {
        if (days < 0) return 'days-danger';
        if (days <= 7) return 'days-danger';
        if (days <= 30) return 'days-warning';
        return 'days-safe';
    },

    daysText: (days) => {
        if (days < 0) return `已过期 ${Math.abs(days)} 天`;
        if (days === 0) return '今天到期';
        return `${days} 天`;
    },

    daysIcon: (days) => {
        if (days < 0) return '🔴';
        if (days <= 7) return '⚠️';
        if (days <= 30) return '⚡';
        return '✅';
    },

    typeIcon: (type) => {
        const icons = { server: '🖥️', certificate: '🔒', icp: '📄', birthday: '🎂', other: '📋' };
        return icons[type] || '📌';
    },

    typeLabel: (type) => {
        const labels = { server: '服务器', certificate: 'SSL证书', icp: 'ICP备案', birthday: '生日', other: '其他' };
        return labels[type] || type;
    },

    badgeClass: (type) => {
        return `badge-${type}`;
    },

    escapeHtml: (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    showLoader: (show = true) => {
        document.getElementById('loaderOverlay').style.display = show ? 'flex' : 'none';
    },

    toast: (message, type = 'info', duration = 4000) => {
        const container = document.getElementById('toastContainer');
        const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<span>${icons[type] || ''}</span><span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// =====================================
// 认证管理
// =====================================
const Auth = {
    async check() {
        try {
            return await Utils.api('check_auth');
        } catch(e) {
            return { authenticated: false, password_set: false };
        }
    },

    showLogin() {
        document.getElementById('authOverlay').style.display = 'flex';
        document.getElementById('appContainer').style.display = 'none';
        document.getElementById('authSetup').style.display = 'none';
        document.getElementById('authLogin').style.display = 'block';
        const pw = document.getElementById('loginPassword');
        if (pw) pw.value = '';
    },

    showSetup() {
        document.getElementById('authOverlay').style.display = 'flex';
        document.getElementById('appContainer').style.display = 'none';
        document.getElementById('authLogin').style.display = 'none';
        document.getElementById('authSetup').style.display = 'block';
    },

    hide() {
        document.getElementById('authOverlay').style.display = 'none';
        document.getElementById('appContainer').style.display = 'flex';
    },

    async login() {
        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value;
        if (!username) { Utils.toast('请输入账号', 'error'); return; }
        if (!password) { Utils.toast('请输入密码', 'error'); return; }

        try {
            Utils.showLoader(true);
            await Utils.api('login', { username, password });
            Utils.toast('登录成功', 'success');
            Auth.hide();
            await App.initApp();
        } catch (e) {
            Utils.toast(e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    async setup() {
        const username = document.getElementById('setupUsername').value.trim();
        const password = document.getElementById('setupPassword').value;
        const password2 = document.getElementById('setupPassword2').value;

        if (!username) { Utils.toast('请输入账号', 'error'); return; }
        if (!password) { Utils.toast('请输入密码', 'error'); return; }
        if (password.length < 4) { Utils.toast('密码至少4个字符', 'error'); return; }
        if (password !== password2) { Utils.toast('两次密码不一致', 'error'); return; }

        try {
            Utils.showLoader(true);
            await Utils.api('setup_password', { username, password });
            Utils.toast('设置成功', 'success');
            document.getElementById('authOverlay').style.display = 'none';
            document.getElementById('appContainer').style.display = 'flex';
            await App.initApp();
        } catch (e) {
            Utils.toast(e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    async logout() {
        if (!confirm('确定要退出登录吗？')) return;
        try {
            await Utils.api('logout', {});
            document.getElementById('authOverlay').style.display = 'flex';
            document.getElementById('authLogin').style.display = 'block';
            document.getElementById('authSetup').style.display = 'none';
            document.getElementById('appContainer').style.display = 'none';
        } catch(e) {
            Utils.toast(e.message, 'error');
        }
    }
};

// 暴露到全局对象，供 onclick 调用
window.Auth = Auth;

// =====================================
// 模态框管理
// =====================================
const Modal = {
    open(title, bodyHtml, footerHtml = null) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBody').innerHTML = bodyHtml;
        const footer = document.getElementById('modalFooter');
        if (footerHtml) {
            footer.innerHTML = footerHtml;
            footer.style.display = 'flex';
        } else {
            footer.style.display = 'none';
        }
        document.getElementById('modalOverlay').classList.add('active');
    },

    close() {
        document.getElementById('modalOverlay').classList.remove('active');
    },

    setConfirmHandler(handler) {
        const btn = document.getElementById('modalConfirm');
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        newBtn.addEventListener('click', handler);
        return newBtn;
    },

    setCancelHandler(handler) {
        const btn = document.getElementById('modalCancel');
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        newBtn.addEventListener('click', handler);
        return newBtn;
    }
};

// 默认关闭
document.getElementById('modalClose').addEventListener('click', () => Modal.close());
document.getElementById('modalOverlay').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) Modal.close();
});

// =====================================
// 主应用
// =====================================
const App = {
    currentPage: 'dashboard',

    async init() {
        // 粒子背景始终显示
        new ParticleEffect();

        // 检查认证状态
        try {
            const auth = await Auth.check();

            if (!auth.password_set) {
                Auth.showSetup();
                // 绑定回车键
                document.getElementById('setupPassword2').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') Auth.setup();
                });
                document.getElementById('setupPassword').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') document.getElementById('setupPassword2').focus();
                });
                return;
            }

            if (!auth.authenticated) {
                Auth.showLogin();
                document.getElementById('loginPassword').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') Auth.login();
                });
                document.getElementById('loginUsername').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') document.getElementById('loginPassword').focus();
                });
                return;
            }

            // 已登录：初始化主应用
            document.getElementById('authOverlay').style.display = 'none';
            document.getElementById('appContainer').style.display = 'flex';
            await this.initApp();

        } catch (e) {
            Auth.showLogin();
        }
    },

    async initApp() {
        // 绑定导航
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                this.navigate(page);
            });
        });

        // 菜单切换（移动端）
        document.getElementById('menuToggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
        });

        // 点击主内容关闭侧边栏
        document.getElementById('mainContent').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('open');
        });

        // 加载默认页面
        await this.navigate('dashboard');
    },

    async navigate(page) {
        this.currentPage = page;
        // 更新导航高亮
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.page === page);
        });

        // 关闭移动端侧边栏
        document.getElementById('sidebar').classList.remove('open');

        // 渲染页面
        const container = document.getElementById('pageContainer');
        Utils.showLoader(true);
        try {
            switch (page) {
                case 'dashboard': await this.renderDashboard(container); break;
                case 'server': await this.renderItemList(container, 'server'); break;
                case 'certificate': await this.renderItemList(container, 'certificate'); break;
                case 'icp': await this.renderItemList(container, 'icp'); break;
                case 'birthday': await this.renderItemList(container, 'birthday'); break;
                case 'other': await this.renderItemList(container, 'other'); break;
                case 'settings': await this.renderSettings(container); break;
                case 'logs': await this.renderLogs(container); break;
            }
        } catch (e) {
            container.innerHTML = `<div class="empty-state"><p style="color:var(--danger)">加载失败: ${Utils.escapeHtml(e.message)}</p></div>`;
            Utils.toast(e.message, 'error');
        }
        Utils.showLoader(false);
    },

    // ==============================
    // 仪表盘
    // ==============================
    async renderDashboard(container) {
        const data = await Utils.api('get_dashboard');
        const { stats, upcoming, expired_count, total_count, recent_logs } = data;

        const statsHtml = Object.entries({ 总计: total_count, ...stats, '已过期': expired_count }).map(([k, v]) => `
            <div class="stat-card">
                <div class="stat-value">${v}</div>
                <div class="stat-label">${Utils.escapeHtml(k)}</div>
            </div>
        `).join('');

        const upcomingHtml = upcoming.length > 0 ? upcoming.map(item => {
            const days = Utils.daysLeft(item.expiry_date);
            const cls = days < 0 ? 'expired' : days <= 7 ? 'urgent' : days <= 30 ? 'warning' : 'safe';
            return `
                <div class="upcoming-card ${cls}" onclick="App.navigate('${item.type}')">
                    <div class="upcoming-icon">${Utils.typeIcon(item.type)}</div>
                    <div class="upcoming-info">
                        <div class="upcoming-name">${Utils.escapeHtml(item.name)}</div>
                        <div class="upcoming-meta">
                            <span class="badge ${Utils.badgeClass(item.type)}">${item.type_label}</span>
                            <span class="upcoming-date">${Utils.formatDate(item.expiry_date)}</span>
                        </div>
                    </div>
                    <div class="upcoming-days ${Utils.daysClass(days)}">
                        <span class="upcoming-days-num">${days < 0 ? Math.abs(days) : days}</span>
                        <span class="upcoming-days-label">${days < 0 ? '天前过期' : days === 0 ? '今天到期' : '天后'}</span>
                    </div>
                </div>
            `;
        }).join('') : '<div class="empty-state" style="padding:20px 0;grid-column:1/-1;"><div class="empty-state-icon" style="font-size:32px">🎉</div><div class="empty-state-title" style="font-size:14px">没有即将到期或已过期的项目</div></div>';

        const logsHtml = recent_logs.length > 0 ? recent_logs.map(log => `
            <div class="log-item ${log.status}">
                <div class="log-icon">${log.status === 'success' ? '✅' : '❌'}</div>
                <div class="log-info">
                    <div class="log-title">${Utils.escapeHtml(log.item_name || '已删除')}</div>
                    <div class="log-meta">提前 ${log.reminder_day} 天 · ${log.status === 'success' ? '成功' : Utils.escapeHtml(log.message)}</div>
                </div>
                <div class="log-time">${log.created_at ? log.created_at.substring(5,16) : ''}</div>
            </div>
        `).join('') : '<div class="empty-state" style="padding:15px 0"><p style="font-size:13px;color:var(--text-muted)">暂无发送记录</p></div>';

        const expiredCount = upcoming.filter(i => Utils.daysLeft(i.expiry_date) < 0).length;

        container.innerHTML = `
            <div class="page-header">
                <div>
                    <h1 class="page-title">📊 仪表盘</h1>
                    <p class="page-subtitle">所有到期提醒一目了然</p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="App.openAddModal()">➕ 新增提醒</button>
                </div>
            </div>

            <div class="dash-row">
                <div class="dash-col dash-col-main">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">⏰ 到期 & 已过期</h3>
                            <div style="display:flex;gap:6px;">
                                ${expiredCount > 0 ? `<span class="badge badge-server" style="font-size:11px;background:rgba(244,67,54,0.2);color:#f44336;">🔴 ${expiredCount} 已过期</span>` : ''}
                                ${upcoming.length > 0 ? `<span class="badge badge-other" style="font-size:11px;">${upcoming.length} 项</span>` : ''}
                            </div>
                        </div>
                        <div class="card-body upcoming-grid">${upcomingHtml}</div>
                    </div>
                </div>
                <div class="dash-col dash-col-side">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">📬 最近发送</h3>
                        </div>
                        <div class="card-body">${logsHtml}</div>
                    </div>
                </div>
            </div>

            <div class="stats-grid" style="margin-top:20px;">${statsHtml}</div>
        `;
    },

    // ==============================
    // 列表页面
    // ==============================
    async renderItemList(container, type) {
        const pageTitle = Utils.typeLabel(type);
        const pageIcon = Utils.typeIcon(type);
        const result = await Utils.api('get_items', { type }, 'GET');
        const items = result.items || [];

        let listHtml;
        if (items.length === 0) {
            listHtml = `
                <div class="empty-state">
                    <div class="empty-state-icon">${pageIcon}</div>
                    <div class="empty-state-title">暂无${pageTitle}提醒</div>
                    <div class="empty-state-desc">点击下方按钮添加第一个${pageTitle}提醒</div>
                    <button class="btn btn-primary" onclick="App.openAddModal('${type}')">➕ 添加${pageTitle}</button>
                </div>
            `;
        } else {
            listHtml = `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>名称</th>
                                ${type === 'server' ? '<th>服务列表</th>' : ''}
                                ${type === 'certificate' || type === 'icp' ? '<th>域名</th>' : ''}
                                <th>到期时间</th>
                                <th>剩余</th>
                                <th>提醒设置</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map(item => this.renderItemRow(item, type)).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        container.innerHTML = `
            <div class="page-header">
                <div>
                    <h1 class="page-title">${pageIcon} ${pageTitle}</h1>
                    <p class="page-subtitle">管理所有${pageTitle}到期提醒</p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="App.openAddModal('${type}')">➕ 新增</button>
                </div>
            </div>
            ${listHtml}
        `;
    },

    renderItemRow(item, type) {
        const days = Utils.daysLeft(item.expiry_date);
        const details = item.details || {};

        let extraCol = '';
        if (type === 'server') {
            const services = details.services || [];
            extraCol = `<td>
                ${services.length > 0 ? services.map(s => {
                    const sName = typeof s === 'string' ? s : (s.name || '');
                    const sUrl = typeof s === 'string' ? '' : (s.url || '');
                    const display = sUrl ? `${Utils.escapeHtml(sName)} <span style="color:var(--text-muted);font-size:11px;">(${Utils.escapeHtml(sUrl)})</span>` : Utils.escapeHtml(sName);
                    return `<span class="service-tag">${display}</span>`;
                }).join(' ') : '<span style="color:var(--text-muted)">-</span>'}
            </td>`;
        }
        if (type === 'certificate' || type === 'icp') {
            extraCol = `<td>${details.domain ? Utils.escapeHtml(details.domain) : '-'}</td>`;
        }

        const reminderDays = item.reminder_days || [];
        const reminderText = reminderDays.map(d => `提前${d}天`).join('<br>');

        return `
            <tr>
                <td>
                    <strong>${Utils.escapeHtml(item.name)}</strong>
                    ${item.type === 'birthday' && details.is_lunar ? '<span class="badge badge-birthday" style="font-size:10px;padding:1px 6px;margin-left:4px;">农历</span>' : ''}
                    ${item.notes ? `<br><span style="font-size:12px;color:var(--text-muted)">${Utils.escapeHtml(item.notes.substring(0, 30))}</span>` : ''}
                </td>
                ${extraCol}
                <td>${Utils.formatDate(item.expiry_date)}</td>
                <td>
                    <span class="days-count ${Utils.daysClass(days)}">
                        ${Utils.daysIcon(days)} ${Utils.daysText(days)}
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text-muted)">${reminderText}</td>
                <td>
                    <div class="action-btns">
                        <button class="btn-icon" onclick="App.openEditModal(${item.id})" title="编辑">✏️</button>
                        <button class="btn-icon" onclick="App.testReminder(${item.id})" title="发送测试提醒">📧</button>
                        <button class="btn-icon" onclick="App.deleteItem(${item.id})" title="删除" style="color:var(--danger)">🗑️</button>
                    </div>
                </td>
            </tr>
        `;
    },

    // ==============================
    // 新增/编辑模态框
    // ==============================
    async openAddModal(type = 'server') {
        const reminderDefaults = [30, 15, 7, 1];
        try {
            const settings = await Utils.api('get_settings');
            if (settings.reminder_defaults) {
                try {
                    const parsed = JSON.parse(settings.reminder_defaults);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.showItemForm(null, type, parsed);
                        return;
                    }
                } catch(e) {}
            }
        } catch(e) {}
        this.showItemForm(null, type, reminderDefaults);
    },

    async openEditModal(id) {
        try {
            const data = await Utils.api('get_item', { id }, 'GET');
            this.showItemForm(data, data.type, data.reminder_days || [30, 15, 7, 1]);
        } catch(e) {
            Utils.toast(e.message, 'error');
        }
    },

    showItemForm(item, type, reminderDays) {
        const isEdit = item !== null;
        const title = isEdit ? `编辑${Utils.typeLabel(type)}` : `新增${Utils.typeLabel(type)}`;

        // 服务器特有字段
        let extraFields = '';
        if (type === 'server') {
            const services = item?.details?.services || [];
            const ip = item?.details?.ip || '';
            const provider = item?.details?.provider || '';
            extraFields = `
                <div class="form-group">
                    <label class="form-label">🌐 IP 地址</label>
                    <input class="form-input" id="formIp" value="${Utils.escapeHtml(ip)}" placeholder="例如：123.123.123.123">
                </div>
                <div class="form-group">
                    <label class="form-label">🏢 服务商</label>
                    <input class="form-input" id="formProvider" value="${Utils.escapeHtml(provider)}" placeholder="例如：阿里云、腾讯云、Vultr">
                </div>
                <div class="form-group">
                    <label class="form-label">📦 提供的服务
                        <span style="font-size:12px;color:var(--text-muted);font-weight:400">（应用名称 + 访问地址）</span>
                    </label>
                    <div class="services-input-row" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <input class="form-input" id="formServiceName" placeholder="服务名称（如：读书站点）" style="flex:1;min-width:120px;" onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('formServiceUrl').focus()}">
                        <input class="form-input" id="formServiceUrl" placeholder="访问地址（如：123.123.123.123:8080）" style="flex:1;min-width:160px;" onkeydown="if(event.key==='Enter'){event.preventDefault();App.addServiceTag()}">
                        <button class="btn btn-sm btn-secondary" onclick="App.addServiceTag()" style="flex-shrink:0;">➕ 添加</button>
                    </div>
                    <div class="services-container" id="servicesContainer">
                        ${services.map(s => {
                            const name = typeof s === 'string' ? s : (s.name || '');
                            const url = typeof s === 'string' ? '' : (s.url || '');
                            return `<span class="service-tag" data-name="${Utils.escapeHtml(name)}" data-url="${Utils.escapeHtml(url)}">
                                ${Utils.escapeHtml(name)}
                                ${url ? `<span style="color:var(--text-muted);font-size:11px;margin-left:2px;">(${Utils.escapeHtml(url)})</span>` : ''}
                                <span class="remove" onclick="this.parentElement.remove()">×</span>
                            </span>`;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        if (type === 'certificate' || type === 'icp') {
            const domain = item?.details?.domain || '';
            extraFields = `
                <div class="form-group">
                    <label class="form-label">🌐 域名</label>
                    <input class="form-input" id="formDomain" value="${Utils.escapeHtml(domain)}" placeholder="例如：example.com">
                </div>
            `;
        }

        if (type === 'birthday') {
            const isLunar = item?.details?.is_lunar || false;
            const lunarMm = item?.details?.lunar_mm || '';
            const lunarDd = item?.details?.lunar_dd || '';
            extraFields = `
                <div class="form-group">
                    <label class="form-label">📅 历法类型</label>
                    <div style="display:flex;gap:12px;margin-top:6px;">
                        <label class="form-switch">
                            <input type="checkbox" id="formIsLunar" onchange="App.toggleLunar()" ${isLunar ? 'checked' : ''}>
                            <span class="switch-track"></span>
                            <span style="font-size:13px;color:var(--text-secondary)">农历</span>
                        </label>
                    </div>
                </div>
                <div id="lunarDateFields" style="${isLunar ? '' : 'display:none;'}">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">农历月</label>
                            <select class="form-select" id="formLunarMm">
                                ${Array.from({length:12}, (_,i) => `<option value="${i+1}" ${lunarMm == i+1 ? 'selected' : ''}>${i+1}月</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">农历日</label>
                            <select class="form-select" id="formLunarDd">
                                ${Array.from({length:30}, (_,i) => `<option value="${i+1}" ${lunarDd == i+1 ? 'selected' : ''}>${i+1}日</option>`).join('')}
                            </select>
                        </div>
                    </div>
                </div>
                <div id="solarDateFields" style="${isLunar ? 'display:none;' : ''}">
                    <div class="form-group">
                        <label class="form-label">🎂 公历生日</label>
                        <input class="form-input" type="date" id="formExpiry" value="${item?.expiry_date || ''}">
                        <div class="form-hint">选出生日期即可，系统会每年自动提醒</div>
                    </div>
                </div>
            `;
        }

        // 提醒天数选择
        const allDays = [365, 180, 90, 60, 30, 15, 7, 3, 1];
        const reminderChips = allDays.map(d => `
            <span class="reminder-day-chip ${reminderDays.includes(d) ? 'active' : ''}" data-day="${d}" onclick="this.classList.toggle('active')">
                ${d < 30 ? d + '天' : d >= 365 ? '一年' : d >= 180 ? '半年' : d + '天'}
            </span>
        `).join('');

        Modal.open(title, `
            <input type="hidden" id="formId" value="${item?.id || 0}">
            <input type="hidden" id="formType" value="${type}">

            <div class="form-group">
                <label class="form-label">📌 名称</label>
                <input class="form-input" id="formName" value="${item ? Utils.escapeHtml(item.name) : ''}" placeholder="输入名称" autofocus>
            </div>

            ${extraFields}

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">📅 到期日期</label>
                    <input class="form-input" type="date" id="formExpiry" value="${item?.expiry_date || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">📧 邮件提醒</label>
                    <label class="form-switch" style="margin-top:8px">
                        <input type="checkbox" id="formNotifyEmail" ${(!item || item.notify_email == 1) ? 'checked' : ''}>
                        <span class="switch-track"></span>
                        <span style="font-size:13px;color:var(--text-secondary)">启用邮件通知</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">⏰ 提醒时间
                    <span style="font-size:12px;color:var(--text-muted);font-weight:400">（点击选择/取消）</span>
                </label>
                <div class="reminder-days-grid" id="reminderDaysGrid">
                    ${reminderChips}
                </div>
                <div style="margin-top:8px;display:flex;gap:8px;">
                    <button class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('.reminder-day-chip').forEach(c=>c.classList.add('active'))">全选</button>
                    <button class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('.reminder-day-chip').forEach(c=>c.classList.remove('active'))">取消</button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">📝 备注</label>
                <textarea class="form-textarea" id="formNotes" placeholder="可选备注信息">${item ? Utils.escapeHtml(item.notes || '') : ''}</textarea>
            </div>
        `, `
            <button class="btn btn-secondary" id="modalCancel">取消</button>
            <button class="btn btn-primary" id="modalConfirm">${isEdit ? '保存修改' : '创建提醒'}</button>
        `);

        Modal.setConfirmHandler(async () => {
            if (await this.saveItemForm()) {
                Modal.close();
                await this.navigate(this.currentPage);
            }
        });

        Modal.setCancelHandler(() => Modal.close());
    },

    async saveItemForm() {
        const id = parseInt(document.getElementById('formId').value);
        const type = document.getElementById('formType').value;
        const name = document.getElementById('formName').value.trim();
        let expiryDate = document.getElementById('formExpiry')?.value || '';
        const notes = document.getElementById('formNotes').value.trim();
        const notifyEmail = document.getElementById('formNotifyEmail').checked ? 1 : 0;

        // 收集细节信息
        const details = {};

        // 生日提前处理：农历用月日格式
        if (type === 'birthday') {
            const isLunar = document.getElementById('formIsLunar')?.checked || false;
            details.is_lunar = isLunar;
            if (isLunar) {
                details.lunar_mm = parseInt(document.getElementById('formLunarMm')?.value || '1');
                details.lunar_dd = parseInt(document.getElementById('formLunarDd')?.value || '1');
                const mm = String(details.lunar_mm).padStart(2,'0');
                const dd = String(details.lunar_dd).padStart(2,'0');
                expiryDate = mm + '-' + dd;
            }
        }

        if (!name) { Utils.toast('请输入名称', 'error'); return false; }
        if (!expiryDate) { Utils.toast('请选择到期日期', 'error'); return false; }

        if (type === 'server') {
            details.ip = document.getElementById('formIp')?.value.trim() || '';
            details.provider = document.getElementById('formProvider')?.value.trim() || '';
            const services = [];
            document.querySelectorAll('#servicesContainer .service-tag').forEach(tag => {
                const sName = tag.dataset.name || tag.textContent.replace('×', '').trim();
                const sUrl = tag.dataset.url || '';
                services.push({ name: sName, url: sUrl });
            });
            details.services = services;
        }

        if (type === 'certificate' || type === 'icp') {
            details.domain = document.getElementById('formDomain')?.value.trim() || '';
        }

        // 收集提醒天数
        const reminderDays = [];
        document.querySelectorAll('.reminder-day-chip.active').forEach(chip => {
            reminderDays.push(parseInt(chip.dataset.day));
        });
        reminderDays.sort((a, b) => b - a);

        try {
            Utils.showLoader(true);
            await Utils.api('save_item', {
                id: id || 0,
                type,
                name,
                details,
                expiry_date: expiryDate,
                reminder_days: reminderDays,
                notes,
                notify_email: notifyEmail,
            });
            Utils.toast(id > 0 ? '保存成功' : '创建成功', 'success');
            return true;
        } catch (e) {
            Utils.toast(e.message, 'error');
            return false;
        } finally {
            Utils.showLoader(false);
        }
    },

    addServiceTag() {
        const nameInput = document.getElementById('formServiceName');
        const urlInput = document.getElementById('formServiceUrl');
        const container = document.getElementById('servicesContainer');
        const name = nameInput.value.trim();
        const url = urlInput.value.trim();
        if (!name) { Utils.toast('请输入服务名称', 'warning'); nameInput.focus(); return; }

        // 去重检查
        let exists = false;
        container.querySelectorAll('.service-tag').forEach(tag => {
            if (tag.dataset.name === name && tag.dataset.url === url) exists = true;
        });
        if (exists) { Utils.toast('该服务已存在', 'warning'); return; }

        const tag = document.createElement('span');
        tag.className = 'service-tag';
        tag.dataset.name = name;
        tag.dataset.url = url;
        tag.innerHTML = `${Utils.escapeHtml(name)}${url ? `<span style="color:var(--text-muted);font-size:11px;margin-left:2px;">(${Utils.escapeHtml(url)})</span>` : ''}<span class="remove" onclick="this.parentElement.remove()">×</span>`;
        container.appendChild(tag);
        nameInput.value = '';
        urlInput.value = '';
        nameInput.focus();
    },

    toggleLunar() {
        const isLunar = document.getElementById('formIsLunar').checked;
        document.getElementById('lunarDateFields').style.display = isLunar ? '' : 'none';
        document.getElementById('solarDateFields').style.display = isLunar ? 'none' : '';
    },

    async deleteItem(id) {
        if (!confirm('确定要删除此提醒吗？')) return;
        try {
            Utils.showLoader(true);
            await Utils.api('delete_item', { id });
            Utils.toast('已删除', 'success');
            await this.navigate(this.currentPage);
        } catch (e) {
            Utils.toast(e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    async testReminder(id) {
        try {
            Utils.showLoader(true);
            await Utils.api('send_test_reminder', { id });
            Utils.toast('测试提醒已发送，请检查邮箱', 'success');
        } catch (e) {
            Utils.toast(e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    // ==============================
    // 设置页面
    // ==============================
    async renderSettings(container) {
        let settings;
        try {
            settings = await Utils.api('get_settings');
        } catch(e) {
            settings = {
                smtp_host: 'smtp.qq.com',
                smtp_port: '465',
                smtp_user: '10361011@qq.com',
                smtp_pass: '',
                notify_email: '10361011@qq.com',
                cron_enabled: '1',
                reminder_defaults: '[30,15,7,1]',
            };
        }

        container.innerHTML = `
            <div class="page-header">
                <div>
                    <h1 class="page-title">⚙️ 系统设置</h1>
                    <p class="page-subtitle">配置邮件发送和系统参数</p>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-section-title">📧 邮件配置（QQ邮箱）</div>
                <div class="form-group">
                    <label class="form-label">SMTP 服务器</label>
                    <input class="form-input" id="setSmtpHost" value="${Utils.escapeHtml(settings.smtp_host || 'smtp.qq.com')}" placeholder="smtp.qq.com">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">端口</label>
                        <input class="form-input" id="setSmtpPort" value="${settings.smtp_port || '465'}" placeholder="465">
                    </div>
                    <div class="form-group">
                        <label class="form-label">邮箱地址</label>
                        <input class="form-input" id="setSmtpUser" value="${Utils.escapeHtml(settings.smtp_user || '')}" placeholder="your@qq.com">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP 授权码
                        <span style="font-size:12px;color:var(--text-muted);font-weight:400">（QQ邮箱需在设置中生成）</span>
                    </label>
                    <input class="form-input" type="password" id="setSmtpPass" value="${Utils.escapeHtml(settings.smtp_pass || '')}" placeholder="填写授权码">
                    <div class="form-hint">💡 使用QQ邮箱请开启 SMTP 服务后获取授权码。如不修改请留空。</div>
                </div>
                <div class="form-group">
                    <label class="form-label">接收通知的邮箱</label>
                    <input class="form-input" id="setNotifyEmail" value="${Utils.escapeHtml(settings.notify_email || '10361011@qq.com')}" placeholder="10361011@qq.com">
                </div>
                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button class="btn btn-primary" onclick="App.saveSettings()">💾 保存设置</button>
                    <button class="btn btn-secondary" onclick="App.testEmailConfig()">📧 发送测试邮件</button>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-section-title">⏰ 提醒设置</div>
                <div class="form-group">
                    <label class="form-label">默认提醒天数（JSON格式）</label>
                    <input class="form-input" id="setReminderDefaults" value="${Utils.escapeHtml(settings.reminder_defaults || '[30,15,7,1]')}" placeholder="[30,15,7,1]">
                    <div class="form-hint">新增提醒时的默认天数，例如 [30,15,7,1] 表示提前1个月、半个月、一周、一天</div>
                </div>
                <div class="form-group">
                    <label class="form-switch">
                        <input type="checkbox" id="setCronEnabled" ${settings.cron_enabled === '1' ? 'checked' : ''}>
                        <span class="switch-track"></span>
                        <span style="font-size:14px;color:var(--text-secondary)">启用定时提醒（需配置 crontab）</span>
                    </label>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-primary" onclick="App.saveSettings()">💾 保存设置</button>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-section-title">🔐 账号安全</div>
                <div class="form-group">
                    <label class="form-label">新账号（可选）</label>
                    <input class="form-input" id="setNewUsername" placeholder="留空则不修改">
                </div>
                <div class="form-group">
                    <label class="form-label">当前密码</label>
                    <input class="form-input" type="password" id="setCurrentPw" placeholder="输入当前密码">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">新密码</label>
                        <input class="form-input" type="password" id="setNewPw" placeholder="至少4位">
                    </div>
                    <div class="form-group">
                        <label class="form-label">确认新密码</label>
                        <input class="form-input" type="password" id="setNewPw2" placeholder="再次输入">
                    </div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-primary" onclick="App.changePassword()">🔑 修改密码</button>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-section-title">📖 使用说明</div>
                <div style="font-size:14px;color:var(--text-secondary);line-height:1.8;">
                    <p>1. <strong>配置邮箱</strong>：填写QQ邮箱和授权码，点击"发送测试邮件"验证。</p>
                    <p>2. <strong>添加提醒</strong>：在各分类页面添加需要提醒的项目。</p>
                    <p>3. <strong>设置提醒时间</strong>：每个项目可设置多个提醒时间点（如提前30天、15天等）。</p>
                    <p>4. <strong>定时任务</strong>（可选）：在服务器 crontab 中添加以下命令实现自动提醒：</p>
                    <pre style="background:rgba(0,0,0,0.3);padding:12px;border-radius:8px;margin:8px 0;font-size:13px;overflow-x:auto;">0 8 * * * /usr/bin/php ${document.location.origin}/cron.php</pre>
                    <p>5. <strong>生日提醒</strong>：生日到期后会自动更新为下一年的日期。</p>
                </div>
            </div>
        `;
    },

    async saveSettings() {
        const data = {
            smtp_host: document.getElementById('setSmtpHost').value.trim(),
            smtp_port: document.getElementById('setSmtpPort').value.trim(),
            smtp_user: document.getElementById('setSmtpUser').value.trim(),
            smtp_pass: document.getElementById('setSmtpPass').value.trim(),
            notify_email: document.getElementById('setNotifyEmail').value.trim(),
            cron_enabled: document.getElementById('setCronEnabled').checked ? '1' : '0',
            reminder_defaults: document.getElementById('setReminderDefaults').value.trim(),
        };

        if (!data.smtp_host) { Utils.toast('请输入SMTP服务器', 'error'); return; }
        if (!data.smtp_user) { Utils.toast('请输入邮箱地址', 'error'); return; }
        if (!data.notify_email) { Utils.toast('请输入接收邮箱', 'error'); return; }

        // 验证 reminder_defaults JSON
        try {
            const parsed = JSON.parse(data.reminder_defaults);
            if (!Array.isArray(parsed) || parsed.length === 0) throw new Error();
        } catch(e) {
            Utils.toast('默认提醒天数格式不正确，请输入合法的JSON数组，如 [30,15,7,1]', 'error');
            return;
        }

        try {
            Utils.showLoader(true);
            await Utils.api('save_settings', data);
            Utils.toast('设置已保存', 'success');
        } catch (e) {
            Utils.toast(e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    async testEmailConfig() {
        const data = {
            smtp_host: document.getElementById('setSmtpHost').value.trim(),
            smtp_port: parseInt(document.getElementById('setSmtpPort').value) || 465,
            smtp_user: document.getElementById('setSmtpUser').value.trim(),
            smtp_pass: document.getElementById('setSmtpPass').value.trim(),
            notify_email: document.getElementById('setNotifyEmail').value.trim(),
        };

        if (!data.smtp_user || !data.smtp_pass) {
            Utils.toast('请先填写邮箱和授权码', 'error');
            return;
        }

        try {
            Utils.showLoader(true);
            await Utils.api('test_email', data);
            Utils.toast('✅ 测试邮件发送成功！请检查收件箱（注意查看垃圾邮件）', 'success');
        } catch (e) {
            Utils.toast('发送失败: ' + e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    async changePassword() {
        const currentPw = document.getElementById('setCurrentPw').value;
        const newPw = document.getElementById('setNewPw').value;
        const newPw2 = document.getElementById('setNewPw2').value;
        const newUsername = document.getElementById('setNewUsername').value.trim();
        if (!currentPw) { Utils.toast('请输入当前密码', 'error'); return; }
        if (!newPw) { Utils.toast('请输入新密码', 'error'); return; }
        if (newPw.length < 4) { Utils.toast('新密码至少4个字符', 'error'); return; }
        if (newPw !== newPw2) { Utils.toast('两次新密码不一致', 'error'); return; }
        try {
            Utils.showLoader(true);
            await Utils.api('change_password', { current_password: currentPw, new_password: newPw, new_username: newUsername });
            Utils.toast('密码已修改', 'success');
            document.getElementById('setCurrentPw').value = '';
            document.getElementById('setNewPw').value = '';
            document.getElementById('setNewPw2').value = '';
        } catch (e) {
            Utils.toast(e.message, 'error');
        } finally {
            Utils.showLoader(false);
        }
    },

    // ==============================
    // 发送日志
    // ==============================
    async renderLogs(container) {
        let data;
        try {
            data = await Utils.api('get_logs');
        } catch(e) {
            data = { logs: [] };
        }
        const logs = data.logs || [];

        const logsHtml = logs.length > 0 ? logs.map(log => `
            <div class="log-item ${log.status}">
                <div class="log-icon">${log.status === 'success' ? '✅' : '❌'}</div>
                <div class="log-info">
                    <div class="log-title">
                        ${log.item_name ? Utils.escapeHtml(log.item_name) : '<span style="color:var(--text-muted)">[已删除]</span>'}
                        ${log.item_type ? `<span class="badge ${Utils.badgeClass(log.item_type)}" style="margin-left:6px">${Utils.typeLabel(log.item_type)}</span>` : ''}
                    </div>
                    <div class="log-meta">
                        提前 ${log.reminder_day} 天提醒 ·
                        ${log.status === 'success' ? '✅ 发送成功' : '❌ ' + Utils.escapeHtml(log.message || '发送失败')}
                    </div>
                </div>
                <div class="log-time">${Utils.escapeHtml(log.created_at || '')}</div>
            </div>
        `).join('') : `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-title">暂无发送记录</div>
                <div class="empty-state-desc">当有到期提醒被触发时，记录会显示在这里</div>
            </div>
        `;

        container.innerHTML = `
            <div class="page-header">
                <div>
                    <h1 class="page-title">📝 发送日志</h1>
                    <p class="page-subtitle">查看邮件通知发送历史</p>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📬 邮件发送记录</h3>
                </div>
                <div class="card-body">
                    ${logsHtml}
                </div>
            </div>
        `;
    }
};

// =====================================
// 暴露到全局，供 onclick 调用
window.App = App;

// 启动应用
// =====================================
document.addEventListener('DOMContentLoaded', () => App.init());
