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
  darkMode: document.documentElement.classList.contains('dark'),
  skillTimer: null,
  skillRequestId: 0,
  matchScore: null,
  matchedSkills: [],
  missingSkills: [],
  profilePhotoEnabled: false,
  cvTheme: 'purple',
  initialScore: null,
  abort: null,
  timer: null,
  editing: null,
  photoDataUrl: '',
  themeQueue: Promise.resolve(),
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
const CV_FACT_CHECK_URL = appUrl(@json(route('cv.fact-check', [], false)));
const CV_CONTENT_URL = appUrl(@json(route('cv.content', [], false)));
const CV_CONTENT_UPDATE_URL = appUrl(@json(route('cv.content.update', [], false)));
const CSRF_REFRESH_URL = appUrl(@json(route('csrf.refresh', [], false)));
let csrfToken = @json(csrf_token());

/*  Theme Toggle  */
function toggleTheme() {
  const dark = !document.documentElement.classList.contains('dark');
  document.documentElement.classList.toggle('dark', dark);
  S.darkMode = dark;
  try { localStorage.setItem('cvcraft-theme', dark ? 'dark' : 'light'); } catch (e) {}
}


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
  btn.title = S.gmailEmail ? `Connected as ${S.gmailEmail}. Click to disconnect.` : 'Connect Gmail to send applications from your own address';
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

async function postForm(url, formData, signal) {
  return requestJson(url, { method: 'POST', body: formData, signal });
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
  const allowed = ['pdf','doc','docx','html','htm','txt'];
  if (!allowed.includes(ext)) { toast('error','Unsupported file. Use PDF, DOC, DOCX, HTML, or TXT.'); return; }
  S.file = f;
  S.generated = false;
  S.tailoredResult = null;
  S.tailoredPreviewUrl = null;
  S.tailoredDownloadUrl = null;
  S.applyPrepared = null;
  resetPhotoTool();
  hideReview();
  S.initialScore = null;
  ['btnDownload','btnShare','btnApply'].forEach(id=>document.getElementById(id).disabled=true);

  const icons = {pdf:'fa-file-pdf',doc:'fa-file-word',docx:'fa-file-word',html:'fa-file-code',htm:'fa-file-code',txt:'fa-file-lines'};
  document.getElementById('fcTypeIcon').className = `fas ${icons[ext]||'fa-file-lines'}`;
  document.getElementById('fcName').textContent = f.name;
  document.getElementById('fcMeta').textContent = `${ext.toUpperCase()} · ${formatBytes(f.size)}`;
  document.getElementById('uploadZone').style.display = 'none';
  document.getElementById('fileCard').classList.add('show');
  document.getElementById('uploadBadge').className = 'badge badge-done';
  document.getElementById('uploadBadge').textContent = 'Uploaded';
  setStep(1,'done'); setStep(2,'active');
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
  hideReview();
  S.initialScore = null;
  document.getElementById('uploadZone').style.display = '';
  document.getElementById('fileCard').classList.remove('show');
  document.getElementById('cvInput').value = '';
  document.getElementById('uploadBadge').className = 'badge badge-req';
  document.getElementById('uploadBadge').textContent = 'Required';
  ['btnDownload','btnShare','btnApply'].forEach(id=>document.getElementById(id).disabled=true);
  setStep(1,'active'); setStep(2,''); setStep(3,'');
  setPreviewSrc(null);
  clearSkillChips();
  showView('empty');
  checkReady();
}

function resetPhotoTool() {
  S.profilePhotoEnabled = false;
  S.cvTheme = 'purple';
  S.photoDataUrl = '';
  document.getElementById('photoToggle').checked = false;
  document.getElementById('photoPickBtn').disabled = true;
  document.getElementById('profilePhotoInput').value = '';
  document.getElementById('designBtn').disabled = true;
  document.getElementById('editBtn').disabled = true;
  closeDesign();
  document.querySelectorAll('.theme-swatch').forEach(btn=>btn.classList.toggle('active', btn.dataset.theme === 'purple'));
}

function showPhotoTool() {
  document.getElementById('designBtn').disabled = false;
  document.getElementById('editBtn').disabled = false;
}

