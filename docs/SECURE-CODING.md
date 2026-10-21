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
- Secrets live in Railway variables, never in the repository.
- Models list `$fillable`; never `$guarded = []`.

**Platform**
- The visitor's address comes from `UseNearestForwardedIp`. Don't read `X-Forwarded-For` or
  `X-Real-IP` directly.
- Security-relevant actions write to the audit log via `App\Support\Audit::log()`.
- New features get a test in `tests/Feature` that proves another workspace, a member and a client
  can't reach them.
