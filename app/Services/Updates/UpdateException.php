<?php

declare(strict_types=1);

namespace App\Services\Updates;

/** Thrown at any failed step of the update pipeline; triggers automatic rollback. */
final class UpdateException extends \RuntimeException
{
}
