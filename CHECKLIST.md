# Feedarium — Feature Implementation Checklist

> All phases follow `AGENTS.md` strictly:
> thin invokable controllers · DB writes only in Jobs · `Model::query()` everywhere · constructor injection · typed return types · `final` classes · domain-first file placement.

---

## Phase 1 — Schema Foundations
> Add every column and pivot table needed by later phases before any logic is built.

### Migrations
- [x] `add_thumbnail_url_to_news_table` — `thumbnail_url string nullable` on `news`
- [x] `add_websub_columns_to_feeds_table` — `hub_url string nullable`, `websub_secret string nullable`, `websub_subscribed_at timestamp nullable` on `feeds`
- [x] `create_saved_articles_table` — pivot: `user_id FK`, `news_id FK`, `created_at`, unique on `[user_id, news_id]`
- [x] `add_full_body_to_news_table` — `full_body longtext nullable` on `news` *(used by Phase 10)*

### Models
- [x] `News` — add `@property string|null $thumbnail_url`, `@property string|null $full_body`, `@property-read Collection<int,User> $savedByUsers`, add `savedByUsers(): BelongsToMany`
- [x] `Feed` — add `@property string|null $hub_url`, `@property string|null $websub_secret`, `@property Carbon|null $websub_subscribed_at`, add `websub_subscribed_at` to `$casts`
- [x] `User` — add `@property-read Collection<int,News> $savedArticles`, add `savedArticles(): BelongsToMany`

---

## Phase 2 — Periodic RSS Fetching (Scheduler)
> Auto-fetch all active feeds every 15 minutes via queued jobs.

### Composer
- [x] Require `simplepie/simplepie` — RSS/Atom parser (`composer require simplepie/simplepie`)

### Jobs
- [x] `App\Domains\Feed\Jobs\RefreshAllFeedsJob` — queries active feeds, fans out one `FetchFeedJob` per feed (queued, `ShouldQueue`)
- [x] Implement `App\Domains\Feed\Jobs\FetchFeedJob::handle()` — HTTP GET, ETag/Last-Modified check, update `etag`, `last_modified_header`, `last_fetched_at`, dispatch `ImportFeedItemsJob`
- [x] Implement `App\Domains\Feed\Jobs\ImportFeedItemsJob::handle()` — parse XML via SimplePie, deduplicate by `guid`/`link`, insert via `News::query()->create()`, dispatch `ScrapeArticleThumbnailJob` per new item

### Scheduler
- [x] `routes/console.php` — add `Schedule::job(new RefreshAllFeedsJob)->everyFifteenMinutes()`

### Tests
- [x] `tests/Feature/RefreshAllFeedsTest.php` — asserts `FetchFeedJob` dispatched per active feed; skipped for inactive feeds
- [x] `tests/Feature/ImportFeedItemsTest.php` — asserts new `News` created; duplicates skipped; `ScrapeArticleThumbnailJob` dispatched per new item

---

## Phase 3 — WebSub (PubSubHubbub) Support
> Subscribe to RSS hubs for real-time push notifications.

### Jobs
- [x] `App\Domains\Feed\Jobs\SubscribeToHubJob` — sends HTTP POST subscription to hub, updates `websub_subscribed_at` and `websub_secret` (queued)
- [x] Update `CreateFeedJob` — after create, if `hub_url` provided, dispatch `SubscribeToHubJob`
- [x] Update `UpdateFeedJob` — if `hub_url` changed, re-dispatch `SubscribeToHubJob`

### Controllers
- [x] `App\Domains\Feed\Controllers\WebSubCallbackController` — invokable; handles GET (returns `hub.challenge`) and POST (verifies HMAC, dispatches `ImportFeedItemsJob`)

### Requests
- [x] `App\Domains\Feed\Requests\WebSubVerifyRequest` — validates GET challenge params
- [x] Update `CreateFeedRequest` — add optional `hub_url url nullable` rule
- [x] Update `UpdateFeedRequest` — add optional `hub_url url nullable` rule

