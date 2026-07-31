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
namespace System\Presentation\Engine;

interface LexerInterface
{
    /**
     * Tokenizes a template string into a `TokenStream`.
     *
     * @throws \Exception
     */
    public function tokenize(string $template, bool $removePhpCode = true): TokenStream;
}