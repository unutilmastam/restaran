import { useTranslation } from 'react-i18next';

/** PHASE 1 — skeleton. Dashboard va sidebar PHASE 6 da quriladi. */
export default function App() {
  const { t } = useTranslation();

  return (
    <main className="min-h-dvh bg-slate-100 p-8">
      <h1 className="text-2xl font-semibold text-slate-900">{t('admin.dashboard')}</h1>
      <p className="mt-2 text-slate-500">{t('common.loading')}</p>
    </main>
  );
}
