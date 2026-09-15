<?php

declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(private int $statusCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function defaultMessage(int $code): string
    {
        return match ($code) {
            400 => 'Bad request.',
            401 => 'You need to sign in to continue.',
            403 => 'You are not allowed to do that.',
            404 => 'The page you are looking for could not be found.',
            419 => 'Your session expired. Please try again.',
            429 => 'Too many requests. Please slow down.',
            503 => 'The website is temporarily under maintenance.',
            default => 'Something went wrong.',
        };
    }
}
