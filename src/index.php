<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Factory\ServerRequestCreatorFactory;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Cria as fábricas PSR-17 e PSR-7
$responseFactory = new ResponseFactory();
$serverRequestFactory = new ServerRequestFactory();
$streamFactory = new StreamFactory();

// Configura Slim para usar as fábricas PSR-17
AppFactory::setResponseFactory($responseFactory);
AppFactory::setStreamFactory($streamFactory);

$app = AppFactory::create();

$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Função para listar ambientes
function listEnvironments($response) {
    $stages = explode(',', $_ENV['STAGES']);
    $data = [];

    foreach ($stages as $stage) {
        $stageNumber = intval($stage);
        if ($stageNumber <= 9) {
            $environments = [
                "stage{$stage}bs" => "/home/stage{$stage}bs/web/stage{$stage}bs.bs2bet.com/public_html",
                "st{$stage}bsapi" => "/home/st{$stage}bsapi/web/stage{$stage}-api.bs2bet.com/public_html",
                "st{$stage}bsoff" => "/home/st{$stage}bsoff/web/stage{$stage}-backoffice.bs2bet.com/public_html"
            ];
        } else {
            $environments = [
                "stage{$stage}b" => "/home/stage{$stage}b/web/stage{$stage}.bs2bet.com/public_html",
                "st{$stage}bsap" => "/home/st{$stage}bsap/web/stage{$stage}-api.bs2bet.com/public_html",
                "st{$stage}bsof" => "/home/st{$stage}bsof/web/stage{$stage}-backoffice.bs2bet.com/public_html"
            ];
        }

        foreach ($environments as $name => $path) {
            $data[] = [
                'name' => $name,
                'path' => $path,
                'url' => generateUrl($name, $stageNumber)
            ];
        }
    }

    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
}

// Função para listar branches do repositório
function listBranches($response) {
    $repoPath = $_ENV['GIT_REPO_PATH'];
    $certPath = $_ENV['GIT_CERT_PATH'];
    $branches = [];
    $cmd = "cd $repoPath && GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i $certPath' git branch -r";

    exec($cmd, $branches, $return_var);
    error_log(print_r(array($cmd, $branches), true));

    if ($return_var !== 0) {
        $response->getBody()->write(json_encode(['error' => 'Failed to list branches']));
    } else {
        $response->getBody()->write(json_encode($branches));
    }

    return $response->withHeader('Content-Type', 'application/json');
}

// Função para listar ENV de um ambiente
function getEnvironment($name, $response) {
    $envPath = getEnvPath($name) . '/.env';

    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
    } else {
        $envContent = file_get_contents(__DIR__ . '/../envs/example.env');
    }

    $response->getBody()->write($envContent);
    return $response->withHeader('Content-Type', 'text/plain');
}

// Função para atualizar ambiente com nova branch e executar script SH
function deploy($params, $response) {
    $envName = $params['environment'];
    $branch = $params['branch'];
    $envPath = getEnvPath($envName);
    $repoPath = $_ENV['GIT_REPO_PATH'];
    $certPath = $_ENV['GIT_CERT_PATH'];
    $sudoUser = $_ENV['SUDO_USER'];
    $sudoCertPath = $_ENV['SUDO_CERT_PATH'];

    // Trocar para o branch e executar script SH
    $cmd = "
        cd $repoPath &&
        GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i $certPath' git fetch origin &&
        GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i $certPath' git checkout $branch &&
        sudo -u $sudoUser -i 'ssh -i $sudoCertPath sh " . __DIR__ . '/../scripts/deploy.sh' . " $envPath'
    ";
    exec($cmd, $output, $return_var);

    $response->getBody()->write(json_encode(['status' => $return_var == 0 ? 'success' : 'failure']));
    return $response->withHeader('Content-Type', 'application/json');
}

// Roteamento baseado em query string
$path = isset($_GET['path']) ? $_GET['path'] : '';
switch ($path) {
    case 'environments':
        $response = listEnvironments($responseFactory->createResponse());
        break;
    case 'branches':
        $response = listBranches($responseFactory->createResponse());
        break;
    case 'environment':
        if (isset($_GET['name'])) {
            $response = getEnvironment($_GET['name'], $responseFactory->createResponse());
        } else {
            $response = $responseFactory->createResponse(400)->withBody($streamFactory->createStream('Missing parameter: name'));
        }
        break;
    case 'deploy':
        $params = (array)$request->getParsedBody();
        $response = deploy($params, $responseFactory->createResponse());
        break;
    default:
        $response = $responseFactory->createResponse()->withBody($streamFactory->createStream('SERVICO OK'));
        break;
}

$headers = $response->getHeaders();
foreach ($headers as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

http_response_code($response->getStatusCode());
echo $response->getBody();

function getEnvPath($envName) {
    $stageNumber = intval(preg_replace('/[^0-9]/', '', $envName));
    if ($stageNumber <= 9) {
        if (strpos($envName, 'api') !== false) {
            return "/home/st{$stageNumber}bsapi/web/stage{$stageNumber}-api.bs2bet.com/public_html";
        } elseif (strpos($envName, 'off') !== false) {
            return "/home/st{$stageNumber}bsoff/web/stage{$stageNumber}-backoffice.bs2bet.com/public_html";
        } else {
            return "/home/stage{$stageNumber}bs/web/stage{$stageNumber}bs.bs2bet.com/public_html";
        }
    } else {
        if (strpos($envName, 'ap') !== false) {
            return "/home/st{$stageNumber}bsap/web/stage{$stageNumber}-api.bs2bet.com/public_html";
        } elseif (strpos($envName, 'of') !== false) {
            return "/home/st{$stageNumber}bsof/web/stage{$stageNumber}-backoffice.bs2bet.com/public_html";
        } else {
            return "/home/stage{$stageNumber}b/web/stage{$stageNumber}.bs2bet.com/public_html";
        }
    }
}

function generateUrl($envName, $stageNumber) {
    $baseDomain = $_ENV['DOMAIN'];
    if ($stageNumber <= 9) {
        if (strpos($envName, 'api') !== false) {
            return "stage{$stageNumber}-api.$baseDomain";
        } elseif (strpos($envName, 'off') !== false) {
            return "stage{$stageNumber}-backoffice.$baseDomain";
        } else {
            return "stage{$stageNumber}.$baseDomain";
        }
    } else {
        if (strpos($envName, 'ap') !== false) {
            return "stage{$stageNumber}-api.$baseDomain";
        } elseif (strpos($envName, 'of') !== false) {
            return "stage{$stageNumber}-backoffice.$baseDomain";
        } else {
            return "stage{$stageNumber}.$baseDomain";
        }
    }
}

$app->run($request);
