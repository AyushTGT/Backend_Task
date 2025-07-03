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
$router->post('/RegisteringUser', 'UserController@RegisteringUser'); //done
$router->post('/addUser', 'UserController@AddUser'); //done

$router->get('/emailVerification', 'UserController@verifyEmail'); //done
$router->post('/login', 'UserController@postLogin' );  //done
$router->post('/forgetPassword', 'UserController@forgetPassword'); //done
$router->post('/resetPassword', 'UserController@resetPassword'); //done
// $router->get('/resetForm', 'UserController@resetForm');
$router->get('/checkEmail', 'UserController@checkEmail'); //done
$router->get('/exportCSV', 'UserController@exportCSV'); //done
$router->post('/reRegisteringUser', 'UserController@reRegisteringUser');



$router->group(['middleware'=>"auth"],function($router){
    $router->get('/getUsers', 'UserController@getUser' ); //done
    // $router->get('/getAll', 'UserController@all' );
    $router->delete('/delUser/{id}', 'UserController@delete' ); //done
    $router->delete('/bulkDelete', 'UserController@bulkDelete' ); //done
    $router->put('/bulkRole', 'UserController@bulkRole' ); //done
    $router->put('/updateUser/{id}', 'UserController@update' ); //done
    $router->post('/logout', 'UserController@postLogout' ); ///done
    $router->get('/me', 'UserController@me' ); //done
    $router->post('/masterVerify/{id}', 'UserController@masterVerify' );  //done 
    $router->get('/getTasks', 'UserController@getTasks'); //done
});

//task apis
$router->post('/addTask', 'TaskController@addTask'); //done
$router->put('/updateTask/{id}', 'TaskController@updateTask'); //done
$router->put('/updateTaskStatus/{id}', 'TaskController@updateTaskStatus'); //done
// $router->get('/getTasks', 'TaskController@getTasks');
$router->get('/filterTasks', 'TaskController@filterTasks'); //done

$router->get('/countTasks', 'TaskController@countTasks'); //done
$router->get('/userName', 'UserController@userName'); //done
$router->get('/completedTasks', 'TaskController@getCompletedTasksPerDay'); //done
$router->get('/overdueTasks', 'TaskController@overDueTasks'); //done
$router->get('/taskCompletedThisMonth', 'TaskController@taskCompletedThisMonth'); //done
$router->get('/byMonths', 'TaskController@byMonths'); //done

//Notif
$router->get('/notifications', 'NotificationController@getNotif'); //done
$router->post('/notifications/{id}/read', 'NotificationController@markAsRead'); //done


// CORS Middleware But did not work
$router->options('/{any:.*}', function() {
    return response('', 204);
});


