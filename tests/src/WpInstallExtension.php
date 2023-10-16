<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

final class WpInstallExtension implements BeforeFirstTestHook, AfterLastTestHook
{
    /**
     * @var bool
     */
    private static $initialized = false;

    /**
     * @return void
     */
    public static function resetDb(): void
    {
        if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-config.php')) {
            throw new \Exception('Can not reset DB: WP is not initialized.');
        }

        $instance = new static();
        $instance->loadEnvVars();
        $instance->installDb();
    }

    /**
     * @return void
     */
    public function executeBeforeFirstTest(): void
    {
        if (!defined('ABSPATH')) {
            return;
        }

        $options = getopt('', ["testsuite:", "filter:"]) ?: [];
        $filter = $options['filter'] ?? null;
        $suite = $options['testsuite'] ?? null;
        $args = $GLOBALS['argv'] ?? [];

        if (!$filter) {
            $filterKey = array_search('--filter', $args, true);
            $filter = (is_numeric($filterKey) && $filterKey >= 0)
                ? ($args[$filterKey + 1] ?? null)
                : null;
        }

        if (!$suite) {
            $suiteKey = array_search('--testsuite', $args, true);
            $suite = (is_numeric($suiteKey) && $suiteKey >= 0)
                ? ($args[$suiteKey + 1] ?? null)
                : null;
        }

        if ($suite && $suite !== 'integration') {
            return;
        }

        if (!$suite && $filter && stripos($filter, 'integration') === false) {
            return;
        }

        $this->initializeWp();
        static::$initialized = true;
    }

    /**
     * @return void
     */
    public function executeAfterLastTest(): void
    {
        if (!static::$initialized) {
            return;
        }

        $this->runWpCliCommand(['db', 'drop', '--yes']);
        @unlink(ABSPATH . 'wp-config.php');
    }

    /**
     * @return void
     */
    private function initializeWp(): void
    {
        [$dbHost, $dbName, $dbUser, $dbPwd] = $this->loadEnvVars();

        $this->runWpCliCommand(
            [
                'config',
                'create',
                "--dbname={$dbName}",
                "--dbuser={$dbUser}",
                "--dbpass={$dbPwd}",
                "--dbhost={$dbHost}",
                '--force',
            ]
        );

        $this->runWpCliCommand(['config', 'set', 'WP_DEBUG', 'true']);
        $this->runWpCliCommand(['config', 'set', 'WP_DEBUG_LOG', 'false']);
        $this->runWpCliCommand(['config', 'set', 'WP_DEBUG_DISPLAY', 'true']);
        $this->runWpCliCommand(['config', 'set', 'SAVEQUERIES', 'true']);

        $this->installDb();
    }

    /**
     * @return array
     */
    private function loadEnvVars(): array
    {
        $testsEnv = getenv('TESTS_DIR') . '/.env';
        if (file_exists($testsEnv) && !getenv('WORDPRESS_DB_NAME')) {
            (new Dotenv())->load($testsEnv);
        }

        $dbHost = getenv('WORDPRESS_DB_HOST');
        $dbName = getenv('WORDPRESS_DB_NAME');
        $dbUser = getenv('WORDPRESS_DB_USER');
        $dbPwd = getenv('WORDPRESS_DB_PASSWORD');

        if (!$dbHost || !$dbName || !$dbUser || !$dbPwd) {
            throw new \Exception('Could not initialize WP: missing env vars.');
        }

        return [$dbHost, $dbName, $dbUser, $dbPwd];
    }

    /**
     * @return void
     */
    private function installDb(): void
    {
        $this->runWpCliCommand(['db', 'reset', '--yes']);

        $this->runWpCliCommand(
            [
                'core',
                'install',
                '--url=localhost',
                '--title=DBAL Test',
                '--admin_user=admin',
                '--admin_password=secret',
                '--admin_email=info@example.com',
            ]
        );
    }

    /**
     * @param array $command
     * @return void
     */
    private function runWpCliCommand(array $command): void
    {
        static $cliPath;
        $cliPath or $cliPath = str_replace('\\', '/', getenv('VENDOR_DIR') . '/bin');

        array_unshift($command, 'wp');
        $command[] = "--path=" . str_replace('\\', '/', ABSPATH);
        $command[] = "--quiet";
        $command[] = "--skip-plugins";
        $command[] = "--skip-themes";
        $command[] = "--allow-root";

        [$dbHost, $dbName, $dbUser, $dbPwd] = $this->loadEnvVars();
        $env = [
            'WORDPRESS_DB_HOST' => $dbHost,
            'WORDPRESS_DB_NAME' => $dbName,
            'WORDPRESS_DB_USER' => $dbUser,
            'WORDPRESS_DB_PASSWORD' => $dbPwd,
        ];

        $process = new Process($command, (string)$cliPath, $env);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception($process->getErrorOutput());
        }
    }
}
