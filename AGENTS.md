# AGENTS.md

## Purpose

This repository is a FOSS/self-hosted RSS reader built with Laravel 12, PHP 8.4, Vue 3, and Inertia.

This file is the source of truth for coding agents and contributors working in this repo.

Use it to keep the codebase:

- maintainable as it grows
- easy to navigate
- strongly typed and IDE-friendly
- organized by domain
- ready for both web UI and future API/mobile clients
- consistent across files and domains

---

## How to use this file

When this file gives guidance, use this priority order:

1. **Must**: required rules
2. **Prefer**: default patterns to follow unless there is a strong reason not to
3. **Avoid**: patterns that should not be introduced unless explicitly requested

If a generic Laravel convention conflicts with this file, follow this file.

When in doubt, optimize for:

1. consistency with the existing project structure
2. domain-oriented organization
3. thin controllers
4. typed and IDE-friendly code
5. explicit code over magic
6. maintainability over convenience shortcuts

Do not introduce a different architectural style unless explicitly requested.

---

## Start here: agent decision tree

Use this quick routing before making changes.

1. **What kind of task is this?**
   - backend/domain behavior -> start in `app/Domains/<Domain>`
   - model/schema/data change -> inspect `app/Models` and `database/migrations`
   - public UI change -> inspect `resources/js` and related Inertia routes/controllers
   - API/output change -> inspect `routes/api.php`, Resources, and Sanctum implications

2. **Does the task belong to an existing domain?**
   - if yes, mirror the nearest existing pattern in that domain
   - if no, create the smallest domain-first structure that fits the task

3. **What artifacts are required?**
   - validation needed -> add/update a Request
   - business logic or DB writes needed -> add/update a Job
   - HTTP entry point needed -> add/update an invokable Controller
   - stable JSON output needed -> add/update a Resource
   - authorization rule needed -> add/update a Policy
   - repeated structured payload needed -> add/update a Data object or Enum

4. **Should this code stop and be reworked before finishing?**
   - yes, if a controller contains DB writes or business logic
   - yes, if Eloquent calls skip `Model::query()` without a strong reason
   - yes, if validation is inline in a controller but deserves a Request
   - yes, if public API output returns raw models instead of Resources
   - yes, if the change invents a new pattern while a nearby repo pattern already exists

---

## Repo snapshot

Use the current repository structure before inventing new structure.

### Current top-level application domains

The actual subdirectories that exist right now (not every domain has every folder):

```text
app/
  Domains/
    Category/
      Controllers/   ← CategoryResource.php here is a stale Filament v2 artifact, ignore it
      Jobs/
      Requests/
    Feed/
      Controllers/   ← FeedResource.php here is a stale Filament v2 artifact, ignore it
      Jobs/
      Requests/
      Support/       ← WebSubSignatureVerifier.php (stateless HMAC helper)
    News/
      Controllers/   ← NewsResource.php here is a stale Filament v2 artifact, ignore it
      Jobs/
      Requests/
      Resources/     ← NewsResource.php (active Laravel API Resource for all news endpoints)
    User/
      Controllers/
      Jobs/
      Requests/
  Events/
    FeedUpdated.php  ← broadcast via Reverb on the public 'feeds' channel
  Filament/
    Pages/           ← AdminSettings.php (Livewire page, password/theme/timezone)
    Resources/       ← CategoryResource.php, FeedResource.php (Filament v5, autodiscovered)
  Http/
    Controllers/
      Settings/      ← ProfileController, PasswordController (Inertia settings pages ONLY)
```

> **API Resources live in `app/Domains/<Domain>/Resources/`**. `NewsResource` (in `News/Resources/`)
> shapes all news API output. Do not return raw Eloquent models from public endpoints.

> **Filament resources belong in `app/Filament/Resources/`**, not in domain Controller folders.
> The `*Resource.php` files inside domain `Controllers/` folders are stale Filament v2 artifacts
> and should be ignored or removed. All active Filament v5 work goes in `app/Filament/`.

> **Settings controllers exception**: `app/Http/Controllers/Settings/` holds the Inertia
> profile/password settings controllers. This is an intentional exception to the domain-first
> structure because these are thin Inertia-only controllers with no business logic.

### Current stack and product rules

- backend: Laravel 12 + PHP 8.4
- public UI: Vue 3 + Inertia
- auth/API readiness: Laravel Sanctum (cookie-based session for Inertia, token for pure API)
- supported databases: SQLite and PostgreSQL
- RSS/Atom parsing: SimplePie (`simplepie/simplepie`)
- real-time: Laravel Reverb (WebSocket broadcasting)

