### 用户管理系统

**技术栈：PHP 7.3+ / MySQL 5.7 / Nginx（支持 phpStudy 本地 + 宝塔远程）**

> 核心特性：路径自适应——无论部署在 `/`、`/pro/`、`/wei/` 或任意子目录，都能开箱即用。

---

#### 一、文件结构

```
pro/
├── admin/index.html          # 后台管理界面（唯一前端入口）
├── api/                      # API 接口（无需直接访问）
│   ├── login.php             # 登录
│   ├── register.php         # 注册
│   ├── list.php             # 用户列表
│   ├── update.php           # 编辑用户
│   ├── batch_delete.php     # 删除用户
│   ├── update_token_expiry.php
│   ├── stats.php            # 统计
│   ├── admin_logs.php       # 操作日志
│   ├── export.php           # 导出
│   ├── import.php           # 导入
│   ├── migrate_export.php  # 数据迁移导出
│   ├── migrate_import.php  # 数据迁移导入
│   ├── csrf_token.php      # CSRF Token
│   └── validate_token.php  # Token 验证
├── config/
│   └── db.php               # 数据库连接（自动建库建表）
├── storage/                  # 静态文件存储
├── index.php                # 前端控制器（路由入口，Nginx 环境用）
├── router.php               # PHP 内置服务器路由（本地开发用）
├── setup.php                # 初始化脚本（自动建库建表）
├── nginx.conf               # Nginx 配置模板
├── .env                     # 环境配置（不上传仓库）
└── .env.example             # 配置模板
```

---

#### 二、本地开发（不需要 Nginx/phpStudy）

使用 PHP 内置服务器，不需要改任何配置：

**第一步：启动服务器**

打开终端，进入项目目录并启动：

```bash
cd /Applications/phpstudy/WWW/pro
php -S localhost:8080 router.php
```

**第二步：访问**

```
http://localhost:8080/           → 后台管理
http://localhost:8080/api/login  → 登录 API
http://localhost:8080/setup.php  → 初始化向导
```

> 如果 8080 端口被占用，换一个：`php -S localhost:9090 router.php`

---

#### 三、宝塔/生产环境部署

宝塔/phpStudy 管理面板 → 数据库 → 添加数据库

| 项目 | 值 |
|------|-----|
| 数据库名 | `gao367888125` |
| 用户名 | `gao367888125` |
| 密码 | `c6f57i8j` |
| 编码 | `utf8mb4` |

**第二步：上传并绑定域名**

1. 上传 `pro` 文件夹到网站根目录
2. 宝塔 → 网站 → 添加站点 → 绑定域名 → PHP版本选 7.3+
3. 根目录选择到 pro 文件夹的**上一级目录**

**第三步：配置 Nginx**

1. 宝塔 → 网站 → 找到你的站点 → 设置 → 配置文件
2. 找到 `location / { ... }` 块
3. 打开项目的 `nginx.conf`，复制里面的配置，粘贴进去
4. 把 `try_files` 里的 `/pro/index.php` 改成你的实际子目录名
5. 找到 `location ~ \.php$ { ... }` 块，把 `fastcgi_pass` socket 路径改成你 PHP 版本对应的路径：

```nginx
# PHP 7.4
fastcgi_pass unix:/tmp/php-cgi-7.4.sock;

# PHP 8.2
fastcgi_pass unix:/tmp/php-cgi-8.2.sock;
```

6. 保存 → 重载 Nginx

> **怎么查 PHP socket 路径？**
> 宝塔：软件商店 → PHP-7.4 → 设置 → 配置 → 搜索 `socket`
> 或者用命令：`find / -name "php-cgi*.sock" 2>/dev/null`

**第四步：初始化**

访问：`http://你的域名/setup.php`

系统会自动：
- 检测数据库连接
- 自动建库建表
- 自动创建管理员账号 `admin / admin123`

**第五步：访问后台**

访问：`http://你的域名/`（或 `http://你的域名/pro/`）

默认账号：`admin` / `admin123`

---

#### 三、部署方式

**方式一：项目在网站根目录**（最简单）

```
网站根目录/
├── index.html
├── css/
├── js/
└── ...你的其他文件...
```

→ 配置 Nginx `try_files` 为 `/index.php`

**方式二：项目在子目录**

```
网站根目录/
├── pro/
│   ├── index.php      ← try_files 指向这里
│   ├── admin/
│   ├── api/
│   └── ...
└── 其他文件
```

→ 配置 Nginx `try_files` 为 `/pro/index.php`

---

#### 四、Nginx 配置关键说明

核心就��两个 `location`：

```nginx
# 1. 把请求路由到 index.php
location / {
    try_files $uri $uri/ /pro/index.php?$query_string;
}

# 2. PHP 文件交给 PHP-FPM 处理
location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi-8.2.sock;  # 改这里！
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    # 重要：把 HTTP 头透传给 PHP（CSRF Token 依赖）
    fastcgi_param HTTP_X_CSRF_TOKEN $http_x_csrf_token;
    fastcgi_param HTTP_X_TOKEN $http_x_token;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
}
```

---

#### 五、安全建议

- ✅ 首次登录后立刻修改默认管理员密码
- ✅ 删除 `setup.php`（或用密码保护）
- ✅ 配置 SSL（宝塔 → 网站 → SSL）
- ✅ `.env` 不上传代码仓库

---

#### 六、常见问题

**Q：数据库连接失败？**
- 检查 MySQL 是否启动
- 检查 `.env` 中数据库名/用户名/密码是否正确

**Q：404 Not Found？**
- Nginx 配置中 `try_files` 的目标路径要和实际目录匹配
- 重载 Nginx 后生效：`nginx -s reload`

**Q：登录报错 "Unexpected token '<'"？**
- PHP 文件没有被执行，返回了 HTML 404 页面
- 检查 `location ~ \.php$` 配置是否正确添加

**Q：本地和远程部署路径不同？**
- 已自动适配，无需修改代码。`index.php` 会根据访问路径自动计算 `BASE_PATH`
