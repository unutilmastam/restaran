import {
  hasErrorCode,
  isApiError,
  useTranslation,
  type Menu,
  type Order,
  type TableEntry,
} from '@sr/shared';
import { lazy, Suspense, useCallback, useEffect, useState } from 'react';

import { LanguageSwitcher } from './components/LanguageSwitcher';
import { ProductImage } from './components/ProductImage';
import { Spinner } from './components/Spinner';
import { api } from './lib/api';
import { readCustomerToken, writeCustomerToken } from './lib/customerToken';
import { parseRoute, tablePath, type TableRoute } from './lib/route';
import { GuestCountScreen } from './screens/GuestCountScreen';
import { MenuScreen } from './screens/MenuScreen';
import { UnavailableScreen } from './screens/UnavailableScreen';
import { useCart } from './store/cart';

// Menyudan keyingi ekranlar alohida chunk: birinchi yuklashda kerak emas.
const CartScreen = lazy(() =>
  import('./screens/CartScreen').then((m) => ({ default: m.CartScreen })),
);
const OrderStatusScreen = lazy(() =>
  import('./screens/OrderStatusScreen').then((m) => ({ default: m.OrderStatusScreen })),
);

type Step = 'guests' | 'menu' | 'cart' | 'status';

export default function App() {
  const { t } = useTranslation();

  const [route] = useState<TableRoute | null>(() => parseRoute());
  const [entry, setEntry] = useState<TableEntry | null>(null);
  const [menu, setMenu] = useState<Menu | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [step, setStep] = useState<Step>('guests');
  const [orderId, setOrderId] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [blockedReason, setBlockedReason] = useState<string | null>(null);

  const loadCart = useCart((state) => state.load);
  const clearCart = useCart((state) => state.clear);
  const lines = useCart((state) => state.lines);

  useEffect(() => {
    if (route === null) {
      setError('INVALID_TABLE');

      return;
    }

    // Cart va token stol bo'yicha alohida kalitda.
    loadCart(route.nfcToken);

    let cancelled = false;

    void (async () => {
      try {
        const { data } = await api.get<TableEntry>(tablePath(route));
        if (cancelled) return;

        setEntry(data);

        if (!data.is_available) {
          setError(data.blocked_reason ?? 'RESTAURANT_UNAVAILABLE');

          return;
        }

        // Mavjud ACTIVE session bo'lsa "Necha kishi?" so'ralmaydi.
        if (data.session !== null) setStep('menu');

        const menuResponse = await api.get<Menu>(tablePath(route, '/menu'));
        if (!cancelled) setMenu(menuResponse.data);
      } catch (caught) {
        if (!cancelled) setError(isApiError(caught) ? caught.code : 'NETWORK_ERROR');
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [route, loadCart]);

  /** Session ochish yoki mavjudiga ulanish (PHASE 4). */
  const ensureSession = useCallback(
    async (guestCount: number) => {
      if (route === null) return;

      if (readCustomerToken(route.nfcToken) !== null) return;

      const { data } = await api.post<{ customer_token: string }>(
        tablePath(route, '/sessions'),
        { guest_count: guestCount },
      );

      writeCustomerToken(route.nfcToken, data.customer_token);
    },
    [route],
  );

  const submitOrder = useCallback(
    async (note: string | null) => {
      if (route === null || lines.length === 0) return;

      setSubmitting(true);
      setBlockedReason(null);

      try {
        await ensureSession(entry?.session?.guest_count ?? 2);

        const { data } = await api.post<{ order: Order }>(tablePath(route, '/orders'), {
          // Idempotency: bir marta yaratiladi va qayta urinishda ham
          // O'SHA uuid ketadi (CLAUDE.md §3.1).
          client_order_uuid: crypto.randomUUID(),
          // ⚠️ NARX YUBORILMAYDI — server DB'dan hisoblaydi.
          items: lines.map((line) => ({ product_id: line.productId, quantity: line.quantity })),
          note,
        });

        clearCart();
        setOrderId(data.order.id);
        setStep('status');
      } catch (caught) {
        if (hasErrorCode(caught, 'SESSION_WAITING_PAYMENT')) {
          // Order draft sifatida saqlandi — mijoz kutadi (docs/01 §12).
          setBlockedReason('SESSION_WAITING_PAYMENT');
        } else if (isApiError(caught)) {
          setBlockedReason(caught.code);
        } else {
          setBlockedReason('NETWORK_ERROR');
        }
      } finally {
        setSubmitting(false);
      }
    },
    [route, lines, entry, ensureSession, clearCart],
  );

  if (error !== null) return <UnavailableScreen code={error} />;
  if (entry === null || menu === null) return <Spinner />;

  if (step === 'status' && orderId !== null) {
    return (
      <Suspense fallback={<Spinner />}>
        <OrderStatusScreen
          orderId={orderId}
          onNewOrder={() => {
            setOrderId(null);
            setStep('menu');
          }}
        />
      </Suspense>
    );
  }

  if (step === 'cart') {
    return (
      <Suspense fallback={<Spinner />}>
        <CartScreen
          products={menu.products}
          onBack={() => setStep('menu')}
          onSubmit={submitOrder}
          submitting={submitting}
          blockedReason={blockedReason}
        />
      </Suspense>
    );
  }

  return (
    <div className="flex min-h-dvh flex-col bg-slate-50">
      <header className="flex items-center gap-3 px-4 py-3">
        <ProductImage
          src={entry.restaurant.logo}
          alt={entry.restaurant.name}
          className="h-10 w-10 shrink-0 rounded-xl"
        />

        <div className="min-w-0 flex-1">
          <p className="truncate font-semibold leading-tight text-slate-900">
            {entry.restaurant.name}
          </p>
          <p className="text-xs text-slate-500">
            {t('common.table')} {entry.table.number}
          </p>
        </div>

        <LanguageSwitcher />
      </header>

      {step === 'guests' ? (
        <GuestCountScreen
          tableNumber={entry.table.number}
          capacity={entry.table.capacity}
          onConfirm={(guestCount) => {
            void ensureSession(guestCount);
            setStep('menu');
          }}
        />
      ) : (
        <MenuScreen
          categories={menu.categories}
          products={menu.products}
          onOpenCart={() => setStep('cart')}
        />
      )}
    </div>
  );
}
