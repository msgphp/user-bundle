<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $form_ns ?>;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\<?= $username_field_class = 'email' === $username_field ? 'EmailType' : 'TextType' ?>;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ForgotPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('<?= $username_field?>', <?= $username_field_class ?>::class, [
            'label' => 'label.username',
            'constraints' => [new NotBlank()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('user_mapping', ['<?= $username_field ?>' => 'user']);
    }
}
