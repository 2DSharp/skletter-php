<?php
/*
 * This file is part of Skletter <https://github.com/2DSharp/Skletter>.
 *
 * (c) Dedipyaman Das <2d@twodee.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Skletter\Factory;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDO;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Predis\Client;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\VirtualProxyInterface;
use Psr\Log\LoggerInterface;
use Skletter\Component\TransportCollector;
use Symfony\Component\HttpFoundation\Request;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TFramedTransport;
use Thrift\Transport\TSocket;
use Twig\Environment;

function addTwigGlobals(Environment $twig)
{
    $twig->addGlobal(
        'server', [
                    'css_assets' => $_ENV['css_assets'],
                    'img_assets' => $_ENV['img_assets'],
                    'base_url' => $_ENV['base_url'],
                    'js_assets' => $_ENV['js_assets'],
                    'USER_IMAGES' => $_ENV['USER_IMAGES']
                ]
    );
}

function buildPDO(): callable
{
    return function (): PDO {
        $dsn = 'mysql:dbname=Skletter;host=127.0.0.1';
        $user = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASS'];

        $obj = new PDO($dsn, $user, $password);
        $obj->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $obj;
    };
}

function buildPredis(): callable
{
    return function (): Client {
        return new Client(
            array(
                "scheme" => "tcp",
                "host" => "localhost",
                "port" => 6379,
                "persistent" => "1")
        );
    };
}

function getRequestFactory(): callable
{
    return function (): Request {
        $obj = Request::createFromGlobals();
        return $obj;
    };
}

function buildLazyLoader(string $proxyDir, bool $generate = false): LazyLoadingValueHolderFactory
{
    $config = new \ProxyManager\Configuration();
    $config->setProxiesTargetDir($proxyDir);

    if ($generate) {
        // generate the proxies and store them as files
        $fileLocator = new \ProxyManager\FileLocator\FileLocator($proxyDir);
        $config->setGeneratorStrategy(new \ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy($fileLocator));
    }

    spl_autoload_register($config->getProxyAutoloader());

    return new LazyLoadingValueHolderFactory($config);
}

function buildRabbitMQ(): callable
{
    return function (): AMQPStreamConnection {
        return new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    };
}

function buildTwig(string $templatesDir, string $cacheDir)
{
    $loader = new \Twig\Loader\FilesystemLoader($templatesDir);
    $twig = new Environment(
        $loader, [
                   //  'cache' => $cacheDir,
               ]
    );
    addTwigGlobals($twig);
    return $twig;
}

function getLazyLoadingTwigFactory(LazyLoadingValueHolderFactory $lazyloader, string $templatesDir,
                                   string $cacheDir): callable
{
    return function () use ($lazyloader, $templatesDir, $cacheDir) : VirtualProxyInterface {
        $factory = $lazyloader;
        $initializer = function (&$wrappedObject, \ProxyManager\Proxy\LazyLoadingInterface $proxy,
                                 $method,
                                 array $parameters,
                                 &$initializer
        ) use ($templatesDir, $cacheDir) {
            $initializer = null; // disable initialization
            $wrappedObject = buildTwig($templatesDir, $cacheDir);
            return true;
        };
        return $factory->createProxy(Environment::class, $initializer);
    };
}

function getLazyLoadingPDO(LazyLoadingValueHolderFactory $lazyloader): callable
{
    return function () use ($lazyloader) : VirtualProxyInterface {
        $factory = $lazyloader;
        $initializer = function (&$wrappedObject, \ProxyManager\Proxy\LazyLoadingInterface $proxy,
                                 $method,
                                 array $parameters,
                                 &$initializer
        ) {
            $wrappedObject = buildPDO();
            $initializer = null; // disable initialization
            return true;
        };
        return $factory->createProxy(PDO::class, $initializer);
    };
}

function buildTFramedTransport(string $address, int $port): TFramedTransport
{

    $timeout = 20; // in seconds.
    $socket = new TSocket($address, $port);
    $socket->setRecvTimeout($timeout * 1000);
    $socket->setSendTimeout($timeout * 1000);

    return new TFramedTransport($socket, 1024, 1024);
}

function buildBinaryProtocol(string $address, int $port, TransportCollector &$collector): TBinaryProtocol
{
    $transport = buildTFramedTransport($address, $port);
    $transport->open();
    $collector->add($transport);
    return new TBinaryProtocol($transport);
}

function buildLogger(string $location): callable
{
    return function () use ($location) : LoggerInterface {
        $logger = new Logger('Debug Log');
        $logger->pushHandler(new StreamHandler($location, Logger::DEBUG));
        return $logger;
    };
}