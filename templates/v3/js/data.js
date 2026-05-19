/**
 * 文轩 WenXuan - 模拟数据 & localStorage 管理
 * 包含文章数据、用户认证、收藏、阅读历史等所有状态操作
 */

/* ============================================
   API 基础配置
   ============================================ */
// 文章模块 API 地址
const ARTICLE_API_BASE = '/article/api';

// 主系统 API 地址（用于登录认证）
const API_BASE = '/api';

/* ============================================
   localStorage 键名
   ============================================ */
const LS = {
  KEY_TOKEN:    'wx_token',      // 登录 token
  KEY_USER_ID:  'wx_user_id',    // 用户 ID
  KEY_USERNAME: 'wx_username',   // 用户名
  KEY_FAVS:     'wx_favs',       // { [username]: [articleId, ...] } 本地缓存
  KEY_HISTORY:  'wx_history',    // { [username]: [{ id, at }] }
  KEY_ARTICLES: 'wx_articles',   // 文章列表缓存
  KEY_ARTICLE_CATEGORIES: 'wx_article_cats', // 文章分类缓存
};

/* ============================================
   用户认证（对接真实 API）
   ============================================ */
const Auth = {
  getToken() {
    return localStorage.getItem(LS.KEY_TOKEN) || null;
  },
  getUserId() {
    return localStorage.getItem(LS.KEY_USER_ID) || null;
  },
  getUsername() {
    return localStorage.getItem(LS.KEY_USERNAME) || null;
  },
  getDevice() {
    // 生成简略设备标识
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Android/.test(ua)) return 'Android';
    return 'Web';
  },
  saveUser(data) {
    localStorage.setItem(LS.KEY_TOKEN,    data.token);
    localStorage.setItem(LS.KEY_USER_ID,   data.user_id);
    localStorage.setItem(LS.KEY_USERNAME,  data.username);
  },
  clearUser() {
    localStorage.removeItem(LS.KEY_TOKEN);
    localStorage.removeItem(LS.KEY_USER_ID);
    localStorage.removeItem(LS.KEY_USERNAME);
  },
  getCurrent() {
    return this.getToken() ? this.getUsername() : null;
  },

  /**
   * 注册
   * @returns Promise<{ ok: boolean, msg: string, data?: object }>
   */
  register(username, password) {
    return fetch(`${API_BASE}/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username,
        password,
        device: this.getDevice(),
      }),
    })
    .then(r => r.json())
    .then(resp => {
      if (resp.code === 0) {
        this.saveUser(resp.data);
        return { ok: true, msg: resp.msg, data: resp.data };
      }
      return { ok: false, msg: resp.msg || '注册失败' };
    })
    .catch(() => ({ ok: false, msg: '网络请求失败，请检查网络' }));
  },

  /**
   * 登录
   * @returns Promise<{ ok: boolean, msg: string, data?: object }>
   */
  login(username, password) {
    return fetch(`${API_BASE}/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username,
        password,
        device: this.getDevice(),
      }),
    })
    .then(r => r.json())
    .then(resp => {
      if (resp.code === 0) {
        this.saveUser(resp.data);
        return { ok: true, msg: resp.msg, data: resp.data };
      }
      return { ok: false, msg: resp.msg || '登录失败' };
    })
    .catch(() => ({ ok: false, msg: '网络请求失败，请检查网络' }));
  },

  logout() {
    this.clearUser();
  },

  /**
   * 检查 token 是否存在（用于判断是否已登录）
   */
  isLoggedIn() {
    return !!this.getToken();
  },
};

/* ============================================
   文章数据管理（对接文章模块 API）
   ============================================ */

// CSRF Token 缓存
let CSRF_TOKEN = null;

