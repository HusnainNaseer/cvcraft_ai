
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CVCraft AI-Powered CV Tailoring & Auto Apply</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
/* 
   DESIGN TOKENS
 */
:root {
  --bg:          #0C0A14;
  --s1:          #141020;
  --s2:          #1C1730;
  --s3:          #252040;
  --s4:          #2E2850;
  --border:      #2A2440;
  --border2:     #352E55;
  --brand:       #8B5CF6;
  --brand-d:     #7C3AED;
  --brand-dim:   rgba(139,92,246,.14);
  --brand-glow:  rgba(139,92,246,.35);
  --linkedin:    #0A66C2;
  --linkedin-d:  #074E95;
  --gmail:       #EA4335;
  --gmail-d:     #C5221F;
  --green:       #34D399;
  --green-dim:   rgba(52,211,153,.12);
  --red:         #F87171;
  --red-dim:     rgba(248,113,113,.12);
  --amber:       #FBBF24;
  --amber-dim:   rgba(251,191,36,.12);
  --text:        #EDE9F8;
  --text2:       #A89DC0;
  --text3:       #6B6080;
  --white:       #ffffff;
  --radius-sm:   6px;
  --radius:      10px;
  --radius-lg:   16px;
  --shadow:      0 4px 24px rgba(0,0,0,.4);
  --shadow-brand:0 0 0 3px var(--brand-dim);
  --font:        'Plus Jakarta Sans', sans-serif;
  --mono:        'JetBrains Mono', monospace;
  --nav-h:       56px;
  --nav-bg:      rgba(12,10,20,.94);
}

/* 
   LIGHT MODE TOKENS
 */
html.light {
  --bg:          #F2F0FA;
  --s1:          #FFFFFF;
  --s2:          #F7F5FD;
  --s3:          #EDE8FA;
  --s4:          #E0D9F5;
  --border:      #DDD6F0;
  --border2:     #CEC5E8;
  --brand:       #7C3AED;
  --brand-d:     #6D28D9;
  --brand-dim:   rgba(124,58,237,.10);
  --brand-glow:  rgba(124,58,237,.25);
  --green:       #059669;
  --green-dim:   rgba(5,150,105,.10);
  --red:         #DC2626;
  --red-dim:     rgba(220,38,38,.10);
  --amber:       #D97706;
  --amber-dim:   rgba(217,119,6,.10);
  --text:        #18112E;
  --text2:       #4A3F6A;
  --text3:       #8E84AE;
  --shadow:      0 4px 24px rgba(0,0,0,.08);
  --shadow-brand:0 0 0 3px var(--brand-dim);
  --nav-bg:      rgba(255,255,255,.94);
}

/* 
   BASE
 */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;transition:background .28s ease,color .28s ease}
a{color:inherit;text-decoration:none}
button{font-family:var(--font);cursor:pointer}
input,textarea{font-family:var(--font)}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--s1)}
::-webkit-scrollbar-thumb{background:var(--s4);border-radius:3px}
::selection{background:var(--brand-dim);color:var(--brand)}

/* 
   NAVBAR
 */
.navbar{
  height:var(--nav-h);
  background:rgba(12,10,20,.92);
  backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;
  padding:0 28px;
  gap:16px;
  position:sticky;top:0;z-index:200;
  transition:border-color .3s, background .28s ease;
}
html.light .navbar{
  background:rgba(255,255,255,.92);
}
.navbar.scrolled{border-color:var(--border2)}

.nav-logo{
  display:flex;align-items:center;gap:10px;
  flex-shrink:0;
}
.logo-mark{
  width:32px;height:32px;
  background:linear-gradient(135deg,var(--brand),#A78BFA);
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:.78rem;font-weight:800;color:#fff;
  box-shadow:0 0 16px var(--brand-glow);
  letter-spacing:-.02em;
}
.logo-text{font-size:.95rem;font-weight:800;color:var(--text);letter-spacing:-.02em}
.logo-text em{font-style:normal;color:var(--brand)}

.nav-sep{width:1px;height:22px;background:var(--border);flex-shrink:0}

.nav-status{
  display:flex;align-items:center;gap:7px;
  font-size:.72rem;font-weight:600;color:var(--text3);
  letter-spacing:.03em;
}
.nav-status-dot{
  width:6px;height:6px;border-radius:50%;
  background:var(--green);
  box-shadow:0 0 6px var(--green);
  animation:breathe 2.4s ease infinite;
}
@keyframes breathe{0%,100%{opacity:1}50%{opacity:.45}}

.nav-spacer{flex:1}

.nav-right{display:flex;align-items:center;gap:10px}

.btn-ghost{
  display:flex;align-items:center;gap:6px;
  padding:7px 13px;
  border:1px solid var(--border2);
  border-radius:var(--radius-sm);
  background:transparent;color:var(--text2);
  font-size:.77rem;font-weight:600;
  transition:all .18s;
}
.btn-ghost:hover{background:var(--s3);border-color:var(--border2);color:var(--text)}

/*  Theme Toggle Button  */
.btn-theme{
  width:34px;height:34px;
  border-radius:var(--radius-sm);
  border:1px solid var(--border2);
  background:var(--s2);
  color:var(--text2);
  display:flex;align-items:center;justify-content:center;
  font-size:.82rem;
  transition:all .22s ease;
  position:relative;
  overflow:hidden;
  flex-shrink:0;
}
.btn-theme:hover{
  background:var(--s3);
  border-color:var(--brand);
  color:var(--brand);
  box-shadow:0 0 12px var(--brand-dim);
  transform:rotate(15deg);
}
.btn-theme .icon-sun,
.btn-theme .icon-moon{
  position:absolute;
  transition:opacity .25s ease, transform .3s ease;
}
/* Dark mode: show moon, hide sun */
.btn-theme .icon-sun  { opacity:0; transform:scale(.6) rotate(90deg); }
.btn-theme .icon-moon { opacity:1; transform:scale(1) rotate(0deg); }
/* Light mode: show sun, hide moon */
html.light .btn-theme .icon-sun  { opacity:1; transform:scale(1) rotate(0deg); }
html.light .btn-theme .icon-moon { opacity:0; transform:scale(.6) rotate(-90deg); }

.btn-connect{
  display:flex;align-items:center;gap:7px;
  padding:8px 15px;
  border-radius:var(--radius-sm);
  background:var(--gmail);
  border:1px solid transparent;
  color:#fff;font-size:.77rem;font-weight:700;
  transition:all .2s;
  box-shadow:0 2px 12px rgba(234,67,53,.3);
}
.btn-connect:hover{background:var(--gmail-d);box-shadow:0 4px 20px rgba(234,67,53,.45);transform:translateY(-1px)}
.btn-connect.active{
  background:transparent;
  border-color:var(--green);
  color:var(--green);
  box-shadow:0 0 12px rgba(52,211,153,.2);
}
.btn-connect.active:hover{background:var(--green-dim);transform:none}

/* 
   WORKSPACE LAYOUT
 */
.workspace{
  flex:1;
  display:flex;
  height:calc(100vh - var(--nav-h));
  overflow:hidden;
}

/* 
   LEFT PANEL
 */
.left-panel{
  width:390px;min-width:390px;
  background:var(--s1);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  overflow-y:auto;
  overflow-x:hidden;
}

.panel-top{
  padding:22px 24px 18px;
  border-bottom:1px solid var(--border);
  flex-shrink:0;
}
.panel-title{font-size:1rem;font-weight:800;color:var(--text);letter-spacing:-.02em}
.panel-sub{font-size:.75rem;color:var(--text3);margin-top:3px;line-height:1.5}

/* Progress track */
.progress-track{
  display:flex;align-items:center;
  gap:0;
  margin-top:16px;
}
.pt-step{
  display:flex;align-items:center;gap:6px;
  flex:1;
}
.pt-num{
  width:22px;height:22px;border-radius:50%;
  background:var(--s3);border:1.5px solid var(--border2);
  color:var(--text3);font-size:.67rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  transition:all .3s;flex-shrink:0;
}
.pt-label{font-size:.67rem;font-weight:600;color:var(--text3);white-space:nowrap;transition:color .3s}
.pt-line{flex:1;height:1px;background:var(--border);margin:0 6px;transition:background .3s}
.pt-step.done .pt-num{background:var(--green-dim);border-color:var(--green);color:var(--green)}
.pt-step.active .pt-num{background:var(--brand-dim);border-color:var(--brand);color:var(--brand);box-shadow:0 0 10px var(--brand-dim)}
.pt-step.active .pt-label,.pt-step.done .pt-label{color:var(--text2)}
.pt-step.done .pt-line{background:var(--green)}

/* Blocks */
.block{
  padding:20px 24px;
  border-bottom:1px solid var(--border);
}
.block-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:14px;
}
.block-title{
  display:flex;align-items:center;gap:8px;
  font-size:.8rem;font-weight:700;color:var(--text);
}
.block-icon{
  width:24px;height:24px;border-radius:6px;
  background:var(--brand-dim);border:1px solid rgba(139,92,246,.2);
  display:flex;align-items:center;justify-content:center;
  color:var(--brand);font-size:.65rem;
}
.block-badge{
  font-size:.65rem;font-weight:700;padding:2px 8px;
  border-radius:100px;
}
.badge-done{background:var(--green-dim);color:var(--green);border:1px solid rgba(52,211,153,.25)}
.badge-req{background:var(--brand-dim);color:var(--brand);border:1px solid rgba(139,92,246,.2)}

/* Upload zone */
.upload-zone{
  border:1.5px dashed var(--border2);
  border-radius:var(--radius);
  padding:24px 16px;
  text-align:center;
  cursor:pointer;
  background:var(--s2);
  transition:all .22s;
  position:relative;
}
.upload-zone input[type=file]{
  position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;
}
.upload-zone:hover,.upload-zone.over{
  border-color:var(--brand);
  background:var(--brand-dim);
}
.upload-zone.over{transform:scale(1.01)}
.uz-icon{
  width:40px;height:40px;
  margin:0 auto 10px;
  background:var(--s3);border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;color:var(--brand);
  transition:transform .2s;
}
.upload-zone:hover .uz-icon{transform:translateY(-2px)}
.uz-title{font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:4px}
.uz-sub{font-size:.72rem;color:var(--text3);line-height:1.5}
.uz-sub span{color:var(--brand);font-weight:600;cursor:pointer}

