---
title: CVCraft AI
emoji: 📄
colorFrom: purple
colorTo: indigo
sdk: docker
app_port: 7860
license: other
pinned: false
---

# CVCraft AI (CV Maker)

Upload a CV, give it a job (link or pasted text), and CVCraft tailors the CV to that job with Google Gemini,
shows how well it matches, exports a PDF, and helps you apply the way the job asks: by email from your own
Gmail account, on LinkedIn, or on the company website.

## How it works

1. **Upload CV**: PDF, DOCX, DOC, TXT or HTML.
2. **Job description**: paste a job URL (LinkedIn job links and most career pages) or paste the text.
   Pressing *Optimise* with only a URL fetches the description first.
3. **Match score**: required skills found in the job vs. skills found in the CV.
4. **Tone**: Professional, Concise or Detailed.
5. **Tailor**: Gemini rewrites the CV for the job using only facts from your CV.
6. **Preview / theme / photo / Download PDF / Share link**.
7. **Apply Now**: the app detects the application method:
   - **Email** (an address is in the job description): review the AI-drafted email and send it from your Gmail with the PDF attached.
   - **LinkedIn**: opens the job and downloads the PDF for Easy Apply. LinkedIn has no public API for submitting applications.
   - **Company website**: opens the application page and downloads the PDF.

## Requirements

- PHP 8.2+ with `curl`, `dom`, `mbstring`, `openssl`, `zip`
- Composer
- Google Chrome or Microsoft Edge (used headless for PDF export and for job pages that block plain HTTP requests)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Fill in `.env`:

| Key | Purpose |
| --- | --- |
| `GEMINI_API_KEY`, `GEMINI_API_KEYS` | Gemini keys; extra keys are used when one hits its rate limit |
| `GEMINI_MODELS` | Models tried in order (default `gemini-flash-latest,gemini-flash-lite-latest`) |
| `GEMINI_THINKING_LEVEL` | `low` keeps responses fast; leave empty for models without thinking support |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Gmail sending (see below) |
| `CHROME_PATH` | Only if Chrome/Edge is installed somewhere unusual |
| `DB_CONNECTION`, `DB_URL`, `DB_SSLMODE` | SQLite locally; in production `pgsql` with the Supabase **Session pooler** URL and `require` |

With XAMPP the app runs at `http://localhost/CV_Maker/public`.

### Gmail sending

1. In [Google Cloud Console](https://console.cloud.google.com/) create a project and enable the **Gmail API**.
2. *OAuth consent screen*: choose **External**, add the scope `https://www.googleapis.com/auth/gmail.send`,
   and add your Gmail address under **Test users** (needed while the app is in testing mode).
3. *Credentials* > *Create credentials* > *OAuth client ID* > **Web application**.
   Add the authorised redirect URI `http://localhost/CV_Maker/public/auth/google/callback`.
4. Put the client ID and secret in `.env`, then run `php artisan config:clear`.
5. In the app click **Connect Gmail** and allow "Send email on your behalf".

Alternatively, configure Laravel's SMTP mailer (`MAIL_MAILER=smtp`, e.g. a Gmail app password); it is used
when no Gmail account is connected, with replies going to the email on the CV.

## Maintenance

Tailored CVs (personal data) are stored in the `tailored_cvs` database table and deleted 30 days after their last update by
`php artisan cv:prune`, which is scheduled daily. Run `php artisan schedule:work` (or a cron entry for
`schedule:run`) to enable it.

## Tests

```bash
php artisan test
```

Gemini, Gmail and Chrome are faked in tests, so no API calls are made.