---

## Non-negotiable rules

### 1. Always use `Model::query()->...`

Start Eloquent operations from `Model::query()` unless there is a very strong reason not to.

**Preferred**

```php
News::query()->create([...]);
User::query()->find($id);
Feed::query()->where('active', true)->get();
```

**Avoid**

```php
News::create([...]);
User::find($id);
Feed::where('active', true)->get();
```

### 2. Use single-action invokable controllers

Controllers should be small, action-based, and have one public `__invoke()` method.

**Good names**

- `ListNewsController`
- `ShowNewsController`
- `CreateFeedController`
- `UpdateFeedController`
- `DeleteFeedController`

**Avoid**

- `NewsController` with multiple CRUD methods
- large resource controllers handling many actions

### 3. Keep controllers thin

Controllers may:

- receive HTTP input
- rely on dedicated Request classes for validation
- authorize when needed
- dispatch a Job
- return a response

Controllers must not:

- contain business logic
- directly perform DB writes
- contain long query chains
- perform feed parsing, importing, or deduplication
- become orchestration-heavy beyond a dispatch and response

### 4. Put business logic in Jobs

Jobs are the main unit of application behavior.

Use Jobs for:

- business rules
- database interaction
- feed fetching/import work
- synchronization and cleanup tasks

Keep each Job focused on one responsibility.

**Good names**

- `CreateNewsJob`
- `UpdateFeedJob`
- `FetchFeedJob`
- `ImportFeedItemsJob`

**Avoid**

- `HandleNewsJob`
- `ManageFeedJob`
- `ProcessEverythingJob`

### 5. Controllers dispatch Jobs via `DispatchesJobs`

Use the `DispatchesJobs` trait in controllers and dispatch Jobs from the controller.

**Preferred controller pattern**

```php
<?php

namespace App\Domains\News\Controllers;

use App\Domains\News\Jobs\CreateNewsJob;
use App\Domains\News\Requests\CreateNewsRequest;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class CreateNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CreateNewsRequest $request): JsonResponse
    {
        $news = $this->dispatchSync(new CreateNewsJob($request));

        return response()->json(['news' => $news], 201);
    }
}
```

Use:

- `$this->dispatchSync(new SomeJob(...))` for synchronous work
- `$this->dispatch(new SomeQueuedJob(...))` for queued work

Do not replace this with static dispatch calls unless explicitly requested.

### 6. Validation belongs in dedicated Request classes

Use dedicated Request classes for:

- create operations
- update operations
- meaningful filter endpoints
- auth-related validation when needed

Do not place large validation arrays in controllers.

Prefer typed request accessors when useful:

```php
$request->string('title')->toString();
$request->boolean('active');
$request->integer('per_page');
```

### 7. Models must include accurate PHPDoc annotations

All Eloquent models must be IDE-friendly.

Add:

- `@property` annotations for scalar attributes
- `@property-read` annotations for relationships when useful
- explicit relationship return types
- casts where appropriate

### 8. Prefer explicit typing everywhere possible

Use:

- typed parameters
- explicit return types
- typed properties where practical
- typed relationship return types
- concrete response types

Avoid vague or untyped public interfaces unless Laravel requires them.

---

## Known architectural exceptions

These patterns deviate from the non-negotiable rules by design. Do not "fix" them.

### 1. WebSubCallbackController — multi-method, no auth

`App\Domains\Feed\Controllers\WebSubCallbackController` handles both `GET` (hub verification)
and `POST` (content push) on the same route. It has private helper methods in addition to
`__invoke`. It is intentionally excluded from Sanctum auth because the hub must reach it
publicly. Route: `Route::match(['GET', 'POST'], '/api/websub/callback/{feedId}', ...)`.

### 2. SaveArticleJob — uses `DB::table()` instead of `Model::query()`

`App\Domains\News\Jobs\SaveArticleJob` inserts into the `saved_articles` pivot table using
`DB::table('saved_articles')->insertOrIgnore(...)`. Eloquent's `BelongsToMany::attach()` does
not support `insertOrIgnore`; the raw query builder call is the approved approach here.

### 3. Settings and Auth controllers live outside domain folders

`app/Http/Controllers/Settings/ProfileController` and `PasswordController` are intentionally
outside the domain structure. They are thin Inertia-only controllers (no business logic, no
DB writes) and are shared infrastructure rather than a domain concern.

