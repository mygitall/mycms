<?php
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: /install.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>资源目录 - 界面还原</title>
    <script>
        // Prevent zoom on iOS Safari
        document.documentElement.addEventListener('gesturestart', function(e) { e.preventDefault(); }, { passive: false });
        document.documentElement.addEventListener('gesturechange', function(e) { e.preventDefault(); }, { passive: false });
        document.documentElement.addEventListener('gestureend', function(e) { e.preventDefault(); }, { passive: false });
        document.documentElement.addEventListener('touchstart', function(e) {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
        document.documentElement.addEventListener('touchmove', function(e) {
            if (e.scale !== 1) e.preventDefault();
        }, { passive: true });
    </script>
    <style>
        :root {
            --bg-color: #f5f5f0;
            --border-color: #c0c0c0;
            --text-color: #333333;
            --link-color: #0000cc;
            --header-bg: #e8e8e0;
            --table-border: #a0a0a0;
            --tag-bg: #f0f0e8;
            --input-bg: #ffffff;
            --accent: #555555;
            --light-border: #d5d5d5;
            --hover-bg: #e0e0d8;
            --code-bg: #fafaf5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
            -webkit-tap-highlight-color: transparent;
            font-family: "Microsoft YaHei", "SimSun", "宋体", "PingFang SC", "Hiragino Sans GB", sans-serif;
            font-size: 13px;
            line-height: 1.5;
            background-color: #e8e5dc;
            color: #333333;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 20px 10px;
        }

        .main-container {
            width: 100%;
            max-width: 820px;
            background-color: #f8f7f2;
            border: 1px solid #c5c5b8;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
            border-radius: 2px;
            overflow: visible;
        }

        .top-bar {
            background-color: #f0efe8;
            border-bottom: 1px solid #d0d0c8;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #555;
            min-height: 30px;
        }

        .top-bar .contact-qq {
            color: #666;
            font-size: 12px;
            font-weight: normal;
            letter-spacing: 0.3px;
        }

        .top-bar .contact-qq a {
            color: #0066cc;
            text-decoration: none;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .top-bar .contact-qq a:hover {
            text-decoration: underline;
            color: #004499;
        }

        .top-bar .login-btn {
            color: #aaa;
            font-size: 11px;
            cursor: pointer;
            user-select: none;
        }

        .top-bar .login-btn.logout-btn {
            color: #999;
        }

        .top-bar .login-btn.logout-btn:hover {
            color: #666;
        }

        .top-bar .admin-goto-btn {
            color: #aaa;
            font-size: 11px;
            text-decoration: none;
        }

        .top-bar .admin-goto-btn:hover {
            color: #666;
        }

        .modal-box .modal-title .modal-close {
            float: right;
            cursor: pointer;
            color: #999;
            font-size: 20px;
            line-height: 1;
        }

        .modal-box .modal-title .modal-close:hover {
            color: #333;
        }

        .tag-nav {
            background-color: #fafaf5;
            border-bottom: 1px solid #d8d8ce;
            padding: 8px 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 10px;
            align-items: center;
            font-size: 13px;
            line-height: 1.4;
        }

        .tag-nav .tag-item {
            display: inline-block;
            color: #333;
            text-decoration: none;
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 12.5px;
            letter-spacing: 0.2px;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.15s;
        }

        .tag-nav .tag-item:hover {
            background-color: #e8e8dc;
            color: #000;
        }

        .tag-nav .tag-separator {
            color: #999;
            font-size: 11px;
            margin: 0 -4px;
            user-select: none;
        }

        .tag-nav .tag-arrow {
            color: #777;
            font-size: 13px;
            margin-right: 2px;
            font-weight: bold;
        }

        .section-header {
            background-color: #f3f2ea;
            border-bottom: 1px solid #d5d5ca;
            padding: 7px 14px;
            font-size: 13px;
            color: #444;
            font-weight: bold;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-header .icon-folder {
            color: #d4a017;
            font-size: 14px;
        }

        .section-header .icon-plus {
            color: #666;
            font-size: 12px;
            margin-left: 2px;
        }

        .section-header .add-btn {
            margin-left: auto;
            background: none;
            border: 1px solid #bbb;
            border-radius: 3px;
            padding: 1px 8px;
            font-size: 11px;
            color: #555;
            cursor: pointer;
            font-family: inherit;
            transition: background-color 0.15s;
        }

        .section-header .add-btn:hover {
            background-color: #e0e0d8;
        }

        .dir-list {
            padding: 6px 14px;
            background-color: #fdfdfa;
            border-bottom: 1px solid #e0e0d5;
        }

        .dir-list .dir-item {
            display: flex;
            align-items: center;
            padding: 4px 0;
            font-size: 13px;
            color: #333;
            border-bottom: 1px dotted #e8e8e0;
            cursor: pointer;
            transition: background-color 0.1s;
            gap: 8px;
        }

        .dir-list .dir-item:last-child {
            border-bottom: none;
        }

        .dir-list .dir-item:hover {
            background-color: #f5f5ee;
            margin: 0 -6px;
            padding-left: 6px;
            padding-right: 6px;
            border-radius: 2px;
        }

        .dir-list .dir-icon {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .dir-list .dir-icon svg {
            width: 16px;
            height: 16px;
        }

        .dir-list .dir-name {
            color: #2a2a2a;
            font-size: 13px;
            letter-spacing: 0.2px;
            text-decoration: none;
            flex: 1;
        }

        .dir-list .dir-name.star {
            color: #c03030;
            font-weight: bold;
        }

        .dir-list .dir-name.important {
            color: #d44;
        }

        .dir-lock-icon {
            font-size: 11px;
            margin-left: 4px;
            opacity: 0.7;
        }

        .dir-pager {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            padding: 8px 0 2px;
            font-size: 12px;
            color: #666;
        }

        .pager-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 26px;
            padding: 0 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
            background: #f5f5f0;
            color: #555;
            font-size: 14px;
            user-select: none;
        }

        .pager-btn:hover:not(.disabled) {
            background: #e8e8e0;
            color: #333;
        }

        .pager-btn.disabled {
            color: #ccc;
            cursor: default;
            background: #fafaf8;
        }

        .pager-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 26px;
            padding: 0 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
            background: #f5f5f0;
            color: #555;
            user-select: none;
        }

        .pager-num:hover {
            background: #e8e8e0;
        }

        .pager-num.active {
            background: var(--header-bg);
            border-color: #b0b0a8;
            color: #333;
            font-weight: bold;
            cursor: default;
        }

        .pager-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 26px;
            color: #aaa;
            user-select: none;
        }

        .pager-info {
            margin-left: 8px;
            font-size: 11px;
            color: #999;
            white-space: nowrap;
        }

        .load-more-btn {
            display: block;
            text-align: center;
            padding: 10px;
            font-size: 13px;
            color: #666;
            cursor: pointer;
            border: 1px dashed #ccc;
            border-radius: 4px;
            margin: 8px 0;
            background: #fafaf8;
        }

        .load-more-btn:hover {
            background: #f0f0e8;
            color: #333;
        }

        .submenu-wrapper {
            display: none;
            background-color: #fff;
            border-top: 1px dashed #d8d8cc;
        }

        .submenu-wrapper.show {
            display: block;
        }

        .submenu-item {
            padding: 6px 14px 6px 42px;
            font-size: 12px;
            color: #555;
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .submenu-item:hover {
            background-color: #f0f0ee;
            color: #333;
        }

        .submenu-item .file-name {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .file-download-icon {
            flex-shrink: 0;
            margin-left: auto;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.2s;
            border-radius: 4px;
            color: #555;
            text-decoration: none;
        }

        .file-download-icon:hover {
            background: #e8e8e0;
            opacity: 1;
        }

        .submenu-item:hover .file-download-icon {
            opacity: 0.45;
        }

        .submenu-item .file-actions {
            opacity: 0;
            margin-left: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: opacity 0.2s;
            display: flex;
            gap: 6px;
        }

        .submenu-item:hover .file-actions {
            opacity: 1;
        }

        .submenu-item .file-actions .file-edit,
        .submenu-item .file-actions .file-del {
            font-size: 11px;
            padding: 1px 5px;
            border-radius: 2px;
            border: 1px solid #ccc;
            background: #f5f5f0;
            cursor: pointer;
            font-family: inherit;
        }

        .submenu-item .file-actions .file-del {
            color: #d00;
        }

        .submenu-item .file-actions .file-edit:hover {
            background-color: #e0e0d8;
        }

        .submenu-item .file-actions .file-del:hover {
            background-color: #ffe6e6;
            border-color: #d00;
        }

        .dir-icon {
            position: relative;
        }

        .icon-menu {
            display: none;
            position: absolute;
            left: 0;
            top: 100%;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            padding: 4px 0;
            z-index: 1000;
            min-width: 80px;
        }

        /* 隐形桥接：填满图标和菜单之间的空隙，防止鼠标穿过时丢失 hover */
        .icon-menu::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 0;
            right: 0;
            height: 6px;
        }

        body.show-admin .dir-icon:hover .icon-menu,
        body.show-admin .icon-menu:hover {
            display: block;
        }

        .icon-menu-item {
            padding: 6px 14px;
            font-size: 12px;
            color: #333;
            cursor: pointer;
            white-space: nowrap;
            text-align: center;
        }

        .icon-menu-item:first-child {
            border-radius: 6px 6px 0 0;
        }

        .icon-menu-item:last-child {
            border-radius: 0 0 6px 6px;
        }

        .icon-menu-item:hover {
            background-color: #f0f0f0;
        }

        .icon-menu-item.delete:hover {
            background-color: #ffe6e6;
            color: #d00;
        }

        .admin-only {
            display: none !important;
        }

        body.show-admin .admin-only {
            display: unset !important;
        }

        body.show-admin .icon-menu .admin-only {
            display: block !important;
        }

        .add-file-inline-btn.admin-only {
            background: none;
            border: 1px solid #c5c5b8;
            border-radius: 3px;
            padding: 1px 8px;
            font-size: 11px;
            color: #888;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s;
            flex-shrink: 0;
        }

        .add-file-inline-btn:hover {
            background-color: #e0e0d8;
            color: #555;
        }

        .btn-add-link {
            background: none;
            border: 1px dashed #bbb;
            border-radius: 3px;
            padding: 4px 10px;
            font-size: 11px;
            color: #888;
            cursor: pointer;
            font-family: inherit;
            margin-top: 6px;
            transition: all 0.15s;
        }

        .btn-add-link:hover {
            background-color: #f0f0ec;
            color: #555;
            border-color: #aaa;
        }

        .file-link-row {
            display: flex;
            gap: 6px;
            margin-bottom: 5px;
            align-items: center;
        }

        .file-link-row input[type="text"] {
            padding: 5px 7px;
            border: 1px solid #bbb;
            border-radius: 3px;
            font-size: 12px;
            font-family: inherit;
            background: #fff;
            color: #333;
        }

        .file-link-row input.link-name {
            flex: 2;
        }

        .file-link-row input.link-url {
            flex: 8;
        }

        .file-link-row input[type="text"]:focus {
            outline: none;
            border-color: #888;
        }

        .file-link-row .link-remove {
            background: none;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 4px 8px;
            font-size: 14px;
            color: #999;
            cursor: pointer;
            font-family: inherit;
            line-height: 1;
            flex-shrink: 0;
        }

        .file-link-row .link-remove:hover {
            background-color: #ffe6e6;
            border-color: #d00;
            color: #d00;
        }

        .message-section {
            border-top: 2px solid #d0d0c0;
            background-color: #fafaf4;
        }

        .message-header {
            background-color: #eeede4;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: bold;
            color: #444;
            border-bottom: 1px solid #d5d5c8;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .message-header .msg-icon {
            font-size: 15px;
        }

        .message-header .add-msg-btn {
            margin-left: auto;
            background: none;
            border: 1px solid #bbb;
            border-radius: 3px;
            padding: 1px 8px;
            font-size: 11px;
            color: #555;
            cursor: pointer;
            font-family: inherit;
            transition: background-color 0.15s;
        }

        .message-header .add-msg-btn:hover {
            background-color: #e0e0d8;
        }

        .message-list {
            padding: 0;
            background-color: #fdfdf8;
        }

        .message-item {
            padding: 8px 60px 8px 14px;
            border-bottom: 1px solid #e8e8dc;
            font-size: 12.5px;
            color: #444;
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 8px;
            line-height: 1.5;
            position: relative;
        }

        .message-item:last-child {
            border-bottom: 1px solid #e0e0d2;
        }

        .message-number {
            color: #888;
            font-size: 11px;
            min-width: 18px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .message-code {
            font-family: "Consolas", "Monaco", "Courier New", "SF Mono", monospace;
            font-size: 11.5px;
            color: #555;
            background-color: #f5f5ec;
            padding: 1px 4px;
            border-radius: 2px;
            letter-spacing: 0.5px;
            border: 1px solid #e0e0d3;
            cursor: default;
        }
        .clickable-code {
            cursor: pointer;
            transition: background-color 0.15s, border-color 0.15s;
        }
        .clickable-code:hover {
            background-color: #e8f0fe;
            border-color: #a0bfff;
            color: #1a73e8;
        }

        .message-date {
            color: #999;
            font-size: 11px;
            white-space: nowrap;
        }

        .message-ip {
            color: #888;
            font-size: 11px;
            white-space: nowrap;
            font-family: "Consolas", "Monaco", "Courier New", "SF Mono", monospace;
            letter-spacing: 0.3px;
        }

        .message-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-top: 2px;
            gap: 8px;
            padding-left: 4em;
        }

        .message-text {
            color: #555;
            font-size: 12px;
            flex: 1;
            min-width: 0;
        }

        .message-item .msg-actions {
            opacity: 0;
            display: flex;
            gap: 4px;
            flex-shrink: 0;
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            transition: opacity 0.2s;
        }

        .message-item:hover .msg-actions {
            opacity: 1;
        }

        .message-item .msg-actions .msg-edit,
        .message-item .msg-actions .msg-del {
            font-size: 10px;
            padding: 1px 5px;
            border-radius: 2px;
            border: 1px solid #ccc;
            background: #f5f5f0;
            cursor: pointer;
            font-family: inherit;
        }

        .message-item .msg-actions .msg-del {
            color: #d00;
        }

        .message-item .msg-actions .msg-edit:hover {
            background-color: #e0e0d8;
        }

        .message-item .msg-actions .msg-del:hover {
            background-color: #ffe6e6;
            border-color: #d00;
        }

        .bottom-info {
            background-color: #f3f2ea;
            padding: 7px 14px;
            font-size: 11.5px;
            color: #777;
            border-top: 1px solid #dcdccf;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 16px;
            letter-spacing: 0.2px;
            border-radius: 0 0 2px 2px;
        }

        .bottom-info .info-sep {
            color: #bbb;
            font-size: 10px;
        }

        .bottom-info .dir-count {
            color: #555;
            font-weight: bold;
        }

        .bottom-info .checkbox-area {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            color: #666;
            font-size: 11.5px;
        }

        .bottom-info .checkbox-area input[type="checkbox"] {
            width: 13px;
            height: 13px;
            cursor: pointer;
            accent-color: #666;
        }

        .bottom-info .help-link {
            color: #0066cc;
            cursor: pointer;
            text-decoration: none;
            font-size: 11.5px;
        }

        .bottom-info .help-link:hover {
            text-decoration: underline;
        }

        .section-divider {
            height: 0;
            border-top: 1px solid #d5d5c8;
            margin: 0;
        }

        .section-divider-thick {
            height: 0;
            border-top: 2px solid #c8c8b8;
            margin: 0;
        }

        .dir-list .dir-item:hover .dir-name,
        .dir-list-bottom .dir-item:hover .dir-name {
            color: #000;
        }

        .highlight-star {
            color: #c03030 !important;
            font-weight: bold;
        }

        .dir-name.guest-upload {
            color: #b02020;
            font-weight: bold;
            letter-spacing: 0.4px;
        }

        .dir-name.pinned {
            color: #1a56db;
            font-weight: bold;
        }

        .top-bar,
        .tag-nav,
        .section-header,
        .dir-list,
        .message-section,
        .bottom-info {
            font-family: "Microsoft YaHei", "SimSun", "PingFang SC", "Hiragino Sans GB", "宋体", sans-serif;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 2000;
            justify-content: center;
            align-items: flex-start;
            overflow-y: auto;
            padding: 60px 0;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: #f8f7f2;
            border: 1px solid #c5c5b8;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 20px;
            width: 90%;
            min-width: 280px;
            max-width: 500px;
        }

        .modal h3 {
            margin-bottom: 14px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #d5d5ca;
            padding-bottom: 8px;
        }

        .modal .form-group {
            margin-bottom: 12px;
        }

        .modal .form-group label {
            display: block;
            font-size: 12px;
            color: #555;
            margin-bottom: 4px;
        }

        .modal .form-group input,
        .modal .form-group textarea,
        .modal .form-group select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #bbb;
            border-radius: 3px;
            font-size: 12px;
            font-family: inherit;
            background: #fff;
            color: #333;
            box-sizing: border-box;
        }

        .modal .form-group input:focus,
        .modal .form-group textarea:focus,
        .modal .form-group select:focus {
            outline: none;
            border-color: #888;
        }

        .modal .form-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 16px;
        }

        .modal .btn {
            padding: 5px 14px;
            border: 1px solid #bbb;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
            font-family: inherit;
            transition: background-color 0.15s;
        }

        .modal .btn-primary {
            background-color: #4a7ab5;
            color: #fff;
            border-color: #3a6a9e;
        }

        .modal .btn-primary:hover {
            background-color: #3a6a9e;
        }

        .modal .btn-secondary {
            background-color: #f0efe8;
            color: #555;
        }

        .modal .btn-secondary:hover {
            background-color: #e0e0d8;
        }

        .modal .btn-danger {
            background-color: #c03030;
            color: #fff;
            border-color: #a02020;
        }

        .modal .btn-danger:hover {
            background-color: #a02020;
        }

        /* 预览弹窗 */
        .preview-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 180px;
            max-height: 72vh;
            overflow: auto;
            background: #f5f5f0;
            border-radius: 6px;
        }

        .preview-content img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            display: block;
        }

        .preview-content embed,
        .preview-content iframe {
            width: 100%;
            height: 70vh;
            border: none;
        }

        .preview-content video {
            max-width: 100%;
            max-height: 70vh;
            display: block;
        }

        .preview-content pre {
            width: 100%;
            align-self: flex-start;
        }

        /* 音频播放器 */
        .audio-player {
            width: 100%;
            padding: 28px 24px 20px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border-radius: 8px;
            text-align: center;
        }

        .audio-player-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .audio-player-title {
            color: #e0e0e0;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 0 4px;
        }

        .audio-player-controls {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ap-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.15s;
            flex-shrink: 0;
            color: #fff;
        }

        .ap-btn-play {
            background: #4a9eff;
            width: 46px;
            height: 46px;
            font-size: 16px;
        }

        .ap-btn-play:hover { background: #3a8eef; }

        .ap-btn-stop {
            background: rgba(255,255,255,0.1);
        }

        .ap-btn-stop:hover { background: rgba(255,255,255,0.2); }

        .ap-progress-wrap {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
        }

        .ap-range {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 5px;
            background: rgba(255,255,255,0.15);
            border-radius: 3px;
            outline: none;
            cursor: pointer;
            accent-color: #4a9eff;
        }

        .ap-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.3);
            border: none;
            cursor: pointer;
            margin-top: -1px;
        }

        .ap-range::-moz-range-thumb {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.3);
            border: none;
            cursor: pointer;
        }

        .ap-range::-webkit-slider-runnable-track {
            height: 5px;
            border-radius: 3px;
        }

        .ap-range::-moz-range-track {
            height: 5px;
            border-radius: 3px;
            background: rgba(255,255,255,0.15);
        }

        .ap-range::-moz-range-progress {
            height: 5px;
            border-radius: 3px;
            background: #4a9eff;
        }

        .ap-time {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            font-family: Menlo, Monaco, Consolas, monospace;
            flex-shrink: 0;
            min-width: 88px;
            text-align: right;
        }

        .ap-audio {
            display: none;
        }

        /* 压缩包文件列表 */
        .zip-file-list {
            width: 100%;
            max-height: 60vh;
            overflow: auto;
            text-align: left;
            font-size: 13px;
        }

        .zip-file-item {
            display: flex;
            align-items: center;
            padding: 6px 12px;
            border-bottom: 1px solid #e8e8e0;
            font-family: Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            color: #333;
            cursor: default;
        }

        .zip-file-item.is-dir {
            color: #4a7ab5;
            font-weight: 500;
            cursor: pointer;
        }

        .zip-file-item.is-dir:hover {
            background: #e8f0fe;
        }

        .zip-file-back {
            cursor: pointer;
            color: #888;
            font-weight: 500;
        }

        .zip-file-back:hover {
            background: #f0f0ee;
            color: #333;
        }

        .zip-breadcrumb {
            display: flex;
            align-items: center;
            padding: 6px 12px;
            border-bottom: 1px solid #e0e0d8;
            font-size: 12px;
            color: #888;
            gap: 4px;
            overflow: hidden;
        }

        .zip-breadcrumb span {
            cursor: pointer;
            color: #4a7ab5;
            white-space: nowrap;
        }

        .zip-breadcrumb span:hover {
            text-decoration: underline;
        }

        .zip-breadcrumb .zip-breadcrumb-sep {
            color: #ccc;
            cursor: default;
        }

        .zip-breadcrumb .zip-breadcrumb-sep:hover {
            text-decoration: none;
        }

        .zip-file-item .zip-file-icon {
            margin-right: 8px;
            flex-shrink: 0;
        }

        .zip-file-item .zip-file-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .zip-file-item .zip-file-size {
            margin-left: 12px;
            color: #999;
            flex-shrink: 0;
            font-size: 11px;
        }

        .zip-file-count {
            text-align: center;
            padding: 8px;
            color: #888;
            font-size: 12px;
            border-bottom: 1px solid #e0e0d8;
        }

        .preview-unsupported {
            text-align: center;
            padding: 50px 30px;
            color: #888;
            font-size: 13px;
        }

        .preview-unsupported .preview-download-btn {
            display: inline-block;
            margin-top: 16px;
            padding: 6px 18px;
            background: #4a7ab5;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }

        .preview-unsupported .preview-download-btn:hover {
            background: #3a6aa5;
        }

        .file-name-text {
            cursor: pointer;
        }

        .file-name-text:hover {
            color: #4a7ab5;
            text-decoration: underline;
        }

        /* 文件上传样式 */
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 12px;
            background: #fafaf8;
        }

        .upload-area:hover {
            border-color: #888;
            background: #f5f5f0;
        }

        .upload-area.dragover {
            border-color: #4a7ab5;
            background: #e8f0fe;
        }

        .upload-area input[type="file"] {
            display: none;
        }

        .upload-icon {
            font-size: 32px;
            color: #888;
            margin-bottom: 8px;
        }

        .upload-text {
            font-size: 12px;
            color: #666;
        }

        .upload-text small {
            display: block;
            color: #999;
            margin-top: 4px;
        }

        .upload-progress {
            display: none;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }

        .upload-progress-bar {
            width: 100%;
            height: 4px;
            background: #e0e0d8;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }

        .upload-progress-bar-fill {
            height: 100%;
            background: #4a7ab5;
            width: 0%;
            transition: width 0.3s;
        }

        .uploaded-files {
            margin-top: 10px;
            padding: 8px;
            background: #f5f5f0;
            border-radius: 4px;
            font-size: 11px;
        }

        .uploaded-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #e0e0d8;
        }

        .uploaded-file-item:last-child {
            border-bottom: none;
        }

        .uploaded-file-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #333;
        }

        .uploaded-file-remove {
            background: none;
            border: none;
            color: #c00;
            cursor: pointer;
            font-size: 14px;
            padding: 0 4px;
        }

        .uploaded-file-remove:hover {
            color: #a00;
        }

        .modal-box {
            background: #f8f7f2;
            border: 1px solid #c5c5b8;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            padding: 24px;
            width: 400px;
            max-width: 90vw;
        }

        .modal-title {
            font-size: 15px;
            font-weight: bold;
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d5d5ca;
        }

        .modal-body {
            font-size: 13px;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 12px;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-top-color: #888;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-left: 8px;
        }

        @media (max-width: 768px) {
            .dir-list .loading,
            .message-list .loading {
                font-size: 13px;
                padding: 16px 0;
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>

    <div class="main-container">

        <div class="top-bar">
            <span class="contact-qq">联系QQ: <a href="#">672786264</a></span>
            <span class="login-btn" id="login-btn">登录</span>
            <span class="login-btn-group" style="display:none;" id="login-btn-group">
                <span class="login-btn logout-btn" id="logout-btn">退出</span>
                <a class="login-btn admin-goto-btn" id="admin-goto-btn" href="/admin/" target="_blank">后台</a>
            </span>
        </div>

        <div class="tag-nav" id="tag-nav">
            <span style="color:#888;font-size:12px;">资源分类导航</span>
        </div>

        <div class="section-header">
            <span class="icon-folder">📁</span>
            <span>增加目录</span>
            <button class="add-btn" id="add-dir-btn" style="display:none;" onclick="openAddDirModal()">+ 新增目录</button>
        </div>

        <div id="dir-list" class="dir-list"></div>

        <div class="message-section">
            <div class="message-header">
                <span class="msg-icon">💬</span>
                <span>留言本</span>
                <button class="add-msg-btn admin-only" onclick="openAddMsgModal()">+ 新增留言</button>
            </div>
            <div id="message-list" class="message-list"></div>
            <div style="font-size:11px;color:#999;padding:4px 0 0;text-align:center;">点击名称自动填入</div>
        </div>

        <div class="bottom-info">
            <span>共 <span class="dir-count" id="dir-count">0</span> 个目录</span>
            <span class="info-sep">|</span>
            <span class="checkbox-area">
                <input type="checkbox" id="toggle-all">
                <label for="toggle-all">展开全部</label>
            </span>
        </div>
    </div>

    <!-- Modal for directory -->
    <div class="modal-overlay" id="dir-modal">
        <div class="modal">
            <h3 id="dir-modal-title">新增目录</h3>
            <input type="hidden" id="dir-id">
            <div class="form-group">
                <label>目录名称</label>
                <input type="text" id="dir-name" placeholder="请输入目录名称">
            </div>
            <div class="form-group">
                <label>目录类型</label>
                <select id="dir-type">
                    <option value="normal">普通目录</option>
                    <option value="guest">游客上传区</option>
                </select>
            </div>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="closeDirModal()">取消</button>
                <button class="btn btn-primary" onclick="saveDir()">保存</button>
            </div>
        </div>
    </div>

    <!-- Modal for file -->
    <div class="modal-overlay" id="file-modal">
        <div class="modal">
            <h3 id="file-modal-title">新增文件</h3>
            <input type="hidden" id="file-dir-id">
            <input type="hidden" id="file-old-name">
            <div class="form-group">
                <label>文件名称</label>
                <input type="text" id="file-name" placeholder="请输入文件名称，如：资料.pdf">
            </div>
            <div class="form-group">
                <label>上传文件 <span style="font-weight:normal;color:#888;font-size:11px;">（支持拖拽上传，上传后自动填入链接）</span></label>
                <div class="upload-area" id="upload-area" onclick="document.getElementById('file-upload-input').click()">
                    <div class="upload-icon">📤</div>
                    <div class="upload-text" id="upload-text">
                        点击选择文件或拖拽文件到此处上传
                        <small>支持常见文件格式，单个文件不超过1024MB</small>
                    </div>
                    <input type="file" id="file-upload-input" onchange="handleFileSelect(event)">
                    <div class="upload-progress" id="upload-progress">
                        <span id="upload-status">上传中...</span>
                        <div class="upload-progress-bar">
                            <div class="upload-progress-bar-fill" id="upload-progress-fill"></div>
                        </div>
                    </div>
                </div>
                <div class="uploaded-files" id="uploaded-files-list" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label>下载链接 <span style="font-weight:normal;color:#888;font-size:11px;">（名称留空默认显示"下载1、2、3..."，可留空）</span></label>
                <div id="file-links-container">
                </div>
                <button type="button" class="btn-add-link" onclick="addFileLinkRow()">+ 添加链接</button>
            </div>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="closeFileModal()">取消</button>
                <button class="btn btn-primary" onclick="saveFile()">保存</button>
            </div>
        </div>
    </div>

    <!-- Modal for message -->
    <div class="modal-overlay" id="msg-modal">
        <div class="modal">
            <h3 id="msg-modal-title">新增留言</h3>
            <input type="hidden" id="msg-id">
            <div class="form-group">
                <label>用户名称</label>
                <input type="text" id="msg-name" placeholder="请输入用户名称（选填）">
            </div>
            <div class="form-group">
                <label>留言内容</label>
                <textarea id="msg-content" rows="3" placeholder="请输入留言内容"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="closeMsgModal()">取消</button>
                <button class="btn btn-primary" onclick="saveMsg()">保存</button>
            </div>
        </div>
    </div>

    <!-- Confirm delete modal -->
    <div class="modal-overlay" id="confirm-modal">
        <div class="modal">
            <h3 id="confirm-title">确认删除</h3>
            <p id="confirm-msg" style="font-size:12px;color:#555;margin:12px 0;">确定要删除吗？</p>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">取消</button>
                <button class="btn btn-danger" id="confirm-btn" onclick="confirmAction()">删除</button>
            </div>
        </div>
    </div>

    <!-- Dir lock modal (admin) -->
    <div class="modal-overlay" id="dir-lock-modal">
        <div class="modal" style="max-width:360px;">
            <h3 id="dir-lock-title">设置密码</h3>
            <div class="form-group">
                <label>密码（留空则取消密码）</label>
                <input type="password" id="dir-lock-password" placeholder="输入密码">
            </div>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="closeDirLockModal()">取消</button>
                <button class="btn btn-primary" onclick="saveDirLock()">保存</button>
            </div>
        </div>
    </div>

    <!-- Dir unlock modal (anonymous) -->
    <div class="modal-overlay" id="dir-unlock-modal">
        <div class="modal" style="max-width:340px;">
            <h3 id="dir-unlock-title">🔒 此目录已加密</h3>
            <p style="font-size:12px;color:#888;margin-bottom:12px;">请输入密码访问</p>
            <div class="form-group">
                <input type="password" id="dir-unlock-password" placeholder="输入密码" onkeydown="if(event.key==='Enter')submitDirUnlock()">
            </div>
            <div class="form-actions">
                <button class="btn btn-secondary" onclick="closeDirUnlockModal()">取消</button>
                <button class="btn btn-primary" onclick="submitDirUnlock()">确认</button>
            </div>
        </div>
    </div>

    <!-- Share dir modal -->
    <div class="modal-overlay" id="share-modal">
        <div class="modal" style="max-width:420px;">
            <h3 style="margin-bottom:8px;">🔗 分享目录</h3>
            <p id="share-dir-name" style="font-size:12px;color:#888;margin-bottom:12px;"></p>
            <div style="display:flex;gap:8px;">
                <input type="text" id="share-url" readonly style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:12px;background:#f5f5f0;" onclick="this.select()">
                <button class="btn btn-primary" onclick="copyShareUrl()" style="white-space:nowrap;">复制</button>
            </div>
            <p id="share-copied" style="font-size:12px;color:#4a9eff;margin-top:8px;display:none;">已复制到剪贴板</p>
            <div class="form-actions" style="margin-top:12px;">
                <button class="btn btn-secondary" onclick="closeShareModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- Download choice modal -->
    <div class="modal-overlay" id="download-modal" style="align-items:flex-start;">
        <div class="modal" style="min-width:280px;max-width:400px;margin-top:120px;">
            <h3 style="margin-bottom:12px;">⬇ 选择下载地址</h3>
            <div id="download-links-container" style="max-height:400px;overflow-y:auto;"></div>
            <div class="form-actions" style="margin-top:12px;">
                <button class="btn btn-secondary" onclick="closeDownloadModal()">取消</button>
            </div>
        </div>
    </div>

    <!-- 预览弹窗 -->
    <div class="modal-overlay" id="preview-modal" style="align-items:flex-start;">
        <div class="modal" style="min-width:360px;max-width:92vw;width:800px;margin-top:50px;">
            <h3 style="margin-bottom:12px;" id="preview-title">📄 预览</h3>
            <div id="preview-content" class="preview-content"></div>
            <div class="form-actions" style="margin-top:12px;">
                <button class="btn btn-secondary" onclick="closePreviewModal()">关闭</button>
            </div>
        </div>
    </div>

<script>

var isLoggedIn = false;
var csrfToken = '';
var PAGE_SIZE = 10;
var currentPage = 1;
var allDirs = [];
var isMobile = false;
var isLoadingMore = false;

function checkIsMobile() {
    isMobile = window.innerWidth <= 768;
}

checkIsMobile();
window.addEventListener('resize', function() {
    var wasMobile = isMobile;
    checkIsMobile();
    if (wasMobile !== isMobile) {
        currentPage = 1;
        renderDirs();
    }
});

function checkLogin() {
    return fetch('/api/index.php?action=check', { method: 'POST', headers: {'Content-Type': 'application/json'}, credentials: 'same-origin' })
        .then(r => r.json().catch(() => ({ logged_in: false })))
        .then(r => {
            isLoggedIn = r.logged_in === true;
            csrfToken = r.csrf_token || '';
            document.getElementById('login-btn').style.display = isLoggedIn ? 'none' : '';
            document.getElementById('login-btn-group').style.display = isLoggedIn ? '' : 'none';
            document.getElementById('add-dir-btn').style.display = isLoggedIn ? '' : 'none';
            isLoggedIn ? document.body.classList.add('show-admin') : document.body.classList.remove('show-admin');
        });
}

document.getElementById('login-btn').addEventListener('click', function() {
    document.getElementById('login-modal').classList.add('show');
    document.getElementById('login-password').focus();
});

document.getElementById('logout-btn').addEventListener('click', function() {
    fetch('/api/index.php?action=logout', { method: 'POST', headers: {'Content-Type': 'application/json'}, credentials: 'same-origin' })
        .then(r => r.json().catch(() => ({ success: true })))
        .then(() => {
            isLoggedIn = false;
            csrfToken = '';
            document.getElementById('login-btn').style.display = '';
            document.getElementById('login-btn-group').style.display = 'none';
            document.getElementById('add-dir-btn').style.display = 'none';
            document.body.classList.remove('show-admin');
        });
});

function closeLoginModal() {
    document.getElementById('login-modal').classList.remove('show');
    document.getElementById('login-password').value = '';
    document.getElementById('login-error').textContent = '';
}

async function doLogin() {
    const pw = document.getElementById('login-password').value;
    const errorEl = document.getElementById('login-error');
    if (!pw) { errorEl.textContent = '请输入密码'; return; }
    errorEl.textContent = '验证中...';
    try {
        const r = await fetch('/api/index.php?action=login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({ password: pw })
        });
        const res = await r.json().catch(() => null);
        if (!res) {
            errorEl.textContent = '服务器错误，请稍后重试';
            return;
        }
        if (res.locked) {
            errorEl.textContent = res.error;
            return;
        }
        if (!res.success) {
            errorEl.textContent = res.error || '登录失败';
            return;
        }
        isLoggedIn = true;
        csrfToken = res.csrf_token || '';
        document.getElementById('login-btn').style.display = 'none';
        document.getElementById('login-btn-group').style.display = '';
        document.getElementById('add-dir-btn').style.display = '';
        document.body.classList.add('show-admin');
        closeLoginModal();
    } catch(e) {
        errorEl.textContent = '网络错误，请重试';
    }
}

function handleLoginKey(e) {
    if (e.key === 'Enter') doLogin();
}
</script>

<!-- Login Modal -->
<div class="modal-overlay" id="login-modal">
    <div class="modal-box" style="max-width:320px">
        <div class="modal-title">管理员登录 <span class="modal-close" onclick="closeLoginModal()">×</span></div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:16px;">
                <svg viewBox="0 0 24 24" fill="#999" width="40" height="40"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            </div>
            <form id="login-form" onsubmit="return false;">
                <div class="form-group">
                    <input type="password" id="login-password" placeholder="请输入密码" onkeydown="handleLoginKey(event)" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:3px;font-size:13px;">
                </div>
                <div id="login-error" style="color:#c00;font-size:12px;margin-bottom:8px;height:16px;"></div>
                <button type="button" class="btn-primary" onclick="doLogin()" style="width:100%;padding:8px;background:#444;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px;">登 录</button>
            </form>
        </div>
    </div>
</div>

<script>const API_BASE = 'api/index.php';
let currentConfirmCallback = null;

async function api(method, url, body) {
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    const opts = {
        method,
        headers: headers,
        credentials: 'same-origin'
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(API_BASE + url, opts);
    const data = await res.json().catch(() => null);
    if (!data) {
        throw new Error('服务器响应错误');
    }
    if (data.unauthorized) {
        document.getElementById('login-modal').classList.add('show');
        document.getElementById('login-password').focus();
        throw new Error(data.error);
    }
    return data;
}

async function loadData() {
    const dirList = document.getElementById('dir-list');
    const msgList = document.getElementById('message-list');

    dirList.innerHTML = '<div class="loading">加载中</div>';
    msgList.innerHTML = '<div class="loading">加载中</div>';

    try {
        const data = await api('GET', '');
        if (!data.success) throw new Error(data.error);

        allDirs = data.dirs || [];
        currentPage = 1;
        renderDirs();
        renderMessages(data.messages || []);
        document.getElementById('dir-count').textContent = allDirs.length;
    } catch (e) {
        dirList.innerHTML = '<div class="loading" style="animation:none;">加载失败: ' + e.message + '</div>';
        msgList.innerHTML = '<div class="loading" style="animation:none;">加载失败: ' + e.message + '</div>';
    }
}

function renderDirs() {
    const container = document.getElementById('dir-list');
    if (allDirs.length === 0) {
        container.innerHTML = '<div style="padding:12px 0;color:#888;font-size:12px;text-align:center;">暂无目录，点击上方按钮添加</div>';
        return;
    }

    if (isMobile) {
        const existingCount = container.querySelectorAll('.dir-item').length;
        if (existingCount === 0) {
            container.innerHTML = '';
        }
        const newDirs = allDirs.slice(existingCount, currentPage * PAGE_SIZE);
        const fragment = document.createDocumentFragment();
        newDirs.forEach(dir => {
            fragment.appendChild(createDirItemEl(dir));
            fragment.appendChild(createSubmenuEl(dir));
        });
        container.appendChild(fragment);

        const oldLoadMore = container.querySelector('.load-more-btn');
        if (oldLoadMore) oldLoadMore.remove();

        if (currentPage * PAGE_SIZE < allDirs.length) {
            const loadMoreBtn = document.createElement('div');
            loadMoreBtn.className = 'load-more-btn';
            loadMoreBtn.textContent = '点击加载更多 (' + (allDirs.length - currentPage * PAGE_SIZE) + ')';
            loadMoreBtn.addEventListener('click', function() {
                currentPage++;
                renderDirs();
            });
            container.appendChild(loadMoreBtn);
        }
        attachDirListeners();
        return;
    }

    const totalPages = Math.ceil(allDirs.length / PAGE_SIZE);
    const start = (currentPage - 1) * PAGE_SIZE;
    const end = start + PAGE_SIZE;
    const pageDirs = allDirs.slice(start, end);

    container.innerHTML = '';

    pageDirs.forEach(dir => {
        container.appendChild(createDirItemEl(dir));
        container.appendChild(createSubmenuEl(dir));
    });

    const pager = document.createElement('div');
    pager.className = 'dir-pager';
    const prevBtn = document.createElement('span');
    prevBtn.className = 'pager-btn' + (currentPage <= 1 ? ' disabled' : '');
    prevBtn.textContent = '‹';
    prevBtn.addEventListener('click', function() {
        if (currentPage > 1) { currentPage--; renderDirs(); }
    });

    const nextBtn = document.createElement('span');
    nextBtn.className = 'pager-btn' + (currentPage >= totalPages ? ' disabled' : '');
    nextBtn.textContent = '›';
    nextBtn.addEventListener('click', function() {
        if (currentPage < totalPages) { currentPage++; renderDirs(); }
    });

    pager.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
            const btn = document.createElement('span');
            btn.className = 'pager-num' + (i === currentPage ? ' active' : '');
            btn.textContent = i;
            btn.addEventListener('click', function() {
                currentPage = i;
                renderDirs();
            });
            pager.appendChild(btn);
        } else if (i === 2 || i === totalPages - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'pager-ellipsis';
            ellipsis.textContent = '...';
            pager.appendChild(ellipsis);
        }
    }

    pager.appendChild(nextBtn);
    pager.innerHTML += '<span class="pager-info">' + (start + 1) + '-' + Math.min(end, allDirs.length) + ' / ' + allDirs.length + '</span>';
    container.appendChild(pager);

    attachDirListeners();
}

