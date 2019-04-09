<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $form_ns ?>;

use MsgPhp\User\Infrastructure\Form\Type\HashedPasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('current', HashedPasswordType::class, [
            'password_options' => ['constraints' => new UserPassword()],
            'mapped' => false,
        ]);
        $builder->add('<?= $password_field ?>', HashedPasswordType::class, [
            'password_confirm' => true,
            'password_options' => ['constraints' => new NotBlank()],
        ]);
    }
}
