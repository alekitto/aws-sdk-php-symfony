<?php

declare(strict_types=1);

namespace Aws\Symfony\DependencyInjection;

use Aws;
use Aws\AwsClient;
use Aws\Symfony\AwsBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

class AwsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter('aws_sdk.class', Aws\Sdk::class);
        $container->register('aws_sdk', '%aws_sdk.class%')
            ->setArguments([null]);

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $this->inflateServicesInConfig($config);

        $container
            ->getDefinition('aws_sdk')
            ->replaceArgument(0, $config + ['ua_append' => [
                'Symfony/' . Kernel::VERSION,
                'SYMOD/' . AwsBundle::VERSION,
            ]]);

        foreach (array_column(Aws\manifest(), 'namespace') as $awsService) {
            $serviceName = 'aws.' . strtolower($awsService);
            $serviceDefinition = $this->createServiceDefinition($awsService);
            $container->setDefinition($serviceName, $serviceDefinition);

            $container->setAlias($serviceDefinition->getClass(), $serviceName);
        }
    }


    private function createServiceDefinition(string $name): Definition
    {
        $clientClass = "Aws\\{$name}\\{$name}Client";
        $serviceDefinition = new Definition(
            class_exists($clientClass) ? $clientClass : AwsClient::class
        );

        return $serviceDefinition
            ->setLazy(true)
            ->setFactory([new Reference('aws_sdk'), 'createClient'])
            ->setArguments([$name]);
    }

    private function inflateServicesInConfig(array &$config): void
    {
        array_walk($config, function (&$value) {
            if (is_array($value)) {
                $this->inflateServicesInConfig($value);
            }

            if (is_string($value) && str_starts_with($value, '@')) {
                // this is either a service reference or a string meant to
                // start with an '@' symbol. In any case, lop off the first '@'
                $value = substr($value, 1);
                if (! str_starts_with($value, '@')) {
                    // this is a service reference, not a string literal
                    $value = new Reference($value);
                }
            }
        });
    }
}