function createDirItemEl(dir) {
    const div = document.createElement('div');
    div.className = 'dir-item';
    div.dataset.dir = dir.name;
    div.dataset.dirId = dir.id;
    div.dataset.locked = dir.has_password ? '1' : '0';
    div.innerHTML =
        '<span class="dir-icon">' +
            '<svg viewBox="0 0 24 24" fill="#e8b830" width="16" height="16"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0 1.1-.9-2-2-2h-8l-2-2z"/></svg>' +
            '<div class="icon-menu" onclick="event.stopPropagation()">' +
                '<div class="icon-menu-item admin-only" onclick="openEditDirModal(' + dir.id + ', \'' + esc(dir.name) + '\', \'' + dir.type + '\')">编辑</div>' +
                '<div class="icon-menu-item admin-only" onclick="togglePinDir(' + dir.id + ')">' + (dir.is_pinned ? '取消置顶' : '置顶') + '</div>' +
                '<div class="icon-menu-item admin-only" onclick="reorderDir(' + dir.id + ', ' + allDirs.length + ')">移动排序</div>' +
                '<div class="icon-menu-item" onclick="shareDir(' + dir.id + ', \'' + esc(dir.name) + '\')">🔗 分享</div>' +
                '<div class="icon-menu-item admin-only" onclick="showDirLockModal(' + dir.id + ', \'' + esc(dir.name) + '\', ' + (dir.has_password ? 'true' : 'false') + ')">' + (dir.has_password ? '🔓 取消密码' : '🔒 设置密码') + '</div>' +
                '<div class="icon-menu-item delete admin-only" onclick="confirmDeleteDir(' + dir.id + ', \'' + esc(dir.name) + '\')">删除</div>' +
            '</div>' +
        '</span>' +
        '<span class="dir-name' + (dir.type === 'guest' ? ' guest-upload' : '') + (dir.is_pinned ? ' pinned' : '') + '">' + escHtml(dir.name) + (dir.has_password ? ' <span class="dir-lock-icon" title="已加密">🔒</span>' : '') + '</span>' +
        '<button class="add-file-inline-btn admin-only" onclick="openAddFileModal(' + dir.id + ')" title="添加文件">+ 添加文件</button>';
    return div;
}

