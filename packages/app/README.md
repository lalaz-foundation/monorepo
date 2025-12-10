# Lalaz App Installer

Interactive wizard to create new Lalaz applications.

## Usage

```bash
composer create-project lalaz/app my-project
```

The wizard will ask you:
- Project type (Web, API, or Minimal)
- Features to include (Database, Auth, Cache, Queue, Docker)
- Configuration preferences

## What Gets Created

Depending on your choices, you'll get:
- ✅ Configured `composer.json` with the right packages
- ✅ `.env` file with your database settings
- ✅ Routes, controllers, and project structure
- ✅ Docker setup (optional)
- ✅ Ready to run `php lalaz serve`

## License

MIT
