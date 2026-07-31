<?php
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace System;

use System\Http\JsonResponse;
use System\Http\Request;

/**
 * A specialized controller for handling JSON-based responses in the Plexis CMS framework.
 *  This class extends the `BaseController` and provides functionality for generating
 *  and sending JSON responses formatted according to specific requirements.
 *
 *  ## Key Responsibilities:
 *  - **JSON Response Handling**: Constructs and returns JSON responses to the client.
 *  - **Response Standardization**: Ensures every JSON response contains a standardized
 *    structure with properties like `success`, `message`, and optional parameters.
 *  - **Integration with Modules**: Works seamlessly with specific modules and request objects
 *    to generate dynamic responses.
 *
 *  ## Features:
 *  - Simplifies the construction of JSON responses containing success or error information.
 *  - Standardized response format with additional flexibility for customization.
 *  - Automatically sets the content type for responses to `application/json`.
 *
 *  ## Usage:
 *  The `JsonController` is intended for use in APIs or AJAX controllers that
 *  exclusively deal with JSON responses. It provides an easy, standardized mechanism
 *  for returning success or error messages along with additional data.
 *
 *  Example:
 *  ```
 *  class ApiExampleController extends JsonController
 *  {
 *      public function fetchData()
 *      {
 *          // Simulate fetching data
 *          $data = ['key1' => 'value1', 'key2' => 'value2'];
 *
 *          return $this->respondWith(true, 'Data fetched successfully.', $data);
 *      }
 *  }
 *  ```
 *
 * ## Response Format:
 *  The `respondWith` method generates a JSON structure similar to the following example:
 *  ```
 *  {
 *      "success": true,
 *      "message": "Operation completed successfully.",
 *      "key1": "value1",
 *      "key2": "value2"
 *  }
 *  ```
 *  If the `$success` parameter is `false`, an additional `error` field will be included:
 *  ```
 *  {
 *      "success": false,
 *      "message": "Operation failed.",
 *      "error": "Operation failed."
 *  }
 *  ```
 *
 * ## Features and Benefits:
 *  - Keeps API structures consistent with properly formatted JSON responses.
 *  - Combines success/error messages with customizable additional parameters in one response.
 *  - Integrates seamlessly with other components in the framework (e.g., `JsonResponse` and `Request`).
 *
 * @package System
 * @extends BaseController
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
 */
abstract class JsonController extends BaseController
{
    /**
     * The response object
     *
     * @var JsonResponse
     */
    protected JsonResponse $response;

    /**
     * Constructor method.
     *
     * @param ModuleProvider $provider The module instance.
     * @param Request $request The request instance.
     */
    public function __construct(ModuleProvider $provider, Request $request)
    {
        parent::__construct($provider, $request);
        $this->response = new JsonResponse($request);
    }

    /**
     * Constructs a JSON response with the specified success status, message, and additional parameters.
     *
     * @param bool $success Indicates whether the response represents a successful operation.
     * @param array|string $message The message or messages to include in the response.
     * @param array $params Optional additional parameters to include in the response.
     *
     * @return JsonResponse The constructed JSON response object.
     */
    public function respondWith(bool $success, array|string $message, array $params = []): JsonResponse
    {
        $params['success'] = $success;
        $params['message'] = $message;
        if (!$success)
            $params['error'] = $message;

        $this->response->contentType('application/json');
        $this->response->append($params);
        return $this->response;
    }
}