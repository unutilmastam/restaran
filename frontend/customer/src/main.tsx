import { initI18n } from '@sr/shared';
import React from 'react';
import ReactDOM from 'react-dom/client';

import App from './App';
import './index.css';

// i18n render'dan OLDIN ishga tushadi — birinchi kadr ham to'g'ri tilda.
initI18n();

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);

// Service worker render'dan KEYIN ro'yxatdan o'tadi — birinchi kadrni
// kechiktirmasin (mobil internet, docs/03-PHASES.md PHASE 3).
//
// ⚠️ Yo'l BASE_URL orqali: sahifa `/t/{nfc_token}` da ochiladi, shuning
// uchun nisbiy 'sw.js' `/t/sw.js` ga ketib, SPA fallback HTML qaytarardi
// ("unsupported MIME type"). BASE_URL subdirectory deploy'da ham to'g'ri.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  const base = import.meta.env.BASE_URL;

  window.addEventListener('load', () => {
    void navigator.serviceWorker.register(`${base}sw.js`, { scope: base }).catch(() => undefined);
  });
}
