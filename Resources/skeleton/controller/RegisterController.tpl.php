<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $form_ns ?>\RegisterType;
use MsgPhp\User\Command\CreateUser;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @Route("/register", name="register")
 */
final class RegisterController
{
    public function __invoke(
        Request $request,
        FormFactoryInterface $formFactory,
        FlashBagInterface $flashBag,
        Environment $twig,
        MessageBusInterface $bus
    ): Response {
        $form = $formFactory->createNamed('', RegisterType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bus->dispatch(new CreateUser($form->getData()));
            $flashBag->add('success', 'user.registered');

            return new RedirectResponse('<?= $controllers['login'] ? '/login' : '/' ?>');
        }

        return new Response($twig->render('<?= $template_dir ?>register.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