function cleanFileName(name) {
    return name.replace(/^📄\s*/, '');
}

function createSubmenuEl(dir) {
    const wrapper = document.createElement('div');
    wrapper.className = 'submenu-wrapper';
    wrapper.dataset.for = dir.name;

    const files = dir.files || [];
    wrapper.innerHTML = files.map(f => {
        const cleanName = cleanFileName(f.name);
        const links = (f.links || []).map((l, i) => typeof l === 'object' ? l : { url: l, name: '下载' + (i + 1) });
        const linksJson = JSON.stringify(links).replace(/"/g, '&quot;');
        const fileNameEscaped = escHtml(cleanName).replace(/"/g, '&quot;');
        const downloadSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        const shareSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>';
        var downloadIcon = '';
        if (links.length === 1) {
            downloadIcon = '<a class="file-download-icon" href="' + escHtml(links[0].url) + '" target="_blank" title="下载 ' + escHtml(links[0].name || '') + '">' + downloadSvg + '</a>';
        } else if (links.length > 1) {
            downloadIcon = '<span class="file-download-icon" onclick="showDownloadChoice(\'' + linksJson.replace(/'/g, "\\'") + '\')" title="' + links.length + '个下载链接">' + downloadSvg + '</span>';
        } else {
            downloadIcon = '<a class="file-download-icon" href="api/index.php?action=file_download&dir_id=' + dir.id + '&file_name=' + encodeURIComponent(cleanName) + '" title="下载">' + downloadSvg + '</a>';
        }
        var shareBtn = '<span class="file-download-icon file-share-icon" onclick="event.stopPropagation();shareFile(' + dir.id + ', \'' + fileNameEscaped + '\')" title="分享">' + shareSvg + '</span>';
        return '<div class="submenu-item">' +
            '<span class="file-name"><span class="file-name-text" data-dir-id="' + dir.id + '" data-filename="' + fileNameEscaped + '" data-links="' + linksJson + '">📄 ' + escHtml(cleanName) + '</span>' + shareBtn + downloadIcon + '</span>' +
            '<span class="file-actions admin-only">' +
                '<button class="file-edit admin-only" data-dir-id="' + dir.id + '" data-filename="' + fileNameEscaped + '" data-links="' + linksJson + '">编辑</button>' +
                '<button class="file-del admin-only" data-dir-id="' + dir.id + '" data-filename="' + fileNameEscaped + '">删除</button>' +
            '</span>' +
        '</div>';
    }).join('');
    return wrapper;
}

function attachDirListeners(container) {
    container = container || document.getElementById('dir-list');

    container.querySelectorAll('.dir-item:not([data-bound])').forEach(item => {
        item.setAttribute('data-bound', '1');
        item.addEventListener('click', function() {
            var dirId = this.dataset.dirId;
            var locked = this.dataset.locked === '1';
            if (locked && !isLoggedIn && !isDirUnlocked(dirId)) {
                showDirUnlockModal(dirId, this.dataset.dir);
                return;
            }
            const wrapper = this.nextElementSibling;
            if (wrapper && wrapper.classList.contains('submenu-wrapper')) {
                wrapper.classList.toggle('show');
            }
        });
    });

    container.querySelectorAll('.file-edit:not([data-bound])').forEach(btn => {
        btn.setAttribute('data-bound', '1');
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dirId = parseInt(this.dataset.dirId);
            const fileName = cleanFileName(this.dataset.filename);
            const links = JSON.parse(this.dataset.links || '[]');
            openEditFileModal(dirId, fileName, links);
        });
    });

    container.querySelectorAll('.file-del:not([data-bound])').forEach(btn => {
        btn.setAttribute('data-bound', '1');
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dirId = parseInt(this.dataset.dirId);
            const fileName = cleanFileName(this.dataset.filename);
            confirmDeleteFile(dirId, fileName);
        });
    });

}

