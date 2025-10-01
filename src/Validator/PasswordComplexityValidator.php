<?php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PasswordComplexityValidator extends ConstraintValidator
{
    // мінімум 8 символів; хоча б одна мала, одна велика літера, одна цифра, один спецсимвол
    private const REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\p{L}\p{N}]).{8,}$/u';

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordComplexity) {
            return;
        }

        if (!\is_string($value) || $value === '') {
            return; // порожнє значення перевіряють інші валідатори (NotBlank)
        }

        if (!\preg_match(self::REGEX, $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
