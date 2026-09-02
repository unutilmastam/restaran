<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web
|--------------------------------------------------------------------------
| Backend faqat API beradi — Blade UI yo'q, asset build ham yo'q.
| Uch frontend alohida statik SPA sifatida deploy qilinadi
| (docs/05-PHASE0-PLAN.md §1). Shu sababli backendda `package.json`
| saqlanmaydi: cPanel serverida `node_modules` bo'lmaydi.
*/

Route::get('/', fn () => redirect('/api/v1/health'));
