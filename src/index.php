<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$responseFactory = new ResponseFactory();
$streamFactory = new StreamFactory();

function listEnvironments() {
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

    return json_encode($data);
}

function getRepoPath($environment) {
    $stageNumber = intval(preg_replace('/[^0-9]/', '', $environment));
    if ($stageNumber <= 9) {
        if (strpos($environment, 'api') !== false) {
            return "/home/st{$stageNumber}bsapi/web/stage{$stageNumber}-api.bs2bet.com/public_html";
        } elseif (strpos($environment, 'off') !== false) {
            return "/home/st{$stageNumber}bsoff/web/stage{$stageNumber}-backoffice.bs2bet.com/public_html";
        } else {
            return "/home/stage{$stageNumber}bs/web/stage{$stageNumber}bs.bs2bet.com/public_html";
        }
    } else {
        if (strpos($environment, 'ap') !== false) {
            return "/home/st{$stageNumber}bsap/web/stage{$stageNumber}-api.bs2bet.com/public_html";
        } elseif (strpos($environment, 'of') !== false) {
            return "/home/st{$stageNumber}bsof/web/stage{$stageNumber}-backoffice.bs2bet.com/public_html";
        } else {
            return "/home/stage{$stageNumber}b/web/stage{$stageNumber}.bs2bet.com/public_html";
        }
    }
}

function executeShellCommand($cmd) {
    error_log("Executing command: $cmd");
    exec($cmd, $output, $return_var);
    error_log("Command result: " . print_r($output, true));
    error_log("Command return value: $return_var");

    return ['output' => $output, 'return_var' => $return_var];
}

function listBranches($environment) {
    $repoPath = getRepoPath($environment);
    
    error_log("Environment: $environment");
    error_log("Repository Path: $repoPath");

    if (!$repoPath) {
        error_log("Error: Repository path for $environment not found.");
        return json_encode(['error' => 'Invalid environment']);
    }

    $cmd = "GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i {$_ENV['GIT_CERT_PATH']}' git -C $repoPath branch -r";
    $result = executeShellCommand($cmd);

    if ($result['return_var'] !== 0) {
        return json_encode(['error' => 'Failed to list branches']);
    }

    $branchNames = array_map(function($line) {
        return preg_replace('/^.*refs\/heads\//', '', $line);
    }, $result['output']);

    return json_encode($branchNames);
}

function getEnvironment($name) {
    $envPath = getRepoPath($name) . '/.env';
    $sudoUser = $_ENV['SUDO_USER'];
    $sudoCertPath = $_ENV['SUDO_CERT_PATH'];

    $cmd = "sudo -u $sudoUser -i 'ssh -i $sudoCertPath cat $envPath'";
    $result = executeShellCommand($cmd);

    if ($result['return_var'] !== 0) {
        $envContent = ''; // Deixa em branco para edição e criação do ENV
    } else {
        $envContent = implode("\n", $result['output']);
    }

    return $envContent;
}

function deploy($params) {
    $envName = $params['environment'];
    $branch = $params['branch'];
    $envPath = getRepoPath($envName);
    $repoPath = $_ENV['GIT_REPO_PATH'];
    $certPath = $_ENV['GIT_CERT_PATH'];
    $sudoUser = $_ENV['SUDO_USER'];
    $sudoCertPath = $_ENV['SUDO_CERT_PATH'];

    $cmd = "
        cd $repoPath &&
        GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i $certPath' git fetch origin &&
        GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i $certPath' git checkout $branch &&
        sudo -u $sudoUser -i 'ssh -i $sudoCertPath sh " . __DIR__ . '/../scripts/deploy.sh' . " $envPath'
    ";
    $result = executeShellCommand($cmd);

    return json_encode(['status' => $result['return_var'] == 0 ? 'success' : 'failure']);
}

function getEnvPath($envName) {
    return getRepoPath($envName);
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

// Processamento baseado em query string
$path = isset($_GET['path']) ? $_GET['path'] : '';
$responseBody = '';
$contentType = 'application/json';

switch ($path) {
    case 'environments':
        $responseBody = listEnvironments();
        break;
    case 'branches':
        if (isset($_GET['environment'])) {
            $responseBody = listBranches($_GET['environment']);
        } else {
            http_response_code(400);
            $responseBody = json_encode(['error' => 'Missing parameter: environment']);
        }
        break;
    case 'environment':
        if (isset($_GET['name'])) {
            $responseBody = getEnvironment($_GET['name']);
            $contentType = 'text/plain';
        } else {
            http_response_code(400);
            $responseBody = 'Missing parameter: name';
        }
        break;
    case 'deploy':
        $params = (array)json_decode(file_get_contents('php://input'), true);
        $responseBody = deploy($params);
        break;
    default:
        $responseBody = 'SERVICO OK';
        $contentType = 'text/plain';
        break;
}

header('Content-Type: ' . $contentType);
echo $responseBody;
