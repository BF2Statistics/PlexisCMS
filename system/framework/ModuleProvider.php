<?php
declare(strict_types=1);
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

use ReflectionException;
use System\Diagnostics\LogWriter;
use System\Http\Request;
use System\Http\Response;
use System\IO\Directory;
use System\Routing\RouteNotFoundException;
use System\Routing\RoutingDirective;

/**
 * Class ModuleProvider
 *
 * Manages the interaction and functionality of individual modules in the Plexis System.
 * The `ModuleProvider` class facilitates module loading, metadata handling,
 * and controller action invocation, all while ensuring module consistency
 * and segregation within the system.
 *
 * ## Key Functionalities:
 * - **Singleton Module Management**:
 *   - Caches loaded modules for reuse through the static `Load` method.
 * - **Module Existence Checks**:
 *   - Verifies if modules exist in the designated directory using the `Exists` method.
 * - **Controller Invocation**:
 *   - Provides the ability to dynamically invoke controller actions for modules.
 * - **Error Handling**:
 *   - Ensures validity of module-related operations by throwing specific exceptions.
 */
class ModuleProvider
{
    /**
     * An array of loaded modules
     * @var ModuleProvider[]
     */
    protected static array $modules = array();

    /**
     * Holds the module instance
     * @var AbstractModule
     */
    public AbstractModule $module;

    /**
     * Holds the plexis Logger object
     * @var LogWriter
     */
    protected static LogWriter $log;

    /**
     * @var string $name The name of the module
     * @throws ModuleNotFoundException if the module folder does not exist, or the Module
     *  class does not exist in the root folder of the module.
     */
    public function __construct(string $name)
    {
        $name = ucfirst($name);
        $className = 'Modules\\' . $name . '\Module';

        // Load the Module class in the root folder of the module
        if (!class_exists($className))
            throw new ModuleNotFoundException("Module '". $name ."' does not exist");

        $this->module = new $className($name);
    }

    /**
     * Main method used to fetch and load modules. This method acts
     * like a factory, and stores all loaded modules statically.
     *
     * @param string $name The name of the module folder
     *
     * @return ModuleProvider Returns a module object
     *@throws ModuleNotFoundException Thrown if the module does not
     *      exist in the modules folder
     *
     */
    public static function Load(string $name) : ModuleProvider
    {
        if (!isset(self::$modules[$name]))
            self::$modules[$name] = new ModuleProvider($name);

        return self::$modules[$name];
    }

    /**
     * Indicates whether a module exists in the modules folder
     *
     * @param string $name The name of the module
     *
     * @return bool
     */
    public static function Exists(string $name) : bool
    {
        $name = ucfirst($name);
        return Directory::Exists( APP_DIR . DS . "modules" . DS . $name . DS . 'Module.php');
    }

    /**
     * Invokes a controller and action within the module.
     *
     * @param string $controller The controller name to call. Case Sensitive!
     * @param string $action The controller method name to execute. Case IN-sensitive.
     * @param string[] $params The parameters to pass to the controller method.
     *
     * @return mixed Returns whatever the method returns, Most likely null.
     * @throws MethodNotFoundException when the controller doesn't have the given action,
     *   or the action method is not a public method
     *
     * @throws ControllerNotFoundException if the controller file cannot be located.
     * @throws MethodNotFoundException if the specified action is not found or is inaccessible.
     * @throws ReflectionException if an issue arises while inspecting or invoking the method.
     */
    public function invoke(string $controller, string $action, array $params = array()): mixed
    {
        // Build our full controller name, with namespace
        $controller = ucfirst($controller);
        $fullClassName = $this->module->namespace .'\\Controllers\\'. $controller;

        // Check if the controller exists already, if not, import it
        if (!class_exists($fullClassName, false))
        {
            // Build file path to the controller, check if it exists
            $file = $this->module->rootPath . DS . 'Controllers' . DS . $controller .'.php';
            if (!file_exists($file))
                throw new ControllerNotFoundException('Could not find the controller file "'. $file .'"');

            // Load our controller file
            require $file;
        }

        $dispatch = new $fullClassName($this);

        // Create a reflection of the controller method
        try {
            $method = new \ReflectionMethod($dispatch, $action);
        }
        catch (ReflectionException $e) {
            throw new MethodNotFoundException("Controller \"{$controller}\" does not contain the method \"{$action}\"", 0, $e);
        }

        // If the method is not public, throw MethodNotFoundException
        if (!$method->isPublic())
            throw new MethodNotFoundException("Method \"{$action}\" is not a public method, and cannot be called via URL.");

        // Invoke the module controller and action
        return $method->invokeArgs($dispatch, $params);
    }

