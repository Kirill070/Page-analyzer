<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use Carbon\Carbon;
use Valitron\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use DiDom\Document;
use Dotenv\Dotenv;

session_start();

try {
    Connection::get()->connect();
} catch (\PDOException $e) {
    echo $e->getMessage();
}

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('pdo', function () {
    $dotenv = Dotenv::createImmutable(__DIR__ . './../');
    $dotenv->safeLoad();

    $databaseUrl = parse_url($_ENV['DATABASE_URL']);
    if (!$databaseUrl) {
        throw new \Exception("Error reading database url");
    }
    $dbHost = $databaseUrl['host'];
    $dbPort = $databaseUrl['port'];
    $dbName = ltrim($databaseUrl['path'], '/');
    $dbUser = $databaseUrl['user'];
    $dbPassword = $databaseUrl['pass'];

    $conStr = sprintf(
        "postgres:host=%s;port=%d;dbname=%s;user=%s;password=%s",
        $dbHost,
        $dbPort,
        $dbName,
        $dbUser,
        $dbPassword
    );

    $pdo = new \PDO($conStr);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

    return $pdo;
    //return Connection::get()->connect();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
})->setName('/');

$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');

    $validator = new Validator(['url' => $url['name']]);
    $validator->rule('required', 'url')->message('URL не должен быть пустым');
    $validator->rule('url', 'url')->message('Некорректный URL');
    $validator->rule('lengthMax', 'url', 255)->message('URL превышает 255 символов');

    if (!$validator->validate()) {
        $params = [
            'request' => $url['name'],
            'errors' => $validator->errors(),
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
    }

    $parsedUrl = parse_url(strtolower($url['name']));
    $urlName = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

    $pdo = $this->get('pdo');
    $sql = 'SELECT * FROM urls WHERE name = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$urlName]);
    $selectedUrl = $stmt->fetchAll();

    if (!$selectedUrl) {
        $sql = 'INSERT INTO urls(name, created_at) VALUES(?, ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$urlName, Carbon::now()]);
        $id = $pdo->lastInsertId('urls_id_seq');
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $id = $selectedUrl[0]['id'];
        $this->get('flash')->addMessage('success', 'Страница уже существует');
    }
    return $response->withRedirect($router->urlFor('url', ['id' => $id]), 302);
});

$app->get('/urls/{id:[0-9]+}', function ($request, $response, array $args) {
    $id = $args['id'];
    $messages = $this->get('flash')->getMessages();

    $pdo = $this->get('pdo');
    $sql = 'SELECT * FROM urls WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $selectedUrl = $stmt->fetch();

    $sql = 'SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $selectedUrlCheck = $stmt->fetchAll();

    $alert = array_key_first($messages) ?? '';
    $flash = $messages[$alert][0] ?? '';

    $params = [
        'flash' => $flash,
        'alert' => $alert,
        'url' => $selectedUrl,
        'checks' => $selectedUrlCheck
    ];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $pdo = $this->get('pdo');
    $sql = 'SELECT
        urls.id AS id,
        urls.name AS name,
        MAX(url_checks.created_at) AS created_at,
        url_checks.status_code AS status_code
        FROM urls LEFT JOIN url_checks ON urls.id = url_checks.url_id
        GROUP BY urls.id, status_code
        ORDER BY id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $urls = $stmt->fetchAll();

    $params = ['data' => $urls];
    return $this->get('renderer')->render($response, "list.phtml", $params);
})->setName('urls');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router) {
    $url_id = $args['url_id'];

    $pdo = $this->get('pdo');
    $sql = 'SELECT name FROM urls WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$url_id]);
    $url = $stmt->fetch();

    $client = new Client();

    try {
        $res = $client->get($url['name']);
        $message = 'Страница успешно проверена';
        $this->get('flash')->addMessage('success', $message);
    } catch (ConnectException $e) {
        $message = 'Произошла ошибка при проверке, не удалось подключиться';
        $this->get('flash')->addMessage('danger', $message);
        return $response->withRedirect($router->urlFor('url', ['id' => $url_id]));
    } catch (RequestException $e) {
        $res = $e->getResponse();
        $message = 'Проверка была выполнена успешно, но сервер ответил c ошибкой';
        $this->get('flash')->clearMessages();
        $this->get('flash')->addMessage('warning', $message);
    }
    $statusCode = !is_null($res) ? $res->getStatusCode() : null;

    $document = new Document($url['name'], true);
    $h1 = optional($document->first('h1'))->text();
    $title = optional($document->first('title'))->text();
    $description = optional($document->first('meta[name=description]'))->attr('content');

    $sql = 'INSERT INTO url_checks(
        url_id,
        status_code,
        h1, 
        title, 
        description,
        created_at
        )
        VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$url_id, $statusCode, $h1, $title, $description, Carbon::now()]);
    $id = $pdo->lastInsertId('url_checks_id_seq');

    return $response->withRedirect($router->urlFor('url', ['id' => $url_id]));
});

$app->run();