/* File card */
.file-card{
  display:none;
  align-items:center;gap:12px;
  background:var(--s2);
  border:1.5px solid var(--green);
  border-radius:var(--radius);
  padding:11px 14px;
  box-shadow:0 0 16px rgba(52,211,153,.08);
}
.file-card.show{display:flex}
.fc-icon{
  width:36px;height:36px;border-radius:8px;
  background:var(--green-dim);
  display:flex;align-items:center;justify-content:center;
  color:var(--green);font-size:.9rem;flex-shrink:0;
}
.fc-info{flex:1;min-width:0}
.fc-name{font-size:.8rem;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fc-meta{font-size:.68rem;color:var(--text3);margin-top:2px}
.fc-actions{display:flex;gap:6px;flex-shrink:0}
.fc-btn{
  width:26px;height:26px;border-radius:6px;
  background:var(--s3);border:1px solid var(--border2);
  color:var(--text2);font-size:.7rem;
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;
}
.fc-btn:hover{background:var(--red-dim);border-color:var(--red);color:var(--red)}

/* Tabs */
.tab-bar{
  display:flex;gap:2px;
  background:var(--s2);
  border:1px solid var(--border);
  border-radius:var(--radius-sm);
  padding:3px;
  margin-bottom:12px;
}
.tab-btn{
  flex:1;padding:6px;
  border:none;border-radius:4px;
  background:transparent;color:var(--text3);
  font-size:.73rem;font-weight:600;
  transition:all .18s;display:flex;align-items:center;justify-content:center;gap:5px;
}
.tab-btn.active{background:var(--s4);color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.3)}
.tab-content{display:none}
.tab-content.active{display:block}

/* URL input */
.url-row{display:flex;gap:8px}
.input-field{
  flex:1;padding:9px 12px;
  background:var(--s2);
  border:1.5px solid var(--border2);
  border-radius:var(--radius-sm);
  color:var(--text);font-size:.78rem;
  transition:border-color .2s,box-shadow .2s;
}
.input-field::placeholder{color:var(--text3)}
.input-field:focus{outline:none;border-color:var(--brand);box-shadow:var(--shadow-brand)}

.btn-fetch{
  padding:9px 13px;
  background:var(--brand-dim);
  border:1px solid rgba(139,92,246,.3);
  border-radius:var(--radius-sm);
  color:var(--brand);font-size:.75rem;font-weight:700;
  white-space:nowrap;transition:all .18s;
  display:flex;align-items:center;gap:5px;
}
.btn-fetch:hover{background:rgba(139,92,246,.22)}

/* Textarea */
.textarea-wrap{position:relative}
.jd-textarea{
  width:100%;min-height:148px;
  padding:11px 12px;
  background:var(--s2);
  border:1.5px solid var(--border2);
  border-radius:var(--radius-sm);
  color:var(--text);font-size:.78rem;line-height:1.65;
  resize:vertical;
  transition:border-color .2s,box-shadow .2s;
}
.jd-textarea::placeholder{color:var(--text3)}
.jd-textarea:focus{outline:none;border-color:var(--brand);box-shadow:var(--shadow-brand)}
.char-pill{
  position:absolute;bottom:9px;right:10px;
  font-size:.65rem;color:var(--text3);font-family:var(--mono);
  background:var(--s2);padding:1px 6px;border-radius:4px;
  pointer-events:none;
}

/* Keywords row */
.kw-row{
  display:flex;flex-wrap:wrap;gap:5px;
  margin-top:10px;
  max-height:0;overflow:hidden;
  transition:max-height .4s ease;
}
.kw-row.open{max-height:180px}
.kw-chip{
  font-size:.67rem;font-weight:600;
  padding:3px 9px;border-radius:100px;
  border:1px solid var(--border2);
  color:var(--text3);background:var(--s2);
  transition:all .2s;
}
.kw-chip.hit{background:var(--green-dim);border-color:rgba(52,211,153,.3);color:var(--green)}
.kw-chip.miss{background:var(--red-dim);border-color:rgba(248,113,113,.2);color:var(--red)}

/* Tone selector */
.tone-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px}
.tone-opt{
  padding:8px 4px;
  border:1.5px solid var(--border2);
  border-radius:var(--radius-sm);
  background:var(--s2);color:var(--text2);
  font-size:.72rem;font-weight:700;
  text-align:center;
  cursor:pointer;transition:all .18s;
  display:flex;flex-direction:column;align-items:center;gap:4px;
}
.tone-opt i{font-size:.85rem}
.tone-opt:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-dim)}
.tone-opt.selected{border-color:var(--brand);background:var(--brand-dim);color:var(--brand);box-shadow:0 0 12px var(--brand-dim)}

/* Generate CTA */
.cta-wrap{padding:20px 24px;flex-shrink:0}

.btn-generate{
  width:100%;padding:14px;
  background:linear-gradient(135deg,var(--brand-d),var(--brand));
  border:none;border-radius:var(--radius);
  color:#fff;font-size:.88rem;font-weight:800;
  letter-spacing:-.01em;
  display:flex;align-items:center;justify-content:center;gap:9px;
  transition:all .2s;
  box-shadow:0 4px 24px var(--brand-glow);
  position:relative;overflow:hidden;
}
.btn-generate::after{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.08),transparent);
  pointer-events:none;
}
.btn-generate:hover:not(:disabled){
  transform:translateY(-1px);
  box-shadow:0 6px 32px var(--brand-glow);
}
.btn-generate:active:not(:disabled){transform:translateY(0)}
.btn-generate:disabled{opacity:.4;cursor:not-allowed;box-shadow:none;transform:none}
.btn-generate .spin{
  width:16px;height:16px;
  border:2px solid rgba(255,255,255,.3);
  border-top-color:#fff;border-radius:50%;
  animation:rot .65s linear infinite;
  display:none;
}
.btn-generate.loading .gen-icon{display:none}
.btn-generate.loading .spin{display:block}
@keyframes rot{to{transform:rotate(360deg)}}

.gen-hint{
  text-align:center;margin-top:8px;
  font-size:.68rem;color:var(--text3);
  display:flex;align-items:center;justify-content:center;gap:5px;
}

