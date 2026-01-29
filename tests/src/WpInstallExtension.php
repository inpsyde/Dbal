<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Tests;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
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

        @unlink(ABSPATH . 'wp-content/database/.ht.sqlite');
    }

    /**
     * @return void
     */
    private function initializeWp(): void
    {
        $this->installDb();
    }

    /**
     * @return void
     */
    private function installDb(): void
    {
        @unlink(ABSPATH . 'wp-content/database/.ht.sqlite');

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

        array_unshift($command, './wp');
        $command[] = "--path=" . str_replace('\\', '/', ABSPATH);
        $command[] = "--quiet";
        $command[] = "--skip-plugins";
        $command[] = "--skip-themes";
        $command[] = "--allow-root";

        $process = new Process($command, (string) $cliPath);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception($process->getErrorOutput());
        }
    }
}
