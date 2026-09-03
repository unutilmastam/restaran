import { errorText, useTranslation } from '@sr/shared';
import { useState } from 'react';

import { inputClass } from '../components/Field';
import { api } from '../lib/api';
import { useAuth, type WaiterUser } from '../lib/auth';

/** Telefon uchun: PIN bilan tez kirish yoki parol. */
export function LoginScreen() {
  const { t, locale } = useTranslation();
  const setSession = useAuth((state) => state.setSession);

  const [mode, setMode] = useState<'pin' | 'password'>('pin');
  const [login, setLogin] = useState('');
  const [secret, setSecret] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);

    try {
      const { data } = await api.post<{ token: string; user: WaiterUser }>('/auth/login', {
        login,
        [mode]: secret,
      });

      setSession(data.token, data.user);
    } catch (caught) {
      setError(errorText(caught, locale, t));
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="flex min-h-dvh flex-col justify-center bg-slate-900 p-6">
      <form onSubmit={submit} className="space-y-4 rounded-3xl bg-white p-6">
        <h1 className="text-xl font-semibold text-slate-900">{t('waiter.login_title')}</h1>

        <label className="block">
          <span className="text-sm text-slate-600">{t('admin.username')}</span>
          <input
            value={login}
            onChange={(event) => setLogin(event.target.value)}
            autoComplete="username"
            autoCapitalize="none"
            required
            className={`${inputClass} h-12`}
          />
        </label>

        <label className="block">
          <span className="text-sm text-slate-600">
            {mode === 'pin' ? t('admin.pin') : t('admin.password')}
          </span>
          <input
            type="password"
            // PIN uchun raqamli klaviatura ochiladi.
            inputMode={mode === 'pin' ? 'numeric' : 'text'}
            value={secret}
            onChange={(event) => setSecret(event.target.value)}
            required
            className={`${inputClass} h-12`}
          />
        </label>

        <button
          type="button"
          onClick={() => {
            setMode(mode === 'pin' ? 'password' : 'pin');
            setSecret('');
          }}
          className="text-sm font-medium text-slate-500 underline"
        >
          {mode === 'pin' ? t('admin.password') : t('admin.pin')}
        </button>

        {error !== null && (
          <p className="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{error}</p>
        )}

        <button
          type="submit"
          disabled={busy}
          className="h-14 w-full rounded-2xl bg-slate-900 text-base font-semibold text-white disabled:bg-slate-300"
        >
          {busy ? t('common.loading') : t('admin.login')}
        </button>
      </form>
    </main>
  );
}
