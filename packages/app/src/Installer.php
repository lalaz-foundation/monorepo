<?php

declare(strict_types=1);

namespace Lalaz\Installer;

class Installer
{
    private const VERSION = '1.0.0-rc1';
    
    private static array $colors = [
        'reset' => "\033[0m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",
    ];

    public static function run(): void
    {
        $installer = new self();
        $installer->execute();
    }

    public function execute(): void
    {
        $this->showBanner();
        
        $projectType = $this->askProjectType();
        $features = $this->askFeatures($projectType);
        
        $this->setupProject($projectType, $features);
        
        $this->showSuccess();
    }

    private function showBanner(): void
    {
        $c = self::$colors;
        
        echo "\n";
        echo "{$c['green']}{$c['bold']}";
        echo "  _          _           \n";
        echo " | |    __ _| | __ _ ____\n";
        echo " | |   / _` | |/ _` |_  /\n";
        echo " | |__| (_| | | (_| |/ / \n";
        echo " |_____\\__,_|_|\\__,_/___|\n";
        echo "{$c['reset']}\n";
        echo "{$c['dim']}  The Modern PHP Framework{$c['reset']}\n";
        echo "{$c['dim']}  Version " . self::VERSION . "{$c['reset']}\n\n";
    }

    private function askProjectType(): string
    {
        $c = self::$colors;
        
        echo "{$c['cyan']}{$c['bold']}? What type of project do you want to create?{$c['reset']}\n\n";
        echo "  {$c['green']}[1]{$c['reset']} Web Application {$c['dim']}(MVC with Twig templates){$c['reset']}\n";
        echo "  {$c['green']}[2]{$c['reset']} REST API {$c['dim']}(JSON responses, no views){$c['reset']}\n";
        echo "  {$c['green']}[3]{$c['reset']} Minimal {$c['dim']}(just the framework core){$c['reset']}\n";
        echo "\n";
        
        $choice = $this->prompt("Your choice", "1");
        
        return match ($choice) {
            '2' => 'api',
            '3' => 'minimal',
            default => 'web',
        };
    }

    private function askFeatures(string $projectType): array
    {
        $c = self::$colors;
        $features = [];
        
        echo "\n{$c['cyan']}{$c['bold']}? Select features to include:{$c['reset']}\n\n";
        
        // Database
        $features['database'] = $this->confirm("Include database support?", true);
        
        if ($features['database']) {
            echo "\n  {$c['dim']}Database driver:{$c['reset']}\n";
            echo "  {$c['green']}[1]{$c['reset']} MySQL\n";
            echo "  {$c['green']}[2]{$c['reset']} PostgreSQL\n";
            echo "  {$c['green']}[3]{$c['reset']} SQLite\n";
            $dbChoice = $this->prompt("  Choose", "1");
            $features['db_driver'] = match ($dbChoice) {
                '2' => 'pgsql',
                '3' => 'sqlite',
                default => 'mysql',
            };
        }
        
        // Auth (only for web/api)
        if ($projectType !== 'minimal') {
            $features['auth'] = $this->confirm("Include authentication?", $projectType === 'web');
        }
        
        // Cache
        $features['cache'] = $this->confirm("Include caching?", false);
        
        // Queue
        $features['queue'] = $this->confirm("Include background jobs/queue?", false);
        
        // Docker
        $features['docker'] = $this->confirm("Include Docker setup?", false);
        
        return $features;
    }

    private function setupProject(string $projectType, array $features): void
    {
        $c = self::$colors;
        
        echo "\n{$c['cyan']}Creating your {$projectType} project...{$c['reset']}\n\n";
        
        // Copy template files
        $this->copyTemplate($projectType);
        
        // Generate composer.json
        $this->generateComposerJson($projectType, $features);
        
        // Generate .env
        $this->generateEnvFile($features);
        
        // Generate config files
        $this->generateConfigFiles($features);
        
        // Docker setup
        if ($features['docker'] ?? false) {
            $this->generateDockerFiles($features);
        }
        
        // Install dependencies
        echo "{$c['yellow']}Installing dependencies...{$c['reset']}\n";
        passthru('composer install --quiet');
        
        // Cleanup installer files
        $this->cleanup();
    }

