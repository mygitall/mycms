/**
 * CMS Frontend API 工具
 * 所有页面共享：认证状态管理、API 调用、Toast 提示
 */
(function() {
  'use strict';

  // BASE_PATH：优先使用 index.php 注入的值，其次从 URL 自动检测
  var path = window.location.pathname;
  var BASE = window.__BASE_PATH__ ||
    (function() {
      var i = path.indexOf('/frontend');
      return i > 0 ? path.substring(0, i) : '';
    })();

  // API 端点
  window.CMS = {
    BASE: BASE,
    articleList:   BASE + '/article/api/list',
    articleView:   BASE + '/article/api/view',
    searchList:    BASE + '/search/api/list',
    csrfToken:     BASE + '/api/csrf_token',
    login:         BASE + '/api/login',
    register:      BASE + '/api/register',
    validateToken: BASE + '/api/validate_token',
    favorite:      BASE + '/article/api/favorite',
    unfavorite:    BASE + '/article/api/unfavorite',
    favoriteStatus:BASE + '/article/api/favorite_status',
    favorites:     BASE + '/article/api/favorites',
    favoritesStats:BASE + '/article/api/favorites_stats',

    // Auth state
    _token: null,
    _user: null,

    // 初始化：从 localStorage 恢复登录态
    init: function() {
      var saved = localStorage.getItem('cms_auth');
      if (saved) {
        try {
          var auth = JSON.parse(saved);
          this._token = auth.token;
          this._user = auth.user;
        } catch(e) {
          localStorage.removeItem('cms_auth');
        }
      }
    },

    // 保存登录态
    saveAuth: function(token, user) {
      this._token = token;
      this._user = user;
      localStorage.setItem('cms_auth', JSON.stringify({ token: token, user: user }));
    },

    // 清除登录态
    clearAuth: function() {
      this._token = null;
      this._user = null;
      localStorage.removeItem('cms_auth');
    },

    // 是否已登录
    isLoggedIn: function() {
      return !!this._token;
    },

    // 通用 fetch（自动处理 token + CSRF）
    fetch: function(url, options) {
      options = options || {};
      var headers = options.headers || {};
      if (typeof headers === 'object' && !(headers instanceof Headers)) {
        headers['Content-Type'] = headers['Content-Type'] || 'application/json';
        if (this._token) {
          headers['X-Token'] = this._token;
        }
      }
      options.headers = headers;
      return fetch(url, options).then(function(r) {
        return r.json().then(function(data) {
          if (data.code === 401) {
            CMS.clearAuth();
            if (typeof updateUI === 'function') updateUI();
          }
          return data;
        });
      });
    },

    // POST JSON
    post: function(url, body) {
      return this.fetch(url, {
        method: 'POST',
        body: JSON.stringify(body || {})
      });
    },

    // GET
    get: function(url) {
      return this.fetch(url, { method: 'GET' });
    },

    // CSRF token
    getCSRF: function() {
      return this.fetch(this.csrfToken, { method: 'GET', credentials: 'include' });
    }
  };

  CMS.init();

  // Toast
  window.showToast = function(msg, type) {
    type = type || 'success';
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
  };

  // 获取 URL 参数
  window.getParam = function(name) {
    return new URLSearchParams(window.location.search).get(name) || '';
  };

  // HTML 转义
  window.escapeHtml = function(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  };

  // 格式化日期
  window.formatDate = function(dateStr) {
    if (!dateStr) return '-';
    var d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    return d.getFullYear() + '-' +
      String(d.getMonth()+1).padStart(2,'0') + '-' +
      String(d.getDate()).padStart(2,'0');
  };

  // 截断文本
  window.truncate = function(str, len) {
    len = len || 120;
    if (!str) return '';
    str = str.replace(/<[^>]+>/g, '');
    if (str.length <= len) return str;
    return str.substring(0, len) + '...';
  };

  // 渲染文章卡片
  window.renderArticleCard = function(item) {
    var title = escapeHtml(item.title || '无标题');
    var content = truncate(item.content, 100);
    var date = formatDate(item.created_at || item.date);
    var cat = escapeHtml(item.category || '');
    var views = item.view_count || item.views || 0;
    var url = CMS.BASE + '/detail?id=' + item.id;
    var initial = title.charAt(0);
    return '<div class="article-card" onclick="location.href=\'' + url + '\'">' +
      '<div class="cover">' + initial + '</div>' +
      '<div class="body">' +
        '<h3>' + title + '</h3>' +
        '<p>' + content + '</p>' +
        '<div class="meta">' +
          '<span>' + date + '</span>' +
          (cat ? '<span class="tag">' + cat + '</span>' : '') +
          '<span>' + views + ' 阅读</span>' +
        '</div>' +
      '</div>' +
    '</div>';
  };

  // 渲染文章列表项
  window.renderArticleItem = function(item) {
    var title = escapeHtml(item.title || '无标题');
    var content = truncate(item.content, 150);
    var date = formatDate(item.created_at || item.date);
    var cat = escapeHtml(item.category || '');
    var views = item.view_count || item.views || 0;
    var url = CMS.BASE + '/detail?id=' + item.id;
    var initial = title.charAt(0);
    return '<div class="item" onclick="location.href=\'' + url + '\'" style="cursor:pointer">' +
      '<div class="thumb">' + initial + '</div>' +
      '<div class="info">' +
        '<h3>' + title + '</h3>' +
        '<p>' + content + '</p>' +
        '<div class="meta">' +
          '<span>' + date + '</span>' +
          (cat ? '<span>' + cat + '</span>' : '') +
          '<span>' + views + ' 阅读</span>' +
        '</div>' +
      '</div>' +
    '</div>';
  };

})();
