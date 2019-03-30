<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @Route("/profile", name="profile")
 */
final class ProfileController
{
    public function __invoke(Environment $twig): Response
    {
        return new Response($twig->render('<?= $template_dir ?>profile.html.twig'));
    }
}
