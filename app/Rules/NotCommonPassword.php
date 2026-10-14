<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Rejects passwords that appear at the top of every leaked-password list. */
class NotCommonPassword implements ValidationRule
{
    protected const COMMON = [
        'password', 'password1', 'password12', 'password123', 'passw0rd', 'p@ssword', 'p@ssw0rd', 'password!',
        '12345678', '123456789', '1234567890', '12341234', '11111111', '00000000', '87654321', '88888888',
        'qwertyui', 'qwerty123', 'qwertyuiop', 'asdfghjk', 'asdf1234', 'zxcvbnm1', '1q2w3e4r', '1qaz2wsx',
        'iloveyou', 'abcd1234', 'abc12345', 'aa123456', 'letmein1', 'welcome1', 'welcome123', 'sunshine',
        'princess', 'football', 'baseball', 'superman', 'trustno1', 'starwars', 'whatever', 'computer',
        'michelle', 'jennifer', 'corvette', 'mercedes', 'internet', 'changeme', 'changeme1', 'admin123',
        'administrator', 'secret123', 'taskflow', 'taskflow1', 'taskflow123',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, self::COMMON, true) || preg_match('/^(.)\1+$/', $normalized)) {
            $fail('That password is too common. Choose something harder to guess.');
        }
    }
}
