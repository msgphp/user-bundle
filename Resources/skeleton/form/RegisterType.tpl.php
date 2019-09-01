<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $form_ns ?>;

<?php if ($has_password): ?>
use MsgPhp\User\Infrastructure\Form\Type\HashedPasswordType;
<?php endif; ?>
use MsgPhp\User\Infrastructure\Validator\UniqueUsername;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\<?= $username_field_class = 'email' === $username_field ? 'EmailType' : 'TextType' ?>;
use Symfony\Component\Form\FormBuilderInterface;
<?php if ('email' === $username_field): ?>
use Symfony\Component\Validator\Constraints\Email;
<?php endif; ?>
use Symfony\Component\Validator\Constraints\NotBlank;

final class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('<?= $username_field ?>', <?= $username_field_class ?>::class, [
                'label' => 'label.username',
                'constraints' => [new NotBlank(), <?= 'email' === $username_field ? 'new Email(), ' : '' ?>new UniqueUsername()],
            ])
<?php if ($has_password): ?>
            ->add('password', HashedPasswordType::class, [
                'password_confirm' => true,
                'password_options' => [
                    'label' => 'label.password',
                    'constraints' => new NotBlank()
                ],
                'password_confirm_options' => [
                    'label' => 'label.confirm_password',
                ],
            ])
<?php endif; ?>
        ;
    }
}
