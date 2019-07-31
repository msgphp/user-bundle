<?= "<?php\n" ?>

declare(strict_types=1);

namespace App\Console;

use <?= $user_class ?>;
use <?= $credential_class ?>;
use MsgPhp\Domain\Infrastructure\Console\Context\ClassContextElementFactory as BaseClassContextElementFactory;
use MsgPhp\Domain\Infrastructure\Console\Context\ContextElement;
<?php if ($has_password): ?>
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
<?php endif; ?>

final class ClassContextElementFactory implements BaseClassContextElementFactory
{
<?php if ($has_password): ?>
    private $passwordHashing;

    public function __construct(PasswordEncoderInterface $passwordHashing)
    {
        $this->passwordHashing = $passwordHashing;
    }

<?php endif; ?>
    public function getElement(string $class, string $method, string $argument): ContextElement
    {
        $element = new ContextElement(ucfirst((string) preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\\1 \\2', '\\1 \\2'], $argument)));

        switch ($argument) {
<?php if ($has_password): ?>
            case '<?= $password_field ?>':
                if (<?= $user_short_class ?>::class === $class || <?= $credential_short_class ?>::class === $class) {
                    $element
                        ->hide()
                        ->generator(function (): string {
                            return bin2hex(random_bytes(8));
                        })
                        ->normalizer(function (string $value): string {
                            return $this->passwordHashing->encodePassword($value, null);
                        })
                    ;
                }
                break;
<?php endif; ?>
<?php if ('email' === $username_field): ?>
            case '<?= $username_field ?>':
                $element->label = 'E-mail';
                break;
<?php endif; ?>
        }

        return $element;
    }
}
