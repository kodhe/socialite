<?php

declare(strict_types=1);

/**
 * Standalone runner for the Kodhe Socialite component.
 *
 * Usage:  php socialite/tests/run.php
 *
 * Works without composer/phpunit: it registers a PSR-4 autoloader for
 * Kodhe\Framework\Socialite, shims PHPUnit's TestCase with the handful
 * of assertions used by SocialiteTest, then runs every test* method.
 */

namespace {
    error_reporting(E_ALL);

    $root = dirname(__DIR__);

    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Kodhe\\Framework\\Socialite\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $path = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });

    require $root . '/src/helpers.php';
}

/* ----------------------------------------------------------------------
 | Minimal PHPUnit shim (only what SocialiteTest uses)
 * ------------------------------------------------------------------- */
namespace PHPUnit\Framework {
    if (! class_exists(TestCase::class, false)) {
        class AssertionFailedError extends \Exception
        {
        }

        abstract class TestCase
        {
            protected ?string $expectedException = null;
            protected ?string $expectedMessage = null;

            public static function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
            {
                if ($expected !== $actual) {
                    throw new AssertionFailedError(($msg ?: 'assertSame')
                        . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
                }
            }

            public static function assertTrue(mixed $v, string $msg = ''): void
            {
                if ($v !== true) {
                    throw new AssertionFailedError($msg ?: 'assertTrue failed');
                }
            }

            public static function assertNotEmpty(mixed $v, string $msg = ''): void
            {
                if (empty($v)) {
                    throw new AssertionFailedError($msg ?: 'assertNotEmpty failed');
                }
            }

            public static function assertContains(mixed $needle, array $haystack, string $msg = ''): void
            {
                if (! in_array($needle, $haystack, true)) {
                    throw new AssertionFailedError($msg ?: 'assertContains failed');
                }
            }

            public static function assertNotContains(mixed $needle, array $haystack, string $msg = ''): void
            {
                if (in_array($needle, $haystack, true)) {
                    throw new AssertionFailedError($msg ?: 'assertNotContains failed');
                }
            }

            public static function assertInstanceOf(string $class, mixed $object, string $msg = ''): void
            {
                if (! $object instanceof $class) {
                    throw new AssertionFailedError($msg ?: "assertInstanceOf {$class} failed");
                }
            }

            public static function assertStringStartsWith(string $prefix, string $subject, string $msg = ''): void
            {
                if (! str_starts_with($subject, $prefix)) {
                    throw new AssertionFailedError($msg ?: "assertStringStartsWith failed: {$subject}");
                }
            }

            public static function assertStringContainsString(string $needle, string $subject, string $msg = ''): void
            {
                if (! str_contains($subject, $needle)) {
                    throw new AssertionFailedError($msg ?: "assertStringContainsString <{$needle}> failed in: {$subject}");
                }
            }

            public function expectException(string $class): void
            {
                $this->expectedException = $class;
            }

            public function expectExceptionMessage(string $message): void
            {
                $this->expectedMessage = $message;
            }

            /** Runner used by run.php below. */
            public function runAll(): array
            {
                $results = [];

                foreach (get_class_methods($this) as $method) {
                    if (! str_starts_with($method, 'test')) {
                        continue;
                    }

                    $this->expectedException = null;
                    $this->expectedMessage   = null;

                    try {
                        $this->{$method}();

                        if ($this->expectedException !== null) {
                            $results[$method] = "FAIL (expected {$this->expectedException} was not thrown)";
                            continue;
                        }

                        $results[$method] = 'PASS';
                    } catch (\Throwable $e) {
                        if ($this->expectedException && $e instanceof $this->expectedException) {
                            if ($this->expectedMessage && ! str_contains($e->getMessage(), $this->expectedMessage)) {
                                $results[$method] = "FAIL (wrong message: {$e->getMessage()})";
                            } else {
                                $results[$method] = 'PASS (expected ' . $e::class . ')';
                            }
                        } else {
                            $results[$method] = 'FAIL (' . $e->getMessage() . ')';
                        }
                    }
                }

                return $results;
            }
        }
    }
}

/* ----------------------------------------------------------------------
 | Run the suite
 * ------------------------------------------------------------------- */
namespace {
    require __DIR__ . '/SocialiteTest.php';

    $test = new Kodhe\Framework\Socialite\Tests\SocialiteTest();

    $failed = 0;

    foreach ($test->runAll() as $name => $result) {
        if (str_starts_with($result, 'FAIL')) {
            $failed++;
        }

        printf("%-6s %s\n", str_starts_with($result, 'PASS') ? '✓' : '✗', $name . ' — ' . $result);
    }

    echo $failed === 0 ? "\nALL TESTS PASSED\n" : "\n{$failed} TEST(S) FAILED\n";

    exit($failed === 0 ? 0 : 1);
}
