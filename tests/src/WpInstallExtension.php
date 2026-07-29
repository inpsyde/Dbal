<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

final class WpInstallExtension implements BeforeFirstTestHook, AfterLastTestHook
{
    private static bool $initialized = false;

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

        $options = getopt('', ['testsuite:', 'filter:']);
        ($options === false) and $options = [];

        $filter = $options['filter'] ?? null;
        $suite = $options['testsuite'] ?? null;
        $args = $GLOBALS['argv'] ?? [];
        is_array($args) or $args = [];

        if ($filter === null) {
            $filterKey = array_search('--filter', $args, true);
            $filter = (is_numeric($filterKey) && ($filterKey >= 0))
                ? ($args[(int) $filterKey + 1] ?? null)
                : null;
        }
        is_string($filter) or $filter = null;

        if ($suite === null) {
            $suiteKey = array_search('--testsuite', $args, true);
            $suite = (is_numeric($suiteKey) && $suiteKey >= 0)
                ? ($args[(int) $suiteKey + 1] ?? null)
                : null;
        }
        is_string($suite) or $suite = null;

        if (($suite !== null) && ($suite !== 'integration')) {
            return;
        }

        if (($suite === null) && (stripos($filter ?? '', 'integration') === false)) {
            return;
        }

        $this->initializeWp();
        static::$initialized = true;
    }

    /**
     * @return void
     *
     * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
     */
    public function executeAfterLastTest(): void
    {
        if (!static::$initialized) {
            return;
        }

        // `--defaults` makes wp-cli load the mysql client's option file (`.my.cnf`),
        // which is where the SSL CA needed to reach the TLS-only test DB lives.
        $this->runWpCliCommand(['db', 'drop', '--yes', '--defaults']);
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
                // wp-cli's own connectivity check for this command shells out to
                // `mysql --no-defaults` and has no way to pass SSL options; the
                // `db reset` call right after this already verifies connectivity.
                '--skip-check',
            ]
        );

        $this->runWpCliCommand(['config', 'set', 'WP_DEBUG', 'true']);
        $this->runWpCliCommand(['config', 'set', 'WP_DEBUG_LOG', 'false']);
        $this->runWpCliCommand(['config', 'set', 'WP_DEBUG_DISPLAY', 'true']);
        $this->runWpCliCommand(['config', 'set', 'SAVEQUERIES', 'true']);
        $this->runWpCliCommand(['config', 'set', 'MYSQL_CLIENT_FLAGS', 'MYSQLI_CLIENT_SSL', '--raw']);

        // Without this dir, core's theme registration no-ops, and wp_is_block_theme()
        // trips a _doing_it_wrong() notice on every bootstrap since WP 6.8.
        $themesDir = ABSPATH . 'wp-content/themes';
        is_dir($themesDir) or mkdir($themesDir, 0777, true);

        $this->installDb();
    }

    /**
     * @return list{non-empty-string, non-empty-string, non-empty-string, string}
     */
    private function loadEnvVars(): array
    {
        $testsDir = getenv('TESTS_DIR');
        assert(is_string($testsDir) && is_dir($testsDir), new \Exception('Please set TESTS_DIR.'));

        $testsEnv = "{$testsDir}/.env";
        if (file_exists($testsEnv) && (getenv('WORDPRESS_DB_NAME') === false)) {
            (new Dotenv())->load($testsEnv);
        }

        $dbHost = getenv('WORDPRESS_DB_HOST');
        $dbName = getenv('WORDPRESS_DB_NAME');
        $dbUser = getenv('WORDPRESS_DB_USER');
        $dbPwd = getenv('WORDPRESS_DB_PASSWORD');

        if (
            ($dbHost === '')
            || ($dbName === '')
            || ($dbUser === '')
            || !is_string($dbHost)
            || !is_string($dbName)
            || !is_string($dbUser)
            || !is_string($dbPwd)
        ) {
            throw new \Exception('Could not initialize WP: missing env vars.');
        }

        return [$dbHost, $dbName, $dbUser, $dbPwd];
    }

    /**
     * @return void
     */
    private function installDb(): void
    {
        $this->runWpCliCommand(['db', 'reset', '--yes', '--defaults']);

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
        if (!isset($cliPath)) {
            $vendor = getenv('VENDOR_DIR');
            assert(is_string($vendor) && is_dir($vendor), new \Exception('Please set VENDOR_DIR'));
            $cliPath = str_replace('\\', '/', $vendor) . '/bin';
        }
        /** @var non-falsy-string $cliPath */

        array_unshift($command, "{$cliPath}/wp");
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

        $process = new Process($command, (string) $cliPath, $env);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception($process->getErrorOutput());
        }
    }
}
