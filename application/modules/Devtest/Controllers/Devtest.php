<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace Modules\Devtest\Controllers;

use System\Diagnostics\Debug;
use System\HtmlController;
use System\Http\RouteNotFoundEvent;
use System\Http\Response;
use System\Http\RouteMatchedEvent;
use System\IO\File;
use System\IO\Path;
use System\ModuleNotFoundException;
use System\ModuleProvider;
use System\Presentation\Engine\Compiler;
use System\Presentation\Engine\Lexer;
use System\Presentation\Engine\Parser;
use System\Routing\Route;
use System\Routing\RouteTarget;
use System\Routing\RouteNotFoundException;
use System\Routing\Router;

class Devtest extends HtmlController
{
    /**
     * @protocol    GET
     * @request     /
     * @output      html
     */
    #[Route('/test', 'dev-test', ['GET'])]
    public function getIndex(): Response
    {
        /*
        $data = md5('test');
        $key = \System::Config()->get('security_seed');
        $aes = new Aes($data, $key, 'aes-256-cbc');

        $enc = $aes->encrypt();
        $len = strlen($enc);

        echo '<pre>';
        echo 'MD5 Hast: ' . $data . PHP_EOL;
        echo 'Encrypted: ' . $aes->encrypt() . ' ('. $len .')' . PHP_EOL;
        echo 'Base64 Encoded (strlen): ' . $enc . ' (' . strlen($enc) . ')' . PHP_EOL;
        echo 'Binary Data Length (pre-Base64): ' . strlen(base64_decode($enc)) . PHP_EOL; // Original binary data length

        $aes->setData($enc);
        echo 'Decrypted: ' . $aes->decrypt() . PHP_EOL;
        echo '</pre>';


        die;
        return $this->respondWith('test');
        */

        $cache = Path::Combine(APP_DIR, 'templates', 'default', 'compiled');
        $path = Path::Combine(APP_DIR, 'templates', 'default', 'layouts', 'test.tpl');
        $file = File::ReadAllText($path);
        $tokenizer = new Lexer();
        $nodes = $tokenizer->tokenize($file);
        //Debug::DumpAndDie($nodes);
        $parsed = (new Parser())->parse($nodes);
        //Debug::DumpAndDie($parsed);
        $compiled = (new Compiler($cache))->compile($parsed);
        Debug::DumpAndDie($compiled);
    }

    /**
     * @throws RouteNotFoundException
     * @throws ModuleNotFoundException
     */
    public static function OnRouteMatched(RouteMatchedEvent $event): void
    {
        // Your event handling logic here
        $event->stopPropagation();
        $route = Router::Instance()->resolveByName('dev-test', $result);
        $event->override(ModuleProvider::Load('Devtest'), $result);
    }

    /**
     * @throws RouteNotFoundException
     * @throws ModuleNotFoundException
     */
    public static function OnRouteNotMatched(RouteNotFoundEvent $event): void
    {
        // Your event handling logic here
        $event->stopPropagation();
        $route = Router::Instance()->resolveByName('dev-test', $result);
        $event->override(ModuleProvider::Load('Devtest'), $result);
    }
}