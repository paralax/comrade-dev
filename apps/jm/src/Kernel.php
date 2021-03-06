<?php

namespace App;

use App\Infra\DependencyInjection\RegisterPvmBehaviorPass;
use App\Message\ExecuteJob;
use Makasim\Values\ObjectBuilderHook;
use function Makasim\Values\register_cast_hooks;
use function Makasim\Values\register_object_hooks;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function getCacheDir(): string
    {
        if ($cacheDir = getenv('APP_CACHE_DIR')) {
            return $cacheDir;
        }

        return dirname(__DIR__).'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        if ($logDir = getenv('APP_LOG_DIR')) {
            return $logDir;
        }

        return dirname(__DIR__).'/var/logs';
    }

    public function registerBundles(): iterable
    {
        $contents = require dirname(__DIR__).'/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    public function boot()
    {
        if (false == $this->booted) {
            $this->configureYadmHooks();
        }
        parent::boot();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $confDir = dirname(__DIR__).'/config';
        $loader->load($confDir.'/packages/*'.self::CONFIG_EXTS, 'glob');
        if (is_dir($confDir.'/packages/'.$this->environment)) {
            $loader->load($confDir.'/packages/'.$this->environment.'/**/*'.self::CONFIG_EXTS, 'glob');
        }
        $container->addCompilerPass(new RegisterPvmBehaviorPass());
        $loader->load($confDir.'/services'.self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $confDir = dirname(__DIR__).'/config';
        if (is_dir($confDir.'/routes/')) {
            $routes->import($confDir.'/routes/*'.self::CONFIG_EXTS, '/', 'glob');
        }
        if (is_dir($confDir.'/routes/'.$this->environment)) {
            $routes->import($confDir.'/routes/'.$this->environment.'/**/*'.self::CONFIG_EXTS, '/', 'glob');
        }
        $routes->import($confDir.'/routes'.self::CONFIG_EXTS, '/', 'glob');
    }

    public function configureYadmHooks()
    {
        register_cast_hooks();
        register_object_hooks();

        (new ObjectBuilderHook([
                // comrade service classes here
                \Formapro\Pvm\Process::SCHEMA => \App\Model\PvmProcess::class,
                \Formapro\Pvm\Token::SCHEMA => \App\Model\PvmToken::class,
                ExecuteJob::SCHEMA => ExecuteJob::class,

                // overwrites
                \Comrade\Shared\Model\JobResult::SCHEMA => \App\Model\JobResult::class,
                \Comrade\Shared\Model\JobTemplate::SCHEMA => \App\Model\JobTemplate::class,
                \Comrade\Shared\Model\Job::SCHEMA => \App\Model\Job::class,
                \Comrade\Shared\Model\SubJob::SCHEMA => \App\Model\SubJob::class,
                \Comrade\Shared\Model\RetryFailedPolicy::SCHEMA => \App\Model\RetryFailedPolicy::class,
                \Comrade\Shared\Model\RunSubJobsPolicy::SCHEMA => \App\Model\RunSubJobsPolicy::class,
        ]))->register();
    }
}
