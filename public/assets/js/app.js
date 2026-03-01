(function () {
  const STORAGE_KEY = 'financial_saas_session';
  const CURRENCY_FORMATTER = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  });
  const DATE_TIME_FORMATTER = new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

  const state = {
    accessToken: '',
    refreshToken: '',
    session: {
      email: '',
    },
    isAuthenticated: false,
    currentView: 'auth',
    health: null,
    transactions: {
      page: 1,
      totalPages: 1,
    },
    refreshPromise: null,
  };

  const nodes = {
    alert: document.getElementById('global-alert'),
    pageTitle: document.getElementById('page-title'),
    apiStatusChip: document.getElementById('api-status-chip'),
    tokenStatusChip: document.getElementById('token-status-chip'),
    loginForm: document.getElementById('login-form'),
    loginSubmit: document.getElementById('login-submit'),
    passwordInput: document.getElementById('password-input'),
    passwordToggle: document.getElementById('password-toggle'),
    logoutButton: document.getElementById('logout-button'),
    sessionEmail: document.getElementById('session-email'),
    sessionState: document.getElementById('session-state'),
    navLinks: Array.from(document.querySelectorAll('.nav-link')),
    authOnlyLinks: Array.from(document.querySelectorAll('[data-auth-only]')),
    views: Array.from(document.querySelectorAll('.view-panel')),
    healthStatusLabel: document.getElementById('health-status-label'),
    healthDbLabel: document.getElementById('health-db-label'),
    healthCacheLabel: document.getElementById('health-cache-label'),
    dashboardIncome: document.getElementById('dashboard-income'),
    dashboardExpense: document.getElementById('dashboard-expense'),
    dashboardBalance: document.getElementById('dashboard-balance'),
    dashboardTxCount: document.getElementById('dashboard-tx-count'),
    dashboardApiHealth: document.getElementById('dashboard-api-health'),
    dashboardDbHealth: document.getElementById('dashboard-db-health'),
    dashboardCacheHealth: document.getElementById('dashboard-cache-health'),
    dashboardUpdatedAt: document.getElementById('dashboard-updated-at'),
    transactionForm: document.getElementById('transaction-form'),
    transactionSubmit: document.getElementById('transaction-submit'),
    transactionsList: document.getElementById('transactions-list'),
    transactionsEmpty: document.getElementById('transactions-empty'),
    transactionsPrev: document.getElementById('transactions-prev'),
    transactionsNext: document.getElementById('transactions-next'),
    transactionsPageLabel: document.getElementById('transactions-page-label'),
    reportForm: document.getElementById('report-form'),
    reportMonth: document.getElementById('report-month'),
    reportSummary: document.getElementById('report-summary'),
    reportMonthLabel: document.getElementById('report-month-label'),
    reportIncomeLabel: document.getElementById('report-income-label'),
    reportExpenseLabel: document.getElementById('report-expense-label'),
    reportBalanceLabel: document.getElementById('report-balance-label'),
  };

  function init() {
    hydrateSession();
    bindEvents();
    setDefaultDates();
    renderSessionState();
    syncViewFromLocation(true);
    loadHealth();

    if (state.isAuthenticated) {
      loadAuthenticatedData();
    }
  }

  function bindEvents() {
    nodes.navLinks.forEach((link) => {
      link.addEventListener('click', function (event) {
        const target = link.getAttribute('data-view-target');
        if (!target) {
          return;
        }

        if (!canAccessView(target)) {
          event.preventDefault();
          navigateToView('auth', true);
          showAlert('Necesitas iniciar sesion para acceder a esa vista.', true);
          return;
        }

        navigateToView(target, true);
      });
    });

    window.addEventListener('hashchange', function () {
      syncViewFromLocation(false);
    });

    nodes.loginForm.addEventListener('submit', handleLogin);
    nodes.passwordToggle.addEventListener('click', togglePasswordVisibility);
    nodes.logoutButton.addEventListener('click', handleLogout);
    nodes.transactionForm.addEventListener('submit', handleCreateTransaction);
    nodes.reportForm.addEventListener('submit', handleReportSubmit);
    nodes.transactionsPrev.addEventListener('click', function () {
      if (state.transactions.page > 1) {
        loadTransactions(state.transactions.page - 1);
      }
    });
    nodes.transactionsNext.addEventListener('click', function () {
      if (state.transactions.page < state.transactions.totalPages) {
        loadTransactions(state.transactions.page + 1);
      }
    });
  }

  function setDefaultDates() {
    const now = new Date();
    const isoDate = toDateInputValue(now);
    const isoMonth = toMonthInputValue(now);
    const dateInput = nodes.transactionForm.querySelector('input[name="transaction_date"]');
    dateInput.value = isoDate;
    nodes.reportMonth.value = isoMonth;
  }

  function hydrateSession() {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return;
      }
      const parsed = JSON.parse(raw);
      state.accessToken = typeof parsed.accessToken === 'string' ? parsed.accessToken : '';
      state.refreshToken = typeof parsed.refreshToken === 'string' ? parsed.refreshToken : '';
      state.session.email = typeof parsed.email === 'string' ? parsed.email : '';
      state.isAuthenticated = Boolean(state.accessToken);
    } catch (_error) {
      clearStoredSession();
    }
  }

  function persistSession() {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        accessToken: state.accessToken,
        refreshToken: state.refreshToken,
        email: state.session.email,
      })
    );
  }

  function clearStoredSession() {
    window.localStorage.removeItem(STORAGE_KEY);
  }

  function renderSessionState() {
    nodes.sessionEmail.textContent = state.session.email || 'Invitado';
    nodes.sessionState.textContent = state.isAuthenticated ? 'Activa' : 'Esperando login';
    nodes.apiStatusChip.textContent = formatHealthStatus(state.health);
    nodes.tokenStatusChip.textContent = state.isAuthenticated ? 'Activo' : 'No disponible';
    nodes.logoutButton.hidden = !state.isAuthenticated;
    nodes.authOnlyLinks.forEach((link) => {
      link.classList.toggle('is-disabled', !state.isAuthenticated);
      link.setAttribute('aria-disabled', !state.isAuthenticated ? 'true' : 'false');
    });
  }

  function setView(viewName) {
    state.currentView = viewName;
    const titles = {
      auth: 'Ingreso seguro',
      dashboard: 'Dashboard operativo',
      transactions: 'Gestor de transacciones',
      reports: 'Reportes mensuales',
    };

    nodes.pageTitle.textContent = titles[viewName] || 'Financial SaaS';

    nodes.views.forEach((panel) => {
      const matches = panel.getAttribute('data-view') === viewName;
      panel.hidden = !matches;
      panel.classList.toggle('is-active', matches);
    });

    nodes.navLinks.forEach((link) => {
      const matches = link.getAttribute('data-view-target') === viewName;
      link.classList.toggle('is-active', matches);
    });

    loadViewData(viewName);
  }

  async function handleLogin(event) {
    event.preventDefault();
    setButtonBusy(nodes.loginSubmit, true, 'Entrando...');
    clearAlert();

    const formData = new FormData(nodes.loginForm);
    const payload = {
      email: String(formData.get('email') || '').trim().toLowerCase(),
      password: String(formData.get('password') || ''),
    };

    try {
      const response = await apiRequest('/api/v1/auth/login', {
        method: 'POST',
        body: payload,
      }, false);

      const data = response.data || {};
      state.accessToken = String(data.access_token || '');
      state.refreshToken = String(data.refresh_token || '');
      state.session.email = payload.email;
      state.isAuthenticated = Boolean(state.accessToken);

      if (!state.isAuthenticated) {
        throw new Error('No se recibio access token.');
      }

      persistSession();
      renderSessionState();
      navigateToView('dashboard', true);
      nodes.loginForm.reset();
      setDefaultDates();
      showAlert('Sesion iniciada. Se actualizaron las vistas protegidas.');
    } catch (error) {
      handleError(error, 'No fue posible iniciar sesion.');
    } finally {
      setButtonBusy(nodes.loginSubmit, false, 'Entrar al panel');
    }
  }

  function togglePasswordVisibility() {
    const shouldShow = nodes.passwordInput.type === 'password';
    nodes.passwordInput.type = shouldShow ? 'text' : 'password';
    nodes.passwordToggle.textContent = shouldShow ? 'Ocultar' : 'Mostrar';
    nodes.passwordToggle.classList.toggle('is-active', shouldShow);
    nodes.passwordToggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
    nodes.passwordToggle.setAttribute('aria-label', shouldShow ? 'Ocultar contrasena' : 'Mostrar contrasena');
  }

  function handleLogout() {
    state.accessToken = '';
    state.refreshToken = '';
    state.session.email = '';
    state.isAuthenticated = false;
    state.refreshPromise = null;
    clearStoredSession();
    renderSessionState();
    navigateToView('auth', true);
    showAlert('Sesion cerrada.');
  }

  async function handleCreateTransaction(event) {
    event.preventDefault();
    setButtonBusy(nodes.transactionSubmit, true, 'Guardando...');
    clearAlert();

    const formData = new FormData(nodes.transactionForm);
    const payload = {
      category_id: Number(formData.get('category_id')),
      type: String(formData.get('type') || ''),
      amount: Number(formData.get('amount')),
      description: String(formData.get('description') || '').trim(),
      transaction_date: String(formData.get('transaction_date') || ''),
    };

    try {
      await apiRequest('/api/v1/transactions', {
        method: 'POST',
        body: payload,
      }, true);

      showAlert('Transaccion registrada correctamente.');
      nodes.transactionForm.reset();
      setDefaultDates();
      await Promise.all([loadTransactions(1), loadDashboard(), loadReports(nodes.reportMonth.value)]);
    } catch (error) {
      handleError(error, 'No fue posible guardar la transaccion.');
    } finally {
      setButtonBusy(nodes.transactionSubmit, false, 'Guardar movimiento');
    }
  }

  async function handleReportSubmit(event) {
    event.preventDefault();
    clearAlert();
    try {
      await loadReports(nodes.reportMonth.value);
    } catch (error) {
      handleError(error, 'No fue posible cargar el reporte.');
    }
  }

  async function loadAuthenticatedData() {
    try {
      await Promise.all([
        loadDashboard(),
        loadTransactions(1),
        loadReports(nodes.reportMonth.value),
      ]);
    } catch (_error) {
      // Cada carga ya informa su propio error.
    }
  }

  function canAccessView(viewName) {
    return state.isAuthenticated || viewName === 'auth';
  }

  function navigateToView(viewName, updateHash) {
    const safeView = canAccessView(viewName) ? viewName : 'auth';
    if (updateHash && window.location.hash !== '#' + safeView) {
      window.location.hash = safeView;
      return;
    }

    setView(safeView);
  }

  function syncViewFromLocation(useDefaultFallback) {
    const requested = resolveViewFromHash(window.location.hash);

    if (!requested) {
      navigateToView(useDefaultFallback && state.isAuthenticated ? 'dashboard' : 'auth', true);
      return;
    }

    if (!canAccessView(requested)) {
      navigateToView('auth', true);
      if (!useDefaultFallback) {
        showAlert('Debes iniciar sesion antes de usar esa vista.', true);
      }
      return;
    }

    navigateToView(requested, false);
  }

  function resolveViewFromHash(hash) {
    const cleaned = String(hash || '').replace(/^#/, '').trim().toLowerCase();
    if (!cleaned) {
      return '';
    }

    const allowed = ['auth', 'dashboard', 'transactions', 'reports'];
    return allowed.indexOf(cleaned) >= 0 ? cleaned : '';
  }

  function loadViewData(viewName) {
    if (!state.isAuthenticated) {
      return;
    }

    if (viewName === 'dashboard') {
      loadDashboard();
      return;
    }

    if (viewName === 'transactions') {
      loadTransactions(state.transactions.page);
      return;
    }

    if (viewName === 'reports') {
      loadReports(nodes.reportMonth.value);
    }
  }

  async function loadHealth() {
    try {
      const response = await apiRequest('/api/v1/health', {}, false);
      state.health = response.data || {};
      renderHealth();
      renderSessionState();
    } catch (error) {
      state.health = null;
      renderHealth();
      handleError(error, 'No fue posible consultar la salud de la API.', true);
    }
  }

  function renderHealth() {
    const checks = state.health && state.health.checks ? state.health.checks : {};
    const status = formatHealthStatus(state.health);
    const dbOk = checks.database && checks.database.ok;
    const cacheOk = checks.cache && checks.cache.ok;

    nodes.healthStatusLabel.textContent = status;
    nodes.healthDbLabel.textContent = dbOk ? 'Operativa' : 'Sin validar';
    nodes.healthCacheLabel.textContent = cacheOk ? 'Operativa' : 'Sin validar';

    nodes.dashboardApiHealth.textContent = status;
    nodes.dashboardDbHealth.textContent = dbOk ? 'Operativa' : 'Sin validar';
    nodes.dashboardCacheHealth.textContent = cacheOk ? 'Operativa' : 'Sin validar';
  }

  async function loadDashboard() {
    if (!state.isAuthenticated) {
      return;
    }

    try {
      const response = await apiRequest('/api/v1/dashboard/summary');
      const data = response.data || {};
      const totalIncome = Number(data.total_income || 0);
      const totalExpense = Number(data.total_expense || 0);
      const balance = totalIncome - totalExpense;

      nodes.dashboardIncome.textContent = formatCurrency(totalIncome);
      nodes.dashboardExpense.textContent = formatCurrency(totalExpense);
      nodes.dashboardBalance.textContent = formatCurrency(balance);
      nodes.dashboardTxCount.textContent = String(data.tx_count || 0);
      nodes.dashboardUpdatedAt.textContent = DATE_TIME_FORMATTER.format(new Date());
      nodes.tokenStatusChip.textContent = 'Activo';
    } catch (error) {
      handleError(error, 'No fue posible cargar el dashboard.');
    }
  }

  async function loadTransactions(page) {
    if (!state.isAuthenticated) {
      return;
    }

    try {
      const response = await apiRequest('/api/v1/transactions?page=' + encodeURIComponent(String(page)) + '&per_page=8');
      const data = response.data || {};
      const items = Array.isArray(data.items) ? data.items : [];
      const meta = data.meta || {};

      state.transactions.page = Number(meta.page || page || 1);
      state.transactions.totalPages = Number(meta.total_pages || 1);

      nodes.transactionsPageLabel.textContent = 'Pagina ' + state.transactions.page + ' de ' + state.transactions.totalPages;
      nodes.transactionsPrev.disabled = state.transactions.page <= 1;
      nodes.transactionsNext.disabled = state.transactions.page >= state.transactions.totalPages;
      nodes.transactionsEmpty.hidden = items.length > 0;
      nodes.transactionsList.innerHTML = items.map(renderTransactionItem).join('');
    } catch (error) {
      handleError(error, 'No fue posible cargar las transacciones.');
    }
  }

  async function loadReports(month) {
    if (!state.isAuthenticated) {
      return;
    }

    try {
      const response = await apiRequest('/api/v1/reports/monthly?month=' + encodeURIComponent(month));
      const data = response.data || {};
      const summary = Array.isArray(data.summary) ? data.summary : [];
      const totals = summary.reduce(function (acc, item) {
        const type = String(item.type || '');
        const total = Number(item.total || 0);
        if (type === 'income') {
          acc.income = total;
        }
        if (type === 'expense') {
          acc.expense = total;
        }
        return acc;
      }, { income: 0, expense: 0 });
      const maxValue = Math.max(totals.income, totals.expense, 1);

      nodes.reportMonthLabel.textContent = String(data.month || month);
      nodes.reportIncomeLabel.textContent = formatCurrency(totals.income);
      nodes.reportExpenseLabel.textContent = formatCurrency(totals.expense);
      nodes.reportBalanceLabel.textContent = formatCurrency(totals.income - totals.expense);
      nodes.reportSummary.innerHTML = summary.map(function (item) {
        const total = Number(item.total || 0);
        const width = Math.max((total / maxValue) * 100, 6);
        return (
          '<article class="report-item">' +
            '<div>' +
              '<strong>' + escapeHtml(labelForTransactionType(String(item.type || ''))) + '</strong>' +
              '<div class="report-bar"><span style="width:' + width.toFixed(2) + '%"></span></div>' +
            '</div>' +
            '<strong>' + escapeHtml(formatCurrency(total)) + '</strong>' +
          '</article>'
        );
      }).join('') || '<div class="empty-state">No hay datos para ese mes.</div>';
    } catch (error) {
      handleError(error, 'No fue posible cargar el reporte.');
    }
  }

  function renderTransactionItem(item) {
    const type = String(item.type || '');
    const description = escapeHtml(String(item.description || 'Sin descripcion'));
    const amount = formatCurrency(Number(item.amount || 0));
    const dateValue = String(item.transaction_date || '');
    const categoryId = String(item.category_id || '-');

    return (
      '<article class="transaction-item">' +
        '<div class="transaction-top">' +
          '<div>' +
            '<strong>' + description + '</strong>' +
            '<div class="transaction-meta">Categoria ' + escapeHtml(categoryId) + '</div>' +
          '</div>' +
          '<div class="transaction-type ' + escapeHtml(type) + '">' + escapeHtml(labelForTransactionType(type)) + '</div>' +
        '</div>' +
        '<div class="transaction-bottom">' +
          '<span class="transaction-date">' + escapeHtml(dateValue) + '</span>' +
          '<strong class="transaction-amount">' + escapeHtml(amount) + '</strong>' +
        '</div>' +
      '</article>'
    );
  }

  async function apiRequest(url, options, requiresAuth, hasRetried) {
    const requestOptions = options || {};
    const headers = {
      Accept: 'application/json',
    };

    if (requestOptions.body) {
      headers['Content-Type'] = 'application/json';
    }

    if (requiresAuth !== false && state.accessToken) {
      headers.Authorization = 'Bearer ' + state.accessToken;
    }

    const response = await fetch(url, {
      method: requestOptions.method || 'GET',
      headers: headers,
      credentials: 'same-origin',
      body: requestOptions.body ? JSON.stringify(requestOptions.body) : undefined,
    });

    const payload = await safeJson(response);

    if (response.status === 401 && requiresAuth !== false && state.refreshToken && !hasRetried) {
      await refreshSession();
      return apiRequest(url, options, requiresAuth, true);
    }

    if (!response.ok || (payload && payload.success === false)) {
      throw buildApiError(payload, response.status);
    }

    return payload || {};
  }

  async function refreshSession() {
    if (state.refreshPromise) {
      return state.refreshPromise;
    }

    state.refreshPromise = (async function () {
      const response = await fetch('/api/v1/auth/refresh', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ refresh_token: state.refreshToken }),
      });
      const payload = await safeJson(response);

      if (!response.ok || !payload || payload.success === false) {
        handleLogout();
        throw buildApiError(payload, response.status);
      }

      const data = payload.data || {};
      state.accessToken = String(data.access_token || '');
      state.refreshToken = String(data.refresh_token || state.refreshToken);
      state.isAuthenticated = Boolean(state.accessToken);
      persistSession();
      renderSessionState();
      return payload;
    })();

    try {
      return await state.refreshPromise;
    } finally {
      state.refreshPromise = null;
    }
  }

  async function safeJson(response) {
    try {
      return await response.json();
    } catch (_error) {
      return null;
    }
  }

  function buildApiError(payload, statusCode) {
    const error = new Error('Error de API');
    const errors = payload && Array.isArray(payload.errors) ? payload.errors : [];
    const first = errors[0] || {};
    error.message = String(first.message || 'No se pudo completar la solicitud.');
    error.statusCode = statusCode;
    return error;
  }

  function setButtonBusy(button, isBusy, busyLabel) {
    if (!button.hasAttribute('data-idle-label')) {
      button.setAttribute('data-idle-label', button.textContent);
    }

    button.disabled = isBusy;
    button.textContent = isBusy ? busyLabel : button.getAttribute('data-idle-label');
  }

  function showAlert(message, isError) {
    nodes.alert.textContent = message;
    nodes.alert.hidden = false;
    nodes.alert.classList.toggle('is-error', Boolean(isError));
  }

  function clearAlert() {
    nodes.alert.hidden = true;
    nodes.alert.textContent = '';
    nodes.alert.classList.remove('is-error');
  }

  function handleError(error, fallbackMessage, silent) {
    const message = error && error.message ? error.message : fallbackMessage;
    if (!silent) {
      showAlert(message || fallbackMessage, true);
    }
  }

  function formatCurrency(value) {
    return CURRENCY_FORMATTER.format(Number.isFinite(value) ? value : 0);
  }

  function formatHealthStatus(health) {
    if (!health) {
      return 'Sin respuesta';
    }
    return String(health.status || 'Sin respuesta');
  }

  function labelForTransactionType(type) {
    if (type === 'income') {
      return 'Ingreso';
    }
    if (type === 'expense') {
      return 'Gasto';
    }
    return 'Movimiento';
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function toDateInputValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
  }

  function toMonthInputValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return year + '-' + month;
  }

  init();
})();