### Routes (`api.php`)
- [x] `GET  /api/websub/callback/{feedId}` → `WebSubCallbackController` *(no auth middleware — hub must reach it)*
- [x] `POST /api/websub/callback/{feedId}` → `WebSubCallbackController` *(no auth middleware)*

### Support
- [x] `App\Domains\Feed\Support\WebSubSignatureVerifier` — HMAC-SHA256 verification helper (pure function, no DB)

### Tests
- [x] `tests/Feature/WebSubSubscriptionTest.php` — asserts `SubscribeToHubJob` dispatched on feed create with `hub_url`
- [x] `tests/Feature/WebSubCallbackTest.php` — GET returns challenge; POST with valid HMAC dispatches `ImportFeedItemsJob`; POST with invalid HMAC returns 403

---

## Phase 4 — Thumbnail Scraping (og:image)
> After import, scrape each article URL for `og:image` and persist as `thumbnail_url`.

### Jobs
- [x] `App\Domains\News\Jobs\ScrapeArticleThumbnailJob` — fetches `$news->link` via `Http` facade, parses `<meta property="og:image">` via `symfony/dom-crawler` or regex, updates `thumbnail_url` (queued, `ShouldQueue`)
- [x] `ImportFeedItemsJob` — dispatch `ScrapeArticleThumbnailJob::dispatch($newsId)` after each new record

### Tests
- [x] `tests/Feature/ScrapeArticleThumbnailTest.php` — mocks `Http`; asserts `thumbnail_url` updated when `og:image` found; asserts graceful `null` when not found

---

## Phase 5 — Search (Feeds & Articles)
> Full-text search over news and feeds using portable LIKE queries (SQLite + PostgreSQL compatible).

### Jobs
- [x] `App\Domains\News\Jobs\SearchNewsJob` — LIKE on `title`, `description`; with `feed`; paginated (sync)
- [x] `App\Domains\Feed\Jobs\SearchFeedsJob` — LIKE on `name`, `description`, `url`; paginated (sync)

### Controllers
- [x] `App\Domains\News\Controllers\SearchNewsController` — invokable, `GET /api/news/search`
- [x] `App\Domains\Feed\Controllers\SearchFeedsController` — invokable, `GET /api/feeds/search`

### Requests
- [x] `App\Domains\News\Requests\SearchNewsRequest` — `q: required|string|min:2`
- [x] `App\Domains\Feed\Requests\SearchFeedsRequest` — `q: required|string|min:2`

### Routes (`api.php`, under `auth:sanctum`)
- [x] `GET /api/news/search` → `SearchNewsController`
- [x] `GET /api/feeds/search` → `SearchFeedsController`

### Frontend
- [x] `resources/js/components/SearchBar.vue` — debounced input, calls search API, shows dropdown results
- [x] Wire `SearchBar` into `AppHeader.vue` or nav layout

### Tests
- [x] `tests/Feature/SearchNewsTest.php` — results for match; empty for no match; 422 for `q < 2 chars`
- [x] `tests/Feature/SearchFeedsTest.php` — same pattern for feeds

---

## Phase 6 — i18n (Translatable UI)
> English baseline locale files for backend messages and Vue I18n for frontend strings.

### Backend
- [x] Create `lang/en/feeds.php` — feed-related messages and validation keys
- [x] Create `lang/en/news.php` — news/article messages
- [x] Create `lang/en/categories.php` — category messages
- [x] Create `lang/en/general.php` — shared UI strings (save, delete, edit, cancel…)
- [x] Create `lang/en/auth.php` — auth-specific messages (already partially exists — extend)
- [ ] Update validation messages in Request classes to use `trans()` keys where appropriate

### Frontend
- [x] `npm install vue-i18n`
- [x] `resources/js/i18n/index.ts` — configure `createI18n` with `locale: 'en'`
- [x] `resources/js/i18n/locales/en.json` — all UI string keys (nav labels, placeholders, button labels, messages)
- [x] Register `i18n` plugin in `resources/js/app.ts`
- [x] Update all existing Vue pages/components to use `$t('key')` instead of hardcoded English

### Tests
- [x] `tests/Feature/LocalizationTest.php` — asserts backend lang keys exist and return non-empty strings

