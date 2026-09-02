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