async function getCsrfToken() {
  if (CSRF_TOKEN) return CSRF_TOKEN;
  try {
    const resp = await fetch(`${location.protocol}//${location.host}/api/csrf_token`);
    CSRF_TOKEN = resp.headers.get('X-CSRF-Token') || document.cookie.match(/csrf_token=([^;]+)/)?.[1] || '';
  } catch (e) {
    CSRF_TOKEN = '';
  }
  return CSRF_TOKEN;
}
let ARTICLES = [];
let ARTICLE_CATEGORIES = [];
let ARTICLES_LOADED = false;
let ARTICLES_LOADING = false;

// 文章数据格式化：将 API 返回的数据格式化为前端需要的格式
function formatArticle(apiArticle) {
  const tags = apiArticle.tags ? apiArticle.tags.split(',').map(t => t.trim()).filter(Boolean) : [];
  const authorColors = ['#3498db', '#e67e22', '#9b59b6', '#2ecc71', '#e74c3c', '#1abc9c', '#e91e63'];
  const authorColor = authorColors[(apiArticle.author_id || 0) % authorColors.length];

  // 兼容后端可能使用的内容字段名
  const rawContent =
    apiArticle.content ||
    apiArticle.body ||
    apiArticle.text ||
    apiArticle.post_content ||
    apiArticle.article_content ||
    '';

  // 解析日期
  let date = apiArticle.published_at || apiArticle.created_at || '';
  if (date) {
    date = date.split(' ')[0];
  }

  // 计算阅读时间（按每 500 字估算）
  const contentLength = rawContent.length;
  const readTime = Math.max(1, Math.ceil(contentLength / 500));

  return {
    id: apiArticle.id,
    title: apiArticle.title || '',
    desc: rawContent ? rawContent.replace(/<[^>]+>/g, '').substring(0, 150) + '...' : '',
    content: rawContent,
    category: apiArticle.category || '',
    categoryName: apiArticle.category || '未分类',
    cover: apiArticle.cover_image || apiArticle.cover || 'https://images.unsplash.com/photo-1484417894907-623942c8ee29?w=800&q=80',
    author: apiArticle.author_name || apiArticle.author || '未知作者',
    authorAvatarColor: authorColor,
    authorBio: apiArticle.author_bio || '文章作者',
    tags: tags,
    readTime: readTime,
    date: date,
    views: apiArticle.view_count || apiArticle.views || 0,
    like_count: apiArticle.like_count || 0,
    // 保留原始数据，供兜底使用
    _raw: apiArticle
  };
}

