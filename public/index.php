<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use App\DBQuery;

use Valitron\Validator;

session_start();

try {
    Connection::get()->connect();
    echo 'A connection to the PostgreSQL database sever has been established successfully.';
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

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$pdo = Connection::get()->connect();
$query = new DBQuery($pdo);

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
})->setName('/');

$app->post('/urls', function ($request, $response) use ($query, $router) {
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

    $selectUrl = $query->selectURL($urlName);

    if (!$selectUrl) {
        $id = $query->insert($urlName);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $id = $selectUrl[0]['id'];
        $this->get('flash')->addMessage('success', 'Страница уже существует');
    }
    return $response->withRedirect($router->urlFor('url', ['id' => $id]), 302);
});

$app->get('/urls/{id}', function ($request, $response, array $args) use ($query) {
    $id = $args['id'];

    $messages = $this->get('flash')->getMessages();

    $selectUrl = $query->selectID($id);
    $selectUrlID = $query->selectUrlID($id);

        $alert = array_key_first($messages);
        $flash = $messages[$alert][0];

        $params = [
            'flash' => $flash,
            'alert' => $alert,
            'url' => $selectUrl,
            'checks' => $selectUrlID
        ];
        return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) use ($query) {
    $selectedAll = $query->selectAll();

    $params = ['data' => $selectedAll];
    return $this->get('renderer')->render($response, "list.phtml", $params);
})->setName('urls');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($query, $router) {
    $url_id = $args['url_id'];

    $id = $query->insertCheck($url_id);

    return $response->withRedirect($router->urlFor('url', ['id' => $url_id]));
});

$app->run();
