<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app = AppFactory::create();

// Listar ambientes
$app->get('/environments', function ($request, $response, $args) {
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
});

// Listar branches do repositório
$app->get('/branches', function ($request, $response, $args) {
    $repoPath = $_ENV['GIT_REPO_PATH'];
    $certPath = $_ENV['GIT_CERT_PATH'];
    $branches = [];

    exec("GIT_SSL_NO_VERIFY=true GIT_SSH_COMMAND='ssh -i $certPath' git -C $repoPath branch -r", $branches);

    $response->getBody()->write(json_encode($branches));
    return $response->withHeader('Content-Type', 'application/json');
});

// Listar ENV de um ambiente
$app->get('/environment/{name}', function ($request, $response, $args) {
    $envName = $args['name'];
    $envPath = getEnvPath($envName) . '/.env';

    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
    } else {
        $envContent = file_get_contents(__DIR__ . '/../envs/example.env');
    }

    $response->getBody()->write($envContent);
    return $response->withHeader('Content-Type', 'text/plain');
});

// Atualizar ambiente com nova branch e executar script SH
$app->post('/deploy', function ($request, $response, $args) {
    $params = (array)$request->getParsedBody();
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
});

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

$app->run();