function handleMobileScroll() {
    if (!isMobile) return;
    if (isLoadingMore) return;
    if (currentPage * PAGE_SIZE >= allDirs.length) return;
    var loadMoreBtn = document.querySelector('.load-more-btn');
    if (!loadMoreBtn) return;
    var rect = loadMoreBtn.getBoundingClientRect();
    if (rect.top <= window.innerHeight) {
        isLoadingMore = true;
        currentPage++;
        renderDirs();
        isLoadingMore = false;
    }
}

window.addEventListener('scroll', handleMobileScroll);

function renderMessages(messages) {
    const container = document.getElementById('message-list');
    if (messages.length === 0) {
        container.innerHTML = '<div style="padding:12px 0;color:#888;font-size:12px;text-align:center;">暂无留言</div>';
        return;
    }
    const msgHtml = messages.map((m, i) => {
        const displayName = m.name || '匿名';
        return `
        <div class="message-item">
            <span class="message-number">${i + 1}</span>
            <span class="message-code clickable-code" data-code="${escHtml(m.code)}" data-name="${escHtml(displayName)}" title="点击填入用户名称">${escHtml(displayName)}</span>
            ${m.created_at ? `<span class="message-date">${formatDate(m.created_at)}</span>` : ''}
            ${m.ip ? `<span class="message-ip admin-only">IP:${escHtml(m.ip)}</span>` : ''}
            <div class="message-bottom">
                ${m.content ? `<span class="message-text">${escHtml(m.content)}</span>` : '<span class="message-text"></span>'}
            </div>
            <span class="msg-actions">
                <button class="msg-edit admin-only" data-id="${m.id}" data-code="${escHtml(m.code).replace(/"/g, '&quot;')}" data-content="${escHtml(m.content || '').replace(/"/g, '&quot;')}" data-name="${escHtml(m.name || '').replace(/"/g, '&quot;')}">编辑</button>
                <button class="msg-del admin-only" data-id="${m.id}">删除</button>
            </span>
        </div>
    `}).join('');
    container.innerHTML = msgHtml;

    container.querySelectorAll('.msg-edit').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = parseInt(this.dataset.id);
            const code = this.dataset.code;
            const content = this.dataset.content;
            const name = this.dataset.name;
            openEditMsgModal(id, code, content, name);
        });
    });

    container.querySelectorAll('.msg-del').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = parseInt(this.dataset.id);
            confirmDeleteMsg(id);
        });
    });

    container.querySelectorAll('.clickable-code').forEach(el => {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            const nameInput = document.getElementById('msg-name');
            if (nameInput) {
                nameInput.value = this.dataset.name;
                nameInput.focus();
            }
        });
    });
}

