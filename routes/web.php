<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    //return $router->app->version();
    echo "Welcome to the Lumen API!";
}); 

$router->get('/loginb', 'UserController@login' );
$router->get('/register', 'UserController@registration' );
$router->post('/registerUser', 'UserController@registerUser' );
$router->get('/dashboard', 'UserController@dashboard' );



//The Required APIS
$router->post('/RegisteringUser', 'UserController@RegisteringUser');
$router->post('/addUser', 'UserController@AddUser');

$router->get('/emailVerification', 'UserController@verifyEmail');
$router->post('/login', 'UserController@postLogin' );
$router->post('/forgetPassword', 'UserController@forgetPassword');
$router->post('/resetPassword', 'UserController@resetPassword');
$router->get('/resetForm', 'UserController@resetForm');
$router->get('/checkEmail', 'UserController@checkEmail');
$router->get('/exportCSV', 'UserController@exportCSV');
$router->post('/reRegisteringUser', 'UserController@reRegisteringUser');



$router->group(['middleware'=>"auth"],function($router){
    $router->get('/getUsers', 'UserController@getUser' );
    // $router->get('/getAll', 'UserController@all' );
    $router->delete('/delUser/{id}', 'UserController@delete' );
    $router->delete('/bulkDelete', 'UserController@bulkDelete' );
    $router->put('/bulkRole', 'UserController@bulkRole' );
    $router->put('/updateUser/{id}', 'UserController@update' );
    $router->post('/logout', 'UserController@postLogout' );
    $router->get('/me', 'UserController@me' );
    $router->post('/masterVerify/{id}', 'UserController@masterVerify' );   
    $router->get('/getTasks', 'UserController@getTasks'); 
});

//task apis
$router->post('/addTask', 'TaskController@addTask');
$router->put('/updateTask/{id}', 'TaskController@updateTask');
$router->put('/updateTaskStatus/{id}', 'TaskController@updateTaskStatus');
// $router->get('/getTasks', 'TaskController@getTasks');
$router->get('/filterTasks', 'TaskController@filterTasks');

$router->get('/countTasks', 'TaskController@countTasks');
$router->get('/userName', 'UserController@userName');
$router->get('/completedTasks', 'TaskController@getCompletedTasksPerDay');
$router->get('/overdueTasks', 'TaskController@overDueTasks');
$router->get('/taskCompletedThisMonth', 'TaskController@taskCompletedThisMonth');
$router->get('/byMonths', 'TaskController@byMonths');

//Notif
$router->get('/notifications', 'NotificationController@getNotif');
$router->post('/notifications/{id}/read', 'NotificationController@markAsRead');


// CORS Middleware But did not work
$router->options('/{any:.*}', function() {
    return response('', 204);
});


