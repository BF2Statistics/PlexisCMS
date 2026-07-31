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

use System;
use System\Configuration\ConfigBase;
use System\Configuration\ConfigManager;
use System\Http\Request;
use System\Http\Response;
use System\IO\Path;
use System\Presentation\View;
use System\Presentation\ViewNotFoundException;

/**
 * This class provides common methods for module controllers to inherit.
 *
 * @package System
 */
abstract class BaseController
{
    /**
     * This module object
     * @Module
     */
    protected ModuleProvider $moduleProvider;

    /**
     * The root path to the module extending this class
     * @var string
     */
    protected string $modulePath;

    /**
     * The http path to the module's root folder
     * @var string
     */
    protected string $moduleUri;

    /**
     * The child module name
     * @var string
     */
    protected string $moduleName;

    /**
     * The HTTP request instance
     *
     * @var Request
     */
    protected Request $request;

    /**
     * Sets up the correct $modulePath and $moduleName variables
     *
     * @param ModuleProvider $provider The Module object of the child Module. Not to be
     *   confused with the child controller, but the argument passed to the chile
     *   controller.
     * @param Request $request
     */
    public function __construct(ModuleProvider $provider, Request $request)
    {
        // Define all our paths for this module
        $this->moduleProvider = $provider;
        $this->moduleName = $provider->module->name;
        $this->modulePath = $provider->getRootPath();
        $this->moduleUri = str_replace(array(ROOT, '\\'), array('', '/'), $this->modulePath);

        // Assign our request value
        $this->request = $request;
    }

    /**
     * Checks to see if the POST or GET action matches any of the arguments passed
     * to this function, and returns the result
     *
     * @return bool true if one of the passed arguments matches the current action,
     *  otherwise false.
     */
    public function isAction(string ...$actionList): bool
    {
        // Check if action is set and exists
        return array_any(
            $actionList,
            fn($action) =>
                $this->request->post('action') == $action || $this->request->get('action') == $action
        );
    }

    /**
     * Loads a model for the child controller.
     *
     * The model will be searched for in the modules "models" folder. The
     * result will also be stored in a class variable, the name of the class:
     * "$this->{$name}".
     *
     * @param string $name The model name to load. This is case-sensitive.
     * @param string|null $propertyName The internal property name to store the model class into.
     * @param array $params An array or parameters to pass to the constructor. Default empty array.
     *
     * @return object The constructed model object
     *
     * @throws \ReflectionException if the class constructor is not public or if the class does not have a constructor
     *                              and the $params parameter contains one or more parameters.
     * @throws \System\IO\FileNotFoundException if the model file could not be located.
     *
     */
    protected function loadModel(string $name, ?string $propertyName = null, array $params = array()): object
    {
        // Fully qualified name?
        if (str_contains($name, '\\'))
        {
            $parts = explode('\\', $name);
            $name = array_pop($parts);
        }

        // Get our path, check for existence
        $modelName = ucfirst($name);
        $path = Path::Combine($this->modulePath, 'Models', $modelName . '.php');
        if (!file_exists($path))
            throw new IO\FileNotFoundException("Model file not found: $path");

        // Load the file
        require $path;

        // Add Namespace to class name
        $nsName = '\\Modules\\' . ucfirst($this->moduleName) . "\\Models\\" . $name;

        // Make sure we have a property name
        if (empty($propertyName))
            $propertyName = lcfirst($modelName);

        // Init a reflection class
        if (!empty($params))
        {
            $Reflection = new \ReflectionClass($nsName);
            if ($Reflection->hasMethod('__construct'))
                $class = $Reflection->newInstanceArgs($params);
            else
                $class = new $nsName();
        }
        else
            $class = new $nsName();

        // Set the model as a class variable
        $this->{$propertyName} = $class;
        return $class;
    }

    /**
     * Loads a controller from the current modules folder and returns a new
     *   instance of that class
     *
     * @param string $name The name of the controller to load. The
     *   result will also be stored in a class variable, the name of the class:
     *   "$this->{$name}".
     *
     * @param Request $Request The request object for the controller
     *   to use
     *
     * @return object|bool Returns the constructed controller or false if
     *   the controller does not exist
     */
    protected function loadController(string $name, Request $Request): object|bool
    {
        // Check for the files existence
        $name = ucfirst($name);
        $path = Path::Combine($this->modulePath, 'Controllers', $name . '.php');
        if (!file_exists($path))
            return false;

        // Load the file
        require $path;

        // Init a reflection class
        $nsName = ucfirst($this->moduleName) . "\\" . $name;
        return new $nsName($this->moduleProvider, $Request);
    }

    /**
     * Loads a config file from the modules config folder
     *
     * @param string $name The name of the config file to load (no extension)
     *
     * @return bool|ConfigBase
     */
    protected function loadConfig(string $name): ConfigBase|bool
    {
        // Get our path
        $path = Path::Combine($this->modulePath, 'config', $name . '.php');
        $result = false;
        try
        {
            $result = ConfigManager::Load($path);
        }
        catch (\Exception $e)
        {

        }

        return $result;
    }
}