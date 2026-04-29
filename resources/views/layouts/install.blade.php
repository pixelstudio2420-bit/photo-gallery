<!DOCTYPE html>
<html lang="th" x-data="{ darkMode: localStorage.getItem('pg-theme') === 'dark' }" :class="{ 'dark': darkMode }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Installation') — {{ $siteName ?? config('app.name', 'Photo Gallery') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
  <style>
    body {
      font-family: 'Sarabun', 'Segoe UI', Arial, sans-serif;
      background: #f1f5f9;
      min-height: 100vh;
    }
    .dark body { background: rgb(2 6 23); }

    /* Page wrapper — no sidebar, no full admin layout */
    .install-shell {
      min-height: 100vh;
      padding: 1.5rem 1rem;
    }
    @media (min-width: 768px) {
      .install-shell { padding: 2rem 1.5rem; }
    }

    /* Top brand bar (minimal) */
    .install-topbar {
      max-width: 80rem;
      margin: 0 auto 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem 0.5rem;
    }
    .install-brand {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      color: rgb(15 23 42);
      font-weight: 700;
      text-decoration: none;
    }
    .dark .install-brand { color: rgb(241 245 249); }
    .install-brand-icon {
      width: 36px; height: 36px; border-radius: 10px;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: white; display: inline-flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }
  </style>
</head>
<body class="text-slate-900 dark:text-slate-100">

<div class="install-shell">
  <div class="install-topbar">
    <div class="install-brand">
      <span class="install-brand-icon"><i class="bi bi-tools"></i></span>
      <div>
        <div class="text-sm font-bold leading-tight">{{ $siteName ?? config('app.name', 'Photo Gallery') }}</div>
        <div class="text-[10px] uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Installation Wizard</div>
      </div>
    </div>
    <button type="button"
            @click="darkMode = !darkMode; localStorage.setItem('pg-theme', darkMode ? 'dark' : 'light')"
            class="w-9 h-9 rounded-lg flex items-center justify-center text-sm
                   bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
                   text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
            title="Toggle dark mode">
      <i class="bi" :class="darkMode ? 'bi-sun-fill' : 'bi-moon-fill'"></i>
    </button>
  </div>

  @yield('content')
</div>

@stack('scripts')
</body>
</html>
