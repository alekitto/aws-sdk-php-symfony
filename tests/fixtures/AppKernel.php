<?php

use Aws\Symfony\AwsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;

class AppKernel extends Kernel
{
    private $extension;

    public function __construct(string $env, bool $debug, string $extension = 'yml')
    {
        $this->extension = $extension;
        parent::__construct($env, $debug);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new AwsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->getTestConfigFile($this->extension));
    }

    public function getTestConfig(): mixed
    {
        return Yaml::parse(file_get_contents($this->getTestConfigFile('yml')));
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return __DIR__ . '/logs/' . $this->environment;
    }

    private function getTestConfigFile(string $extension): string
    {
        return __DIR__ . '/config.' . $extension;
    }
}
