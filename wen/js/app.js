/**
 * 文轩 WenXuan - 主交互逻辑
 * 同时适用于 index.html / detail.html / user.html
 */

(function () {
  'use strict';

  /* ============================================
     Toast 提示
     ============================================ */
  let toastTimer = null;
  function showToast(msg, type = '') {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toastMsg');
    if (!toast) return;
    toastMsg.textContent = msg;
    toast.className = 'toast' + (type ? ' ' + type : '');
    clearTimeout(toastTimer);
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
  }

  /* ============================================
     头部滚动效果
     ============================================ */
  function initHeaderScroll() {
    const header = document.getElementById('siteHeader');
    if (!header) return;
    window.addEventListener('scroll', () => {
      header.classList.toggle('scrolled', window.scrollY > 10);
    }, { passive: true });
  }

  /* ============================================
     移动端菜单
     ============================================ */
  function initMobileMenu() {
    const btn = document.getElementById('mobileMenuBtn');
    const nav = document.getElementById('mobileNav');
    if (!btn || !nav) return;
    btn.addEventListener('click', () => {
      nav.classList.toggle('open');
    });
    nav.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => nav.classList.remove('open'));
    });
  }

  /* ============================================
     搜索
     ============================================ */
  const SEARCH_HISTORY_KEY = 'wx_search_history';
  const MAX_HISTORY = 8;

  function getSearchHistory() {
    try { return JSON.parse(localStorage.getItem(SEARCH_HISTORY_KEY)) || []; }
    catch { return []; }
  }
  function saveSearchHistory(query) {
    if (!query.trim()) return;
    let hist = getSearchHistory().filter(h => h !== query.trim());
    hist.unshift(query.trim());
    hist = hist.slice(0, MAX_HISTORY);
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(hist));
  }
  function clearSearchHistory() {
    localStorage.removeItem(SEARCH_HISTORY_KEY);
  }

  // 高亮搜索关键词
  function highlightKeyword(text, keyword) {
    if (!keyword || !text) return escapeHtml(text || '');
    const escaped = escapeHtml(text);
    const regex = new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return escaped.replace(regex, '<mark>$1</mark>');
  }

  async function initSearch() {
    const overlay = document.getElementById('searchOverlay');
    const input = document.getElementById('searchInput');
    const results = document.getElementById('searchResults');
    const searchBtn = document.getElementById('searchBtn');
    const closeBtn = document.getElementById('searchClose');
    if (!overlay) return;

    // 等待文章数据加载
    await Articles.init();

    // 渲染搜索历史
    function renderSearchHistory() {
      const hist = getSearchHistory();
      const histContainer = document.getElementById('searchHistory');
      const histList = document.getElementById('searchHistoryList');
      if (!histContainer || !histList) return;
      if (hist.length === 0) {
        histContainer.style.display = 'none';
        return;
      }
      histContainer.style.display = 'block';
      histList.innerHTML = hist.map(h => `
        <button class="search-history-tag" data-query="${escapeHtml(h)}">${escapeHtml(h)}</button>
      `).join('');
      histList.querySelectorAll('.search-history-tag').forEach(tag => {
        tag.addEventListener('click', () => {
          const q = tag.dataset.query;
          input.value = q;
          input.dispatchEvent(new Event('input'));
        });
      });
    }

    // 清空历史
    const clearBtn = document.getElementById('clearSearchHistory');
    clearBtn && clearBtn.addEventListener('click', () => {
      clearSearchHistory();
      renderSearchHistory();
    });

    function openSearch() {
      overlay.classList.add('open');
      input.focus();
      renderSearchHistory();
    }
    function closeSearch() {
      overlay.classList.remove('open');
      input.value = '';
      results.innerHTML = '';
    }

    searchBtn && searchBtn.addEventListener('click', openSearch);
    closeBtn && closeBtn.addEventListener('click', closeSearch);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeSearch(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSearch(); });

    input && input.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      if (!q) {
        renderSearchHistory();
        return;
      }
      const matched = ARTICLES.filter(a =>
        a.title.toLowerCase().includes(q) ||
        (a.desc && a.desc.toLowerCase().includes(q)) ||
        a.tags.some(t => t.toLowerCase().includes(q))
      ).slice(0, 8);

      const keyword = this.value.trim();
      if (!matched.length) {
        results.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:20px;font-size:14px;">未找到相关文章</p>';
        return;
      }
      results.innerHTML = matched.map(a => `
        <div class="search-result-item" onclick="location.href='detail.html?id=${a.id}'">
          <div class="search-result-thumb"><img src="${a.cover}" alt=""></div>
          <div class="search-result-info">
            <h4>${highlightKeyword(a.title, keyword)}</h4>
            <p>${a.categoryName} · ${a.readTime}分钟</p>
          </div>
        </div>
      `).join('');
    });

    // 回车保存搜索历史
    input && input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && this.value.trim()) {
        saveSearchHistory(this.value.trim());
        renderSearchHistory();
      }
    });
  }

  /* ============================================
     HTML 转义
     ============================================ */
  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  /* ============================================
     登录 / 注册弹窗
     ============================================ */
  function initAuth() {
    const modal = document.getElementById('authModal');
    if (!modal) return;

    // 打开 / 关闭
    function openModal() { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.classList.remove('open'); document.body.style.overflow = ''; }

    document.querySelectorAll('#btnLoginHeader, #btnLoginWall').forEach(btn => {
      btn && btn.addEventListener('click', openModal);
    });
    document.getElementById('modalClose') && document.getElementById('modalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // 切换登录/注册
    document.getElementById('toRegister') && document.getElementById('toRegister').addEventListener('click', e => {
      e.preventDefault();
      document.getElementById('loginForm').style.display = 'none';
      document.getElementById('registerForm').style.display = 'block';
    });
    document.getElementById('toLogin') && document.getElementById('toLogin').addEventListener('click', e => {
      e.preventDefault();
      document.getElementById('registerForm').style.display = 'none';
      document.getElementById('loginForm').style.display = 'block';
    });

    // 密码可见切换
    document.querySelectorAll('.toggle-pwd').forEach(btn => {
      btn.addEventListener('click', function () {
        const inp = this.closest('.password-wrap').querySelector('input');
        const isPwd = inp.type === 'password';
        inp.type = isPwd ? 'text' : 'password';
        this.innerHTML = isPwd
          ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
          : '<svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
      });
    });

    // 登录（调用真实 API）
    document.getElementById('btnLogin') && document.getElementById('btnLogin').addEventListener('click', async function () {
      const username = document.getElementById('loginUsername').value.trim();
      const password = document.getElementById('loginPassword').value;
      const errEl = document.getElementById('loginError');
      if (!username || !password) {
        errEl.textContent = '请填写用户名和密码'; errEl.classList.add('show');
        return;
      }
      this.disabled = true;
      this.textContent = '登录中...';
      errEl.classList.remove('show');

      const result = await Auth.login(username, password);
      this.disabled = false;
      this.textContent = '登 录';

      if (result.ok) {
        errEl.classList.remove('show');
        closeModal();
        showToast('登录成功', 'success');
        updateUserUI();
        if (typeof onLoginSuccess === 'function') onLoginSuccess();
      } else {
        errEl.textContent = result.msg; errEl.classList.add('show');
      }
    });

    // 注册（调用真实 API）
    document.getElementById('btnRegister') && document.getElementById('btnRegister').addEventListener('click', async function () {
      const username = document.getElementById('regUsername').value.trim();
      const password = document.getElementById('regPassword').value;
      const password2 = document.getElementById('regPassword2').value;
      const errEl = document.getElementById('regError');
      if (!username || !password || !password2) {
        errEl.textContent = '请填写所有字段'; errEl.classList.add('show');
        return;
      }
      if (password !== password2) {
        errEl.textContent = '两次密码输入不一致'; errEl.classList.add('show');
        return;
      }
      this.disabled = true;
      this.textContent = '注册中...';
      errEl.classList.remove('show');

      const result = await Auth.register(username, password);
      this.disabled = false;
      this.textContent = '注 册';

      if (result.ok) {
        errEl.classList.remove('show');
        closeModal();
        showToast('注册成功，欢迎加入文轩！', 'success');
        updateUserUI();
        if (typeof onLoginSuccess === 'function') onLoginSuccess();
      } else {
        errEl.textContent = result.msg; errEl.classList.add('show');
      }
    });
  }

  /* ============================================
     更新用户 UI（header 头像 / 登录按钮切换）
     ============================================ */
  function updateUserUI() {
    const username = Auth.getUsername();
    const btnLogin = document.getElementById('btnLoginHeader');
    const userAvatar = document.getElementById('userAvatar');
    const avatarInitial = document.getElementById('avatarInitial');
    if (!btnLogin) return;

    if (username) {
      btnLogin.style.display = 'none';
      if (userAvatar) {
        userAvatar.style.display = 'flex';
        if (avatarInitial) avatarInitial.textContent = username[0].toUpperCase();
        const color = AVATAR_COLORS[usernameCode(username) % AVATAR_COLORS.length];
        userAvatar.style.background = color;
      }
    } else {
      btnLogin.style.display = 'inline-block';
      if (userAvatar) userAvatar.style.display = 'none';
    }
  }

  // 根据用户名生成数字编码（用于固定头像颜色）
  function usernameCode(name) {
    let code = 0;
    for (let i = 0; i < name.length; i++) code = code * 31 + name.charCodeAt(i);
    return Math.abs(code);
  }

  /* ============================================
     头像下拉菜单（注册/登录后）
     ============================================ */
  function initUserDropdown() {
    const userAvatar = document.getElementById('userAvatar');
    if (!userAvatar) return;

    // 点击头像 toggle 下拉菜单
    userAvatar.addEventListener('click', e => {
      e.stopPropagation();
      userAvatar.classList.toggle('open');
    });

    // 点击页面其他地方关闭
    document.addEventListener('click', () => {
      userAvatar.classList.remove('open');
    });

    // 个人中心
    document.getElementById('toUserCenter') && document.getElementById('toUserCenter').addEventListener('click', () => {
      userAvatar.classList.remove('open');
      location.href = 'user.html';
    });

    // 退出登录
    document.getElementById('btnLogout') && document.getElementById('btnLogout').addEventListener('click', () => {
      Auth.logout();
      showToast('已退出登录');
      updateUserUI();
    });
  }

  /* ============================================
     收藏按钮渲染（卡片右上角 + 详情页）
     ============================================ */
  function makeFavBtn(articleId) {
    const btn = document.createElement('button');
    btn.className = 'card-fav' + (Favorites.isFaved(articleId) ? ' faved' : '');
    btn.title = Favorites.isFaved(articleId) ? '取消收藏' : '收藏';
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
    </svg>`;
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const user = Auth.getCurrent();
      if (!user) {
        showToast('请先登录后收藏', 'error');
        return;
      }
      Favorites.toggle(articleId).then(added => {
        if (added === null) {
          showToast('操作失败，请重试', 'error');
          return;
        }
        btn.classList.toggle('faved', added);
        btn.title = added ? '取消收藏' : '收藏';
        showToast(added ? '已收藏' : '已取消收藏', added ? 'success' : '');
      });
    });
    return btn;
  }

  /* ============================================
     渲染文章卡片
     ============================================ */
  function renderArticleCard(article) {
    const faved = Favorites.isFaved(article.id);
    const card = document.createElement('div');
    card.className = 'article-card';
    card.dataset.id = article.id;
    card.addEventListener('click', () => { location.href = `detail.html?id=${article.id}`; });
    card.innerHTML = `
      <div class="card-cover">
        <img src="${article.cover}" alt="${escapeHtml(article.title)}" loading="lazy">
        <span class="card-category">${article.categoryName}</span>
        <button class="card-fav${faved ? ' faved' : ''}" data-id="${article.id}" title="${faved ? '取消收藏' : '收藏'}">
          <svg width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </button>
      </div>
      <div class="card-body">
        <h3 class="card-title">${escapeHtml(article.title)}</h3>
        <p class="card-desc">${escapeHtml(article.desc)}</p>
        <div class="card-meta">
          <div class="card-author">
            <span class="card-author-avatar" style="background:${article.authorAvatarColor}">${article.author[0]}</span>
            <span>${escapeHtml(article.author)}</span>
          </div>
          <span class="card-dot">·</span>
          <span class="card-read">${article.readTime}分钟</span>
          <button class="card-fav-btn" data-id="${article.id}" title="${faved ? '取消收藏' : '收藏'}">
            <svg width="15" height="15" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </button>
        </div>
      </div>`;

    // 收藏按钮事件（同时绑定两个按钮）
    card.querySelectorAll('.card-fav, .card-fav-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const id = parseInt(btn.dataset.id);
        const user = Auth.getCurrent();
        if (!user) { showToast('请先登录后收藏', 'error'); return; }
        Favorites.toggle(id).then(added => {
          if (added === null) { showToast('操作失败，请重试', 'error'); return; }
          // 同步更新卡片内所有收藏按钮状态
          card.querySelectorAll('.card-fav, .card-fav-btn').forEach(b => {
            const isFav = added;
            b.classList.toggle('faved', isFav);
            b.classList.toggle('liked', isFav);
            b.title = isFav ? '取消收藏' : '收藏';
          });
          showToast(added ? '已收藏' : '已取消收藏', added ? 'success' : '');
        });
      });
    });

    return card;
  }

  /* ============================================
     渲染列表卡片（用户中心）
     ============================================ */
  function renderListCard(article, showRemove = false) {
    const card = document.createElement('div');
    card.className = 'article-list-card';
    card.dataset.id = article.id;
    card.addEventListener('click', () => { location.href = `detail.html?id=${article.id}`; });
    card.innerHTML = `
      <div class="list-card-thumb"><img src="${article.cover}" alt="" loading="lazy"></div>
      <div class="list-card-body">
        <div class="list-card-meta">
          <span class="list-card-cat">${article.categoryName}</span>
          <span class="list-card-time">${Utils.timeAgo(article.date)}</span>
        </div>
        <h4 class="list-card-title">${escapeHtml(article.title)}</h4>
        <p class="list-card-desc">${escapeHtml(article.desc)}</p>
      </div>`;
    if (showRemove) {
      const rmBtn = document.createElement('button');
      rmBtn.className = 'list-card-remove';
      rmBtn.title = '取消收藏';
      rmBtn.innerHTML = '♡';
      rmBtn.addEventListener('click', e => {
        e.stopPropagation();
        Favorites.remove(article.id).then(() => {
          card.remove();
          showToast('已取消收藏');
          refreshFavList();
          const statFav = document.getElementById('statFav');
          if (statFav) {
            const count = Favorites.getAll().length;
            statFav.textContent = count;
          }
        });
      });
      card.appendChild(rmBtn);
    }
    return card;
  }

  /* ============================================
     渲染精选 Banner
     ============================================ */
  function renderHeroBanner() {
    const container = document.getElementById('heroBannerInner');
    if (!container || ARTICLES.length === 0) return;

    // Banner 始终显示"全部"下的前3篇最新文章
    const bannerSource = ARTICLES;
    const featured = bannerSource[0];
    const second = bannerSource.slice(1, 3);

    const leftHTML = `
      <div class="hero-thumb">
        <img src="${featured.cover}" alt="${escapeHtml(featured.title)}" loading="eager">
        <div class="hero-overlay">
          <span class="hero-cat-tag">${escapeHtml(featured.categoryName)}</span>
          <h2 class="hero-title">${escapeHtml(featured.title)}</h2>
          <div class="hero-meta">
            <span>${escapeHtml(featured.author)}</span>
            <span>·</span>
            <span>${featured.readTime}分钟阅读</span>
            <span>·</span>
            <span>${Utils.timeAgo(featured.date)}</span>
          </div>
        </div>
      </div>`;

    const leftLink = document.createElement('a');
    leftLink.className = 'hero-banner-left';
    leftLink.href = `detail.html?id=${featured.id}`;
    leftLink.innerHTML = leftHTML;

    const rightContainer = document.createElement('div');
    rightContainer.className = 'hero-banner-right';

    second.forEach(a => {
      const item = document.createElement('a');
      item.className = 'hero-banner-right-item';
      item.href = `detail.html?id=${a.id}`;
      item.innerHTML = `
        <div class="hero-thumb-sm">
          <img src="${a.cover}" alt="${escapeHtml(a.title)}" loading="eager">
        </div>
        <div class="hero-info">
          <div class="hero-info-cat">${escapeHtml(a.categoryName)}</div>
          <div class="hero-info-title">${escapeHtml(a.title)}</div>
          <div class="hero-info-meta">${a.readTime}分钟 · ${Utils.timeAgo(a.date)}</div>
        </div>`;
      rightContainer.appendChild(item);
    });

    container.innerHTML = '';
    container.appendChild(leftLink);
    container.appendChild(rightContainer);
  }

  /* ============================================
     渲染列表视图文章卡片（图文上下排列）
     ============================================ */
  function renderListViewCard(article) {
    const faved = Favorites.isFaved(article.id);
    const card = document.createElement('div');
    card.className = 'list-view-card';
    card.dataset.id = article.id;
    card.addEventListener('click', () => { location.href = `detail.html?id=${article.id}`; });
    card.innerHTML = `
      <div class="list-thumb">
        <img src="${article.cover}" alt="${escapeHtml(article.title)}" loading="lazy">
        <span class="list-cat-tag">${escapeHtml(article.categoryName)}</span>
      </div>
      <div class="list-content">
        <h3 class="list-title">${escapeHtml(article.title)}</h3>
        <p class="list-desc">${escapeHtml(article.desc)}</p>
        <div class="list-meta">
          <div class="list-meta-author">
            <span class="list-meta-avatar" style="background:${article.authorAvatarColor}">${article.author[0]}</span>
            <span>${escapeHtml(article.author)}</span>
          </div>
          <span class="list-dot">·</span>
          <span>${Utils.timeAgo(article.date)}</span>
          <span class="list-dot">·</span>
          <span>${article.readTime}分钟阅读</span>
          <button class="card-fav-btn${faved ? ' faved' : ''}" data-id="${article.id}" title="${faved ? '取消收藏' : '收藏'}">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </button>
        </div>
      </div>`;

    // 收藏按钮事件
    card.querySelectorAll('.card-fav-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const id = parseInt(btn.dataset.id);
        const user = Auth.getCurrent();
        if (!user) { showToast('请先登录后收藏', 'error'); return; }
        Favorites.toggle(id).then(added => {
          if (added === null) { showToast('操作失败，请重试', 'error'); return; }
          card.querySelectorAll('.card-fav-btn').forEach(b => {
            b.classList.toggle('faved', added);
            b.title = added ? '取消收藏' : '收藏';
          });
          showToast(added ? '已收藏' : '已取消收藏', added ? 'success' : '');
        });
      });
    });

    return card;
  }

  /* ============================================
     ========== 首页逻辑 ==========
     ============================================ */
  async function initIndex() {
    const grid = document.getElementById('articleGrid');
    const listView = document.getElementById('articleListView');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const loadMoreWrap = document.getElementById('loadMoreBtnWrap');
    if (!grid) return;

    // 等待文章数据加载完成
    await Articles.init();
    // 同步收藏数据
    await Favorites.init();

    let currentTab = 'all';
    let currentView = localStorage.getItem('wx_view_mode') || 'grid';
    let page = 0;
    const PAGE_SIZE = 6;
    let loading = false;
    let totalShown = 0;
    let listOffset = 0;

    // 更新视图切换按钮状态
    function applyViewState() {
      const gridBtn = document.getElementById('viewGridBtn');
      const listBtn = document.getElementById('viewListBtn');
      const gridEl = document.getElementById('articleGrid');
      const listEl = document.getElementById('articleListView');
      if (currentView === 'grid') {
        gridBtn && gridBtn.classList.add('active');
        listBtn && listBtn.classList.remove('active');
        gridEl && (gridEl.style.display = '');
        listEl && (listEl.style.display = 'none');
      } else {
        gridBtn && gridBtn.classList.remove('active');
        listBtn && listBtn.classList.add('active');
        gridEl && (gridEl.style.display = 'none');
        listEl && (listEl.style.display = '');
      }
    }

    // 更新文章计数和标题
    function updateViewHeader() {
      const titleEl = document.getElementById('viewTitle');
      const countEl = document.getElementById('viewCount');
      const tabNames = { all: '全部文章', tech: '科技', life: '生活', design: '设计', finance: '财经', travel: '旅行' };
      const filtered = currentTab === 'all' ? ARTICLES : ARTICLES.filter(a => a.category === currentTab);
      const total = filtered.length - (currentTab === 'all' ? 3 : 0);
      if (titleEl) titleEl.textContent = tabNames[currentTab] || '文章';
      if (countEl) countEl.textContent = total > 0 ? `共 ${total} 篇` : '';
    }

    // 视图切换按钮事件
    document.getElementById('viewGridBtn') && document.getElementById('viewGridBtn').addEventListener('click', () => {
      if (currentView === 'grid') return;
      currentView = 'grid';
      localStorage.setItem('wx_view_mode', 'grid');
      page = 0;
      totalShown = 0;
      grid.innerHTML = '';
      listView.innerHTML = '';
      applyViewState();
      updateViewHeader();
      load();
    });

    document.getElementById('viewListBtn') && document.getElementById('viewListBtn').addEventListener('click', () => {
      if (currentView === 'list') return;
      currentView = 'list';
      localStorage.setItem('wx_view_mode', 'list');
      page = 0;
      totalShown = 0;
      grid.innerHTML = '';
      listView.innerHTML = '';
      applyViewState();
      updateViewHeader();
      load();
    });

    // Tab 切换
    function setTab(tab) {
      currentTab = tab;
      page = 0;
      totalShown = 0;
      grid.innerHTML = '';
      listView.innerHTML = '';
      loadMoreWrap.style.display = 'block';
      document.getElementById('noMore').style.display = 'none';

      if (tab === 'all') {
        renderHeroBanner();
        listOffset = 3;
      } else {
        const bannerInner = document.getElementById('heroBannerInner');
        if (bannerInner) bannerInner.innerHTML = '';
        listOffset = 0;
      }

      updateViewHeader();
      load();
    }

    function getFiltered() {
      if (currentTab === 'all') return ARTICLES;
      return ARTICLES.filter(a => a.category === currentTab);
    }

    function load() {
      if (loading) return;
      loading = true;
      const loadEl = document.getElementById('loadingMore');
      const btnEl = document.getElementById('loadMoreBtn');
      if (loadEl) loadEl.style.display = 'flex';
      if (btnEl) btnEl.style.display = 'none';

      setTimeout(() => {
        const filtered = getFiltered();
        const batch = filtered.slice(listOffset + page * PAGE_SIZE, listOffset + (page + 1) * PAGE_SIZE);
        if (currentView === 'grid') {
          batch.forEach(a => grid.appendChild(renderArticleCard(a)));
        } else {
          batch.forEach(a => listView.appendChild(renderListViewCard(a)));
        }
        totalShown += batch.length;
        page++;
        loading = false;
        if (loadEl) loadEl.style.display = 'none';

        if (totalShown >= filtered.length - listOffset) {
          if (btnEl) btnEl.style.display = 'none';
          document.getElementById('noMore').style.display = 'block';
        } else {
          if (btnEl) btnEl.style.display = 'inline-block';
        }
      }, 500);
    }

    // 顶部导航 tab + 移动端底部分类栏
    document.querySelectorAll('.nav-link, .tab-btn, .mobile-tab-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        if (btn.dataset.tab) {
          e.preventDefault();
          const tab = btn.dataset.tab;
          document.querySelectorAll('.nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
          document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
          document.querySelectorAll('.mobile-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
          setTab(tab);
        }
      });
    });

    loadMoreBtn && loadMoreBtn.addEventListener('click', load);

    // 初始化视图状态
    applyViewState();
    setTab('all');
  }

  /* ============================================
     ========== 详情页逻辑 ==========
     ============================================ */
  async function initDetail() {
    const bodyEl = document.getElementById('articleBody');
    if (!bodyEl) return;

    // 等待文章数据加载
    await Articles.init();

    const params = new URLSearchParams(location.search);
    const id = parseInt(params.get('id'));

    // 获取文章详情（会从 /detail API 获取完整正文）
    const article = await Articles.getById(id);
    console.info('[initDetail] 获取到的文章:', article ? { id: article.id, title: article.title, contentLen: (article.content || '').length } : null);

    if (!article) {
      bodyEl.innerHTML = '<p style="text-align:center;padding:60px;color:var(--text-muted)">文章不存在或已下架（id=' + id + '）</p>';
      return;
    }

    // 记录阅读历史
    const user = Auth.getCurrent();
    if (user) History.add(article.id);

    // ========== 阅读时间精确计算 ==========
    const finalReadTime = Utils.estimateReadTime(article.content);

    // 渲染头部（更新阅读时间）
    document.getElementById('articleHeader').innerHTML = `
      <span class="card-category">${article.categoryName}</span>
      <h1>${escapeHtml(article.title)}</h1>
      <div class="article-meta-row">
        <div class="article-author-info">
          <span class="article-author-avatar" style="background:${article.authorAvatarColor}">${article.author[0]}</span>
          <span class="article-author-name">${escapeHtml(article.author)}</span>
        </div>
        <span class="article-divider">|</span>
        <span>${Utils.timeAgo(article.date)}</span>
        <span class="article-divider">|</span>
        <span>约 ${finalReadTime} 分钟阅读</span>
        <span class="article-divider">|</span>
        <span>${Utils.formatViews(article.views)} 阅读</span>
      </div>`;

    // 渲染正文
    document.title = article.title + ' · 文轩';
    const contentToRender = article.content || article.desc || '<p style="color:var(--text-muted)">暂无正文内容</p>';
    bodyEl.innerHTML = contentToRender;

    // ========== 文章目录导航 ==========
    const tocEl = document.getElementById('articleToc');
    const tocListEl = document.getElementById('tocList');
    const tocSidebarEl = document.getElementById('tocSidebar');
    const tocSidebarList = document.getElementById('tocSidebarList');
    const tocToggle = document.getElementById('tocToggle');

    function buildTOC() {
      if (!bodyEl) return [];
      const headings = bodyEl.querySelectorAll('h2, h3');
      const tocItems = [];
      headings.forEach((h, i) => {
        const id = 'heading-' + i;
        h.id = id;
        tocItems.push({
          id,
          level: h.tagName.toLowerCase(),
          text: h.textContent.replace(/<[^>]+>/g, '').trim(),
        });
      });
      return tocItems;
    }

    function renderTOC(tocItems) {
      if (!tocListEl || !tocSidebarList) return;
      const html = tocItems.map(item => `
        <li class="toc-item level-${item.level === 'h3' ? 3 : 2}" data-id="${item.id}">
          <a href="#${item.id}">
            <span class="toc-dot"></span>
            ${escapeHtml(item.text)}
          </a>
        </li>`).join('');

      tocListEl.innerHTML = html;
      tocSidebarList.innerHTML = html;

      // 目录点击平滑滚动
      document.querySelectorAll('.toc-item a').forEach(a => {
        a.addEventListener('click', e => {
          e.preventDefault();
          const target = document.querySelector(a.getAttribute('href'));
          if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            tocSidebarEl.classList.remove('visible');
          }
        });
      });
    }

    const tocItems = buildTOC();
    if (tocItems.length >= 2) {
      renderTOC(tocItems);
      // 目录收起/展开
      tocToggle && tocToggle.addEventListener('click', () => {
        const isOpen = tocListEl.style.display !== 'none';
        tocListEl.style.display = isOpen ? 'none' : '';
        tocToggle.textContent = isOpen ? '展开' : '收起';
      });
      // 滚动时高亮当前章节
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          const id = entry.target.id;
          document.querySelectorAll('.toc-item').forEach(item => {
            item.classList.toggle('active', item.dataset.id === id);
          });
          // 右侧悬浮目录
          if (entry.isIntersecting) {
            tocSidebarEl.classList.add('visible');
          }
        });
      }, { rootMargin: '-20% 0px -60% 0px' });
      tocItems.forEach(item => {
        const el = document.getElementById(item.id);
        if (el) observer.observe(el);
      });
      // 鼠标移入右侧目录显示
      tocSidebarEl && tocSidebarEl.addEventListener('mouseenter', () => {
        tocSidebarEl.classList.add('visible');
      });
    } else {
      if (tocEl) tocEl.style.display = 'none';
      if (tocSidebarEl) tocSidebarEl.style.display = 'none';
    }

    // ========== 上下篇导航 ==========
    const prevArticle = ARTICLES.filter(a => a.id < article.id).sort((a, b) => b.id - a.id)[0];
    const nextArticle = ARTICLES.filter(a => a.id > article.id).sort((a, b) => a.id - b.id)[0];

    const prevEl = document.getElementById('articleNavPrev');
    const nextEl = document.getElementById('articleNavNext');

    if (prevEl) {
      prevEl.innerHTML = prevArticle ? `
        <a href="detail.html?id=${prevArticle.id}" class="article-nav-item prev">
          <span class="article-nav-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            上一篇
          </span>
          <span class="article-nav-title">${escapeHtml(prevArticle.title)}</span>
        </a>` : `<div class="article-nav-empty">没有上一篇了</div>`;
    }

    if (nextEl) {
      nextEl.innerHTML = nextArticle ? `
        <a href="detail.html?id=${nextArticle.id}" class="article-nav-item next">
          <span class="article-nav-label">
            下一篇
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
          </span>
          <span class="article-nav-title">${escapeHtml(nextArticle.title)}</span>
        </a>` : `<div class="article-nav-empty">没有下一篇了</div>`;
    }

    // ========== 阅读进度条 ==========
    const progressBar = document.getElementById('readingProgress');
    if (progressBar) {
      window.addEventListener('scroll', () => {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = docHeight > 0 ? Math.min(100, (scrollTop / docHeight) * 100) : 0;
        progressBar.style.width = progress + '%';
      }, { passive: true });
    }

    // ========== 点赞功能 ==========
    // API 占位：调用 POST ${ARTICLE_API_BASE}/like
    // 请求: { _token, article_id }
    // 响应: { code: 0, data: { liked: bool, count: number } }
    let liked = false;
    let likeCount = article.like_count || Math.floor(Math.random() * 500) + 50;

    const btnLike = document.getElementById('btnLike');
    const likeCountEl = document.getElementById('likeCount');
    if (likeCountEl) likeCountEl.textContent = likeCount;

    async function toggleLike() {
      if (!user) { showToast('请先登录后点赞', 'error'); return; }
      liked = !liked;
      likeCount = liked ? likeCount + 1 : likeCount - 1;
      if (likeCountEl) likeCountEl.textContent = likeCount;
      btnLike && btnLike.classList.toggle('liked', liked);
      btnLike && btnLike.classList.add('like-pop');
      setTimeout(() => btnLike && btnLike.classList.remove('like-pop'), 400);
      showToast(liked ? '点赞成功' : '取消点赞', liked ? 'success' : '');

      // TODO: 调用真实 API
      // await fetch(`${ARTICLE_API_BASE}/like`, {
      //   method: 'POST',
      //   headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${Auth.getToken()}` },
      //   body: JSON.stringify({ _token: Auth.getToken(), article_id: article.id })
      // });
    }

    btnLike && btnLike.addEventListener('click', toggleLike);

    // 评论数量按钮：滚动到评论区
    const btnCommentCount = document.getElementById('btnCommentCount');
    btnCommentCount && btnCommentCount.addEventListener('click', () => {
      const section = document.getElementById('commentsSection');
      if (section) section.scrollIntoView({ behavior: 'smooth' });
    });

    // ========== 评论功能 ==========
    // API 占位：
    // - 获取评论: GET ${ARTICLE_API_BASE}/comments?article_id={id}&page=1&page_size=20
    // - 发布评论: POST ${ARTICLE_API_BASE}/comment
    //   请求: { _token, article_id, content }
    //   响应: { code: 0, data: { id, author, content, created_at, like_count } }
    // - 点赞评论: POST ${ARTICLE_API_BASE}/comment/like
    //   请求: { _token, comment_id }

    let comments = [];
    let commentPage = 1;
    const commentListEl = document.getElementById('commentList');
    const commentCountEl = document.getElementById('commentCount');
    const commentsCountText = document.getElementById('commentsCountText');
    const commentInput = document.getElementById('commentInput');
    const btnSubmit = document.getElementById('btnCommentSubmit');
    const commentAvatar = document.getElementById('commentAvatar');

    // 初始化评论头像
    if (user && commentAvatar) {
      commentAvatar.textContent = user[0].toUpperCase();
      commentAvatar.style.background = AVATAR_COLORS[usernameCode(user) % AVATAR_COLORS.length];
    }

    // 渲染单条评论
    function renderComment(comment) {
      const likedByMe = false; // TODO: 从 API 获取
      return `
        <div class="comment-item" data-id="${comment.id}">
          <div class="comment-avatar" style="background:${comment.avatarColor || 'var(--accent)'}">${(comment.author || 'U')[0].toUpperCase()}</div>
          <div class="comment-body">
            <div class="comment-meta">
              <span class="comment-author">${escapeHtml(comment.author || '匿名用户')}</span>
              <span class="comment-time">${Utils.timeSince(comment.created_at || Date.now())}</span>
            </div>
            <p class="comment-content">${escapeHtml(comment.content || '')}</p>
            <div class="comment-actions">
              <button class="comment-action-btn${likedByMe ? ' liked' : ''}" data-action="like" data-id="${comment.id}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="${likedByMe ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                  <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                </svg>
                <span>${comment.like_count || 0}</span>
              </button>
              <button class="comment-action-btn" data-action="reply" data-id="${comment.id}">回复</button>
            </div>
          </div>
        </div>`;
    }

    // 加载评论
    async function loadComments() {
      const loadingEl = document.getElementById('commentLoading');
      if (loadingEl) loadingEl.style.display = 'block';

      // TODO: 替换为真实 API 调用
      // const resp = await fetch(`${ARTICLE_API_BASE}/comments?article_id=${id}&page=${commentPage}&page_size=20`);
      // const result = await resp.json();
      // comments = result.data?.list || [];
      // const total = result.data?.total || 0;

      // 模拟数据（后端接入后删除这段）
      await new Promise(r => setTimeout(r, 400));
      comments = [
        { id: 3, author: '林小晚', content: '写得真好，终于有人把这个话题讲清楚了！', created_at: Date.now() - 3600000 * 2, like_count: 24, avatarColor: '#3498db' },
        { id: 2, author: '张大力', content: '收藏了，准备仔细研读一下。', created_at: Date.now() - 3600000 * 8, like_count: 12, avatarColor: '#e67e22' },
        { id: 1, author: '陈思思', content: '观点很有深度，期待更多类似的文章。', created_at: Date.now() - 86400000, like_count: 5, avatarColor: '#9b59b6' },
      ];

      if (commentListEl) {
        if (loadingEl) loadingEl.style.display = 'none';
        commentListEl.innerHTML = comments.length
          ? comments.map(renderComment).join('')
          : '<p class="comment-empty">还没有评论，来抢沙发吧~</p>';

        // 评论数
        if (commentCountEl) commentCountEl.textContent = comments.length;
        if (commentsCountText) commentsCountText.textContent = `(${comments.length} 条)`;

        // 评论点赞事件
        commentListEl.querySelectorAll('[data-action="like"]').forEach(btn => {
          btn.addEventListener('click', async () => {
            if (!user) { showToast('请先登录', 'error'); return; }
            const cid = btn.dataset.id;
            showToast('功能开发中', '');
            // TODO: await fetch(`${ARTICLE_API_BASE}/comment/like`, { POST, _token, comment_id: cid });
          });
        });
      }
    }

    loadComments();

    // 发布评论
    btnSubmit && btnSubmit.addEventListener('click', async () => {
      const content = commentInput.value.trim();
      if (!content) { showToast('请输入评论内容', 'error'); return; }
      if (!user) { showToast('请先登录后发表评论', 'error'); return; }

      btnSubmit.disabled = true;
      btnSubmit.textContent = '发布中...';

      // TODO: 替换为真实 API
      // const resp = await fetch(`${ARTICLE_API_BASE}/comment`, {
      //   method: 'POST',
      //   headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${Auth.getToken()}` },
      //   body: JSON.stringify({ _token: Auth.getToken(), article_id: article.id, content })
      // });
      // const result = await resp.json();

      // 模拟成功（后端接入后删除这段）
      await new Promise(r => setTimeout(r, 600));
      const newComment = {
        id: Date.now(),
        author: user,
        content,
        created_at: Date.now(),
        like_count: 0,
        avatarColor: AVATAR_COLORS[usernameCode(user) % AVATAR_COLORS.length],
      };

      comments.unshift(newComment);
      commentInput.value = '';

      // 更新列表
      const empty = commentListEl && commentListEl.querySelector('.comment-empty');
      if (empty) empty.remove();
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = renderComment(newComment);
      commentListEl && commentListEl.insertBefore(tempDiv.firstElementChild, commentListEl.firstChild);

      const cnt = comments.length;
      if (commentCountEl) commentCountEl.textContent = cnt;
      if (commentsCountText) commentsCountText.textContent = `(${cnt} 条)`;

      btnSubmit.disabled = false;
      btnSubmit.textContent = '发表评论';
      showToast('评论发布成功', 'success');
    });

    // 右侧悬浮栏收藏
    const sidebarFavBtn = document.getElementById('btnFav');
    if (sidebarFavBtn) {
      const isF = Favorites.isFaved(article.id);
      sidebarFavBtn.classList.toggle('faved', isF);
      document.getElementById('favTips').textContent = isF ? '已收藏' : '收藏';
      sidebarFavBtn.addEventListener('click', () => {
        if (!user) { showToast('请先登录后收藏', 'error'); return; }
        Favorites.toggle(article.id).then(added => {
          if (added === null) { showToast('操作失败，请重试', 'error'); return; }
          sidebarFavBtn.classList.toggle('faved', added);
          document.getElementById('favTips').textContent = added ? '已收藏' : '收藏';
          showToast(added ? '已收藏' : '已取消收藏', added ? 'success' : '');
        });
      });
    }

    // 作者卡片
    document.getElementById('authorCard').innerHTML = `
      <div class="author-card-avatar" style="background:${article.authorAvatarColor}">${article.author[0]}</div>
      <div class="author-card-info">
        <div class="author-card-name">${escapeHtml(article.author)}</div>
        <p class="author-card-bio">${escapeHtml(article.authorBio)}</p>
      </div>`;

    // 相关推荐
    const related = ARTICLES.filter(a => a.id !== article.id && a.category === article.category).slice(0, 2);
    if (related.length < 2) {
      ARTICLES.filter(a => a.id !== article.id).slice(0, 2 - related.length).forEach(a => related.push(a));
    }
    document.getElementById('relatedGrid').innerHTML = related.map(a => `
      <div class="related-card" onclick="location.href='detail.html?id=${a.id}'">
        <div class="related-thumb"><img src="${a.cover}" alt="" loading="lazy"></div>
        <div class="related-info">
          <h4>${escapeHtml(a.title)}</h4>
          <p>${Utils.timeAgo(a.date)} · ${a.readTime}分钟</p>
        </div>
      </div>
    `).join('');

    // 分享
    const shareModal = document.getElementById('shareModal');
    document.getElementById('btnShare') && document.getElementById('btnShare').addEventListener('click', () => {
      shareModal.classList.add('open');
    });
    document.getElementById('shareClose') && document.getElementById('shareClose').addEventListener('click', () => {
      shareModal.classList.remove('open');
    });
    shareModal && shareModal.addEventListener('click', e => {
      if (e.target === shareModal) shareModal.classList.remove('open');
    });

    document.getElementById('shareCopy') && document.getElementById('shareCopy').addEventListener('click', () => {
      navigator.clipboard.writeText(location.href).then(() => {
        shareModal.classList.remove('open');
        showToast('链接已复制', 'success');
      });
    });

    // 回到顶部
    const backTopBtn = document.getElementById('btnBackTop');
    window.addEventListener('scroll', () => {
      if (backTopBtn) {
        const show = window.scrollY > 400;
        backTopBtn.style.display = show ? 'flex' : 'none';
        document.getElementById('backTopTips').textContent = show ? '顶部' : '';
      }
    }, { passive: true });
    backTopBtn && backTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ============================================
     ========== 用户中心逻辑 ==========
     ============================================ */
  function refreshFavList() {
    const list = document.getElementById('favList');
    const empty = document.getElementById('favEmpty');
    const hint = document.getElementById('favRemoveHint');
    const statFav = document.getElementById('statFav');
    if (!list) return;

    const ids = Favorites.getAll();
    list.innerHTML = '';

    if (ids.length === 0) {
      empty.style.display = 'block';
      if (hint) hint.style.display = 'none';
    } else {
      empty.style.display = 'none';
      if (hint) hint.style.display = 'block';
      // 从 ARTICLES 匹配文章
      const articles = ids.map(id => Articles.getById(id)).filter(Boolean);
      if (articles.length === 0) {
        // 如果没有匹配到文章，可能需要刷新文章列表
        empty.style.display = 'block';
        if (hint) hint.style.display = 'none';
      } else {
        articles.forEach(a => list.appendChild(renderListCard(a, true)));
      }
    }
    if (statFav) statFav.textContent = ids.length;
  }

  /**
   * 从后端获取收藏列表并显示
   */
  async function refreshFavListFromServer() {
    const list = document.getElementById('favList');
    const empty = document.getElementById('favEmpty');
    const hint = document.getElementById('favRemoveHint');
    const statFav = document.getElementById('statFav');
    if (!list) return;

    if (!Auth.isLoggedIn()) {
      empty.style.display = 'block';
      if (hint) hint.style.display = 'none';
      if (statFav) statFav.textContent = '0';
      return;
    }

    // 显示加载状态
    list.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);">加载中...</div>';

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
          page_size: 100
        }),
        credentials: 'include'
      });

      const result = await response.json();

      list.innerHTML = '';

      if (result.code === 0 && result.data && result.data.list) {
        const favArticles = result.data.list;

        // 更新本地收藏缓存
        const ids = favArticles.map(a => a.id);
        Favorites._localIds = ids;
        Favorites._saveLocal(ids);

        if (favArticles.length === 0) {
          empty.style.display = 'block';
          if (hint) hint.style.display = 'none';
        } else {
          empty.style.display = 'none';
          if (hint) hint.style.display = 'block';
          favArticles.forEach(apiArticle => {
            const article = formatArticle(apiArticle);
            list.appendChild(renderListCard(article, true));
          });
        }

        if (statFav) statFav.textContent = favArticles.length;
      } else {
        empty.style.display = 'block';
        if (hint) hint.style.display = 'none';
        if (statFav) statFav.textContent = '0';
      }
    } catch (error) {
      console.error('获取收藏列表失败:', error);
      list.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);">加载失败，请刷新重试</div>';
    }
  }

  function refreshHistoryList() {
    const list = document.getElementById('historyList');
    const empty = document.getElementById('historyEmpty');
    if (!list) return;
    const records = History.getAll();
    list.innerHTML = '';
    if (records.length === 0) {
      empty.style.display = 'block';
    } else {
      empty.style.display = 'none';
      // 从 ARTICLES 匹配文章
      const articles = records.map(r => Articles.getById(r.id)).filter(Boolean);
      articles.forEach(a => list.appendChild(renderListCard(a, false)));
    }
  }

  function updateProfileUI(username) {
    if (!username) return;
    const nameEl = document.getElementById('profileUsername');
    const bioEl = document.getElementById('profileBio');
    const avatarEl = document.getElementById('profileAvatar');
    if (nameEl) nameEl.textContent = username;
    if (bioEl) bioEl.textContent = localStorage.getItem('wx_bio') || '这个人很懒，什么也没留下';
    if (avatarEl) {
      avatarEl.textContent = username[0].toUpperCase();
      const savedColor = localStorage.getItem('wx_avatar_color');
      avatarEl.style.background = savedColor || AVATAR_COLORS[usernameCode(username) % AVATAR_COLORS.length];
    }
  }

  async function initUser() {
    const userWall = document.getElementById('loginWall');
    const userCenter = document.getElementById('userCenter');
    if (!userCenter) return;

    const username = Auth.getUsername();

    if (!username) {
      userWall.style.display = 'flex';
      userCenter.style.display = 'none';
      return;
    }

    userWall.style.display = 'none';
    userCenter.style.display = 'block';

    updateProfileUI(username);

    // 初始化收藏数据（从服务器同步）
    await Favorites.init(true);

    // 统计
    const statFav = document.getElementById('statFav');
    const statRead = document.getElementById('statRead');
    const statDays = document.getElementById('statDays');
    if (statFav) statFav.textContent = Favorites.getAll().length;
    if (statRead) statRead.textContent = Math.floor(History.getAll().length * 7.5) + '分钟';
    if (statDays) statDays.textContent = '1+';

    // 初始化列表 - 优先从服务器获取收藏列表
    refreshFavListFromServer();
    refreshHistoryList();

    // Tab 切换
    document.querySelectorAll('.user-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.userTab;
        document.querySelectorAll('.user-tab').forEach(b => b.classList.toggle('active', b === btn));
        ['fav', 'history', 'settings'].forEach(t => {
          const el = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
          if (el) el.style.display = t === tab ? 'block' : 'none';
        });
      });
    });

    // 编辑资料
    const editModal = document.getElementById('editProfileModal');
    document.getElementById('profileEditBtn') && document.getElementById('profileEditBtn').addEventListener('click', () => {
      if (!Auth.getUsername()) return;
      document.getElementById('editUsername').value = Auth.getUsername();
      document.getElementById('bioCount').textContent = '0';
      editModal.classList.add('open');
    });

    document.getElementById('cancelEdit') && document.getElementById('cancelEdit').addEventListener('click', () => {
      editModal.classList.remove('open');
    });

    document.getElementById('editBio') && document.getElementById('editBio').addEventListener('input', function () {
      document.getElementById('bioCount').textContent = this.value.length;
    });

    document.getElementById('saveEdit') && document.getElementById('saveEdit').addEventListener('click', () => {
      const errEl = document.getElementById('editError');
      errEl.classList.remove('show');
      editModal.classList.remove('open');
      showToast('资料已更新', 'success');
    });

    // 头像颜色选择
    const avatarModal = document.getElementById('avatarPickerModal');
    document.getElementById('avatarEditBtn') && document.getElementById('avatarEditBtn').addEventListener('click', () => {
      avatarModal.classList.add('open');
    });
    document.getElementById('avatarPickerClose') && document.getElementById('avatarPickerClose').addEventListener('click', () => {
      avatarModal.classList.remove('open');
    });
    let selectedColor = AVATAR_COLORS[usernameCode(username) % AVATAR_COLORS.length];
    document.querySelectorAll('.avatar-color').forEach(el => {
      el.addEventListener('click', () => {
        selectedColor = el.dataset.color;
        const avatarEl = document.getElementById('profileAvatar');
        if (avatarEl) {
          avatarEl.style.background = selectedColor;
          localStorage.setItem('wx_avatar_color', selectedColor);
        }
        avatarModal.classList.remove('open');
        showToast('头像颜色已更新', 'success');
      });
    });
    // 初始化头像颜色
    const savedColor = localStorage.getItem('wx_avatar_color');
    const avatarEl2 = document.getElementById('profileAvatar');
    if (avatarEl2) {
      avatarEl2.style.background = savedColor || AVATAR_COLORS[usernameCode(username) % AVATAR_COLORS.length];
    }

    // 账号设置：修改密码（需调用后端接口，若有的话）
    document.getElementById('btnChangePwd') && document.getElementById('btnChangePwd').addEventListener('click', () => {
      showToast('请联系管理员修改密码', 'error');
    });

    // 注销账号
    document.getElementById('btnDeleteAccount') && document.getElementById('btnDeleteAccount').addEventListener('click', async () => {
      if (!confirm('确定要注销账号吗？所有收藏和阅读记录将永久删除。')) return;
      if (!confirm('此操作不可恢复，再次确认？')) return;
      await Favorites.clearAll(username);
      History.clearAll(username);
      Auth.logout();
      showToast('已退出账号', 'error');
      location.reload();
    });
  }

  /* ============================================
     全局登录成功后回调
     ============================================ */
  window.onLoginSuccess = null;

  // ============================================
  // 主题切换（白天/夜间模式）
  // ============================================
  const THEME_KEY = 'wx_theme';

  function initTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    // 默认白天模式，无偏好时默认 light
    const theme = saved || 'light';
    applyTheme(theme);
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(THEME_KEY, theme);
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    applyTheme(current === 'light' ? 'dark' : 'light');
  }

  /* ============================================
     初始化
     ============================================ */
  document.addEventListener('DOMContentLoaded', async () => {
    // 初始化主题（尽早应用防止闪烁）
    initTheme();

    // 绑定主题切换按钮
    const toggleBtn = document.getElementById('themeToggle');
    toggleBtn && toggleBtn.addEventListener('click', toggleTheme);

    // 先初始化文章数据
    await Articles.init();

    updateUserUI();
    initHeaderScroll();
    initMobileMenu();
    initSearch();
    initAuth();
    initUserDropdown();
    initIndex();
    initDetail();
    initUser();
  });

})();