const Articles = {
  /**
   * 获取所有文章（从 API 或缓存）
   * @param {Object} options - 查询选项
   * @param {number} options.page - 页码
   * @param {number} options.pageSize - 每页条数
   * @param {string} options.keyword - 关键词搜索
   * @param {string} options.category - 分类筛选
   * @param {boolean} options.forceRefresh - 强制刷新缓存
   * @returns Promise<Array>
   */
  async getAll(options = {}) {
    const { page = 1, pageSize = 100, keyword = '', category = '', forceRefresh = false } = options;

    // 如果只需要文章列表（不分页），且缓存已加载，直接返回缓存
    if (!forceRefresh && page === 1 && pageSize >= 100 && !keyword && !category && ARTICLES_LOADED) {
      return ARTICLES;
    }

    // 调用 API
    return this.fetchFromAPI({ page, page_size: pageSize, keyword, category });
  },

  /**
   * 从 API 获取文章列表
   */
  async fetchFromAPI(params = {}) {
    try {
      const queryBody = {
        page: params.page || 1,
        page_size: Math.min(params.page_size || 100, 100),
        status: '1' // 只获取已发布的文章
      };
      if (params.keyword) queryBody.keyword = params.keyword;
      if (params.category) queryBody.category = params.category;

      const token = Auth.getToken();
      if (token) queryBody._token = token;

      const headers = { 'Content-Type': 'application/json' };
      if (token) headers['Authorization'] = `Bearer ${token}`;
      const csrf = await getCsrfToken();
      if (csrf) headers['X-CSRF-Token'] = csrf;

      const response = await fetch(`${ARTICLE_API_BASE}/list`, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(queryBody),
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (result.code === 0 && result.data) {
        // 格式化文章数据
        const articles = (result.data.list || []).map(formatArticle);

        // 保存分类
        if (result.data.categories) {
          ARTICLE_CATEGORIES = result.data.categories;
          localStorage.setItem(LS.KEY_ARTICLE_CATEGORIES, JSON.stringify(ARTICLE_CATEGORIES));
        }

        // 如果是首次加载完整列表，更新缓存
        if (params.page === 1 && params.page_size >= 100 && !params.keyword && !params.category) {
          ARTICLES = articles;
          ARTICLES_LOADED = true;
          localStorage.setItem(LS.KEY_ARTICLES, JSON.stringify(articles));
        }

        return articles;
      }

      console.warn('获取文章列表失败:', result.msg);
      return [];
    } catch (error) {
      console.error('获取文章列表出错:', error);

      // 如果 API 调用失败，尝试从本地缓存加载
      if (!ARTICLES_LOADED) {
        const cached = localStorage.getItem(LS.KEY_ARTICLES);
        if (cached) {
          try {
            ARTICLES = JSON.parse(cached);
            ARTICLES_LOADED = true;
            console.info('已从本地缓存加载文章数据');
            return ARTICLES;
          } catch (e) {
            console.error('解析文章缓存失败:', e);
          }
        }
      }

      return ARTICLES; // 返回可能已缓存的数据
    }
  },

  /**
   * 根据 ID 获取单篇文章详情（包含完整正文）
   * @param {number} id - 文章 ID
   * @returns Promise<object|null>
   */
  async getById(id) {
    const numId = parseInt(id);
    const cached = ARTICLES.find(a => a.id === numId);

    // 缓存中有完整正文，直接用
    if (cached && cached.content && cached.content.length > 50) {
      return cached;
    }

    // 缓存正文为空，尝试 /detail 接口
    try {
      const token = Auth.getToken();
      const headers = { 'Content-Type': 'application/json' };
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
        headers['X-CSRF-Token'] = await getCsrfToken();
      }
      const response = await fetch(`${ARTICLE_API_BASE}/detail`, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({ _token: token, id: numId }),
        credentials: 'include'
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const result = await response.json();
      if (result.code === 0 && result.data) {
        const article = formatArticle(result.data);
        const idx = ARTICLES.findIndex(a => a.id === article.id);
        if (idx !== -1) { ARTICLES[idx] = article; localStorage.setItem(LS.KEY_ARTICLES, JSON.stringify(ARTICLES)); }
        return article;
      }
    } catch (error) {
      console.error('[Articles] /detail 接口失败:', error.message);
    }

    // /detail 失败，尝试从 /list 中找（如果有的话）
    const fromList = ARTICLES.find(a => a.id === numId);
    if (fromList && fromList.content && fromList.content.length > 10) {
      return fromList;
    }

    console.warn('[Articles] 未找到文章, id:', numId);
    return fromList || null;
  },

  /**
   * 获取分类列表
   */
  getCategories() {
    return ARTICLE_CATEGORIES;
  },

  /**
   * 按分类筛选
   */
  filterByCategory(category) {
    if (!category || category === 'all') return ARTICLES;
    return ARTICLES.filter(a => a.category === category);
  },

  /**
   * 初始化文章数据（在页面加载时调用）
   * @param {boolean} forceRefresh - 是否强制刷新
   */
  async init(forceRefresh = false) {
    if (ARTICLES_LOADING) return;
    if (ARTICLES_LOADED && !forceRefresh) return;

    ARTICLES_LOADING = true;

    // 先尝试从缓存加载（快速显示）
    const cached = localStorage.getItem(LS.KEY_ARTICLES);
    if (cached) {
      try {
        ARTICLES = JSON.parse(cached);
        ARTICLES_LOADED = true;
      } catch (e) {
        console.error('解析文章缓存失败:', e);
      }
    }

    const cachedCats = localStorage.getItem(LS.KEY_ARTICLE_CATEGORIES);
    if (cachedCats) {
      try {
        ARTICLE_CATEGORIES = JSON.parse(cachedCats);
      } catch (e) {}
    }

    // 从 API 刷新数据
    await this.fetchFromAPI({ page: 1, page_size: 100 });

    ARTICLES_LOADING = false;
  },
};

/* ============================================
   收藏（对接后端 API + 本地缓存）
   ============================================ */
const Favorites = {
  // 本地缓存（ID 列表）
  _localIds: [],

  /**
   * 初始化收藏数据
   * @param {boolean} forceRefresh - 是否强制从后端刷新
   */
  async init(forceRefresh = false) {
    // 先加载本地缓存
    const username = Auth.getUsername();
    if (username) {
      const all = JSON.parse(localStorage.getItem(LS.KEY_FAVS) || '{}');
      this._localIds = all[username] || [];
    }

    // 如果已登录，尝试从后端获取最新数据
    if (Auth.isLoggedIn() && (forceRefresh || this._localIds.length === 0)) {
      await this.syncFromServer();
    }
  },

  /**
   * 从后端服务器同步收藏数据
   */
  async syncFromServer() {
    if (!Auth.isLoggedIn()) return;

    try {
      const token = Auth.getToken();
      const csrf = await getCsrfToken();
      const response = await fetch(`${ARTICLE_API_BASE}/favorites`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({
          _token: token,
          page: 1,
          page_size: 1000
        }),
        credentials: 'include'
      });

      const result = await response.json();

      if (result.code === 0 && result.data) {
        // 提取收藏的文章 ID
        const serverIds = (result.data.list || []).map(item => item.id);

        // 合并本地和服务器数据（取并集）
        const mergedIds = [...new Set([...this._localIds, ...serverIds])];

        // 更新本地缓存
        this._saveLocal(mergedIds);

        return mergedIds;
      }
    } catch (error) {
      console.error('同步收藏数据失败:', error);
    }

    return this._localIds;
  },

  /**
   * 获取所有收藏的文章 ID
   */
  getAll() {
    return this._localIds;
  },

  /**
   * 保存到本地 localStorage
   */
  _saveLocal(ids) {
    this._localIds = ids;
    const username = Auth.getUsername();
    if (username) {
      const all = JSON.parse(localStorage.getItem(LS.KEY_FAVS) || '{}');
      all[username] = ids;
      localStorage.setItem(LS.KEY_FAVS, JSON.stringify(all));
    }
  },

  /**
   * 检查文章是否已收藏
   */
  isFaved(id) {
    return this._localIds.includes(parseInt(id));
  },

  /**
   * 切换收藏状态（同时更新后端和本地）
   * @param {number} id - 文章 ID
   * @returns {boolean|null} true=收藏成功, false=取消收藏, null=未登录
   */
  async toggle(id) {
    const user = Auth.getUsername();
    if (!user) return null;

    const articleId = parseInt(id);
    const idx = this._localIds.indexOf(articleId);
    const isNowFaved = idx === -1; // 操作后是否为收藏状态

    // 先更新本地（乐观更新）
    if (idx === -1) {
      this._localIds.push(articleId);
    } else {
      this._localIds.splice(idx, 1);
    }
    this._saveLocal(this._localIds);

    // 调用后端 API
    try {
      const token = Auth.getToken();
      const apiPath = isNowFaved ? '/favorite' : '/unfavorite';
      const body = { _token: token, article_id: articleId };

      const csrf = await getCsrfToken();
      const response = await fetch(`${ARTICLE_API_BASE}${apiPath}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify(body),
        credentials: 'include'
      });

      const result = await response.json();

      if (result.code !== 0 && result.code !== 200) {
        // API 调用失败，回滚本地状态
        if (idx === -1) {
          this._localIds.pop();
        } else {
          this._localIds.splice(idx, 0, articleId);
        }
        this._saveLocal(this._localIds);
        console.warn('收藏操作失败:', result.msg);
        return null;
      }
    } catch (error) {
      console.error('收藏 API 调用失败:', error);
      // 即使 API 调用失败，本地状态已经更新，用户体验更好
    }

    return isNowFaved;
  },

  /**
   * 移除收藏
   */
  async remove(id) {
    const articleId = parseInt(id);
    const idx = this._localIds.indexOf(articleId);
    if (idx !== -1) {
      this._localIds.splice(idx, 1);
      this._saveLocal(this._localIds);

      // 同步到后端
      if (Auth.isLoggedIn()) {
        try {
          const token = Auth.getToken();
          const csrf = await getCsrfToken();
          await fetch(`${ARTICLE_API_BASE}/unfavorite`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${token}`,
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({ _token: token, article_id: articleId }),
            credentials: 'include'
          });
        } catch (error) {
          console.error('取消收藏失败:', error);
        }
      }
    }
  },

  /**
   * 清空所有收藏
   */
  async clearAll(username) {
    this._localIds = [];
    this._saveLocal([]);

    if (Auth.isLoggedIn()) {
      try {
        const token = Auth.getToken();
        const csrf = await getCsrfToken();
        await fetch(`${ARTICLE_API_BASE}/unfavorite`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ _token: token }),
          credentials: 'include'
        });
      } catch (error) {
        console.error('清空收藏失败:', error);
      }
    }
  }
};

