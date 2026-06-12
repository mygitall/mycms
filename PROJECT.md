# MYCMS — 完整项目文档

> 本文档是对 `CLAUDE.md` 规则手册的完整展开，包含项目的所有技术细节、功能说明和使用指南。
>
> **文档版本：** v1.0.0
>
> **最后更新：** 2026-06-10

---

## 目录

1. [项目概述](#1-项目概述)
2. [技术架构](#2-技术架构)
3. [目录结构](#3-目录结构)
4. [入口文件体系](#4-入口文件体系)
5. [数据库设计](#5-数据库设计)
6. [API 接口体系](#6-api-接口体系)
7. [标签模板系统](#7-标签模板系统)
8. [安全体系](#8-安全体系)
9. [安装与部署](#9-安装与部署)
10. [运维工具](#10-运维工具)
11. [前端页面体系](#11-前端页面体系)
12. [模块化设计](#12-模块化设计)
13. [性能优化](#13-性能优化)
14. [开发规范](#14-开发规范)
15. [常见问题](#15-常见问题)

---

## 1. 项目概述

### 1.1 项目定位

MYCMS 是一个生产级的 **无框架内容管理系统**，具备以下特征：

| 特性 | 说明 |
|------|------|
| PHP 兼容性 | PHP 5.3 ~ PHP 8+ 全覆盖 |
| 数据库 | MySQL 5.5+ / MariaDB 5.5+ |
| 无框架 | 纯原生 PHP，无 Composer 重度依赖 |
| 类帝国 CMS | 参照帝国 CMS 的架构思路 |
| 共享主机优先 | 兼容无 SSH 权限的虚拟主机 |
| 多环境支持 | 宝塔 / Apache / Nginx / MAMP / 虚拟主机 / 子目录部署 |

### 1.2 核心目标优先级

```
1. 安全性优先
2. 稳定性优先
3. 兼容性优先
4. 可运行优先
5. 低维护成本优先
6. 性能优化次之
```

### 1.3 系统能力矩阵

| 模块 | 状态 | 说明 |
|------|------|------|
| 用户认证 | ✅ | Token + Cookie 双轨认证 |
| RBAC 权限 | ✅ | 超级管理员 / 普通管理员二级制 |
| 文章系统 | ✅ | 草稿 / 发布 / 置顶 / 定时 / 回收站 |
| 软件库 | ✅ | 分类管理 / 多平台支持 / 下载统计 |
| 搜索系统 | ✅ | 全局搜索 / 搜索历史 / 防刷保护 |
| 收藏系统 | ✅ | 文章收藏 / 点赞统计 |
| 栏目管理 | ✅ | 树形栏目 / 多种类型（列表/单页/外链） |
| 模板系统 | ✅ | 多模板支持 / 标签系统 / 模板编译缓存 |
| 操作日志 | ✅ | 全操作记录 / IP 追踪 / 分页查询 |
| 数据迁移 | ✅ | JSON 导出 / 导入 / 格式校验 |
| 图片上传 | ✅ | 白名单 / MIME 校验 / 按年月分目录 |
| IP 封禁 | ✅ | 暴力破解封禁 / 频率限制 / 一键解封 |
| 安全响应头 | ✅ | CSP / X-Frame-Options / X-Content-Type-Options |

---

## 2. 技术架构

### 2.1 核心技术栈

```
┌─────────────────────────────────────────────────┐
│                   前端                            │
│  HTML5 + CSS3 + Vanilla JS + Fetch API          │
│  (零前端框架依赖，纯原生实现)                       │
└────────────────┬────────────────────────────────┘
                 │ HTTP(S)
┌────────────────▼────────────────────────────────┐
│              PHP 入口层                           │
│  index.php (前台) / admin.php (后台)             │
│  login.php / article.php / article.php           │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│              路由分发层                           │
│  基于 SCRIPT_NAME 自动检测 BASE_PATH             │
│  支持根目录 / 子目录 / 多级子目录部署              │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│              API 处理层                           │
│  /api/*.php  — 核心 API                         │
│  /admin/api/*.php — 后台管理 API                 │
│  /article/api/*.php — 文章模块 API               │
│  /search/api/*.php — 搜索模块 API               │
│  /software/api/*.php — 软件模块 API              │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│              核心函数库                           │
│  config/db.php (数据库 + 认证 + 安全 + 工具)    │
│  includes/auth.php (重置工具鉴权)                │
│  includes/mysql_install_helper.php (安装探测)     │
└────────────────┬────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────┐
│              数据持久层                           │
│         MySQL 5.5+ / PDO                        │
└─────────────────────────────────────────────────┘
```

### 2.2 路径自适应机制（核心）

本系统最重要的架构特性：**零配置路径自适应**。

无论项目部署在：

- `http://localhost/` (根目录)
- `http://localhost/pro/` (一级子目录)
- `http://localhost/wei/cms/` (多级子目录)

系统均能自动检测路径前缀并正确运行，无需修改任何配置。

```php
// 核心路径检测逻辑 (index.php)
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$scriptDir = pathinfo($scriptName, PATHINFO_DIRNAME);
if ($scriptDir === '.' || $scriptDir === '/') {
    $BASE_PATH = '';
} else {
    $BASE_PATH = $scriptDir; // 如 /wei 或 /pro
}
```

所有资源路径（CSS/JS/图片/API）均基于 `BASE_PATH` 动态生成：

```php
$url = rtrim($BASE_PATH, '/') . '/api/login';
// 根目录部署 → /api/login
// 子目录部署 → /pro/api/login
```

### 2.3 多服务器环境支持

| 环境 | 支持方式 | 说明 |
|------|---------|------|
| Apache | `.htaccess` URL 重写 | ModRewrite |
| Nginx | `nginx.conf` 模板 | try_files + fastcgi |
| MAMP | PHP 内置服务器 | 直接访问 .php 入口文件 |
| 宝塔面板 | 静态规则 + PHP-FPM | 站点配置 |
| 虚拟主机 | .htaccess | 无 SSH 权限首选 |
| 无 Shell 主机 | 直接访问 .php 文件 | 不依赖 URL 重写 |

---

## 3. 目录结构

```
MYCMS/
│
├── 📄 入口文件（根目录直接访问）
│   ├── index.php          # 前台控制器 — 路由分发 + 页面渲染
│   ├── admin.php          # 后台管理入口
│   ├── login.php          # 独立登录入口（含登录表单）
│   ├── article.php         # 文章管理入口
│   ├── reset_admin.php     # 管理员密码重置工具 ⚠️高危
│   ├── reset_all.php       # 全量重置工具 ⚠️高危
│   └── clear_ban.php       # IP 封禁急救工具
│
├── 📁 config/             # 核心配置
│   └── db.php             # 数据库连接 + 全局函数库（最重要文件）
│
├── 📁 api/                # 核心 API（用户管理 / 系统）
│   ├── login.php          # 登录
│   ├── register.php       # 注册
│   ├── logout.php         # 登出
│   ├── list.php           # 用户列表
│   ├── update.php         # 用户更新
│   ├── delete.php         # 用户删除
│   ├── batch_delete.php   # 批量删除
│   ├── stats.php          # 统计
│   ├── admin_logs.php     # 操作日志
│   ├── home.php           # 前台首页数据
│   ├── export.php         # 数据导出
│   ├── import.php         # 数据导入
│   ├── migrate_export.php  # 数据迁移导出
│   ├── migrate_import.php  # 数据迁移导入
│   ├── search_history.php  # 搜索历史
│   ├── ip_ban_toggle.php  # IP 封禁开关
│   ├── csrf_token.php     # CSRF Token
│   ├── validate_token.php  # Token 验证
│   ├── update_token_expiry.php # Token 续期
│   └── template_settings.php # 模板设置
│
├── 📁 admin/              # 后台管理界面
│   ├── index.html         # 后台管理 SPA（单页应用）
│   ├── index.php          # 后台入口（注入 BASE_PATH）
│   └── api/               # 后台专用 API
│       ├── columns.php     # 栏目管理
│       ├── tag_list.php   # 标签列表
│       ├── upload_image.php # 图片上传
│       ├── search_history.php # 搜索历史
│       ├── template_settings.php # 模板设置
│       ├── check_setup.php # 安装状态检查
│       ├── save_setup.php  # 保存安装配置
│       └── test_connection.php # 数据库连接测试
│
├── 📁 article/            # 文章模块
│   ├── config.php         # 文章数据库初始化
│   ├── index.html         # 文章管理页面
│   ├── article-list.html  # 文章列表模板
│   ├── detail.html        # 文章详情模板
│   ├── favorites.html     # 收藏页面
│   ├── login.html         # 登录页面
│   ├── search.html        # 搜索页面
│   ├── api/               # 文章 API
│   │   ├── list.php       # 文章列表
│   │   ├── create.php     # 创建文章
│   │   ├── update.php     # 更新文章
│   │   ├── delete.php     # 删除文章
│   │   ├── view.php       # 文章浏览
│   │   ├── favorites.php  # 收藏列表
│   │   ├── favorite.php   # 收藏操作
│   │   ├── unfavorite.php # 取消收藏
│   │   ├── favorite_status.php # 收藏状态
│   │   ├── favorites_stats.php  # 收藏统计
│   │   └── favorites_admin.php  # 后台收藏管理
│   └── assets/            # 文章模块静态资源
│
├── 📁 software/           # 软件模块
│   ├── config.php         # 软件数据库初始化 + API 封装
│   ├── api/               # 软件 API
│   │   ├── list.php       # 软件列表
│   │   ├── create.php     # 创建软件
│   │   ├── update.php     # 更新软件
│   │   ├── delete.php     # 删除软件
│   │   ├── status.php     # 上下架
│   │   └── categories.php # 分类管理
│   └── detail.html        # 软件详情模板
│
├── 📁 search/             # 搜索模块
│   ├── config.php         # 搜索数据源配置
│   ├── index.html         # 搜索页面
│   └── api/
│       └── list.php       # 搜索接口
│
├── 📁 wen/                # 文档模块（预留）
│
├── 📁 frontend/           # 前台页面（默认模板）
│   ├── index.html         # 首页
│   ├── article-list.html  # 文章列表
│   ├── detail.html        # 文章详情
│   ├── favorites.html     # 收藏
│   ├── login.html         # 登录
│   ├── search.html        # 搜索
│   └── assets/            # 前台静态资源
│
├── 📁 templates/           # 多模板目录
│   ├── v1/               # 模板 v1（默认后备）
│   └── v4/               # 模板 v4（当前激活）
│       ├── index.html
│       ├── article-list.html
│       ├── article-detail.html
│       ├── software-list.html
│       ├── software-detail.html
│       ├── news-proxy.php # 代理页（跨域）
│       └── *.html         # 其他模板页面
│
├── 📁 module/             # 核心模块
│   └── tags/              # 标签模板引擎
│       ├── config.php    # 标签系统入口
│       ├── Registry.php  # 标签注册表
│       ├── Parser.php    # 标签解析器
│       ├── Compiler.php  # 标签编译器
│       ├── Cache.php    # 编译缓存
│       ├── Hook.php     # 渲染钩子
│       └── builtin.php  # 内置标签实现
│
├── 📁 includes/           # 公共函数库
│   ├── auth.php          # 重置工具鉴权
│   └── mysql_install_helper.php # MySQL 安装辅助
│
├── 📁 install/            # 安装系统
│   ├── index.html         # 安装向导 HTML
│   ├── install.config.example.php # 配置模板
│   ├── install.config.php # 实际配置（安装后生成）
│   ├── install.lock       # 安装锁定文件
│   └── reset.php          # 重置安装状态
│
├── 📁 storage/           # 运行时存储
│   ├── runtime/          # 限流文件 / 运行时缓存
│   │   └── login_rate_*.json # IP 频率记录
│   │   └── login_ban_*.json  # IP 封禁记录
│   ├── cache/            # 模板编译缓存
│   │   └── tags/         # 标签编译缓存
│   └── uploads/          # 上传文件
│       └── images/       # 图片上传目录
│           └── YYYY/MM/ # 按年月分目录
│
├── 📁 performance/        # 性能优化工具
│   └── optimize_indexes.php # 索引优化
│
├── 📄 .htaccess          # Apache URL 重写规则
├── 📄 nginx.conf         # Nginx 配置模板
├── 📄 sw.js              # Service Worker（PWA）
├── 📄 .gitignore         # Git 忽略规则
├── 📄 README.md          # 项目说明
├── 📄 DEPLOY.md          # 部署指南
├── 📄 CLAUDE.md          # AI 编码规则（本文档的规则部分）
└── 📄 api-docs.html      # API 接口文档（HTML）
```

---

## 4. 入口文件体系

### 4.1 入口文件总览

本系统采用**多入口独立部署**策略，每个入口文件职责单一，互不依赖：

| 入口文件 | 职责 | 访问方式 |
|---------|------|---------|
| `index.php` | 前台路由：页面渲染 + API 分发 + 静态文件 | `/` 或 `/index.php` |
| `admin.php` | 后台管理入口 | `/admin.php` |
| `login.php` | 独立登录页面（含表单） | `/login.php` |
| `article.php` | 文章管理入口 | `/article.php` |
| `reset_admin.php` | 管理员密码重置 | `/reset_admin.php` |
| `reset_all.php` | 系统全量重置 | `/reset_all.php` |
| `clear_ban.php` | IP 封禁急救 | `/clear_ban.php` |

### 4.2 前台入口 (index.php)

`index.php` 是整个前台系统的核心路由器，负责：

**路由映射规则：**

```php
// 前台页面路由
'/'              → index.html（首页）
'/article-list'  → article-list.html（文章列表）
'/drone-list'   → drone-list.html（无人机列表）
'/software-list' → software-list.html（软件列表）
'/tag-doc'      → tag-doc.html（标签文档）
'/detail'       → detail.html（文章详情）
'/search'       → search.html（搜索页）
'/favorites'    → favorites.html（收藏页）
'/login'        → login.html（登录页）

// API 路由
/api/xxx         → /api/xxx.php
/storage/xxx     → /storage/xxx (静态文件)

// 详情页路由
/article/p/:id   → article-detail.html（文章详情）
/software/p/:id  → software-detail.html（软件详情）

// 模块路由
/article/*       → /article/* (文章模块)
/search/*       → /search/* (搜索模块)
```

**模板优先级：**

```
当前激活模板 → templates/{v4}/xxx.html
             → templates/v1/xxx.html (后备)
             → frontend/xxx.html (最后兜底)
```

**BASE_PATH 注入：**

每个页面输出前自动注入：

```html
<script>window.__BASE_PATH__ = ""; // 或 "/pro"</script>
```

### 4.3 后台入口 (admin.php)

- 加载 `admin/index.html`（后台 SPA）
- 注入 `BASE_PATH`，确保 API 路径正确
- 设置安全响应头

### 4.4 登录入口 (login.php)

独立登录页面，提供：

- 登录表单（用户名 + 密码 + 记住我）
- CSRF Token 保护
- 登录成功后重定向到 `/admin.php`
- Token 写入 HttpOnly Cookie

### 4.5 文章入口 (article.php)

- 加载 `article/index.html`
- 注入 `BASE_PATH`

---

## 5. 数据库设计

### 5.1 表结构一览

所有表均使用 `utf8mb4` 字符集和 `utf8mb4_unicode_ci` 排序规则。

#### 核心表（sys_users / sys_admin_logs / sys_user_tokens / sys_config）

**sys_users — 用户/管理员表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键，自增 |
| `username` | VARCHAR(50) UNIQUE | 用户名 |
| `password` | VARCHAR(255) | bcrypt 密码哈希 |
| `login_count` | INT UNSIGNED | 登录次数 |
| `created_at` | DATETIME | 创建时间 |
| `last_login_at` | DATETIME | 最后登录时间 |
| `is_super_admin` | TINYINT | 超级管理员标识：0=普通管理员，1=超级管理员 |
| `password_changed_at` | DATETIME | 密码最后修改时间（Token 失效检测用） |

**sys_admin_logs — 管理员操作日志表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `admin_id` | INT UNSIGNED | 管理员 ID |
| `admin_username` | VARCHAR(50) | 管理员用户名 |
| `action` | VARCHAR(50) | 操作类型（login/logout/delete_user/...） |
| `target_type` | VARCHAR(30) | 目标类型：user/token/column/software |
| `target_id` | INT UNSIGNED | 目标 ID |
| `target_username` | VARCHAR(50) | 目标用户名 |
| `detail` | TEXT | 操作详情 |
| `ip` | VARCHAR(45) | IP 地址（支持 IPv6） |
| `created_at` | DATETIME | 操作时间 |

**sys_user_tokens — 认证 Token 表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `user_id` | INT UNSIGNED | 关联用户 ID |
| `token` | VARCHAR(64) UNIQUE | Token 值（64位随机字符串） |
| `device` | VARCHAR(100) | 设备标识 |
| `ip` | VARCHAR(45) | 登录 IP |
| `expires_at` | DATETIME | 过期时间（默认7天，记住我30天） |
| `created_at` | DATETIME | 创建时间 |

**sys_config — 系统配置表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `config_key` | VARCHAR(100) UNIQUE | 配置键名 |
| `config_value` | TEXT | 配置值 |
| `updated_at` | DATETIME | 更新时间 |

预置配置项：

| 配置键 | 说明 |
|--------|------|
| `frontend_template` | 当前激活的前台模板名称（v1/v4） |
| `site_name` | 站点名称 |
| `site_description` | 站点描述 |
| 其他 | 可扩展 |

**sys_columns — 栏目表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `parent_id` | INT UNSIGNED | 父栏目 ID（0=顶级） |
| `name` | VARCHAR(100) | 栏目名称 |
| `type` | VARCHAR(20) | 栏目类型：list=列表页 page=单页 link=外链 |
| `template` | VARCHAR(100) | 绑定的模板文件 |
| `url` | VARCHAR(500) | 外链 URL（type=link 时使用） |
| `sort_order` | INT | 排序（越小越靠前） |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

#### 文章模块表（articles / article_favorites）

**articles — 文章表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `title` | VARCHAR(255) | 文章标题 |
| `content` | MEDIUMTEXT | 文章内容（富文本 HTML） |
| `category` | VARCHAR(100) | 分类 |
| `tags` | VARCHAR(255) | 标签（逗号分隔） |
| `author_id` | INT UNSIGNED | 作者 ID |
| `author_name` | VARCHAR(50) | 作者名 |
| `author_avatar` | VARCHAR(500) | 作者头像 |
| `cover_image` | VARCHAR(1000) | 封面图片 URL |
| `source_url` | VARCHAR(1000) | 来源 URL |
| `status` | TINYINT | 状态：0=草稿，1=已发布 |
| `view_count` | INT UNSIGNED | 浏览次数 |
| `is_featured` | TINYINT | 置顶标识 |
| `published_at` | DATETIME | 发布时间 |
| `expires_in` | INT UNSIGNED | 有效期（秒），NULL=永久有效 |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

索引：

- `idx_status_time` — `(status, published_at, created_at)` 列表查询优化
- `idx_category` — `(category)` 分类筛选
- `idx_featured` — `(is_featured)` 置顶查询

软删除策略：`deleted_at` 字段（预留，逻辑删除）

**article_favorites — 文章收藏表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `user_id` | INT UNSIGNED | 用户 ID |
| `article_id` | INT UNSIGNED | 文章 ID |
| `created_at` | DATETIME | 收藏时间 |

索引：

- `uk_user_article` — `UNIQUE(user_id, article_id)` 防止重复收藏
- `idx_user_created` — `(user_id, created_at)` 用户收藏列表
- `idx_article` — `(article_id)` 文章收藏统计

#### 软件模块表（sys_software / sys_software_categories）

**sys_software_categories — 软件分类表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `name` | VARCHAR(100) UNIQUE | 分类名称 |
| `sort_order` | INT | 排序 |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

**sys_software — 软件表**

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INT UNSIGNED PK | 主键 |
| `name` | VARCHAR(255) | 软件名称 |
| `version` | VARCHAR(100) | 版本号 |
| `category_id` | INT UNSIGNED | 分类 ID |
| `category_name` | VARCHAR(100) | 分类名称 |
| `os_support` | VARCHAR(255) | 支持平台（逗号分隔） |
| `file_size` | VARCHAR(100) | 文件大小 |
| `download_urls` | TEXT | 下载链接（多行，每行一个） |
| `screenshots` | TEXT | 截图（逗号分隔 URL） |
| `description` | MEDIUMTEXT | 详细描述 |
| `changelog` | MEDIUMTEXT | 更新日志 |
| `status` | TINYINT | 状态：0=下架，1=上架，2=待审核 |
| `sort_order` | INT | 排序 |
| `tags` | VARCHAR(255) | 标签 |
| `view_count` | INT UNSIGNED | 浏览次数 |
| `download_count` | INT UNSIGNED | 下载次数 |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

索引：

- `idx_status_sort` — `(status, sort_order, id)` 列表查询
- `idx_category_name` — `(category_name)` 分类筛选
- `idx_category_id` — `(category_id)` 分类 ID 筛选

### 5.2 初始化策略

数据库采用**渐进式初始化**策略：

1. **自动建库**：`config/db.php` 在首次连接时自动 `CREATE DATABASE IF NOT EXISTS`
2. **自动建表**：`CREATE TABLE IF NOT EXISTS`，表不存在则自动创建
3. **字段补全**：检查字段是否存在，不存在则 `ALTER TABLE ADD COLUMN`
4. **安装锁定**：`install.lock` 文件标记安装完成

这种策略确保：

- 零手动初始化：首次访问自动完成所有数据库设置
- 升级友好：新增字段时自动补全，不破坏旧数据
- 可重置：删除 `install.lock` 可重新执行安装流程

---

## 6. API 接口体系

### 6.1 API 设计规范

**统一响应格式：**

```json
// 成功
{
  "code": 0,
  "msg": "success",
  "data": { ... }
}

// 失败
{
  "code": 400,
  "msg": "错误信息",
  "data": null
}
```

**通用错误码：**

| code | 说明 |
|------|------|
| 0 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未登录 / Token 无效 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 429 | 请求过于频繁（限流） |
| 500 | 服务器内部错误 |
| 503 | 系统未初始化 |

### 6.2 认证机制

**Token 认证流程：**

```
客户端请求 → 携带 Token（Header/Body/Cookie 任一）
           ↓
服务端解析（优先级：JSON Body > POST > Header > Cookie）
           ↓
verifyToken() 验证 Token 有效性
           ↓
检查过期时间 + 密码修改时间（密码修改后 Token 自动失效）
           ↓
返回用户 ID 或 null
```

**Token 获取方式（优先级）：**

1. JSON body: `{"_token": "xxx"}`
2. 表单 POST: `_token=xxx`
3. HTTP Header: `Authorization: Bearer xxx` 或 `X-Token: xxx`
4. Cookie: `admin_token`

### 6.3 核心 API 列表

#### 用户管理 API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `/api/login` | POST | 否 | 用户登录 |
| `/api/register` | POST | 否 | 用户注册 |
| `/api/logout` | POST | 是 | 登出 |
| `/api/list` | POST | 是 | 用户列表（分页/排序/筛选） |
| `/api/update` | POST | 是 | 更新用户 |
| `/api/delete` | POST | 是 | 删除用户 |
| `/api/batch_delete` | POST | 是 | 批量删除 |
| `/api/stats` | POST | 是 | 用户统计 |
| `/api/export` | POST | 是 | 导出数据 |
| `/api/import` | POST | 是 | 导入数据 |
| `/api/migrate_export` | POST | 是 | 数据迁移导出 |
| `/api/migrate_import` | POST | 是 | 数据迁移导入 |

#### 系统 API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `/api/home` | GET | 否 | 前台首页数据 |
| `/api/admin_logs` | POST | 是 | 操作日志 |
| `/api/csrf_token` | GET | 否 | 获取 CSRF Token |
| `/api/validate_token` | POST | 是 | 验证 Token |
| `/api/update_token_expiry` | POST | 是 | 续期 Token |
| `/api/search_history` | GET | 是 | 搜索历史 |
| `/api/ip_ban_toggle` | POST | 是 | IP 封禁开关 |
| `/api/template_settings` | GET/POST | 是 | 模板设置 |

#### 文章 API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `/article/api/list` | POST | 可选 | 文章列表 |
| `/article/api/create` | POST | 是 | 创建文章 |
| `/article/api/update` | POST | 是 | 更新文章 |
| `/article/api/delete` | POST | 是 | 删除文章 |
| `/article/api/view` | POST | 否 | 浏览计数 |
| `/article/api/favorites` | POST | 是 | 收藏列表 |
| `/article/api/favorite` | POST | 是 | 收藏/取消收藏 |
| `/article/api/favorite_status` | POST | 是 | 收藏状态 |
| `/article/api/favorites_stats` | GET | 否 | 收藏统计 |

#### 软件 API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `/software/api/list` | POST | 可选 | 软件列表 |
| `/software/api/create` | POST | 是 | 创建软件 |
| `/software/api/update` | POST | 是 | 更新软件 |
| `/software/api/delete` | POST | 是 | 删除软件 |
| `/software/api/status` | POST | 是 | 上下架 |
| `/software/api/categories` | POST | 是 | 分类管理 |

#### 搜索 API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `/search/api/list` | GET/POST | 否 | 全局搜索 |

#### 后台管理 API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `/admin/api/columns` | POST | 是 | 栏目管理 |
| `/admin/api/tag_list` | GET | 是 | 标签列表 |
| `/admin/api/upload_image` | POST | 是 | 图片上传 |
| `/admin/api/search_history` | GET | 是 | 搜索历史 |
| `/admin/api/template_settings` | GET/POST | 是 | 模板设置 |
| `/admin/api/check_setup` | GET | 否 | 安装状态检查 |
| `/admin/api/save_setup` | POST | 否 | 保存安装配置 |
| `/admin/api/test_connection` | POST | 否 | 数据库连接测试 |

---

## 7. 标签模板系统

### 7.1 标签系统架构

标签系统是本 CMS 的核心特色之一，允许在 HTML 模板中通过类似 `[--tag_name--]` 的语法嵌入动态内容。

**工作流程：**

```
模板 HTML（含 [--tag--] 占位符）
    ↓
TagHook::render() 入口
    ↓
检查编译缓存（TagCache）
    ↓
TagParser::tokenize() — 词法分析
    ↓
TagParser::buildAST() — 构建 AST
    ↓
TagCompiler::compile() — 编译为 PHP 代码
    ↓
写入缓存文件
    ↓
执行编译后的 PHP 代码 → 输出 HTML
```

**安全特性：**

- 编译后的 PHP 代码缓存到 `storage/cache/tags/`
- 每次请求通过 `include` 而非 `eval` 执行（更安全的错误处理）
- 执行后自动删除临时文件

### 7.2 内置标签一览

#### 系统标签

| 标签 | 语法 | 说明 |
|------|------|------|
| 站点名称 | `[--site_name--]` | 从数据库读取站点名称 |
| 站点 URL | `[--site_url--]` | 完整站点 URL（协议+域名+路径） |
| 当前年份 | `[--current_year--]` | 输出当前年份 |
| 当前日期 | `[--current_date--]` 或 `[--current_date(format=Y年m月d日)--]` | 输出当前日期 |
| 读取配置 | `[--config(key=site_name)--]` | 读取任意配置项 |
| 搜索表单 | `[--search_form--]` | GET 搜索表单 |
| 登录表单 | `[--login_form--]` | POST 登录表单 |
| 引入文件 | `[--include(name=header)--]` | 引入公共模板文件 |

#### 内容标签（文章）

| 标签 | 语法 | 说明 |
|------|------|------|
| 文章循环 | `[--loop:articles(num=10)--]...[--/loop:articles--]` | 可用变量：title/url/summary/category/view_count/published_at/cover_image |
| 文章列表 | `[--article_list(num=10)--]` | 直接输出 HTML 列表 |
| 文章详情 | `[--loop:article_detail--]...[--/loop:article_detail--]` | 需配合文章详情页 |
| 相关文章 | `[--loop:related_articles(num=5)--]...[--/loop:related_articles--]` | 同分类推荐 |

#### 内容标签（软件）

| 标签 | 语法 | 说明 |
|------|------|------|
| 软件循环 | `[--loop:software(num=20)--]...[--/loop:software--]` | 可用变量：name/url/summary/version/category/view_count/download_count |
| 软件列表 | `[--software_list(num=6)--]` | 直接输出 HTML 卡片 |
| 软件详情 | `[--loop:software_detail--]...[--/loop:software_detail--]` | 需配合软件详情页 |

#### 分类标签

| 标签 | 语法 | 说明 |
|------|------|------|
| 分类循环 | `[--loop:categories--]...[--/loop:categories--]` | 可用变量：name/url/cnt |
| 分类导航 | `[--category_nav--]` | 直接输出分类导航 |
| 栏目循环 | `[--loop:columns(pid=0)--]...[--/loop:columns--]` | 树形栏目 |
| 栏目信息 | `[--column_info--]` | 读取 URL ?col=ID 对应的栏目名 |

#### 导航标签

| 标签 | 语法 | 说明 |
|------|------|------|
| 面包屑 | `[--breadcrumb--]` | 根据 URL 自动生成 |
| 分页导航 | `[--pagination(total=50,per_page=10,page=1,url=?)--]` | 分页组件 |
| 首页轮播 | `[--carousel(num=5)--]` | 返回 JSON 数据 |
| 栏目树 | `[--column_tree--]` 或 `[--column_tree(pid=0)--]` | 自动递归所有层级 |

### 7.3 标签注册机制

任何模块可通过 `TagRegistry::register()` 注册自己的标签处理器：

```php
TagRegistry::register(
    'my_tag',           // 标签名
    'tag_my_tag',       // 处理函数名
    '标签说明',          // 帮助文本
    'content'           // 分类：content/category/system/navigation
);
```

---

## 8. 安全体系

### 8.1 多层安全防御

```
┌─────────────────────────────────────────────────┐
│ 第一层：传输层                                    │
│  HTTPS 全站加密（生产环境强制）                   │
│  Secure Cookie（仅 HTTPS 传输）                   │
└────────────────┬────────────────────────────────┘
                 ↓
┌────────────────▼────────────────────────────────┐
│ 第二层：认证层                                    │
│  Token 认证（7天 / 30天有效期）                   │
│  密码修改后 Token 自动失效                         │
│  多 Token 支持（多设备同时在线）                   │
│  IP 绑定（可选）                                 │
└────────────────┬────────────────────────────────┘
                 ↓
┌────────────────▼────────────────────────────────┐
│ 第三层：CSRF 防御                                 │
│  Double Submit Cookie 模式                        │
│  Header Token + Cookie Token 双重验证              │
│  hash_equals() 恒定时间比较（防时序攻击）          │
│  Origin/Referer 兜底检查                          │
└────────────────┬────────────────────────────────┘
                 ↓
┌────────────────▼────────────────────────────────┐
│ 第四层：防暴力破解                                │
│  登录频率限制（文件锁 + 全局限流）                 │
│  连续失败自动封禁（默认 3 次失败 / 30 分钟封禁）   │
│  IP 封禁开关（可后台关闭）                        │
│  限流 API（60 次 / 小时 / 普通操作）              │
└────────────────┬────────────────────────────────┘
                 ↓
┌────────────────▼────────────────────────────────┐
│ 第五层：输入安全                                  │
│  参数化查询（PDO prepare）                        │
│  HTML 转义（htmlspecialchars）                   │
│  路径安全（realpath + 白名单）                    │
│  文件名净化（白名单扩展名）                        │
└────────────────┬────────────────────────────────┘
                 ↓
┌────────────────▼────────────────────────────────┐
│ 第六层：输出安全                                  │
│  安全响应头（CSP / X-Frame-Options / XCTO）      │
│  Content-Type sniffing 防护                       │
│  XSS 过滤（strip_tags + 正则净化）               │
└────────────────┬────────────────────────────────┘
                 ↓
┌────────────────▼────────────────────────────────┐
│ 第七层：上传安全                                  │
│  扩展名白名单（jpg/png/gif/webp/bmp）             │
│  MIME 类型校验（finfo_file）                      │
│  文件大小限制（5MB）                              │
│  文件重命名（时间戳+随机数）                       │
│  按年月分目录存储                                  │
│  禁止 PHP 文件执行                                │
└─────────────────────────────────────────────────┘
```

### 8.2 密码安全

- 使用 PHP `password_hash()` / `password_verify()`（bcrypt）
- 密码修改时间记录到 `password_changed_at`
- Token 创建时间与 `password_changed_at` 对比，旧 Token 自动失效

### 8.3 IP 安全

- 仅在可信代理（127.0.0.1/localhost）下读取 `X-Forwarded-For`
- 其他情况直接使用 `REMOTE_ADDR`
- 防止客户端伪造 IP

### 8.4 安全响应头

所有 API 响应自动设置：

```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Cache-Control: no-store, no-cache, must-revalidate
X-Powered-By: UserSys
Content-Security-Policy: ...
```

---

## 9. 安装与部署

### 9.1 环境要求

| 项目 | 最低要求 | 推荐 |
|------|---------|------|
| PHP | 5.3+ | 7.3+ / 8.0+ |
| MySQL | 5.5+ | 5.7+ / 8.0+ |
| Apache | 2.2+ (mod_rewrite) | 2.4+ |
| Nginx | 1.10+ | 1.20+ |
| 内存 | 128MB | 256MB+ |
| Disk | 50MB | 100MB+ |

### 9.2 安装流程

**方式一：通过后台安装向导**

1. 访问 `/admin.php?force_setup=1`
2. 填写数据库配置
3. 系统自动探测 MySQL root 密码（支持常见环境）
4. 创建数据库和表
5. 设置管理员账号
6. 生成 `install.config.php` 和 `install.lock`

**方式二：手动配置**

1. 复制 `install/install.config.example.php` 为 `install/install.config.php`
2. 填写数据库信息
3. 访问 `/admin.php?force_setup=1` 完成安装
4. 系统自动创建所有表

### 9.3 数据库配置优先级

```
install.config.php > .env > config/db.php 默认值
```

### 9.4 部署到子目录

**Apache (.htaccess)：**

`.htaccess` 已包含 URL 重写规则，无需额外配置。

**Nginx：**

修改 `nginx.conf` 中的 `try_files`：

```nginx
# 根目录
try_files $uri $uri/ /index.php?$query_string;

# 子目录 /pro/
try_files $uri $uri/ /pro/index.php?$query_string;
```

### 9.5 默认账号

| 账号 | 密码 | 角色 |
|------|------|------|
| admin | admin123 | 超级管理员 |

> ⚠️ 首次登录后立即修改默认密码。

---

## 10. 运维工具

### 10.1 重置管理员密码

访问 `/reset_admin.php`，通过以下任一方式认证：

1. **本地访问**：从 localhost/127.0.0.1 访问
2. **Token 认证**：携带有效管理员 Token
3. **Secret 认证**：在 `.env` 配置 `RESET_SECRET=xxx`，访问时加 `?secret=xxx`

功能：

- 输入用户名 + 新密码
- 用户不存在时自动创建（默认超级管理员）
- 更新密码后使所有旧 Token 失效

### 10.2 全量重置系统

访问 `/reset_all.php`，提供两种重置模式：

| 模式 | 参数 | 说明 |
|------|------|------|
| 软重置 | `?step=1` | 仅删除 install.lock，保留数据库配置 |
| 完全重置 | `?all=1` | 删除 install.lock + install.config.php，清空所有数据 |

### 10.3 IP 封禁急救

访问 `/clear_ban.php`，可清除：

1. 当前 IP 的登录封禁记录
2. 当前 IP 的频率限制记录
3. 查看 IP 封禁开关状态

### 10.4 索引优化

访问 `/performance/optimize_indexes.php`，可分析并优化数据库索引。

---

## 11. 前端页面体系

### 11.1 页面架构

本系统采用**多模板 + SPA** 混合架构：

```
┌──────────────────────────────────────────┐
│ 前台页面（静态 HTML + Fetch API）          │
│  templates/v4/*.html                      │
│  templates/v1/*.html                      │
│  frontend/*.html（后备）                   │
└────────────────┬─────────────────────────┘
                 │ Fetch API
┌────────────────▼─────────────────────────┐
│ 后台管理（SPA 单页应用）                   │
│  admin/index.html（HTML + 内联 JS）        │
└──────────────────────────────────────────┘
```

### 11.2 前台页面列表

| 页面 | 路由 | 说明 |
|------|------|------|
| 首页 | `/` | 展示最新文章和软件 |
| 文章列表 | `/article-list` | 分页列表，支持分类筛选 |
| 文章详情 | `/article/p/:id` | 文章完整内容 |
| 软件列表 | `/software-list` | 软件卡片展示 |
| 软件详情 | `/software/p/:id` | 软件详细信息 |
| 搜索页 | `/search` | 全局搜索 |
| 收藏页 | `/favorites` | 用户收藏（需登录） |
| 登录页 | `/login` | 前台登录 |
| 标签文档 | `/tag-doc` | 标签系统使用文档 |

### 11.3 后台管理功能

后台 SPA (`admin/index.html`) 提供：

| 模块 | 功能 |
|------|------|
| 用户管理 | 列表 / 创建 / 编辑 / 删除 / 批量删除 / 导出 / 导入 |
| 文章管理 | 列表 / 创建 / 编辑 / 删除 / 收藏管理 |
| 软件管理 | 列表 / 创建 / 编辑 / 删除 / 分类管理 / 上下架 |
| 栏目管理 | 树形列表 / 创建 / 编辑 / 删除 |
| 模板设置 | 模板切换 / 站点信息配置 |
| 操作日志 | 分页查看 / 筛选（按类型/时间/管理员） |
| 搜索历史 | 查看搜索关键词统计 |
| 附件管理 | 图片上传（TinyMCE 集成） |
| 系统急救 | IP 封禁开关 / 解封 |

---

## 12. 模块化设计

### 12.1 模块独立性

每个功能模块（文章/软件/搜索）均为独立子模块：

```
模块/
├── config.php    # 数据库初始化 + API 函数封装
├── index.html    # 模块管理页面
├── api/          # 模块 API
│   ├── list.php
│   ├── create.php
│   ├── update.php
│   └── delete.php
└── assets/       # 模块静态资源
```

**独立性保障：**

- `config.php` 通过 `if (!function_exists(...))` 包装函数，支持重复加载
- 模块 API 通过 `require_once` 加载主配置，不会重复初始化
- 每个模块维护自己的表结构，通过 `CREATE TABLE IF NOT EXISTS` 确保存在

### 12.2 插件扩展机制

系统预留了插件扩展接口：

```php
// 注册自定义标签
TagRegistry::register('my_plugin_tag', 'tag_my_plugin', '说明', 'content');

// 通过 Hook 扩展生命周期
// （系统预留 Hook 机制，版本迭代中完善）
```

### 12.3 API 函数封装

各模块在 `config.php` 中提供 API 封装函数：

```php
// 文章模块
art_initDatabase($pdo);

// 软件模块
sw_requireAdmin();         // 鉴权
sw_validateCSRF();         // CSRF 验证
swBuildWhere($input);      // WHERE 构造
initSoftwareTables();      // 初始化表

// 搜索模块
getSearchInput();          // 获取搜索参数
search_getSources();       // 获取搜索数据源配置
```

---

## 13. 性能优化

### 13.1 数据库优化

**索引策略：**

- 所有主键自动索引
- 外键字段添加索引（如 `user_id`、`category_id`）
- 频繁查询字段组合索引（如 `status + published_at + created_at`）
- 唯一约束防止重复（如 `username`、`config_key`）

**查询优化：**

- 参数化查询（防注入 + 查询计划缓存）
- 分页查询（`LIMIT + OFFSET`）
- COUNT 预计算（避免 `SQL_CALC_FOUND_ROWS`）
- 避免 `SELECT *`（明确指定字段）

### 13.2 缓存策略

| 缓存类型 | 存储位置 | 说明 |
|---------|---------|------|
| 标签编译缓存 | `storage/cache/tags/` | 编译后的 PHP 代码，按模板文件缓存 |
| 限流记录 | `storage/runtime/` | JSON 文件，24小时自动清理 |
| 上传文件 | `storage/uploads/` | 按年月分目录 |

**缓存清理策略：**

- 标签缓存：修改模板文件时自动失效
- 限流缓存：概率性清理（1% 概率检查，保留 24 小时）
- 安装时清理旧缓存

### 13.3 限流保护

**双层限流：**

1. **单 IP 限流**：文件锁 + 计数器，防止单 IP 暴力破解
2. **全局动作限流**：文件锁 + 计数器，防止 IP 池轮换攻击

**默认限流规则：**

| 动作 | 单 IP 限制 | 全局限制 | 封禁时间 |
|------|-----------|---------|---------|
| 登录 | 30 次/小时 | 300 次/小时 | 30 分钟 |
| 列表查询 | 60 次/小时 | — | — |
| 搜索 | 30 次/小时 | 300 次/小时 | — |

---

## 14. 开发规范

### 14.1 PHP 编码规范

**必须遵循：**

- 兼容 PHP 5.3+（禁止 namespace/trait/yield/箭头函数等）
- 使用传统函数式写法
- 禁止 scalar type hints / return type / nullable type
- 函数使用 `@param` / `@return` 文档注释
- 变量和函数名使用驼峰式或下划线式
- 常量使用全大写下划线分隔

**命名规范：**

```php
// 类名（无 namespace）：TagRegistry / TagParser
// 函数名：getDB() / verifyToken() / createToken()
// 常量：DB_PREFIX / TOKEN_EXPIRY_SECONDS / DB_CHARSET
// 变量：$pdo / $adminId / $token / $input
// 配置数组键：snake_case（config_key）
```

### 14.2 SQL 规范

```php
// ✅ 正确：参数绑定
$stmt = $pdo->prepare("SELECT * FROM {$prefix}users WHERE id = :id");
$stmt->execute([':id' => $userId]);

// ❌ 错误：字符串拼接
$sql = "SELECT * FROM {$prefix}users WHERE id = " . $userId;

// ✅ 正确：明确字段
SELECT id, username, created_at FROM ...

// ❌ 错误：全字段
SELECT * FROM ...
```

### 14.3 安全规范

| 要求 | 说明 |
|------|------|
| 所有输入不可信 | 必须验证/过滤/转义 |
| 密码必须 hash | 使用 `password_hash()` |
| SQL 必须参数化 | 禁止字符串拼接 |
| XSS 必须转义 | `htmlspecialchars()` |
| 文件上传必须校验 | 扩展名 + MIME + 大小 |
| 高危操作必须日志 | 删除/修改/上传/登录 |

### 14.4 Git 提交规范

```
feat:     新功能
fix:      Bug 修复
security: 安全修复
refactor: 重构（不改变功能）
perf:     性能优化
docs:     文档更新
```

---

## 15. 常见问题

### Q1：访问页面显示 404

**原因：** Nginx/Apache URL 重写未配置或配置错误。

**解决：**

- Nginx：确保 `nginx.conf` 中的 `try_files` 指向 `index.php`
- Apache：确保 `.htaccess` 文件存在且 `mod_rewrite` 已启用
- MAMP：直接访问 `.php` 文件，不依赖 URL 重写

### Q2：数据库连接失败

**原因：** 数据库配置错误或 MySQL 服务未启动。

**解决：**

1. 检查 `.env` 或 `install.config.php` 中的数据库配置
2. 确认 MySQL 服务已启动（MAMP 中确保 MySQL 运行）
3. 确认数据库用户有访问权限
4. 尝试修改 `config/db.php` 中的默认端口（MAMP 默认 8889）

### Q3：登录报错 "Unexpected token"

**原因：** PHP 文件未被执行，返回了 HTML 404 页面。

**解决：**

1. 检查 `location ~ \.php$` 配置是否正确
2. 确认 `fastcgi_pass` 路径与 PHP 版本匹配
3. 重载 Nginx：`nginx -s reload`

### Q4：登录显示空白页

**原因：** PHP 语法错误或文件权限不足。

**解决：**

1. 检查 PHP 错误日志
2. 确认 `storage/runtime/` 目录可写
3. 检查文件编码（确保 UTF-8 无 BOM）

### Q5：子目录部署路径错误

**原因：** `BASE_PATH` 未正确检测。

**解决：**

1. 确保通过 `admin.php` / `article.php` 等入口文件访问（而非通过 index.php 路由）
2. 入口文件已自动检测 `SCRIPT_NAME` 并计算 `BASE_PATH`
3. 后台 API 请求确保携带正确的 `BASE_PATH` 前缀

### Q6：忘记后台登录密码

**解决：**

1. 访问 `/reset_admin.php`，输入用户名和新密码
2. 或通过 phpMyAdmin 打开 `sys_users` 表手动修改
3. 或删除 `install.lock`，重新执行安装向导

### Q7：IP 被封禁无法登录

**解决：**

1. 从本地网络访问（localhost/127.0.0.1）
2. 访问 `/clear_ban.php` 解封
3. 或修改 `storage/runtime/login_ban_*.json` 文件

### Q8：上传图片失败

**原因：** 目录无写权限。

**解决：**

```bash
chmod -R 755 storage/
chmod -R 755 storage/runtime/
chmod -R 755 storage/cache/
chmod -R 755 storage/uploads/
```

### Q9：模板修改后不生效

**原因：** 标签编译缓存未失效。

**解决：**

1. 删除 `storage/cache/tags/` 目录下的缓存文件
2. 或访问后台清理缓存

### Q10：如何切换前台模板

**解决：**

1. 访问后台 → 模板设置
2. 或直接修改数据库 `sys_config` 表：

```sql
UPDATE sys_config SET config_value = 'v1' WHERE config_key = 'frontend_template';
```

---

## 附录

### A. 文件清单（73 个 PHP 文件）

**核心系统（9个）：**
`index.php` / `admin.php` / `login.php` / `article.php` / `reset_admin.php` / `reset_all.php` / `clear_ban.php` / `config/db.php` / `includes/auth.php`

**核心 API（22个）：**
`api/` 目录下的所有 .php 文件

**后台 API（9个）：**
`admin/api/` 目录下的所有 .php 文件

**文章模块（11个）：**
`article/` 目录下的所有 .php 文件

**软件模块（6个）：**
`software/api/` 目录下的所有 .php 文件

**搜索模块（2个）：**
`search/` 目录下的所有 .php 文件

**标签系统（6个）：**
`module/tags/` 目录下的所有 .php 文件

**安装系统（3个）：**
`install/` 目录下的 .php 文件

**其他（5个）：**
`performance/optimize_indexes.php` / `includes/mysql_install_helper.php` / `admin/index.php` / `install/reset.php` / `module/tags/config.php`

### B. 数据库表清单

| 表名 | 说明 | 前缀 |
|------|------|------|
| `sys_users` | 用户/管理员表 | ✅ |
| `sys_admin_logs` | 操作日志表 | ✅ |
| `sys_user_tokens` | Token 表 | ✅ |
| `sys_config` | 配置表 | ✅ |
| `sys_columns` | 栏目表 | ✅ |
| `sys_software` | 软件表 | ✅ |
| `sys_software_categories` | 软件分类表 | ✅ |
| `articles` | 文章表 | ❌ |
| `article_favorites` | 文章收藏表 | ❌ |

### C. 安全检查清单

- [ ] 修改默认管理员密码
- [ ] 启用 HTTPS（生产环境）
- [ ] 删除 `/reset_admin.php`（重置后）
- [ ] 删除 `/reset_all.php`（重置后）
- [ ] 确认 `.env` 不上传到代码仓库
- [ ] 确认 `install.lock` 存在（防止重复安装）
- [ ] 确认 `storage/` 目录不可从 Web 访问
- [ ] 定期查看操作日志
- [ ] 定期备份数据库

---

*本文档由 AI 辅助生成，如有疑问请参考 `CLAUDE.md` 规则文件。*