    /**
     * Invokes a specific action on a controller based on the routing result and request.
     *
     * @param Request $request The HTTP request object containing request data.
     * @param RoutingDirective $directive The routing information object containing the target controller and action names,
     * parameters, and other routing details.
     *
     * @return Response Returns a Response object generated by the invoked controller action.
     *
     * @throws RouteNotFoundException Thrown if the controller class cannot be found, the controller file
     *  does not exist, the controller class does not implement the required base class, the action method is
     *  not defined or accessible, or the action does not return a valid Response object.
     * @throws ReflectionException
     */
    public function invokeAction(Request $request, RoutingDirective $directive): Response
    {
        // Uppercase names, and build our full controller name, with namespace
        $fullClassName = $directive->target->controllerClassName;
        $parts = explode('\\', $fullClassName);
        $controller = end($parts);
        $action = $directive->target->methodName;

        // Check if the controller exists already, if not, import it
        if (!class_exists($fullClassName, false))
        {
            // Build file path to the controller, check if it exists
            $file = $this->module->rootPath . DS .'Controllers'. DS . $controller .'.php';
            if (!file_exists($file))
                throw new Routing\RouteNotFoundException('Could not find the controller file "'. $file .'"');

            // Load our controller file
            require $file;
        }

        // Load the controller reflection
        try {
            $rController = new \ReflectionClass($fullClassName);
        }
        catch (ReflectionException $e) {
            throw new Routing\RouteNotFoundException('Module controller not found "'. $fullClassName .'"', 0, $e);
        }

        // Ensure our controller contains the IController interface
        if (!$rController->isSubclassOf('System\BaseController'))
            throw new Routing\RouteNotFoundException(
                'Module controller "'. $fullClassName .'" does not implement the required BaseController class.'
            );

        // Make sure the controller is not abstract object
        if ($rController->isAbstract())
            throw new Routing\RouteNotFoundException(
                'Module controller "'. $fullClassName .'" is abstract, and cannot be called via url'
            );

        // Check request method prefixed action
        if (!$rController->hasMethod($action))
            throw new Routing\RouteNotFoundException(
                "Controller \"{$controller}\" does not contain the a method \"{$action}\""
            );

        // If the method is not public, throw a 404 exception
        $method = $rController->getMethod($action);
        if (!$method->isPublic() || $method->isAbstract())
            throw new Routing\RouteNotFoundException("Method \"{$action}\" is not a public method or is abstract, and cannot be called via URL.");

        // @TODO catch exception RequestCancelled
        // Invoke the module controller and action
        $ci = $rController->newInstance($this, $request);// new $fullClassName($this, $request);

        try
        {
            // PHP 8+ Magic:
            // 1. Matches Route keys ('id') to Method arguments ($id) automatically.
            // 2. Uses default values if a key is missing in $result->parameters.
            // 3. Independent of order.
            $returned = $method->invokeArgs($ci, $directive->parameters);
        }
        catch (\ArgumentCountError $e)
        {
            // Occurs if Route is missing a REQUIRED parameter (no default value)
            throw new Routing\RouteNotFoundException("Missing required parameter for action '{$action}': " . $e->getMessage());
        }
        catch (\Error $e)
        {
            // Occurs if Route passes a parameter that the Controller DOES NOT accept
            // e.g. Route has {slug}, but function index($id) has no $slug
            if (str_contains($e->getMessage(), 'Unknown named parameter')) {
                throw new Routing\RouteNotFoundException("Route defines a parameter that the action '{$action}' does not accept: " . $e->getMessage());
            }
            throw $e; // Rethrow actual code errors
        }

        if (!($returned instanceof Response))
            throw new Routing\RouteNotFoundException("Method \"{$action}\" did not return a WebResponse object.");

        return $returned;
    }

    /**
     * Returns the path to the modules root folder
     *
     * @return string Returns the set controller path, or false
     *   if the path isn't set
     */
    public function getRootPath(): string
    {
        return $this->module->rootPath;
    }
}