---

## Phase 7 — Dark / Light Theme
> Theme toggle (light / dark / system) persisted via cookie, consistent with existing `HandleAppearance` middleware.

### Backend
- [x] Update `HandleInertiaRequests::share()` — add `'appearance' => $request->cookie('appearance', 'system')` to shared props
- [x] Update `resources/views/app.blade.php` — inject `class="{{ $appearance === 'dark' ? 'dark' : '' }}"` on `<html>` tag

### Frontend
- [x] Verify `tailwind.config.ts` (or CSS config) has `darkMode: 'class'`
- [x] Verify/extend `useAppearance.ts` composable — handles `light | dark | system` toggle, localStorage + cookie write
- [x] Update `AppearanceTabs.vue` — ensures light / dark / system options call `updateAppearance()`
- [x] Expose theme toggle in `AppHeader.vue` or `NavUser.vue` (accessible from all pages)

### Tests
- [x] `tests/Feature/AppearanceTest.php` — asserts `appearance` cookie is set and forwarded as Inertia shared prop

---

## Phase 8 — Mobile-Friendly / Responsive Design
> All pages fully responsive using Tailwind CSS utility classes; mobile sidebar, touch-friendly targets.

### Frontend
- [x] `AppShell.vue` / `AppSidebar.vue` / `AppHeader.vue` — responsive sidebar collapse (hamburger icon on mobile, overlay on small screens)
- [x] `AppSidebar.vue` — `lg:block hidden` Tailwind pattern or `useBreakpoints` from `@vueuse/core` for toggling
- [x] All page layouts in `resources/js/pages/` — replace fixed-width containers with `max-w-* mx-auto px-4` responsive patterns
- [x] Feed/Category/News index pages — convert tables to card layouts on small screens (`sm:hidden` table, `sm:block` cards)
- [x] `NavMain.vue`, `NavFooter.vue` — verify min 44px touch targets
- [ ] Smoke test all pages at 375px, 768px, 1280px viewport widths

---

## Phase 9 — Read Later / Save for Later *(Optional)*
> Users can save articles to a personal reading list via pivot table.

### Jobs
- [x] `App\Domains\News\Jobs\SaveArticleJob` — `DB::table('saved_articles')->insertOrIgnore(...)` (idempotent)
- [x] `App\Domains\News\Jobs\UnsaveArticleJob` — removes row from `saved_articles`
- [x] `App\Domains\News\Jobs\ListSavedArticlesJob` — queries pivot for auth user, returns paginated news

### Controllers
- [x] `App\Domains\News\Controllers\SaveArticleController` — `POST /api/news/{id}/save`
- [x] `App\Domains\News\Controllers\UnsaveArticleController` — `DELETE /api/news/{id}/save`
- [x] `App\Domains\News\Controllers\ListSavedArticlesController` — `GET /api/news/saved`

### Routes (`api.php`, under `auth:sanctum`)
- [x] `POST   /api/news/{id}/save` → `SaveArticleController`
- [x] `DELETE /api/news/{id}/save` → `UnsaveArticleController`
- [x] `GET    /api/news/saved`     → `ListSavedArticlesController`

### Frontend
- [x] `resources/js/composables/useSavedArticles.ts` — wraps save/unsave API calls
- [x] Bookmark icon button on article card component
- [x] `resources/js/pages/feeds/Saved.vue` — lists saved articles
- [x] Add web route `GET /saved` → Inertia `feeds/Saved`

### Tests
- [x] `tests/Feature/SaveArticleTest.php` — save creates pivot row; unsave removes it; duplicate save is idempotent; list returns only current user's saved articles

---

## Phase 10 — Full Page Scraping *(Optional)*
> Fetch and store the full article body from the article URL.

### Config
- [x] `config/feedarium.php` — create with `'scrape_full_body' => env('FEEDARIUM_SCRAPE_FULL_BODY', false)`
- [x] `.env.example` — add `FEEDARIUM_SCRAPE_FULL_BODY=false`

