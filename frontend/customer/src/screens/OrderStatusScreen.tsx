import { formatMoney, useTranslation, type Order } from '@sr/shared';
import { useEffect, useState } from 'react';

import { Spinner } from '../components/Spinner';
import { api } from '../lib/api';

interface Props {
  orderId: number;
  onNewOrder: () => void;
}

/**
 * Buyurtma holati — docs/03-PHASES.md PHASE 5.
 *
 *   Yuborildi → Qabul qilindi → Biriktirildi → Yetkazilmoqda → Yetkazildi
 *
 * ⚠️ "Kitchen accepted" kabi bosqich YO'Q — oshpaz tizimdan
 * foydalanmaydi (CLAUDE.md §2.1).
 *
 * Polling 5 sekundda. Customer WebSocket'ga ULANMAYDI: Pusher free
 * plan 100 connection bilan cheklangan (docs/05-PHASE0-PLAN.md §0).
 * `is_final` bo'lgach polling TO'XTAYDI — batareya va traffik tejaladi.
 */
const POLL_MS = 5000;

const STEPS = [
  { status: 'PENDING', key: 'order.pending' },
  { status: 'ACCEPTED', key: 'order.accepted' },
  { status: 'ASSIGNED', key: 'order.assigned' },
  { status: 'DELIVERING', key: 'order.delivering' },
  { status: 'DELIVERED', key: 'order.delivered' },
] as const;

/** `WAITING_FOR_WAITER` va `WAITER_ACCEPTED` mijozga alohida ko'rsatilmaydi. */
function stepIndex(status: Order['status']): number {
  switch (status) {
    case 'PENDING':
      return 0;
    case 'ACCEPTED':
    case 'WAITING_FOR_WAITER':
      return 1;
    case 'ASSIGNED':
    case 'WAITER_ACCEPTED':
      return 2;
    case 'DELIVERING':
      return 3;
    case 'DELIVERED':
      return 4;
    default:
      return 0;
  }
}

export function OrderStatusScreen({ orderId, onNewOrder }: Props) {
  const { t, locale } = useTranslation();
  const [order, setOrder] = useState<Order | null>(null);

  useEffect(() => {
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout>;

    const poll = async () => {
      try {
        const { data } = await api.get<{ order: Order }>(`/orders/${orderId}`);
        if (cancelled) return;

        setOrder(data.order);

        // Yetkazilgach yoki bekor qilingach so'rov yuborilmaydi.
        if (!data.order.is_final) timer = setTimeout(poll, POLL_MS);
      } catch {
        // Tarmoq uzilgan bo'lishi mumkin — keyingi urinishda tiklanadi.
        if (!cancelled) timer = setTimeout(poll, POLL_MS);
      }
    };

    void poll();

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [orderId]);

  if (order === null) return <Spinner />;

  const current = stepIndex(order.status);
  const cancelled = order.status === 'CANCELLED' || order.status === 'EXPIRED';

  return (
    <div className="flex min-h-dvh flex-col bg-slate-50 px-6 py-10">
      <p className="text-center text-sm text-slate-400">
        {t('customer.order_number')} {order.order_number}
      </p>
      <h1 className="mt-2 text-center text-2xl font-semibold text-slate-900">
        {cancelled ? t('order.cancelled') : t('customer.order_sent')}
      </h1>

      {!cancelled && (
        <ol className="mt-10 space-y-1">
          {STEPS.map((step, index) => {
            const done = index <= current;

            return (
              <li key={step.status} className="flex items-center gap-3">
                <div className="flex flex-col items-center">
                  <span
                    className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold ${
                      done ? 'bg-slate-900 text-white' : 'bg-white text-slate-300 ring-1 ring-slate-200'
                    }`}
                  >
                    {done ? '✓' : index + 1}
                  </span>
                  {index < STEPS.length - 1 && (
                    <span className={`h-8 w-0.5 ${index < current ? 'bg-slate-900' : 'bg-slate-200'}`} />
                  )}
                </div>

                <span
                  className={`pb-8 ${done ? 'font-medium text-slate-900' : 'text-slate-400'}`}
                  aria-current={index === current}
                >
                  {t(step.key)}
                </span>
              </li>
            );
          })}
        </ol>
      )}

      <div className="mt-auto rounded-2xl bg-white p-4 ring-1 ring-slate-200">
        <ul className="space-y-2">
          {order.items.map((item) => (
            <li key={item.product_id} className="flex justify-between gap-3 text-sm">
              <span className="text-slate-600">
                {item.name} × {item.quantity}
              </span>
              <span className="font-medium text-slate-900">
                {formatMoney(item.subtotal, locale)}
              </span>
            </li>
          ))}
        </ul>

        <div className="mt-3 flex justify-between border-t border-slate-100 pt-3">
          <span className="text-slate-500">{t('common.total')}</span>
          <span className="text-lg font-semibold text-slate-900">
            {formatMoney(order.total, locale)}
          </span>
        </div>
      </div>

      {order.is_final && (
        <button
          type="button"
          onClick={onNewOrder}
          className="mt-4 h-14 rounded-2xl bg-slate-900 text-base font-semibold text-white transition active:bg-slate-800"
        >
          {t('customer.new_order')}
        </button>
      )}
    </div>
  );
}
