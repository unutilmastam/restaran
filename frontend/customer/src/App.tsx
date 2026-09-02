import { isApiError, useTranslation, type Menu, type TableEntry } from '@sr/shared';
import { lazy, Suspense, useEffect, useState } from 'react';

import { LanguageSwitcher } from './components/LanguageSwitcher';
import { ProductImage } from './components/ProductImage';
import { Spinner } from './components/Spinner';
import { api } from './lib/api';
import { parseRoute, tablePath, type TableRoute } from './lib/route';
import { GuestCountScreen } from './screens/GuestCountScreen';
import { MenuScreen } from './screens/MenuScreen';
import { UnavailableScreen } from './screens/UnavailableScreen';
import { useCart } from './store/cart';

// Cart ekrani alohida chunk: mijoz menyuni ko'rmasdan turib cartga
// kirmaydi, shuning uchun birinchi yuklashda kerak emas.
const CartScreen = lazy(() =>
  import('./screens/CartScreen').then((module) => ({ default: module.CartScreen })),
);

type Step = 'guests' | 'menu' | 'cart';

export default function App() {
  const { t } = useTranslation();

  const [route] = useState<TableRoute | null>(() => parseRoute());
  const [entry, setEntry] = useState<TableEntry | null>(null);
  const [menu, setMenu] = useState<Menu | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [step, setStep] = useState<Step>('guests');

  const loadCart = useCart((state) => state.load);

  useEffect(() => {
    if (route === null) {
      setError('INVALID_TABLE');

      return;
    }

    // Cart stol bo'yicha alohida kalitda — boshqa stolga o'tsa aralashmaydi.
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

        const menuResponse = await api.get<Menu>(tablePath(route, '/menu'));
        if (!cancelled) setMenu(menuResponse.data);
      } catch (caught) {
        if (!cancelled) {
          setError(isApiError(caught) ? caught.code : 'NETWORK_ERROR');
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [route, loadCart]);

  if (error !== null) return <UnavailableScreen code={error} />;
  if (entry === null || menu === null) return <Spinner />;

  if (step === 'cart') {
    return (
      <Suspense fallback={<Spinner />}>
        <CartScreen products={menu.products} onBack={() => setStep('menu')} />
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
          onConfirm={() => setStep('menu')}
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
