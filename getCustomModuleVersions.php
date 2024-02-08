<?php
/**
 * Script loads all custom/3rd party modules and provide currently installed and latest available composer version.
 */

use Composer\Console\Application;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

try {
    require_once __DIR__ . '/app/bootstrap.php';
} catch (Exception $e) {
    echo 'Autoload error: ' . $e->getMessage();
    exit(1);
}

$coreModules = [ // v2.4.3
    'Amazon_Login',
    'Amazon_Payment',
    'Amazon_Core',
    'Yotpo_Yotpo',
    'Dotdigitalgroup_Email',
    'Dotdigitalgroup_Sms',
    'Dotdigitalgroup_ChatGraphQl',
    'Dotdigitalgroup_EmailGraphQl',
    'Dotdigitalgroup_Chat',
    'Temando_ShippingRemover',
    'Klarna_Onsitemessaging',
    'Klarna_Ordermanagement',
    'Klarna_Core',
    'Klarna_KpGraphQl',
    'Klarna_Kp',
    'PayPal_Braintree',
    'PayPal_BraintreeGraphQl',
    'Vertex_AddressValidation',
    'Vertex_RequestLoggingApi',
    'Vertex_Tax',
    'Vertex_AddressValidationApi',
    'Vertex_RequestLogging'
];

$excludePatterns = ['/^Magento_/'];

try {
    $bootstrap = Bootstrap::create(BP, $_SERVER);
    $objectManager = $bootstrap->getObjectManager();
    $appState = $objectManager->get(State::class);
    $appState->setAreaCode('frontend');

    $modules = getNonCoreModules();
    foreach ($modules as $moduleName => $status) {
        list($composerData, $isVendor) = getModuleComposerData($moduleName);
        $composerName = $composerData['name'] ?? '';
        $composerVersion = $composerData['version'] ?? '';

        $latestVersion = 'unavailable';
        if ($isVendor && $composerName) {
            $info = getLatestComposerInfo($composerName);
            $latestVersion = $info['latest'] ?? '';
        }

        echo sprintf(
            '%s (%s) - Installed: [%s], Latest: [%s] Status: %s',
            $moduleName,
            $composerName,
            $composerVersion,
            $latestVersion,
            $status
        ) . PHP_EOL;
    }
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

function getModuleComposerData(string $moduleName): array
{
    $objectManager = ObjectManager::getInstance();
    $registrar = $objectManager->get(ComponentRegistrarInterface::class);
    $readFactory = $objectManager->get(ReadFactory::class);

    $path = $registrar->getPath(
        ComponentRegistrar::MODULE,
        $moduleName
    );

    $directoryRead = $readFactory->create($path);
    $composerJsonData = $directoryRead->isFile('composer.json') ? $directoryRead->readFile('composer.json') : '';

    $data = json_decode($composerJsonData, true);

    return [
        $data ?? [],
        strpos($path, '/vendor/') !== false
    ];
}

function getNonCoreModules(): array
{
    global $coreModules, $excludePatterns;

    $config = include 'app/etc/config.php';
    $modules = $config['modules'];

    $nonCoreModules = [];
    foreach ($modules as $module => $enabled) {
        if (in_array($module, $coreModules)) {
            continue;
        }

        $excluded = false;
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $module)) {
                $excluded = true;
                break;
            }
        }
        if ($excluded) {
            continue;
        }

        $nonCoreModules[$module] = $enabled;
    }

    ksort($nonCoreModules);

    return $nonCoreModules;
}

function getLatestComposerInfo(string $name): ?array
{
    $input = new ArrayInput(['command' => 'show', 'package' => $name, '--latest' => 1, '--format' => 'json']);
    $output = new BufferedOutput();
    $application = new Application();
    $application->setAutoExit(false);
    $application->run($input, $output);

    $result = $output->fetch();
    $result = preg_replace('/<warning>.*<\/warning>/m', '', $result);

    return json_decode($result, true);
}
