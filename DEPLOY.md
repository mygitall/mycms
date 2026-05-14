# 开箱即用部署指南

## 一、上传前准备

### 1. 修改数据库配置
编辑 `.env` 文件，填写你的宝塔虚拟主机数据库信息：

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=你的数据库名
DB_USER=数据库用户名
DB_PASS=数据库密码
```

### 2. 确认上传位置

**方法 A（推荐）**：把整个 `pro` 文件夹里的内容**直接放到网站根目录**
```
/www/wwwroot/你的域名/
├── admin/
├── api/
├── article/
├── config/
├── storage/
├── admin.php        ← 确保这个文件在根目录
├── api.php          ← 确保这个文件在根目录
├── index.php
├── setup.php
└── ...
```
访问地址：`http://你的域名/admin.php`

**方法 B**：放到子目录 `pro/`
```
/www/wwwroot/你的域名/pro/
├── admin/
├── api/
├── ...
```
访问地址：`http://你的域名/pro/admin.php`

> ⚠️ 上传后用宝塔**文件管理器**确认文件确实在正确位置。

---

## 二、初始化数据库

访问 `http://你的域名/setup.php`，按提示完成数据库初始化。

---

## 三、访问地址汇总

| 页面 | 地址 |
|------|------|
| 后台管理 | `http://你的域名/admin.php` |
| 文章管理 | `http://你的域名/article.php` |
| 初始化向导 | `http://你的域名/setup.php` |
| 前台首页 | `http://你的域名/` |

> 💡 `admin.php`、`api.php`、`article.php` 是直接入口文件，**不需要修改任何 Nginx 配置**。

---

## 四、常见问题

### 404 Not Found
- 确认文件上传到了网站根目录（不是上级目录）
- 如果放到了子目录，访问地址要加目录名，如 `/pro/admin.php`
- 宝塔面板 → 网站 → 确认网站的运行目录是否正确

### 500 Internal Server Error
- 检查 `.env` 数据库配置是否正确
- 检查 PHP 版本（推荐 PHP 7.4+ 或 8.2）

### API 请求失败 (404)
- 确保虚拟主机开启了 PHP 支持
- 宝塔面板 → 网站 → 设置 → PHP版本，确认已选择 PHP 版本
