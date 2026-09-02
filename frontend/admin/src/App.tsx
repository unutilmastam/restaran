import { SUPPORTED_LOCALES, setLocale, useTranslation, type Locale } from '@sr/shared';
import { useEffect, useState } from 'react';

import { api } from './lib/api';
import { useAuth, type AdminUser } from './lib/auth';
import { DashboardScreen } from './screens/DashboardScreen';
import { LoginScreen } from './screens/LoginScreen';
import { OrdersScreen } from './screens/OrdersScreen';

type Tab = 'dashboard' | 'orders';

/**
 * PHASE 6 — dashboard va buyurtmalar. Menyu/stol/afitsant CRUD
 * ekranlari API bilan to'liq tayyor; UI ularga PHASE 6 davomida
 * qo'shiladi. Real-time PHASE 9 da (hozircha polling).
 */
export default function App() {
  const { t, locale } = useTranslation();
  const token = useAuth((state) => state.token);
  const user = useAuth((state) => state.user);
  const setUser = useAuth((state) => state.setUser);
  const clear = useAuth((state) => state.clear);

  const [tab, setTab] = useState<Tab>('dashboard');

  // Sahifa yangilanganda token bor, lekin user yo'q — uni tiklaymiz.
  useEffect(() => {
    if (token === null || user !== null) return;

    void (async () => {
      try {
        const { data } = await api.get<{ user: AdminUser }>('/auth/me');
        setUser(data.user);
      } catch {
        clear();
      }
    })();
  }, [token, user, setUser, clear]);

  if (token === null) return <LoginScreen />;
  if (user === null) return <p className="p-8 text-slate-400">{t('common.loading')}</p>;

  return (
    <div className="min-h-dvh bg-slate-100">
      <header className="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-6 py-3">
        <div className="min-w-0 flex-1">
          <p className="truncate font-semibold text-slate-900">
            {user.restaurant?.name ?? t('admin.dashboard')}
          </p>
          <p className="text-xs text-slate-500">{user.name}</p>
        </div>

        <nav className="flex gap-1">
          {(['dashboard', 'orders'] as Tab[]).map((item) => (
            <button
              key={item}
              type="button"
              onClick={() => setTab(item)}
              className={`h-10 rounded-xl px-4 text-sm font-medium transition ${
                tab === item ? 'bg-slate-900 text-white' : 'text-slate-600 ring-1 ring-slate-200'
              }`}
            >
              {t(item === 'dashboard' ? 'admin.dashboard' : 'admin.orders')}
            </button>
          ))}
        </nav>

        <div className="flex gap-1">
          {SUPPORTED_LOCALES.map((option: Locale) => (
            <button
              key={option}
              type="button"
              onClick={() => setLocale(option)}
              className={`h-10 w-11 rounded-xl text-sm font-semibold uppercase transition ${
                locale === option ? 'bg-slate-900 text-white' : 'text-slate-500 ring-1 ring-slate-200'
              }`}
            >
              {option}
            </button>
          ))}
        </div>

        <button
          type="button"
          onClick={() => {
            void api.post('/auth/logout').catch(() => undefined);
            clear();
          }}
          className="h-10 rounded-xl px-4 text-sm font-medium text-slate-500 ring-1 ring-slate-200 transition active:bg-slate-100"
        >
          {t('admin.logout')}
        </button>
      </header>

      <main>{tab === 'dashboard' ? <DashboardScreen /> : <OrdersScreen />}</main>
    </div>
  );
}
