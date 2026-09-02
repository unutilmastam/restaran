import { formatMoney, useTranslation, type Category, type Product } from '@sr/shared';
import { useMemo, useState } from 'react';

import { ProductImage } from '../components/ProductImage';
import { QuantityStepper } from '../components/QuantityStepper';
import { useCart } from '../store/cart';

interface Props {
  categories: Category[];
  products: Product[];
  onOpenCart: () => void;
}

export function MenuScreen({ categories, products, onOpenCart }: Props) {
  const { t, locale } = useTranslation();
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const [search, setSearch] = useState('');

  const lines = useCart((state) => state.lines);
  const add = useCart((state) => state.add);
  const remove = useCart((state) => state.remove);

  const visible = useMemo(() => {
    const query = search.trim().toLowerCase();

    return products.filter((product) => {
      if (activeCategory !== null && product.category_id !== activeCategory) return false;
      if (query === '') return true;

      return product.name.toLowerCase().includes(query);
    });
  }, [products, activeCategory, search]);

  // Cart summasi HAR DOIM yangi menyu narxidan hisoblanadi — cartda narx
  // saqlanmaydi (CLAUDE.md §2.7). Bu faqat ko'rsatish uchun; haqiqiy total
  // backendda hisoblanadi.
  const total = useMemo(
    () =>
      lines.reduce((sum, line) => {
        const product = products.find((item) => item.id === line.productId);

        return sum + (product ? product.effective_price * line.quantity : 0);
      }, 0),
    [lines, products],
  );

  const itemCount = lines.reduce((sum, line) => sum + line.quantity, 0);

  return (
    <>
      <div className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 backdrop-blur">
        <div className="px-4 pb-3 pt-2">
          <input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('common.search')}
            className="h-11 w-full rounded-xl bg-white px-4 text-sm text-slate-900 ring-1 ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-900"
          />
        </div>

        <div className="flex gap-2 overflow-x-auto px-4 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <CategoryTab
            label={t('admin.menu')}
            active={activeCategory === null}
            onClick={() => setActiveCategory(null)}
          />
          {categories.map((category) => (
            <CategoryTab
              key={category.id}
              label={category.name}
              active={activeCategory === category.id}
              onClick={() => setActiveCategory(category.id)}
            />
          ))}
        </div>
      </div>

      <ul className="flex-1 divide-y divide-slate-100 pb-28">
        {visible.length === 0 && (
          <li className="px-4 py-16 text-center text-sm text-slate-400">{t('common.empty')}</li>
        )}

        {visible.map((product) => (
          <li key={product.id} className="flex gap-3 px-4 py-3">
            <ProductImage
              src={product.image}
              alt={product.name}
              className="h-20 w-20 shrink-0 rounded-xl"
            />

            <div className="flex min-w-0 flex-1 flex-col justify-between">
              <div>
                <p className="font-medium leading-tight text-slate-900">{product.name}</p>
                {product.description && (
                  <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">
                    {product.description}
                  </p>
                )}
                {product.weight !== null && (
                  <p className="mt-0.5 text-xs text-slate-400">{product.weight} g</p>
                )}
              </div>

              <div className="mt-2 flex items-center justify-between gap-2">
                <span className="font-semibold text-slate-900">
                  {formatMoney(product.effective_price, locale)}
                </span>

                <QuantityStepper
                  quantity={lines.find((line) => line.productId === product.id)?.quantity ?? 0}
                  onIncrement={() => add(product.id)}
                  onDecrement={() => remove(product.id)}
                  // is_available = false mahsulot cartga qo'shilmaydi.
                  disabled={!product.is_available}
                />
              </div>
            </div>
          </li>
        ))}
      </ul>

      {itemCount > 0 && (
        <div className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <button
            type="button"
            onClick={onOpenCart}
            className="flex h-14 w-full items-center justify-between rounded-2xl bg-slate-900 px-5 text-white transition active:bg-slate-800"
          >
            <span className="text-sm font-semibold">
              {t('customer.items_count', { count: itemCount })}
            </span>
            <span className="font-semibold">{formatMoney(total, locale)}</span>
          </button>
        </div>
      )}
    </>
  );
}

function CategoryTab({
  label,
  active,
  onClick,
}: {
  label: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`h-9 shrink-0 whitespace-nowrap rounded-full px-4 text-sm font-medium transition ${
        active ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'
      }`}
    >
      {label}
    </button>
  );
}
