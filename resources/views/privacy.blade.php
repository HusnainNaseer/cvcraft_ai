<!DOCTYPE html>
<html lang="en">
<head>
<title>Privacy policy · CVCraft AI</title>
@include('partials.head')
<style>
  .doc { max-width: 760px; margin: 0 auto; padding: 48px 20px 64px; }
  .doc h1 { font-size: 2rem; font-weight: 700; letter-spacing: -.02em; line-height: 1.2; }
  .doc .updated { color: var(--text-3); margin-top: 6px; }
  .doc .summary { margin: 28px 0 8px; padding: 18px 20px; border-radius: var(--radius-lg); background: var(--surface); border: 1px solid var(--border); }
  .doc .summary h2 { margin-top: 0; }
  .doc h2 { font-size: 1.1875rem; font-weight: 700; margin: 36px 0 10px; letter-spacing: -.01em; }
  .doc p, .doc li { color: var(--text-2); }
  .doc p + p { margin-top: 10px; }
  .doc ul { padding-left: 20px; display: grid; gap: 8px; margin-top: 8px; }
  .doc strong { color: var(--text); }
  .doc table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: .875rem; }
  .doc th, .doc td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
  .doc th { color: var(--text); font-weight: 600; background: var(--surface-2); }
  .doc td { color: var(--text-2); }
  .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); }
</style>
</head>
<body>
@php($supportEmail = config('app.support_email'))

<header class="app-header">
  <a class="brand" href="{{ route('home') }}" aria-label="CVCraft AI home">
    <span class="brand-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h4"/></svg>
    </span>
    <span>CVCraft <span class="brand-ai">AI</span></span>
  </a>
  <div class="header-spacer"></div>
  <a class="btn btn-secondary" href="{{ route('home') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to the app</a>
</header>

<main class="doc" id="main">
  <h1>Privacy policy</h1>
  <p class="updated">Last updated {{ \Illuminate\Support\Carbon::parse('2026-11-17')->format('j F Y') }}</p>

  <section class="summary">
    <h2>The short version</h2>
    <ul>
      <li>Your CV is used only to create your tailored CV and application email.</li>
      <li>Tailored CVs are <strong>deleted automatically after 30 days</strong>.</li>
      <li>Gmail access lets us <strong>send</strong> emails you approve. We cannot read your inbox.</li>
      <li>No ads, no tracking analytics, and we do not sell your data.</li>
    </ul>
  </section>

  <h2>What we collect and why</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Data</th><th>Why</th><th>How long</th></tr></thead>
      <tbody>
        <tr><td>The CV file you upload</td><td>Read once to create your tailored CV. The file itself is not stored.</td><td>Not kept</td></tr>
        <tr><td>Text of your original CV, the tailored CV, and the job details</td><td>To show, edit, download and share your tailored CV, and to fact-check the application email against your original CV.</td><td>30 days after your last change</td></tr>
        <tr><td>Profile photo (only if you add one)</td><td>Shown on your tailored CV.</td><td>Deleted with the tailored CV</td></tr>
        <tr><td>Gmail connection (an access token for the "send email" permission)</td><td>To send application emails from your own address when you click Send.</td><td>Until you disconnect or your session ends</td></tr>
        <tr><td>Application emails you send</td><td>Sent through your Gmail account. A copy appears in your Gmail "Sent" folder. We keep only a technical log entry (time and recipient address).</td><td>In server logs</td></tr>
      </tbody>
    </table>
  </div>

  <h2>Services we use</h2>
  <ul>
    <li><strong>Google Gemini API</strong> processes your CV (its text, and for PDF, TXT and HTML uploads the file itself) and the job description to tailor and fact-check your CV and to draft the email. If the operator of this service uses Google's free Gemini API tier, Google may use submitted content to improve its products; see Google's Gemini API terms.</li>
    <li><strong>Google Gmail API</strong> sends the application emails you approve, using only the "send email on your behalf" permission.</li>
    <li><strong>Our database host</strong> (Supabase, PostgreSQL) stores tailored CVs until they are deleted.</li>
    <li><strong>Job sites</strong>: when you paste a job link, we fetch that public page to read the job description.</li>
  </ul>

  <h2>Share links</h2>
  <p>Each tailored CV has a private link that is hard to guess. Anyone you give the link to can view that CV until it is deleted. Only share it with people you trust.</p>

  <h2>Gmail</h2>
  <p>We request only Google's <strong>gmail.send</strong> permission, plus your name and email address to show which account is connected. We cannot read, search or delete your emails. Every email is shown to you for review and is sent only when you click <strong>Send application</strong>.</p>
  <p>You can disconnect at any time from the app, or remove access in your Google Account under <a href="https://myaccount.google.com/permissions" rel="noopener" target="_blank">Third-party connections</a>.</p>

  <h2>Cookies and local storage</h2>
  <p>We use one session cookie and one security (CSRF) cookie, both needed for the app to work. Your light/dark theme choice is saved in your browser. We do not use advertising or analytics cookies.</p>

  <h2>Your choices</h2>
  <ul>
    <li>Don't upload information you don't want processed. You can remove personal details such as your date of birth from your CV first.</li>
    <li>Use <strong>Edit</strong> to change or remove anything in your tailored CV.</li>
    <li>@if ($supportEmail) To have a tailored CV deleted before the 30 days are up, or to ask what we hold, email <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a> with its share link. @else To have a tailored CV deleted early, contact the operator of this service with its share link. @endif</li>
  </ul>

  <h2>Changes</h2>
  <p>If we change how we handle your data, we will update this page and the date at the top.</p>
</main>

<footer class="app-footer">
  <span>&copy; {{ date('Y') }} CVCraft AI</span>
  <nav aria-label="Footer">
    <a href="{{ route('home') }}">Home</a>
    @if ($supportEmail)
      <a href="mailto:{{ $supportEmail }}">Support</a>
    @endif
  </nav>
</footer>
</body>
</html>
