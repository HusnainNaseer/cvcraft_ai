<style>
/* ==========================================================================
   Design tokens. Light is the default; html.dark switches the palette.
   ========================================================================== */
:root {
  --bg: #F5F6FA;
  --surface: #FFFFFF;
  --surface-2: #F3F4F8;
  --surface-3: #E9EBF2;
  --canvas: #E8EBF2;
  --canvas-dot: rgba(15, 23, 42, .07);
  --border: #E1E4EC;
  --border-strong: #CBD0DC;

  --text: #0F172A;
  --text-2: #475569;
  --text-3: #64748B;

  --brand: #4F46E5;
  --brand-hover: #4338CA;
  --brand-text: #4338CA;
  --brand-soft: #EEF0FF;
  --brand-ring: rgba(79, 70, 229, .28);
  --on-brand: #FFFFFF;

  --success: #047857;
  --success-soft: #E8F7F0;
  --warning: #A15C07;
  --warning-soft: #FEF6E4;
  --danger: #B42318;
  --danger-soft: #FDEEEC;

  --shadow-sm: 0 1px 2px rgba(15, 23, 42, .06);
  --shadow: 0 1px 3px rgba(15, 23, 42, .06), 0 6px 16px rgba(15, 23, 42, .06);
  --shadow-lg: 0 12px 40px rgba(15, 23, 42, .16);
  --shadow-paper: 0 2px 6px rgba(15, 23, 42, .08), 0 18px 48px rgba(15, 23, 42, .14);

  --radius-sm: 6px;
  --radius: 10px;
  --radius-lg: 14px;
  --header-h: 60px;
  --font: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --mono: 'JetBrains Mono', ui-monospace, monospace;
  --ease: cubic-bezier(.2, .7, .2, 1);
}

html.dark {
  --bg: #0B1120;
  --surface: #121A2B;
  --surface-2: #172135;
  --surface-3: #1E2A42;
  --canvas: #0E1626;
  --canvas-dot: rgba(148, 163, 184, .08);
  --border: #24304A;
  --border-strong: #33415E;

  --text: #E6EAF2;
  --text-2: #AAB4C6;
  --text-3: #8794AA;

  --brand: #6366F1;
  --brand-hover: #7477F4;
  --brand-text: #A5B4FC;
  --brand-soft: rgba(99, 102, 241, .16);
  --brand-ring: rgba(129, 140, 248, .4);

  --success: #34D399;
  --success-soft: rgba(52, 211, 153, .12);
  --warning: #FBBF24;
  --warning-soft: rgba(251, 191, 36, .12);
  --danger: #F87171;
  --danger-soft: rgba(248, 113, 113, .12);

  --shadow-sm: 0 1px 2px rgba(0, 0, 0, .3);
  --shadow: 0 1px 3px rgba(0, 0, 0, .3), 0 8px 24px rgba(0, 0, 0, .25);
  --shadow-lg: 0 16px 48px rgba(0, 0, 0, .5);
  --shadow-paper: 0 2px 8px rgba(0, 0, 0, .35), 0 24px 60px rgba(0, 0, 0, .45);
}

/* ==========================================================================
   Base
   ========================================================================== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
body {
  font-family: var(--font);
  font-size: .9375rem;
  line-height: 1.55;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
a { color: var(--brand-text); text-decoration: none; }
a:hover { text-decoration: underline; }
button, input, textarea, select { font: inherit; color: inherit; }
button { cursor: pointer; background: none; border: 0; }
button:disabled { cursor: not-allowed; }
img, svg { display: block; max-width: 100%; }
:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; border-radius: 4px; }
::selection { background: var(--brand-soft); color: var(--text); }
[hidden] { display: none !important; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
.muted { color: var(--text-3); }
.kbd {
  font-family: var(--mono); font-size: .75rem; font-weight: 500;
  padding: 1px 6px; border-radius: 5px;
  background: var(--surface-2); border: 1px solid var(--border); border-bottom-width: 2px;
  color: var(--text-2); white-space: nowrap;
}

/* Scrollbars */
* { scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent; }
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 10px; border: 3px solid transparent; background-clip: content-box; }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
}

