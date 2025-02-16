<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/', function (Request $request, Response $response, array $args) {
    $entry = [
        'message' => 'Welcome to the OSMI graph!',
        '_links' => [
            'self' => [
                'href' => '/',
                'title' => 'You are here!',
            ],
            'questions' => [
                'href' => '/questions',
                'title' => 'Survey questions',
            ],
            'respondents' => [
                'href' => '/respondents',
                'title' => 'Survey respondents',
            ],
            'disorders' => [
                'href' => '/disorders',
                'title' => 'Disorders identified in survey',
            ],
            'countries' => [
                'href' => '/countries',
                'title' => 'Countries identified in survey',
            ],
        ],
    ];

    return $response->withJson($entry);
})->setName('entry_point');
