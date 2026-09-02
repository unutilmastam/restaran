import { useTranslation } from 'react-i18next';

/** PHASE 1 — skeleton. Afitsant ekranlari PHASE 7 da quriladi. */
export default function App() {
  const { t } = useTranslation();

  return (
    <main className="min-h-dvh bg-slate-900 p-6 text-slate-100">
      <h1 className="text-xl font-semibold">{t('waiter.my_orders')}</h1>
      <p className="mt-2 text-slate-400">{t('common.loading')}</p>
    </main>
  );
}
