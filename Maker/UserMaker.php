<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\Maker;

use Doctrine\ORM\EntityManagerInterface;
use MsgPhp\Domain\Event\{DomainEventHandlerInterface, DomainEventHandlerTrait};
use MsgPhp\Domain\Infra\Doctrine\MappingConfig;
use MsgPhp\User\{CredentialInterface, Entity, Role};
use MsgPhp\User\Password\PasswordAlgorithm;
use MsgPhp\UserBundle\DependencyInjection\Configuration;
use SebastianBergmann\Diff\Differ;
use Sensio\Bundle\FrameworkExtraBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class UserMaker implements MakerInterface
{
    private $classMapping;
    private $projectDir;
    private $mappingConfig;
    private $credential;
    private $passwordReset = false;
    private $configs = [];
    private $services = [];
    private $routes = [];
    private $writes = [];
    private $interactive = false;

    /** @var \ReflectionClass */
    private $user;

    public function __construct(array $classMapping, string $projectDir, MappingConfig $mappingConfig)
    {
        $this->classMapping = $classMapping;
        $this->projectDir = $projectDir;
        $this->mappingConfig = $mappingConfig;
    }

    public static function getCommandName(): string
    {
        return 'make:user:msgphp';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $this->credential = $this->user = null;
        $this->passwordReset = false;
        $this->configs = $this->services = $this->routes = $this->writes = [];
        $this->interactive = $input->isInteractive();

        if (!isset($this->classMapping[Entity\User::class])) {
            throw new \LogicException('User class not configured. Did you install the bundle using Symfony Recipes?');
        }

        $continue = true;
        if (!class_exists(Differ::class)) {
            $io->note(['It\'s recommended to (temporarily) enable the Diff implementation for better reviewing changes, run:', 'composer require --dev sebastian/diff']);
            $continue = false;
        }
        if (!interface_exists(EntityManagerInterface::class)) {
            $io->note(['It\'s recommended to enable Doctrine ORM, run:', 'composer require orm']);
            $continue = false;
        }

        if (!interface_exists(MessageBusInterface::class)) {
            $io->note(['It\'s recommended to enable Symfony Messenger, run:', 'composer require messenger']);
            $continue = false;
        }

        if (!$continue && !$io->confirm('Continue anyway?', false)) {
            return;
        }

        if ($io->confirm('Enable Symfony Messenger configuration (recommended)? (config/packages/messenger.yaml)')) {
            $this->writes[] = [$this->projectDir.'/config/packages/messenger.yaml', self::getSkeleton('messenger.php')];
        }

        $this->user = new \ReflectionClass($this->classMapping[Entity\User::class]);

        $this->generateUser($io);
        $this->generateControllers($io);
        $this->generateConsole($io);

        if ($this->configs || $this->services) {
            array_unshift($this->writes, [$this->projectDir.'/config/packages/msgphp_user.make.php', self::getSkeleton('config.php', [
                'config' => $this->configs ? var_export(array_merge_recursive(...$this->configs), true) : null,
                'services' => $this->services,
            ])]);
            $this->configs = $this->services = [];
        }

        if ($this->routes) {
            array_unshift($this->writes, [$this->projectDir.'/config/routes/user.php', self::getSkeleton('routes.php', [
                'routes' => $this->routes,
            ])]);
            $this->routes = [];
        }

        if (!$this->writes) {
            return;
        }

        $io->success('All questions have been answered!');
        $io->note(\count($this->writes).' file(s) are about to be written');

        $review = $io->confirm('Review changes? All changes will be written otherwise!');
        $written = [];
        $writer = function (string $file, string $contents) use ($io, &$written): void {
            if (!file_put_contents($file, $contents)) {
                $io->error(sprintf('Cannot write changes to "%s"', $file));

                return;
            }

            $written[] = $file;
        };
        $differ = class_exists(Differ::class) ? new Differ() : null;
        $choices = ['y' => 'Yes', 'r' => 'No, show this review once more', 'n' => 'No, skip this file and continue'];

        while ($write = array_shift($this->writes)) {
            [$file, $contents] = $write;

            if (!is_dir($parent = \dirname($file))) {
                mkdir($parent, 0777, true);
            }

            if (!$review) {
                $writer($file, $contents);
                continue;
            }

            do {
                $io->text('<info>'.(($exist = file_exists($file)) ? '[changed file]' : '[new file]').'</> '.$file);
                if (null === $differ) {
                    if ($exist) {
                        $io->writeln(['--- Original', file_get_contents($file), '+++ New', $contents]);
                    } else {
                        $io->writeln($contents);
                    }
                } else {
                    $io->writeln($differ->diff($exist ? file_get_contents($file) : '', $contents));
                }
            } while ('r' === $choice = $io->choice('Write changes and continue reviewing?', $choices, $choices['r']));

            if ('y' === $choice) {
                $writer($file, $contents);
            }
        }

        $io->success('Done!');
        $io->note('Don\'t forget to update your database schema, if needed');

        if ($written && $io->confirm('Show written file names?')) {
            sort($written);
            $io->listing($written);
        }
    }

    private function generateUser(ConsoleStyle $io): void
    {
        $lines = file($fileName = $this->user->getFileName());
        $traits = array_flip($this->user->getTraitNames());
        $implementors = array_flip($this->user->getInterfaceNames());
        $inClass = $inClassBody = $hasUses = $hasTraitUses = $hasImplements = false;
        $useLine = $traitUseLine = $implementsLine = $constructorLine = 0;
        $nl = null;
        $indent = '';

        foreach ($tokens = token_get_all(implode('', $lines)) as $i => $token) {
            if (!\is_array($token)) {
                if ('{' === $token && $inClass && !$inClassBody) {
                    $inClassBody = true;
                }
                continue;
            }

            if ($inClassBody) {
                if (!$traitUseLine) {
                    $traitUseLine = $token[2];
                }
            } else {
                if (!$useLine) {
                    $useLine = $token[2];
                }
            }

            if (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_WHITESPACE], true)) {
                if (\T_WHITESPACE === $token[0]) {
                    if (!$nl) {
                        $nl = \in_array($nl = trim($token[1], ' '), ["\n", "\r", "\r\n"], true) ? $nl : null;
                    }
                    if (!$indent && $inClassBody && $nl) {
                        $spaces = explode($nl, $token[1]);
                        $indent = end($spaces);
                    }
                }
                continue;
            }

            if (\T_NAMESPACE === $token[0] && !$useLine) {
                $useLine = $token[2];
            } elseif (\T_CLASS === $token[0] && !$inClass) {
                $inClass = true;
            } elseif (\T_USE === $token[0]) {
                if (!$inClass) {
                    $useLine = $token[2];
                    $hasUses = true;
                } else {
                    $traitUseLine = $token[2];
                    $hasTraitUses = true;
                }
            } elseif (\T_EXTENDS === $token[0] && $inClass) {
                $implementsLine = $tokens[2];
                $j = $i + 1;
                while (isset($tokens[$j])) {
                    if (isset($tokens[$j][0]) && \T_STRING === $tokens[$j][0]) {
                        $implementsLine = $tokens[$j][2];
                    } elseif ('{' === $tokens[$j] || (isset($tokens[$j][0]) && \T_IMPLEMENTS === $tokens[$j][0])) {
                        break;
                    }
                    ++$j;
                }
            } elseif (\T_IMPLEMENTS === $token[0] && $inClass) {
                $hasImplements = true;
                $implementsLine = $token[2];
                $j = $i + 1;
                while (isset($tokens[$j])) {
                    if (\is_array($tokens[$j]) && \T_STRING === $tokens[$j][0]) {
                        $implementsLine = $tokens[$j][2];
                    } elseif ('{' === $tokens[$j]) {
                        break;
                    }
                    ++$j;
                }
            } elseif (\T_FUNCTION === $token[0]) {
                $constructorLine = $token[2];
                $j = $i - 1;
                while (isset($tokens[$j])) {
                    if (\is_array($tokens[$j])) {
                        $constructorLine = $tokens[$j][2];
                    } elseif (';' === $tokens[$j] || '}' === $tokens[$j]) {
                        break;
                    }
                    --$j;
                }
            }
        }
        if (!$constructorLine) {
            $constructorLine = $traitUseLine;
        }

        $nl = $nl ?? \PHP_EOL;
        $addUses = $addTraitUses = $addImplementors = [];
        $write = false;
        $enableEventHandler = function () use ($implementors, &$addUses, &$addImplementors, &$addTraitUses): void {
            if (!isset($implementors[DomainEventHandlerInterface::class])) {
                $addUses[DomainEventHandlerInterface::class] = true;
                $addUses[DomainEventHandlerTrait::class] = true;
                $addImplementors['DomainEventHandlerInterface'] = true;
                $addTraitUses['DomainEventHandlerTrait'] = true;
            }
        };

        $this->credential = $this->classMapping[CredentialInterface::class] ?? null;

        if (!$this->hasCredential() && $io->confirm('Generate a user credential?')) {
            $credentials = [];
            foreach (Configuration::getPackageMetadata()->findPaths('Entity/Credential') as $path) {
                if ('.php' !== substr($path, -4) || !is_file($path)) {
                    continue;
                }
                if (!\in_array($credential = basename($path, '.php'), ['Anonymous', 'EmailSaltedPassword', 'NicknameSaltedPassword'], true)) {
                    $credentials[] = $credential;
                }
            }
            sort($credentials);

            $credential = $io->choice('Select credential type:', $credentials, 'EmailPassword');
            $credentialClass = $this->credential = Configuration::PACKAGE_NS.'Entity\\Credential\\'.$credential;
            $credentialTrait = Configuration::PACKAGE_NS.'Entity\\Features\\'.($credentialName = $credential.'Credential');
            $credentialSignature = self::getConstructorSignature(new \ReflectionClass($credentialClass));
            $credentialInit = '$this->credential = new '.$credential.'('.self::getSignatureVariables($credentialSignature).');';

            $addUses[$credentialClass] = true;
            if (!isset($traits[$credentialTrait])) {
                $addUses[$credentialTrait] = true;
                $addTraitUses[$credentialName] = true;
                $enableEventHandler();
            }

            if (null !== $constructor = $this->user->getConstructor()) {
                $offset = $constructor->getStartLine() - 1;
                $length = $constructor->getEndLine() - $offset;
                $contents = preg_replace_callback_array([
                    '~^[^_]*+__construct\([^\)]*+\)~i' => function (array $match) use ($credentialSignature): string {
                        $signature = substr($match[0], 0, -1);
                        if ('' !== $credentialSignature) {
                            $signature .= ('(' !== substr(rtrim($signature), -1) ? ', ' : '').$credentialSignature;
                        }

                        return $signature.')';
                    },
                    '~\s*+}\s*+$~s' => function ($match) use ($nl, $indent, $credential, $credentialInit): string {
                        $indent = ltrim(substr($match[0], 0, strpos($match[0], '}')), "\r\n").'    ';

                        return $nl.$indent.$credentialInit.$match[0];
                    },
                ], $oldContents = implode('', \array_slice($lines, $offset, $length)));

                if ($contents !== $oldContents) {
                    array_splice($lines, $offset, $length, $contents);
                    $write = true;
                    if ($traitUseLine > $offset + 1) {
                        ++$traitUseLine;
                    }
                }
            } else {
                $constructor = array_map(function (string $line) use ($nl, $indent): string {
                    return $indent.$line.$nl;
                }, explode("\n", <<<PHP
public function __construct(${credentialSignature})
{
    ${credentialInit}
}
PHP
                ));
                array_unshift($constructor, $nl);
                array_splice($lines, $constructorLine, 0, $constructor);
                $write = true;
                if ($traitUseLine > $constructorLine) {
                    $traitUseLine += 4;
                }
            }
        }

        $this->passwordReset = isset($traits[Entity\Features\ResettablePassword::class]);
        if (!$this->passwordReset && $this->hasPassword() && $io->confirm('Can users reset their password?')) {
            $addUses[Entity\Features\ResettablePassword::class] = true;
            $addTraitUses['ResettablePassword'] = true;
            $enableEventHandler();
            $this->passwordReset = true;
        }

        $roleProviders = [];
        if (!isset($this->classMapping[Entity\Role::class]) && $io->confirm('Can users have assigned roles?')) {
            $baseDir = \dirname($this->user->getFileName());
            $vars = ['ns' => $ns = $this->user->getNamespaceName()];

            $addUses[Entity\Fields\RolesField::class] = true;
            $addTraitUses['RolesField'] = true;

            $this->writes[] = [$baseDir.'/Role.php', $this->mappingConfig->interpolate(self::getSkeleton('entity/Role.php', $vars))];
            $this->writes[] = [$baseDir.'/UserRole.php', self::getSkeleton('entity/UserRole.php', $vars)];
            $this->configs[] = ['class_mapping' => [
                Entity\Role::class => $ns.'\\Role',
                Entity\UserRole::class => $userRoleClass = $ns.'\\UserRole',
            ]];
            $roleProviders[] = Role\UserRoleProvider::class;
        }

        $defaultRoles = [];
        do {
            do {
                $defaultRole = $io->ask('Provide a default user role (e.g. <comment>ROLE_USER</>)');
            } while (null === $defaultRole && $this->interactive);
            $defaultRoles[] = $defaultRole;
        } while ($io->confirm('Add another default user role?', false));

        $this->configs[] = ['role_providers' => ['default' => $defaultRoles] + $roleProviders];

