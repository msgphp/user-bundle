<?php

declare(strict_types=1);

$uses = [
    'use '.$userClass.';',
    'use '.$userRoleClass.';',
    'use MsgPhp\User\Entity\User as BaseUser;',
    'use MsgPhp\User\Infra\Security\UserRolesProviderInterface;',
];

sort($uses);
$uses = implode("\n", $uses);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

${uses}

final class ${class} implements UserRolesProviderInterface
{
    /**
     * @param User \$user
     */
    public function getRoles(BaseUser \$user): array
    {
        return array_merge(['${defaultRole}'], \$user->getRoles()->map(function (UserRole \$userRole) {
            return \$userRole->getRoleName();
        }));
    }
}

PHP;
