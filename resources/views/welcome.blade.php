<!DOCTYPE html>
<html lang="en">
<head>
<title>CVCraft AI · Tailor your CV to any job</title>
@include('partials.head')
</head>
<body class="app">
@php($gmailEmail = data_get(session('gmail_token'), 'email'))
@php($supportEmail = config('app.support_email'))

<a class="sr-only" href="#main">Skip to content</a>
<div class="toast-stack" id="toastStack" role="status" aria-live="polite"></div>

<!-- ============================ HEADER ============================ -->
<header class="app-header">
  <a class="brand" href="{{ route('home') }}" aria-label="CVCraft AI home">
    <span class="brand-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h4"/></svg>
    </span>
    <span>CVCraft <span class="brand-ai">AI</span></span>
  </a>

  <div class="header-spacer"></div>

  <div class="header-actions">
    <button class="btn btn-ghost" type="button" onclick="openInfo()">
      <i class="fas fa-circle-question" aria-hidden="true"></i><span class="btn-label">How it works</span>
    </button>
    <button class="icon-btn theme-toggle" id="themeBtn" type="button" onclick="toggleTheme()" aria-label="Switch between light and dark mode" title="Light / dark mode">
      <i class="fas fa-moon" aria-hidden="true"></i><i class="fas fa-sun" aria-hidden="true"></i>
    </button>
    <span class="header-divider" aria-hidden="true"></span>
    <button class="btn btn-secondary gmail-btn{{ $gmailEmail ? ' active' : '' }}" id="gmailBtn" type="button" onclick="toggleGmail()"
            title="{{ $gmailEmail ? 'Connected as '.$gmailEmail.'. Click to disconnect.' : 'Connect Gmail to send applications from your own address' }}">
      <span class="gmail-icon" aria-hidden="true"><i class="fas fa-envelope"></i><span class="gmail-dot"></span></span>
      <span id="gmailLabel">{{ $gmailEmail ?: 'Connect Gmail' }}</span>
    </button>
  </div>
</header>