//        if (!isset($traits[Features\CanBeEnabled::class]) && $io->confirm('Can users be enabled / disabled?')) {
//            $implementors[] = DomainEventHandlerInterface::class;
//            $addUses[Features\CanBeEnabled::class] = true;
//            $addTraitUses['CanBeEnabled'] = true;
//            $enableEventHandler();
//        }
//
//        if (!isset($traits[Features\CanBeConfirmed::class]) && $io->confirm('Can users be confirmed?')) {
//            $implementors[] = DomainEventHandlerInterface::class;
//            $addUses[Features\CanBeConfirmed::class] = true;
//            $addTraitUses['CanBeConfirmed'] = true;
//            $enableEventHandler();
//        }

        if ($numUses = \count($addUses)) {
            ksort($addUses);
            $uses = array_map(function (string $use) use ($nl): string {
                return 'use '.$use.';'.$nl;
            }, array_keys($addUses));
            if (!$hasUses) {
                $uses[] = $nl;
                ++$implementsLine;
                ++$traitUseLine;
            }
            array_splice($lines, $useLine, 0, $uses);
            $write = true;
            $implementsLine += $numUses;
            $traitUseLine += $numUses;
        }

        if ($numTraitUses = \count($addTraitUses)) {
            ksort($addTraitUses);
            $traitUses = array_map(function (string $use) use ($nl, $indent): string {
                return $indent.'use '.$use.';'.$nl;
            }, array_keys($addTraitUses));
            if (!$hasTraitUses) {
                $traitUses[] = $nl;
            }
            array_splice($lines, $traitUseLine, 0, $traitUses);
            $write = true;
        }

        if ($numImplementors = \count($addImplementors)) {
            ksort($addImplementors);
            $implements = ($hasImplements ? ', ' : ' implements ').implode(', ', array_keys($addImplementors));
            $lines[$implementsLine - 1] = preg_replace('~(\s*+{?\s*+)$~', $implements.'\\1', $lines[$implementsLine - 1], 1);
            $write = true;
        }

        if ($write) {
            $this->writes[] = [$fileName, implode('', $lines)];
        }
    }

    private function generateControllers(ConsoleStyle $io): void
    {
        if (!$this->credential || !$io->confirm('Generate controllers?')) {
            return;
        }

        if (
            !class_exists(AnnotatedRouteControllerLoader::class) ||
            !class_exists(Route::class) ||
            !interface_exists(FormInterface::class) ||
            !interface_exists(ValidatorInterface::class) ||
            !class_exists(Environment::class) ||
            !interface_exists(MessageBusInterface::class)
        ) {
            $io->warning(['Not all controller dependencies are met, run:', 'composer require annotations router form validator twig messenger']);

            if (!$io->confirm('Continue anyway?', false)) {
                return;
            }
        }

        $usernameField = $this->hasCredential() ? $this->credential::getUsernameField() : null;
        $nsForm = trim($io->ask('Provide the form namespace', 'App\\Form\\User\\'), '\\');
        $nsController = trim($io->ask('Provide the controller namespace', 'App\\Controller\\User\\'), '\\');
        $templateDir = trim($io->ask('Provide the base template directory', 'user/'), '/');
        $baseTemplate = ltrim($io->ask('Provide the base template file', 'base.html.twig'), '/');
        $baseTemplateBlock = $io->ask('Provide the base template block name', 'body');
        $hasRegistration = $this->hasCredential() && $io->confirm('Add a registration controller?');
        $hasForgotPassword = $this->passwordReset && $io->confirm('Add a forgot and reset password controller?');
        $hasLogin = $this->hasPassword() && $io->confirm('Add a login and profile controller?'); // keep last

        if ($hasLogin) {
            if (!class_exists(Security::class)) {
                $io->warning(['Not all controller dependencies are met, run:', 'composer require security']);

                if (!$io->confirm('Continue anyway?', false)) {
                    return;
                }
            }

            $this->routes[] = <<<'PHP'
->add('logout', '/logout')
PHP;
            $this->writes[] = [$this->projectDir.'/config/packages/security.yaml', self::getSkeleton('security.php', [
                'hashAlgorithm' => $this->getPasswordHashAlgorithm(),
                'fieldName' => $usernameField,
            ])];
        }

        if ($hasRegistration) {
            $this->writes[] = [$this->getClassFileName($nsForm.'\\RegisterType'), self::getSkeleton('form/RegisterType.php', [
                'hasPassword' => $this->hasPassword(),
                'ns' => $nsForm,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\RegisterController'), self::getSkeleton('controller/RegisterController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'fieldName' => $usernameField,
                'template' => $template = $templateDir.'/register.html.twig',
                'redirect' => $hasLogin ? '/login' : '/',
            ])];
            $this->writes[] = [$this->getTemplateFileName($template), self::getSkeleton('template/register.html.php', [
                'hasPassword' => $this->hasPassword(),
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
        }

        if ($hasLogin) {
            $this->writes[] = [$this->getClassFileName($nsForm.'\\LoginType'), self::getSkeleton('form/LoginType.php', [
                'ns' => $nsForm,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\LoginController'), self::getSkeleton('controller/LoginController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'fieldName' => $usernameField,
                'template' => $templateLogin = $templateDir.'/login.html.twig',
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\ProfileController'), self::getSkeleton('controller/ProfileController.php', [
                'ns' => $nsController,
                'template' => $templateProfile = $templateDir.'/profile.html.twig',
            ])];
            $this->writes[] = [$this->getTemplateFileName($templateLogin), self::getSkeleton('template/login.html.php', [
                'hasForgotPassword' => $hasForgotPassword,
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getTemplateFileName($templateProfile), self::getSkeleton('template/profile.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
        }

        if ($hasForgotPassword) {
            $this->writes[] = [$this->getClassFileName($nsForm.'\\ForgotPasswordType'), self::getSkeleton('form/ForgotPasswordType.php', [
                'ns' => $nsForm,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getClassFileName($nsForm.'\\ResetPasswordType'), self::getSkeleton('form/ResetPasswordType.php', [
                'ns' => $nsForm,
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\ForgotPasswordController'), self::getSkeleton('controller/ForgotPasswordController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'fieldName' => $usernameField,
                'userClass' => $this->user->getName(),
                'userShortClass' => $this->user->getShortName(),
                'template' => $templateForgot = $templateDir.'/forgot_password.html.twig',
            ])];
            $this->writes[] = [$this->getClassFileName($nsController.'\\ResetPasswordController'), self::getSkeleton('controller/ResetPasswordController.php', [
                'ns' => $nsController,
                'formNs' => $nsForm,
                'userClass' => $this->user->getName(),
                'userShortClass' => $this->user->getShortName(),
                'template' => $templateReset = $templateDir.'/reset_password.html.twig',
            ])];
            $this->writes[] = [$this->getTemplateFileName($templateForgot), self::getSkeleton('template/forgot_password.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
            $this->writes[] = [$this->getTemplateFileName($templateReset), self::getSkeleton('template/reset_password.html.php', [
                'base' => $baseTemplate,
                'block' => $baseTemplateBlock,
                'fieldName' => $usernameField,
            ])];
        }
    }

    private function generateConsole(ConsoleStyle $io): void
    {
        [$contextElementFactoryNs, $contextElementFactoryShortClass] = self::splitClass($contextElementFactoryClass = 'App\\Console\\ClassContextElementFactory');

        $this->writes[] = [$this->getClassFileName($contextElementFactoryClass), self::getSkeleton('service/ClassContextElementFactory.php', [
            'ns' => $contextElementFactoryNs,
            'class' => $contextElementFactoryShortClass,
            'userClass' => $this->user->getName(),
            'userShortClass' => $this->user->getShortName(),
            'credentialClass' => $this->credential,
            'credentialShortClass' => self::splitClass($this->credential)[1],
        ])];
        $this->services[] = <<<PHP
->set(${contextElementFactoryClass}::class)
->alias(MsgPhp\\Domain\\Infra\\Console\\Context\\ClassContextElementFactoryInterface::class, ${contextElementFactoryClass}::class)
PHP;
    }

    private static function getConstructorSignature(\ReflectionClass $class): string
    {
        if (null === $constructor = $class->getConstructor()) {
            return '';
        }

        $lines = file($class->getFileName());
        $offset = $constructor->getStartLine() - 1;
        $body = implode('', \array_slice($lines, $offset, $constructor->getEndLine() - $offset));

        if (preg_match('~^[^_]*+__construct\(([^\)]++)\)~i', $body, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function getSignatureVariables(string $signature): string
    {
        preg_match_all('~(?:\.{3})?\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*~', $signature, $matches);

        return isset($matches[0][0]) ? implode(', ', $matches[0]) : '';
    }

    private static function getSkeleton(string $path, array $vars = [])
    {
        return (function () use ($path, $vars) {
            extract($vars);

            return require \dirname(__DIR__).'/Resources/skeleton/'.$path;
        })();
    }

    private static function splitClass(string $class): array
    {
        $ns = 'App';

        if (false !== $i = strrpos($class, '\\')) {
            $ns = substr($class, 0, $i);
            $class = substr($class, $i + 1);
        }

        return [$ns, $class];
    }

    private function getTemplateFileName(string $path): string
    {
        return $this->projectDir.'/templates/'.$path;
    }

    private function getClassFileName(string $class): string
    {
        if ('App\\' === substr($class, 0, 4)) {
            $class = substr($class, 4);
        }

        return $this->projectDir.'/src/'.str_replace('\\', '/', $class).'.php';
    }

    private function hasCredential(): bool
    {
        return $this->credential && Entity\Credential\Anonymous::class !== $this->credential;
    }

    private function hasPassword(): bool
    {
        return $this->hasCredential() && false !== strpos($this->credential, 'Password');
    }

    private function getPasswordHashAlgorithm(): string
    {
        if ($this->hasCredential() && false !== strpos($this->credential, 'SaltedPassword')) {
            return PasswordAlgorithm::DEFAULT_LEGACY;
        }

        switch (\PASSWORD_DEFAULT) {
            case \defined('PASSWORD_ARGON2I') ? \PASSWORD_ARGON2I : 2:
                return 'argon2i';
            case \PASSWORD_BCRYPT:
            default:
                return 'bcrypt';
        }
    }
}
