<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $form_ns ?>;

use MsgPhp\User\Infrastructure\Validator\UniqueUsername;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\<?= $username_field_class = 'email' === $username_field ? 'EmailType' : 'TextType' ?>;
use Symfony\Component\Form\FormBuilderInterface;
<?php if ('email' === $username_field): ?>
use Symfony\Component\Validator\Constraints\Email;
<?php endif; ?>
use Symfony\Component\Validator\Constraints\NotBlank;

final class Change<?= ucfirst($username_field) ?>Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('<?= $username_field?>', <?= $username_field_class ?>::class, [
            'label' => 'label.username',
            'constraints' => [new NotBlank(), <?= 'email' === $username_field ? 'new Email(), ' : '' ?>new UniqueUsername()],
        ]);
    }
}
