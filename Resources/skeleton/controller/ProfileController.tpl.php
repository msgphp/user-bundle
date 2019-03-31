<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $controller_ns ?>;

use <?= $user_class ?>;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
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
    public function __invoke(<?= $user_short_class ?> $user, Environment $twig): Response
    {
        return new Response($twig->render('<?= $template_dir ?>profile.html.twig'));
    }
}