/*  Tabs  */
function switchTab(tab) {
  ['url','paste'].forEach(t=>{
    const name = t.charAt(0).toUpperCase()+t.slice(1);
    const button = document.getElementById('tab'+name);
    button.classList.toggle('active', t===tab);
    button.setAttribute('aria-selected', String(t===tab));
    document.getElementById('tc'+name).classList.toggle('active', t===tab);
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

function renderSkillLoading(text, busy = true) {
  document.getElementById('matchCard').classList.add('show');
  document.getElementById('matchLabelText').textContent = 'Skills match';
  document.getElementById('matchPct').textContent = '--';
  document.getElementById('matchFill').style.width = '0%';
  document.getElementById('matchLegend').hidden = true;
  const chips = document.getElementById('matchChips');
  chips.innerHTML = '';
  const c = document.createElement('span');
  c.className = 'kw-chip';
  c.innerHTML = `${busy ? '<span class="spin" style="width:10px;height:10px;border-width:1.5px"></span>' : '<i class="fas fa-circle-info" aria-hidden="true"></i>'}${escapeChip(text)}`;
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
    renderSkillLoading(error.message || 'Could not compare CV skills', false);
    return;
  }

  try {
    if (requestId !== S.skillRequestId) return;

    S.matchScore = Number.isInteger(data.score) ? data.score : null;
    S.matchedSkills = data.matched || [];
    S.missingSkills = data.missing || [];

    if (S.matchScore !== null) {
      if (!S.generated) S.initialScore = S.matchScore;
      showMatchScore(S.matchScore, S.matchedSkills, S.missingSkills, 'Skills match: your CV');
    } else {
      hideMatchScore();
    }
  } catch (error) {
    if (requestId === S.skillRequestId) {
      renderSkillLoading('Skill comparison unavailable', false);
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

  const fetchButton = document.getElementById('fetchBtn');
  const originalButtonHtml = fetchButton.innerHTML;
  fetchButton.disabled = true;
  fetchButton.innerHTML = '<span class="spin" aria-hidden="true"></span> Importing';

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
  document.querySelectorAll('.tone-opt').forEach(o=>{
    o.classList.toggle('selected', o === el);
    o.setAttribute('aria-checked', String(o === el));
  });
  S.tone = el.dataset.tone;
}

/*  Ready check  */
function checkReady() {
  const jd = document.getElementById('jobDesc').value.trim();
  const url = document.getElementById('jobUrl').value.trim();
  S.jobReady = jd.length >= 50 || /^https?:\/\/\S+\.\S+/i.test(url);

  const badge = document.getElementById('jobBadge');
  badge.className = S.jobReady ? 'badge badge-done' : 'badge badge-req';
  badge.textContent = S.jobReady ? 'Added' : 'Required';

  if (S.file) setStep(2, S.jobReady ? 'done' : 'active');
  if (S.file && S.jobReady && !S.generated) setStep(3, 'active');
  document.getElementById('esStep1').classList.toggle('done', Boolean(S.file));
  document.getElementById('esStep2').classList.toggle('done', S.jobReady);
  document.getElementById('genBtn').disabled = !(S.jobReady && S.file);
}

/*  Steps  */
function setStep(n, state) {
  const el = document.getElementById('ps'+n);
  el.classList.remove('active', 'done');
  if (state) el.classList.add(state);
}

/*  Generate  */
async function runGenerate() {
  if (!S.file || !S.jobReady || S.abort) return;

  if (document.getElementById('jobDesc').value.trim().length < 50 && !(await fetchUrl())) return;

  const btn = document.getElementById('genBtn');
  btn.classList.add('loading');
  btn.disabled = true;
  document.getElementById('genLabel').textContent = 'Tailoring…';
  setStatus('working', 'Tailoring');
  S.viewBeforeRun = S.generated ? 'cv' : 'empty';
  showView('proc');

  S.abort = new AbortController();
  const progress = createProgress();
  try {
    await runTailoring(btn, S.abort.signal, progress);
  } finally {
    progress.stop();
    S.abort = null;
  }
}

/*
 * Progress across the real stages of tailoring. Each stage owns a share of the bar; within a stage the bar
 * creeps forward on an easing curve based on its usual duration, and it only fills that share when the
 * stage has actually finished. So the bar never jumps ahead of the work and never stalls completely.
 */
const STAGES = [
  { id: 'stUpload', weight: 6, expected: 2 },
  { id: 'stTailor', weight: 56, expected: 14 },
  { id: 'stCheck', weight: 28, expected: 8 },
  { id: 'stLayout', weight: 10, expected: 2 },
];

function createProgress() {
  const started = Date.now();
  const state = { index: -1, base: 0, stageStart: 0, ratio: null };
  const fill = document.getElementById('procFill');
  const bar = document.getElementById('procBar');
  const pct = document.getElementById('procPct');
  const timer = document.getElementById('procTimer');
  const note = document.getElementById('procNote');

  note.textContent = 'Usually 15–40 seconds';
  STAGES.forEach(stage => {
    const el = document.getElementById(stage.id);
    el.classList.remove('active', 'done');
    el.querySelector('.stage-time').textContent = '';
  });

  const render = () => {
    const stage = STAGES[state.index];
    let value = state.base;
    if (stage) {
      const seconds = (Date.now() - state.stageStart) / 1000;
      const fraction = state.ratio !== null ? state.ratio : 1 - Math.exp(-seconds / stage.expected);
      value += stage.weight * Math.min(fraction, 0.95);
    }
    const rounded = Math.min(100, Math.floor(value));
    fill.style.width = `${value}%`;
    pct.textContent = `${rounded}%`;
    bar.setAttribute('aria-valuenow', String(rounded));

    const elapsed = Math.floor((Date.now() - started) / 1000);
    timer.textContent = `${Math.floor(elapsed / 60)}:${String(elapsed % 60).padStart(2, '0')}`;
    if (elapsed >= 45) note.textContent = 'The AI service is busy right now. Still working…';
  };
  const interval = setInterval(render, 200);
  render();

  return {
    get index() { return state.index; },
    start(index) {
      state.index = index;
      state.stageStart = Date.now();
      state.ratio = null;
      document.getElementById(STAGES[index].id).classList.add('active');
      render();
    },
    ratio(value) { state.ratio = Math.max(0, Math.min(1, value)); },
    done(index) {
      const el = document.getElementById(STAGES[index].id);
      el.classList.remove('active');
      el.classList.add('done');
      el.querySelector('.stage-time').textContent = `${Math.max(0.1, (Date.now() - state.stageStart) / 1000).toFixed(1)}s`;
      state.base += STAGES[index].weight;
      state.index = -1;
      render();
    },
    stop() { clearInterval(interval); },
  };
}

/**
 * POST a form with real upload progress (fetch cannot report it). Same error handling as requestJson.
 */
function sendForm(url, formData, { signal, onUploadProgress, onUploaded } = {}, retried = false) {
  return new Promise((resolve, reject) => {
    if (signal?.aborted) return reject(new DOMException('Cancelled', 'AbortError'));

    const xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
    xhr.upload.onprogress = event => { if (event.lengthComputable) onUploadProgress?.(event.loaded / event.total); };
    xhr.upload.onload = () => onUploaded?.();

    xhr.onload = async () => {
      // 419 = the session (and its CSRF token) expired while the page was open: renew it and retry once.
      if (xhr.status === 419 && !retried) {
        try {
          await refreshCsrfToken();
          resolve(await sendForm(url, formData, { signal, onUploadProgress, onUploaded }, true));
        } catch (error) {
          reject(error);
        }
        return;
      }

      let data = {};
      try { data = JSON.parse(xhr.responseText); } catch (e) {}
      if (xhr.status >= 200 && xhr.status < 300) return resolve(data);

      const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
      const error = new Error(firstError || data.message || `Request failed (${xhr.status}).`);
      error.status = xhr.status;
      reject(error);
    };
    xhr.onerror = () => reject(new Error('Network error. Check your connection and try again.'));
    xhr.onabort = () => reject(new DOMException('Cancelled', 'AbortError'));
    signal?.addEventListener('abort', () => xhr.abort(), { once: true });
    xhr.send(formData);
  });
}

async function runTailoring(btn, signal, progress) {
  const resetButton = () => {
    btn.classList.remove('loading');
    btn.disabled = false;
    document.getElementById('genLabel').textContent = S.generated ? 'Tailor again' : 'Tailor my CV';
  };
  const previousPreview = S.generated ? S.tailoredPreviewUrl : null;

  try {
    const jobDescription = document.getElementById('jobDesc').value.trim();
    if (!S.file) throw new Error('Please upload the CV you want to tailor.');
    if (jobDescription.length < 50) throw new Error('The job description is too short. Please add at least 50 characters.');

    const formData = new FormData();
    formData.append('jobDescription', jobDescription);
    formData.append('template', S.templateFile || 'default.json');
    formData.append('tone', S.tone || 'professional');
    formData.append('cv', S.file);
    Object.entries(S.job).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach(item => formData.append(`job[${key}][]`, item));
      else if (value) formData.append(`job[${key}]`, value);
    });

    // Stage 1 + 2: upload, then the AI rewrite (the request returns when tailoring is done).
    progress.start(0);
    const toRewrite = () => { if (progress.index === 0) { progress.done(0); progress.start(1); } };
    const tailored = await sendForm(CV_TAILOR_URL, formData, {
      signal,
      onUploadProgress: ratio => { if (progress.index === 0) progress.ratio(ratio); },
      onUploaded: toRewrite,
    });
    toRewrite();
    progress.done(1);

    const data = tailored.data || {};
    if (!data.result || !data.previewUrl) throw new Error('The tailored CV could not be prepared for preview.');

    // Stage 3: the AI fact check. If it cannot run, the CV is still shown, marked "please review".
    progress.start(2);
    let check;
    try {
      check = await requestJson(CV_FACT_CHECK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ result: data.result }),
        signal,
      });
    } catch (error) {
      if (error.name === 'AbortError') throw error;
      check = { factCheck: { verified: false, removed: [] } };
    }
    progress.done(2);

    // Stage 4: render the CV page.
    progress.start(3);
    setPreviewSrc(appUrl(data.previewUrl));
    await waitForPreview(20000, signal);
    progress.done(3);
    await new Promise(resolve => setTimeout(resolve, 300));

    const match = check.match || data.match;
    S.tailoredResult = data.result;
    S.tailoredPreviewUrl = appUrl(data.previewUrl);
    S.tailoredDownloadUrl = appUrl(data.downloadUrl);
    S.application = data.application || null;
    S.applyPrepared = null;
    S.applyRendered = false;
    S.applyPromise = null;
    if (match) {
      S.matchScore = Number.isInteger(match.score) ? match.score : null;
      S.matchedSkills = match.matched || [];
      S.missingSkills = match.missing || [];
    }
    resetPhotoTool();

    S.generated = true;
    resetButton();
    showView('cv');
    window.innerWidth < 900 ? resetZoom() : measurePreview();
    setStatus('ready', 'Ready');
    setStep(3, 'done');
    document.getElementById('esStep3').classList.add('done');
    ['btnDownload','btnShare','btnApply'].forEach(id=>document.getElementById(id).disabled=false);
    showPhotoTool();
    showMatchScore(S.matchScore ?? 0, S.matchedSkills, S.missingSkills, 'Skills match: tailored CV');
    showFactCheck({
      verified: Boolean(check.factCheck?.verified),
      removed: [...(data.removed || []), ...(check.factCheck?.removed || [])],
    });
    showFit(data.fit);
    // On phones the preview sits below the form: bring the finished CV into view.
    if (window.innerWidth < 900) document.querySelector('.stage').scrollIntoView({ behavior: 'smooth', block: 'start' });
    prepareApplication().catch(() => {});
  } catch (error) {
    resetButton();
    if (previousPreview && document.getElementById('cvIframe').src.indexOf(previousPreview) !== 0) setPreviewSrc(previousPreview);
    showView(S.viewBeforeRun || 'empty');
    if (error.name === 'AbortError') {
      setStatus(S.generated ? 'ready' : 'idle', S.generated ? 'Ready' : 'Waiting');
      toast('info', 'Tailoring cancelled.');
      return;
    }
    setStatus('error', 'Failed');
    toast('error', error.message || 'Could not tailor the CV. Please try again.', 7000);
  }
}

