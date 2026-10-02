<?php
declare(strict_types=1);

namespace App;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(?string $given): bool
    {
        return is_string($given) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $given);
    }

    public static function verifyOrFail(?string $given): void
    {
        if (!self::check($given)) {
            http_response_code(400);
            exit('Your session expired. Go back, reload the page and try again.');
        }
    }
}
