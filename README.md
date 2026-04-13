# Feedarium

<p>
    <a href="https://github.com/kodorama/feedarium/releases"><img alt="Version" src="https://img.shields.io/github/v/release/kodorama/feedarium?color=success&label=version&sort=semver&style=flat-square"></a>
    <a href="https://github.com/kodorama/feedarium/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/badge/license-AGPL--3.0-blue?style=flat-square"></a>
    <a href="https://github.com/kodorama/feedarium/issues"><img alt="Issues" src="https://img.shields.io/github/issues/kodorama/feedarium?style=flat-square"></a>
    <a href="https://github.com/kodorama/feedarium/stargazers"><img alt="Stars" src="https://img.shields.io/github/stars/kodorama/feedarium?style=flat-square"></a>
</p>

## About Feedarium
<p><i>Feed + Diarium (Latin for `diary / newspaper` )</i></p>
Feedarium is an elegant, modern open-source RSS reader built with Laravel and Vue.js. It allows you to subscribe to your favorite websites and blogs, organizing all your reading in one beautiful, distraction-free interface.

### Key Features

- **Clean, Minimalist Interface**: Focus on what matters—your content.
- **Feed Management**: Easily add, organize, and categorize your RSS feeds.
- **Article Filtering & Search**: Quickly find the content you're looking for.
- **Reading Experience**: Comfortable reading view with adjustable text size and theme options.
- **Mobile Friendly**: Responsive design that works on all your devices.
- [**Self-Hosted**](#self-hosting-with-portainer-casaos-or-any-docker-host): Own your data and reading habits by hosting Feedarium on your own server.

## Screenshots

![Screen Recording 2026-03-31 at 02 37 42](https://github.com/user-attachments/assets/6d488036-ef16-413e-85de-80f05ddbb293)


## Requirements

- PHP 8.4 or higher
- Composer
- Node.js & NPM
- Docker & Docker Compose (for Docker installation)

## Installation

### Using Docker (Recommended)

Feedarium ships with a `dev` helper script that wraps all Docker Compose commands.
Make it executable once after cloning:

```bash
chmod +x dev
```

#### Default setup (SQLite + Redis)

1. **Clone the repository**

```bash
git clone https://github.com/kodorama/feedarium.git
cd feedarium
```

2. **Build and start the environment**

This single command copies `.env.example`, builds the images, starts all containers,
installs dependencies, generates the application key, and runs the migrations:

```bash
./dev build
```

3. **Access Feedarium**

Visit [http://localhost:8080](http://localhost:8080) in your browser.

> The default port is `8080`. Override it by setting `NGINX_PORT` in your `.env` file.

#### With PostgreSQL

Pass the `--pgsql` flag to include the PostgreSQL service:

```bash
./dev build --pgsql
```

Then update your `.env` file to use PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=feedarium
DB_USERNAME=laravel
DB_PASSWORD=secret
```

#### With MeiliSearch

Pass the `--meilisearch` flag to include the MeiliSearch service:

```bash
./dev build --meilisearch
```

Then set the Scout driver in your `.env` file:

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey
```

Flags can be combined:

```bash
./dev build --pgsql --meilisearch
```

### Managing the Docker environment

| Command | Description |
|---|---|
| `./dev up` | Start all containers (add `--pgsql` / `--meilisearch` as needed) |
| `./dev down` | Stop and remove all containers |
| `./dev workspace` | Open a bash shell inside the `app` container |

### Traditional Installation (without Docker)

1. **Clone the repository**

```bash
git clone https://github.com/kodorama/feedarium.git
cd feedarium
```

2. **Install dependencies**

```bash
composer install
npm install
```

3. **Copy the environment file and generate key**

```bash
cp .env.example .env
php artisan key:generate
```

4. **Set up the database**

Configure your database connection in the `.env` file, then run:

```bash
php artisan migrate
```

5. **Build assets**

```bash
npm run build
```

6. **Start the development server**

```bash
php artisan serve
```

7. **Access Feedarium**

Visit [http://localhost:8000](http://localhost:8000) in your browser.

---

## Self-Hosting with Portainer, CasaOS, or any Docker host

The GitHub Actions workflow automatically builds a **single, self-contained image** and
pushes it to the GitHub Container Registry on every **published GitHub Release**.

The image bundles **nginx**, **PHP-FPM**, the **queue worker**, and the **task
scheduler** in one container managed by supervisord.  You do not need to clone the
repository — just paste one of the compose snippets below.

### What happens on first start

The container's entrypoint performs these steps automatically **before** supervisord
starts the web server:

1. Creates writable `storage/` and `bootstrap/cache/` directory scaffolding inside
   any mounted volumes (Docker volumes start empty).
2. Generates and persists `APP_KEY` to `storage/app_key` inside the storage volume if
   `APP_KEY` is not set in the environment — the key survives container upgrades.
3. Runs `php artisan migrate --force` (retries up to 15× to wait for slow-starting
   databases).
4. Runs `php artisan config:cache`, `route:cache`, and `view:cache` for production
   performance.
5. When `SCOUT_DRIVER=meilisearch`, runs `php artisan scout:sync-index-settings`
   to configure filterable and sortable attributes automatically.

> **Variable substitution:** all environment values use `${VAR:-default}` syntax.
> In Portainer, CasaOS, or a `.env` file next to your `compose.yml`, set only the
> variables you want to override — everything else falls back to the shown defaults.
<img width="1565" height="661" alt="image" src="https://github.com/user-attachments/assets/0ab0e51d-4083-4fb2-b62f-df409150f212" />

>
>
> **`APP_KEY`** has no default and must be set explicitly.  Generate one with:
>
> ```bash
> docker run --rm ghcr.io/kodorama/feedarium:latest \
>     php artisan key:generate --show
> ```

---

### Option A — SQLite (simplest, good for personal use)

```yaml
services:
  app:
    image: ghcr.io/kodorama/feedarium:latest
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    environment:
      APP_KEY: "${APP_KEY:-}"
      APP_URL: "${APP_URL:-http://YOUR_SERVER_IP:8080}"
      DB_CONNECTION: "${DB_CONNECTION:-sqlite}"
      REDIS_HOST: "${REDIS_HOST:-redis}"
    volumes:
      - feedarium-storage:/var/www/html/storage   # logs, sessions, view cache
      - feedarium-db:/var/www/html/database        # SQLite database file
    depends_on:
      - redis

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - feedarium-redis:/data

volumes:
  feedarium-storage:
  feedarium-db:
  feedarium-redis:
```

---

### Option B — PostgreSQL (recommended for multi-user / heavier workloads)

```yaml
services:
  app:
    image: ghcr.io/kodorama/feedarium:latest
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    environment:
      APP_KEY: "${APP_KEY:-}"
      APP_URL: "${APP_URL:-http://YOUR_SERVER_IP:8080}"
      DB_CONNECTION: "${DB_CONNECTION:-pgsql}"
      DB_HOST: "${DB_HOST:-postgres}"
      DB_PORT: "${DB_PORT:-5432}"
      DB_DATABASE: "${DB_DATABASE:-feedarium}"
      DB_USERNAME: "${DB_USERNAME:-feedarium}"
      DB_PASSWORD: "${DB_PASSWORD:-change-me}"
      REDIS_HOST: "${REDIS_HOST:-redis}"
    volumes:
      - feedarium-storage:/var/www/html/storage
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:15-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: "${DB_DATABASE:-feedarium}"
      POSTGRES_USER: "${DB_USERNAME:-feedarium}"
      POSTGRES_PASSWORD: "${DB_PASSWORD:-change-me}"
    volumes:
      - feedarium-pgsql:/var/lib/postgresql/data

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - feedarium-redis:/data

volumes:
  feedarium-storage:
  feedarium-pgsql:
  feedarium-redis:
```

---

### Option C — Add MeiliSearch (full-text search)

Append the `meilisearch` service to either compose file above and add the
corresponding environment variables to the `app` service.

**Additional service:**

```yaml
  meilisearch:
    image: getmeili/meilisearch:v1.8
    restart: unless-stopped
    environment:
      MEILI_MASTER_KEY: "${MEILISEARCH_KEY:-change-me-meilisearch-key}"
      MEILI_ENV: production
    volumes:
      - feedarium-meilisearch:/meili_data
```

**Additional `app` environment variables:**

```yaml
      SCOUT_DRIVER: "${SCOUT_DRIVER:-meilisearch}"
      MEILISEARCH_HOST: "${MEILISEARCH_HOST:-http://meilisearch:7700}"
      MEILISEARCH_KEY: "${MEILISEARCH_KEY:-change-me-meilisearch-key}"
```

**Additional volume:**

```yaml
  feedarium-meilisearch:
```

> MeiliSearch index settings are synced automatically on every container start.
> You can also trigger a manual sync from the **Admin → Settings → Search Index** panel.

---

### Option D — PostgreSQL + MeiliSearch (full production stack)

A single ready-to-paste compose file that combines Options B and C.

```yaml
services:
  app:
    image: ghcr.io/kodorama/feedarium:latest
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    environment:
      APP_KEY: "${APP_KEY:-}"
      APP_URL: "${APP_URL:-http://YOUR_SERVER_IP:8080}"
      DB_CONNECTION: "${DB_CONNECTION:-pgsql}"
      DB_HOST: "${DB_HOST:-postgres}"
      DB_PORT: "${DB_PORT:-5432}"
      DB_DATABASE: "${DB_DATABASE:-feedarium}"
      DB_USERNAME: "${DB_USERNAME:-feedarium}"
      DB_PASSWORD: "${DB_PASSWORD:-change-me}"
      REDIS_HOST: "${REDIS_HOST:-redis}"
      SCOUT_DRIVER: "${SCOUT_DRIVER:-meilisearch}"
      MEILISEARCH_HOST: "${MEILISEARCH_HOST:-http://meilisearch:7700}"
      MEILISEARCH_KEY: "${MEILISEARCH_KEY:-change-me-meilisearch-key}"
    volumes:
      - feedarium-storage:/var/www/html/storage
    depends_on:
      - postgres
      - redis
      - meilisearch

  postgres:
    image: postgres:15-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: "${DB_DATABASE:-feedarium}"
      POSTGRES_USER: "${DB_USERNAME:-feedarium}"
      POSTGRES_PASSWORD: "${DB_PASSWORD:-change-me}"
    volumes:
      - feedarium-pgsql:/var/lib/postgresql/data

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - feedarium-redis:/data

  meilisearch:
    image: getmeili/meilisearch:v1.8
    restart: unless-stopped
    environment:
      MEILI_MASTER_KEY: "${MEILISEARCH_KEY:-change-me-meilisearch-key}"
      MEILI_ENV: production
    volumes:
      - feedarium-meilisearch:/meili_data

volumes:
  feedarium-storage:
  feedarium-pgsql:
  feedarium-redis:
  feedarium-meilisearch:
```

> MeiliSearch index settings are synced automatically on every container start.
> To import existing articles into the index after first deploy, use the
> **Admin → Settings → Search Index → Import All Articles** button.

---

### Upgrading

Pull the new image and recreate the container.  The entrypoint will automatically
run any new migrations.

```bash
docker compose pull
docker compose up -d
```

---

## CI / CD — GitHub Actions

The workflow at `.github/workflows/docker-publish.yml` builds and pushes the image
automatically.

| Trigger | Image tags produced |
|---|---|
| GitHub Release published (e.g. `v20260406`, `v20260406-1`) | `:20260406` / `:20260406-1`, `:latest` |
| Manual (`workflow_dispatch`) | same as release |

Builds target **`linux/amd64`** and **`linux/arm64`** so the image runs on both
x86 servers and ARM boards (Raspberry Pi, Odroid, etc.).

No secrets need to be configured — the workflow uses the built-in
`GITHUB_TOKEN` to authenticate with GHCR.

---

## Development

### Starting the development environment

```bash
./dev up
```

To open a shell inside the app container:

```bash
./dev workspace
```

All `artisan`, `composer`, and `npm` commands can be run from within that shell.

### Running tests

```bash
./dev workspace
php artisan test --compact
```

### Code style

```bash
vendor/bin/pint --dirty
```

### Static analysis

```bash
composer require --dev phpstan/phpstan
./vendor/bin/phpstan analyse
```

## Contributing

Contributions are welcome and greatly appreciated!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/amazing-feature`)
3. Commit your Changes (`git commit -m 'Add some amazing feature'`)
4. Push to the Branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

Distributed under the [GNU Affero General Public License v3.0 (AGPL-3.0)](https://www.gnu.org/licenses/agpl-3.0.html). See `LICENSE` for more information.

## Support & Community

- [GitHub Issues](https://github.com/kodorama/feedarium/issues) for bug reports and feature requests
- [GitHub Discussions](https://github.com/kodorama/feedarium/discussions) for community support and ideas

---

## Star History

<a href="https://www.star-history.com/?repos=kodorama%2Ffeedarium&type=date&legend=top-left">
 <picture>
   <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/chart?repos=kodorama/feedarium&type=date&theme=dark&legend=top-left" />
   <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/chart?repos=kodorama/feedarium&type=date&legend=top-left" />
   <img alt="Star History Chart" src="https://api.star-history.com/chart?repos=kodorama/feedarium&type=date&legend=top-left" />
 </picture>
</a>

<p>
  Made with ❤️ by the Feedarium community
</p>
