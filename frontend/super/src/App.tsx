import { useTranslation } from '@sr/shared';

/** PHASE 1 — skeleton. To'liq panel PHASE 13.5 da (docs/06-SAAS.md §9). */
export default function App() {
  const { t } = useTranslation();

  return (
    <main className="min-h-dvh bg-slate-950 p-8 text-slate-100">
      <h1 className="text-2xl font-semibold">{t('super.title')}</h1>
      <p className="mt-2 text-slate-400">{t('common.loading')}</p>
    </main>
  );
}