/* ============================================
   阅读历史
   ============================================ */
const History = {
  getAll() {
    const username = Auth.getUsername();
    if (!username) return [];
    const all = JSON.parse(localStorage.getItem(LS.KEY_HISTORY) || '{}');
    return all[username] || [];
  },
  _save(username, records) {
    const all = JSON.parse(localStorage.getItem(LS.KEY_HISTORY) || '{}');
    all[username] = records;
    localStorage.setItem(LS.KEY_HISTORY, JSON.stringify(all));
  },
  add(articleId) {
    const username = Auth.getUsername();
    if (!username) return;
    const records = this.getAll().filter(r => r.id !== articleId);
    records.unshift({ id: articleId, at: Date.now() });
    // 最多保留100条
    if (records.length > 100) records.splice(100);
    this._save(username, records);
  },
  clearAll(username) {
    const all = JSON.parse(localStorage.getItem(LS.KEY_HISTORY) || '{}');
    delete all[username];
    localStorage.setItem(LS.KEY_HISTORY, JSON.stringify(all));
  },
};

/* ============================================
   头像颜色池
   ============================================ */
const AVATAR_COLORS = [
  '#e74c3c','#e67e22','#f1c40f','#2ecc71',
  '#3498db','#9b59b6','#1abc9c','#e91e63',
];

