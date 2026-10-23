# TaskFlow secure-coding standard

Every change is scanned by `.github/workflows/security.yml`. That workflow runs Semgrep with the
open-source PHP/JS/secrets rules and our own rules in `.semgrep/taskflow.yml`, plus
`composer audit` and `npm audit`. A finding fails the build. To scan locally:

```bash
semgrep scan --config .semgrep/taskflow.yml --exclude vendor --exclude node_modules --exclude tests .
```

If a finding is a genuine false positive, add `// nosemgrep: <rule-id> -- <why it's safe>` on that
line. The reason is required and gets reviewed like code.

## Rules

**Access control (most important)**
- Every Livewire action re-checks permission on the server: `$this->authorize(...)`,
  `requireAdmin()`, or a lookup through a scoped relation such as `$this->project->tasks()->findOrFail($id)`.
  Never use a bare `Model::find($id)` on an id that came from the browser.
- Public `int` id properties are `#[Locked]` and only set inside actions that checked access.
  `#[Url]` ids must be re-validated wherever they're used.
- Clients only ever see `client_visible` content of their own portal projects.
- Rendering must use the same scoped queries as the actions. A screen can leak data too.

**Output and injection**
- Blade text uses `{{ }}`. Rich text uses `{!! \App\Support\HtmlSanitizer::clean(...) !!}` only.
- Values inside `x-data`, `x-on:*`, `wire:click` and similar are JavaScript. Pass them with `@js($value)`,
  never `'{{ $text }}'`. Integer ids are fine.
- No `x-html`, no `innerHTML`.
- SQL uses the query builder or bindings (`?`). Never concatenate input into `*Raw()` / `DB::` calls.
- CSV cells go through `App\Support\Export\Csv::row()` so spreadsheets don't run formulas.

**Requests, files and secrets**
- Redirect to named routes, never to an address taken from the request.
- Outbound HTTP uses a pinned host (`Http::baseUrl(...)`) and validated input (no SSRF).
- Uploads go through `Attachment::rules()`, which covers the size limit, type allow-list, content
  check and storage quota. Files are stored under random names and always served as downloads.
- Tokens and secrets come from `Str::random()` or `random_bytes()`. No `rand`, `mt_rand`,
  `uniqid`, `md5` or `sha1`.
- Compare secrets with `hash_equals()`.
- Secrets live in the host's environment variables, never in the repository.
- Models list `$fillable`; never `$guarded = []`.

**Platform**
- The visitor's address comes from `UseNearestForwardedIp`. Don't read `X-Forwarded-For` or
  `X-Real-IP` directly.
- Security-relevant actions write to the audit log via `App\Support\Audit::log()`.
- New features get a test in `tests/Feature` that proves another workspace, a member and a client
  can't reach them.

## Dynamic testing (DAST)

`.github/workflows/dast.yml` boots a throwaway copy of the app inside the CI runner (debug off,
demo data) and scans it with OWASP ZAP. It never points at the live site.

- Every push and pull request: passive baseline scan.
- Weekly (Monday) and on demand: full active scan.
- The job fails on any High-risk alert. Reviewed alerts are listed in `.zap/rules.tsv` with the reason.

Last full run: 21 Oct 2026, ZAP 2.16.1, against a local copy. It was signed in as admin, member and
client, including Livewire traffic. 6,293 requests across 44 active rules found **0 High**.

| Alert | Risk | Verdict |
|---|---|---|
| CSP allows `unsafe-eval` (script-src) | Medium | Accepted for now. Alpine evaluates inline `x-data`/`x-on` expressions. Livewire's CSP-safe build was tried and breaks about 20 inline components, drag and drop and doc images. Removing it means moving those into `Alpine.data()` components (a planned refactor). Injected scripts are already blocked by the nonce. |
| CSP allows inline styles (style-src) | Medium | Accepted. Needed for per-project colours and Livewire transitions. Inline styles can't run script. |
| No CSP on `/build` | Medium | Not the app. That 404 comes from PHP's local test server for a folder. App pages, 404s included, all send the policy. |
| `XSRF-TOKEN` cookie not HttpOnly | Low | By design. JavaScript must read it to send the CSRF header. The session cookie is HttpOnly and SameSite=Lax. |
| `X-Content-Type-Options` missing on `/build/*` | Low | Static files are served by the web server, not Laravel, so the header must be added there (Cloudflare transform rule once the domain is in front). Files are hashed and served with the right type. |
| Big redirect, Unix timestamp, suspicious comments | Low/Info | False positives. The standard Laravel "Redirecting to" body, a doc's edit time, and comments inside bundled third-party JS. |

Access control is not something a scanner can judge. That is covered by `AccessTest`,
`NotificationsTest` and `SecurityReviewTest`.
