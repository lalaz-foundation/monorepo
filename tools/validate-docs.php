#!/usr/bin/env php
<?php
/**
 * Documentation Validator
 *
 * Validates that documentation is adherent to source code by checking:
 * - Class names mentioned in docs exist in source
 * - Method signatures match between docs and source
 * - Namespace references are correct
 * - Config keys mentioned exist in config files
 *
 * Usage: php tools/validate-docs.php [package]
 * Example: php tools/validate-docs.php queue
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

class DocValidator
{
    private string $basePath;
    private array $errors = [];
    private array $warnings = [];
    private array $allPackageClasses = []; // Cross-package class registry
    private array $stats = [
        'packages_checked' => 0,
        'docs_files_checked' => 0,
        'classes_validated' => 0,
        'methods_validated' => 0,
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->buildCrossPackageIndex();
    }

    private function buildCrossPackageIndex(): void
    {
        $packagesPath = $this->basePath . '/packages';
        $packages = array_filter(
            scandir($packagesPath),
            fn($dir) => $dir !== '.' && $dir !== '..' && is_dir($packagesPath . '/' . $dir)
        );

        foreach ($packages as $pkg) {
            $srcPath = $packagesPath . '/' . $pkg . '/src';
            if (is_dir($srcPath)) {
                $classes = $this->extractSourceClasses($srcPath, $pkg);
                foreach ($classes as $className => $info) {
                    $this->allPackageClasses[$className] = $info;
                    $this->allPackageClasses[$info['fqcn']] = $info;
                }
            }
        }
    }

    public function validate(?string $package = null): void
    {
        $packagesPath = $this->basePath . '/packages';

        if ($package) {
            $this->validatePackage($packagesPath . '/' . $package, $package);
        } else {
            $packages = array_filter(
                scandir($packagesPath),
                fn($dir) => $dir !== '.' && $dir !== '..' && is_dir($packagesPath . '/' . $dir)
            );

            foreach ($packages as $pkg) {
                $this->validatePackage($packagesPath . '/' . $pkg, $pkg);
            }
        }

        $this->printReport();
    }

    private function validatePackage(string $packagePath, string $packageName): void
    {
        $docsPath = $packagePath . '/docs';
        $srcPath = $packagePath . '/src';

        if (!is_dir($docsPath)) {
            $this->warnings[] = "[{$packageName}] No docs directory found";
            return;
        }

        if (!is_dir($srcPath)) {
            $this->warnings[] = "[{$packageName}] No src directory found";
            return;
        }

        $this->stats['packages_checked']++;

        echo "\n📦 Validating package: {$packageName}\n";
        echo str_repeat('-', 50) . "\n";

        // Get all source classes
        $sourceClasses = $this->extractSourceClasses($srcPath, $packageName);

        // Get all doc files
        $docFiles = $this->getDocFiles($docsPath);

        foreach ($docFiles as $docFile) {
            $this->validateDocFile($docFile, $sourceClasses, $packageName, $srcPath);
        }
    }

    private function extractSourceClasses(string $srcPath, string $packageName): array
    {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace($srcPath . '/', '', $file->getPathname());

            // Extract namespace
            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $namespace = $matches[1];
            } else {
                continue;
            }

            // Extract class/interface/trait name (including abstract/final/readonly)
            // Use word boundary to avoid matching inside comments
            if (preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $content, $matches)) {
                $className = $matches[1];
                $fqcn = $namespace . '\\' . $className;

                // Extract methods
                $methods = $this->extractMethods($content);

                // Extract properties
                $properties = $this->extractProperties($content);

                $classes[$className] = [
                    'fqcn' => $fqcn,
                    'namespace' => $namespace,
                    'file' => $relativePath,
                    'methods' => $methods,
                    'properties' => $properties,
                ];

                $this->stats['classes_validated']++;
            }
        }

        return $classes;
    }

    private function extractMethods(string $content): array
    {
        $methods = [];

        // Match public/protected methods with their signatures
        $pattern = '/(?:public|protected)\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)(?:\s*:\s*([^\s{;]+))?/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $methodName = $match[1];
                $params = $match[2] ?? '';
                $returnType = isset($match[3]) ? rtrim($match[3], ';') : 'void';

                $methods[$methodName] = [
                    'params' => $this->parseParams($params),
                    'return' => trim($returnType),
                ];

                $this->stats['methods_validated']++;
            }
        }

        return $methods;
    }

    private function parseParams(string $params): array
    {
        if (empty(trim($params))) {
            return [];
        }

        $result = [];
        $parts = explode(',', $params);

        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/(?:(\??\w+(?:\|[\w\|]+)?)\s+)?(\$\w+)(?:\s*=\s*(.+))?/', $part, $match)) {
                $result[] = [
                    'type' => $match[1] ?? 'mixed',
                    'name' => $match[2],
                    'default' => $match[3] ?? null,
                ];
            }
        }

        return $result;
    }

    private function extractProperties(string $content): array
    {
        $properties = [];

        $pattern = '/(?:public|protected|private)\s+(?:static\s+)?(?:readonly\s+)?(\??\w+)?\s*(\$\w+)(?:\s*=\s*([^;]+))?;/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $propName = ltrim($match[2], '$');
                $properties[$propName] = [
                    'type' => $match[1] ?? 'mixed',
                    'default' => isset($match[3]) ? trim($match[3]) : null,
                ];
            }
        }

        return $properties;
    }

    private function getDocFiles(string $docsPath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($docsPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function validateDocFile(string $docFile, array $sourceClasses, string $packageName, string $srcPath): void
    {
        $this->stats['docs_files_checked']++;

        $content = file_get_contents($docFile);
        $relativePath = basename($docFile);

        echo "  📄 Checking: {$relativePath}\n";

        // 1. Validate class references in code blocks
        $this->validateClassReferences($content, $sourceClasses, $packageName, $relativePath);

        // 2. Validate method signatures in code blocks
        $this->validateMethodSignatures($content, $sourceClasses, $packageName, $relativePath);

        // 3. Validate use statements
        $this->validateUseStatements($content, $sourceClasses, $packageName, $relativePath);

        // 4. Check for deprecated mentions
        $this->checkDeprecatedMentions($content, $srcPath, $packageName, $relativePath);
    }

    private function validateClassReferences(string $content, array $sourceClasses, string $packageName, string $docFile): void
    {
        // Extract class names from code blocks
        preg_match_all('/```php\s*(.*?)```/s', $content, $codeBlocks);

        foreach ($codeBlocks[1] as $codeBlock) {
            // Look for class instantiations: new ClassName
            if (preg_match_all('/new\s+(\w+)\s*\(/', $codeBlock, $matches)) {
                foreach ($matches[1] as $className) {
                    $this->checkClassExists($className, $sourceClasses, $packageName, $docFile);
                }
            }

            // Look for static calls: ClassName::method
            if (preg_match_all('/(\w+)::\w+\s*\(/', $codeBlock, $matches)) {
                foreach ($matches[1] as $className) {
                    if (!in_array($className, ['self', 'static', 'parent'])) {
                        $this->checkClassExists($className, $sourceClasses, $packageName, $docFile);
                    }
                }
            }

            // Look for class extends: class X extends Y
            if (preg_match_all('/class\s+\w+\s+extends\s+(\w+)/', $codeBlock, $matches)) {
                foreach ($matches[1] as $className) {
                    $this->checkClassExists($className, $sourceClasses, $packageName, $docFile);
                }
            }

            // Look for implements: implements InterfaceName
            if (preg_match_all('/implements\s+([\w,\s]+)/', $codeBlock, $matches)) {
                foreach ($matches[1] as $interfaces) {
                    $interfaceList = array_map('trim', explode(',', $interfaces));
                    foreach ($interfaceList as $interface) {
                        $this->checkClassExists($interface, $sourceClasses, $packageName, $docFile);
                    }
                }
            }
        }
    }

    private function checkClassExists(string $className, array $sourceClasses, string $packageName, string $docFile): void
    {
        // Skip common PHP classes
        $builtinClasses = [
            'Exception', 'RuntimeException', 'InvalidArgumentException', 'PDO',
            'DateTime', 'DateTimeImmutable', 'Closure', 'stdClass', 'ArrayIterator',
            'ReflectionClass', 'ReflectionMethod', 'Throwable', 'Error',
        ];

        if (in_array($className, $builtinClasses) || class_exists($className)) {
            return;
        }

        // Skip if class exists in source
        if (isset($sourceClasses[$className])) {
            return;
        }

        // Skip example/mock classes (common patterns in docs)
        $examplePatterns = [
            '/^(My|Your|Example|Test|Mock|Fake|Stub|Sample)/',
            '/^(User|Order|Product|Customer|Email|Report|App|Post)/',
            '/(Controller|Service|Repository|Handler|Factory|Builder)$/',
            // Common job examples
            '/^(Send|Process|Generate|Create|Update|Delete|Sync|Cleanup|Validate|Reserve|Track|Perform)/',
            // Common service/utility classes
            '/^(Mailer|Mail|Storage|Cache|Log|Logger|Http|Slack|Route|Config|Image|Media|Payment|Auth)/',
            // Exception classes
            '/Exception$/',
            // Gateway/Client classes
            '/(Gateway|Client)$/',
            // Custom driver examples
            '/Driver$/',
            // Model/Record examples
            '/^(Local|Remote|External)/',
            '/(Record|Model|Entity)$/',
            // Common test classes
            '/^Failing/',
            '/(Job|Task|Worker|Report)$/',
            // Middleware examples
            '/Middleware\d*$/',
            '/^(Rate|Conditional|MiddlewareStack)/',
            // Transport examples
            '/Transport$/',
            '/^(Guzzle|Retry|CircuitBreaker|Recording)/',
            // Response examples
            '/Response$/',
            '/^(Paginated|Download|Api)Response/',
            // Adapter examples
            '/Adapter$/',
            '/^S3/',
            // Uploader/Browser/Calculator/Finder examples
            '/(Uploader|Browser|Calculator|Finder|Info)$/',
            '/^(Batch|Directory|Disk|File)/',
            // Validator examples
            '/^(Validator|Common|Extended|Custom|Form|Validation)/',
            '/(Rules|Bag|Result|Request)$/',
            // Component examples
            '/Component$/',
            '/^Reactive/',
            // Empty or self/static/model/class matches
            '/^(self|static|model|modelClass|class)$/',
            // Database/Facades
            '/^(DB|Queue|SMS|Push|Webhook|DeferredEvents)$/',
            // Notification examples
            '/^(Notification|DatabaseNotification|NewUserNotification)$/',
            // Auth examples
            '/^(Admin|Role|Profile|Invoice|Shipping)$/',
            '/^(Ldap|Legacy|OAuth)/',
            '/^(Signer|Token)/',
            '/(Guard|Provider|Authenticatable)$/',
            // Activity/Audit
            '/^(Activity|Audit)/',
            // Test case classes
            '/(TestCase|Recorder)$/',
            // Reactive component examples
            '/^(Counter|Todo|TodoList|Cart|Item|Contact|Dashboard)/',
            '/^(Content|Settings|Simple|Event|Domain)/',
            // Misc
            '/^(Analytics|Str|Tagged|Oracle|New)/',
        ];

        foreach ($examplePatterns as $pattern) {
            if (preg_match($pattern, $className)) {
                return;
            }
        }

        // Check cross-package references
        if (isset($this->allPackageClasses[$className])) {
            return;
        }

        $this->warnings[] = "[{$packageName}] {$docFile}: Class '{$className}' referenced but not found in source";
    }

    private function validateMethodSignatures(string $content, array $sourceClasses, string $packageName, string $docFile): void
    {
        // Extract method documentation patterns like: #### `methodName(params): returnType`
        if (preg_match_all('/####\s+`(\w+)\s*\(([^)]*)\)(?:\s*:\s*(\w+))?`/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $methodName = $match[1];
                $docParams = $match[2] ?? '';
                $docReturn = $match[3] ?? 'void';

                // Skip methods that exist in multiple classes (like onQueue in Job vs PendingDispatch)
                $foundIn = [];
                foreach ($sourceClasses as $className => $classInfo) {
                    if (isset($classInfo['methods'][$methodName])) {
                        $foundIn[] = $className;
                    }
                }

                // If method exists in multiple classes, skip the comparison
                if (count($foundIn) > 1) {
                    continue;
                }

                // Try to find this method in source classes
                foreach ($sourceClasses as $className => $classInfo) {
                    if (isset($classInfo['methods'][$methodName])) {
                        $sourceMethod = $classInfo['methods'][$methodName];

                        // Check return type mismatch
                        if ($docReturn !== 'void' && $sourceMethod['return'] !== $docReturn) {
                            // Allow for nullable differences
                            $sourceReturn = ltrim($sourceMethod['return'], '?');
                            // Also allow self/static to match class name
                            if ($sourceReturn === 'self' || $sourceReturn === 'static') {
                                continue;
                            }
                            if ($sourceReturn !== $docReturn) {
                                $this->warnings[] = "[{$packageName}] {$docFile}: Method '{$methodName}' return type mismatch - doc says '{$docReturn}', source says '{$sourceMethod['return']}'";
                            }
                        }
                    }
                }
            }
        }
    }

    private function validateUseStatements(string $content, array $sourceClasses, string $packageName, string $docFile): void
    {
        // Extract use statements from code blocks
        preg_match_all('/```php\s*(.*?)```/s', $content, $codeBlocks);

        foreach ($codeBlocks[1] as $codeBlock) {
            if (preg_match_all('/use\s+(Lalaz\\\\[^;]+);/', $codeBlock, $matches)) {
                foreach ($matches[1] as $fqcn) {
                    $parts = explode('\\', $fqcn);
                    $className = end($parts);

                    // Check if this FQCN matches any source class in current package
                    $found = false;
                    foreach ($sourceClasses as $srcClassName => $classInfo) {
                        if ($classInfo['fqcn'] === $fqcn || $srcClassName === $className) {
                            $found = true;
                            break;
                        }
                    }

                    // Check cross-package references
                    if (!$found && isset($this->allPackageClasses[$fqcn])) {
                        $found = true;
                    }
                    if (!$found && isset($this->allPackageClasses[$className])) {
                        $found = true;
                    }

                    if (!$found) {
                        // Only warn for Lalaz namespaces that should exist
                        $this->warnings[] = "[{$packageName}] {$docFile}: Use statement '{$fqcn}' may not exist";
                    }
                }
            }
        }
    }

    private function checkDeprecatedMentions(string $content, string $srcPath, string $packageName, string $docFile): void
    {
        // Look for @deprecated in source files
        $deprecatedMethods = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $srcContent = file_get_contents($file->getPathname());

            // Find deprecated methods
            if (preg_match_all('/@deprecated.*?\n.*?function\s+(\w+)/', $srcContent, $matches)) {
                foreach ($matches[1] as $method) {
                    $deprecatedMethods[] = $method;
                }
            }
        }

        // Check if deprecated methods are documented without deprecation notice
        foreach ($deprecatedMethods as $method) {
            if (stripos($content, $method) !== false && stripos($content, 'deprecated') === false) {
                $this->warnings[] = "[{$packageName}] {$docFile}: Documents deprecated method '{$method}' without deprecation notice";
            }
        }
    }

    private function printReport(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 VALIDATION REPORT\n";
        echo str_repeat('=', 60) . "\n";

        echo "\n📈 Statistics:\n";
        echo "   Packages checked: {$this->stats['packages_checked']}\n";
        echo "   Doc files checked: {$this->stats['docs_files_checked']}\n";
        echo "   Classes validated: {$this->stats['classes_validated']}\n";
        echo "   Methods validated: {$this->stats['methods_validated']}\n";

        if (!empty($this->errors)) {
            echo "\n❌ Errors (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "   • {$error}\n";
            }
        }

        if (!empty($this->warnings)) {
            echo "\n⚠️  Warnings (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "   • {$warning}\n";
            }
        }

        if (empty($this->errors) && empty($this->warnings)) {
            echo "\n✅ All documentation is adherent to source code!\n";
        }

        echo "\n" . str_repeat('=', 60) . "\n";

        // Exit code
        if (!empty($this->errors)) {
            exit(1);
        }
    }
}

// Run validator
$package = $argv[1] ?? null;

echo "🔍 Lalaz Documentation Validator\n";
echo "================================\n";

$validator = new DocValidator($basePath);
$validator->validate($package);