`app/Http/Controllers/Auth/` contains standard Laravel starter kit auth controllers
(login, register, password reset, email verification). These are also intentionally outside
the domain structure — they are framework scaffolding. Routes for these live in `routes/auth.php`.

---

## Domain feature inventory

Use this to understand what currently exists before adding new features.

### Feed pipeline

The feed refresh pipeline runs as follows:

```
Schedule (every 15 min)
  └── RefreshAllFeedsJob        fans out one FetchFeedJob per active feed
        └── FetchFeedJob        HTTP GET with ETag/If-Modified-Since caching
              └── ImportFeedItemsJob   parses XML via SimplePie, deduplicates by guid/link
                    ├── ScrapeArticleThumbnailJob  (queued) scrapes og:image per new item
                    └── ScrapeArticleBodyJob       (queued, only if scrape_full_body=true)
```

Key behaviors:
- `FetchFeedJob`: 3 tries, 60 s backoff; skips import on HTTP 304
- `ImportFeedItemsJob`: deduplicates by `guid` OR `link` within the same feed
- `ScrapeArticleBodyJob`: guarded by `config('feedarium.scrape_full_body', false)` — set `FEEDARIUM_SCRAPE_FULL_BODY=true` in `.env` to enable
- All scraping jobs: 3 tries, 60 s backoff; log warnings on failure without crashing

### WebSub (PubSubHubbub) integration

Feeds with a `hub_url` can receive real-time push updates:

1. After creating/updating a feed with `hub_url`, `SubscribeToHubJob` (queued) sends a subscription request to the hub, stores `websub_secret` and `websub_subscribed_at` on the feed.
2. The hub calls back to `/api/websub/callback/{feedId}`:
   - `GET` — echoes `hub.challenge` for subscription verification
   - `POST` — verifies HMAC-SHA256 signature via `Feed\Support\WebSubSignatureVerifier`, then dispatches `ImportFeedItemsJob`
3. After import, `BroadcastFeedUpdatedJob` fires `App\Events\FeedUpdated` over the public `feeds` Reverb channel.

### Real-time broadcasting

- Event: `App\Events\FeedUpdated` — implements `ShouldBroadcastNow`, broadcasts on `Channel('feeds')` with `['feed_id' => $feedId]`
- Job: `BroadcastFeedUpdatedJob` — dispatches `FeedUpdated` via `Broadcast::event()`
- Frontend: `resources/js/composables/useFeedNotifications.ts` — listens on `window.Echo.channel('feeds')` for `FeedUpdated`

### Saved articles

- Pivot table: `saved_articles` (`user_id`, `news_id`, `created_at`)
- Relationships: `User::savedArticles()` BelongsToMany News; `News::savedByUsers()` BelongsToMany User
- Jobs: `SaveArticleJob`, `UnsaveArticleJob`, `ListSavedArticlesJob`
- Frontend composable: `resources/js/composables/useSavedArticles.ts` — `save(newsId)` / `unsave(newsId)` via axios


---

## Dev workflow

This project runs inside Docker. The local machine's PHP/Node environment may differ from the container environment. **Always run backend commands inside the Docker `app` container** to ensure consistency.

### Starting the Docker environment

```bash
./dev up                  # start all containers (SQLite + Redis by default)
./dev up --pgsql          # include PostgreSQL
./dev up --meilisearch    # include MeiliSearch
./dev up --pgsql --meilisearch  # full stack
```

To open a shell inside the `app` container:

```bash
./dev workspace
```

All `artisan`, `composer`, and `php` commands should be run from within that shell, or prefixed with `docker compose exec app`:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan tinker
docker compose exec app composer install
```

**The queue worker runs automatically inside Docker** (managed by supervisord). You do not need to start it manually.

### Running migrations

Always run migrations inside the Docker `app` container, not locally:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan migrate:rollback --step=1 --force
docker compose exec app php artisan migrate:status
```

> Running `php artisan migrate` locally against a local SQLite file while Docker uses PostgreSQL will produce schema mismatches. Always target the container.

### Running tests

```bash
php artisan test --compact
php artisan test --compact --filter=FetchFeedJobTest
```

### Code formatting

PHP formatting runs locally via Pint (it only reads/writes files, no DB access):

```bash
vendor/bin/pint --dirty --format agent   # PHP (run after any PHP edit)
```

Frontend formatting also runs locally:

```bash
npm run format                           # Prettier for resources/
npm run lint                             # ESLint fix
```

### Local-only (non-Docker) development

If running without Docker:

```bash
composer run dev
```

This runs four processes concurrently: `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan pail --timeout=0`, and `npm run dev`.

**The queue worker must be running** for any queued job to execute (feed fetching, scraping, WebSub subscription, broadcasting).

---

## Domain-first structure

Place new code in the relevant domain whenever practical.

### Preferred domain layout

```text
app/
  Domains/
    News/
      Controllers/
      Jobs/
      Requests/
      Resources/
      Policies/
      Data/
      Enums/
      Exceptions/
      Support/
    Feed/
      Controllers/
      Jobs/
      Requests/
      Resources/
      Policies/
      Data/
      Enums/
      Exceptions/
      Support/
    Category/
      Controllers/
      Jobs/
      Requests/
      Resources/
      Policies/
      Data/
      Enums/
      Exceptions/
      Support/
    User/
      Controllers/
      Jobs/
      Requests/
      Resources/
      Policies/
      Data/
      Enums/
      Exceptions/
      Support/
```

Not every domain needs every folder immediately. Add folders only when the domain needs them.

### File placement rules

- controllers: `app/Domains/<Domain>/Controllers/`
- jobs: `app/Domains/<Domain>/Jobs/`
- requests: `app/Domains/<Domain>/Requests/`
- resources: `app/Domains/<Domain>/Resources/`
- policies: `app/Domains/<Domain>/Policies/`
- DTOs/data objects: `app/Domains/<Domain>/Data/`
- enums: `app/Domains/<Domain>/Enums/`
- exceptions: `app/Domains/<Domain>/Exceptions/`
- small domain helpers: `app/Domains/<Domain>/Support/`

Do not place domain-specific behavior in generic global folders unless it is truly cross-domain infrastructure.

---

## Fast do/don't matrix

| Concern | Do | Don't |
| --- | --- | --- |
| Controllers | Keep them invokable, thin, and response-focused | Put queries, DB writes, or business rules in them |
| Jobs | Put business logic and persistence here | Create broad catch-all jobs with mixed responsibilities |
| Validation | Use dedicated Request classes | Keep large validation arrays inside controllers |
| Eloquent | Start from `Model::query()` | Call `Model::create()`, `Model::find()`, or `Model::where()` directly by default |
| API output | Return Resources for stable public JSON | Return raw Eloquent models from public endpoints |
| Structure | Reuse the nearest existing domain pattern | Invent a new folder or architecture style without need |
| Types | Add explicit parameter, return, and relationship types | Leave public-facing code vague when a concrete type is available |

---

## Task playbooks for agents

### When adding a new write feature

Follow this order:

1. identify the domain
2. add or update the Request class
3. add an invokable controller
4. add a focused Job
5. update models, casts, relationships, or policies if needed
6. add a Resource if the feature affects public API output
7. add or update tests

### When adding a new read feature

Prefer:

1. thin controller
2. small query-oriented Job when the read is non-trivial
3. explicit return type
4. Inertia response for web UI or API Resource for public JSON

Avoid large inline query chains in controllers.

### When refactoring existing code

Prefer these improvements:

- convert static Eloquent shortcuts to `Model::query()`
- split multi-action controllers into single-action controllers
- move DB logic out of controllers and into Jobs
- move inline validation into Request classes
- add missing model annotations
- improve parameter, return, and relationship typing

Do not do unrelated stylistic churn during refactors.

### When changing API-facing code

Keep the application API-ready from the start.

Rules:

- keep web and API concerns separated
- prefer versioned API routes such as `/api/v1/...`
- use Laravel API Resources for stable JSON output
- do not expose raw Eloquent models as public API contracts
- preserve Sanctum compatibility

---

## Controller rules

Controllers should:

- be invokable
- have one responsibility
- receive typed Request classes when validation is needed
- dispatch Jobs
- return explicit response types such as `JsonResponse`, `RedirectResponse`, `Response`, `View`, or `Inertia\Response`

Controllers should not:

- run complex queries inline
- write to the database directly
- contain feed import logic
- contain heavy transformations
- contain hidden authorization rules when a Policy is more appropriate

---

## Job rules

Jobs should:

- do one thing well
- hold business logic
- own database writes and meaningful query behavior
- use `Model::query()` style
- receive clear inputs

Job inputs:

- passing a validated Request object is acceptable when it is the established domain pattern
- for queued work, prefer queue-safe primitives or DTOs over carrying unnecessary request context
- do not pass raw unvalidated arrays around

Use queued Jobs for:

- RSS fetching
- remote polling
- import processing
- retries/backoff workflows
- cleanup work
- longer-running background tasks

---

## Request rules

Request classes should:

- own validation
- own authorization when appropriate
- expose data through typed accessors when helpful

Controllers should not duplicate validation already defined in Requests.

---

## Model rules

Models should be explicit and IDE-friendly.

Required conventions:

- add accurate `@property` annotations
- add relationship method return types
- add casts where appropriate
- keep fillable/guarded choices consistent with the project
- prefer explicitness over magic

### Example model pattern

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $feed_id
 * @property string $title
 * @property string $link
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $author
 * @property string|null $guid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Feed $feed
 */
final class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_id',
        'title',
        'link',
        'description',
        'published_at',
        'author',
        'guid',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }
}
```

---

## Frontend and admin rules

### Public UI

The first-party reader UI uses Vue 3 + Inertia.

#### Page structure

Pages live in `resources/js/pages/` organized by domain:

```text
resources/js/pages/
  auth/          ← Login, Register, RegisterAdmin, etc.
  feeds/         ← Index.vue (main reader), Saved.vue
  Feed/          ← Create.vue, Edit.vue, Index.vue
  Category/      ← Create.vue, Edit.vue, Index.vue
  User/          ← Create.vue, Edit.vue, Index.vue
  settings/      ← Appearance.vue, Categories.vue, FeedSources.vue, Profile.vue, Password.vue
  Dashboard.vue
```

#### Layouts

- `resources/js/layouts/AppLayout.vue` — wraps `app/AppSidebarLayout.vue`; use this for all authenticated pages
- `resources/js/layouts/AuthLayout.vue` — for auth pages
- `resources/js/layouts/app/AppSidebarLayout.vue` — composes `AppShell`, `AppSidebar`, `AppContent`

#### Components

- Shared components: `resources/js/components/` (e.g. `AppHeader.vue`, `AppSidebar.vue`, `SearchBar.vue`)
- UI primitives: `resources/js/components/ui/` — shadcn-vue-style components built on **reka-ui** (button, card, dialog, input, sidebar, etc.)
- Icons: **lucide-vue-next** (`import { Rss, Bookmark } from 'lucide-vue-next'`)
- Class merging utility: `resources/js/lib/utils.ts` exports `cn()` (clsx + tailwind-merge)

#### Composables

```text
resources/js/composables/
  useAppearance.ts        ← light/dark/system theme (cookie-persisted)
  useFeedNotifications.ts ← Reverb/Echo listener for FeedUpdated events
  useInitials.ts          ← derives initials from a name string
  useSavedArticles.ts     ← save(newsId) / unsave(newsId) via axios
```

#### Internationalization

- Setup: `resources/js/i18n/index.ts` bootstraps vue-i18n v11 with `legacy: false`
- Locale files: `resources/js/i18n/locales/en.json`
- Usage: `const { t } = useI18n()` in `<script setup>`

#### TypeScript types

- Shared types: `resources/js/types/index.d.ts` — `AppPageProps`, `Auth`, `User`, `NavItem`, `BreadcrumbItem`
- Use `AppPageProps<T>` as the Inertia page props type

#### HTTP calls from Vue

- API calls from composables/pages use `axios` configured in `resources/js/lib/axios.ts` (withCredentials + XSRF-TOKEN)
- Inertia form submissions use `useForm()` from `@inertiajs/vue3`
- Route generation uses `route()` from `ziggy-js` / `ZiggyVue`

### Admin UI

Filament resources exist under `app/Filament/` but the admin panel is not actively used. Do not use Filament as the main public reader UI.

---

## Database rules

The application supports both SQLite and PostgreSQL.

Prefer:

- portable migrations
- database-agnostic application code when practical
- clearly isolated database-specific behavior when necessary

SQLite is fine for lightweight installs. PostgreSQL is the recommended serious deployment target.

---

## Authorization rules

Use Laravel authorization features explicitly.

Prefer:

- Policies
- Gates where appropriate

Do not bury authorization rules inside controllers when a Policy is the better fit.

---

## Naming rules

Use explicit, action-based names.

### Controllers

**Good**

- `ListNewsController`
- `ShowNewsController`
- `CreateFeedController`

**Avoid**

- `NewsController`
- `FeedController` with many methods

### Jobs

**Good**

- `CreateNewsJob`
- `FetchFeedJob`
- `ImportFeedItemsJob`

**Avoid**

- `HandleNewsJob`
- `ManageFeedsJob`

### Requests

**Good**

- `CreateNewsRequest`
- `UpdateFeedRequest`

### Resources

**Good**

- `NewsResource`
- `FeedResource`
- `CategoryResource`

### Policies

**Good**

- `NewsPolicy`
- `FeedPolicy`

---

## What to avoid

Do not introduce these patterns unless explicitly requested:

- fat controllers
- multi-method CRUD controllers
- direct DB writes inside controllers
- `Model::create()` and other direct static Eloquent shortcuts
- giant service classes with multiple responsibilities
- repository-pattern boilerplate without clear value
- raw public API responses from unwrapped models
- large untyped arrays passed around without structure
- domain logic hidden in helpers or facades

---

## Example patterns

### Example controller

```php
<?php