/* Match Score */
.match-card{
  width:100%;
  margin:12px 0 0;
  background:var(--s2);border:1px solid var(--border2);
  border-radius:var(--radius);padding:14px 16px;
  display:none;
}
.match-card.show{display:block;animation:popIn .35s ease}
@keyframes popIn{from{opacity:0;transform:scale(.97)}to{opacity:1;transform:scale(1)}}
.match-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.match-label{font-size:.73rem;font-weight:700;color:var(--text2)}
.match-val{font-size:1.2rem;font-weight:800;color:var(--brand);font-family:var(--mono)}
.match-bar{height:5px;background:var(--s4);border-radius:3px;overflow:hidden;margin-bottom:10px}
.match-fill{height:100%;background:linear-gradient(90deg,var(--brand),#A78BFA);border-radius:3px;width:0;transition:width 1.4s cubic-bezier(.4,0,.2,1)}
.match-chips{display:flex;flex-wrap:wrap;gap:4px}

/* 
   RIGHT PANEL
 */
.right-panel{
  flex:1;
  display:flex;flex-direction:column;
  background:var(--bg);
  overflow:hidden;
}

.preview-bar{
  height:50px;
  background:rgba(12,10,20,.9);
  backdrop-filter:blur(8px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;
  padding:0 22px;gap:14px;
  flex-shrink:0;
  transition:background .28s ease;
}
html.light .preview-bar{
  background:rgba(255,255,255,.9);
}
.pbar-left{display:flex;align-items:center;gap:10px;flex:1}
.pbar-title{font-size:.82rem;font-weight:700;color:var(--text)}
.status-pill{
  font-size:.65rem;font-weight:700;
  padding:2px 9px;border-radius:100px;
  display:flex;align-items:center;gap:4px;
  transition:all .3s;
}
.sp-idle{background:var(--s3);color:var(--text3);border:1px solid var(--border)}
.sp-working{background:var(--amber-dim);color:var(--amber);border:1px solid rgba(251,191,36,.2)}
.sp-ready{background:var(--green-dim);color:var(--green);border:1px solid rgba(52,211,153,.25)}
.sp-dot{width:5px;height:5px;border-radius:50%;background:currentColor}
.sp-ready .sp-dot{animation:breathe 1.8s ease infinite}

.pbar-center{display:flex;align-items:center;gap:4px}
.zoom-btn{
  width:28px;height:28px;border-radius:6px;
  background:var(--s2);border:1px solid var(--border);
  color:var(--text2);font-size:.75rem;
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;
}
.zoom-btn:hover{background:var(--s3);border-color:var(--border2);color:var(--text)}
.zoom-val{
  min-width:42px;text-align:center;
  font-size:.72rem;font-weight:700;
  color:var(--text2);font-family:var(--mono);
}
.pbar-right{display:flex;align-items:center;gap:7px}

.pbtn{
  display:flex;align-items:center;gap:6px;
  padding:7px 13px;
  border-radius:var(--radius-sm);
  border:1px solid var(--border2);
  background:var(--s2);color:var(--text2);
  font-size:.74rem;font-weight:600;
  transition:all .18s;
}
.pbtn:hover{background:var(--s3);border-color:var(--brand);color:var(--brand)}
.pbtn:disabled{opacity:.35;pointer-events:none}
.pbtn-apply{
  background:linear-gradient(135deg,var(--linkedin-d),var(--linkedin));
  border-color:transparent;color:#fff;
  box-shadow:0 2px 10px rgba(10,102,194,.3);
}
.pbtn-apply:hover{box-shadow:0 4px 18px rgba(10,102,194,.45);background:linear-gradient(135deg,var(--linkedin),#1a7fd4);color:#fff;border-color:transparent}
.photo-tool{
  display:none;align-items:center;gap:6px;
  padding:6px 9px;
  border:1px solid var(--border2);
  border-radius:var(--radius-sm);
  background:var(--s2);
}
.photo-tool.show{display:flex}
.photo-check{
  display:flex;align-items:center;gap:5px;
  font-size:.72rem;font-weight:700;color:var(--text2);
  white-space:nowrap;
}
.photo-check input{accent-color:var(--brand)}
.photo-pick{
  width:28px;height:28px;
  border-radius:6px;border:1px solid var(--border2);
  background:var(--s3);color:var(--text2);
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;transition:all .15s;
}
.photo-pick:hover:not(:disabled){border-color:var(--brand);color:var(--brand);background:var(--brand-dim)}
.photo-pick:disabled{opacity:.35;cursor:not-allowed}
.theme-tool{
  display:none;align-items:center;gap:5px;
  padding:6px 9px;
  border:1px solid var(--border2);
  border-radius:var(--radius-sm);
  background:var(--s2);
}
.theme-tool.show{display:flex}
.theme-swatch{
  width:18px;height:18px;border-radius:50%;
  border:2px solid var(--border2);
  background:var(--swatch);
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.12);
  transition:all .15s;
}
.theme-swatch:hover{border-color:var(--text2);transform:translateY(-1px)}
.theme-swatch.active{border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-dim), inset 0 0 0 1px rgba(255,255,255,.18)}

/* Preview body */
.preview-body{
  flex:1;
  display:flex;align-items:flex-start;justify-content:center;
  overflow-y:auto;
  padding:32px 24px;
  position:relative;
}

/* Empty state */
.empty-state{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;text-align:center;
  padding:60px 40px;gap:14px;
  width:100%;min-height:400px;
}
.es-art{
  width:88px;height:88px;
  background:var(--s2);border:1px solid var(--border2);
  border-radius:20px;
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;color:var(--brand);
  position:relative;
  box-shadow:0 0 40px rgba(139,92,246,.12);
}
.es-art::after{
  content:'';
  position:absolute;inset:-1px;border-radius:21px;
  background:linear-gradient(135deg,rgba(139,92,246,.3),transparent 60%);
  pointer-events:none;
}
.es-title{font-size:1.05rem;font-weight:800;color:var(--text);letter-spacing:-.02em}
.es-sub{font-size:.78rem;color:var(--text3);max-width:300px;line-height:1.6}
.es-steps{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:6px}
.es-step{
  display:flex;align-items:center;gap:5px;
  font-size:.7rem;font-weight:600;color:var(--text3);
  background:var(--s2);border:1px solid var(--border);
  border-radius:100px;padding:4px 11px;
}
.es-step i{color:var(--brand)}

/* Processing */
.proc-state{
  display:none;
  flex-direction:column;align-items:center;
  justify-content:center;
  gap:18px;padding:60px 40px;
  width:100%;min-height:400px;
  text-align:center;
}
.proc-state.show{display:flex}
.proc-ring{
  width:52px;height:52px;
  border:3px solid var(--s4);
  border-top-color:var(--brand);
  border-radius:50%;
  animation:rot .8s linear infinite;
}
.proc-title{font-size:.9rem;font-weight:700;color:var(--text)}
.proc-list{
  display:flex;flex-direction:column;gap:6px;
  width:100%;max-width:280px;
}
.pl-item{
  display:flex;align-items:center;gap:9px;
  padding:8px 13px;
  border-radius:var(--radius-sm);
  border:1px solid var(--border);
  background:var(--s2);
  font-size:.76rem;color:var(--text3);
  text-align:left;
  transition:all .3s;
}
.pl-item.active{border-color:var(--brand);background:var(--brand-dim);color:var(--brand);font-weight:600}
.pl-item.done{border-color:rgba(52,211,153,.3);background:var(--green-dim);color:var(--green);font-weight:600}
.pl-item i{width:13px;text-align:center;font-size:.72rem}

/* CV Frame */
.cv-frame-outer{
  display:none;
  flex-direction:column;align-items:center;
  width:100%;
}
.cv-frame-outer.show{display:flex}
.cv-viewport{
  overflow:hidden;
  border-radius:4px;
  box-shadow:0 8px 48px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.04);
  transition:box-shadow .3s;
}
.cv-viewport:hover{box-shadow:0 12px 60px rgba(0,0,0,.7),0 0 0 1px rgba(139,92,246,.15)}
.cv-scaler{transform-origin:top left}
.cv-iframe{border:none;display:block;background:#fff}

/* 
   APPLY MODAL
 */
.modal-backdrop{
  position:fixed;inset:0;
  background:rgba(0,0,0,.7);
  backdrop-filter:blur(6px);
  z-index:500;
  display:none;align-items:center;justify-content:center;
  animation:fadeIn .2s ease;
}
.modal-backdrop.show{display:flex}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

.modal{
  background:var(--s1);
  border:1px solid var(--border2);
  border-radius:var(--radius-lg);
  padding:28px;
  width:100%;max-width:560px;max-height:calc(100vh - 32px);overflow-y:auto;
  margin:16px;
  box-shadow:0 24px 80px rgba(0,0,0,.6);
  animation:slideUp .25s ease;
}
@keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

.modal-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:6px;
}
.modal-title{font-size:1rem;font-weight:800;color:var(--text);letter-spacing:-.02em}
.modal-close{
  width:30px;height:30px;border-radius:7px;
  background:var(--s3);border:1px solid var(--border);
  color:var(--text2);font-size:.8rem;
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;
}
.modal-close:hover{background:var(--red-dim);border-color:var(--red);color:var(--red)}
.modal-sub{font-size:.76rem;color:var(--text3);margin-bottom:22px;line-height:1.5}

.apply-cards{display:flex;flex-direction:column;gap:10px}
.apply-card{
  border:1.5px solid var(--border2);
  border-radius:var(--radius);
  padding:16px 18px;
  background:var(--s2);
  cursor:pointer;transition:all .2s;
  display:flex;align-items:flex-start;gap:14px;
}
.apply-card:hover{border-color:var(--brand);background:var(--brand-dim)}
.apply-card.linkedin-card:hover{border-color:var(--linkedin);background:rgba(10,102,194,.1)}
.apply-card.static{cursor:default}
.apply-card.static:hover{border-color:var(--border2);background:var(--s2)}
.apply-card.recommended{border-color:var(--brand)}
.ac-badge{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:99px;background:var(--brand-dim);color:var(--brand);font-size:.62rem;font-weight:700;vertical-align:middle}
.apply-loading{display:flex;align-items:center;gap:10px;padding:26px 4px;color:var(--text2);font-size:.8rem}
.apply-loading .spin{width:16px;height:16px;border:2px solid var(--border2);border-top-color:var(--brand);border-radius:50%;animation:rot .65s linear infinite}
.ac-icon{
  width:38px;height:38px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;
  transition:transform .2s;
}
.apply-card:hover .ac-icon{transform:scale(1.08)}
.ac-icon-li{background:rgba(10,102,194,.15);color:var(--linkedin)}
.ac-icon-email{background:var(--brand-dim);color:var(--brand)}
.ac-body{flex:1}
.ac-title{font-size:.84rem;font-weight:700;color:var(--text);margin-bottom:3px}
.ac-desc{font-size:.73rem;color:var(--text3);line-height:1.5}
.ac-arrow{color:var(--text3);font-size:.75rem;margin-top:4px;transition:transform .2s}
.apply-card:hover .ac-arrow{transform:translateX(3px);color:var(--text2)}

.modal-email-form{display:none;margin-top:16px}
.modal-email-form.show{display:block}
.mef-label{font-size:.73rem;font-weight:600;color:var(--text2);margin-bottom:6px;display:block}
.mef-input{
  width:100%;padding:9px 12px;
  background:var(--s2);border:1.5px solid var(--border2);
  border-radius:var(--radius-sm);color:var(--text);font-size:.78rem;
  margin-bottom:10px;transition:border-color .2s;
}
.mef-input:focus{outline:none;border-color:var(--brand)}
textarea.mef-input{min-height:190px;resize:vertical;line-height:1.5;font-family:inherit}
.sender-line{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:.72rem;color:var(--text3);margin:2px 0 12px}
.sender-line strong{color:var(--text2)}
.send-error{display:flex;gap:8px;align-items:flex-start;margin-top:10px;padding:10px 12px;border-radius:8px;background:var(--red-dim);border:1px solid var(--red);color:var(--text);font-size:.74rem;line-height:1.45}
.send-error[hidden]{display:none}
.send-error i{color:var(--red);margin-top:2px;flex-shrink:0}
.link-btn{background:none;border:none;padding:0;color:var(--brand);font-size:.72rem;font-weight:600;cursor:pointer}
.btn-send:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none}
.mef-input::placeholder{color:var(--text3)}
.btn-send{
  width:100%;padding:11px;
  background:linear-gradient(135deg,var(--brand-d),var(--brand));
  border:none;border-radius:var(--radius-sm);
  color:#fff;font-size:.82rem;font-weight:700;
  transition:all .2s;box-shadow:0 3px 16px var(--brand-glow);
}
.btn-send:hover{transform:translateY(-1px);box-shadow:0 5px 22px var(--brand-glow)}

/* 
   TOASTS
 */
.toast-stack{
  position:fixed;bottom:24px;right:24px;
  z-index:999;
  display:flex;flex-direction:column;
  gap:8px;align-items:flex-end;
}
.toast{
  display:flex;align-items:center;gap:10px;
  background:var(--s2);
  border:1px solid var(--border2);
  border-radius:var(--radius);
  padding:11px 15px;
  min-width:260px;max-width:340px;
  box-shadow:var(--shadow);
  animation:toastIn .28s ease;
  position:relative;overflow:hidden;
}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
.toast.out{animation:toastOut .28s ease forwards}
@keyframes toastOut{to{opacity:0;transform:translateX(20px)}}
.toast-icon{font-size:.88rem;flex-shrink:0}
.toast-msg{font-size:.76rem;font-weight:600;color:var(--text);flex:1;line-height:1.4}
.toast-prog{position:absolute;bottom:0;left:0;height:2px;border-radius:0 0 2px 2px;animation:prog 3s linear forwards}
@keyframes prog{from{width:100%}to{width:0%}}
.toast.success .toast-icon{color:var(--green)}
.toast.success .toast-prog{background:var(--green)}
.toast.error   .toast-icon{color:var(--red)}
.toast.error   .toast-prog{background:var(--red)}
.toast.info    .toast-icon{color:var(--brand)}
.toast.info    .toast-prog{background:var(--brand)}
.toast.warn    .toast-icon{color:var(--amber)}
.toast.warn    .toast-prog{background:var(--amber)}

/* 
   FOOTER
 */
.footer{
  height:38px;
  background:var(--s1);
  border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;flex-shrink:0;
}
.footer-l{font-size:.67rem;color:var(--text3);display:flex;align-items:center;gap:6px}
.footer-r{display:flex;align-items:center;gap:14px}
.footer-r a{font-size:.67rem;color:var(--text3);transition:color .15s}
.footer-r a:hover{color:var(--text2)}
.kbd{
  display:inline-flex;align-items:center;gap:4px;
  font-size:.62rem;font-family:var(--mono);color:var(--text3);
  background:var(--s3);border:1px solid var(--border2);
  border-radius:4px;padding:1px 5px;
}

/* 
   RESPONSIVE
 */
@media(max-width:860px){
  .workspace{flex-direction:column;height:auto}
  .left-panel{width:100%;min-width:unset}
  .right-panel{min-height:60vh}
  .nav-status{display:none}
  .pbar-center{display:none}
}
</style>
</head>
<body>
@php($gmailEmail = data_get(session('gmail_token'), 'email'))

<!-- TOAST STACK -->
<div class="toast-stack" id="toastStack"></div>

<!-- APPLY MODAL -->
<div class="modal-backdrop" id="applyModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="applyTitle">
    <div class="modal-header">
      <span class="modal-title" id="applyTitle">Apply for this job</span>
      <button class="modal-close" onclick="closeModal()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <p class="modal-sub" id="applySub">Your tailored CV is attached as a PDF.</p>

    <div class="apply-loading" id="applyLoading">
      <div class="spin"></div> Preparing your application email...
    </div>

    <div class="apply-cards" id="applyCards" style="display:none">
      <div class="apply-card static" id="applyEmailCard">
        <div class="ac-icon ac-icon-email"><i class="fas fa-envelope"></i></div>
        <div class="ac-body">
          <div class="ac-title">Send application by email <span class="ac-badge" id="emailBadge">Recommended</span></div>
          <div class="ac-desc" id="emailDesc">The job asks for applications by email. Review the message, then send it from your Gmail.</div>
          <div class="modal-email-form show">
            <br/>
            <label class="mef-label" for="recEmail">To</label>
            <input class="mef-input" type="email" placeholder="recruiter@company.com" id="recEmail" list="recEmailOptions"/>
            <datalist id="recEmailOptions"></datalist>
            <label class="mef-label" for="emailSubject">Subject</label>
            <input class="mef-input" type="text" id="emailSubject" maxlength="200"/>
            <label class="mef-label" for="emailBody">Message</label>
            <textarea class="mef-input" id="emailBody" maxlength="5000"></textarea>
            <div class="sender-line" id="senderLine"></div>
            <button class="btn-send" id="btnSendEmail" onclick="sendApplicationEmail()"><i class="fas fa-paper-plane"></i> &nbsp;Send Application</button>
            <div class="send-error" id="sendError" role="alert" hidden><i class="fas fa-circle-exclamation"></i><span id="sendErrorText"></span></div>
          </div>
        </div>
      </div>

      <div class="apply-card linkedin-card" id="applyLinkCard" onclick="applyViaLink()">
        <div class="ac-icon ac-icon-li"><i class="fas fa-arrow-up-right-from-square" id="linkIcon"></i></div>
        <div class="ac-body">
          <div class="ac-title" id="linkTitle">Apply on the job page</div>
          <div class="ac-desc" id="linkDesc">Opens the application page and downloads your tailored PDF so you can upload it there.</div>
        </div>
        <i class="fas fa-chevron-right ac-arrow"></i>
      </div>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-logo">
    <div class="logo-mark">CV</div>
    <span class="logo-text">CV<em>Craft</em></span>
  </div>
  <div class="nav-sep"></div>
  <div class="nav-status">
    <span class="nav-status-dot"></span>
    AI Engine Ready
  </div>

  <div class="nav-spacer"></div>

  <div class="nav-right">
    <button class="btn-ghost" onclick="toast('info','Keyboard shortcut: Ctrl + Enter to generate')">
      <i class="fas fa-keyboard"></i> Shortcuts
    </button>

    <!--  Theme Toggle  -->
    <button class="btn-theme" id="themeBtn" onclick="toggleTheme()" title="Toggle light / dark mode" aria-label="Toggle theme">
      <i class="fas fa-sun icon-sun"></i>
      <i class="fas fa-moon icon-moon"></i>
    </button>

    <div class="nav-sep"></div>
    <button class="btn-connect{{ $gmailEmail ? ' active' : '' }}" id="gmailBtn" type="button" onclick="toggleGmail()"
            title="{{ $gmailEmail ? 'Disconnect Gmail' : 'Connect Gmail to send applications' }}">
      <i class="fas fa-envelope"></i>
      <span id="gmailLabel">{{ $gmailEmail ?: 'Connect Gmail' }}</span>
    </button>
  </div>
</nav>

<!-- WORKSPACE -->
<div class="workspace">

  <!-- LEFT PANEL -->
  <div class="left-panel">

    <div class="panel-top">
      <div class="panel-title">Build Your Tailored CV</div>
      <div class="panel-sub">Upload once. Tailor for every job. Apply in seconds.</div>
      <div class="progress-track" id="progressTrack">
        <div class="pt-step active" id="ps1">
          <div class="pt-num">1</div>
          <div class="pt-label">Upload</div>
          <div class="pt-line"></div>
        </div>
        <div class="pt-step" id="ps2">
          <div class="pt-num">2</div>
          <div class="pt-label">Job</div>
          <div class="pt-line"></div>
        </div>
        <div class="pt-step" id="ps3">
          <div class="pt-num">3</div>
          <div class="pt-label">Generate</div>
        </div>
      </div>
    </div>

    <!-- BLOCK 1: Upload -->
    <div class="block">
      <div class="block-header">
        <div class="block-title">
          <div class="block-icon"><i class="fas fa-file-alt"></i></div>
          Your CV
        </div>
        <span class="block-badge badge-req" id="uploadBadge">Required</span>
      </div>

      <div class="upload-zone" id="uploadZone"
           ondragover="onDragOver(event)"
           ondragleave="onDragLeave(event)"
           ondrop="onDrop(event)">
        <input type="file" id="cvInput" accept=".pdf,.doc,.docx,.html,.txt"
               onchange="onFileSelect(event)"/>
        <div class="uz-icon"><i class="fas fa-cloud-upload-alt"></i></div>
        <div class="uz-title">Drop your CV here</div>
        <div class="uz-sub">or <span onclick="document.getElementById('cvInput').click()">browse files</span>
          </div>
      </div>

      <div class="file-card" id="fileCard">
        <div class="fc-icon"><i class="fas fa-file-alt" id="fcTypeIcon"></i></div>
        <div class="fc-info">
          <div class="fc-name" id="fcName">No file selected</div>
          <div class="fc-meta" id="fcMeta">Waiting for upload</div>
        </div>
        <div class="fc-actions">
          <button class="fc-btn" title="Replace" onclick="replaceFile()"><i class="fas fa-sync-alt"></i></button>
          <button class="fc-btn" title="Remove" onclick="removeFile()"><i class="fas fa-trash"></i></button>
        </div>
      </div>
    </div>

    <!-- BLOCK 2: Job Description -->
    <div class="block">
      <div class="block-header">
        <div class="block-title">
          <div class="block-icon"><i class="fas fa-briefcase"></i></div>
          Job Description
        </div>
        <span class="block-badge badge-req" id="jobBadge">Required</span>
      </div>

      <div class="tab-bar">
        <button class="tab-btn active" id="tabUrl" onclick="switchTab('url')">
          <i class="fas fa-link"></i> Job URL
        </button>
        <button class="tab-btn" id="tabPaste" onclick="switchTab('paste')">
          <i class="fas fa-align-left"></i> Paste Text
        </button>
      </div>

      <div class="tab-content active" id="tcUrl">
        <div class="url-row">
          <input class="input-field" type="url" id="jobUrl"
                 placeholder="https://linkedin.com/jobs/view/..."
                 oninput="onJobUrlInput()"/>
          <button class="btn-fetch" onclick="fetchUrl()">
            <i class="fas fa-bolt"></i> Fetch
          </button>
        </div>
        <div style="font-size:.68rem;color:var(--text3);margin-top:7px;line-height:1.5">
          <i class="fas fa-info-circle" style="color:var(--brand)"></i>
          &nbsp;Works with LinkedIn job links and most company career pages. If a site blocks the fetch, paste the description instead.
        </div>
      </div>

      <div class="tab-content" id="tcPaste">
        <div class="textarea-wrap">
          <textarea class="jd-textarea" id="jobDesc"
                    placeholder="Paste the full job description here...&#10;&#10;e.g. We're looking for a Laravel developer with 3+ years experience in PHP, MySQL, REST APIs, and modern frontend tooling..."
                    oninput="onJdInput(this)"></textarea>
          <span class="char-pill" id="charPill">0</span>
        </div>
      </div>

      <!-- Match Score Card -->
      <div class="match-card" id="matchCard">
        <div class="match-top">
          <span class="match-label"><i class="fas fa-bullseye" style="color:var(--brand);margin-right:5px"></i><span id="matchLabelText">Job Match Score</span></span>
          <span class="match-val" id="matchPct">--</span>
        </div>
        <div class="match-bar"><div class="match-fill" id="matchFill"></div></div>
        <div class="match-chips" id="matchChips"></div>
      </div>
    </div>

    <!-- BLOCK 3: Options -->
    <div class="block">
      <div class="block-header">
        <div class="block-title">
          <div class="block-icon"><i class="fas fa-sliders-h"></i></div>
          Tone & Focus
        </div>
        <span style="font-size:.68rem;color:var(--text3);font-weight:600">Optional</span>
      </div>
      <div class="tone-grid">
        <div class="tone-opt selected" data-tone="professional" onclick="selectTone(this)">
          <i class="fas fa-user-tie"></i> Professional
        </div>
        <div class="tone-opt" data-tone="concise" onclick="selectTone(this)">
          <i class="fas fa-compress-alt"></i> Concise
        </div>
        <div class="tone-opt" data-tone="detailed" onclick="selectTone(this)">
          <i class="fas fa-expand-alt"></i> Detailed
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="cta-wrap">
      <button class="btn-generate" id="genBtn" disabled onclick="runGenerate()">
        <i class="fas fa-magic gen-icon"></i>
        <div class="spin"></div>
        <span id="genLabel">Optimise My CV</span>
      </button>
      <div class="gen-hint">
        <span class="kbd"><i class="fas fa-command"></i> Ctrl+Enter</span>
        &nbsp;to generate &nbsp;|&nbsp; usually under a minute
      </div>
    </div>

  </div><!-- /left-panel -->

  <!-- RIGHT PANEL -->
  <div class="right-panel">

    <!-- Toolbar -->
    <div class="preview-bar">
      <div class="pbar-left">
        <span class="pbar-title">CV Preview</span>
        <div class="status-pill sp-idle" id="statusPill">
          <span class="sp-dot"></span>
          <span id="statusText">Waiting</span>
        </div>
      </div>
      <div class="pbar-center">
        <button class="zoom-btn" onclick="changeZoom(-10)"><i class="fas fa-minus"></i></button>
        <span class="zoom-val" id="zoomVal">65%</span>
        <button class="zoom-btn" onclick="changeZoom(10)"><i class="fas fa-plus"></i></button>
        <button class="zoom-btn" onclick="resetZoom()" title="Fit to window" style="margin-left:2px">
          <i class="fas fa-compress"></i>
        </button>
      </div>
      <div class="pbar-right">
        <div class="photo-tool" id="photoTool">
          <label class="photo-check" title="Add or remove a profile picture in the tailored CV">
            <input type="checkbox" id="photoToggle" onchange="toggleProfilePhoto(this)">
            Picture
          </label>
          <button class="photo-pick" id="photoPickBtn" disabled onclick="pickProfilePhoto()" title="Choose profile picture">
            <i class="fas fa-camera"></i>
          </button>
          <input type="file" id="profilePhotoInput" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="onProfilePhotoSelect(event)">
        </div>
        <div class="theme-tool" id="themeTool" title="Change CV color">
          <button class="theme-swatch active" data-theme="purple" style="--swatch:#5C2D91" onclick="selectCvTheme(this)" aria-label="Purple theme"></button>
          <button class="theme-swatch" data-theme="blue" style="--swatch:#1D4ED8" onclick="selectCvTheme(this)" aria-label="Blue theme"></button>
          <button class="theme-swatch" data-theme="green" style="--swatch:#047857" onclick="selectCvTheme(this)" aria-label="Green theme"></button>
          <button class="theme-swatch" data-theme="teal" style="--swatch:#0F766E" onclick="selectCvTheme(this)" aria-label="Teal theme"></button>
          <button class="theme-swatch" data-theme="red" style="--swatch:#B91C1C" onclick="selectCvTheme(this)" aria-label="Red theme"></button>
          <button class="theme-swatch" data-theme="dark" style="--swatch:#1F2937" onclick="selectCvTheme(this)" aria-label="Dark theme"></button>
          <button class="theme-swatch" data-theme="gold" style="--swatch:#A16207" onclick="selectCvTheme(this)" aria-label="Gold theme"></button>
        </div>
        <button class="pbtn" id="btnDownload" disabled onclick="downloadCV()">
          <i class="fas fa-file-pdf"></i> Download PDF
        </button>
        <button class="pbtn" id="btnShare" disabled onclick="shareCV()">
          <i class="fas fa-share-nodes"></i> Share
        </button>
        <button class="pbtn pbtn-apply" id="btnApply" disabled onclick="openModal()">
          <i class="fas fa-paper-plane"></i> Apply Now
        </button>
      </div>
    </div>

    <!-- Preview body -->
    <div class="preview-body" id="previewBody">

      <!-- Empty state -->
      <div class="empty-state" id="emptyState">
        <div class="es-art"><i class="fas fa-file-user"></i></div>
        <div class="es-title">Your Tailored CV Appears Here</div>
        <div class="es-sub">Complete the three steps on the left and hit <strong style="color:var(--brand)">Optimise My CV</strong>. Your document will render instantly.</div>
        <div class="es-steps">
          <div class="es-step"><i class="fas fa-upload"></i> Upload CV</div>
          <div class="es-step"><i class="fas fa-briefcase"></i> Add Job</div>
          <div class="es-step"><i class="fas fa-magic"></i> Generate</div>
        </div>
      </div>

      <!-- Processing state -->
      <div class="proc-state" id="procState">
        <div class="proc-ring"></div>
        <div class="proc-title">Tailoring your CV to the job...</div>
        <div class="proc-list">
          <div class="pl-item" id="pl0"><i class="fas fa-search"></i> Reading uploaded CV</div>
          <div class="pl-item" id="pl1"><i class="fas fa-brain"></i> Matching job skills & requirements</div>
          <div class="pl-item" id="pl2"><i class="fas fa-edit"></i> Rewriting CV sections</div>
          <div class="pl-item" id="pl3"><i class="fas fa-magic"></i> Optimising keywords & tone</div>
          <div class="pl-item" id="pl4"><i class="fas fa-check-circle"></i> Finalising document layout</div>
        </div>
      </div>

      <!-- CV iframe -->
      <div class="cv-frame-outer" id="cvFrameOuter">
        <div class="cv-viewport" id="cvViewport">
          <div class="cv-scaler" id="cvScaler">
            <iframe class="cv-iframe" id="cvIframe"
                    src="about:blank"
                    title="Tailored CV Preview">
            </iframe>
          </div>
        </div>
      </div>

    </div><!-- /preview-body -->
  </div><!-- /right-panel -->

</div><!-- /workspace -->

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-l">
    <span style="color:var(--brand);font-weight:800">CVCraft AI</span>
    <span>|</span>
    <span>Tailor. Apply. Land.</span>
    <span>|</span>
    <span>&copy; 2026</span>
  </div>
  <div class="footer-r">
    <a href="#">How it works</a>
    <a href="#">Privacy</a>
    <a href="#">Support</a>
    <span class="kbd"><i class="fas fa-command"></i> Ctrl+Enter</span>
  </div>
</footer>

<!-- 
     JAVASCRIPT
 -->
<script>
/*  State  */
function emptyJob() {
  return { url: '', title: '', company: '', location: '', applyUrl: '', emails: [] };
}

const S = {
  file: null,
  templateFile: 'default.json',
  tailoredResult: null,
  tailoredPreviewUrl: null,
  tailoredDownloadUrl: null,
  jobReady: false,
  generated: false,
  gmailEmail: @json($gmailEmail),
  job: emptyJob(),
  application: null,
  tone: 'professional',
  zoom: 65,
  applyPrepared: null,
  darkMode: true,
  skillTimer: null,
  skillRequestId: 0,
  matchScore: null,
  matchedSkills: [],
  missingSkills: [],
  profilePhotoEnabled: false,
  cvTheme: 'purple',
};

const APP_BASE_PATH = @json(rtrim(request()->getBaseUrl(), '/'));

function appUrl(path) {
  if (!path) return APP_BASE_PATH || '/';
  if (/^https?:\/\//i.test(path)) return path;
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${APP_BASE_PATH}${normalizedPath}` || normalizedPath;
}

const GMAIL_CONNECT_URL = appUrl(@json(route('google.redirect', [], false)));
const GMAIL_DISCONNECT_URL = appUrl(@json(route('google.disconnect', [], false)));
const APPLICATION_PREPARE_URL = appUrl(@json(route('application.prepare', [], false)));
const APPLICATION_EMAIL_URL = appUrl(@json(route('application.email', [], false)));
const JOB_DESCRIPTION_FETCH_URL = appUrl(@json(route('job-description.fetch', [], false)));
const JOB_DESCRIPTION_SKILLS_URL = appUrl(@json(route('job-description.skills', [], false)));
const CV_TAILOR_URL = appUrl(@json(route('cv.tailor', [], false)));
const CV_PHOTO_URL = appUrl(@json(route('cv.photo', [], false)));
const CV_THEME_URL = appUrl(@json(route('cv.theme', [], false)));
const CV_DOWNLOAD_URL = appUrl(@json(route('cv.download', [], false)));
const CV_SHARE_URL = appUrl(@json(route('cv.share', [], false)));
const CSRF_REFRESH_URL = appUrl(@json(route('csrf.refresh', [], false)));
let csrfToken = @json(csrf_token());

/*  Theme Toggle  */
function toggleTheme() {
  S.darkMode = !S.darkMode;
  if (S.darkMode) {
    document.documentElement.classList.remove('light');
    toast('info', 'Switched to dark mode');
  } else {
    document.documentElement.classList.add('light');
    toast('info', 'Switched to light mode');
  }
  // Persist preference
  localStorage.setItem('cvcraft-theme', S.darkMode ? 'dark' : 'light');
}

/*  Init theme from localStorage  */
(function initTheme() {
  const saved = localStorage.getItem('cvcraft-theme');
  if (saved === 'light') {
    S.darkMode = false;
    document.documentElement.classList.add('light');
  }
})();

/*  Toast  */
function toast(type, msg, duration = 3200) {
  const icons = { success:'fa-check-circle', error:'fa-times-circle', info:'fa-info-circle', warn:'fa-triangle-exclamation' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<i class="fas ${icons[type]} toast-icon"></i><span class="toast-msg"></span><div class="toast-prog"></div>`;
  el.querySelector('.toast-msg').textContent = msg;
  el.querySelector('.toast-prog').style.animationDuration = `${duration}ms`;
  document.getElementById('toastStack').prepend(el);
  setTimeout(()=>{el.classList.add('out');setTimeout(()=>el.remove(),300)},duration);
}

/*  Gmail connection  */
function toggleGmail() {
  return S.gmailEmail ? disconnectGmail() : connectGmail();
}

function connectGmail() {
  const popup = window.open(GMAIL_CONNECT_URL, 'gmail-connect', 'width=520,height=680');
  if (!popup) toast('warn','Allow pop-ups for this site to connect Gmail.');
}

async function disconnectGmail() {
  try {
    await postJson(GMAIL_DISCONNECT_URL, {});
    setGmailAccount(null);
    toast('info','Gmail disconnected.');
  } catch (error) {
    toast('error', error.message);
  }
}

function setGmailAccount(email) {
  S.gmailEmail = email || null;
  const btn = document.getElementById('gmailBtn');
  btn.classList.toggle('active', Boolean(S.gmailEmail));
  btn.title = S.gmailEmail ? 'Disconnect Gmail' : 'Connect Gmail to send applications';
  document.getElementById('gmailLabel').textContent = S.gmailEmail || 'Connect Gmail';
  if (S.applyPrepared) renderSenderLine();
}

window.addEventListener('message', event => {
  if (event.origin !== window.location.origin || event.data?.type !== 'gmail-oauth') return;
  if (event.data.connected) setGmailAccount(event.data.email);
  toast(event.data.connected ? 'success' : 'error', event.data.message);
});

/*  HTTP helpers  */
async function postJson(url, payload) {
  return requestJson(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
}

async function refreshCsrfToken() {
  const response = await fetch(CSRF_REFRESH_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
  if (!response.ok) throw new Error('Your session expired. Please reload the page.');
  csrfToken = (await response.json()).token;
}

async function postForm(url, formData) {
  return requestJson(url, { method: 'POST', body: formData });
}

async function requestJson(url, options, retried = false) {
  const response = await fetch(url, {
    ...options,
    headers: { ...(options.headers || {}), 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
  });

  // 419 = the session (and its CSRF token) expired while the page was open: renew it and retry once.
  if (response.status === 419 && !retried) {
    await refreshCsrfToken();
    return requestJson(url, options, true);
  }

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
    const error = new Error(firstError || data.message || `Request failed (${response.status}).`);
    error.status = response.status;
    throw error;
  }
  return data;
}

/*  File Handling  */
function onDragOver(e) { e.preventDefault(); document.getElementById('uploadZone').classList.add('over'); }
function onDragLeave() { document.getElementById('uploadZone').classList.remove('over'); }
function onDrop(e) { e.preventDefault(); onDragLeave(); const f=e.dataTransfer.files[0]; if(f) processFile(f); }
function onFileSelect(e) { const f=e.target.files[0]; if(f) processFile(f); }
function replaceFile() { document.getElementById('cvInput').click(); }

function processFile(f) {
  const ext = f.name.split('.').pop().toLowerCase();
  const allowed = ['pdf','doc','docx','html','txt'];
  if (!allowed.includes(ext)) { toast('error','Unsupported file. Use PDF, DOC, DOCX, HTML, or TXT.'); return; }
  S.file = f;
  S.generated = false;
  S.tailoredResult = null;
  S.tailoredPreviewUrl = null;
  S.tailoredDownloadUrl = null;
  S.applyPrepared = null;
  resetPhotoTool();
  ['btnDownload','btnShare','btnApply'].forEach(id=>document.getElementById(id).disabled=true);

  const icons = {pdf:'fa-file-pdf',doc:'fa-file-word',docx:'fa-file-word',html:'fa-file-code'};
  document.getElementById('fcTypeIcon').className = `fas ${icons[ext]||'fa-file-alt'}`;
  document.getElementById('fcName').textContent = f.name;
  document.getElementById('fcMeta').textContent = `${ext.toUpperCase()} | ${formatBytes(f.size)} | Ready`;
  document.getElementById('uploadZone').style.display = 'none';
  document.getElementById('fileCard').classList.add('show');
  document.getElementById('uploadBadge').className = 'block-badge badge-done';
  document.getElementById('uploadBadge').textContent = 'Uploaded';
  setStep(1,'done'); setStep(2,'active');
  toast('success',`CV uploaded: "${f.name}"`);
  checkReady();
  scheduleSkillMatch();
}

function removeFile() {
  S.file = null;
  S.generated = false;
  S.tailoredResult = null;
  S.tailoredPreviewUrl = null;
  S.tailoredDownloadUrl = null;
  S.applyPrepared = null;
  resetPhotoTool();
  document.getElementById('uploadZone').style.display = '';
  document.getElementById('fileCard').classList.remove('show');
  document.getElementById('cvInput').value = '';
  document.getElementById('uploadBadge').className = 'block-badge badge-req';
  document.getElementById('uploadBadge').textContent = 'Required';
  ['btnDownload','btnShare','btnApply'].forEach(id=>document.getElementById(id).disabled=true);
  setStep(1,'active'); setStep(2,''); setStep(3,'');
  document.getElementById('cvIframe').src = 'about:blank';
  clearSkillChips();
  showView('empty');
  checkReady();
}

function resetPhotoTool() {
  S.profilePhotoEnabled = false;
  S.cvTheme = 'purple';
  const tool = document.getElementById('photoTool');
  const themeTool = document.getElementById('themeTool');
  const toggle = document.getElementById('photoToggle');
  const pick = document.getElementById('photoPickBtn');
  const input = document.getElementById('profilePhotoInput');
  if (tool) tool.classList.remove('show');
  if (themeTool) themeTool.classList.remove('show');
  if (toggle) toggle.checked = false;
  if (pick) pick.disabled = true;
  if (input) input.value = '';
  document.querySelectorAll('.theme-swatch').forEach(btn=>btn.classList.toggle('active', btn.dataset.theme === 'purple'));
}

function showPhotoTool() {
  document.getElementById('photoTool').classList.add('show');
  document.getElementById('themeTool').classList.add('show');
}

/*  Tabs  */
function switchTab(tab) {
  ['url','paste'].forEach(t=>{
    document.getElementById('tab'+t.charAt(0).toUpperCase()+t.slice(1)).classList.toggle('active',t===tab);
    document.getElementById('tc'+t.charAt(0).toUpperCase()+t.slice(1)).classList.toggle('active',t===tab);
  });
  checkReady();
}

/*  Job description  */
function onJdInput(el) {
  const len = el.value.length;
  document.getElementById('charPill').textContent = len;
  checkReady();
  scheduleSkillMatch();
}

function clearSkillChips() {
  S.matchScore = null;
  S.matchedSkills = [];
  S.missingSkills = [];
  hideMatchScore();
}

function renderSkillLoading(text) {
  document.getElementById('matchCard').classList.add('show');
  document.getElementById('matchLabelText').textContent = 'Job Match Score';
  document.getElementById('matchPct').textContent = '--';
  document.getElementById('matchFill').style.width = '0%';
  const chips = document.getElementById('matchChips');
  chips.innerHTML = '';
  const c=document.createElement('span');
  c.className='kw-chip';
  c.innerHTML=`<i class="fas fa-spinner fa-spin" style="font-size:.55rem;margin-right:3px"></i>${escapeChip(text)}`;
  chips.appendChild(c);
}

function scheduleSkillMatch() {
  clearTimeout(S.skillTimer);
  S.skillTimer = setTimeout(analyzeJobSkills, 450);
}

async function analyzeJobSkills() {
  const jobDescription = document.getElementById('jobDesc').value.trim();

  if (jobDescription.length < 50) {
    clearSkillChips();
    return;
  }

  if (!S.file) {
    S.matchScore = null;
    S.matchedSkills = [];
    S.missingSkills = [];
    hideMatchScore();
    renderSkillLoading('Upload CV to compare required skills');
    return;
  }

  const requestId = ++S.skillRequestId;
  renderSkillLoading('Matching job skills with CV');

  const formData = new FormData();
  formData.append('jobDescription', jobDescription);
  formData.append('cv', S.file);

  let data;
  try {
    data = await postForm(JOB_DESCRIPTION_SKILLS_URL, formData);
  } catch (error) {
    if (requestId !== S.skillRequestId) return;
    S.matchScore = null;
    S.matchedSkills = [];
    S.missingSkills = [];
    hideMatchScore();
    renderSkillLoading(error.message || 'Could not compare CV skills');
    return;
  }

  try {
    if (requestId !== S.skillRequestId) return;

    S.matchScore = Number.isInteger(data.score) ? data.score : null;
    S.matchedSkills = data.matched || [];
    S.missingSkills = data.missing || [];

    if (S.matchScore !== null) {
      showMatchScore(S.matchScore, S.matchedSkills, S.missingSkills, 'Uploaded CV Match Score');
    } else {
      hideMatchScore();
    }
  } catch (error) {
    if (requestId === S.skillRequestId) {
      renderSkillLoading('Skill comparison unavailable');
    }
  }
}

function escapeChip(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function onJobUrlInput() {
  S.job = emptyJob();
  checkReady();
}

/**
 * Import the job advert from the URL field. Resolves to true when a description was loaded.
 */
async function fetchUrl() {
  const url = document.getElementById('jobUrl').value.trim();
  if (!url) { toast('warn','Please enter a job listing URL first.'); return false; }

  const fetchButton = document.querySelector('.btn-fetch');
  const originalButtonHtml = fetchButton.innerHTML;
  fetchButton.disabled = true;
  fetchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching';
  toast('info','Fetching job description...');

  try {
    const data = await postJson(JOB_DESCRIPTION_FETCH_URL, { url });

    S.job = {
      url: data.url || url,
      title: data.title || '',
      company: data.company || '',
      location: data.location || '',
      applyUrl: data.applyUrl || '',
      emails: data.emails || []
    };

    const header = [data.title, data.company, data.location].filter(Boolean);
    const jobDesc = document.getElementById('jobDesc');
    jobDesc.value = (header.length ? header.join('\n') + '\n\n' : '') + data.description;
    document.getElementById('charPill').textContent = jobDesc.value.length;

    switchTab('paste');
    scheduleSkillMatch();
    checkReady();
    toast('success', applicationHint(data.application));
    return true;
  } catch (error) {
    toast('error', error.message || 'Could not fetch this job description.');
    return false;
  } finally {
    fetchButton.disabled = false;
    fetchButton.innerHTML = originalButtonHtml;
  }
}

function applicationHint(application) {
  switch (application?.method) {
    case 'email': return `Job fetched. Applications go by email to ${application.email}.`;
    case 'linkedin': return 'Job fetched. This job is applied for on LinkedIn.';
    case 'website': return 'Job fetched. This job is applied for on the company website.';
    default: return 'Job description fetched successfully!';
  }
}

/* Tone  */
function selectTone(el) {
  document.querySelectorAll('.tone-opt').forEach(o=>o.classList.remove('selected'));
  el.classList.add('selected');
  S.tone = el.dataset.tone;
}

/*  Ready check  */
function checkReady() {
  const jd = document.getElementById('jobDesc').value.trim();
  const url = document.getElementById('jobUrl').value.trim();
  S.jobReady = jd.length >= 50 || /^https?:\/\/\S+\.\S+/i.test(url);
  if (S.jobReady && S.file) {
    document.getElementById('jobBadge').className='block-badge badge-done';
    document.getElementById('jobBadge').textContent='Added';
    setStep(2,'done'); setStep(3,'active');
  }
  document.getElementById('genBtn').disabled = !(S.jobReady && S.file);
}

/*  Steps  */
function setStep(n, state) {
  const el = document.getElementById('ps'+n);
  el.className = 'pt-step' + (state ? ' '+state : '');
}

/*  Generate  */
async function runGenerate() {
  if (!S.file || !S.jobReady) return;

  if (document.getElementById('jobDesc').value.trim().length < 50 && !(await fetchUrl())) return;

  const btn = document.getElementById('genBtn');
  btn.classList.add('loading');
  btn.disabled = true;
  document.getElementById('genLabel').textContent = 'Optimising';
  setStatus('working','Processing...');
  showView('proc');

  const steps = ['pl0','pl1','pl2','pl3','pl4'];
  steps.forEach(id => {
    const step = document.getElementById(id);
    step.classList.remove('active', 'done');
    const icon = step.querySelector('i');
    if (icon) icon.className = icon.dataset.originalIcon || icon.className;
  });

  // Simulate step progress
  steps.forEach((id,i)=>{
    setTimeout(()=>{
      if(i>0){
        const prev=document.getElementById(steps[i-1]);
        prev.classList.remove('active');
        prev.classList.add('done');
        prev.querySelector('i').className='fas fa-check-circle';
      }
      document.getElementById(id).classList.add('active');
    }, i*400);
  });

  // Call Gemini API to tailor CV
  setTimeout(() => {
    tailorCvWithGemini(btn, steps);
  }, steps.length * 400);
}

async function tailorCvWithGemini(btn, steps) {
  try {
    const iframe = document.getElementById('cvIframe');
    const jobDescription = document.getElementById('jobDesc').value.trim();
    
    if (!S.file) {
      throw new Error('Please upload the CV you want to tailor.');
    }

    if (!jobDescription || jobDescription.length < 50) {
      throw new Error('Job description too short. Please provide at least 50 characters.');
    }

    const formData = new FormData();
    formData.append('jobDescription', jobDescription);
    formData.append('template', S.templateFile || 'default.json');
    formData.append('tone', S.tone || 'professional');
    formData.append('cv', S.file);
    Object.entries(S.job).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach(item => formData.append(`job[${key}][]`, item));
      else if (value) formData.append(`job[${key}]`, value);
    });

    const result = await postForm(CV_TAILOR_URL, formData);

    // Load the generated result. The system JSON template remains untouched.
    if (result.data.result && result.data.previewUrl) {
      S.tailoredResult = result.data.result;
      S.tailoredPreviewUrl = appUrl(result.data.previewUrl);
      S.tailoredDownloadUrl = appUrl(result.data.downloadUrl);
      S.application = result.data.application || null;
      S.applyPrepared = null;
      S.applyRendered = false;
      iframe.src = `${S.tailoredPreviewUrl}&t=${Date.now()}`;
      if (result.data.match) {
        S.matchScore = Number.isInteger(result.data.match.score) ? result.data.match.score : null;
        S.matchedSkills = result.data.match.matched || [];
        S.missingSkills = result.data.match.missing || [];
      }
    } else {
      throw new Error('The tailored CV could not be prepared for preview.');
    }
    
    // Mark step complete
    document.getElementById(steps[steps.length-1]).classList.remove('active');
    document.getElementById(steps[steps.length-1]).classList.add('done');

    scaleCv();
    showView('cv');
    setStatus('ready','CV Tailored & Ready');

    btn.classList.remove('loading');
    btn.innerHTML='<i class="fas fa-sync-alt gen-icon"></i><div class="spin"></div><span id="genLabel">Re-Optimise</span>';
    btn.disabled = false;

    S.generated = true;
    prepareApplication().catch(() => {});
    setStep(3,'done');
    ['btnDownload','btnShare','btnApply'].forEach(id=>document.getElementById(id).disabled=false);
    showPhotoTool();
    showMatchScore(S.matchScore ?? 0, S.matchedSkills, S.missingSkills, 'Tailored CV Match Score');
    showFactCheck(result.data.factCheck);

  } catch (error) {
    // Mark last step as failed
    document.getElementById('pl4').classList.remove('active');
    document.getElementById('pl4').classList.add('done');
    document.getElementById('pl4').querySelector('i').className='fas fa-exclamation-circle';
    
    btn.classList.remove('loading');
    btn.disabled = false;
    
    showView('empty');
    setStatus('error', 'Tailoring Error');
    toast('error', error.message || 'Failed to tailor CV. Please try again.');
    document.getElementById('cvIframe').src = 'about:blank';
  }
}

/* CV Preview scaling  */
function scaleCv(overrideZoom) {
  const zoom = overrideZoom !== undefined ? overrideZoom : S.zoom;
  S.zoom = Math.max(40, Math.min(100, zoom));
  document.getElementById('zoomVal').textContent = S.zoom+'%';

  const scale   = S.zoom / 100;
  const cvW     = 794;
  const cvH     = 1120;
  const vpW     = Math.round(cvW * scale);
  const vpH     = Math.round(cvH * scale);

  const vp      = document.getElementById('cvViewport');
  const scaler  = document.getElementById('cvScaler');
  const iframe  = document.getElementById('cvIframe');

  iframe.style.width  = cvW+'px';
  iframe.style.height = cvH+'px';
  scaler.style.width  = cvW+'px';
  scaler.style.transform = `scale(${scale})`;
  scaler.style.transformOrigin = 'top left';
  vp.style.width  = vpW+'px';
  vp.style.height = vpH+'px';
}

function changeZoom(delta) { scaleCv(S.zoom + delta); }
function resetZoom() {
  const body = document.getElementById('previewBody');
  const available = body.clientWidth - 48;
  const best = Math.floor((available / 794) * 100);
  scaleCv(Math.min(best, 100));
}

/*  Match score  */
function showMatchScore(score, matched, missing, label = 'Job Match Score') {
  score = Math.max(0, Math.min(100, Number(score) || 0));
  document.getElementById('matchCard').classList.add('show');
  document.getElementById('matchLabelText').textContent = label;
  document.getElementById('matchPct').textContent = score+'%';
  document.getElementById('matchFill').style.width = '0%';
  setTimeout(()=>{ document.getElementById('matchFill').style.width = score+'%'; },100);
  const chips = document.getElementById('matchChips');
  chips.innerHTML='';
  matched.forEach(w=>{
    const c=document.createElement('span');
    c.className='kw-chip hit';
    c.innerHTML=`<i class="fas fa-check" style="font-size:.55rem;margin-right:3px"></i>${escapeChip(w)}`;
    chips.appendChild(c);
  });
  missing.forEach(w=>{
    const c=document.createElement('span');
    c.className='kw-chip miss';
    c.innerHTML=`<i class="fas fa-minus" style="font-size:.55rem;margin-right:3px"></i>${escapeChip(w)}`;
    chips.appendChild(c);
  });
}

function hideMatchScore() {
  document.getElementById('matchCard').classList.remove('show');
  document.getElementById('matchLabelText').textContent = 'Job Match Score';
  document.getElementById('matchPct').textContent = '--';
  document.getElementById('matchFill').style.width = '0%';
  document.getElementById('matchChips').innerHTML = '';
}

/*  Views  */
function showView(which) {
  document.getElementById('emptyState').style.display   = which==='empty'  ? '' : 'none';
  document.getElementById('procState').classList.toggle ('show', which==='proc');
  document.getElementById('cvFrameOuter').classList.toggle('show', which==='cv');
}

function setStatus(type, text) {
  const el = document.getElementById('statusPill');
  el.className = 'status-pill sp-'+type;
  el.innerHTML = '<span class="sp-dot"></span><span></span>';
  el.lastElementChild.textContent = text;
}

/*  Toolbar actions  */
function toggleProfilePhoto(el) {
  S.profilePhotoEnabled = el.checked;
  document.getElementById('photoPickBtn').disabled = !S.profilePhotoEnabled;

  if (S.profilePhotoEnabled) {
    pickProfilePhoto();
    return;
  }

  updateTailoredPhoto(false);
}

function pickProfilePhoto() {
  if (!S.generated || !S.profilePhotoEnabled) return;
  document.getElementById('profilePhotoInput').click();
}

function onProfilePhotoSelect(event) {
  const file = event.target.files[0];
  if (!file) return;

  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
    toast('error','Please choose a JPG, PNG, or WEBP picture.');
    event.target.value = '';
    return;
  }

  updateTailoredPhoto(true, file);
}

async function updateTailoredPhoto(includePhoto, file = null) {
  if (!S.generated || !S.tailoredResult) return;

  const pick = document.getElementById('photoPickBtn');
  pick.disabled = true;

  const formData = new FormData();
  formData.append('result', S.tailoredResult);
  formData.append('includePhoto', includePhoto ? '1' : '0');
  if (file) formData.append('photo', file);

  try {
    const data = await postForm(CV_PHOTO_URL, formData);

    S.tailoredPreviewUrl = appUrl(data.previewUrl);
    S.tailoredDownloadUrl = appUrl(data.downloadUrl);
    document.getElementById('cvIframe').src = `${S.tailoredPreviewUrl}&t=${Date.now()}`;
    toast('success', includePhoto ? 'Profile picture added.' : 'Profile picture removed.');
  } catch (error) {
    toast('error', error.message || 'Could not update the profile picture.');
    if (includePhoto) {
      document.getElementById('photoToggle').checked = false;
      S.profilePhotoEnabled = false;
    }
  } finally {
    pick.disabled = !S.profilePhotoEnabled;
  }
}

async function selectCvTheme(button) {
  if (!S.generated || !S.tailoredResult) return;

  const theme = button.dataset.theme || 'purple';
  S.cvTheme = theme;
  document.querySelectorAll('.theme-swatch').forEach(btn=>btn.classList.toggle('active', btn === button));

  const formData = new FormData();
  formData.append('result', S.tailoredResult);
  formData.append('theme', theme);

  try {
    const data = await postForm(CV_THEME_URL, formData);

    S.tailoredPreviewUrl = appUrl(data.previewUrl);
    S.tailoredDownloadUrl = appUrl(data.downloadUrl);
    document.getElementById('cvIframe').src = `${S.tailoredPreviewUrl}&t=${Date.now()}`;
    toast('success','CV color updated.');
  } catch (error) {
    toast('error', error.message || 'Could not update CV color.');
  }
}

async function downloadCV() {
  if (!S.generated || !S.tailoredDownloadUrl) return false;

  const btn = document.getElementById('btnDownload');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing PDF';

  try {
    const response = await fetch(S.tailoredDownloadUrl);
    if (!response.ok) throw new Error('The CV could not be downloaded.');

    const disposition = response.headers.get('Content-Disposition') || '';
    const fileName = (disposition.match(/filename="([^"]+)"/) || [])[1] || 'Tailored-CV.pdf';
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10000);
    toast('success', `Downloaded ${fileName}`);
    return true;
  } catch (error) {
    toast('error', error.message || 'The CV could not be downloaded.');
    return false;
  } finally {
    btn.disabled = false;
    btn.innerHTML = original;
  }
}

async function shareCV() {
  if (!S.generated || !S.tailoredPreviewUrl) return;

  const shareUrl = new URL(S.tailoredPreviewUrl, window.location.origin).href;

  try {
    if (navigator.share) {
      await navigator.share({ title: 'Tailored CV', url: shareUrl });
      return;
    }
    await navigator.clipboard.writeText(shareUrl);
    toast('success','CV link copied to clipboard.');
  } catch (error) {
    if (error.name !== 'AbortError') toast('error','Could not share this CV.');
  }
}

/*  Apply  */
async function openModal() {
  if (!S.generated || !S.tailoredResult) return;

  document.getElementById('applyModal').classList.add('show');
  if (S.applyPrepared?.result === S.tailoredResult && S.applyRendered) return;
  showSendError('');

  document.getElementById('applyLoading').style.display = '';
  document.getElementById('applyCards').style.display = 'none';
  document.getElementById('applyTitle').textContent = 'Apply for this job';
  document.getElementById('applySub').textContent = 'Your tailored CV is attached as a PDF.';

  try {
    await prepareApplication();
    renderApplyOptions();
    S.applyRendered = true;
  } catch (error) {
    closeModal();
    toast('error', error.message || 'Could not prepare the application.');
  }
}

/**
 * Detect the application method and draft the email once per tailored CV (started right after tailoring).
 */
function prepareApplication() {
  const result = S.tailoredResult;
  if (S.applyPromise?.result !== result) {
    const promise = postJson(APPLICATION_PREPARE_URL, { result, tone: S.tone })
      .then(data => { if (S.tailoredResult === result) S.applyPrepared = { ...data, result }; });
    promise.catch(() => { if (S.applyPromise?.promise === promise) S.applyPromise = null; });
    S.applyPromise = { result, promise };
  }
  return S.applyPromise.promise;
}

function renderApplyOptions() {
  const { application, job, draft, attachmentName } = S.applyPrepared;
  const role = [job.title, job.company].filter(Boolean).join(' at ');
  const onLinkedIn = application.method === 'linkedin';
  const byEmail = application.method === 'email';
  const hasLink = Boolean(application.applyUrl);

  document.getElementById('applyTitle').textContent = role ? `Apply: ${role}` : 'Apply for this job';
  document.getElementById('applySub').textContent = `Attachment: ${attachmentName}`;

  // Email option: always available; recommended when the job lists an application email.
  const emailCard = document.getElementById('applyEmailCard');
  emailCard.classList.toggle('recommended', byEmail);
  document.getElementById('emailBadge').style.display = byEmail ? '' : 'none';
  document.getElementById('emailDesc').textContent = byEmail
    ? `This job asks for applications by email (${application.email}). Review the message, then send it.`
    : 'No application email was found in the job description. Enter the recruiter\'s address to apply by email.';
  document.getElementById('recEmail').value = application.email || '';
  document.getElementById('recEmailOptions').innerHTML = application.emails
    .map(email => `<option value="${escapeChip(email)}"></option>`).join('');
  document.getElementById('emailSubject').value = draft.subject;
  document.getElementById('emailBody').value = draft.body;
  renderSenderLine();

  // Link option: LinkedIn or the company's application page.
  const linkCard = document.getElementById('applyLinkCard');
  linkCard.style.display = hasLink ? '' : 'none';
  linkCard.classList.toggle('recommended', !byEmail && hasLink);
  document.getElementById('linkIcon').className = onLinkedIn ? 'fab fa-linkedin' : 'fas fa-arrow-up-right-from-square';
  document.getElementById('linkTitle').textContent = onLinkedIn ? 'Apply on LinkedIn' : 'Apply on the company website';
  document.getElementById('linkDesc').textContent = onLinkedIn
    ? 'Opens the LinkedIn job and downloads your tailored PDF. Use Easy Apply (or the company link) and upload the PDF.'
    : 'Opens the application page and downloads your tailored PDF so you can upload it there.';

  // Recommended option first.
  const cards = document.getElementById('applyCards');
  cards.prepend(byEmail || !hasLink ? emailCard : linkCard);

  document.getElementById('applyLoading').style.display = 'none';
  cards.style.display = '';
}

function renderSenderLine() {
  const line = document.getElementById('senderLine');
  const sendBtn = document.getElementById('btnSendEmail');
  const { sender } = S.applyPrepared;

  if (S.gmailEmail) {
    line.innerHTML = '<span>Sending from <strong></strong></span><button class="link-btn" type="button" onclick="disconnectGmail()">Use another account</button>';
    line.querySelector('strong').textContent = S.gmailEmail;
    sendBtn.disabled = false;
  } else if (sender.gmailConfigured) {
    line.innerHTML = '<span>Connect Gmail to send this from your own mailbox.</span><button class="link-btn" type="button" onclick="connectGmail()"><i class="fas fa-envelope"></i> Connect Gmail</button>';
    sendBtn.disabled = !sender.smtpAvailable;
  } else if (sender.smtpAvailable) {
    line.innerHTML = '<span>Sent by the CVCraft mail server; replies go to the email on your CV.</span>';
    sendBtn.disabled = false;
  } else {
    line.innerHTML = '<span>Email sending is not set up yet: add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env.</span>';
    sendBtn.disabled = true;
  }
}

async function sendApplicationEmail() {
  const to = document.getElementById('recEmail').value.trim();
  const subject = document.getElementById('emailSubject').value.trim();
  const body = document.getElementById('emailBody').value.trim();

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) { toast('warn','Enter a valid recruiter email address.'); return; }
  if (!subject || body.length < 20) { toast('warn','Add a subject and a message before sending.'); return; }

  const btn = document.getElementById('btnSendEmail');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> &nbsp;Sending...';
  showSendError('');

  try {
    const data = await postJson(APPLICATION_EMAIL_URL, { result: S.tailoredResult, to, subject, body });
    closeModal();
    toast('success', data.message);
  } catch (error) {
    // Keep the reason on screen next to the button; a toast disappears before it can be read.
    showSendError(error.message || 'The application email could not be sent.');
  } finally {
    btn.innerHTML = original;
    renderSenderLine();
  }
}

