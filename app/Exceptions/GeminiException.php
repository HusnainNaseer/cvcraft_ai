<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Gemini call failed; the message is safe to show to the user.
 */
class GeminiException extends RuntimeException
{
}
