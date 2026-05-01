{{--
  Tailwind class safelist for the pSEO landing page templates.
  ─────────────────────────────────────────────────────────────
  show.blade.php constructs gradient + accent classes from $theme
  data at runtime ("from-rose-400", "text-rose-500", etc). Tailwind's
  v4 scanner walks Blade files looking for literal class strings —
  it doesn't follow PHP variable interpolation, so the dynamic
  classes never make it into the compiled CSS without help.

  This file is never rendered. Its only job is to exist as a Blade
  template containing the literal class names so Tailwind picks them
  up when it scans `resources/**/*.blade.php`.

  Add new theme classes here whenever a new theme preset is added
  to SeoLandingPage::THEMES.
--}}

@php return; @endphp

{{-- ── Gradients (from-X via-X to-X) ─────────────────── --}}
<div class="from-indigo-500 via-violet-500 to-purple-600"></div>
<div class="from-rose-400 via-pink-500 to-fuchsia-600"></div>
<div class="from-cyan-500 via-blue-500 to-indigo-600"></div>
<div class="from-violet-600 via-purple-600 to-fuchsia-700"></div>
<div class="from-slate-600 via-slate-700 to-zinc-800"></div>
<div class="from-amber-500 via-orange-500 to-red-500"></div>
<div class="from-yellow-400 via-orange-500 to-pink-500"></div>
<div class="from-emerald-500 via-teal-600 to-cyan-700"></div>

{{-- ── Accent colours (text-X-500/600, bg-X-50/100, etc.) ─────── --}}
<div class="text-indigo-500 text-indigo-600 text-rose-500 text-rose-600 text-blue-500 text-blue-600 text-violet-500 text-violet-600 text-slate-500 text-slate-600 text-amber-500 text-amber-600 text-orange-500 text-orange-600 text-emerald-500 text-emerald-600"></div>
<div class="bg-indigo-50 bg-indigo-100 dark:bg-indigo-500/10 dark:bg-indigo-500/15 dark:bg-indigo-500/20"></div>
<div class="bg-rose-50 bg-rose-100 dark:bg-rose-500/10 dark:bg-rose-500/15 dark:bg-rose-500/20"></div>
<div class="bg-blue-50 bg-blue-100 dark:bg-blue-500/10 dark:bg-blue-500/15 dark:bg-blue-500/20"></div>
<div class="bg-violet-50 bg-violet-100 dark:bg-violet-500/10 dark:bg-violet-500/15 dark:bg-violet-500/20"></div>
<div class="bg-slate-50 bg-slate-100 dark:bg-slate-500/10 dark:bg-slate-500/15 dark:bg-slate-500/20"></div>
<div class="bg-amber-50 bg-amber-100 dark:bg-amber-500/10 dark:bg-amber-500/15 dark:bg-amber-500/20"></div>
<div class="bg-orange-50 bg-orange-100 dark:bg-orange-500/10 dark:bg-orange-500/15 dark:bg-orange-500/20"></div>
<div class="bg-emerald-50 bg-emerald-100 dark:bg-emerald-500/10 dark:bg-emerald-500/15 dark:bg-emerald-500/20"></div>
<div class="text-indigo-400 text-rose-400 text-blue-400 text-violet-400 text-slate-400 text-amber-400 text-orange-400 text-emerald-400"></div>
<div class="border-indigo-300 border-rose-300 border-blue-300 border-violet-300 border-slate-300 border-amber-300 border-orange-300 border-emerald-300"></div>
<div class="dark:border-indigo-500/30 dark:border-rose-500/30 dark:border-blue-500/30 dark:border-violet-500/30 dark:border-slate-500/30 dark:border-amber-500/30 dark:border-orange-500/30 dark:border-emerald-500/30"></div>
<div class="hover:text-indigo-600 hover:text-rose-600 hover:text-blue-600 hover:text-violet-600 hover:text-slate-600 hover:text-amber-600 hover:text-orange-600 hover:text-emerald-600"></div>
<div class="hover:bg-indigo-50 hover:bg-rose-50 hover:bg-blue-50 hover:bg-violet-50 hover:bg-slate-50 hover:bg-amber-50 hover:bg-orange-50 hover:bg-emerald-50"></div>
<div class="dark:hover:bg-indigo-500/10 dark:hover:bg-rose-500/10 dark:hover:bg-blue-500/10 dark:hover:bg-violet-500/10 dark:hover:bg-slate-500/10 dark:hover:bg-amber-500/10 dark:hover:bg-orange-500/10 dark:hover:bg-emerald-500/10"></div>
<div class="hover:border-indigo-300 hover:border-rose-300 hover:border-blue-300 hover:border-violet-300 hover:border-slate-300 hover:border-amber-300 hover:border-orange-300 hover:border-emerald-300"></div>
<div class="dark:hover:border-indigo-500/30 dark:hover:border-rose-500/30 dark:hover:border-blue-500/30 dark:hover:border-violet-500/30 dark:hover:border-slate-500/30 dark:hover:border-amber-500/30 dark:hover:border-orange-500/30 dark:hover:border-emerald-500/30"></div>
<div class="group-hover:text-indigo-500 group-hover:text-rose-500 group-hover:text-blue-500 group-hover:text-violet-500 group-hover:text-slate-500 group-hover:text-amber-500 group-hover:text-orange-500 group-hover:text-emerald-500"></div>
<div class="group-hover:text-indigo-600 group-hover:text-rose-600 group-hover:text-blue-600 group-hover:text-violet-600 group-hover:text-slate-600 group-hover:text-amber-600 group-hover:text-orange-600 group-hover:text-emerald-600"></div>
