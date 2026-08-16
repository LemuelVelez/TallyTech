<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Persistence\MySqlScoringRepository;
use App\Application\Services\ScoringService;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    protected function repository(): MySqlScoringRepository
    {
        return new MySqlScoringRepository();
    }

    protected function scoringService(): ScoringService
    {
        return new ScoringService($this->repository());
    }

    /**
     * Return a scalar POST field as a string. Array/object payloads are treated
     * as invalid input instead of being implicitly cast to values such as "1".
     */
    protected function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Return a strictly positive integer POST field, or zero when malformed.
     */
    protected function postPositiveInt(string $key): int
    {
        $value = trim($this->postString($key));
        if ($value === '' || ! preg_match('/^[1-9]\d*$/', $value)) {
            return 0;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? 0 : (int) $validated;
    }

    /**
     * Surface expected business-rule errors while keeping database/internal
     * exception details out of user-facing flash messages.
     */
    protected function safeErrorMessage(\Throwable $error, string $fallback): string
    {
        if ($error instanceof \RuntimeException
            && ! $error instanceof \CodeIgniter\Database\Exceptions\DatabaseException) {
            return $error->getMessage();
        }

        return $fallback;
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }
}