function cancelGenerate() {
  if (S.abort) S.abort.abort();
}


/* CV Preview loading  */
// Show a loading state until the CV page has rendered, then size the frame to the CV's real length
// (a two-page CV must not be cut off at one page).
function setPreviewSrc(url) {
  const iframe = document.getElementById('cvIframe');
  const viewport = document.getElementById('cvViewport');
  viewport.classList.toggle('loading', Boolean(url));
  iframe.src = url ? `${url}&t=${Date.now()}` : 'about:blank';
}

/** Resolves when the preview page has loaded (or after a timeout), rejects when cancelled. */
function waitForPreview(timeoutMs, signal) {
  return new Promise((resolve, reject) => {
    const iframe = document.getElementById('cvIframe');
    const done = () => { clearTimeout(timer); iframe.removeEventListener('load', done); resolve(); };
    const timer = setTimeout(done, timeoutMs);
    iframe.addEventListener('load', done);
    signal?.addEventListener('abort', () => { clearTimeout(timer); iframe.removeEventListener('load', done); reject(new DOMException('Cancelled', 'AbortError')); }, { once: true });
  });
}

/** Size the frame to the CV's real length once it is visible (a two-page CV must not be cut off). */
function measurePreview() {
  try {
    const height = document.getElementById('cvIframe').contentDocument.documentElement.scrollHeight;
    if (height > 0) S.cvHeight = height;
  } catch (e) {}
  scaleCv();
}