### Jobs
- [x] `App\Domains\News\Jobs\ScrapeArticleBodyJob` — fetches `$news->link`, extracts `article`/`main`/`.content` via `symfony/dom-crawler`, stores cleaned HTML in `full_body` (queued, behind config flag)
- [x] `ImportFeedItemsJob` — conditionally dispatch `ScrapeArticleBodyJob` when `config('feedarium.scrape_full_body')` is true

### Frontend
- [x] Article reader view — display `full_body` in prose overlay/modal if available (`v-html` + Tailwind Typography `prose` class)
- [x] Add `@tailwindcss/typography` plugin (`npm install @tailwindcss/typography`)

### Tests
- [x] `tests/Feature/ScrapeArticleBodyTest.php` — `full_body` populated when flag enabled; `null` when disabled

---

## Phase 11 — Browser Bookmarklet "Save for Later" *(Optional)*
> Users can save any browser tab to their reading list via a Sanctum-authenticated bookmarklet.

### Jobs
- [ ] Reuse `CreateNewsJob` (stub article from URL+title) then `SaveArticleJob`

### Controllers
- [ ] `App\Domains\News\Controllers\BookmarkletSaveController` — `POST /api/bookmarklet/save`; accepts `url`, `title`, `description?`; creates stub news and saves it; returns `201`
- [ ] `App\Domains\User\Controllers\BookmarkletPageController` — `GET /bookmarklet` → Inertia page with token instructions + JS snippet

### Requests
- [ ] `App\Domains\News\Requests\BookmarkletSaveRequest` — `url: required|url`, `title: required|string`

### Routes
- [ ] `POST /api/bookmarklet/save` → `BookmarkletSaveController` (under `auth:sanctum`)
- [ ] `GET  /bookmarklet`          → `BookmarkletPageController` (web, auth)

### Frontend
- [ ] `resources/js/pages/settings/Bookmarklet.vue` — copy-able bookmarklet JS snippet with embedded API URL and user token instructions
- [ ] Add link in settings sidebar navigation

### Tests
- [ ] `tests/Feature/BookmarkletSaveTest.php` — article created and saved via endpoint; unauthenticated request rejected with 401

---

## Phase 12 — Custom Themes via Admin Panel *(Optional)*
> Admins can upload and activate custom CSS theme files through Filament.

### Migration
- [ ] `create_themes_table` — `id`, `name string`, `css_path string`, `is_active boolean default false`, `timestamps`

### Model
- [ ] `App\Models\Theme` — `@property int $id`, `@property string $name`, `@property string $css_path`, `@property bool $is_active`

### Jobs
- [ ] `App\Domains\Admin\Jobs\ActivateThemeJob` — sets selected theme `is_active = true`, all others `false`

### Filament
- [ ] `App\Filament\Resources\ThemeResource` — list, create (CSS file upload), set-active action

### Backend
- [ ] Update `HandleAppearance` middleware — share active theme `css_path` with Blade view
- [ ] `resources/views/app.blade.php` — conditionally inject `<link rel="stylesheet" href="...">` for active custom theme

### Tests
- [ ] `tests/Feature/ThemeActivationTest.php` — activating a theme sets it active and deactivates others; CSS path is shared with Blade

---

## Global AGENTS.md Compliance Checklist
> Verify at the end of each phase.

- [x] All controllers are `final` invokable classes extending `Illuminate\Routing\Controller`
- [x] All controllers use `DispatchesJobs` trait
- [x] All controllers dispatch via `$this->dispatchSync(new Job($arg))` or `$this->dispatch(new Job($arg))`
- [x] No DB writes in any controller
- [x] All Eloquent queries use `Model::query()->...`
- [x] All Jobs use constructor injection (`readonly` properties)
- [x] All Request classes are `final` and extend `FormRequest`
- [x] All models have full `@property` PHPDoc annotations
- [x] All new routes added to `routes/api.php` (API) or `routes/web.php` (web)
- [x] All new tests follow Pest syntax with `describe()` + `it()` blocks and `beforeEach()` for auth setup
- [x] No fat controllers, no repository pattern, no generic CRUD boilerplate

---

*Last updated: 2026-03-09*
