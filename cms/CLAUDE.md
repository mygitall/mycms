# CLAUDE.md

# MYCMS — AI 工程规则（高安全 / 高稳定 CMS）

本文件用于约束 Claude Code 在本项目中的行为。

核心目标：

- 安全性优先
- 稳定性优先
- 兼容性优先
- 可运行优先
- 低维护成本优先

禁止为了“高级感”进行复杂化设计。

---

# 项目概述

MYCMS 是一个：

- PHP 5.3+ / MySQL
- 无框架
- 类帝国 CMS
- 支持用户管理
- 支持文章 / 软件 / 文模块
- 支持子目录部署
- 支持共享主机
- 适合宝塔 / MAMP / 轻量服务器

核心特点：

- 路径自适应
- 无框架低依赖
- 前后端分离
- 安全优先
- 稳定优先

---

# 核心架构

```text
├── index.php              # 前端控制器（rewrite模式）
├── admin.php              # 后台入口
├── article.php            # 文章模块入口
├── login.php              # 登录页

├── config/
│   └── db.php             # 核心安全系统（高危文件）

├── api/                   # 用户系统 API
├── article/               # 文章模块
├── software/              # 软件模块
├── search/                # 搜索模块
├── wen/                   # 静态模块

├── install/               # 安装系统
├── storage/runtime/       # 限流 / 封禁状态

├── reset_admin.php        # 重置管理员
├── reset_all.php          # 系统重置
└── clear_ban.php          # 封禁解除