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

The easiest way to get started with Feedarium is using the provided Docker configuration.

1. **Clone the repository**

```bash
git clone https://github.com/yourusername/feedarium.git
cd feedarium
```

2. **Copy the environment file**

```bash
cp .env.example .env
```

3. **Start the Docker environment**

```bash
docker-compose up -d
```

4. **Install PHP dependencies**

```bash
docker-compose exec app composer install
```

5. **Generate application key**

```bash
docker-compose exec app php artisan key:generate
```

6. **Run database migrations**

```bash
docker-compose exec app php artisan migrate
```

7. **Install and build frontend assets**

```bash
docker-compose exec app npm install
docker-compose exec app npm run build
```

8. **Access Feedarium**

Visit [http://localhost:8080](http://localhost:8080) in your browser.

### Using PostgreSQL (Optional)

By default, Feedarium uses SQLite for simplicity. If you prefer PostgreSQL:

1. **Update your .env file**

```env
DB_CONNECTION=pgsql
DB_DATABASE=feedarium
DB_USERNAME=laravel
DB_PASSWORD=secret
```

2. **Start the Docker environment with PostgreSQL**

```bash
docker-compose up -d
```

### Traditional Installation (without Docker)

1. **Clone the repository**

```bash
git clone https://github.com/yourusername/feedarium.git
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
docker-compose up -d
docker-compose exec app npm run dev
```

### Running tests

```bash
docker-compose exec app php artisan test
```

### Code style

```bash
docker-compose exec app composer run pint
```

### Static analysis

```bash
docker-compose exec app composer require --dev phpstan/phpstan
docker-compose exec app ./vendor/bin/phpstan analyse
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

- [GitHub Issues](https://github.com/yourusername/feedarium/issues) for bug reports and feature requests
- [GitHub Discussions](https://github.com/yourusername/feedarium/discussions) for community support and ideas

---

<p align="center">
  Made with ❤️ by the Feedarium community
</p>
