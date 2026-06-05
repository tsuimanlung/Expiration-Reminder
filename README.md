<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/SQLite-3-003B57?style=flat-square&logo=sqlite" alt="SQLite">
  <img src="https://img.shields.io/badge/license-MIT-blue?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/status-stable-brightgreen?style=flat-square" alt="Status">
  <img src="https://img.shields.io/badge/Nginx-1.20-009639?style=flat-square&logo=nginx" alt="Nginx">
</p>

<h1 align="center">⏰ Expiration Reminder</h1>
<h3 align="center">到期提醒系统 — 云服务器 · SSL证书 · ICP备案 · 生日 · 自定义提醒</h3>

<p align="center">
  一款功能强大的到期提醒管理工具，支持多类型到期监控、多级邮件自动通知，拥有酷炫的蓝色粒子动态界面。
</p>

<p align="center">
  <em>生产环境已部署</em>
</p>

<p align="center">
  <a href="#-功能特色">功能特色</a> •
  <a href="#-快速开始">快速开始</a> •
  <a href="#-部署指南">部署指南</a> •
  <a href="#-配置邮件">配置邮件</a> •
  <a href="#-项目结构">项目结构</a> •
  <a href="#-API文档">API文档</a>
</p>

---

## ✨ 功能特色

### 📊 统一仪表盘
- 所有到期项目一目了然
- 统计概览：各类型数量、已过期数量
- 即将到期时间线（30天内高亮显示）
- 最近邮件发送记录

### 🖥️ 云服务器管理
- 记录服务器 IP、服务商信息
- **多应用管理**：记录每台服务器上运行的服务（如读书站点 `ip:8080`、记账站点 `ip:9090`）
- 服务器续费到期提醒

### 🔒 SSL证书 & 📄 ICP备案
- 跟踪域名证书到期时间
- 管理网站备案有效期
- 支持多个域名

### 🎂 生日提醒
- 生日到期后自动续期为下一年
- 无需手动更新

### 📋 其他提醒
- 支持任意自定义到期提醒场景
- 灵活的名称和备注

### 🔐 登录认证
- **账号密码保护**：首次使用引导设置账号密码，防止未授权访问
- **会话管理**：基于 PHP Session 的登录机制，退出即销毁
- **密码修改**：设置页面支持修改密码和账号

### 📧 自动邮件通知
- 通过 **QQ邮箱 SMTP** 发送提醒邮件
- 每项目可设置**多个提醒时间点**（如提前30天、15天、7天、1天）
- 到期当天自动发送
- 发送记录完整可查

### 🎨 酷炫界面
- **Canvas 粒子系统**：鼠标移动时粒子跟随，产生动态流动效果
- **玻璃态设计**：毛玻璃卡片、渐变边框、发光效果
- **深蓝主题**：护眼深色系，适合长期使用
- **完全响应式**：桌面和移动端均可使用

---

## 🚀 快速开始

### 环境要求

| 依赖 | 版本 | 说明 |
|------|------|------|
| PHP | ≥ 7.4 | 推荐 8.0+ |
| SQLite3 扩展 | ✅ `php-sqlite3` | 数据库 |
| PDO SQLite 扩展 | ✅ `php-pdo_sqlite` | 数据库连接 |
| OpenSSL 扩展 | ✅ `php-openssl` | SMTP加密 |
| MBString 扩展 | ✅ `php-mbstring` | 中文支持 |

### 安装（5 分钟）

```bash
# 1. 下载代码
git clone https://github.com/tsuimanlung/Expiration-Reminder.git
cd Expiration-Reminder

# 2. 确保 data 目录存在且可写
mkdir -p data
chmod -R 755 .
chmod -R 777 data/

# 3. 启动开发服务器测试
php -S 0.0.0.0:8080

# 4. 打开浏览器访问
# http://localhost:8080
```

> 数据库会自动初始化，无需手动创建。

---

## 📦 部署指南

### CentOS 7 部署（以本文 VPS 为例）

#### 1️⃣ 安装 PHP 8.0

```bash
# 安装 EPEL 和 REMI 源
yum install -y epel-release
yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
yum install -y yum-utils
yum-config-manager --enable remi-php80

# 安装 PHP 及必要扩展
yum install -y php php-cli php-pdo php-sqlite3 php-mbstring php-openssl php-fpm

# 启动 PHP-FPM
systemctl start php-fpm
systemctl enable php-fpm
```

#### 2️⃣ 配置 Nginx

```nginx
server {
    listen       8080;
    listen       [::]:8080;
    server_name  _;

    root         /var/www/Expiration-Reminder;
    index        index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }
}
```

```bash
# 测试并重载 Nginx
nginx -t && systemctl reload nginx
```

#### 3️⃣ 设置目录权限

```bash
chmod -R 755 /var/www/Expiration-Reminder/
chown -R nginx:nginx /var/www/Expiration-Reminder/data/
chmod -R 777 /var/www/Expiration-Reminder/data/
```

#### 4️⃣ 设置 PHP 时区

```bash
# 修改 php.ini
vi /etc/php.ini
# 找到 ;date.timezone = 改为 date.timezone = Asia/Shanghai

# 重启 PHP-FPM
systemctl restart php-fpm
```

#### 5️⃣ 配置定时任务（自动发邮件）

