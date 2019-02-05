<?php

declare(strict_types=1);

/*
 * This file is part of the MsgPHP package.
 *
 * (c) Roland Franssen <franssen.roland@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

use Doctrine\\ORM\\Mapping as ORM;
use MsgPhp\\User\\Entity\\UserRole as BaseUserRole;

/**
 * @ORM\\Entity()
 * @ORM\\AssociationOverrides({
 *     @ORM\\AssociationOverride(name="user", inversedBy="roles")
 * })
 *
 * @final
 */
class UserRole extends BaseUserRole
{
}

PHP;
