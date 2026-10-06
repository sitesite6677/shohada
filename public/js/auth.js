/**
 * ==============================================================================
 * ماژول احراز هویت مدیریت: js/auth.js
 * ==============================================================================
 * مدیریت ورود، خروج، و بررسی نشست مدیر در سیستم
 */
let _cachedAdminEmail = null;

function getAuthApiUrl(endpoint) {
  const base = (window.APP_CONFIG && window.APP_CONFIG.apiBaseUrl) || window.API_BASE_URL || '/api';
  const cleanBase = base.replace(/\/+$/, '');
  const cleanEndpoint = endpoint.replace(/^\/+/, '');
  return `${cleanBase}/${cleanEndpoint}`;
}

async function isAuthenticatedAdmin() {
  let token = null;
  try {
    token = localStorage.getItem('ADMIN_AUTH_TOKEN');
  } catch (e) {}

  if (!token) {
    _cachedAdminEmail = null;
    return false;
  }

  try {
    const resp = await fetch(getAuthApiUrl('auth?action=check'), {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    const res = await resp.json();
    if (res.authenticated && res.user) {
      _cachedAdminEmail = res.user.email;
      return true;
    }
    _cachedAdminEmail = null;
    return false;
  } catch (err) {
    console.warn('خطا در بررسی توکن ادمین:', err);
    return false;
  }
}

async function getAdminEmail() {
  if (_cachedAdminEmail) {
    return _cachedAdminEmail;
  }
  const isAuth = await isAuthenticatedAdmin();
  return isAuth ? (_cachedAdminEmail || 'مدیر سیستم') : '';
}

async function loginAdmin(email, password) {
  const cleanEmail = (email || '').trim();
  const cleanPass = (password || '').trim();

  if (!cleanEmail) {
    return { success: false, message: 'لطفاً ایمیل مدیریت را وارد کنید.' };
  }
  if (!cleanPass) {
    return { success: false, message: 'لطفاً رمز عبور را وارد کنید.' };
  }

  try {
    const resp = await fetch(getAuthApiUrl('auth'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: cleanEmail,
        password: cleanPass
      })
    });
    const res = await resp.json();
    if (!res.success || !res.token) {
      return {
        success: false,
        message: res.message || 'ایمیل یا رمز عبور نامعتبر است.'
      };
    }

    try {
      localStorage.setItem('ADMIN_AUTH_TOKEN', res.token);
    } catch (e) {}

    _cachedAdminEmail = res.user ? res.user.email : cleanEmail;
    return {
      success: true,
      user: res.user,
      token: res.token
    };
  } catch (err) {
    return {
      success: false,
      message: `خطا در ارتباط با سرور: ${err.message}`
    };
  }
}

async function logoutAdmin() {
  let token = null;
  try {
    token = localStorage.getItem('ADMIN_AUTH_TOKEN');
    localStorage.removeItem('ADMIN_AUTH_TOKEN');
  } catch (e) {}

  _cachedAdminEmail = null;

  if (token) {
    try {
      await fetch(getAuthApiUrl('auth?action=logout'), {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
    } catch (e) {}
  }

  if (typeof window.showLoginView === 'function') {
    window.showLoginView();
  } else {
    window.location.reload();
  }
}

window.AdminAuth = {
  isAuthenticatedAdmin,
  getAdminEmail,
  loginAdmin,
  logoutAdmin
};