    private function copyTemplate(string $type): void
    {
        $templateDir = __DIR__ . "/../templates/{$type}";
        
        if (!is_dir($templateDir)) {
            $templateDir = __DIR__ . '/../templates/minimal';
        }
        
        $this->recurseCopy($templateDir, getcwd());
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            
            if (is_dir($srcPath)) {
                $this->recurseCopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        
        closedir($dir);
    }

    private function generateComposerJson(string $projectType, array $features): void
    {
        $projectName = basename(getcwd());
        
        $composer = [
            'name' => "app/{$projectName}",
            'description' => 'A Lalaz application',
            'type' => 'project',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.3',
                'lalaz/framework' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];
        
        // Add packages based on features
        if ($projectType === 'web') {
            $composer['require']['lalaz/web'] = '^1.0';
            $composer['require']['twig/twig'] = '^3.0';
        }
        
        if ($features['database'] ?? false) {
            $composer['require']['lalaz/database'] = '^1.0';
            $composer['require']['lalaz/orm'] = '^1.0';
        }
        
        if ($features['auth'] ?? false) {
            $composer['require']['lalaz/auth'] = '^1.0';
        }
        
        if ($features['cache'] ?? false) {
            $composer['require']['lalaz/cache'] = '^1.0';
        }
        
        if ($features['queue'] ?? false) {
            $composer['require']['lalaz/queue'] = '^1.0';
        }
        
        file_put_contents(
            getcwd() . '/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function generateEnvFile(array $features): void
    {
        $env = "APP_NAME=Lalaz\n";
        $env .= "APP_ENV=local\n";
        $env .= "APP_DEBUG=true\n";
        $env .= "APP_URL=http://localhost:8000\n\n";
        
        if ($features['database'] ?? false) {
            $driver = $features['db_driver'] ?? 'mysql';
            
            $env .= "DB_CONNECTION={$driver}\n";
            
            if ($driver === 'sqlite') {
                $env .= "DB_DATABASE=database/database.sqlite\n";
            } else {
                $env .= "DB_HOST=127.0.0.1\n";
                $env .= "DB_PORT=" . ($driver === 'pgsql' ? '5432' : '3306') . "\n";
                $env .= "DB_DATABASE=lalaz\n";
                $env .= "DB_USERNAME=root\n";
                $env .= "DB_PASSWORD=\n";
            }
            $env .= "\n";
        }
        
        if ($features['cache'] ?? false) {
            $env .= "CACHE_DRIVER=file\n\n";
        }
        
        if ($features['queue'] ?? false) {
            $env .= "QUEUE_CONNECTION=database\n\n";
        }
        
        file_put_contents(getcwd() . '/.env', $env);
        file_put_contents(getcwd() . '/.env.example', $env);
    }

    private function generateConfigFiles(array $features): void
    {
        @mkdir(getcwd() . '/config', 0755, true);
        
        // app.php
        $appConfig = <<<'PHP'
<?php

return [
    'name' => env('APP_NAME', 'Lalaz'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    
    'providers' => [
        Lalaz\Config\ConfigServiceProvider::class,
        Lalaz\Logging\LogServiceProvider::class,
    ],
];
PHP;
        
        file_put_contents(getcwd() . '/config/app.php', $appConfig);
    }

    private function generateDockerFiles(array $features): void
    {
        $dockerfile = <<<'DOCKER'
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    git

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000

CMD ["php", "lalaz", "serve", "--host=0.0.0.0"]
DOCKER;

        $dockerCompose = <<<'YAML'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
YAML;

        if ($features['database'] ?? false) {
            $driver = $features['db_driver'] ?? 'mysql';
            
            if ($driver === 'mysql') {
                $dockerCompose .= <<<'YAML'

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: lalaz
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
YAML;
            } elseif ($driver === 'pgsql') {
                $dockerCompose .= <<<'YAML'

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_PASSWORD: password
      POSTGRES_DB: lalaz
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
YAML;
            }
        }

        file_put_contents(getcwd() . '/Dockerfile', $dockerfile);
        file_put_contents(getcwd() . '/docker-compose.yml', $dockerCompose);
    }

    private function cleanup(): void
    {
        // Remove installer files
        $this->removeDir(getcwd() . '/src');
        $this->removeDir(getcwd() . '/templates');
        @unlink(getcwd() . '/src/Installer.php');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    private function showSuccess(): void
    {
        $c = self::$colors;
        $projectName = basename(getcwd());
        
        echo "\n";
        echo "{$c['green']}{$c['bold']}✅ Success!{$c['reset']} Your Lalaz project is ready.\n\n";
        echo "{$c['cyan']}Next steps:{$c['reset']}\n\n";
        echo "  {$c['dim']}1.{$c['reset']} cd {$projectName}\n";
        echo "  {$c['dim']}2.{$c['reset']} php lalaz serve\n";
        echo "  {$c['dim']}3.{$c['reset']} Open {$c['blue']}http://localhost:8000{$c['reset']}\n";
        echo "\n";
        echo "{$c['dim']}Happy coding! 🚀{$c['reset']}\n\n";
    }

    private function prompt(string $question, string $default = ''): string
    {
        $c = self::$colors;
        $defaultHint = $default ? " {$c['dim']}[{$default}]{$c['reset']}" : '';
        
        echo "{$c['green']}›{$c['reset']} {$question}{$defaultHint}: ";
        
        $handle = fopen('php://stdin', 'r');
        $input = trim(fgets($handle));
        fclose($handle);
        
        return $input !== '' ? $input : $default;
    }

    private function confirm(string $question, bool $default = false): bool
    {
        $c = self::$colors;
        $hint = $default ? 'Y/n' : 'y/N';
        
        echo "{$c['green']}›{$c['reset']} {$question} {$c['dim']}({$hint}){$c['reset']}: ";
        
        $handle = fopen('php://stdin', 'r');
        $input = strtolower(trim(fgets($handle)));
        fclose($handle);
        
        if ($input === '') {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', 's', 'sim'], true);
    }
}