function cvHeight() {
  return Math.max(1120, S.cvHeight || 0);
}

document.getElementById('cvIframe').addEventListener('load', event => {
  const iframe = event.target;
  if (iframe.src === 'about:blank') return;
  document.getElementById('cvViewport').classList.remove('loading');
  measurePreview();
});

/* CV Preview scaling  */
function scaleCv(overrideZoom) {
  const zoom = overrideZoom !== undefined ? overrideZoom : S.zoom;
  S.zoom = Math.max(30, Math.min(100, zoom));
  document.getElementById('zoomVal').textContent = S.zoom+'%';

  const scale   = S.zoom / 100;
  const cvW     = 794;
  const vpW     = Math.round(cvW * scale);
  const vpH     = Math.round(cvHeight() * scale);

  const vp      = document.getElementById('cvViewport');
  const scaler  = document.getElementById('cvScaler');
  const iframe  = document.getElementById('cvIframe');

  iframe.style.width  = cvW+'px';
  iframe.style.height = cvHeight()+'px';
  scaler.style.width  = cvW+'px';
  scaler.style.transform = `scale(${scale})`;
  scaler.style.transformOrigin = 'top left';
  vp.style.width  = vpW+'px';
  vp.style.height = vpH+'px';
}

function changeZoom(delta) { scaleCv(S.zoom + delta); }
function resetZoom() {
  const body = document.getElementById('previewBody');
  const available = body.clientWidth - (window.innerWidth < 900 ? 24 : 64);
  const best = Math.floor((available / 794) * 100);
  scaleCv(Math.max(40, Math.min(best, 100)));
}

