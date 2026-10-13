<?php
declare(strict_types=1);

/** @var \App\Core\Router $router */

// Guest
$router->get('/', 'AuthController@loginForm', ['guest']);
$router->get('/login', 'AuthController@loginForm', ['guest']);
$router->post('/login', 'AuthController@login', ['guest']);
$router->get('/logout', 'AuthController@logout', ['auth']);

// Dashboard
$router->get('/dashboard', 'DashboardController@index', ['auth']);

// Clients
$router->get('/clients', 'ClientController@index', ['auth']);
$router->get('/clients/create', 'ClientController@create', ['auth']);
$router->post('/clients', 'ClientController@store', ['auth']);
$router->get('/clients/{id}', 'ClientController@show', ['auth']);
$router->get('/clients/{id}/edit', 'ClientController@edit', ['auth']);
$router->post('/clients/{id}', 'ClientController@update', ['auth']);

// Services
$router->get('/services', 'ServiceController@index', ['auth']);
$router->get('/services/create', 'ServiceController@create', ['auth']);
$router->post('/services', 'ServiceController@store', ['auth']);
$router->get('/services/{id}/edit', 'ServiceController@edit', ['auth']);
$router->post('/services/{id}', 'ServiceController@update', ['auth']);

// Estimates
$router->get('/estimates', 'EstimateController@index', ['auth']);
$router->get('/estimates/create', 'EstimateController@create', ['auth']);
$router->post('/estimates', 'EstimateController@store', ['auth']);
$router->get('/estimates/{id}', 'EstimateController@show', ['auth']);
$router->post('/estimates/{id}/accept', 'EstimateController@accept', ['auth']);
$router->post('/estimates/{id}/convert-invoice', 'EstimateController@convertToInvoice', ['auth']);

// Invoices
$router->get('/invoices', 'InvoiceController@index', ['auth']);
$router->get('/invoices/create', 'InvoiceController@create', ['auth']);
$router->post('/invoices', 'InvoiceController@store', ['auth']);
$router->get('/invoices/{id}', 'InvoiceController@show', ['auth']);
$router->get('/invoices/{id}/print', 'InvoiceController@print', ['auth']);
$router->post('/invoices/{id}/send', 'InvoiceController@send', ['auth']);

// Payments
$router->get('/payments', 'PaymentController@index', ['auth']);
$router->get('/payments/create', 'PaymentController@create', ['auth']);
$router->post('/payments', 'PaymentController@store', ['auth']);
$router->get('/payments/{id}', 'PaymentController@show', ['auth']);
$router->get('/payments/{id}/receipt', 'PaymentController@receipt', ['auth']);

// Settings
$router->get('/settings', 'SettingsController@index', ['auth']);
$router->post('/settings', 'SettingsController@save', ['auth']);
