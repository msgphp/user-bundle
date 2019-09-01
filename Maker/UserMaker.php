<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\Maker;

use MsgPhp\Domain\Event\DomainEventHandler;
use MsgPhp\Domain\Event\DomainEventHandlerTrait;
use MsgPhp\Domain\Infrastructure\DependencyInjection\FeatureDetection;
use MsgPhp\Domain\Infrastructure\Doctrine\MappingConfig;
use MsgPhp\User\Credential\Anonymous;
use MsgPhp\User\Credential\Credential;
use MsgPhp\User\Credential\EmailPassword;
use MsgPhp\User\Credential\PasswordProtectedCredential;
use MsgPhp\User\Credential\UsernameCredential;
use MsgPhp\User\Model\ResettablePassword;
use MsgPhp\User\Model\RolesField;
use MsgPhp\User\Role;
use MsgPhp\User\User;
use MsgPhp\User\UserRole;
use MsgPhp\UserBundle\DependencyInjection\Configuration;
use SebastianBergmann\Diff\Differ;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Encoder\SodiumPasswordEncoder;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class UserMaker implements MakerInterface
{
    private $kernel;
    private $classMapping;
    private $projectDir;
    private $mappingConfig;
    private $credential;
    private $credentials = [];
    private $defaultRole;
    private $passwordReset;
    private $configs = [];
    private $services = [];
    private $routes = [];
    private $writes = [];

    /** @var \ReflectionClass */
    private $user;

    public function __construct(KernelInterface $kernel, array $classMapping, string $projectDir, MappingConfig $mappingConfig)
    {
        $this->kernel = $kernel;
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
        $command
            ->setDescription('Configures user management')
            ->addOption('credential', null, InputOption::VALUE_REQUIRED, 'The credential type to use (e.g. "EmailPassword")')
            ->addOption('no-review', null, InputOption::VALUE_NONE, 'Skip file reviewing (use VCS instead)')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $this->user = new \ReflectionClass($this->classMapping[User::class]);
        $this->credential = $this->classMapping[Credential::class];
        $this->credentials = self::getCredentials();
        $this->defaultRole = null;
        $this->passwordReset = false;
        $this->configs = $this->services = $this->routes = $this->writes = [];

        if (null !== $credential = $input->getOption('credential')) {
            if (!isset($this->credentials[$credential])) {
                throw new \LogicException('Unknown credential "'.$credential.'", should be one of "'.implode('", "', array_keys($this->credentials)).'".');
            }
            if (Anonymous::class !== $this->credential) {
                throw new \LogicException('Cannot (re)configure credential "'.$credential.'", credential is already generated for "'.$this->credential.'".');
            }
            $this->credential = $this->credentials[$credential];
        }

        if (!$this->applicationPrerequisitesMet($io, $input)) {
            return;
        }
        if ($io->confirm('Enable Symfony Messenger configuration (recommended)? (config/packages/messenger.yaml)')) {
            $this->writes[] = [$this->projectDir.'/config/packages/messenger.yaml', $this->getSkeleton('messenger.tpl.php')];
        }

        $this->generateUser($io, $input);
        $this->generateControllers($io, $input);
        $this->generateConsole($io);

        if ($this->configs || $this->services) {
            array_unshift($this->writes, [$this->projectDir.'/config/packages/msgphp_user.make.php', $this->getSkeleton('config.tpl.php', [
                'config' => $this->configs ? var_export(array_merge_recursive(...$this->configs), true) : null,
                'services' => $this->services,
            ])]);
            $this->configs = $this->services = [];
        }
        if ($this->routes) {
            array_unshift($this->writes, [$this->projectDir.'/config/routes/user.php', $this->getSkeleton('routes.tpl.php', [
                'routes' => $this->routes,
            ])]);
            $this->routes = [];
        }
        if (!$this->writes) {
            return;
        }

        $io->success('All questions have been answered!');
        $io->note(\count($this->writes).' file(s) are about to be written');

        $review = !$input->getOption('no-review') && $io->confirm('Review changes? All changes will be written otherwise!', $input->isInteractive());
        $written = [];
        $writer = static function (string $file, string $contents) use ($io, &$written): void {
            if (!file_put_contents($file, $contents)) {
                $io->error('Cannot write changes to '.$file);

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
            if ($contents instanceof \Closure) {
                $contents = $contents();
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

        if (!$written) {
            $io->note('No files were written.');

            return;
        }

        if ($io->confirm('Show written file names?')) {
            sort($written);
            $io->listing($written);
        }

        $io->note('Don\'t forget to update your database schema, if needed');
    }

    private static function getConstructorSignature(\ReflectionClass $class): string
    {
        if (null === $constructor = $class->getConstructor()) {
            return '';
        }

        $lines = file($class->getFileName());
        $offset = $constructor->getStartLine() - 1;
        $body = implode('', \array_slice($lines, $offset, $constructor->getEndLine() - $offset));

        if (preg_match('~^[^_]*+__construct\(([^)]++)\)~i', $body, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function getSignatureVariables(string $signature): string
    {
        preg_match_all('~(?:\.{3})?\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*~', $signature, $matches);

        return isset($matches[0][0]) ? implode(', ', $matches[0]) : '';
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

    private static function getCredentials(): array
    {
        $credentials = [];
        foreach (Configuration::getPackageMetadata()->findPaths('Credential') as $path) {
            if ('.php' !== substr($path, -4) || !is_file($path) || Anonymous::class === ($credentialClass = Configuration::PACKAGE_NS.'Credential\\'.($credential = basename($path, '.php'))) || !is_subclass_of($credentialClass, Credential::class) || !class_exists($credentialClass, false)) {
                continue;
            }

            $credentials[$credential] = $credentialClass;
        }
        ksort($credentials);

        return $credentials;
    }

    private function applicationPrerequisitesMet(ConsoleStyle $io, InputInterface $input): bool
    {
        if (null === $container = $this->kernel->getContainer()) {
            throw new \RuntimeException('Kernel is shutdown.');
        }

        $met = true;
        if (!class_exists(Differ::class) && $input->isInteractive() && !$input->getOption('no-review')) {
            $io->note(['It\'s recommended to (temporarily) enable the Diff implementation for better reviewing changes, run:', 'composer require --dev sebastian/diff']);
            $met = false;
        }
        if (!FeatureDetection::isDoctrineOrmAvailable($container)) {
            $io->note(['It\'s recommended to enable Doctrine ORM, run:', 'composer require orm']);
            $met = false;
        }
        if (!FeatureDetection::isMessengerAvailable($container)) {
            $io->note(['It\'s recommended to enable Symfony Messenger, run:', 'composer require messenger']);
            $met = false;
        }

        return $met || $io->confirm('Continue anyway?', !$input->isInteractive());
    }

    private function generateUser(ConsoleStyle $io, InputInterface $input): void
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
        $enableEventHandler = static function () use ($implementors, $traits, &$addUses, &$addImplementors, &$addTraitUses): void {
            if (!isset($implementors[DomainEventHandler::class])) {
                $addUses[DomainEventHandler::class] = true;
                $addImplementors['DomainEventHandler'] = true;
            }
            if (!isset($traits[DomainEventHandlerTrait::class])) {
                $addUses[DomainEventHandlerTrait::class] = true;
                $addTraitUses['DomainEventHandlerTrait'] = true;
            }
        };

        if (Anonymous::class === $this->credential && $io->confirm('Generate a user credential?')) {
            $credential = $io->choice('Select credential type:', array_keys($this->credentials), self::splitClass(EmailPassword::class)[1]);
            $credentialClass = $this->credential = Configuration::PACKAGE_NS.'Credential\\'.$credential;
            $credentialTrait = Configuration::PACKAGE_NS.'Model\\'.($credentialTraitName = $credential.'Credential');
            $credentialSignature = self::getConstructorSignature(new \ReflectionClass($credentialClass));
            $credentialInit = '$this->credential = new '.$credential.'('.self::getSignatureVariables($credentialSignature).');';

            $addUses[$credentialClass] = true;
            if (!isset($traits[$credentialTrait])) {
                $addUses[$credentialTrait] = true;
                $addTraitUses[$credentialTraitName] = true;
                $enableEventHandler();
            }

            if (null !== $constructor = $this->user->getConstructor()) {
                $offset = $constructor->getStartLine() - 1;
                $length = $constructor->getEndLine() - $offset;
                $contents = preg_replace_callback_array([
                    '~^[^_]*+__construct\([^)]*+\)~i' => static function (array $match) use ($credentialSignature): string {
                        $signature = substr($match[0], 0, -1);
                        if ('' !== $credentialSignature) {
                            $signature .= ('(' !== substr(rtrim($signature), -1) ? ', ' : '').$credentialSignature;
                        }

                        return $signature.')';
                    },
                    '~\s*+}\s*+$~s' => static function ($match) use ($nl, $indent, $credential, $credentialInit): string {
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
                $constructor = array_map(static function (string $line) use ($nl, $indent): string {
                    return $indent.$line.$nl;
                }, explode("\n", <<<PHP
public function __construct({$credentialSignature})
{
    {$credentialInit}
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

            $this->configs[] = ['class_mapping' => [Credential::class => $credentialClass]];
        }

        if (!isset($traits[ResettablePassword::class]) && $this->hasPassword() && $io->confirm('Can users reset their password?')) {
            $addUses[ResettablePassword::class] = true;
            $addTraitUses['ResettablePassword'] = true;
            $enableEventHandler();
            $this->passwordReset = true;
        }

        $roleProviders = [];
        if (!isset($this->classMapping[Role::class]) && $io->confirm('Can users have assigned roles?')) {
            $baseDir = \dirname($this->user->getFileName());
            $baseNs = $this->user->getNamespaceName();
            $this->writes[] = [$baseDir.'/Role.php', $this->getSkeleton('entity/Role.tpl.php')];
            $this->writes[] = [$baseDir.'/UserRole.php', $this->getSkeleton('entity/UserRole.tpl.php')];
            $this->configs[] = ['class_mapping' => [
                Role::class => $baseNs.'\\Role',
                UserRole::class => $baseNs.'\\UserRole',
            ]];
            $roleProviders[] = Role\UserRoleProvider::class;

            if (!isset($traits[RolesField::class])) {
                $addUses[RolesField::class] = true;
                $addTraitUses['RolesField'] = true;
            }
        }

        $this->defaultRole = $io->ask('Provide a default user role', Configuration::DEFAULT_ROLE);

        $this->configs[] = ['role_providers' => ['default' => [$this->defaultRole]] + $roleProviders];

        if ($numUses = \count($addUses)) {
            ksort($addUses);
            $uses = array_map(static function (string $use) use ($nl): string {
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
            $traitUses = array_map(static function (string $use) use ($nl, $indent): string {
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

    private function controllerPrerequisitesMet(ConsoleStyle $io, InputInterface $input, ?array &$controllers): bool
    {
        $controllers = [];

        if (!$io->confirm('Generate controllers?')) {
            return false;
        }

        $controllers = [
            'registration' => $this->hasUsername() && $io->confirm('Add a registration controller?'),
            'login' => $this->hasUsername() && $this->hasPassword() && $io->confirm('Add a login and profile controller?'),
            'forgot_password' => $this->hasUsername() && $this->passwordReset && $io->confirm('Add a forgot and reset password controller?'),
        ];

        if (!array_filter($controllers)) {
            return false;
        }

        if (null === $container = $this->kernel->getContainer()) {
            throw new \RuntimeException('Kernel is shutdown.');
        }

        $prerequisites = [
            'annotation' => FeatureDetection::hasSensioFrameworkExtraBundle($container),
            'twig' => FeatureDetection::hasTwigBundle($container),
            'router' => FeatureDetection::isRouterAvailable($container),
            'form' => FeatureDetection::isFormAvailable($container),
            'validator' => FeatureDetection::isValidatorAvailable($container),
            'messenger' => FeatureDetection::isMessengerAvailable($container),
        ];
        if ($controllers['login']) {
            $prerequisites['security'] = FeatureDetection::hasSecurityBundle($container);
        }

        $prerequisites = array_filter($prerequisites, static function (bool $met): bool {
            return !$met;
        });
        if ($prerequisites) {
            $io->warning(['Not all controller dependencies are met, run:', 'composer require '.implode(' ', array_keys($prerequisites))]);

            return $io->confirm('Continue anyway?', !$input->isInteractive());
        }

        return true;
    }

    private function generateControllers(ConsoleStyle $io, InputInterface $input): void
    {
        if (!$this->controllerPrerequisitesMet($io, $input, $controllers)) {
            return;
        }

        $formNs = trim($io->ask('Provide the form namespace', 'App\\Form\\'), '\\');
        $controllerNs = trim($io->ask('Provide the controller namespace', 'App\\Controller\\'), '\\');
        $baseTemplate = ltrim($io->ask('Provide the base template file', 'base.html.twig'), '/');
        $baseTemplateBlock = $io->ask('Provide the template content block name', 'main');
        $templateDir = trim($io->ask('Provide the user template directory', 'user/'), '/');

        if ('' !== $templateDir) {
            $templateDir .= '/';
        }

        $vars = [
            'form_ns' => $formNs,
            'controller_ns' => $controllerNs,
            'template_dir' => $templateDir,
            'base_template' => $baseTemplate,
            'base_template_block' => $baseTemplateBlock,
            'controllers' => $controllers,
        ];

        $this->writes[] = [$this->projectDir.'/translations/messages+intl-icu.en.xlf', file_get_contents(\dirname(__DIR__).'/Resources/skeleton/translations/messages+intl-icu.en.xlf')];
        $this->writes[] = [$this->getTemplateFileName($baseTemplate), $this->getSkeleton('template/base.tpl.php', $vars)];
        $this->writes[] = [$this->getTemplateFileName('partials/flash-messages.html.twig'), $this->getSkeleton('template/flash-messages.tpl.php', $vars)];

        if ($controllers['registration']) {
            $this->writes[] = [$this->getClassFileName($formNs.'\\RegisterType'), $this->getSkeleton('form/RegisterType.tpl.php', $vars)];
            $this->writes[] = [$this->getClassFileName($controllerNs.'\\RegisterController'), $this->getSkeleton('controller/RegisterController.tpl.php', $vars)];
            $this->writes[] = [$this->getTemplateFileName($templateDir.'register.html.twig'), $this->getSkeleton('template/register.tpl.php', $vars)];
        }

        if ($controllers['login']) {
            $this->routes[] = <<<'PHP'
->add('logout', '/logout')
PHP;
            $this->writes[] = [$this->projectDir.'/config/packages/security.yaml', $this->getSkeleton('security.tpl.php')];

            $this->writes[] = [$this->getClassFileName($formNs.'\\LoginType'), $this->getSkeleton('form/LoginType.tpl.php', $vars)];
            $this->writes[] = [$this->getClassFileName($controllerNs.'\\LoginController'), $this->getSkeleton('controller/LoginController.tpl.php', $vars)];
            $this->writes[] = [$this->getTemplateFileName($templateDir.'login.html.twig'), $this->getSkeleton('template/login.tpl.php', $vars)];

            $this->writes[] = [$this->getClassFileName($formNs.'\\Change'.ucfirst($this->credential::getUsernameField()).'Type'), $this->getSkeleton('form/ChangeUsernameType.tpl.php', $vars)];
            if ($this->hasPassword()) {
                $this->writes[] = [$this->getClassFileName($formNs.'\\ChangePasswordType'), $this->getSkeleton('form/ChangePasswordType.tpl.php', $vars)];
            }
            $this->writes[] = [$this->getClassFileName($controllerNs.'\\ProfileController'), $this->getSkeleton('controller/ProfileController.tpl.php', $vars)];
            $this->writes[] = [$this->getTemplateFileName($templateDir.'profile.html.twig'), $this->getSkeleton('template/profile.tpl.php', $vars)];
        }

        if ($controllers['forgot_password']) {
            $this->writes[] = [$this->getClassFileName($formNs.'\\ForgotPasswordType'), $this->getSkeleton('form/ForgotPasswordType.tpl.php', $vars)];
            $this->writes[] = [$this->getClassFileName($controllerNs.'\\ResetPasswordController'), $this->getSkeleton('controller/ResetPasswordController.tpl.php', $vars)];
            $this->writes[] = [$this->getTemplateFileName($templateDir.'forgot_password.html.twig'), $this->getSkeleton('template/forgot_password.tpl.php', $vars)];

            $this->writes[] = [$this->getClassFileName($formNs.'\\ResetPasswordType'), $this->getSkeleton('form/ResetPasswordType.tpl.php', $vars)];
            $this->writes[] = [$this->getClassFileName($controllerNs.'\\ForgotPasswordController'), $this->getSkeleton('controller/ForgotPasswordController.tpl.php', $vars)];
            $this->writes[] = [$this->getTemplateFileName($templateDir.'reset_password.html.twig'), $this->getSkeleton('template/reset_password.tpl.php', $vars)];
        }
    }

    private function generateConsole(ConsoleStyle $io): void
    {
        $this->writes[] = [$this->getClassFileName('App\\Console\\ClassContextElementFactory'), $this->getSkeleton('service/ClassContextElementFactory.tpl.php')];
        $this->services[] = <<<'PHP'
->set(App\Console\ClassContextElementFactory::class)
->alias(MsgPhp\Domain\Infrastructure\Console\Context\ClassContextElementFactory::class, App\Console\ClassContextElementFactory::class)
PHP;
    }

    private function getSkeleton(string $path, array $vars = []): \Closure
    {
        return function () use ($path, $vars): string {
            extract($vars + $this->getDefaultTemplateVars(), \EXTR_OVERWRITE);

            ob_start();
            require \dirname(__DIR__).'/Resources/skeleton/'.$path;

            return ob_get_clean();
        };
    }

    private function getTemplateFileName(string $path): string
    {
        return $this->projectDir.'/templates/'.$path;
    }

    private function getDefaultTemplateVars(): array
    {
        return [
            'entity_ns' => $this->user->getNamespaceName(),
            'entity_key_max_length' => $this->mappingConfig->keyMaxLength,
            'user_class' => $this->user->getName(),
            'user_short_class' => $this->user->getShortName(),
            'credential_class' => $this->credential,
            'credential_short_class' => self::splitClass($this->credential)[1],
            'has_username' => $hasUsername = $this->hasUsername(),
            'username_field' => $hasUsername ? $this->credential::getUsernameField() : null,
            'has_password' => $hasPassword = $this->hasPassword(),
            'password_field' => $hasPassword ? $this->credential::getPasswordField() : null,
            'hashing' => class_exists(SodiumPasswordEncoder::class) ? 'auto' : 'argon2i',
            'default_role' => $this->defaultRole,
        ];
    }

    private function getClassFileName(string $class): string
    {
        if (0 === strpos($class, 'App\\')) {
            $class = substr($class, 4);
        }

        return $this->projectDir.'/src/'.str_replace('\\', '/', $class).'.php';
    }

    private function hasUsername(): bool
    {
        return is_subclass_of($this->credential, UsernameCredential::class);
    }

    private function hasPassword(): bool
    {
        return is_subclass_of($this->credential, PasswordProtectedCredential::class);
    }
}