/*  Match score  */
function showMatchScore(score, matched, missing, label = 'Skills match') {
  score = Math.max(0, Math.min(100, Number(score) || 0));
  document.getElementById('matchCard').classList.add('show');
  document.getElementById('matchLabelText').textContent = label;
  document.getElementById('matchPct').textContent = score+'%';
  document.getElementById('matchFill').style.width = '0%';
  setTimeout(()=>{ document.getElementById('matchFill').style.width = score+'%'; },100);
  const chips = document.getElementById('matchChips');
  chips.innerHTML = '';
  const add = (word, cls, icon, title) => {
    const c = document.createElement('span');
    c.className = `kw-chip ${cls}`;
    c.title = title;
    c.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i>${escapeChip(word)}`;
    chips.appendChild(c);
  };
  matched.forEach(w => add(w, 'hit', 'fa-check', 'In your CV'));
  missing.forEach(w => add(w, 'miss', 'fa-minus', 'Not in your CV'));
  document.getElementById('matchLegend').hidden = !(matched.length || missing.length);
}

function hideMatchScore() {
  document.getElementById('matchCard').classList.remove('show');
  document.getElementById('matchLabelText').textContent = 'Skills match';
  document.getElementById('matchPct').textContent = '--';
  document.getElementById('matchFill').style.width = '0%';
  document.getElementById('matchChips').innerHTML = '';
  document.getElementById('matchLegend').hidden = true;
}

/*  Views  */
function showView(which) {
  document.getElementById('emptyState').style.display = which==='empty' ? '' : 'none';
  document.getElementById('procState').classList.toggle('show', which==='proc');
  document.getElementById('cvFrameOuter').classList.toggle('show', which==='cv');
  document.getElementById('cancelBtn').disabled = which !== 'proc';
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

  const previous = S.photoDataUrl;
  S.photoDataUrl = '';
  templateApi()?.setPhoto('');
  measurePreview();
  updateTailoredPhoto(false, null, previous);
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
  if (file.size > 3 * 1024 * 1024) {
    toast('error','Please choose a picture smaller than 3 MB.');
    event.target.value = '';
    return;
  }

  // Show the picture straight away; it is saved in the background.
  const reader = new FileReader();
  reader.onload = () => {
    const previous = S.photoDataUrl;
    S.photoDataUrl = reader.result;
    templateApi()?.setPhoto(reader.result);
    setTimeout(measurePreview, 50);
    updateTailoredPhoto(true, file, previous);
  };
  reader.readAsDataURL(file);
}

async function updateTailoredPhoto(includePhoto, file = null, previous = '') {
  if (!S.generated || !S.tailoredResult) return;

  const formData = new FormData();
  formData.append('result', S.tailoredResult);
  formData.append('includePhoto', includePhoto ? '1' : '0');
  if (file) formData.append('photo', file);

  try {
    await postForm(CV_PHOTO_URL, formData);
  } catch (error) {
    S.photoDataUrl = previous;
    templateApi()?.setPhoto(previous);
    setTimeout(measurePreview, 50);
    if (includePhoto && !previous) {
      document.getElementById('photoToggle').checked = false;
      S.profilePhotoEnabled = false;
      document.getElementById('photoPickBtn').disabled = true;
    }
    toast('error', error.message || 'Could not save the profile picture.');
  }
}

function templateApi() {
  try { return document.getElementById('cvIframe').contentWindow.cvTemplate || null; } catch (e) { return null; }
}

function swatchColors(button) {
  return { primary: button.dataset.primary, primary_light: button.dataset.light };
}

/** Recolour the preview immediately, then save in the background (saves run in click order). */
function selectCvTheme(button) {
  if (!S.generated || !S.tailoredResult) return;
  const theme = button.dataset.theme || 'purple';
  if (theme === S.cvTheme) return;

  const previous = document.querySelector('.theme-swatch.active');
  S.cvTheme = theme;
  document.querySelectorAll('.theme-swatch').forEach(btn => btn.classList.toggle('active', btn === button));
  templateApi()?.applyTheme(swatchColors(button));

  const result = S.tailoredResult;
  S.themeQueue = S.themeQueue.then(async () => {
    const formData = new FormData();
    formData.append('result', result);
    formData.append('theme', theme);
    try {
      await postForm(CV_THEME_URL, formData);
    } catch (error) {
      if (S.cvTheme !== theme || !previous) return;
      S.cvTheme = previous.dataset.theme;
      document.querySelectorAll('.theme-swatch').forEach(btn => btn.classList.toggle('active', btn === previous));
      templateApi()?.applyTheme(swatchColors(previous));
      toast('error', error.message || 'Could not save the CV colour.');
    }
  });
}

async function downloadCV() {
  if (!S.generated || !S.tailoredDownloadUrl) return false;

  const btn = document.getElementById('btnDownload');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spin" aria-hidden="true"></span><span class="btn-text">Preparing</span>';

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
    toast('success','Share link copied. Anyone with the link can view this CV for 30 days.', 5000);
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
  btn.innerHTML = '<span class="spin" aria-hidden="true"></span> Sending…';
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
  const verified = Boolean(factCheck?.verified);
  const card = document.getElementById('reviewCard');

  card.classList.remove('ok', 'warn');
  card.classList.add(verified ? 'ok' : 'warn', 'show');
  document.getElementById('reviewIcon').className = `fas ${verified ? 'fa-shield-halved' : 'fa-triangle-exclamation'}`;
  document.getElementById('reviewTitle').textContent = verified ? 'Fact-checked against your CV' : 'Please review before sending';
  document.getElementById('reviewSub').textContent = !verified
    ? 'The automatic fact check could not finish. Read the CV carefully and use Edit to fix anything.'
    : removed.length
      ? `${removed.length} detail${removed.length === 1 ? ' was' : 's were'} removed or reworded because ${removed.length === 1 ? 'it is' : 'they are'} not in your original CV.`
      : 'Everything in the tailored CV comes from your original CV.';

  const before = S.initialScore;
  document.getElementById('scoreBefore').textContent = Number.isInteger(before) ? `${before}%` : '--';
  document.getElementById('scoreAfter').textContent = Number.isInteger(S.matchScore) ? `${S.matchScore}%` : '--';

  const list = document.getElementById('reviewList');
  list.innerHTML = '';
  removed.forEach(item => {
    const li = document.createElement('li');
    li.textContent = item;
    list.appendChild(li);
  });
  const details = document.getElementById('reviewDetails');
  details.hidden = removed.length === 0;
  details.open = false;
  document.getElementById('reviewToggle').textContent = `See what was changed (${removed.length})`;

  toast(verified ? 'success' : 'warn', verified ? 'Your tailored CV is ready.' : 'CV ready, but please review it before sending.', verified ? 3200 : 7000);
}

/** Tell the user honestly how well their CV fits the job, and what it does not show. */
function showFit(fit) {
  const box = document.getElementById('fitNote');
  const level = fit?.level || 'strong';
  box.hidden = level === 'strong';
  if (box.hidden) return;

  const role = fit.target_role || 'this job';
  box.classList.toggle('partial', level === 'partial');
  document.getElementById('fitTitle').textContent = level === 'weak'
    ? `Career change: your CV is reframed for ${role}`
    : `Partial match for ${role}`;
  document.getElementById('fitText').textContent = fit.note || (level === 'weak'
    ? 'Your experience is from a different field, so the CV leads with the transferable skills this job needs.'
    : 'Some requirements of this job are not shown in your CV.');
  const missing = document.getElementById('fitMissing');
  missing.innerHTML = '';
  (fit.missing || []).forEach(item => {
    const chip = document.createElement('span');
    chip.className = 'kw-chip miss';
    chip.innerHTML = '<i class="fas fa-minus" aria-hidden="true"></i>';
    chip.append(document.createTextNode(item));
    missing.appendChild(chip);
  });
}

function hideReview() {
  document.getElementById('reviewCard').classList.remove('show');
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

function openInfo() {
  document.getElementById('infoModal').classList.add('show');
}

function closeInfo() {
  document.getElementById('infoModal').classList.remove('show');
}

/*  Design popover  */
function toggleDesign() {
  const pop = document.getElementById('designPopover');
  const open = !pop.classList.contains('show');
  pop.classList.toggle('show', open);
  document.getElementById('designBtn').setAttribute('aria-expanded', String(open));
}

function closeDesign() {
  document.getElementById('designPopover').classList.remove('show');
  document.getElementById('designBtn').setAttribute('aria-expanded', 'false');
}

document.addEventListener('click', e => {
  if (!e.target.closest('.popover-wrap')) closeDesign();
});

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
  if (e.key==='Escape') { closeModal(); closeInfo(); closeDesign(); if (S.editing) closeEditor(); }
});


/*  Close modals on backdrop click  */
['applyModal', 'infoModal'].forEach(id => {
  const backdrop = document.getElementById(id);
  backdrop.addEventListener('click', e => { if (e.target === backdrop) backdrop.classList.remove('show'); });
});

/*  CV editor  */
// [field, label, type ('text' | 'textarea' | 'lines' | 'csv'), hint, wide]
const EDITOR_SECTIONS = [
  { key: 'experience', title: 'Experience', item: 'Role', add: 'Add role', fields: [
    ['title', 'Job title'], ['company', 'Company'], ['period', 'Dates'], ['location', 'Location'],
    ['summary', 'Summary', 'textarea', '', true],
    ['highlights', 'Highlights', 'lines', 'One achievement per line', true],
  ] },
  { key: 'education', title: 'Education', item: 'Education', add: 'Add education', fields: [
    ['degree', 'Degree or qualification'], ['institution', 'School or university'], ['date', 'Dates'], ['location', 'Location'],
    ['details', 'Details', 'textarea', '', true],
  ] },
  { key: 'skills', title: 'Skills', item: 'Skill group', add: 'Add skill group', fields: [
    ['label', 'Group name'], ['items', 'Skills', 'csv', 'Separate skills with commas', true],
  ] },
  { key: 'projects', title: 'Projects', item: 'Project', add: 'Add project', fields: [
    ['title', 'Project name'], ['subtitle', 'Type or technologies'], ['period', 'Dates'], ['link', 'Link'],
    ['description', 'Description', 'textarea', '', true],
  ] },
  { key: 'certifications', title: 'Certifications', item: 'Certificate', add: 'Add certificate', fields: [
    ['title', 'Certificate'], ['type', 'Issuer'], ['detail', 'Details', 'text', '', true],
  ] },
  { key: 'interests', title: 'Interests', item: 'Interest', add: 'Add interest', fields: [
    ['description', 'Interest', 'textarea', '', true],
  ] },
];

function h(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  Object.entries(attrs).forEach(([key, value]) => {
    if (key === 'text') node.textContent = value;
    else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
    else if (value !== false && value !== null && value !== undefined) node.setAttribute(key, value === true ? '' : value);
  });
  [].concat(children).forEach(child => child && node.append(child));
  return node;
}

let editorFieldId = 0;
function editorField(label, value, type = 'text', hint = '', wide = false, data = {}) {
  const id = `ed-${++editorFieldId}`;
  const shown = type === 'lines' ? (value || []).join('\n') : type === 'csv' ? (value || []).join(', ') : (value || '');
  const control = (type === 'textarea' || type === 'lines')
    ? h('textarea', { class: 'textarea', id, rows: type === 'lines' ? 4 : 3, ...data })
    : h('input', { class: 'input', id, type: 'text', ...data });
  control.value = shown;
  return h('div', { class: `ed-field${wide ? ' full' : ''}` }, [
    h('label', { for: id, text: label }),
    control,
    hint ? h('div', { class: 'ed-help', text: hint }) : null,
  ]);
}

function editorItem(section, entry) {
  const item = h('div', { class: 'ed-item' });
  item._original = entry;
  const remove = h('button', { class: 'btn btn-ghost btn-sm', type: 'button', 'aria-label': `Remove ${section.item.toLowerCase()}`, onclick: () => item.remove() },
    [h('i', { class: 'fas fa-trash-can', 'aria-hidden': 'true' }), ' Remove']);
  const grid = h('div', { class: 'ed-grid' },
    section.fields.map(([field, label, type, hint, wide]) => editorField(label, entry[field], type, hint, wide, { 'data-field': field, 'data-type': type || 'text' })));
  item.append(h('div', { class: 'ed-item-head' }, [h('span', { text: section.item }), remove]), grid);
  return item;
}

function renderEditor(cv) {
  const form = document.getElementById('editBody');
  form.innerHTML = '';
  const personal = cv.personal || {};

  form.append(h('section', { class: 'ed-section' }, [
    h('div', { class: 'ed-section-head' }, h('h3', { text: 'Personal details' })),
    h('div', { class: 'ed-grid' }, [
      editorField('Full name', personal.name, 'text', '', false, { 'data-scalar': 'personal.name', required: true }),
      editorField('Headline', personal.headline, 'text', 'One line under your name, aimed at this job.', false, { 'data-scalar': 'personal.headline' }),
      editorField('Email', personal.email, 'text', '', false, { 'data-scalar': 'personal.email', type: 'email' }),
      editorField('Phone', personal.phone, 'text', '', false, { 'data-scalar': 'personal.phone' }),
      editorField('Location', personal.address, 'text', '', false, { 'data-scalar': 'personal.address' }),
      editorField('LinkedIn', personal.linkedin, 'text', '', false, { 'data-scalar': 'personal.linkedin' }),
      editorField('GitHub', personal.github, 'text', '', false, { 'data-scalar': 'personal.github' }),
      editorField('Website', personal.website, 'text', '', false, { 'data-scalar': 'personal.website' }),
      editorField('About', cv.about, 'textarea', 'A short summary at the top of your CV.', true, { 'data-scalar': 'about', rows: 4 }),
    ]),
  ]));

  EDITOR_SECTIONS.forEach(section => {
    const list = h('div', { 'data-section': section.key });
    (cv[section.key] || []).forEach(entry => list.append(editorItem(section, entry)));
    const add = h('button', { class: 'btn btn-ghost btn-sm', type: 'button', onclick: () => {
      const item = editorItem(section, {});
      list.append(item);
      item.querySelector('input, textarea')?.focus();
    } }, [h('i', { class: 'fas fa-plus', 'aria-hidden': 'true' }), ` ${section.add}`]);
    form.append(h('section', { class: 'ed-section' }, [
      h('div', { class: 'ed-section-head' }, [h('h3', { text: section.title }), add]),
      list,
    ]));
  });

  const languages = cv.languages || {};
  form.append(h('section', { class: 'ed-section' }, [
    h('div', { class: 'ed-section-head' }, h('h3', { text: 'Languages and more' })),
    h('div', { class: 'ed-grid' }, [
      editorField('Mother tongue', languages.mother, 'text', 'Leave empty if you prefer not to say.', false, { 'data-lang': 'mother' }),
      editorField('Other languages', (languages.other || []).map(l => l.name), 'csv', 'Separate with commas', false, { 'data-lang': 'other' }),
      editorField('Driving licence', cv.driving, 'text', '', false, { 'data-scalar': 'driving' }),
      editorField('Hobbies', cv.hobbies, 'text', '', false, { 'data-scalar': 'hobbies' }),
    ]),
  ]));
}

function parseEditorValue(value, type) {
  if (type === 'lines') return value.split('\n').map(v => v.trim()).filter(Boolean);
  if (type === 'csv') return value.split(',').map(v => v.trim()).filter(Boolean);
  return value.trim();
}

function collectEditor() {
  const form = document.getElementById('editBody');
  const cv = structuredClone(S.editing);

  form.querySelectorAll('[data-scalar]').forEach(input => {
    const path = input.dataset.scalar.split('.');
    let target = cv;
    path.slice(0, -1).forEach(key => { target = target[key] = target[key] || {}; });
    target[path[path.length - 1]] = input.value.trim();
  });

  EDITOR_SECTIONS.forEach(section => {
    cv[section.key] = [...form.querySelectorAll(`[data-section="${section.key}"] > .ed-item`)].map(item => {
      const entry = { ...(item._original || {}) };
      item.querySelectorAll('[data-field]').forEach(input => {
        entry[input.dataset.field] = parseEditorValue(input.value, input.dataset.type);
      });
      return entry;
    });
  });

  // Keep any language levels for languages that are still listed.
  const previous = Object.fromEntries((S.editing.languages?.other || []).map(l => [String(l.name).toLowerCase(), l]));
  const names = parseEditorValue(form.querySelector('[data-lang="other"]').value, 'csv');
  cv.languages = {
    mother: form.querySelector('[data-lang="mother"]').value.trim(),
    other: names.map(name => previous[name.toLowerCase()] || { name }),
  };

  return cv;
}

function showEditorError(message) {
  document.getElementById('editErrorText').textContent = message;
  document.getElementById('editError').hidden = !message;
}

async function openEditor() {
  if (!S.generated || !S.tailoredResult) return;
  const btn = document.getElementById('editBtn');
  btn.disabled = true;
  try {
    const data = await requestJson(`${CV_CONTENT_URL}?result=${encodeURIComponent(S.tailoredResult)}`, { method: 'GET' });
    S.editing = data.cv;
    renderEditor(data.cv);
    showEditorError('');
    document.getElementById('editBackdrop').classList.add('show');
    const drawer = document.getElementById('editDrawer');
    drawer.classList.add('show');
    drawer.setAttribute('aria-hidden', 'false');
    document.getElementById('editBody').scrollTop = 0;
    setTimeout(() => document.querySelector('#editBody input')?.focus(), 200);
  } catch (error) {
    toast('error', error.message || 'Could not open the editor.');
  } finally {
    btn.disabled = false;
  }
}

function closeEditor() {
  S.editing = null;
  document.getElementById('editBackdrop').classList.remove('show');
  const drawer = document.getElementById('editDrawer');
  drawer.classList.remove('show');
  drawer.setAttribute('aria-hidden', 'true');
  document.getElementById('editBtn').focus();
}

async function saveEditor() {
  if (!S.editing) return;
  const cv = collectEditor();
  if (!cv.personal?.name) {
    showEditorError('Your name cannot be empty.');
    document.querySelector('[data-scalar="personal.name"]')?.focus();
    return;
  }

  const btn = document.getElementById('editSave');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spin" aria-hidden="true"></span> Saving…';
  showEditorError('');

  try {
    const data = await postJson(CV_CONTENT_UPDATE_URL, { result: S.tailoredResult, cv });
    S.tailoredPreviewUrl = appUrl(data.previewUrl);
    S.tailoredDownloadUrl = appUrl(data.downloadUrl);
    setPreviewSrc(S.tailoredPreviewUrl);
    if (data.match) {
      S.matchScore = Number.isInteger(data.match.score) ? data.match.score : S.matchScore;
      S.matchedSkills = data.match.matched || [];
      S.missingSkills = data.match.missing || [];
      showMatchScore(S.matchScore ?? 0, S.matchedSkills, S.missingSkills, 'Skills match: tailored CV');
      document.getElementById('scoreAfter').textContent = Number.isInteger(S.matchScore) ? `${S.matchScore}%` : '--';
    }
    closeEditor();
    toast('success', 'Changes saved.');
  } catch (error) {
    showEditorError(error.message || 'Could not save your changes.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = original;
  }
}

/*  Init  */
showView('empty');
</script>
