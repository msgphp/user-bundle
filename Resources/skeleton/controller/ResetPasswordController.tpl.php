<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $user_class ?>;
use <?= $form_ns ?>\ResetPasswordType;
use MsgPhp\User\Command\ChangeUserCredential;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @Route("/reset-password/{token}", name="reset_password")
 */
final class ResetPasswordController
{
    public function __invoke(
        string $token,
        Request $request,
        FormFactoryInterface $formFactory,
        FlashBagInterface $flashBag,
        Environment $twig,
        MessageBusInterface $bus,
        EntityManagerInterface $em
    ): Response {
        $user = $em->getRepository(<?= $user_short_class ?>::class)->findOneBy(['passwordResetToken' => $token]);

        if (!$user instanceof <?= $user_short_class ?>) {
            throw new NotFoundHttpException();
        }

        $form = $formFactory->createNamed('', ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bus->dispatch(new ChangeUserCredential($user->getId(), $form->getData()));
            $flashBag->add('success', 'You\'re password is changed.');

            return new RedirectResponse('/login');
        }

        return new Response($twig->render('<?= $template_dir ?>reset_password.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
