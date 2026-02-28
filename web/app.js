const apiBase = '/api';
let token = localStorage.getItem('token') || '';

const els = {
  showRegister: document.getElementById('showRegister'),
  showLogin: document.getElementById('showLogin'),
  registerForm: document.getElementById('registerForm'),
  loginForm: document.getElementById('loginForm'),
  appSection: document.getElementById('appSection'),
  authCard: document.getElementById('authCard'),
  message: document.getElementById('message'),
  meInfo: document.getElementById('meInfo'),
  jiraForm: document.getElementById('jiraForm'),
  projectForm: document.getElementById('projectForm'),
  projectsList: document.getElementById('projectsList'),
  logoutBtn: document.getElementById('logoutBtn')
};

function setMessage(text) {
  els.message.textContent = text;
}

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || 'Ошибка запроса');
  return data;
}

function switchAuth(mode) {
  const register = mode === 'register';
  els.registerForm.classList.toggle('hidden', !register);
  els.loginForm.classList.toggle('hidden', register);
  els.showRegister.classList.toggle('active', register);
  els.showLogin.classList.toggle('active', !register);
}

async function loadApp() {
  if (!token) {
    els.appSection.classList.add('hidden');
    els.authCard.classList.remove('hidden');
    return;
  }

  try {
    const me = await api('/me');
    els.meInfo.textContent = `${me.name} • ${me.email}`;
    els.jiraForm.jiraBaseUrl.value = me.jira_base_url || '';
    els.jiraForm.jiraEmail.value = me.jira_email || '';
    els.jiraForm.jiraApiToken.value = '';

    els.authCard.classList.add('hidden');
    els.appSection.classList.remove('hidden');
    await loadProjects();
  } catch (e) {
    token = '';
    localStorage.removeItem('token');
    setMessage(e.message);
  }
}

async function loadProjects() {
  const projects = await api('/projects');
  els.projectsList.innerHTML = '';
  if (!projects.length) {
    els.projectsList.innerHTML = '<li>Проекты пока не добавлены.</li>';
    return;
  }
  projects.forEach((p) => {
    const li = document.createElement('li');
    li.innerHTML = `<strong>${p.title}</strong> — ${p.status}<br><small>${p.description || ''}</small>`;
    els.projectsList.appendChild(li);
  });
}

els.showRegister.addEventListener('click', () => switchAuth('register'));
els.showLogin.addEventListener('click', () => switchAuth('login'));

els.registerForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = Object.fromEntries(new FormData(els.registerForm).entries());
  try {
    const data = await api('/auth/register', { method: 'POST', body: JSON.stringify(formData) });
    token = data.token;
    localStorage.setItem('token', token);
    setMessage('Регистрация успешна');
    els.registerForm.reset();
    await loadApp();
  } catch (err) {
    setMessage(err.message);
  }
});

els.loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = Object.fromEntries(new FormData(els.loginForm).entries());
  try {
    const data = await api('/auth/login', { method: 'POST', body: JSON.stringify(formData) });
    token = data.token;
    localStorage.setItem('token', token);
    setMessage('Вход выполнен');
    els.loginForm.reset();
    await loadApp();
  } catch (err) {
    setMessage(err.message);
  }
});

els.jiraForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(els.jiraForm).entries());
  try {
    await api('/integrations/jira', { method: 'PUT', body: JSON.stringify(payload) });
    setMessage('Интеграция Jira сохранена');
    els.jiraForm.jiraApiToken.value = '';
  } catch (err) {
    setMessage(err.message);
  }
});

els.projectForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(els.projectForm).entries());
  try {
    await api('/projects', { method: 'POST', body: JSON.stringify(payload) });
    setMessage('Проект добавлен');
    els.projectForm.reset();
    await loadProjects();
  } catch (err) {
    setMessage(err.message);
  }
});

els.logoutBtn.addEventListener('click', () => {
  token = '';
  localStorage.removeItem('token');
  setMessage('Вы вышли из аккаунта');
  loadApp();
});

switchAuth('register');
loadApp();
