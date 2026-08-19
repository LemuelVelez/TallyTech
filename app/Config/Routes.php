<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'ScoreboardController::index');
$routes->get('scoreboard', 'ScoreboardController::index');
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attempt');
$routes->post('logout', 'AuthController::logout');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('notifications', 'NotificationsController::index');
    $routes->get('team-ranking', 'DashboardController::ranking', ['filter' => 'role:admin,manager,validator,facilitator']);
    $routes->get('settings', 'SettingsController::index');
    $routes->post('settings', 'SettingsController::update');

    $routes->group('', ['filter' => 'role:admin'], static function (RouteCollection $routes): void {
        $routes->get('teams', 'TeamsController::index');
        $routes->post('teams', 'TeamsController::store');
        $routes->post('teams/(:num)/update', 'TeamsController::update/$1');
        $routes->post('teams/(:num)/delete', 'TeamsController::delete/$1');

        $routes->get('events', 'EventsController::index');
        $routes->post('events', 'EventsController::store');
        $routes->post('events/(:num)/update', 'EventsController::update/$1');
        $routes->post('events/(:num)/activate', 'EventsController::activate/$1');
        $routes->post('events/(:num)/delete', 'EventsController::delete/$1');

        $routes->get('sports', 'SportsController::index');
        $routes->post('sports', 'SportsController::store');
        $routes->post('sports/(:num)/update', 'SportsController::update/$1');
        $routes->post('sports/(:num)/delete', 'SportsController::delete/$1');

        $routes->get('sport-categories', 'SportCategoriesController::index');
        $routes->post('sport-categories', 'SportCategoriesController::store');
        $routes->post('sport-categories/(:num)/update', 'SportCategoriesController::update/$1');
        $routes->post('sport-categories/(:num)/status', 'SportCategoriesController::setStatus/$1');
        $routes->post('sport-categories/(:num)/delete', 'SportCategoriesController::delete/$1');

        $routes->get('locations', 'LocationsController::index');
        $routes->post('locations', 'LocationsController::store');
        $routes->post('locations/(:num)/update', 'LocationsController::update/$1');
        $routes->post('locations/(:num)/status', 'LocationsController::setStatus/$1');
        $routes->post('locations/(:num)/delete', 'LocationsController::delete/$1');

        $routes->get('schedules', 'SchedulesController::index');
        $routes->post('schedules', 'SchedulesController::store');
        $routes->post('schedules/(:num)/update', 'SchedulesController::update/$1');
        $routes->post('schedules/(:num)/delete', 'SchedulesController::delete/$1');

        $routes->get('users', 'UsersController::index');
        $routes->post('users', 'UsersController::store');
        $routes->post('users/(:num)/update', 'UsersController::update/$1');
        $routes->post('users/(:num)/delete', 'UsersController::delete/$1');

        // Legacy Sports Manager URLs remain available for existing bookmarks/forms.
        $routes->get('sports-managers', 'UsersController::sportsManagers');
        $routes->post('sports-managers', 'UsersController::storeSportsManager');
        $routes->post('sports-managers/(:num)/update', 'UsersController::updateSportsManager/$1');
        $routes->post('sports-managers/(:num)/delete', 'UsersController::deleteSportsManager/$1');
    });

    $routes->group('', ['filter' => 'role:admin,manager'], static function (RouteCollection $routes): void {
        $routes->get('reports', 'ReportsController::index');
    });

    $routes->get('weighted-points', 'WeightedPointsController::index', ['filter' => 'role:manager,validator']);

    $routes->group('', ['filter' => 'role:manager'], static function (RouteCollection $routes): void {
        $routes->post('weighted-points', 'WeightedPointsController::store');
        $routes->post('weighted-points/(:num)/update', 'WeightedPointsController::update/$1');
        $routes->post('weighted-points/(:num)/delete', 'WeightedPointsController::delete/$1');

        $routes->get('facilitators', 'UsersController::facilitators');
        $routes->post('facilitators', 'UsersController::storeFacilitator');
        $routes->post('facilitators/(:num)/update', 'UsersController::updateFacilitator/$1');
        $routes->post('facilitators/(:num)/delete', 'UsersController::deleteFacilitator/$1');
    });

    $routes->group('', ['filter' => 'role:manager,validator,facilitator'], static function (RouteCollection $routes): void {
        $routes->get('match-results', 'ResultsController::matches');
        $routes->get('judged-results', 'ResultsController::judged');
    });

    $routes->group('', ['filter' => 'role:manager,facilitator'], static function (RouteCollection $routes): void {
        $routes->post('results', 'ResultsController::store');
        $routes->post('results/(:num)/update', 'ResultsController::update/$1');
        $routes->post('results/(:num)/delete', 'ResultsController::delete/$1');
    });

    $routes->group('', ['filter' => 'role:validator'], static function (RouteCollection $routes): void {
        $routes->post('weighted-points/(:num)/validate', 'WeightedPointsController::validatePoints/$1');
        $routes->post('results/(:num)/validate', 'ResultsController::validateResult/$1');
    });
});