namespace App\Domains\News\Controllers;

use App\Domains\News\Jobs\CreateNewsJob;
use App\Domains\News\Requests\CreateNewsRequest;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class CreateNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CreateNewsRequest $request): JsonResponse
    {
        $news = $this->dispatchSync(new CreateNewsJob($request));

        return response()->json(['news' => $news], 201);
    }
}
```

### Example Job

```php
<?php

namespace App\Domains\News\Jobs;

use App\Domains\News\Requests\CreateNewsRequest;
use App\Models\News;

final class CreateNewsJob
{
    public function __construct(
        private readonly CreateNewsRequest $request,
    ) {
    }

    public function handle(): News
    {
        return News::query()->create([
            'feed_id' => $this->request->integer('feed_id'),
            'title' => $this->request->string('title')->toString(),
            'link' => $this->request->string('link')->toString(),
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
            'published_at' => $this->request->input('published_at'),
            'author' => $this->request->filled('author')
                ? $this->request->string('author')->toString()
                : null,
            'guid' => $this->request->filled('guid')
                ? $this->request->string('guid')->toString()
                : null,
        ]);
    }
}
```

---

## Agent checklists

### Quick pre-edit checklist

Before generating or refactoring code, check:

- Is this the correct domain?
- Does the repo already have a matching pattern nearby?
- Does the change need a Request class?
- Does the change need a Job?
- Does the change touch public API output?
- Does the model need annotations, casts, or relationship types?

### Quick post-edit checklist

Before finishing, check:

- Are controllers still thin?
- Are Eloquent calls using `Model::query()`?
- Is validation in a Request class?
- Is business logic in a Job?
- Are parameter and return types explicit?
- Are models IDE-friendly?
- Are tests updated or added when behavior changed?

---

## Conflict resolution for agents

If instructions seem to compete, resolve them in this order:

1. follow explicit user instructions
2. follow this file's non-negotiable repo rules
3. follow the nearest existing repository pattern in the same domain
4. prefer the smallest change that preserves current architecture
5. prefer explicit, typed, domain-first code over framework-default shortcuts

If two options are both valid, choose the one that:

- adds fewer new concepts
- matches neighboring files more closely
- keeps controllers thinner
- keeps API output more stable

If a change would violate a non-negotiable rule, rework it before finishing.

---

## Final rule

When uncertain, prefer the existing repository pattern over framework-default shortcuts.

This repository values:

- domain organization
- thin invokable controllers
- job-based business logic
- `Model::query()` consistency
- strong type support
- explicit, maintainable code

Follow those preferences consistently.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
- filament/filament (FILAMENT) - v5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== filament/filament rules ===

## Filament

- Filament is used by this application. Follow the existing conventions for how and where it is implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Always inspect required options before running a command, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Actions encapsulate a button with an optional modal form and logic:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data))

</code-snippet>

### Testing

Always authenticate before testing panel functionality. Filament uses Livewire, so use `Livewire::test()` or `livewire()` (available when `pestphp/pest-plugin-livewire` is in `composer.json`):

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

<code-snippet name="Calling actions in pages" lang="php">
use Filament\Actions\DeleteAction;
use function Pest\Livewire\livewire;

livewire(EditUser::class, ['record' => $user->id])
    ->callAction(DeleteAction::class)
    ->assertNotified()
    ->assertRedirect();

</code-snippet>

<code-snippet name="Calling actions in tables" lang="php">
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, and `Fieldset` do not span all columns by default. Explicitly set column spans when needed.

</laravel-boost-guidelines>
