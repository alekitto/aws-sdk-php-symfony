<?php

namespace Aws\Symfony\DependencyInjection;

use AppKernel;
use Aws\AwsClient;
use Aws\CodeDeploy\CodeDeployClient;
use Aws\Lambda\LambdaClient;
use Aws\S3\S3Client;
use Aws\Sdk;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AwsExtensionTest extends TestCase
{
    /**
     * @var AppKernel
     */
    protected $kernel;

    /**
     * @var ContainerInterface
     */
    protected $container;

    protected function setUp(): void
    {
        $this->kernel = new AppKernel('test', true);
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();
    }

    /**
     * @test
     */
    public function sdk_config_should_be_passed_directly_to_the_constructor_and_resolved_by_the_sdk()
    {
        $config           = $this->kernel->getTestConfig()['aws'];
        $s3Region         = $config['S3']['region'] ?? $config['region'];
        $lambdaRegion     = $config['Lambda']['region'] ?? $config['region'];
        $codeDeployRegion = $config['CodeDeploy']['region'] ?? $config['region'];

        $testService = $this->container->get('test_service');

        $this->assertSame($s3Region, $testService->getS3Client()->getRegion());
        $this->assertSame($lambdaRegion, $testService->getLambdaClient()->getRegion());
        $this->assertSame($codeDeployRegion, $testService->getCodeDeployClient()->getRegion());
    }

    /**
     * @test
     *
     */
    public function all_web_services_in_sdk_manifest_should_be_accessible_as_container_services() {
        $testService = $this->container->get('test_service');

        $this->assertInstanceOf(S3Client::class, $testService->getS3Client());
        $this->assertInstanceOf(LambdaClient::class, $testService->getLambdaClient());
        $this->assertInstanceOf(CodeDeployClient::class, $testService->getCodeDeployClient());

        foreach ($testService->getClients() as $client) {
            $this->assertInstanceOf(AwsClient::class, $client);
        }
    }

    /**
     * @test
     */
    public function extension_should_escape_strings_that_begin_with_at_sign()
    {
        $extension = new AwsExtension;
        $config = ['credentials' => [
            'key' => '@@key',
            'secret' => '@@secret'
        ]];

        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->onlyMethods(['getDefinition'])
            ->getMock();

        $definition = new Definition(Sdk::class, [null]);
        $container->expects($this->once())
            ->method('getDefinition')
            ->with('aws_sdk')
            ->willReturn($definition);

        $extension->load([$config], $container);

        $defArgument = $definition->getArgument(0);
        self::assertIsArray($defArgument);
        self::assertArrayHasKey('credentials', $defArgument);
        self::assertEquals([
            'key' => '@key',
            'secret' => '@secret'
        ], $defArgument['credentials']);
    }

    /**
     * @test
     */
    public function extension_should_expand_service_references()
    {
        $extension = new AwsExtension;
        $config = ['credentials' => '@aws_sdk'];
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->onlyMethods(['getDefinition'])
            ->getMock();

        $definition = new Definition(Sdk::class, [null]);
        $container->expects($this->once())
            ->method('getDefinition')
            ->with('aws_sdk')
            ->willReturn($definition);

        $extension->load([$config], $container);

        $defArgument = $definition->getArgument(0);
        self::assertIsArray($defArgument);
        self::assertArrayHasKey('credentials', $defArgument);
        self::assertInstanceOf(Reference::class, $defArgument['credentials']);
        self::assertEquals('aws_sdk', (string) $defArgument['credentials']);
    }

    /**
     * @test
     */
    public function extension_should_validate_and_not_merge_configs()
    {
        $extension = new AwsExtension;
        $config = [
            'credentials' => false,
            'debug' => [
                'http' => true
            ],
            'stats' => [
                'http' => true
            ],
            'retries' => 5,
            'endpoint' => 'http://localhost:8000',
            'endpoint_discovery' => [
                'enabled' => true,
                'cache_limit' => 1000
            ],
            'http' => [
                'connect_timeout' => 5.5,
                'debug' => true,
                'decode_content' => true,
                'delay' => 1,
                'expect' => true,
                'proxy' => 'http://localhost:9000',
                'sink' => '/path/to/sink',
                'synchronous' => true,
                'stream' => true,
                'timeout' => 3.14,
                'verify' => '/path/to/ca_cert_bundle'
            ],
            'profile' => 'prod',
            'region' => 'us-west-2',
            'scheme' => 'http',
            'signature_version' => 'v4',
            'ua_append' => [
                'prod',
                'foo'
            ],
            'validate' => [
                'required' => true
            ],
            'version' => 'latest',
            'S3' => [
                'version' => '2006-03-01',
            ]
        ];
        $configDev = [
            'credentials' => '@aws_sdk',
            'debug' => true,
            'stats' => true,
            'ua_append' => 'dev',
            'validate' => true,
        ];
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->onlyMethods(['getDefinition'])
            ->getMock();

        $definition = new Definition(Sdk::class, [null]);
        $container->expects($this->once())
            ->method('getDefinition')
            ->with('aws_sdk')
            ->willReturn($definition);

        $extension->load([$config, $configDev], $container);

        $defArgument = $definition->getArgument(0);
        self::assertIsArray($defArgument);
        self::assertArrayHasKey('credentials', $defArgument);
        self::assertInstanceOf(Reference::class, $defArgument['credentials']);
        self::assertEquals('aws_sdk', (string) $defArgument['credentials']);
        self::assertArrayHasKey('debug', $defArgument);
        self::assertTrue($defArgument['debug']);
        self::assertArrayHasKey('stats', $defArgument);
        self::assertTrue($defArgument['stats']);
        self::assertArrayNotHasKey('retries', $defArgument);
        self::assertArrayHasKey('validate', $defArgument);
        self::assertTrue($defArgument['validate']);
        self::assertArrayNotHasKey('endpoint', $defArgument);
        self::assertArrayNotHasKey('endpoint_discovery', $defArgument);
        self::assertArrayNotHasKey('S3', $defArgument);
    }

    /**
     * @test
     */
    public function extension_should_error_merging_unknown_config_options()
    {
        $extension = new AwsExtension;
        $config = [
            'foo' => 'bar'
        ];

        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->getMock();

        $this->expectException(InvalidConfigurationException::class);
        $extension->load([$config], $container);
    }
}