/* ==========================================================================
   Buttons
   ========================================================================== */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  height: 38px; padding: 0 14px;
  border-radius: var(--radius-sm);
  font-size: .875rem; font-weight: 600; line-height: 1;
  border: 1px solid transparent;
  white-space: nowrap;
  transition: background .15s, border-color .15s, color .15s, box-shadow .15s, transform .05s;
}
.btn:active:not(:disabled) { transform: translateY(1px); }
.btn i { font-size: .9em; }
.btn-primary { background: var(--brand); color: var(--on-brand); box-shadow: var(--shadow-sm); }
.btn-primary:hover:not(:disabled) { background: var(--brand-hover); }
.btn-secondary { background: var(--surface); color: var(--text); border-color: var(--border-strong); box-shadow: var(--shadow-sm); }
.btn-secondary:hover:not(:disabled) { background: var(--surface-2); }
.btn-ghost { color: var(--text-2); }
.btn-ghost:hover:not(:disabled) { background: var(--surface-2); color: var(--text); }
.btn:disabled { opacity: .45; box-shadow: none; }
.btn-lg { height: 48px; padding: 0 20px; font-size: .9375rem; border-radius: var(--radius); }
.btn-sm { height: 32px; padding: 0 10px; font-size: .8125rem; }
.btn-block { width: 100%; }
.icon-btn {
  width: 36px; height: 36px; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: var(--radius-sm); color: var(--text-2);
  transition: background .15s, color .15s;
}
.icon-btn:hover:not(:disabled) { background: var(--surface-2); color: var(--text); }
.icon-btn:disabled { opacity: .4; }
.link-btn { color: var(--brand-text); font-weight: 600; font-size: .8125rem; display: inline-flex; align-items: center; gap: 6px; }
.link-btn:hover { text-decoration: underline; }

