import { errorText, useTranslation } from '@sr/shared';
import { useState } from 'react';

import { api } from '../lib/api';
import { useAuth, type AdminUser } from '../lib/auth';

export function LoginScreen() {
  const { t, locale } = useTranslation();
  const setSession = useAuth((state) => state.setSession);

  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);

    try {
      const { data } = await api.post<{ token: string; user: AdminUser }>('/auth/login', {
        login,
        password,
      });

      setSession(data.token, data.user);
    } catch (caught) {
      // Server xabari ikkala tilda keladi; tarmoq uzilgan bo'lsa
      // frontend lug'atiga qaytiladi.
      setError(errorText(caught, locale, t));
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="flex min-h-dvh items-center justify-center bg-slate-100 p-6">
      <form onSubmit={submit} className="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        <h1 className="text-xl font-semibold text-slate-900">{t('admin.login_title')}</h1>

        <label className="block">
          <span className="text-sm text-slate-600">{t('admin.username')}</span>
          <input
            value={login}
            onChange={(event) => setLogin(event.target.value)}
            autoComplete="username"
            required
            className="mt-1 h-11 w-full rounded-xl bg-slate-50 px-4 ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-900"
          />
        </label>

        <label className="block">
          <span className="text-sm text-slate-600">{t('admin.password')}</span>
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete="current-password"
            required
            className="mt-1 h-11 w-full rounded-xl bg-slate-50 px-4 ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-900"
          />
        </label>

        {error !== null && (
          <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{error}</p>
        )}

        <button
          type="submit"
          disabled={busy}
          className="h-12 w-full rounded-xl bg-slate-900 font-semibold text-white transition active:bg-slate-800 disabled:bg-slate-300"
        >
          {busy ? t('common.loading') : t('admin.login')}
        </button>
      </form>
    </main>
  );
}
