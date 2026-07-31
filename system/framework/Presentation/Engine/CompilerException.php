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
namespace System\Presentation\Engine;

use System\ContextException;

class CompilerException extends ContextException
{
    /**
     * Set the view file associated with this exception.
     * Appends the file name to the message and adds it to the context.
     *
     * @param string $file The path or name of the view file.
     */
    public function setViewFile(string $file): void
    {
        // Only set the view file if it hasn't already been set (bottom level)
        if (!isset($this->context['view']))
        {
            $message = trim($this->getMessage(), '.');
            $this->message = $message . ", in view [{$file}]";
            $this->context['view'] = $file;
        }
    }
}