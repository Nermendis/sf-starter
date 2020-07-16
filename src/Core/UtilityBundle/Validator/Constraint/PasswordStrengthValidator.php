<?php


namespace Core\UtilityBundle\Validator\Constraint;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;


/**
 * Password strength Validation.
 *
 * Validates if the password strength is equal or higher
 * to the required minimum and the password length is equal
 * or longer than the minimum length.
 *
 * The strength is computed from various measures including
 * length and usage of characters.
 *
 * The strengths are marked up as follow.
 *  1: Very Weak
 *  2: Weak
 *  3: Medium
 *  4: Strong
 *  5: Very Strong
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 * @author Shouvik Chatterjee <mailme@shouvik.net>
 */
class PasswordStrengthValidator extends ConstraintValidator
{
    private static $levelToLabel = [
        1 => 'very_weak',
        2 => 'weak',
        3 => 'medium',
        4 => 'strong',
        5 => 'very_strong',
    ];
    /** @var TranslatorInterface */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }


    /**
     * @param string|null                 $password
     * @param PasswordStrength|Constraint $constraint
     */
    public function validate($password, Constraint $constraint)
    {
        if (null === $password || '' === $password) {
            return;
        }

        if (!is_scalar($password) && !(is_object($password) && method_exists($password, '__toString'))) {
            throw new UnexpectedTypeException($password, 'string');
        }

        $password = (string) $password;
        $passLength = mb_strlen($password);

        if ($passLength < $constraint->minLength) {
            $this->context->buildViolation($constraint->tooShortMessage)
                ->setParameters(['{{length}}' => $constraint->minLength])
                ->addViolation();

            return;
        }

        $tips = [];

        if ($constraint->unicodeEquality) {
            $passwordStrength = $this->calculateStrengthUnicode($password, $tips);
        } else {
            $passwordStrength = $this->calculateStrength($password, $tips);
        }

        if ($passLength > 12) {
            ++$passwordStrength;
        } else {
            $tips[] = 'length';
        }

        // There is no decrease of strength on weak combinations.
        // Detecting this is tricky and requires a deep understanding of the syntax.

        if ($passwordStrength < $constraint->minStrength) {
            $parameters = [
                '{{ length }}' => $constraint->minLength,
                '{{ min_strength }}' => $this->translator->trans('rollerworks_password.strength_level.'.self::$levelToLabel[$constraint->minStrength], [], 'validators'),
                '{{ current_strength }}' => $this->translator->trans('rollerworks_password.strength_level.'.self::$levelToLabel[$passwordStrength], [], 'validators'),
                '{{ strength_tips }}' => implode(', ', array_map([$this, 'translateTips'], $tips)),
            ];

            $this->context->buildViolation($constraint->message)
                ->setParameters($parameters)
                ->addViolation();
        }
    }

    /**
     * @internal
     */
    public function translateTips($tip)
    {
        return $this->translator->trans('rollerworks_password.tip.'.$tip, [], 'validators');
    }

    private function calculateStrength($password, &$tips)
    {
        $passwordStrength = 0;

        if (preg_match('/[a-zA-Z]/', $password)) {
            ++$passwordStrength;

            if (!preg_match('/[a-z]/', $password)) {
                $tips[] = 'lowercase_letters';
            } elseif (preg_match('/[A-Z]/', $password)) {
                ++$passwordStrength;
            } else {
                $tips[] = 'uppercase_letters';
            }
        } else {
            $tips[] = 'letters';
        }

        if (preg_match('/\d+/', $password)) {
            ++$passwordStrength;
        } else {
            $tips[] = 'numbers';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            ++$passwordStrength;
        } else {
            $tips[] = 'special_chars';
        }

        return $passwordStrength;
    }

    private function calculateStrengthUnicode($password, &$tips)
    {
        $passwordStrength = 0;

        if (preg_match('/\p{L}/u', $password)) {
            ++$passwordStrength;

            if (!preg_match('/\p{Ll}/u', $password)) {
                $tips[] = 'lowercase_letters';
            } elseif (preg_match('/\p{Lu}/u', $password)) {
                ++$passwordStrength;
            } else {
                $tips[] = 'uppercase_letters';
            }
        } else {
            $tips[] = 'letters';
        }

        if (preg_match('/\p{N}/u', $password)) {
            ++$passwordStrength;
        } else {
            $tips[] = 'numbers';
        }

        if (preg_match('/[^\p{L}\p{N}]/u', $password)) {
            ++$passwordStrength;
        } else {
            $tips[] = 'special_chars';
        }

        return $passwordStrength;
    }
}
