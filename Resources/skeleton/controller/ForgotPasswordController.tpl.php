<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $user_class ?>;
use <?= $form_ns ?>\ForgotPasswordType;
use MsgPhp\User\Command\RequestUserPassword;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @Route("/forgot-password", name="forgot_password")
 */
final class ForgotPasswordController
{
    public function __invoke(
        Request $request,
        FormFactoryInterface $formFactory,
        FlashBagInterface $flashBag,
        Environment $twig,
        MessageBusInterface $bus
    ): Response {
        $form = $formFactory->createNamed('', ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if (isset($data['user'])) {
                /** @var <?= $user_short_class ?> $user */
                $user = $data['user'];
                $bus->dispatch(new RequestUserPassword($user->getId()));
            }

            $flashBag->add('success', 'user.password_requested');

            return new RedirectResponse('/login');
        }

        return new Response($twig->render('<?= $template_dir ?>forgot_password.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
