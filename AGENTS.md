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
   - admin/backoffice change -> prefer Filament, not the public UI stack
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

```text
app/Domains/
  Category/
  Feed/
  News/
  User/
```

### Current stack and product rules

- backend: Laravel 12 + PHP 8.4
- public UI: Vue 3 + Inertia
- admin/backoffice: Filament only
- auth/API readiness: Laravel Sanctum
- supported databases: SQLite and PostgreSQL

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
| Admin vs public UI | Use Filament for admin workflows only | Build the public reader UI in Filament |
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

### Admin UI

Filament is for admin and backoffice workflows only.

Use Filament for areas like:

- feed management
- categories
- settings
- moderation
- diagnostics and import health

Do not use Filament as the main public reader UI.

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
- placing the public reader UI into Filament

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
