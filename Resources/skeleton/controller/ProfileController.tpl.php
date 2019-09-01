<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $user_class ?>;
use <?= $form_ns ?>\Change<?= ucfirst($username_field) ?>Type;
<?php if ($has_password): ?>
use <?= $form_ns ?>\ChangePasswordType;
<?php endif; ?>
use MsgPhp\User\Command\ChangeUserCredential;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @Route("/profile", name="profile")
 */
final class ProfileController
{
    /**
     * @ParamConverter("user", converter="msgphp.current_user")
     */
    public function __invoke(
        <?= $user_short_class ?> $user,
        Request $request,
        FormFactoryInterface $formFactory,
        FlashBagInterface $flashBag,
        Environment $twig,
        MessageBusInterface $bus
    ): Response {
        $usernameForm = $formFactory->create(Change<?= ucfirst($username_field) ?>Type::class);
        $usernameForm->handleRequest($request);

        if ($usernameForm->isSubmitted() && $usernameForm->isValid()) {
            $bus->dispatch(new ChangeUserCredential($user->getId(), $usernameForm->getData()));
            $flashBag->add('success', 'user.username_changed');

            return new RedirectResponse('/profile');
        }

<?php if ($has_password): ?>
        $passwordForm = $formFactory->create(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $bus->dispatch(new ChangeUserCredential($user->getId(), $passwordForm->getData()));
            $flashBag->add('success', 'user.password_changed');

            return new RedirectResponse('/profile');
        }
<?php endif; ?>

        return new Response($twig->render('<?= $template_dir ?>profile.html.twig', [
            'username_form' => $usernameForm->createView(),
<?php if ($has_password): ?>
            'password_form' => $passwordForm->createView(),
<?php endif; ?>
        ]));
    }
}
