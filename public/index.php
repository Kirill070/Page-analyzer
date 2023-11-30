<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use DI\Container;
use Carbon\Carbon;
use Valitron\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use DiDom\Document;
use Dotenv\Dotenv;

session_start();

$dotenv = Dotenv::createImmutable(__DIR__ . './../');
$dotenv->safeLoad();

$container = new Container();

$app = AppFactory::createFromContainer($container);

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('router', $app->getRouteCollector()->getRouteParser());

$container->set('renderer', function () use ($container) {
    $renderer = new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    $renderer->setLayout('layout.phtml');
    $renderer->addAttribute('router', $container->get('router'));
    return $renderer;
});

$container->set('pdo', function () {
    $databaseUrl = parse_url($_ENV['DATABASE_URL']);
    if (!$databaseUrl) {
        throw new \Exception("Error reading database url");
    }
    $host = $databaseUrl['host'] ?? '';
    $port = $databaseUrl['port'] ?? '';
    $name = $databaseUrl['path'] ? ltrim($databaseUrl['path'], '/') : '';
    $user = $databaseUrl['user'] ?? '';
    $pass = $databaseUrl['pass'] ?? '';

    if ($port) {
        $conStr = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
            $host,
            $port,
            $name,
            $user,
            $pass
        );
    } else {
        $conStr = sprintf(
            "pgsql:host=%s;dbname=%s;user=%s;password=%s",
            $host,
            $name,
            $user,
            $pass
        );
    }

    $pdo = new \PDO($conStr);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

    return $pdo;
});

$app->addErrorMiddleware(true, false, false);

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    if ($response->getStatusCode() === 404) {
        $response = new Response();
        return $this->get('renderer')->render($response->withStatus(404), '404.phtml');
    }

    return $response;
});

$app->get('/', function ($request, $response) {
    $params = [
        'homeActive' => 'active'
    ];
    return $this->get('renderer')->render($response, 'home.phtml', $params);
})->setName('home');

$app->post('/urls', function ($request, $response) {
    $url = $request->getParsedBodyParam('url');

    $validator = new Validator(['url' => $url['name']]);
    $validator->rule('required', 'url')->message('URL не должен быть пустым');
    $validator->rule('url', 'url')->message('Некорректный URL');
    $validator->rule('lengthMax', 'url', 255)->message('URL превышает 255 символов');

    if (!$validator->validate()) {
        $params = [
            'url' => $url['name'],
            'errors' => $validator->errors()
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $parsedUrl = parse_url(strtolower($url['name']));
    $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

    $pdo = $this->get('pdo');
    $sql = 'SELECT * FROM urls WHERE name = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$normalizedUrl]);
    $selectedUrl = $stmt->fetchAll();

    if (!$selectedUrl) {
        $sql = 'INSERT INTO urls(name, created_at) VALUES(?, ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$normalizedUrl, Carbon::now()]);
        $id = $pdo->lastInsertId('urls_id_seq');
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $id = $selectedUrl[0]['id'];
        $this->get('flash')->addMessage('success', 'Страница уже существует');
    }
    return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $id]));
})->setName('urls.store');

$app->get('/urls/{id:[0-9]+}', function ($request, $response, array $args) {
    $id = $args['id'];

    $messages = $this->get('flash')->getMessages();

    $pdo = $this->get('pdo');
    $sql = 'SELECT * FROM urls WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $selectedUrl = $stmt->fetch();

    if (!$selectedUrl) {
        return $response->withStatus(404)->write('Page not found');
    }

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
})->setName('urls.show');

$app->get('/urls', function ($request, $response) {
    $pdo = $this->get('pdo');

    $sqlUrls = 'SELECT id, name FROM urls ORDER BY id DESC';

    /** @var \Illuminate\Support\Collection $urls */
    $urls = collect($pdo->query($sqlUrls)->fetchAll(\PDO::FETCH_ASSOC));

    $sqlUrlChecks = 'SELECT DISTINCT ON (url_id) url_id, created_at, status_code
                    FROM url_checks
                    ORDER BY url_id, created_at DESC;';

    /** @var \Illuminate\Support\Collection $urlChecks */
    $urlChecks = collect($pdo->query($sqlUrlChecks)->fetchAll(\PDO::FETCH_ASSOC))
        ->keyBy('url_id');

    $data = $urls->map(function ($url) use ($urlChecks) {
        return array_merge($url, $urlChecks->get($url['id'], []));
    })->all();

    $params = [
        'data' => $data,
        'indexActive' => 'active'
    ];
    return $this->get('renderer')->render($response, "index.phtml", $params);
})->setName('urls.index');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) {
    $url_id = $args['url_id'];

    $pdo = $this->get('pdo');
    $sql = 'SELECT name FROM urls WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$url_id]);
    $selectedUrl = $stmt->fetch();

    $client = new Client();

    try {
        $res = $client->get($selectedUrl['name']);
        $html = (string) $res->getBody();
        $statusCode = $res->getStatusCode();
        $message = 'Страница успешно проверена';
        $this->get('flash')->addMessage('success', $message);

        $document = new Document($html);
        $h1 = optional($document->first('h1'))->text();
        $title = optional($document->first('title'))->text();
        $description = optional($document->first('meta[name=description]'))->attr('content');
    } catch (ConnectException $e) {
        $message = 'Произошла ошибка при проверке, не удалось подключиться';
        $this->get('flash')->addMessage('danger', $message);
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $url_id]));
    } catch (ClientException $e) {
        $res = $e->getResponse();
        $statusCode = $res->getStatusCode();
        $h1 = 'Доступ ограничен: проблема с IP';
        $title = 'Доступ ограничен: проблема с IP';
        $description = '';
        $message = 'Проверка была выполнена успешно, но сервер ответил с ошибкой';
        $this->get('flash')->addMessage('warning', $message);
    } catch (ServerException $e) {
        $message = 'Проверка была выполнена успешно, но сервер ответил с ошибкой';
        $this->get('flash')->addMessage('warning', $message);
        return $this->get('renderer')->render($response, '500.phtml');
    } catch (RequestException $e) {
        $message = 'Проверка была выполнена успешно, но сервер ответил с ошибкой';
        $this->get('flash')->addMessage('warning', $message);
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $url_id]));
    }

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

    return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $url_id]));
})->setName('urls.check');

$app->run();
