<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $entity_ns ?>;

use Doctrine\ORM\Mapping as ORM;
use MsgPhp\User\Role as BaseRole;

/**
 * @ORM\Entity()
 *
 * @final
 */
class Role extends BaseRole
{
    /** @ORM\Id() @ORM\Column(length=<?= $entity_key_max_length ?>) */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