<!-- ============================ WORKSPACE ============================ -->
<main class="workspace" id="main">

  <!-- Left: steps -->
  <section class="sidebar" aria-label="Tailor your CV">
    <div class="sidebar-scroll">
      <div class="sidebar-intro">
        <h1>Tailor your CV to a job</h1>
        <p>Upload your CV, add the job, and get a version that puts your most relevant experience first.</p>
        <div class="trust-line"><i class="fas fa-shield-halved" aria-hidden="true"></i> Every line is checked against your original CV. Nothing is invented.</div>
      </div>

      <!-- Review (after tailoring) -->
      <section class="review-card" id="reviewCard" aria-live="polite">
        <div class="review-head">
          <div class="review-icon"><i class="fas fa-shield-halved" id="reviewIcon" aria-hidden="true"></i></div>
          <div>
            <div class="review-title" id="reviewTitle">Fact-checked</div>
            <div class="review-sub" id="reviewSub"></div>
          </div>
        </div>
        <div class="fit-note" id="fitNote" hidden>
          <i class="fas fa-route" aria-hidden="true"></i>
          <div>
            <strong id="fitTitle"></strong>
            <p id="fitText"></p>
            <div class="fit-missing" id="fitMissing"></div>
            <p class="fit-hint">Is any of this true for you? <button type="button" class="link-btn" onclick="showRequirements()">Tick it under Job requirements</button>, then press <strong>Tailor again</strong>.</p>
          </div>
        </div>
        <div class="added-note" id="addedNote" hidden>
          <i class="fas fa-pen-ruler" aria-hidden="true"></i>
          <div>
            <strong id="addedTitle">Added to match the job</strong>
            <p>These are not in your original CV. If you want to change anything, use <button type="button" class="link-btn" onclick="openEditor()">Edit</button> before you send.</p>
            <ul class="added-list" id="addedList"></ul>
          </div>
        </div>
        <div class="review-scores" id="reviewScores">
          <div class="score-box"><b id="scoreBefore">--</b><span>Your CV</span></div>
          <i class="fas fa-arrow-right score-arrow" aria-hidden="true"></i>
          <div class="score-box"><b id="scoreAfter">--</b><span>Tailored CV</span></div>
        </div>
        <details class="review-details" id="reviewDetails">
          <summary><i class="fas fa-chevron-right" aria-hidden="true"></i><span id="reviewToggle">What the fact check changed</span></summary>
          <ul class="review-list" id="reviewList"></ul>
        </details>
      </section>

      <ol class="steps">
        <!-- Step 1: CV -->
        <li class="step active" id="ps1">
          <div class="step-head">
            <span class="step-num" aria-hidden="true">1</span>
            <h2 class="step-title">Your CV</h2>
            <span class="badge badge-req" id="uploadBadge">Required</span>
          </div>

          <div class="upload-zone" id="uploadZone" ondragover="onDragOver(event)" ondragleave="onDragLeave(event)" ondrop="onDrop(event)">
            <input type="file" id="cvInput" accept=".pdf,.doc,.docx,.html,.htm,.txt" onchange="onFileSelect(event)" aria-label="Upload your CV"/>
            <div class="uz-icon" aria-hidden="true"><i class="fas fa-arrow-up-from-bracket"></i></div>
            <div class="uz-title">Drop your CV here or <span class="brand-ai">browse</span></div>
            <div class="uz-sub">PDF, DOCX, DOC, TXT or HTML · up to 10 MB</div>
          </div>

          <div class="file-card" id="fileCard">
            <div class="fc-icon" aria-hidden="true"><i class="fas fa-file-lines" id="fcTypeIcon"></i></div>
            <div class="fc-info">
              <div class="fc-name" id="fcName">No file selected</div>
              <div class="fc-meta" id="fcMeta">Waiting for upload</div>
            </div>
            <button class="icon-btn" type="button" onclick="replaceFile()" aria-label="Replace CV" title="Replace"><i class="fas fa-arrows-rotate"></i></button>
            <button class="icon-btn" type="button" onclick="removeFile()" aria-label="Remove CV" title="Remove"><i class="fas fa-trash-can"></i></button>
          </div>
          <div class="send-error upload-warning" id="uploadWarning" role="alert" hidden>
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span>This file is a CV <strong>downloaded from CVCraft</strong>, not your original. Tailoring it again gives weaker results. <button type="button" class="link-btn" onclick="replaceFile()">Upload your original CV</button></span>
          </div>
        </li>

        <!-- Step 2: Job -->
        <li class="step" id="ps2">
          <div class="step-head">
            <span class="step-num" aria-hidden="true">2</span>
            <h2 class="step-title">The job</h2>
            <span class="badge badge-req" id="jobBadge">Required</span>
          </div>

          <div class="segmented" role="tablist" aria-label="How to add the job">
            <button class="active" id="tabUrl" type="button" role="tab" onclick="switchTab('url')"><i class="fas fa-link" aria-hidden="true"></i> Job link</button>
            <button id="tabPaste" type="button" role="tab" onclick="switchTab('paste')"><i class="fas fa-align-left" aria-hidden="true"></i> Paste text</button>
          </div>

          <div class="tab-content active" id="tcUrl" role="tabpanel">
            <label class="sr-only" for="jobUrl">Job link</label>
            <div class="url-row">
              <input class="input" type="url" id="jobUrl" placeholder="https://www.linkedin.com/jobs/view/…" oninput="onJobUrlInput()" onkeydown="if(event.key==='Enter'){event.preventDefault();fetchUrl();}"/>
              <button class="btn btn-secondary" id="fetchBtn" type="button" onclick="fetchUrl()"><i class="fas fa-download" aria-hidden="true"></i> Import</button>
            </div>
            <p class="field-hint">Works with LinkedIn and most company career pages. If a site blocks it, paste the text instead.</p>
          </div>

          <div class="tab-content" id="tcPaste" role="tabpanel">
            <label class="sr-only" for="jobDesc">Job description</label>
            <div class="textarea-wrap">
              <textarea class="textarea jd-textarea" id="jobDesc" placeholder="Paste the full job description, including requirements and how to apply…" oninput="onJdInput(this)"></textarea>
              <span class="char-pill" id="charPill" aria-hidden="true">0</span>
            </div>
          </div>

          <div class="match-card" id="matchCard" aria-live="polite">
            <div class="match-top">
              <span class="match-label" id="matchLabelText">Job requirements met</span>
              <span class="match-val" id="matchPct">--</span>
            </div>
            <div class="match-bar"><div class="match-fill" id="matchFill"></div></div>
            <p class="confirm-hint" id="confirmHint" hidden><i class="fas fa-hand-pointer" aria-hidden="true"></i> Tick the requirements that are true for you. They will be added to your CV.</p>
            <div class="match-chips" id="matchChips"></div>
            <p class="confirm-count" id="confirmCount" hidden></p>
            <div class="match-legend" id="matchLegend" hidden>
              <span><i class="fas fa-check" aria-hidden="true"></i> Your CV shows this</span>
              <span><i class="far fa-square" aria-hidden="true"></i> Not shown yet: tick if true</span>
            </div>
          </div>
        </li>

        <!-- Step 3: Tone -->
        <li class="step" id="ps3">
          <div class="step-head">
            <span class="step-num" aria-hidden="true">3</span>
            <h2 class="step-title">Writing style</h2>
            <span class="step-tag">Optional</span>
          </div>
          <div class="tone-grid" role="radiogroup" aria-label="Writing style">
            <button class="tone-opt selected" type="button" role="radio" aria-checked="true" data-tone="professional" onclick="selectTone(this)">
              <span class="tone-name">Professional</span><span class="tone-desc">Balanced and polished</span>
            </button>
            <button class="tone-opt" type="button" role="radio" aria-checked="false" data-tone="concise" onclick="selectTone(this)">
              <span class="tone-name">Concise</span><span class="tone-desc">Short and to the point</span>
            </button>
            <button class="tone-opt" type="button" role="radio" aria-checked="false" data-tone="detailed" onclick="selectTone(this)">
              <span class="tone-name">Detailed</span><span class="tone-desc">More context per role</span>
            </button>
          </div>
          <div class="fill-gaps">
            <label class="switch"><input type="checkbox" id="fillGaps" checked> <span><strong>Fill gaps from the job</strong></span></label>
            <p class="field-hint">Rewrites every section around the job description and fills what your CV is missing. Everything added is listed for you, and you can change anything with Edit.</p>
          </div>
        </li>
      </ol>
    </div>

    <div class="sidebar-footer">
      <button class="btn btn-primary btn-lg btn-block gen-btn" id="genBtn" type="button" disabled onclick="runGenerate()">
        <i class="fas fa-wand-magic-sparkles gen-icon" aria-hidden="true"></i><span class="spin" aria-hidden="true"></span>
        <span id="genLabel">Tailor my CV</span>
      </button>
      <div class="gen-hint" id="genHint">Usually 15–40 seconds · <span class="kbd">Ctrl</span> + <span class="kbd">Enter</span></div>
    </div>
  </section>

  <!-- Right: preview -->
  <section class="stage" aria-label="CV preview">
    <div class="toolbar">
      <div class="toolbar-title">
        <span>Preview</span>
        <span class="status-pill sp-idle" id="statusPill"><span class="sp-dot"></span><span id="statusText">Waiting</span></span>
      </div>

      <div class="toolbar-spacer"></div>

      <div class="zoom-group" aria-label="Zoom">
        <button class="icon-btn" type="button" onclick="changeZoom(-10)" aria-label="Zoom out"><i class="fas fa-minus"></i></button>
        <span class="zoom-val" id="zoomVal" aria-live="polite">65%</span>
        <button class="icon-btn" type="button" onclick="changeZoom(10)" aria-label="Zoom in"><i class="fas fa-plus"></i></button>
        <button class="icon-btn" type="button" onclick="resetZoom()" aria-label="Fit to width" title="Fit to width"><i class="fas fa-expand"></i></button>
      </div>

      <div class="toolbar-group">
        <button class="btn btn-secondary" id="editBtn" type="button" disabled onclick="openEditor()">
          <i class="fas fa-pen" aria-hidden="true"></i><span class="btn-text">Edit</span>
        </button>

        <div class="popover-wrap">
          <button class="btn btn-secondary" id="designBtn" type="button" disabled onclick="toggleDesign()" aria-haspopup="true" aria-expanded="false" aria-controls="designPopover">
            <i class="fas fa-palette" aria-hidden="true"></i><span class="btn-text">Design</span>
          </button>
          <div class="popover" id="designPopover" role="dialog" aria-label="CV design">
            <div class="popover-section" id="themeTool">
              <div class="popover-label">Accent colour</div>
              <div class="swatches">
                <button class="theme-swatch active" type="button" data-theme="purple" data-primary="#5C2D91" data-light="#7B4DB5" style="--swatch:#5C2D91" onclick="selectCvTheme(this)" aria-label="Purple" title="Purple"></button>
                <button class="theme-swatch" type="button" data-theme="blue" data-primary="#1D4ED8" data-light="#60A5FA" style="--swatch:#1D4ED8" onclick="selectCvTheme(this)" aria-label="Blue" title="Blue"></button>
                <button class="theme-swatch" type="button" data-theme="green" data-primary="#047857" data-light="#34D399" style="--swatch:#047857" onclick="selectCvTheme(this)" aria-label="Green" title="Green"></button>
                <button class="theme-swatch" type="button" data-theme="teal" data-primary="#0F766E" data-light="#2DD4BF" style="--swatch:#0F766E" onclick="selectCvTheme(this)" aria-label="Teal" title="Teal"></button>
                <button class="theme-swatch" type="button" data-theme="red" data-primary="#B91C1C" data-light="#F87171" style="--swatch:#B91C1C" onclick="selectCvTheme(this)" aria-label="Red" title="Red"></button>
                <button class="theme-swatch" type="button" data-theme="dark" data-primary="#1F2937" data-light="#6B7280" style="--swatch:#1F2937" onclick="selectCvTheme(this)" aria-label="Charcoal" title="Charcoal"></button>
                <button class="theme-swatch" type="button" data-theme="gold" data-primary="#A16207" data-light="#FBBF24" style="--swatch:#A16207" onclick="selectCvTheme(this)" aria-label="Gold" title="Gold"></button>
              </div>
            </div>
            <div class="popover-section" id="photoTool">
              <div class="popover-label">Profile photo</div>
              <div class="photo-row">
                <label class="switch"><input type="checkbox" id="photoToggle" onchange="toggleProfilePhoto(this)"> Show photo</label>
                <button class="btn btn-ghost btn-sm" id="photoPickBtn" type="button" disabled onclick="pickProfilePhoto()"><i class="fas fa-camera" aria-hidden="true"></i> Change</button>
              </div>
              <input type="file" id="profilePhotoInput" accept="image/png,image/jpeg,image/webp" hidden onchange="onProfilePhotoSelect(event)">
            </div>
          </div>
        </div>

        <button class="btn btn-secondary" id="btnDownload" type="button" disabled onclick="downloadCV()">
          <i class="fas fa-file-arrow-down" aria-hidden="true"></i><span class="btn-text">PDF</span>
        </button>
        <button class="icon-btn" id="btnShare" type="button" disabled onclick="shareCV()" aria-label="Copy share link" title="Copy share link">
          <i class="fas fa-link"></i>
        </button>
        <button class="btn btn-primary" id="btnApply" type="button" disabled onclick="openModal()">
          <i class="fas fa-paper-plane" aria-hidden="true"></i><span class="btn-text">Apply</span>
        </button>
      </div>
    </div>

    <div class="preview-body" id="previewBody">
      <!-- Empty -->
      <div class="empty-state" id="emptyState">
        <div class="paper-art" aria-hidden="true">
          <span class="pa-head"></span><span></span><span class="pa-mid"></span><span class="pa-short"></span>
          <span></span><span class="pa-mid"></span><span class="pa-short"></span><span></span>
          <div class="pa-badge"><i class="fas fa-check"></i></div>
        </div>
        <h2>Your tailored CV will appear here</h2>
        <p>Complete the steps on the left. You can review, edit and restyle the result before you download or apply.</p>
        <div class="es-steps">
          <span class="es-step" id="esStep1"><b>1</b> Upload CV</span>
          <span class="es-step" id="esStep2"><b>2</b> Add the job</span>
          <span class="es-step" id="esStep3"><b>3</b> Tailor</span>
        </div>
      </div>

      <!-- Working -->
      <div class="proc-state" id="procState" role="status" aria-live="polite">
        <div class="proc-card">
          <div class="proc-top">
            <h2>Tailoring your CV</h2>
            <span class="proc-pct" id="procPct">0%</span>
          </div>
          <div class="proc-bar" role="progressbar" aria-label="Tailoring progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="procBar"><div class="proc-fill" id="procFill"></div></div>
          <div class="proc-meta"><span id="procNote">Usually 15–40 seconds</span><span class="proc-timer" id="procTimer">0:00</span></div>
          <ol class="stage-list">
            <li class="pstage" id="stUpload"><span class="stage-icon" aria-hidden="true"></span><span class="stage-text"><strong>Uploading your CV</strong><small>Sending your file securely</small></span><span class="stage-time"></span></li>
            <li class="pstage" id="stTailor"><span class="stage-icon" aria-hidden="true"></span><span class="stage-text"><strong>Rewriting for the job</strong><small>Putting your most relevant experience first</small></span><span class="stage-time"></span></li>
            <li class="pstage" id="stCheck"><span class="stage-icon" aria-hidden="true"></span><span class="stage-text"><strong>Fact-checking every line</strong><small>Removing anything not in your original CV</small></span><span class="stage-time"></span></li>
            <li class="pstage" id="stLayout"><span class="stage-icon" aria-hidden="true"></span><span class="stage-text"><strong>Laying out your CV</strong><small>Preparing the preview</small></span><span class="stage-time"></span></li>
          </ol>
          <button class="btn btn-secondary" id="cancelBtn" type="button" onclick="cancelGenerate()"><i class="fas fa-xmark" aria-hidden="true"></i> Cancel</button>
        </div>
      </div>

      <!-- CV -->
      <div class="cv-frame-outer" id="cvFrameOuter">
        <div class="cv-viewport" id="cvViewport">
          <div class="cv-scaler" id="cvScaler">
            <iframe class="cv-iframe" id="cvIframe" src="about:blank" title="Tailored CV preview"></iframe>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="app-footer">
  <span>&copy; {{ date('Y') }} CVCraft AI</span>
  <nav aria-label="Footer">
    <button type="button" onclick="openInfo()">How it works</button>
    <a href="{{ route('privacy') }}">Privacy</a>
    @if ($supportEmail)
      <a href="mailto:{{ $supportEmail }}">Support</a>
    @endif
  </nav>
