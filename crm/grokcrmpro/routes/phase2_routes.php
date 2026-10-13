<?php
/**
 * Phase 2 routes — ADD these lines to routes/web.php
 * (or require this file from web.php)
 */

/** @var \App\Core\Router $router */

// Leads
$router->get('/leads', 'LeadController@index', ['auth']);
$router->get('/leads/create', 'LeadController@create', ['auth']);
$router->post('/leads', 'LeadController@store', ['auth']);
$router->get('/leads/{id}', 'LeadController@show', ['auth']);
$router->get('/leads/{id}/edit', 'LeadController@edit', ['auth']);
$router->post('/leads/{id}', 'LeadController@update', ['auth']);
$router->post('/leads/{id}/convert', 'LeadController@convert', ['auth']);

// Projects
$router->get('/projects', 'ProjectController@index', ['auth']);
$router->get('/projects/create', 'ProjectController@create', ['auth']);
$router->post('/projects', 'ProjectController@store', ['auth']);
$router->get('/projects/{id}', 'ProjectController@show', ['auth']);
$router->get('/projects/{id}/edit', 'ProjectController@edit', ['auth']);
$router->post('/projects/{id}', 'ProjectController@update', ['auth']);
