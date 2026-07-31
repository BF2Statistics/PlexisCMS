<?php
/**
 * Manual Route Overrides
 *
 * These routes take precedence over auto-generated routes defined by controllers using the #[Route] attribute
 * and stored in the system/cache/routes.cache.php.
 *
 * This file is NOT auto-generated and will not be overwritten.
 *
 * Format:
 *   return [
 *       'route-name' => [
 *           'path' => '/your/path',
 *           'methods' => ['GET'],
 *           'isAjax' => false,
 *           'isInternal' => false,
 *           'controller' => 'Modules\YourModule\Controllers\YourController::method',
 *           'middleware' => [],
 *       ],
 *   ];
 */