/**
 * Tell the user what the fact check did: every claim the AI added that is not in their CV was removed.
 */
function showFactCheck(factCheck) {
  const removed = factCheck?.removed || [];
  if (removed.length) console.info('[CVCraft] Fact check removed or reworded:', removed);

  if (!factCheck?.verified) {
    toast('warn', 'CV tailored, but the fact check could not finish. Read it carefully before sending.', 8000);
  } else if (removed.length) {
    toast('success', `CV tailored. Fact check removed ${removed.length} detail${removed.length === 1 ? '' : 's'} that ${removed.length === 1 ? 'is' : 'are'} not in your original CV.`, 6000);
  } else {
    toast('success', 'CV tailored and fact-checked against your original CV.');
  }
}

function showSendError(message) {
  const box = document.getElementById('sendError');
  document.getElementById('sendErrorText').textContent = message;
  box.hidden = !message;
}

async function applyViaLink() {
  const url = S.applyPrepared?.application?.applyUrl;
  if (!url) return;

  // Open the tab inside the click handler so the browser does not block it as a pop-up.
  const tab = window.open(url, '_blank', 'noopener');
  if (!tab) toast('warn','Allow pop-ups to open the job page.');
  closeModal();
  if (await downloadCV()) toast('info','Upload the downloaded PDF on the application page.');
}

function closeModal() {
  document.getElementById('applyModal').classList.remove('show');
}

/*  Utils  */
function formatBytes(b) {
  if(b<1024)return b+' B';
  if(b<1048576)return(b/1024).toFixed(1)+' KB';
  return(b/1048576).toFixed(1)+' MB';
}

/*  Keyboard shortcuts  */
document.addEventListener('keydown', e=>{
  if ((e.ctrlKey||e.metaKey) && e.key==='Enter') {
    e.preventDefault();
    if (!document.getElementById('genBtn').disabled) runGenerate();
  }
  if (e.key==='Escape') closeModal();
});

/*  Navbar scroll shadow  */
window.addEventListener('scroll',()=>{
  document.getElementById('navbar').classList.toggle('scrolled', window.scrollY>4);
});

/*  Close modal on backdrop click  */
document.getElementById('applyModal').addEventListener('click', e=>{
  if (e.target===document.getElementById('applyModal')) closeModal();
});

/*  Init  */
showView('empty');
</script>
</body>
</html>