```bash
crontab -e
```

添加以下行（每天晚 8 点自动检查）：

```
0 20 * * * /usr/bin/php /var/www/Expiration-Reminder/cron.php
```

#### 6️⃣ 防火墙放行端口

```bash
firewall-cmd --add-port=8080/tcp --permanent
firewall-cmd --reload
```

> **首次访问**：部署完成后打开网站，会引导您设置登录账号和密码，请务必牢记。

### 通用 Nginx 配置

适用于 Debian/Ubuntu 等其他系统：

---

## 📧 配置邮件

### QQ 邮箱授权码获取

| 步骤 | 操作 |
|------|------|
| ① | 登录 [QQ邮箱](https://mail.qq.com) |
| ② | 进入 **设置 → 账户** |
| ③ | 找到 **POP3/SMTP服务**，点击**开启** |
| ④ | 按提示发送短信，获取**授权码** |
| ⑤ | 将授权码填入本系统的 **系统设置** 页面 |

### 默认邮件配置

| 参数 | 默认值 |
|------|--------|
| SMTP 服务器 | `smtp.qq.com` |
| 端口 | `465` (SSL) |
| 发件邮箱 | `10361011@qq.com` |
| 收件邮箱 | `10361011@qq.com` |

> 支持自定义修改为任意支持 SMTP 的邮箱（163、Gmail、Outlook 等）。

---

## 🗂️ 项目结构

```
Expiration-Reminder/
├── index.php              # 主页面入口（SPA 外壳）
├── api.php                # 后端 API（CRUD + 邮件）
├── cron.php               # 定时任务脚本
├── functions.php          # 公共函数（数据库操作、邮件发送）
├── smtp.php               # SMTP 邮件发送类
├── db.php                 # SQLite 数据库初始化
├── README.md              # 项目文档
├── .gitignore
├── assets/
│   ├── css/
│   │   └── style.css      # 蓝色酷炫主题样式
│   └── js/
│       └── app.js         # 前端 SPA 应用逻辑
├── data/                  # SQLite 数据库文件（自动生成）
└── backup/                # 数据备份目录
```

### 数据库表结构

#### `items` — 到期项目表
| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键，自增 |
| `type` | TEXT | 类型：server/certificate/icp/birthday/other |
| `name` | TEXT | 名称 |
| `details` | TEXT(JSON) | 详细信息（服务列表、IP、域名等） |
| `expiry_date` | TEXT | 到期日期 (Y-m-d) |
| `reminder_days` | TEXT(JSON) | 提醒天数数组，如 [30,15,7,1] |
| `notify_email` | INTEGER | 是否邮件通知 |
| `notes` | TEXT | 备注 |
| `enabled` | INTEGER | 是否启用 |
| `created_at` / `updated_at` | TEXT | 时间戳 |

#### `email_logs` — 邮件发送日志
| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键，自增 |
| `item_id` | INTEGER | 关联项目ID |
| `reminder_day` | INTEGER | 提前天数 |
| `sent_date` | TEXT | 发送日期 |
| `status` | TEXT | 状态：success/failed |
| `message` | TEXT | 错误信息 |
| `created_at` | TEXT | 创建时间 |

#### `settings` — 系统设置
| 字段 | 类型 | 说明 |
|------|------|------|
| `key_name` | TEXT | 设置键名 |
| `value` | TEXT | 设置值 |

---

## 🔌 API 文档

所有 API 通过 `api.php?action={action}` 访问。

| Action | 方法 | 参数 | 说明 |
|--------|------|------|------|
| `get_dashboard` | GET | - | 获取仪表盘数据 |
| `get_items` | GET | type | 按类型获取项目列表 |
| `get_item` | GET | id | 获取单个项目详情 |
| `save_item` | POST | JSON Body | 创建/更新项目 |
| `delete_item` | POST | {id} | 删除项目 |
| `get_settings` | GET | - | 获取系统设置 |
| `save_settings` | POST | JSON Body | 保存系统设置 |
| `test_email` | POST | JSON Body | 发送测试邮件 |
| `get_logs` | GET | - | 获取发送日志 |
| `send_test_reminder` | POST | {id} | 手动发送某个项目的提醒 |

---

## 🖥️ 本地开发

```bash
# 使用 PHP 内置服务器（无需 Nginx/Apache）
php -S 0.0.0.0:8080

# 或者在项目目录启动
php -S localhost:8080
```

**Windows 用户**：下载 PHP for Windows 后，将 `php.exe` 所在目录添加到 PATH 环境变量即可。

---

## 🛠️ 技术栈

| 层级 | 技术 |
|------|------|
| **前端** | Vanilla JavaScript SPA + CSS3 动画 + Canvas 粒子效果 |
| **后端** | PHP 8.x |
| **数据库** | SQLite（文件数据库，无需额外服务） |
| **邮件** | SMTP 协议（原生 PHP 实现，无外部依赖） |

---

## 📄 License

MIT License © 2024 [tsuimanlung](https://github.com/tsuimanlung)

---

<p align="center">
  <sub>Built with ❤️ and PHP</sub>
  <br>
  <a href="https://github.com/tsuimanlung/Expiration-Reminder">GitHub</a> •
  <a href="#-功能特色">功能特色</a> •
  <a href="#-快速开始">快速开始</a>
</p>
