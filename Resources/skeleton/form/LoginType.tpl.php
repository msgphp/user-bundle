<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $form_ns ?>;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\<?= $username_field_class = 'email' === $username_field ? 'EmailType' : 'TextType' ?>;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;

final class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('<?= $username_field ?>', <?= $username_field_class ?>::class, [
                'label' => 'label.username',
            ])
            ->add('<?= $password_field ?>', PasswordType::class, [
                'label' => 'label.password',
            ])
        ;
    }
}
