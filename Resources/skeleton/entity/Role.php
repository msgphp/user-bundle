<?php

declare(strict_types=1);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

use Doctrine\\ORM\\Mapping as ORM;
use MsgPhp\\User\\Entity\\Role as BaseRole;

/**
 * @ORM\\Entity()
 *
 * @final
 */
class Role extends BaseRole
{
    /** @ORM\\Id() @ORM\\Column(length=%msgphp.doctrine.mapping_config.key_max_length%) */
    private \$name;

    public function __construct(string \$name)
    {
        \$this->name = \$name;
    }

    public function getName(): string
    {
        return \$this->name;
    }
}

PHP;
