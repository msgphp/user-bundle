<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $user_class ?>;
use <?= $form_ns ?>\ResetPasswordType;
use MsgPhp\User\Command\ResetUserPassword;
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
 * @Route("/reset-password/{token}", name="reset_password")
 */
final class ResetPasswordController
{
    /**
     * @ParamConverter("user", converter="doctrine.orm", options={"mapping": {"token": "passwordResetToken"}})
     */
    public function __invoke(
        <?= $user_short_class ?> $user,
        Request $request,
        FormFactoryInterface $formFactory,
        FlashBagInterface $flashBag,
        Environment $twig,
        MessageBusInterface $bus
    ): Response {
        $form = $formFactory->createNamed('', ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bus->dispatch(new ResetUserPassword($user->getId(), $form->getData()['<?= $password_field ?>']));
            $flashBag->add('success', 'user.password_changed');

            return new RedirectResponse('/login');
        }

        return new Response($twig->render('<?= $template_dir ?>reset_password.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