/* ============================================
   工具函数
   ============================================ */
const Utils = {
  timeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const d = Math.floor(diff / 86400000);
    if (d === 0) return '今天';
    if (d === 1) return '昨天';
    if (d < 7) return `${d}天前`;
    if (d < 30) return `${Math.floor(d/7)}周前`;
    return `${Math.floor(d/30)}月前`;
  },
  timeSince(timestamp) {
    const diff = Date.now() - timestamp;
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return '刚刚';
    if (mins < 60) return `${mins}分钟前`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}小时前`;
    const days = Math.floor(hrs / 24);
    return `${days}天前`;
  },
  getDaysSince(timestamp) {
    return Math.floor((Date.now() - timestamp) / 86400000) || 1;
  },
  formatViews(n) {
    if (n >= 10000) return (n / 10000).toFixed(1) + 'w';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
    return String(n);
  },
  /**
   * 估算阅读时间（基于纯文字字数）
   * @param {string} content - 文章 HTML 或纯文本
   * @param {number} wpm - 每分钟字数，默认 400
   * @returns {number} 分钟数，最小 1
   */
  estimateReadTime(content, wpm = 400) {
    if (!content) return 1;
    const text = content.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
    const words = text.length;
    return Math.max(1, Math.ceil(words / wpm));
  },
};