.spin {
  width: 16px; height: 16px; flex-shrink: 0;
  border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ==========================================================================
   Form fields
   ========================================================================== */
.field-label { display: block; font-size: .8125rem; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.field-hint { font-size: .8125rem; color: var(--text-3); margin-top: 8px; line-height: 1.5; }
.input, .textarea, .mef-input {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 9px 12px;
  font-size: .9375rem;
  color: var(--text);
  transition: border-color .15s, box-shadow .15s;
}
.input::placeholder, .textarea::placeholder, .mef-input::placeholder { color: var(--text-3); opacity: .85; }
.input:focus, .textarea:focus, .mef-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
.textarea, textarea.mef-input { resize: vertical; line-height: 1.6; }

/* ==========================================================================
   Header
   ========================================================================== */
.app-header {
  height: var(--header-h);
  position: sticky; top: 0; z-index: 50;
  display: flex; align-items: center; gap: 12px;
  padding: 0 20px;
  background: color-mix(in srgb, var(--surface) 88%, transparent);
  backdrop-filter: saturate(1.4) blur(12px);
  border-bottom: 1px solid var(--border);
}
.brand { display: flex; align-items: center; gap: 10px; color: var(--text); font-weight: 700; font-size: 1.0625rem; letter-spacing: -.01em; }
.brand:hover { text-decoration: none; }
.brand-mark {
  width: 30px; height: 30px; border-radius: 8px;
  display: grid; place-items: center;
  background: linear-gradient(140deg, #6366F1, #4338CA);
  color: #fff; box-shadow: 0 2px 8px rgba(79, 70, 229, .35);
}
.brand-mark svg { width: 18px; height: 18px; }
.brand-ai { color: var(--brand-text); }
.header-spacer { flex: 1; }
.header-actions { display: flex; align-items: center; gap: 4px; }
.header-divider { width: 1px; height: 24px; background: var(--border); margin: 0 6px; }

.theme-toggle .fa-sun { display: none; }
html.dark .theme-toggle .fa-sun { display: inline; }
html.dark .theme-toggle .fa-moon { display: none; }

.gmail-btn { max-width: 280px; }
.gmail-icon { position: relative; display: inline-flex; color: var(--text-2); }
.gmail-btn .gmail-dot { position: absolute; right: -4px; top: -3px; width: 8px; height: 8px; border-radius: 50%; background: var(--text-3); box-shadow: 0 0 0 2px var(--surface); }
.gmail-btn.active { border-color: color-mix(in srgb, var(--success) 45%, var(--border)); }
.gmail-btn:not(.active) .gmail-dot { display: none; }
.gmail-btn.active .gmail-dot { background: var(--success); }
.gmail-btn.active .gmail-icon { color: var(--success); }
.gmail-btn span:last-child { overflow: hidden; text-overflow: ellipsis; }

/* ==========================================================================
   Workspace
   ========================================================================== */
.workspace {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(360px, 440px) 1fr;
  min-height: 0;
}
/* Desktop: the app fills the window exactly; each panel scrolls on its own. */
@media (min-width: 901px) {
  body.app { height: 100vh; overflow: hidden; }
}

/* Left: the steps */
.sidebar {
  display: flex; flex-direction: column;
  min-height: 0;
  background: var(--surface);
  border-right: 1px solid var(--border);
}
.sidebar-scroll { flex: 1; overflow-y: auto; padding: 24px 24px 8px; }
.sidebar-intro h1 { font-size: 1.25rem; font-weight: 700; letter-spacing: -.015em; line-height: 1.3; }
.sidebar-intro p { color: var(--text-2); font-size: .9375rem; margin-top: 4px; }
.trust-line {
  display: flex; align-items: center; gap: 8px;
  margin-top: 12px; padding: 8px 12px;
  background: var(--success-soft); color: var(--success);
  border-radius: var(--radius-sm);
  font-size: .8125rem; font-weight: 600;
}

.steps { list-style: none; display: flex; flex-direction: column; gap: 14px; margin-top: 20px; }
.step {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px;
  transition: border-color .2s, box-shadow .2s;
}
.step.active { border-color: color-mix(in srgb, var(--brand) 45%, var(--border)); box-shadow: 0 0 0 4px var(--brand-soft); }
.step-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.step-num {
  width: 26px; height: 26px; flex-shrink: 0;
  display: grid; place-items: center;
  border-radius: 50%;
  font-size: .8125rem; font-weight: 700;
  background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border-strong);
  transition: background .2s, color .2s, border-color .2s;
}
.step.active .step-num { background: var(--brand); color: var(--on-brand); border-color: var(--brand); }
.step.done .step-num { background: var(--success); color: #fff; border-color: var(--success); font-size: 0; }
.step.done .step-num::before { content: '\f00c'; font: 900 .75rem 'Font Awesome 6 Free'; }
html.dark .step.done .step-num { color: #062C1F; }
.step-title { font-size: 1rem; font-weight: 600; flex: 1; }
.step-tag { font-size: .75rem; font-weight: 600; color: var(--text-3); }

.badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .75rem; font-weight: 600; line-height: 1;
  padding: 4px 8px; border-radius: 999px;
}
.badge-req { background: var(--surface-2); color: var(--text-2); }
.badge-done { background: var(--success-soft); color: var(--success); }
.badge-done::before { content: '\f00c'; font: 900 .625rem 'Font Awesome 6 Free'; }

/* Upload */
.upload-zone {
  position: relative;
  border: 1.5px dashed var(--border-strong);
  border-radius: var(--radius);
  padding: 22px 16px;
  text-align: center;
  background: var(--surface-2);
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.upload-zone:hover, .upload-zone.over, .upload-zone:focus-within { border-color: var(--brand); background: var(--brand-soft); }
.upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.uz-icon { width: 40px; height: 40px; margin: 0 auto 10px; border-radius: 10px; display: grid; place-items: center; background: var(--surface); color: var(--brand-text); box-shadow: var(--shadow-sm); font-size: 1.05rem; }
.uz-title { font-weight: 600; font-size: .9375rem; }
.uz-sub { font-size: .8125rem; color: var(--text-3); margin-top: 2px; }
.uz-sub strong { color: var(--brand-text); }

.file-card { display: none; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface-2); }
.file-card.show { display: flex; }
.fc-icon { width: 40px; height: 40px; flex-shrink: 0; border-radius: 8px; display: grid; place-items: center; background: var(--danger-soft); color: var(--danger); font-size: 1.1rem; }
.fc-info { flex: 1; min-width: 0; }
.fc-name { font-weight: 600; font-size: .9375rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fc-meta { font-size: .8125rem; color: var(--text-3); }

/* Segmented control (job source tabs, tone) */
.segmented { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 4px; padding: 4px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); }
.segmented > button {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  height: 34px; border-radius: 7px;
  font-size: .875rem; font-weight: 600; color: var(--text-2);
  transition: background .15s, color .15s, box-shadow .15s;
}
.segmented > button:hover { color: var(--text); }
.segmented > button.active { background: var(--surface); color: var(--text); box-shadow: var(--shadow-sm), 0 0 0 1px var(--border); }
.tab-content { display: none; margin-top: 12px; }
.tab-content.active { display: block; }
.url-row { display: flex; gap: 8px; }
.url-row .input { flex: 1; min-width: 0; }
.textarea-wrap { position: relative; }
.jd-textarea { min-height: 150px; max-height: 340px; padding-bottom: 28px; }
.char-pill { position: absolute; right: 10px; bottom: 8px; font-size: .75rem; color: var(--text-3); font-variant-numeric: tabular-nums; pointer-events: none; }

/* Tone */
.tone-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.tone-opt {
  text-align: left; padding: 10px 12px;
  border: 1px solid var(--border); border-radius: var(--radius);
  background: var(--surface);
  transition: border-color .15s, background .15s, box-shadow .15s;
}
.tone-opt:hover { border-color: var(--border-strong); }
.tone-opt.selected { border-color: var(--brand); background: var(--brand-soft); box-shadow: 0 0 0 1px var(--brand) inset; }
.tone-name { display: block; font-weight: 600; font-size: .875rem; }
.tone-desc { display: block; font-size: .75rem; color: var(--text-3); margin-top: 2px; line-height: 1.4; }

/* Match score */
.match-card { display: none; margin-top: 14px; padding: 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface-2); }
.match-card.show { display: block; }
.match-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.match-label { font-size: .8125rem; font-weight: 600; color: var(--text-2); }
.match-val { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.match-bar { height: 6px; background: var(--surface-3); border-radius: 99px; margin: 8px 0 12px; overflow: hidden; }
.match-fill { height: 100%; width: 0; border-radius: 99px; background: var(--brand); transition: width .8s var(--ease); }
.match-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.kw-chip {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .75rem; font-weight: 600;
  padding: 3px 9px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border); color: var(--text-2);
}
.kw-chip.hit { background: var(--success-soft); border-color: transparent; color: var(--success); }
.kw-chip.miss { background: var(--surface); color: var(--text-3); border-style: dashed; }
.kw-chip i { font-size: .625rem; }
.match-legend { display: flex; gap: 14px; margin-top: 10px; font-size: .75rem; color: var(--text-3); }

/* Review card (after tailoring) */
.review-card { display: none; margin-top: 20px; border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; background: var(--surface); }
.review-card.show { display: block; }
.review-head { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
.review-icon { width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; }
.review-card.ok .review-icon { background: var(--success-soft); color: var(--success); }
.review-card.warn .review-icon { background: var(--warning-soft); color: var(--warning); }
.review-title { font-weight: 600; font-size: .9375rem; }
.review-sub { font-size: .8125rem; color: var(--text-2); }
.fit-note { display: flex; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--border); background: var(--warning-soft); }
.fit-note > i { color: var(--warning); margin-top: 3px; }
.fit-note strong { display: block; font-size: .875rem; }
.fit-note p { font-size: .8125rem; color: var(--text-2); margin-top: 2px; }
.fit-note.partial { background: var(--brand-soft); }
.fit-note.partial > i { color: var(--brand-text); }
.fit-missing { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.fit-missing .kw-chip { background: var(--surface); }
.fit-hint { margin-top: 8px !important; }
.review-scores { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface-2); }
.score-box { text-align: center; }
.score-box b { display: block; font-size: 1.25rem; font-variant-numeric: tabular-nums; }
.score-box span { font-size: .75rem; color: var(--text-3); }
.score-arrow { color: var(--text-3); }
.review-details { border-top: 1px solid var(--border); }
.review-details summary { list-style: none; cursor: pointer; padding: 12px 16px; font-size: .8125rem; font-weight: 600; color: var(--brand-text); display: flex; align-items: center; gap: 8px; }
.review-details summary::-webkit-details-marker { display: none; }
.review-details summary i { transition: transform .2s; font-size: .7rem; }
.review-details[open] summary i { transform: rotate(90deg); }
.review-list { list-style: none; padding: 0 16px 14px; display: flex; flex-direction: column; gap: 6px; max-height: 220px; overflow-y: auto; }
.review-list li { font-size: .8125rem; color: var(--text-2); padding-left: 16px; position: relative; line-height: 1.45; }
.review-list li::before { content: ''; position: absolute; left: 2px; top: 8px; width: 6px; height: 2px; background: var(--danger); border-radius: 2px; }

/* Sticky action bar */
.sidebar-footer { padding: 16px 24px 18px; border-top: 1px solid var(--border); background: var(--surface); }
.gen-btn { position: relative; }
.gen-btn .spin { display: none; }
.gen-btn.loading .spin { display: block; }
.gen-btn.loading .gen-icon { display: none; }
.gen-hint { margin-top: 10px; text-align: center; font-size: .8125rem; color: var(--text-3); }

/* ==========================================================================
   Preview
   ========================================================================== */
.stage { display: flex; flex-direction: column; min-width: 0; min-height: 0; background: var(--canvas); }
.toolbar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  min-height: 60px; padding: 10px 20px;
  background: var(--surface); border-bottom: 1px solid var(--border);
}
.toolbar-title { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.toolbar-spacer { flex: 1; }
.toolbar-group { display: flex; align-items: center; gap: 6px; }
.zoom-group { display: flex; align-items: center; gap: 2px; padding: 2px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); }
.zoom-group .icon-btn { width: 30px; height: 30px; }
.zoom-val { min-width: 46px; text-align: center; font-size: .8125rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--text-2); }

