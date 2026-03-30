# Feedarium

<p align="center">
    <a href="https://github.com/kodorama/feedarium/releases"><img alt="Version" src="https://img.shields.io/github/v/release/kodorama/feedarium?color=success&label=version&sort=semver&style=flat-square"></a>
    <a href="https://github.com/kodorama/feedarium/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/github/license/kodorama/feedarium?style=flat-square"></a>
    <a href="https://github.com/kodorama/feedarium/issues"><img alt="Issues" src="https://img.shields.io/github/issues/kodorama/feedarium?style=flat-square"></a>
    <a href="https://github.com/kodorama/feedarium/stargazers"><img alt="Stars" src="https://img.shields.io/github/stars/kodorama/feedarium?style=flat-square"></a>
</p>

## About Feedarium

Feedarium is an elegant, modern open-source RSS reader built with Laravel and Vue.js. It allows you to subscribe to your favorite websites and blogs, organizing all your reading in one beautiful, distraction-free interface.

### Key Features

- **Clean, Minimalist Interface**: Focus on what matters—your content.
- **Feed Management**: Easily add, organize, and categorize your RSS feeds.
- **Article Filtering & Search**: Quickly find the content you're looking for.
- **Reading Experience**: Comfortable reading view with adjustable text size and theme options.
- **Mobile Friendly**: Responsive design that works on all your devices.
- **Self-Hosted**: Own your data and reading habits by hosting Feedarium on your own server.

## Screenshots

*Coming soon*

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

Contributions are welcome and greatly appreciated! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for more information.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/amazing-feature`)
3. Commit your Changes (`git commit -m 'Add some amazing feature'`)
4. Push to the Branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.

## Support & Community

- [GitHub Issues](https://github.com/kodorama/feedarium/issues) for bug reports and feature requests
- [GitHub Discussions](https://github.com/kodorama/feedarium/discussions) for community support and ideas

---

<p align="center">
  Made with ❤️ by the Feedarium community
</p>
