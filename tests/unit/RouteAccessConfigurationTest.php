<?php

use PHPUnit\Framework\TestCase;

final class RouteAccessConfigurationTest extends TestCase
{
    public function testReferenceDataUserManagementAndReportsRouteAccess(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $managerStart = strpos($routes, "['filter'=>'role:manager']");
        $managerEnd = strpos($routes, "['filter'=>'role:admin,manager,facilitator']", $managerStart + 1);

        $this->assertNotFalse($managerStart);
        $this->assertNotFalse($managerEnd);
        $managerGroup = substr($routes, $managerStart, $managerEnd - $managerStart);
        foreach (["'teams'", "'events'", "'sports'", "'sport-categories'", "'locations'", "'users'"] as $route) {
            $this->assertStringContainsString($route, $managerGroup);
        }

        $this->assertStringContainsString("['filter'=>'role:admin,manager,facilitator']", $routes);
        $this->assertStringContainsString("reports','ReportsController::index", $routes);
        $this->assertStringContainsString("sports-managers/(:num)/reset-password", $routes);
    }

    public function testAdminRouteGroupKeepsValidationAndSportsCoordinatorManagementOnly(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $adminStart = strpos($routes, "['filter'=>'role:admin']");
        $adminEnd = strpos($routes, "['filter'=>'role:admin,manager,facilitator']", $adminStart + 1);

        $this->assertNotFalse($adminStart);
        $this->assertNotFalse($adminEnd);
        $adminGroup = substr($routes, $adminStart, $adminEnd - $adminStart);
        $this->assertStringContainsString('sports-managers', $adminGroup);
        $this->assertStringContainsString('results/(:num)/validate', $adminGroup);
        foreach (["'teams'", "'events'", "'sports'", "'sport-categories'", "'locations'", "'users'"] as $route) {
            $this->assertStringNotContainsString($route, $adminGroup);
        }
    }
}
