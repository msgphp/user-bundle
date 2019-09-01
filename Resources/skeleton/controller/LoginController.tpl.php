<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $form_ns ?>\LoginType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

/**
 * @Route("/login", name="login")
 */
final class LoginController
{
    public function __invoke(
        Environment $twig,
        FormFactoryInterface $formFactory,
        AuthenticationUtils $authenticationUtils
    ): Response {
        $form = $formFactory->createNamed('', LoginType::class, [
            '<?= $username_field ?>' => $authenticationUtils->getLastUsername(),
        ]);

        return new Response($twig->render('<?= $template_dir ?>login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'form' => $form->createView(),
        ]));
    }
}