// Directory CRUD
function openAddDirModal() {
    document.getElementById('dir-modal-title').textContent = '新增目录';
    document.getElementById('dir-id').value = '';
    document.getElementById('dir-name').value = '';
    document.getElementById('dir-type').value = 'normal';
    document.getElementById('dir-modal').classList.add('show');
    document.getElementById('dir-name').focus();
}

function openEditDirModal(id, name, type) {
    document.getElementById('dir-modal-title').textContent = '编辑目录';
    document.getElementById('dir-id').value = id;
    document.getElementById('dir-name').value = name;
    document.getElementById('dir-type').value = type;
    document.getElementById('dir-modal').classList.add('show');
    document.getElementById('dir-name').focus();
}

function closeDirModal() {
    document.getElementById('dir-modal').classList.remove('show');
}

async function saveDir() {
    const id = document.getElementById('dir-id').value;
    const name = document.getElementById('dir-name').value.trim();
    const type = document.getElementById('dir-type').value;

    if (!name) {
        alert('请输入目录名称');
        return;
    }

    try {
        let res;
        if (id) {
            res = await api('PUT', '?action=dir', { id: parseInt(id), name, type });
        } else {
            res = await api('POST', '?action=dir', { name, type, files: [] });
        }
        if (!res.success) throw new Error(res.error);
        closeDirModal();
        loadData();
    } catch (e) {
        alert('保存失败: ' + e.message);
    }
}

async function reorderDir(id, total) {
    const newPos = prompt('当前共有 ' + total + ' 个目录\n请输入目标位置（1-' + total + '）：');
    if (newPos === null) return;
    const pos = parseInt(newPos);
    if (isNaN(pos) || pos < 1 || pos > total) {
        alert('请输入 1-' + total + ' 之间的数字');
        return;
    }
    try {
        const res = await api('PUT', '?action=reorder_dir', { id: id, position: pos });
        if (!res.success) throw new Error(res.error);
        loadData();
    } catch (e) {
        alert('排序失败: ' + e.message);
    }
}

async function togglePinDir(id) {
    try {
        const res = await api('PUT', '?action=toggle_pin', { id: id });
        if (!res.success) throw new Error(res.error);
        loadData();
    } catch (e) {
        alert('操作失败: ' + e.message);
    }
}

