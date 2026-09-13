<?php

namespace Dynamic\Calendar\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SilverStripe\Core\Injector\Injector;

/**
 * Logger Fallback Trait
 *
 * Routes a message through the injected Psr\Log\LoggerInterface, falling back to
 * error_log() only when that fails, so a broken logger can neither turn a normal code
 * path into a fatal error nor swallow the message entirely.
 *
 * Resolving the logger and writing to it are guarded separately and annotated
 * differently, because they fail for different reasons: 'logger service unavailable'
 * means Injector never produced a logger, 'logging failed' means it did and the fault
 * is downstream of Injector. Whether the message reached any handler before a handler
 * threw is not knowable from here, so the write-path fallback is written unconditionally
 * and must not be read as redundant - for a service that is not a logger at all, or a
 * first handler that throws, it is the only record there is. Both annotations carry the
 * level and the throw point for the same reason: they may be all that survives.
 *
 * This is the convention for the call sites that use it, not yet the whole module:
 * CalendarFilterForm still resolves the logger itself without this guard (its sites are
 * static, so adopting this trait there needs its own decision - filed separately).
 *
 * The level is validated against a whitelist and dispatched to the matching shorthand
 * method rather than passed to Psr\Log\LoggerInterface::log(), deliberately: Psr\Log
 * specifies that log() should throw on an unknown level, and this trait catches
 * Throwable, so a bad level routed through log() would be demoted to an error_log()
 * write and lose the injected-logger record - the opposite of what the guard is for.
 *
 * @package Dynamic\Calendar\Traits
 */
trait LoggerFallback
{
    /**
     * The levels Psr\Log\LoggerInterface accepts, taken from Psr\Log\LogLevel so this list
     * cannot drift from the installed psr/log. A level outside it is a caller mistake, not
     * a logging failure.
     */
    private const SUPPORTED_LOG_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * Log a message through the injected logger, falling back to error_log() if either
     * resolving that service or writing to it fails.
     *
     * @param string $message Message to log.
     * @param string $level Psr\Log\LogLevel constant, matched case-insensitively. Anything
     *                      else is reported through error() with the requested level named.
     * @return void
     */
    protected function logWithFallback(
        string $message,
        string $level = LogLevel::WARNING
    ): void {
        $requestedLevel = $level;
        $level = strtolower($level);

        if (!in_array($level, self::SUPPORTED_LOG_LEVELS, true)) {
            // A caller mistake, not a pipeline failure. Corrected before the service is
            // resolved so it is still named on the unresolvable path, where this is the
            // only record - and named as passed in, so it matches the caller's own source.
            $message .= sprintf(
                ' (requested log level \'%s\' is not a Psr\Log\LogLevel constant; logged at error)',
                $requestedLevel
            );
            $level = LogLevel::ERROR;
        }

        try {
            $logger = Injector::inst()->get(LoggerInterface::class);
        } catch (\Throwable $e) {
            $reason = sprintf(
                'logger service unavailable: %s: %s at %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            error_log($message . ' (' . strtoupper($level) . '; ' . $reason . ')');
            return;
        }

        try {
            $logger->{$level}($message);
        } catch (\Throwable $e) {
            $reason = sprintf(
                'logging failed: %s: %s at %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            error_log($message . ' (' . strtoupper($level) . '; ' . $reason . ')');
        }
    }
}
