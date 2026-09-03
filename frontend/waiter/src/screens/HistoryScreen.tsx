import { formatDateTime, formatMoney, useTranslation, type Order } from '@sr/shared';
import { useEffect, useState } from 'react';

import { api } from '../lib/api';

interface HistoryOrder extends Order {
  table?: { number: number } | null;
}

export function HistoryScreen() {
  const { t, locale } = useTranslation();
  const [orders, setOrders] = useState<HistoryOrder[]>([]);

  useEffect(() => {
    void api
      .get<{ items: HistoryOrder[] }>('/waiter/history')
      .then(({ data }) => setOrders(data.items));
  }, []);

  if (orders.length === 0) {
    return <p className="p-8 text-center text-slate-400">{t('waiter.no_history')}</p>;
  }

  return (
    <ul className="divide-y divide-slate-100">
      {orders.map((order) => (
        <li key={order.id} className="flex items-center gap-3 bg-white px-4 py-3">
          <div className="min-w-0 flex-1">
            <p className="font-medium text-slate-900">
              {t('common.table')} {order.table?.number ?? '—'} · {order.order_number}
            </p>
            <p className="text-xs text-slate-400">
              {order.delivered_at !== null ? formatDateTime(order.delivered_at) : '—'}
            </p>
          </div>
          <span className="shrink-0 font-semibold text-slate-900">
            {formatMoney(order.total, locale)}
          </span>
        </li>
      ))}
    </ul>
  );
}
