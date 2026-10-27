<?php

use PHPUnit\Framework\TestCase;

final class RouteAccessConfigurationTest extends TestCase
{
    public function testReferenceDataUserManagementAndReportsRouteAccess(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $coordinatorStart = strpos($routes, "['filter'=>'role:sports_coordinator']");
        $coordinatorEnd = strpos($routes, "['filter'=>'role:admin,sports_coordinator,tournament_manager']", $coordinatorStart + 1);

        $this->assertNotFalse($coordinatorStart);
        $this->assertNotFalse($coordinatorEnd);
        $coordinatorGroup = substr($routes, $coordinatorStart, $coordinatorEnd - $coordinatorStart);
        foreach (["'teams'", "'events'", "'sports'", "'sport-categories'", "'locations'", "'users'"] as $route) {
            $this->assertStringContainsString($route, $coordinatorGroup);
        }

        $this->assertStringContainsString("['filter'=>'role:admin,sports_coordinator,tournament_manager']", $routes);
        $this->assertStringContainsString("reports','ReportsController::index", $routes);
        $this->assertStringContainsString("get('facilitators', static fn() => redirect()->to(site_url('users')))", $routes);
        $this->assertStringContainsString("sports-coordinators/(:num)/reset-password", $routes);
    }

    public function testThemeUpdateRouteIsAvailableToEveryAuthenticatedRole(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $authStart = strpos($routes, "\$routes->group('', ['filter'=>'auth']");
        $adminStart = strpos($routes, "['filter'=>'role:admin']", $authStart + 1);

        $this->assertNotFalse($authStart);
        $this->assertNotFalse($adminStart);
        $authenticatedRoutes = substr($routes, $authStart, $adminStart - $authStart);
        $this->assertStringContainsString("settings/theme','SettingsController::updateTheme", $authenticatedRoutes);
        $this->assertStringContainsString("get('profile','ProfileController::index", $authenticatedRoutes);
        $this->assertStringContainsString("post('profile','ProfileController::update", $authenticatedRoutes);
        $this->assertStringContainsString("users/(:num)/profile','ProfileController::view", $authenticatedRoutes);
    }

    public function testAdminRouteGroupKeepsValidationAndSportsCoordinatorManagementOnly(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $adminStart = strpos($routes, "['filter'=>'role:admin']");
        $adminEnd = strpos($routes, "['filter'=>'role:admin,sports_coordinator,tournament_manager']", $adminStart + 1);

        $this->assertNotFalse($adminStart);
        $this->assertNotFalse($adminEnd);
        $adminGroup = substr($routes, $adminStart, $adminEnd - $adminStart);
        $this->assertStringContainsString('sports-coordinators', $adminGroup);
        $this->assertStringContainsString('results/(:num)/validate', $adminGroup);
        $this->assertStringContainsString("get('sports-managers', static fn() => redirect()->to(site_url('sports-coordinators')))", $adminGroup);
        foreach (["'teams'", "'events'", "'sports'", "'sport-categories'", "'locations'", "'users'"] as $route) {
            $this->assertStringNotContainsString($route, $adminGroup);
        }
    }
}
