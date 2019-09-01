<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $form_ns ?>;

use MsgPhp\User\Infrastructure\Form\Type\HashedPasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('<?= $password_field ?>', HashedPasswordType::class, [
                'password_confirm' => true,
                'password_options' => [
                    'label' => 'label.password',
                    'constraints' => new NotBlank(),
                ],
                'password_confirm_options' => [
                    'label' => 'label.confirm_password',
                ],
            ])
        ;
    }
}