function confirmDeleteDir(id, name) {
    document.getElementById('confirm-title').textContent = '确认删除目录';
    document.getElementById('confirm-msg').textContent = '确定要删除目录 "' + name + '" 吗？目录内的文件也将一并删除。';
    document.getElementById('confirm-modal').classList.add('show');
    currentConfirmCallback = async () => {
        try {
            const res = await api('DELETE', '?action=dir&id=' + id);
            if (!res.success) throw new Error(res.error);
            closeConfirmModal();
            loadData();
        } catch (e) {
            alert('删除失败: ' + e.message);
        }
    };
}

// 目录密码
var _currentLockDirId = 0;
var _currentUnlockDirId = 0;

function showDirLockModal(id, name, hasPassword) {
    _currentLockDirId = id;
    document.getElementById('dir-lock-title').textContent = '🔒 ' + name;
    document.getElementById('dir-lock-password').value = '';
    document.getElementById('dir-lock-password').placeholder = hasPassword ? '输入新密码（留空则取消密码）' : '输入密码';
    document.getElementById('dir-lock-modal').classList.add('show');
    document.getElementById('dir-lock-password').focus();
}

function closeDirLockModal() {
    document.getElementById('dir-lock-modal').classList.remove('show');
    _currentLockDirId = 0;
}

async function saveDirLock() {
    var password = document.getElementById('dir-lock-password').value;
    try {
        var res = await api('POST', '?action=dir_lock', { dir_id: _currentLockDirId, password: password });
        if (!res.success) throw new Error(res.error);
        closeDirLockModal();
        loadData();
    } catch (e) {
        alert('操作失败: ' + e.message);
    }
}

// sessionStorage 存储已解锁的目录 ID
function getUnlockedDirs() {
    try {
        return JSON.parse(sessionStorage.getItem('unlocked_dirs') || '[]');
    } catch(e) { return []; }
}

function isDirUnlocked(dirId) {
    return getUnlockedDirs().indexOf(parseInt(dirId)) !== -1;
}

function setDirUnlocked(dirId) {
    var dirs = getUnlockedDirs();
    var id = parseInt(dirId);
    if (dirs.indexOf(id) === -1) dirs.push(id);
    sessionStorage.setItem('unlocked_dirs', JSON.stringify(dirs));
}

function showDirUnlockModal(dirId, dirName) {
    _currentUnlockDirId = dirId;
    document.getElementById('dir-unlock-title').textContent = '🔒 ' + dirName;
    document.getElementById('dir-unlock-password').value = '';
    document.getElementById('dir-unlock-modal').classList.add('show');
    setTimeout(function() { document.getElementById('dir-unlock-password').focus(); }, 100);
}

function closeDirUnlockModal() {
    document.getElementById('dir-unlock-modal').classList.remove('show');
    _currentUnlockDirId = 0;
}

async function submitDirUnlock() {
    var password = document.getElementById('dir-unlock-password').value;
    if (!password) { alert('请输入密码'); return; }
    try {
        var res = await api('POST', '?action=dir_unlock', { dir_id: _currentUnlockDirId, password: password });
        if (!res.success) throw new Error(res.error);
        setDirUnlocked(_currentUnlockDirId);
        closeDirUnlockModal();
        // 展开目录
        var item = document.querySelector('.dir-item[data-dir-id="' + _currentUnlockDirId + '"]');
        if (item) {
            var wrapper = item.nextElementSibling;
            if (wrapper && wrapper.classList.contains('submenu-wrapper')) {
                wrapper.classList.add('show');
            }
        }
    } catch (e) {
        alert('密码错误');
    }
}

// 分享目录
function shareDir(dirId, dirName) {
    document.getElementById('share-dir-name').textContent = '目录：' + dirName;
    document.getElementById('share-url').value = location.origin + location.pathname + '?dir=' + dirId;
    document.getElementById('share-copied').style.display = 'none';
    document.getElementById('share-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

// 分享文件
async function shareFile(dirId, fileName) {
    document.getElementById('share-dir-name').textContent = '文件：' + fileName;
    document.getElementById('share-url').value = '正在生成短链接...';
    document.getElementById('share-copied').style.display = 'none';
    document.getElementById('share-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
    try {
        var res = await api('POST', '?action=share_create', { dir_id: dirId, file_name: fileName });
        if (res.success && res.code) {
            document.getElementById('share-url').value = location.origin + '/' + res.code;
        }
    } catch(e) {
        // 降级：长链接
        document.getElementById('share-url').value = location.origin + '/share.php?dir_id=' + dirId + '&file_name=' + encodeURIComponent(fileName);
    }
}

function closeShareModal() {
    document.getElementById('share-modal').classList.remove('show');
    document.body.style.overflow = '';
}

function copyShareUrl() {
    var input = document.getElementById('share-url');
    input.select();
    document.execCommand('copy');
    // 现代浏览器的 clipboard API
    if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value).catch(function(){});
    }
    document.getElementById('share-copied').style.display = 'block';
    setTimeout(function() {
        document.getElementById('share-copied').style.display = 'none';
    }, 2000);
}

// 页面加载时处理 ?dir=ID 参数，自动展开目录
function handleShareUrl() {
    var m = location.search.match(/[?&]dir=(\d+)/);
    if (!m) return;
    var dirId = parseInt(m[1]);
    setTimeout(function() {
        var item = document.querySelector('.dir-item[data-dir-id="' + dirId + '"]');
        if (item) {
            // 检查是否加密
            if (item.dataset.locked === '1' && !isLoggedIn && !isDirUnlocked(dirId)) {
                showDirUnlockModal(dirId, item.dataset.dir);
                return;
            }
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
            var wrapper = item.nextElementSibling;
            if (wrapper && wrapper.classList.contains('submenu-wrapper')) {
                wrapper.classList.add('show');
            }
        }
    }, 500);
}

// File CRUD
let fileLinkCount = 0;
let uploadedFiles = []; // 存储已上传的文件信息

var uploadAreaSetupDone = false;

function setupUploadArea() {
    if (uploadAreaSetupDone) return;
    uploadAreaSetupDone = true;

    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-upload-input');

    // 拖拽上传
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFile(files[0]);
        }
    });
}

function handleFileSelect(event) {
    const files = event.target.files;
    if (files.length > 0) {
        uploadFile(files[0]);
    }
}

async function uploadFile(file) {
    const progress = document.getElementById('upload-progress');
    const status = document.getElementById('upload-status');
    const progressFill = document.getElementById('upload-progress-fill');
    const uploadText = document.getElementById('upload-text');
    const uploadIcon = document.querySelector('.upload-icon');

    // 上传前显示当前文件名
    uploadText.innerHTML = '📄 ' + file.name + '<br><small style="color:#888;">上传中...</small>';
    uploadIcon.textContent = '⏳';

    progress.style.display = 'block';
    status.textContent = '上传中: ' + file.name;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = percent + '%';
                status.textContent = '上传中: ' + file.name + ' (' + percent + '%)';
            }
        });

        xhr.onload = function() {
            progress.style.display = 'none';
            progressFill.style.width = '0%';

            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        // 添加到已上传列表
                        uploadedFiles.push({
                            name: file.name,
                            url: res.url,
                            path: res.path
                        });
                        renderUploadedFiles();

                        // 自动填入链接
                        addFileLinkRow({ url: res.url, name: file.name });

                        // 如果文件名称为空，自动填入
                        const fileNameInput = document.getElementById('file-name');
                        if (!fileNameInput.value.trim()) {
                            fileNameInput.value = file.name;
                        }

                        // 上传成功后显示成功状态
                        uploadText.innerHTML = '✅ ' + file.name + '<br><small style="color:#4a7ab5;">上传成功！</small>';
                        uploadIcon.textContent = '📁';
                    } else {
                        // 上传失败，恢复默认状态
                        uploadText.innerHTML = '点击选择文件或拖拽文件到此处上传<small>支持常见文件格式，单个文件不超过1024MB</small>';
                        uploadIcon.textContent = '📤';
                        alert('上传失败: ' + (res.error || '未知错误'));
                    }
                } catch (e) {
                    // 解析失败，恢复默认状态
                    uploadText.innerHTML = '点击选择文件或拖拽文件到此处上传<small>支持常见文件格式，单个文件不超过1024MB</small>';
                    uploadIcon.textContent = '📤';
                    alert('上传失败: 响应解析错误');
                }
            } else {
                // HTTP错误，恢复默认状态
                uploadText.innerHTML = '点击选择文件或拖拽文件到此处上传<small>支持常见文件格式，单个文件不超过1024MB</small>';
                uploadIcon.textContent = '📤';
                alert('上传失败: HTTP ' + xhr.status);
            }
        };

        xhr.onerror = function() {
            progress.style.display = 'none';
            progressFill.style.width = '0%';
            // 网络错误，恢复默认状态
            uploadText.innerHTML = '点击选择文件或拖拽文件到此处上传<small>支持常见文件格式，单个文件不超过1024MB</small>';
            uploadIcon.textContent = '📤';
            alert('上传失败: 网络错误');
        };

        xhr.open('POST', '/api/upload.php');
        if (csrfToken) xhr.setRequestHeader('X-CSRF-Token', csrfToken);
        xhr.send(formData);

    } catch (e) {
        progress.style.display = 'none';
        progressFill.style.width = '0%';
        // 异常，恢复默认状态
        uploadText.innerHTML = '点击选择文件或拖拽文件到此处上传<small>支持常见文件格式，单个文件不超过1024MB</small>';
        uploadIcon.textContent = '📤';
        alert('上传失败: ' + e.message);
    }
}

