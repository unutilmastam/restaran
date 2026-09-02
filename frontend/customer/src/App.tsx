import { SUPPORTED_LOCALES, setLocale, type Locale } from '@sr/shared';
import { useTranslation } from 'react-i18next';

/**
 * PHASE 1 — skeleton. Haqiqiy ekranlar (guest count → menyu → cart)
 * PHASE 3 da quriladi.
 *
 * Bu yerda ham matn i18n orqali chaqiriladi: CLAUDE.md §2.9 —
 * hardcode qilingan string yozilmaydi, hatto vaqtinchalik ekranda ham.
 */
export default function App() {
  const { t, i18n } = useTranslation();

  return (
    <main className="min-h-dvh bg-slate-50 p-6">
      <header className="mb-8 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('customer.view_menu')}</h1>
        <nav aria-label={t('common.language')} className="flex gap-2">
          {SUPPORTED_LOCALES.map((locale: Locale) => (
            <button
              key={locale}
              type="button"
              onClick={() => setLocale(locale)}
              className={`rounded-lg px-3 py-1.5 text-sm font-medium uppercase transition ${
                i18n.language === locale
                  ? 'bg-slate-900 text-white'
                  : 'bg-white text-slate-600 ring-1 ring-slate-200'
              }`}
            >
              {locale}
            </button>
          ))}
        </nav>
      </header>

      <p className="text-slate-500">{t('common.loading')}</p>
    </main>
  );
}