</footer>

<!-- ============================ APPLY MODAL ============================ -->
<div class="modal-backdrop" id="applyModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="applyTitle">
    <div class="modal-header">
      <div>
        <h2 class="modal-title" id="applyTitle">Apply for this job</h2>
        <p class="modal-sub" id="applySub">Your tailored CV is attached as a PDF.</p>
      </div>
      <button class="icon-btn" type="button" onclick="closeModal()" aria-label="Close"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="apply-loading" id="applyLoading"><span class="spin" aria-hidden="true"></span> Writing and fact-checking your application email…</div>

      <div class="apply-cards" id="applyCards" style="display:none">
        <div class="apply-card" id="applyEmailCard">
          <div class="ac-icon ac-icon-email" aria-hidden="true"><i class="fas fa-envelope"></i></div>
          <div class="ac-body">
            <div class="ac-title">Send by email <span class="ac-badge" id="emailBadge">Recommended</span></div>
            <div class="ac-desc" id="emailDesc">The job asks for applications by email. Review the message, then send it from your Gmail.</div>
            <div class="modal-email-form">
              <label class="mef-label" for="recEmail">To</label>
              <input class="mef-input" type="email" placeholder="recruiter@company.com" id="recEmail" list="recEmailOptions" autocomplete="off"/>
              <datalist id="recEmailOptions"></datalist>
              <label class="mef-label" for="emailSubject">Subject</label>
              <input class="mef-input" type="text" id="emailSubject" maxlength="200"/>
              <label class="mef-label" for="emailBody">Message</label>
              <textarea class="mef-input" id="emailBody" maxlength="5000"></textarea>
              <div class="sender-line" id="senderLine"></div>
              <button class="btn btn-primary btn-lg btn-send" id="btnSendEmail" type="button" onclick="sendApplicationEmail()"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send application</button>
              <div class="send-error" id="sendError" role="alert" hidden><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span id="sendErrorText"></span></div>
            </div>
          </div>
        </div>

        <div class="apply-card linkedin-card" id="applyLinkCard" role="button" tabindex="0" onclick="applyViaLink()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();applyViaLink();}">
          <div class="ac-icon ac-icon-li" aria-hidden="true"><i class="fas fa-arrow-up-right-from-square" id="linkIcon"></i></div>
          <div class="ac-body">
            <div class="ac-title" id="linkTitle">Apply on the job page</div>
            <div class="ac-desc" id="linkDesc">Opens the application page and downloads your tailored PDF so you can upload it there.</div>
          </div>
          <i class="fas fa-chevron-right ac-arrow" aria-hidden="true"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================ HOW IT WORKS ============================ -->