function renderUploadedFiles() {
    const container = document.getElementById('uploaded-files-list');
    if (uploadedFiles.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    container.innerHTML = '<div style="font-weight:bold;margin-bottom:6px;color:#555;">已上传文件：</div>' +
        uploadedFiles.map((f, i) => '<div class="uploaded-file-item">' +
            '<span class="uploaded-file-name" title="' + f.name + '">' + f.name + '</span>' +
            '<button class="uploaded-file-remove" onclick="removeUploadedFile(' + i + ')">×</button>' +
        '</div>').join('');
}

function removeUploadedFile(index) {
    uploadedFiles.splice(index, 1);
    renderUploadedFiles();
}

function addFileLinkRow(data) {
    const container = document.getElementById('file-links-container');
    const id = 'file-link-' + (fileLinkCount++);
    const div = document.createElement('div');
    div.className = 'file-link-row';
    div.id = id;
    const url = (data && (typeof data === 'object' ? data.url : data)) ? escHtml(typeof data === 'object' ? data.url : data) : '';
    const name = (data && typeof data === 'object' && data.name) ? escHtml(data.name) : '';
    div.innerHTML =
        '<input type="text" class="link-name" placeholder="名称（选填）" value="' + name + '">' +
        '<input type="text" class="link-url" placeholder="下载链接地址，如：https://example.com/file.zip" value="' + url + '">' +
        '<button type="button" class="link-remove" onclick="removeFileLinkRow(\'' + id + '\')">×</button>';
    container.appendChild(div);
}

function removeFileLinkRow(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

function getFileLinks() {
    const links = [];
    document.querySelectorAll('#file-links-container .file-link-row').forEach(row => {
        const urlInput = row.querySelector('.link-url');
        const nameInput = row.querySelector('.link-name');
        const url = urlInput ? urlInput.value.trim() : '';
        const name = nameInput ? nameInput.value.trim() : '';
        if (url) {
            links.push({ url: url, name: name });
        }
    });
    return links;
}

function openAddFileModal(dirId) {
    document.getElementById('file-modal-title').textContent = '新增文件';
    document.getElementById('file-dir-id').value = dirId;
    document.getElementById('file-old-name').value = '';
    document.getElementById('file-name').value = '';
    document.getElementById('file-links-container').innerHTML = '';
    fileLinkCount = 0;
    uploadedFiles = [];
    renderUploadedFiles();
    document.getElementById('file-modal').classList.add('show');
    document.getElementById('file-name').focus();
    setupUploadArea();
}

function openEditFileModal(dirId, oldName, links) {
    document.getElementById('file-modal-title').textContent = '编辑文件';
    document.getElementById('file-dir-id').value = dirId;
    document.getElementById('file-old-name').value = oldName;
    document.getElementById('file-name').value = oldName;
    document.getElementById('file-links-container').innerHTML = '';
    fileLinkCount = 0;
    uploadedFiles = [];
    renderUploadedFiles();
    const linkArr = links || [];
    if (linkArr.length === 0) {
        addFileLinkRow();
    } else {
        linkArr.forEach(link => addFileLinkRow(link));
    }
    document.getElementById('file-modal').classList.add('show');
    document.getElementById('file-name').focus();
    setupUploadArea();
}

function closeFileModal() {
    document.getElementById('file-modal').classList.remove('show');
}

async function saveFile() {
    const dirId = parseInt(document.getElementById('file-dir-id').value);
    const oldName = document.getElementById('file-old-name').value;
    const fileName = document.getElementById('file-name').value.trim();
    const links = getFileLinks();

    if (!fileName) {
        alert('请输入文件名称');
        return;
    }

    try {
        let res;
        if (oldName) {
            res = await api('PUT', '?action=file', { dir_id: dirId, old_name: oldName, new_name: fileName, links: links });
        } else {
            res = await api('POST', '?action=file_create', { dir_id: dirId, file_name: fileName, links: links });
        }
        if (!res.success) throw new Error(res.error);
        closeFileModal();
        await loadData();
        // 自动展开刚上传/编辑文件所在的目录
        var dir = allDirs.find(function(d) { return parseInt(d.id) === dirId; });
        if (dir) {
            var submenu = document.querySelector('.submenu-wrapper[data-for="' + CSS.escape(dir.name) + '"]');
            if (submenu) submenu.classList.add('show');
        }
    } catch (e) {
        alert('保存失败: ' + e.message);
    }
}

var _prevBodyOverflow = '';

function showDownloadChoice(linksJson) {
    const links = JSON.parse(linksJson);
    const container = document.getElementById('download-links-container');
    container.innerHTML = links.map((link, i) =>
        '<a href="' + escHtml(link.url) + '" target="_blank" rel="noopener noreferrer" class="download-link-item" style="display:flex;align-items:center;padding:6px 10px;border:1px solid #ddd;border-radius:4px;margin-bottom:4px;text-decoration:none;color:#333;background:#f9f9f9;" onmouseover="this.style.background=\'#e8f4ff\'" onmouseout="this.style.background=\'#f9f9f9\'">' +
            '<span style="font-weight:500;flex-shrink:0;margin-right:8px;">' + (link.name || '下载' + (i + 1)) + '</span>' +
            '<span style="font-size:11px;color:#888;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escHtml(link.url) + '</span>' +
        '</a>'
    ).join('');
    document.getElementById('download-modal').classList.add('show');
    _prevBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
}

function closeDownloadModal() {
    document.getElementById('download-modal').classList.remove('show');
    document.body.style.overflow = _prevBodyOverflow;
    document.getElementById('download-modal').style.marginTop = '';
}

function confirmDeleteFile(dirId, fileName) {
    document.getElementById('confirm-title').textContent = '确认删除文件';
    document.getElementById('confirm-msg').textContent = '确定要删除文件 "' + fileName + '" 吗？';
    document.getElementById('confirm-modal').classList.add('show');
    currentConfirmCallback = async () => {
        try {
            const res = await api('DELETE', '?action=file&dir_id=' + dirId + '&file_name=' + encodeURIComponent(fileName));
            if (!res.success) throw new Error(res.error);
            closeConfirmModal();
            loadData();
        } catch (e) {
            alert('删除失败: ' + e.message);
        }
    };
}

// Message CRUD
function openAddMsgModal() {
    document.getElementById('msg-modal-title').textContent = '新增留言';
    document.getElementById('msg-id').value = '';
    document.getElementById('msg-name').value = '';
    document.getElementById('msg-content').value = '';
    document.getElementById('msg-modal').classList.add('show');
    document.getElementById('msg-name').focus();
}

function openEditMsgModal(id, code, content, name) {
    document.getElementById('msg-modal-title').textContent = '编辑留言';
    document.getElementById('msg-id').value = id;
    document.getElementById('msg-name').value = name || '';
    document.getElementById('msg-content').value = content;
    document.getElementById('msg-modal').classList.add('show');
    document.getElementById('msg-name').focus();
}

function closeMsgModal() {
    document.getElementById('msg-modal').classList.remove('show');
}

async function saveMsg() {
    const id = document.getElementById('msg-id').value;
    const name = document.getElementById('msg-name').value.trim();
    const content = document.getElementById('msg-content').value.trim();

    try {
        if (id) {
            const res = await api('PUT', '?action=message', { id: parseInt(id), name, content });
            if (!res.success) throw new Error(res.error);
        } else {
            const res = await api('POST', '?action=message', { name, content });
            if (!res.success) throw new Error(res.error);
        }
        closeMsgModal();
        loadData();
    } catch (e) {
        alert('保存失败: ' + e.message);
    }
}

function confirmDeleteMsg(id) {
    document.getElementById('confirm-title').textContent = '确认删除留言';
    document.getElementById('confirm-msg').textContent = '确定要删除这条留言吗？';
    document.getElementById('confirm-modal').classList.add('show');
    currentConfirmCallback = async () => {
        try {
            const res = await api('DELETE', '?action=message&id=' + id);
            if (!res.success) throw new Error(res.error);
            closeConfirmModal();
            loadData();
        } catch (e) {
            alert('删除失败: ' + e.message);
        }
    };
}

// Confirm modal
function closeConfirmModal() {
    document.getElementById('confirm-modal').classList.remove('show');
    currentConfirmCallback = null;
}

// Download modal
function closeDownloadModal() {
    document.getElementById('download-modal').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('download-modal').style.marginTop = '';
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        // 点击遮罩关闭弹窗，但不关闭预览弹窗（预览弹窗通过按钮关闭）
        if (e.target.id !== 'preview-modal') {
            e.target.classList.remove('show');
        }
    }
});

// 文件名点击 → 预览
document.addEventListener('click', function(e) {
    var nameText = e.target.closest('.file-name-text');
    if (!nameText) return;
    e.preventDefault();
    e.stopPropagation();
    var dirId = nameText.dataset.dirId;
    var fileName = cleanFileName(nameText.dataset.filename);
    var linksJson = nameText.dataset.links;
    previewFile(dirId, fileName, linksJson);
});

var _prevPreviewBodyOverflow = '';

function getPreviewUrl(dirId, fileName, linksJson) {
    return 'api/index.php?action=file_download&dir_id=' + dirId + '&file_name=' + encodeURIComponent(fileName) + '&inline=1';
}

function getFileExt(fileName) {
    return fileName.replace(/^📄\s*/, '').split('.').pop().toLowerCase();
}

function isPreviewableImage(ext) {
    return ['jpg','jpeg','png','gif','bmp','webp','svg','ico','apng','avif','tiff','tif','jpe','jfi','jif','jp2','j2k','jxr','wbmp'].indexOf(ext) !== -1;
}

function isPreviewableDoc(ext) {
    return ext === 'pdf';
}

function isPreviewableText(ext) {
    return ['html','htm','xhtml','xml','json','css','scss','sass','less','styl','js','jsx','ts','tsx','mjs','cjs','vue','svelte','astro','php','php3','php4','php5','php7','php8','phtml','pht','phar','ini','conf','env','sql','txt','log','csv','md','yml','yaml','jsonc','map'].indexOf(ext) !== -1;
}

function isPreviewableVideo(ext) {
    return ['mp4','webm','ogg'].indexOf(ext) !== -1;
}

function isPreviewableAudio(ext) {
    return ['mp3','wav','flac'].indexOf(ext) !== -1;
}

