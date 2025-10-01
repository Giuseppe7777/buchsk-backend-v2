<?php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class PasswordComplexity extends Constraint
{
    public string $message = 'Password is too weak: must be at least 8 characters long and include uppercase, lowercase, digit, and special character.';

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