<div class="modal-backdrop" id="infoModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="infoTitle">
    <div class="modal-header">
      <div>
        <h2 class="modal-title" id="infoTitle">How CVCraft works</h2>
        <p class="modal-sub">From your CV to a sent application in a few minutes.</p>
      </div>
      <button class="icon-btn" type="button" onclick="closeInfo()" aria-label="Close"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <ol class="how-steps">
        <li><div><strong>Upload your CV</strong><p>PDF, Word, text or HTML. It is used only to build your tailored version.</p></div></li>
        <li><div><strong>Add the job</strong><p>Paste a LinkedIn or careers-page link, or the description itself. You will see which required skills your CV already shows.</p></div></li>
        <li><div><strong>Tailor and fact-check</strong><p>AI reorders and rewords your CV for the job, then every line is checked against your original. Anything it cannot back up is removed.</p></div></li>
        <li><div><strong>Review, edit and apply</strong><p>Edit any text, choose a colour, download the PDF, or send it by email from your own Gmail.</p></div></li>
      </ol>
      <div class="info-grid">
        <div class="info-box">
          <h3><i class="fas fa-lock" aria-hidden="true"></i> Your data</h3>
          Tailored CVs are kept for 30 days so your links keep working, then deleted. Gmail access is used only to send the emails you approve. <a href="{{ route('privacy') }}">Privacy policy</a>
        </div>
        <div class="info-box">
          <h3><i class="fas fa-keyboard" aria-hidden="true"></i> Shortcuts</h3>
          <ul>
            <li><span>Tailor my CV</span><span><span class="kbd">Ctrl</span> + <span class="kbd">Enter</span></span></li>
            <li><span>Close a window</span><span class="kbd">Esc</span></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================ EDIT DRAWER ============================ -->
<div class="drawer-backdrop" id="editBackdrop" onclick="closeEditor()"></div>
<aside class="drawer" id="editDrawer" role="dialog" aria-modal="true" aria-labelledby="editTitle" aria-hidden="true">
  <div class="drawer-head">
    <div>
      <h2 class="modal-title" id="editTitle">Edit your CV</h2>
      <p class="modal-sub">Changes apply to the preview, the PDF and your application.</p>
    </div>
    <button class="icon-btn" type="button" onclick="closeEditor()" aria-label="Close editor"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="send-error ed-error" id="editError" role="alert" hidden><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span id="editErrorText"></span></div>
  <form class="drawer-body" id="editBody" onsubmit="event.preventDefault(); saveEditor();" novalidate></form>
  <div class="drawer-foot">
    <button class="btn btn-ghost" type="button" onclick="closeEditor()">Cancel</button>
    <button class="btn btn-primary" id="editSave" type="button" onclick="saveEditor()"><i class="fas fa-check" aria-hidden="true"></i> Save changes</button>
  </div>
</aside>

@include('partials.app-script')
</body>
</html>