.status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: .75rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--surface-2); color: var(--text-2); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.sp-working { background: var(--brand-soft); color: var(--brand-text); }
.sp-working .sp-dot { animation: pulse 1.2s ease-in-out infinite; }
.sp-ready { background: var(--success-soft); color: var(--success); }
.sp-error { background: var(--danger-soft); color: var(--danger); }
@keyframes pulse { 50% { opacity: .3; } }

/* Design popover */
.popover-wrap { position: relative; }
.popover {
  position: absolute; right: 0; top: calc(100% + 8px); z-index: 30;
  width: 272px; padding: 14px;
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  display: none;
}
.popover.show { display: block; animation: pop .14s var(--ease); }
@keyframes pop { from { opacity: 0; transform: translateY(-4px); } }
.popover-label { font-size: .75rem; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 10px; }
.popover-section + .popover-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); }
.swatches { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
.theme-swatch {
  aspect-ratio: 1; border-radius: 50%;
  background: var(--swatch);
  box-shadow: 0 0 0 1px rgba(0, 0, 0, .12) inset;
  transition: transform .12s, box-shadow .12s;
}
.theme-swatch:hover { transform: scale(1.08); }
.theme-swatch.active { box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--swatch); }
.photo-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.switch { display: inline-flex; align-items: center; gap: 10px; font-size: .875rem; font-weight: 500; cursor: pointer; }
.switch input { appearance: none; width: 36px; height: 20px; border-radius: 99px; background: var(--border-strong); position: relative; cursor: pointer; transition: background .15s; flex-shrink: 0; }
.switch input::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: var(--shadow-sm); transition: transform .15s var(--ease); }
.switch input:checked { background: var(--brand); }
.switch input:checked::after { transform: translateX(16px); }

