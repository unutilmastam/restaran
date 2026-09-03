import { SUPPORTED_LOCALES, setLocale, useTranslation, type Locale } from '@sr/shared';
import { useCallback, useEffect, useState } from 'react';

import { api } from './lib/api';
import { useAuth, type WaiterUser } from './lib/auth';
import { CallsScreen } from './screens/CallsScreen';
import { HistoryScreen } from './screens/HistoryScreen';
import { LoginScreen } from './screens/LoginScreen';
import { OrdersScreen } from './screens/OrdersScreen';

type Tab = 'orders' | 'calls' | 'history' | 'profile';

interface Profile {
  waiter: WaiterUser & { status: 'FREE' | 'BUSY' | 'OFFLINE' | null };
  today: { delivered: number; active: number };
}

const TAB_KEY: Record<Tab, string> = {
  orders: 'waiter.my_orders',
  calls: 'waiter.calls',
  history: 'waiter.history',
  profile: 'waiter.profile',
};

export default function App() {
  const { t, locale } = useTranslation();
  const token = useAuth((state) => state.token);
  const clear = useAuth((state) => state.clear);

  const [tab, setTab] = useState<Tab>('orders');
  const [profile, setProfile] = useState<Profile | null>(null);

  const loadProfile = useCallback(async () => {
    if (token === null) return;

    try {
      const { data } = await api.get<Profile>('/waiter/profile');
      setProfile(data);
    } catch {
      clear();
    }
  }, [token, clear]);

  useEffect(() => {
    void loadProfile();
  }, [loadProfile]);

  if (token === null) return <LoginScreen />;
  if (profile === null) return <p className="p-8 text-center text-slate-400">{t('common.loading')}</p>;

  const status = profile.waiter.status ?? 'OFFLINE';
  const online = status !== 'OFFLINE';

  const toggleShift = async () => {
    await api.post('/waiter/status', { status: online ? 'OFFLINE' : 'FREE' });
    await loadProfile();
  };

  return (
    <div className="flex min-h-dvh flex-col bg-slate-100">
      <header className="bg-slate-900 px-4 pb-4 pt-3 text-white">
        <div className="flex items-center gap-3">
          <div className="min-w-0 flex-1">
            <p className="truncate text-lg font-semibold">
              {t('waiter.greeting', { name: profile.waiter.name })}
            </p>
            <p className="mt-0.5 text-sm">
              {/* 🟢 BO'SH / 🔴 BAND — docs/03-PHASES.md PHASE 7 */}
              {status === 'FREE' && `🟢 ${t('waiter.status_free')}`}
              {status === 'BUSY' && `🔴 ${t('waiter.status_busy')}`}
              {status === 'OFFLINE' && `⚪ ${t('waiter.status_offline')}`}
            </p>
          </div>

          <div className="flex gap-1">
            {SUPPORTED_LOCALES.map((option: Locale) => (
              <button
                key={option}
                type="button"
                onClick={() => setLocale(option)}
                className={`h-10 w-11 rounded-xl text-sm font-semibold uppercase ${
                  locale === option ? 'bg-white text-slate-900' : 'text-slate-300 ring-1 ring-white/20'
                }`}
              >
                {option}
              </button>
            ))}
          </div>
        </div>
      </header>

      <main className="flex-1 pb-20">
        {tab === 'orders' && <OrdersScreen onChanged={() => void loadProfile()} />}
        {tab === 'calls' && <CallsScreen />}
        {tab === 'history' && <HistoryScreen />}
        {tab === 'profile' && (
          <div className="space-y-4 p-4">
            <dl className="grid grid-cols-2 gap-3">
              <Stat label={t('waiter.today_delivered')} value={String(profile.today.delivered)} />
              <Stat label={t('waiter.active_orders')} value={String(profile.today.active)} />
            </dl>

            <div className="rounded-2xl bg-white p-4">
              <p className="font-medium text-slate-900">{profile.waiter.name}</p>
              <p className="text-sm text-slate-500">@{profile.waiter.username}</p>
              {profile.waiter.restaurant !== null && (
                <p className="mt-1 text-sm text-slate-400">{profile.waiter.restaurant.name}</p>
              )}
            </div>

            <button
              type="button"
              onClick={() => void toggleShift()}
              className={`h-14 w-full rounded-2xl text-base font-semibold ${
                online ? 'bg-white text-slate-700 ring-1 ring-slate-200' : 'bg-emerald-600 text-white'
              }`}
            >
              {online ? t('waiter.go_offline') : t('waiter.go_online')}
            </button>

            <button
              type="button"
              onClick={() => {
                void api.post('/auth/logout').catch(() => undefined);
                clear();
              }}
              className="h-12 w-full rounded-2xl text-sm font-medium text-slate-500"
            >
              {t('admin.logout')}
            </button>
          </div>
        )}
      </main>

      {/* Pastki navigatsiya — bosh barmoq yetadigan joyda. */}
      <nav className="fixed inset-x-0 bottom-0 flex border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]">
        {(Object.keys(TAB_KEY) as Tab[]).map((item) => (
          <button
            key={item}
            type="button"
            onClick={() => setTab(item)}
            className={`h-16 flex-1 text-xs font-medium transition ${
              tab === item ? 'text-slate-900' : 'text-slate-400'
            }`}
          >
            <span className={`block ${tab === item ? 'border-t-2 border-slate-900 pt-3' : 'pt-3'}`}>
              {t(TAB_KEY[item])}
            </span>
          </button>
        ))}
      </nav>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-white p-4">
      <dt className="text-xs uppercase tracking-wide text-slate-400">{label}</dt>
      <dd className="mt-1 text-2xl font-semibold text-slate-900">{value}</dd>
    </div>
  );
}
