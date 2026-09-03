import { formatMoney, formatTime, useTranslation, type Order } from '@sr/shared';
import { useCallback, useEffect, useState } from 'react';

import { HoldButton } from '../components/HoldButton';
import { api } from '../lib/api';

interface WaiterOrder extends Order {
  table?: { number: number; name: string | null } | null;
}

interface Props {
  onChanged: () => void;
}

/**
 * Menga biriktirilgan buyurtmalar.
 *
 * Oqim: ASSIGNED → [Qabul qilish] → WAITER_ACCEPTED →
 *       [Oshxonadan oldim] → DELIVERING → [Yetkazildi] → DELIVERED
 *
 * ⚠️ "Yetkazildi" — BOSIB TURISH tugmasi (HoldButton). Afitsant
 * telefonni bir qo'lda ushlab yuradi va tasodifan bosish buyurtmani
 * yetkazilgan deb belgilardi.
 */
export function OrdersScreen({ onChanged }: Props) {
  const { t, locale } = useTranslation();
  const [orders, setOrders] = useState<WaiterOrder[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const { data } = await api.get<{ items: WaiterOrder[] }>('/waiter/orders');
      setOrders(data.items);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();

    // Real-time PHASE 9 da. Hozircha polling — afitsant qo'lda
    // yangilashni kutib turmasin.
    const timer = setInterval(() => void load(), 10000);

    return () => clearInterval(timer);
  }, [load]);

  const act = async (id: number, action: 'accept' | 'delivering' | 'deliver') => {
    setBusyId(id);

    try {
      await api.post(`/waiter/orders/${id}/${action}`);
      await load();
      onChanged();
    } finally {
      setBusyId(null);
    }
  };

  if (loading) return <p className="p-8 text-center text-slate-400">{t('common.loading')}</p>;

  if (orders.length === 0) {
    return (
      <div className="p-8 text-center">
        <p className="text-slate-400">{t('waiter.no_orders')}</p>
        <button
          type="button"
          onClick={() => void load()}
          className="mt-4 h-12 rounded-xl px-6 text-sm font-medium text-slate-600 ring-1 ring-slate-200"
        >
          {t('waiter.refresh')}
        </button>
      </div>
    );
  }

  return (
    <ul className="space-y-3 p-4">
      {orders.map((order) => (
        <li key={order.id} className="rounded-2xl bg-white p-4 shadow-sm">
          <div className="flex items-baseline justify-between">
            <span className="text-lg font-bold text-slate-900">
              {t('common.table')} {order.table?.number ?? '—'}
            </span>
            <span className="text-xs text-slate-400">
              {order.order_number} · {formatTime(order.created_at)}
            </span>
          </div>

          <ul className="mt-3 space-y-1">
            {order.items.map((item) => (
              <li key={item.product_id} className="flex justify-between gap-3 text-[15px]">
                <span className="text-slate-700">{item.name}</span>
                <span className="shrink-0 font-semibold text-slate-900">× {item.quantity}</span>
              </li>
            ))}
          </ul>

          {order.note !== null && (
            <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">
              {order.note}
            </p>
          )}

          <p className="mt-3 border-t border-slate-100 pt-3 text-right font-semibold text-slate-900">
            {formatMoney(order.total, locale)}
          </p>

          <div className="mt-3">
            {order.status === 'ASSIGNED' && (
              <button
                type="button"
                onClick={() => void act(order.id, 'accept')}
                disabled={busyId === order.id}
                className="h-16 w-full rounded-2xl bg-slate-900 text-lg font-bold text-white disabled:bg-slate-300"
              >
                {t('waiter.accept')}
              </button>
            )}

            {order.status === 'WAITER_ACCEPTED' && (
              <button
                type="button"
                onClick={() => void act(order.id, 'delivering')}
                disabled={busyId === order.id}
                className="h-16 w-full rounded-2xl bg-amber-500 text-lg font-bold text-white disabled:bg-slate-300"
              >
                {t('waiter.start_delivering')}
              </button>
            )}

            {order.status === 'DELIVERING' && (
              <>
                <HoldButton
                  label={t('waiter.delivered_btn')}
                  holdingLabel={t('waiter.holding')}
                  onComplete={() => void act(order.id, 'deliver')}
                  disabled={busyId === order.id}
                />
                <p className="mt-2 text-center text-xs text-slate-400">
                  {t('waiter.swipe_hint')}
                </p>
              </>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}