/* Stage body */
.preview-body {
  flex: 1; min-height: 0; overflow: auto;
  display: flex; justify-content: center; align-items: flex-start;
  padding: 32px 24px 48px;
  background-image: radial-gradient(var(--canvas-dot) 1px, transparent 1px);
  background-size: 18px 18px;
}

.empty-state { margin: auto; max-width: 440px; text-align: center; padding: 24px; }
.paper-art {
  width: 132px; height: 170px; margin: 0 auto 24px;
  background: var(--surface); border-radius: 8px; box-shadow: var(--shadow-paper);
  padding: 16px 14px; display: flex; flex-direction: column; gap: 7px;
  position: relative;
}
.paper-art span { display: block; height: 6px; border-radius: 3px; background: var(--surface-3); }
.paper-art .pa-head { height: 22px; border-radius: 4px; background: linear-gradient(90deg, var(--brand), color-mix(in srgb, var(--brand) 55%, #fff)); margin-bottom: 5px; }
.paper-art .pa-short { width: 60%; }
.paper-art .pa-mid { width: 82%; }
.paper-art .pa-badge {
  position: absolute; right: -14px; bottom: -12px;
  width: 40px; height: 40px; border-radius: 50%;
  display: grid; place-items: center;
  background: var(--success); color: #fff; font-size: 1rem;
  box-shadow: 0 0 0 4px var(--canvas);
}
.empty-state h2 { font-size: 1.25rem; font-weight: 700; letter-spacing: -.01em; }
.empty-state p { color: var(--text-2); margin-top: 6px; }
.es-steps { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.es-step { display: inline-flex; align-items: center; gap: 7px; font-size: .8125rem; font-weight: 600; color: var(--text-2); background: var(--surface); border: 1px solid var(--border); padding: 6px 12px; border-radius: 999px; }
.es-step b { width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; font-size: .6875rem; background: var(--surface-2); color: var(--text-2); }
.es-step.done { color: var(--success); border-color: color-mix(in srgb, var(--success) 35%, var(--border)); }
.es-step.done b { background: var(--success); color: #fff; }

/* Processing */
.proc-state { display: none; margin: auto; width: 100%; max-width: 440px; }
.proc-state.show { display: block; }
.proc-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 26px 26px 22px; }
.proc-card h2 { font-size: 1.125rem; font-weight: 700; }
.proc-top { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.proc-pct { font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--brand-text); }
.proc-bar { height: 8px; border-radius: 99px; background: var(--surface-3); margin: 12px 0 8px; overflow: hidden; }
.proc-fill { height: 100%; width: 0; border-radius: 99px; background: linear-gradient(90deg, var(--brand), color-mix(in srgb, var(--brand) 60%, #22D3EE)); transition: width .4s linear; position: relative; overflow: hidden; }
.proc-fill::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .35), transparent); animation: shimmer 1.4s infinite; }
@keyframes shimmer { from { transform: translateX(-100%); } to { transform: translateX(100%); } }
.proc-meta { display: flex; justify-content: space-between; gap: 12px; font-size: .8125rem; color: var(--text-3); }
.proc-timer { font-variant-numeric: tabular-nums; }
.stage-list { list-style: none; margin: 20px 0 22px; display: flex; flex-direction: column; gap: 4px; }
.pstage { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: var(--radius); transition: background .2s; }
.stage-icon { width: 26px; height: 26px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; border: 2px solid var(--border-strong); color: transparent; transition: background .2s, border-color .2s; }
.stage-text { flex: 1; min-width: 0; }
.stage-text strong { display: block; font-size: .9375rem; font-weight: 600; color: var(--text-3); transition: color .2s; }
.stage-text small { display: block; font-size: .8125rem; color: var(--text-3); }
.stage-time { font-size: .8125rem; color: var(--text-3); font-variant-numeric: tabular-nums; }
.pstage.active { background: var(--brand-soft); }
.pstage.active .stage-icon { border-color: var(--brand); border-right-color: transparent; animation: spin .8s linear infinite; }
.pstage.active .stage-text strong, .pstage.done .stage-text strong { color: var(--text); }
.pstage.done .stage-icon { background: var(--success); border-color: var(--success); color: #fff; font-size: .7rem; }
.pstage.done .stage-icon::before { content: '\f00c'; font: 900 .7rem 'Font Awesome 6 Free'; }
html.dark .pstage.done .stage-icon { color: #062C1F; }
.proc-card .btn { width: 100%; }

/* CV paper */
.cv-frame-outer { display: none; }
.cv-frame-outer.show { display: block; animation: rise .35s var(--ease); }
@keyframes rise { from { opacity: 0; transform: translateY(8px); } }
.cv-viewport { position: relative; overflow: hidden; border-radius: 4px; box-shadow: var(--shadow-paper); background: #fff; }
.cv-viewport::after {
  content: 'Loading preview…'; position: absolute; inset: 0;
  display: grid; place-items: center; padding-top: 48px;
  font-size: .875rem; font-weight: 500; color: #64748B; background: #fff;
  opacity: 0; pointer-events: none; transition: opacity .2s;
}
.cv-viewport::before {
  content: ''; position: absolute; left: 50%; top: 50%; z-index: 1;
  width: 28px; height: 28px; margin: -34px 0 0 -14px;
  border: 3px solid #E0E7FF; border-top-color: #4F46E5; border-radius: 50%;
  animation: spin .9s linear infinite; opacity: 0; transition: opacity .2s;
}
.cv-viewport.loading::after, .cv-viewport.loading::before { opacity: 1; }
.cv-scaler { transform-origin: top left; }
.cv-iframe { border: 0; display: block; background: #fff; }

/* ==========================================================================
   Footer
   ========================================================================== */
.app-footer {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 12px 20px; border-top: 1px solid var(--border); background: var(--surface);
  font-size: .8125rem; color: var(--text-3);
}
.app-footer nav { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.app-footer a, .app-footer button { color: var(--text-2); font-weight: 500; font-size: .8125rem; }
.app-footer a:hover, .app-footer button:hover { color: var(--text); text-decoration: none; }

/* ==========================================================================
   Modals and drawer
   ========================================================================== */
.modal-backdrop, .drawer-backdrop {
  position: fixed; inset: 0; z-index: 100;
  background: rgba(15, 23, 42, .5);
  backdrop-filter: blur(3px);
  display: none;
}
html.dark .modal-backdrop, html.dark .drawer-backdrop { background: rgba(2, 6, 23, .7); }
.modal-backdrop { padding: 24px; overflow-y: auto; align-items: flex-start; justify-content: center; }
.modal-backdrop.show { display: flex; animation: fade .15s ease; }
@keyframes fade { from { opacity: 0; } }
.modal {
  width: 100%; max-width: 620px; margin: auto;
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  animation: rise .2s var(--ease);
}
.modal-header { display: flex; align-items: flex-start; gap: 12px; padding: 20px 22px 0; }
.modal-header > div { flex: 1; min-width: 0; }
.modal-title { font-size: 1.125rem; font-weight: 700; letter-spacing: -.01em; }
.modal-sub { font-size: .875rem; color: var(--text-2); margin-top: 2px; }
.modal-body { padding: 18px 22px 22px; }

.apply-loading { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 36px 0; color: var(--text-2); }
.apply-cards { display: flex; flex-direction: column; gap: 12px; }
.apply-card { display: flex; gap: 14px; padding: 16px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); }
.apply-card.recommended { border-color: color-mix(in srgb, var(--brand) 50%, var(--border)); box-shadow: 0 0 0 3px var(--brand-soft); }
.apply-card.linkedin-card { cursor: pointer; align-items: center; transition: background .15s, border-color .15s; }
.apply-card.linkedin-card:hover { background: var(--surface-2); border-color: var(--border-strong); }
.ac-icon { width: 40px; height: 40px; flex-shrink: 0; border-radius: 10px; display: grid; place-items: center; font-size: 1.05rem; }
.ac-icon-email { background: var(--brand-soft); color: var(--brand-text); }
.ac-icon-li { background: rgba(10, 102, 194, .12); color: #0A66C2; }
html.dark .ac-icon-li { color: #5AA7F0; }
.ac-body { flex: 1; min-width: 0; }
.ac-title { font-weight: 600; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ac-badge { font-size: .6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 3px 7px; border-radius: 5px; background: var(--brand-soft); color: var(--brand-text); }
.ac-desc { font-size: .875rem; color: var(--text-2); margin-top: 2px; }
.ac-arrow { color: var(--text-3); }
.modal-email-form { margin-top: 14px; display: grid; gap: 4px; }
.mef-label { font-size: .8125rem; font-weight: 600; margin-top: 8px; }
#emailBody { min-height: 220px; }
.sender-line { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: .8125rem; color: var(--text-2); margin: 10px 0 12px; }
.sender-line strong { color: var(--text); }
.btn-send { width: 100%; }
.send-error { display: flex; gap: 10px; align-items: flex-start; margin-top: 12px; padding: 12px 14px; border-radius: var(--radius-sm); background: var(--danger-soft); border: 1px solid color-mix(in srgb, var(--danger) 40%, transparent); color: var(--text); font-size: .875rem; line-height: 1.5; }
.send-error i { color: var(--danger); margin-top: 3px; }

/* How it works */
.how-steps { list-style: none; display: grid; gap: 16px; counter-reset: how; }
.how-steps li { display: flex; gap: 14px; }
.how-steps li::before { counter-increment: how; content: counter(how); width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; background: var(--brand-soft); color: var(--brand-text); font-weight: 700; font-size: .875rem; }
.how-steps strong { display: block; }
.how-steps p { color: var(--text-2); font-size: .875rem; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
.info-box { padding: 14px; border-radius: var(--radius); background: var(--surface-2); border: 1px solid var(--border); font-size: .8125rem; color: var(--text-2); }
.info-box h3 { font-size: .875rem; color: var(--text); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
.info-box ul { list-style: none; display: grid; gap: 6px; }
.info-box li { display: flex; justify-content: space-between; gap: 8px; }

/* Edit drawer */
.drawer-backdrop.show { display: block; animation: fade .15s ease; }
.drawer {
  position: fixed; top: 0; right: 0; bottom: 0; z-index: 101;
  width: min(560px, 100%);
  background: var(--surface); border-left: 1px solid var(--border); box-shadow: var(--shadow-lg);
  display: flex; flex-direction: column;
  transform: translateX(100%); visibility: hidden;
  transition: transform .25s var(--ease), visibility .25s;
}
.drawer.show { transform: none; visibility: visible; }
.drawer-head { display: flex; align-items: flex-start; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--border); }
.drawer-head > div { flex: 1; }
.drawer-body { flex: 1; overflow-y: auto; padding: 8px 22px 24px; }
.drawer-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border); background: var(--surface); }
.ed-section { padding: 18px 0; border-bottom: 1px solid var(--border); }
.ed-section:last-child { border-bottom: 0; }
.ed-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.ed-section-head h3 { font-size: .9375rem; font-weight: 700; }
.ed-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ed-grid .full { grid-column: 1 / -1; }
.ed-item { position: relative; padding: 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface-2); }
.ed-item + .ed-item { margin-top: 10px; }
.ed-item-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; font-size: .8125rem; font-weight: 600; color: var(--text-3); }
.ed-field .input, .ed-field .textarea { font-size: .875rem; padding: 8px 10px; background: var(--surface); }
.ed-field label { display: block; font-size: .75rem; font-weight: 600; color: var(--text-2); margin-bottom: 4px; }
.ed-field .textarea { min-height: 76px; }
.ed-help { font-size: .75rem; color: var(--text-3); margin-top: 4px; }
.ed-empty { font-size: .8125rem; color: var(--text-3); }
.ed-error { margin: 0 22px; }

/* ==========================================================================
   Toasts
   ========================================================================== */
.toast-stack { position: fixed; right: 20px; bottom: 20px; z-index: 200; display: flex; flex-direction: column-reverse; gap: 10px; max-width: min(400px, calc(100vw - 40px)); }
.toast {
  position: relative; overflow: hidden;
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px;
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  animation: toastIn .22s var(--ease);
}
.toast.out { animation: toastOut .25s ease forwards; }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } }
@keyframes toastOut { to { opacity: 0; transform: translateY(8px); } }
.toast-icon { margin-top: 2px; }
.toast-msg { font-size: .875rem; font-weight: 500; line-height: 1.45; flex: 1; }
.toast-prog { position: absolute; left: 0; bottom: 0; height: 2px; animation: prog 3s linear forwards; }
@keyframes prog { from { width: 100%; } to { width: 0; } }
.toast.success .toast-icon { color: var(--success); } .toast.success .toast-prog { background: var(--success); }
.toast.error .toast-icon { color: var(--danger); }   .toast.error .toast-prog { background: var(--danger); }
.toast.warn .toast-icon { color: var(--warning); }   .toast.warn .toast-prog { background: var(--warning); }
.toast.info .toast-icon { color: var(--brand-text); } .toast.info .toast-prog { background: var(--brand); }

/* ==========================================================================
   Responsive
   ========================================================================== */
@media (max-width: 1100px) {
  .workspace { grid-template-columns: 380px 1fr; }
  .btn .hide-md { display: none; }
}
@media (max-width: 900px) {
  .workspace { display: flex; flex-direction: column; height: auto; }
  .sidebar { border-right: 0; border-bottom: 1px solid var(--border); }
  .sidebar-scroll { overflow: visible; padding: 20px 16px 8px; }
  .sidebar-footer { position: sticky; bottom: 0; z-index: 20; padding: 12px 16px; box-shadow: 0 -6px 16px rgba(15, 23, 42, .06); }
  .stage { min-height: 70vh; }
  .toolbar { padding: 10px 16px; position: sticky; top: var(--header-h); z-index: 20; }
  .preview-body { padding: 20px 12px 32px; }
  .app-header { padding: 0 12px; }
  .header-actions .btn-label { display: none; }
  .gmail-btn { max-width: 44px; padding: 0; width: 38px; }
  .gmail-btn span:last-child { display: none; }
  .zoom-group { display: none; }
}
@media (max-width: 560px) {
  body { font-size: .9375rem; }
  .tone-grid { grid-template-columns: 1fr; }
  .tone-opt { display: flex; align-items: baseline; gap: 8px; }
  .tone-desc { margin-top: 0; }
  .toolbar .btn .btn-text { display: none; }
  .toolbar .btn { padding: 0 11px; }
  .ed-grid, .info-grid { grid-template-columns: 1fr; }
  .modal-backdrop { padding: 0; align-items: flex-end; }
  .modal { border-radius: var(--radius-lg) var(--radius-lg) 0 0; margin: 0; max-height: 92vh; overflow-y: auto; }
  .app-footer { justify-content: center; text-align: center; }
  .toast-stack { right: 12px; left: 12px; bottom: 84px; max-width: none; }
}
</style>
