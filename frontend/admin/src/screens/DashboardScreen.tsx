import { formatMoney, useTranslation, type TableStatus } from '@sr/shared';
import { useCallback, useEffect, useState } from 'react';

import { api } from '../lib/api';

interface DashboardData {
  today: {
    revenue: number;
    orders: number;
    guests: number;
    average_check: number;
    pending_orders: number;
  };
  tables: {
    id: number;
    number: number;
    capacity: number;
    status: TableStatus;
    session: { guest_count: number; total_amount: number; open_orders: number } | null;
  }[];
  limits: Record<string, { used: number; max: number }>;
}

/** docs/01-ARCHITECTURE.md §3 — stol statusi rangi. */
const STATUS_STYLE: Record<TableStatus, string> = {
  AVAILABLE: 'bg-white text-slate-400 ring-slate-200',
  ACTIVE: 'bg-sky-50 text-sky-900 ring-sky-200',
  ORDER_PENDING: 'bg-rose-50 text-rose-900 ring-rose-300',
  WAITER_ASSIGNED: 'bg-amber-50 text-amber-900 ring-amber-200',
  DELIVERED: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
  WAITING_PAYMENT: 'bg-violet-50 text-violet-900 ring-violet-300',
};

export function DashboardScreen() {
  const { t, locale } = useTranslation();
  const [data, setData] = useState<DashboardData | null>(null);

  const load = useCallback(async () => {
    const response = await api.get<DashboardData>('/admin/dashboard');
    setData(response.data);
  }, []);

  useEffect(() => {
    void load();

    // PHASE 9 gacha polling. Real-time WebSocket keyin qo'shiladi.
    const timer = setInterval(() => void load(), 10000);

    return () => clearInterval(timer);
  }, [load]);

  if (data === null) return <p className="p-8 text-slate-400">{t('common.loading')}</p>;

  return (
    <div className="space-y-8 p-6">
      <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label={t('admin.revenue')} value={formatMoney(data.today.revenue, locale)} />
        <Stat label={t('admin.orders')} value={String(data.today.orders)} />
        <Stat label={t('admin.guests')} value={String(data.today.guests)} />
        <Stat label={t('admin.avg_check')} value={formatMoney(data.today.average_check, locale)} />
      </section>

      <section>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
          {t('admin.tables')}
        </h2>

        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
          {data.tables.map((table) => (
            <div
              key={table.id}
              className={`rounded-2xl p-4 ring-1 ${STATUS_STYLE[table.status]}`}
            >
              <p className="text-2xl font-semibold tabular-nums">{table.number}</p>
              <p className="mt-1 text-xs">
                {table.session === null
                  ? t('admin.table_free')
                  : t('customer.guests', { count: table.session.guest_count })}
              </p>
              {table.session !== null && table.session.total_amount > 0 && (
                <p className="mt-1 text-xs font-medium">
                  {formatMoney(table.session.total_amount, locale)}
                </p>
              )}
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
      <p className="text-xs uppercase tracking-wide text-slate-400">{label}</p>
      <p className="mt-1 text-xl font-semibold text-slate-900">{value}</p>
    </div>
  );
}
