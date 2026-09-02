import { formatMoney, formatTime, useTranslation, type Order } from '@sr/shared';
import { useCallback, useEffect, useState } from 'react';

import { api } from '../lib/api';

interface OrderRow extends Order {
  table?: { number: number } | null;
}

/**
 * Yangi buyurtmalar va ularni qabul qilish.
 *
 * ⚠️ DRAFT bu ro'yxatda HECH QACHON ko'rinmaydi — server `visible()`
 * scope'i bilan filtrlaydi (docs/05-PHASE0-PLAN.md §2.4).
 */
export function OrdersScreen() {
  const { t, locale } = useTranslation();
  const [orders, setOrders] = useState<OrderRow[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    const { data } = await api.get<{ items: OrderRow[] }>('/admin/orders?status=PENDING');
    setOrders(data.items);
  }, []);

  useEffect(() => {
    void load();

    // PHASE 9 da WebSocket bilan almashtiriladi.
    const timer = setInterval(() => void load(), 8000);

    return () => clearInterval(timer);
  }, [load]);

  const act = async (id: number, action: 'accept' | 'cancel') => {
    setBusyId(id);

    try {
      await api.post(`/admin/orders/${id}/${action}`);
      await load();
    } finally {
      setBusyId(null);
    }
  };

  if (orders.length === 0) {
    return <p className="p-8 text-slate-400">{t('admin.no_orders')}</p>;
  }

  return (
    <ul className="grid gap-3 p-6 lg:grid-cols-2 xl:grid-cols-3">
      {orders.map((order) => (
        <li key={order.id} className="rounded-2xl bg-white p-4 ring-1 ring-rose-200">
          <div className="flex items-baseline justify-between">
            <span className="font-semibold text-slate-900">
              {t('common.table')} {order.table?.number ?? '—'}
            </span>
            <span className="text-xs text-slate-400">
              {order.order_number} · {formatTime(order.created_at)}
            </span>
          </div>

          <ul className="mt-3 space-y-1 text-sm">
            {order.items.map((item) => (
              <li key={item.product_id} className="flex justify-between gap-3">
                <span className="text-slate-600">
                  {item.name} × {item.quantity}
                </span>
                <span className="text-slate-500">{formatMoney(item.subtotal, locale)}</span>
              </li>
            ))}
          </ul>

          {order.note !== null && (
            <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
              {order.note}
            </p>
          )}

          <div className="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-3">
            <span className="text-sm text-slate-500">{t('common.total')}</span>
            <span className="text-lg font-semibold text-slate-900">
              {formatMoney(order.total, locale)}
            </span>
          </div>

          <div className="mt-3 flex gap-2">
            <button
              type="button"
              onClick={() => void act(order.id, 'accept')}
              disabled={busyId === order.id}
              className="h-12 flex-1 rounded-xl bg-slate-900 font-semibold text-white transition active:bg-slate-800 disabled:bg-slate-300"
            >
              {t('admin.accept')}
            </button>
            <button
              type="button"
              onClick={() => void act(order.id, 'cancel')}
              disabled={busyId === order.id}
              className="h-12 rounded-xl px-4 text-sm font-medium text-slate-500 ring-1 ring-slate-200 transition active:bg-slate-100"
            >
              {t('common.cancel')}
            </button>
          </div>
        </li>
      ))}
    </ul>
  );
}
