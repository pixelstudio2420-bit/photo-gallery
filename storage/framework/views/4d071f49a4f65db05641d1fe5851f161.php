
<style>
/* ───────── Toggle switch ───────── */
.tw-switch {
  --track-bg: rgb(203 213 225);          /* slate-300 */
  --track-bg-on: rgb(79 70 229);         /* indigo-600 */
  --knob-bg: #fff;
  position: relative;
  display: inline-block;
  width: 2.75rem;
  height: 1.5rem;
  flex-shrink: 0;
  cursor: pointer;
}
.dark .tw-switch { --track-bg: rgb(51 65 85); --track-bg-on: rgb(99 102 241); } /* slate-700 / indigo-500 */
.tw-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.tw-switch .tw-switch-track {
  position: absolute; inset: 0;
  background: var(--track-bg);
  border-radius: 9999px;
  transition: background-color .2s ease;
}
.tw-switch .tw-switch-knob {
  position: absolute;
  top: 50%;
  left: 0.18rem;
  width: 1.125rem;
  height: 1.125rem;
  background: var(--knob-bg);
  border-radius: 9999px;
  transform: translateY(-50%);
  transition: transform .2s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,.25);
}
.tw-switch input:checked ~ .tw-switch-track { background: var(--track-bg-on); }
.tw-switch input:checked ~ .tw-switch-knob  { transform: translate(1.25rem, -50%); }
.tw-switch input:focus-visible ~ .tw-switch-track {
  box-shadow: 0 0 0 3px rgba(99,102,241,.35);
}

/* ───────── Status dot ───────── */
.status-dot {
  display: inline-flex; align-items: center; gap: .4rem;
  font-size: .72rem; font-weight: 600;
  padding: .25rem .65rem;
  border-radius: 9999px;
  border: 1px solid transparent;
  white-space: nowrap;
}
.status-dot::before {
  content: ''; width: 6px; height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}
.status-dot.connected    { background: rgb(220 252 231); color: rgb(21 128 61);  border-color: rgb(187 247 208); }
.status-dot.disconnected { background: rgb(254 226 226); color: rgb(185 28 28);  border-color: rgb(254 202 202); }
.status-dot.unknown      { background: rgb(241 245 249); color: rgb(71 85 105);  border-color: rgb(226 232 240); }
.status-dot.warning      { background: rgb(254 243 199); color: rgb(146 64 14);  border-color: rgb(253 230 138); }
.dark .status-dot.connected    { background: rgba(16,185,129,.12);  color: rgb(110 231 183); border-color: rgba(16,185,129,.30); }
.dark .status-dot.disconnected { background: rgba(239,68,68,.12);   color: rgb(252 165 165); border-color: rgba(239,68,68,.30); }
.dark .status-dot.unknown      { background: rgba(148,163,184,.12); color: rgb(203 213 225); border-color: rgba(148,163,184,.30); }
.dark .status-dot.warning      { background: rgba(245,158,11,.12);  color: rgb(253 224 71);  border-color: rgba(245,158,11,.30); }

/* ───────── Inline spinner ───────── */
.tw-spinner {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: tw-spin .7s linear infinite;
  vertical-align: -2px;
}
@keyframes tw-spin { to { transform: rotate(360deg); } }

/* Alpine cloak helper */
[x-cloak] { display: none !important; }
</style>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/settings/_shared-styles.blade.php ENDPATH**/ ?>