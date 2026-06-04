# ⏰ Expiration Reminder - 到期提醒系统

一款功能强大的到期提醒管理工具，支持云服务器续费、SSL证书、ICP备案、生日等多种提醒场景。拥有酷炫的蓝色粒子界面和自动邮件通知功能。

## ✨ 功能特色

- 🖥️ **云服务器管理**：记录服务器信息及提供的服务列表，到期自动提醒
- 🔒 **SSL证书监控**：跟踪域名证书到期时间
- 📄 **ICP备案管理**：管理网站备案有效期
- 🎂 **生日提醒**：自动更新下一年的生日日期
- 📋 **其他提醒**：支持自定义任意到期提醒
- 📧 **自动邮件通知**：通过 QQ 邮箱 SMTP 发送提醒邮件
- ⏰ **多级提醒**：每个项目可设置多个提醒时间（如提前30天、15天、7天、1天）
- 🎨 **酷炫界面**：深蓝色粒子主题，鼠标跟随特效，玻璃态设计
- 🔄 **生日自动续期**：生日到期后自动更新为下一年

## 🚀 快速部署

### 环境要求

- PHP 7.4+
- PHP SQLite 扩展（`php-sqlite3`）
- PHP OpenSSL 扩展（用于 SMTP 加密）
- Web 服务器（Nginx / Apache / Caddy）

### 安装步骤

1. **下载代码**

```bash
git clone https://github.com/tsuimanlung/Expiration-Reminder.git
cd Expiration-Reminder
```

2. **配置 Web 服务器**

将网站根目录指向项目目录，确保 PHP 已启用。

3. **设置目录权限**

```bash
chmod -R 755 .
chmod -R 777 data/   # 确保 SQLite 数据库可写
```

4. **配置 SMTP 邮箱**

打开系统 → 设置页面，填写：
- SMTP 服务器：`smtp.qq.com`
- 端口：`465`
- 邮箱地址：`your@qq.com`
- 授权码：在 QQ 邮箱设置中生成
- 接收邮箱：填写接收通知的邮箱

5. **设置定时任务（可选，用于自动发送提醒）**

```bash
crontab -e
```

添加以下行（每天上午8点执行）：

```
0 8 * * * /usr/bin/php /path/to/Expiration-Reminder/cron.php
```

或通过 Web 访问（需设置 cron_key，在设置中未开放此功能则直接 CLI 运行）：

```
https://your-domain.com/cron.php
```

### QQ 邮箱授权码获取

1. 登录 QQ 邮箱 → 设置 → 账户
2. 找到"POP3/SMTP服务" → 开启
3. 按照提示发送短信，获取授权码
4. 将授权码填入系统设置

## 🗂️ 项目结构

```
Expiration-Reminder/
├── index.php          # 主页面入口
├── api.php            # API 接口
├── cron.php           # 定时任务脚本
├── db.php             # 数据库初始化
├── smtp.php           # SMTP 邮件发送类
├── assets/
│   ├── css/
│   │   └── style.css  # 酷炫蓝色主题样式
│   └── js/
│       └── app.js     # 前端 SPA 应用逻辑
├── data/              # SQLite 数据库目录
├── backup/            # 数据备份目录
└── README.md
```

## 📸 截图预览

| 仪表盘 | 服务器管理 | 设置页面 |
|--------|-----------|---------|
| 到期概览 | 服务器及服务管理 | 邮件配置 |

## 🛠️ 技术栈

- **前端**：Vanilla JS SPA + CSS3 动画 + Canvas 粒子效果
- **后端**：PHP + SQLite
- **邮件**：SMTP 协议（支持 SSL/TLS）
- **数据库**：SQLite（无需额外数据库服务）

## 📄 License

MIT License

---

**GitHub**: [https://github.com/tsuimanlung/Expiration-Reminder](https://github.com/tsuimanlung/Expiration-Reminder)
