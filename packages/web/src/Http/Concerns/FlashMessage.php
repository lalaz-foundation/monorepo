<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Concerns;

use Lalaz\Web\Http\SessionManager;

/**
 * Flash message functionality for controllers and views.
 *
 * Flash messages are session-stored messages that are automatically
 * cleared after being displayed once.
 *
 * @package lalaz/web
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
trait FlashMessage
{
    /**
     * Session key for flash messages storage.
     */
    public const FLASH = 'FLASH_MESSAGES';

    /**
     * Flash message type: error.
     */
    public const FLASH_ERROR = 'error';

    /**
     * Flash message type: warning.
     */
    public const FLASH_WARNING = 'warning';

    /**
     * Flash message type: info.
     */
    public const FLASH_INFO = 'info';

    /**
     * Flash message type: success.
     */
    public const FLASH_SUCCESS = 'success';

    /**
     * Create a flash message.
     *
     * @param string $name Message identifier
     * @param string $message The message content
     * @param string $type Message type (error, warning, info, success)
     * @return void
     */
    public function createFlashMessage(string $name, string $message, string $type): void
    {
        $all = SessionManager::getValue(self::FLASH) ?? [];

        if (isset($all[$name])) {
            unset($all[$name]);
        }

        $all[$name] = [
            'message' => $message,
            'type' => $type,
        ];

        SessionManager::setValue(self::FLASH, $all);
    }

    /**
     * Get and remove a flash message (HTML escaped).
     *
     * @param string $name Message identifier
     * @return array{message: string, type: string}|false
     */
    public static function showFlashMessage(string $name): array|false
    {
        $all = SessionManager::getValue(self::FLASH) ?? [];

        if (!isset($all[$name])) {
            return false;
        }

        $flash_message = $all[$name];

        unset($all[$name]);
        SessionManager::setValue(self::FLASH, $all);

        if (isset($flash_message['message'])) {
            $flash_message['message'] = htmlspecialchars(
                $flash_message['message'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
        }

        if (isset($flash_message['type'])) {
            $flash_message['type'] = htmlspecialchars(
                $flash_message['type'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
        }

        return $flash_message;
    }

    /**
     * Get and remove a flash message (raw, not escaped).
     *
     * @param string $name Message identifier
     * @return array{message: string, type: string}|false
     */
    public static function showFlashMessageRaw(string $name): array|false
    {
        $all = SessionManager::getValue(self::FLASH) ?? [];

        if (!isset($all[$name])) {
            return false;
        }

        $flash_message = $all[$name];

        unset($all[$name]);
        SessionManager::setValue(self::FLASH, $all);

        return $flash_message;
    }

    /**
     * Check if a flash message exists.
     *
     * @param string $name Message identifier
     * @return bool
     */
    public static function hasFlashMessage(string $name): bool
    {
        $all = SessionManager::getValue(self::FLASH) ?? [];
        return isset($all[$name]);
    }

    /**
     * Create an error flash message.
     *
     * @param string $name Message identifier
     * @param string $message The message content
     * @return void
     */
    public function flashError(string $name, string $message): void
    {
        $this->createFlashMessage($name, $message, self::FLASH_ERROR);
    }

    /**
     * Create a warning flash message.
     *
     * @param string $name Message identifier
     * @param string $message The message content
     * @return void
     */
    public function flashWarning(string $name, string $message): void
    {
        $this->createFlashMessage($name, $message, self::FLASH_WARNING);
    }

    /**
     * Create an info flash message.
     *
     * @param string $name Message identifier
     * @param string $message The message content
     * @return void
     */
    public function flashInfo(string $name, string $message): void
    {
        $this->createFlashMessage($name, $message, self::FLASH_INFO);
    }

    /**
     * Create a success flash message.
     *
     * @param string $name Message identifier
     * @param string $message The message content
     * @return void
     */
    public function flashSuccess(string $name, string $message): void
    {
        $this->createFlashMessage($name, $message, self::FLASH_SUCCESS);
    }
}
