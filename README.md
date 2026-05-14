# 资源目录管理系统

前端静态页面 + PHP + MySQL 后端 API，实现目录和留言本的增删改查。

## 目录结构

```
/Applications/MAMP/htdocs/
├── index.php           # 前端页面
├── config.php          # 数据库配置文件
├── api/
│   ├── index.php       # API 入口（增删改查）
│   ├── db.php          # 数据库连接与初始化
│   ├── upload.php      # 文件上传接口
│   └── init.php        # 数据库初始化脚本（含初始数据）
├── admin/
│   └── index.php       # 后台管理页面
├── uploads/            # 上传文件存储目录
└── data/
    └── backups/        # 数据库备份目录
```

## 快速开始

### 1. 配置数据库

编辑 `config.php`，填写 MySQL 连接信息：

```php
return [
    'type'  => 'mysql',
    'mysql' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'your_db_name',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset'  => 'utf8mb4',
    ],
];
```

### 2. 初始化数据库

首次使用，访问以下地址初始化数据库表、管理员账号和初始数据：

```
http://localhost/api/init.php
```

成功后会输出 `[OK] 数据库初始化完成！`，表示所有表和初始数据已就绪。

默认管理员密码会在初始化时随机生成并显示，请妥善保存。

### 3. 访问首页

```
http://localhost/
```

## 功能说明

### 目录管理
- 新增目录（普通目录 / 游客上传区）
- 编辑目录名称和类型
- 删除目录（同时删除目录下所有文件）
- 目录下可添加文件（支持多下载链接）
- 文件支持编辑名称、链接和删除
- 文件上传（支持拖拽上传）

### 留言本管理
- 新增留言（输入验证码 + 内容）
- 编辑留言
- 删除留言
- 点击验证码自动填入

## API 接口

| 方法 | URL | 说明 |
|------|-----|------|
| GET | `api/index.php` | 获取所有目录和留言 |
| GET | `api/index.php?type=dirs` | 获取目录列表 |
| GET | `api/index.php?type=messages` | 获取留言列表 |
| POST | `api/index.php?action=dir` | 新增目录 |
| POST | `api/index.php?action=file_create` | 新增文件 |
| POST | `api/index.php?action=message` | 新增留言 |
| PUT | `api/index.php?action=dir` | 编辑目录 |
| PUT | `api/index.php?action=file` | 编辑文件 |
| PUT | `api/index.php?action=message` | 编辑留言 |
| DELETE | `api/index.php?action=dir&id=1` | 删除目录 |
| DELETE | `api/index.php?action=file&dir_id=1&file_name=xxx` | 删除文件 |
| DELETE | `api/index.php?action=message&id=1` | 删除留言 |

## 数据存储

使用 MySQL 数据库，配置文件位于 `config.php`。

## 注意事项

- 后端依赖 PHP 环境，需开启 PDO 和 PDO_MySQL 扩展
- MAMP 默认已开启这些扩展
- API 路径为 `/api/index.php`
- 上传文件存储在 `uploads/` 目录