function isPreviewableArchive(ext) {
    return ext === 'zip';
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function previewFile(dirId, fileName, linksJson) {
    var ext = getFileExt(fileName);
    var url = getPreviewUrl(dirId, fileName, linksJson);
    var dlUrl = url.replace('&inline=1', '');
    var title = fileName.replace(/^📄\s*/, '');
    document.getElementById('preview-title').textContent = '📄 ' + title;
    var content = document.getElementById('preview-content');
    document.getElementById('preview-modal').classList.add('show');
    _prevPreviewBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    if (isPreviewableImage(ext)) {
        content.innerHTML = '<img src="' + escHtml(url) + '" alt="' + escHtml(title) + '" style="max-width:100%;max-height:70vh;object-fit:contain;display:block;">';
        var img = content.querySelector('img');
        img.onerror = function() {
            content.innerHTML = '<div class="preview-unsupported">图片加载失败<p style="margin-top:8px;font-size:12px;color:#aaa;">文件可能不存在或格式不受支持</p><p><a class="preview-download-btn" href="' + escHtml(dlUrl) + '">下载查看</a></p></div>';
        };
    } else if (isPreviewableDoc(ext)) {
        content.innerHTML = '<iframe src="' + escHtml(url) + '" style="width:100%;height:70vh;border:none;background:#fff;"></iframe>';
    } else if (isPreviewableVideo(ext)) {
        content.innerHTML = '<video controls style="max-width:100%;max-height:70vh;display:block;"><source src="' + escHtml(url) + '"></video>';
    } else if (isPreviewableAudio(ext)) {
        var pid = 'ap' + Date.now();
        content.innerHTML =
            '<div class="audio-player" data-player="' + pid + '">' +
                '<div class="audio-player-icon">🎵</div>' +
                '<div class="audio-player-title">' + escHtml(title) + '</div>' +
                '<div class="audio-player-controls">' +
                    '<button class="ap-btn ap-btn-play" data-action="play" title="播放">▶</button>' +
                    '<button class="ap-btn ap-btn-stop" data-action="stop" title="停止">■</button>' +
                    '<div class="ap-progress-wrap"><input type="range" class="ap-range" value="0" min="0" max="1000" step="1"></div>' +
                    '<span class="ap-time">00:00 / 00:00</span>' +
                '</div>' +
                '<audio class="ap-audio" src="' + escHtml(url) + '" preload="metadata"></audio>' +
            '</div>';
        initAudioPlayer(content.querySelector('.audio-player'));
    } else if (isPreviewableText(ext)) {
        content.innerHTML = '<div style="text-align:center;padding:30px;color:#888;">加载中...</div>';
        fetch(url).then(function(r) { if (!r.ok) throw Error(); return r.text(); }).then(function(text) {
            content.innerHTML = '<pre style="margin:0;padding:16px;background:#1e1e1e;color:#d4d4d4;font-size:12px;font-family:Menlo,Monaco,Consolas,monospace;white-space:pre-wrap;word-wrap:break-word;max-height:70vh;overflow:auto;border-radius:6px;line-height:1.6;"><code>' + escHtml(text) + '</code></pre>';
        }).catch(function() {
            content.innerHTML = '<div class="preview-unsupported">文件加载失败<p style="margin-top:8px;font-size:12px;color:#aaa;">文件可能不存在或过大</p><p><a class="preview-download-btn" href="' + escHtml(dlUrl) + '">下载查看</a></p></div>';
        });
    } else if (isPreviewableArchive(ext)) {
        content.innerHTML = '<div style="text-align:center;padding:30px;color:#888;">正在解析压缩包...</div>';
        fetch(url.replace('&inline=1', ''))
            .then(function(r) { if (!r.ok) throw Error(); return r.arrayBuffer(); })
            .then(function(buf) { return JSZip.loadAsync(buf); })
            .then(function(zip) {
                var tree = {};
                zip.forEach(function(path, entry) {
                    var parts = path.split('/');
                    var node = tree;
                    for (var i = 0; i < parts.length; i++) {
                        var p = parts[i];
                        if (!p) continue;
                        if (!node[p]) node[p] = { _d: i < parts.length - 1 || entry.dir, _c: {} };
                        if (i < parts.length - 1) node = node[p]._c;
                    }
                });

                var currentPath = [];

                function getNode(pathArr) {
                    var node = tree;
                    for (var i = 0; i < pathArr.length; i++) { node = node[pathArr[i]]._c; }
                    return node;
                }

                function refresh() {
                    var node = getNode(currentPath);
                    document.getElementById('preview-title').textContent = '📦 ' + (currentPath.length > 0 ? currentPath[currentPath.length - 1] + '/' : title);
                    var names = Object.keys(node).sort(function(a, b) {
                        return node[a]._d === node[b]._d ? a.localeCompare(b) : (node[a]._d ? -1 : 1);
                    });
                    var html = '<div class="zip-file-list">';
                    if (currentPath.length > 0) {
                        html += '<div class="zip-file-item zip-file-back" data-action="up"><span class="zip-file-icon">↩</span><span class="zip-file-name">..</span></div>';
                        html += '<div class="zip-breadcrumb"><span data-idx="0">根</span>';
                        for (var i = 0; i < currentPath.length; i++) {
                            html += '<span class="zip-breadcrumb-sep">/</span><span data-idx="' + (i + 1) + '">' + escHtml(currentPath[i]) + '</span>';
                        }
                        html += '</div>';
                    }
                    html += '<div class="zip-file-count" style="text-align:left;padding-left:12px;">' + names.length + ' 项</div>';
                    names.forEach(function(name) {
                        var e = node[name];
                        html += '<div class="zip-file-item' + (e._d ? ' is-dir' : '') + '"' + (e._d ? ' data-dir="' + escHtml(name) + '"' : '') + '><span class="zip-file-icon">' + (e._d ? '📁' : '📄') + '</span><span class="zip-file-name">' + escHtml(name) + '</span></div>';
                    });
                    html += '</div>';
                    content.innerHTML = html;

                    content.querySelectorAll('[data-dir]').forEach(function(el) {
                        el.addEventListener('click', function() { currentPath.push(this.dataset.dir); refresh(); });
                    });
                    var backBtn = content.querySelector('[data-action="up"]');
                    if (backBtn) backBtn.addEventListener('click', function() { currentPath.pop(); refresh(); });
                    content.querySelectorAll('.zip-breadcrumb span[data-idx]').forEach(function(el) {
                        el.addEventListener('click', function() { currentPath = currentPath.slice(0, parseInt(this.dataset.idx)); refresh(); });
                    });
                }

                refresh();
            }).catch(function() {
                content.innerHTML = '<div class="preview-unsupported">压缩包解析失败<p style="margin-top:8px;font-size:12px;color:#aaa;">文件可能损坏或格式不兼容</p><p><a class="preview-download-btn" href="' + escHtml(dlUrl) + '">下载查看</a></p></div>';
            });
    } else {
        content.innerHTML = '<div class="preview-unsupported">不支持 .' + ext + ' 格式在线预览<p style="margin-top:8px;font-size:12px;color:#aaa;">请下载后使用对应软件查看</p><p><a class="preview-download-btn" href="' + escHtml(dlUrl) + '">下载文件</a></p></div>';
    }
}

function closePreviewModal() {
    // 停止正在播放的音频
    var playing = document.querySelector('.audio-player.playing');
    if (playing) { var a = playing.querySelector('audio'); if (a) { a.pause(); a.currentTime = 0; } }
    document.getElementById('preview-modal').classList.remove('show');
    document.body.style.overflow = _prevPreviewBodyOverflow;
    document.getElementById('preview-content').innerHTML = '';
}

function formatTime(sec) {
    var m = Math.floor(sec / 60);
    var s = Math.floor(sec % 60);
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

function initAudioPlayer(player) {
    var audio = player.querySelector('audio');
    var playBtn = player.querySelector('[data-action="play"]');
    var stopBtn = player.querySelector('[data-action="stop"]');
    var range = player.querySelector('.ap-range');
    var timeDisplay = player.querySelector('.ap-time');
    var ignoreTimeUpdate = false;

    function updateProgress() {
        if (ignoreTimeUpdate) return;
        if (audio.duration && isFinite(audio.duration)) {
            var val = (audio.currentTime / audio.duration) * 1000;
            range.value = Math.round(val);
            var pct = (audio.currentTime / audio.duration) * 100;
            range.style.background = 'linear-gradient(to right, #4a9eff 0%, #4a9eff ' + pct + '%, rgba(255,255,255,0.15) ' + pct + '%, rgba(255,255,255,0.15) 100%)';
        }
        timeDisplay.textContent = formatTime(audio.currentTime) + ' / ' + (audio.duration && isFinite(audio.duration) ? formatTime(audio.duration) : '00:00');
    }

    range.addEventListener('input', function() {
        if (!audio.duration || !isFinite(audio.duration)) return;
        audio.currentTime = (range.value / 1000) * audio.duration;
        ignoreTimeUpdate = true;
    });

    // seeked 事件在 audio 跳转完成后触发，之后恢复 timeupdate 同步
    audio.addEventListener('seeked', function() {
        ignoreTimeUpdate = false;
    });

    playBtn.addEventListener('click', function() {
        if (audio.paused) {
            audio.play();
            playBtn.textContent = '⏸';
            playBtn.title = '暂停';
            player.classList.add('playing');
        } else {
            audio.pause();
            playBtn.textContent = '▶';
            playBtn.title = '播放';
            player.classList.remove('playing');
        }
    });

    stopBtn.addEventListener('click', function() {
        audio.pause();
        audio.currentTime = 0;
        range.value = 0;
        playBtn.textContent = '▶';
        playBtn.title = '播放';
        player.classList.remove('playing');
        if (audio.duration && isFinite(audio.duration)) {
            timeDisplay.textContent = '00:00 / ' + formatTime(audio.duration);
        }
    });

    audio.addEventListener('timeupdate', updateProgress);

    audio.addEventListener('loadedmetadata', function() {
        range.max = 1000;
        timeDisplay.textContent = '00:00 / ' + formatTime(audio.duration);
    });

    audio.addEventListener('ended', function() {
        playBtn.textContent = '▶';
        playBtn.title = '播放';
        player.classList.remove('playing');
        audio.currentTime = 0;
        range.value = 0;
        if (audio.duration && isFinite(audio.duration)) {
            timeDisplay.textContent = '00:00 / ' + formatTime(audio.duration);
        }
    });

    audio.addEventListener('error', function() {
        player.innerHTML = '<div class="preview-unsupported" style="color:#ccc;">音频加载失败<p style="margin-top:8px;font-size:12px;color:rgba(255,255,255,0.5);">文件可能不存在或格式不受支持</p></div>';
    });
}

function confirmAction() {
    if (currentConfirmCallback) {
        currentConfirmCallback();
    }
}

// Toggle all
document.getElementById('toggle-all').addEventListener('change', function() {
    document.querySelectorAll('.submenu-wrapper').forEach(w => {
        w.classList.toggle('show', this.checked);
    });
});

// Helpers
function esc(s) {
    return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    const m = ('0' + (d.getMonth() + 1)).slice(-2);
    const day = ('0' + d.getDate()).slice(-2);
    const hh = ('0' + d.getHours()).slice(-2);
    const mm = ('0' + d.getMinutes()).slice(-2);
    return m + '-' + day + ' ' + hh + ':' + mm;
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

// Init
async function init() {
    await checkLogin();
    await loadData();
    handleShareUrl();
}
init();
</script>

</body>
</html>
