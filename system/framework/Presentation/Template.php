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
namespace System\Presentation;
use Exception;
use System\HtmlController;
use System\IO\Directory;
use System\IO\File;
use System\IO\FileStream;
use System\IO\Path;
use System\ModuleProvider;
use System\Presentation\Engine\Compiler;
use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\CompilerInterface;
use System\Presentation\Engine\Lexer;
use System\Presentation\Engine\LexerException;
use System\Presentation\Engine\LexerInterface;
use System\Presentation\Engine\Parser;
use System\Presentation\Engine\ParserInterface;
use System\Presentation\Engine\ParsingException;
use Throwable;
use TypeError;

/**
 * A class responsible for managing and compiling template views into a cacheable format.
 *
 * The Template class provides functionality for setting themes, managing compiled templates,
 * and performing template compilation using Lexer, Parser, and Compiler components.
 * It employs caching mechanisms and ensures that template files are up-to-date before
 * returning the compiled versions.
 */
class Template
{
    /**
     * templateFilePath => cacheFilePath
     */
    private static array $compiledFiles = [];

    /**
     * The name of the theme. Default is null.
     */
    private static ?string $themeName = null;

    /**
     * Defines the directory path for storing compiled templates or cache files.
     * This is constructed using the application directory constant, directory separator,
     * and specific sub-directory names for cache and compiled files.
     */
    private static string $compileDir = APP_DIR . DS . 'cache' . DS . 'compiled';

    /**
     * @var null|callable(): LexerInterface
     */
    private static $lexerFactory = null;

    /** @var null|callable(): ParserInterface */
    private static $parserFactory = null;

    /**
     * @var null|callable(string $compileDir): CompilerInterface
     */
    private static $compilerFactory = null;

    /***
     * Sets the directory where compiled files will be stored and resets the compiled files list.
     *
     * @param string $dir The directory path for compiled files.
     *
     * @return void
     */
    public static function SetCompileDir(string $dir): void
    {
        if (strcmp($dir, self::$compileDir) != 0)
        {
            // Ensure the directory exists
            if (!Directory::Exists($dir))
                Directory::CreateDirectory($dir);

            self::$compileDir = $dir;
            self::$compiledFiles = [];
        }
    }

    /**
     * Retrieves the directory path used for storing compiled templates.
     *
     * This method returns the path of the directory where compiled template files
     * are stored. The directory is typically configured as part of the application's
     * template compilation process.
     *
     * @return string The path to the compile directory.
     */
    public static function GetCompileDir(): string
    {
        return self::$compileDir;
    }

    /**
     * Sets the current theme for the application and resets the compiled files cache.
     *
     * @param string $themeName The name of the theme to set
     *
     * @return void
     *
     * @throws InvalidThemePathException If the theme's template.xml file is missing or the path is invalid.
     * @throws ViewNotFoundException If the specified {@see Layout::$layout} file does not exist.
     */
    public static function SetTheme(string $themeName): void
    {
        if (strcmp($themeName, self::$themeName) != 0)
        {
            self::$themeName = $themeName;
            self::$compiledFiles = [];
        }

        // Check
        $layout = HtmlController::GetLayout();
        if ($layout instanceof LayoutInterface)
        {
            $layout->reloadTheme();
        }
    }

    /**
     * Retrieves the current theme name.
     *
     * @return string The name of the current theme.
     */
    public static function GetTheme(): string
    {
        return self::$themeName ?? \System::Config()->get("theme", "default");
    }

    /**
     * Checks if the specified file has been compiled and optionally provides the path to the cache file.
     *
     * @param string|View $file The path to the file to check or the view file.
     * @param ?string $cacheFile Optional variable to store the path to the compiled cache file, if available.
     *
     * @return bool Returns true if the file is compiled, otherwise false.
     */
    public static function IsCompiled(string|View $file, ?string &$cacheFile): bool
    {
        if ($file instanceof View)
            $filePath = $file->filePath;
        else
            $filePath = Path::Normalize($file);

        // Have we already compiled this file?
        if (array_key_exists($filePath, self::$compiledFiles))
        {
            $cacheFile = self::$compiledFiles[$filePath];
            return true;
        }
        else
        {
            $cacheFile = self::GetCacheFileNameAndPath($filePath);
            if (!file_exists($cacheFile))
                return false;

            // This correctly invalidates cache when template is modified
            $isValid = filemtime($cacheFile) >= filemtime($filePath);
            if ($isValid) self::$compiledFiles[$filePath] = $cacheFile;
            return $isValid;
        }
    }

    /**
     * Compiles the specified view into a compiled phtml file.
     *
     * This method normalizes the file path from the provided view, checks if a
     * cached version already exists, and determines whether re-compilation is
     * needed. If necessary, it tokenizes, parses, and compiles the view content
     * into a compiled file stored in the cache directory.
     *
     * Compilation involves leveraging a Lexer, Parser, and Compiler to process
     * the view content, ensuring proper handling of templates. The method also
     * implements locking mechanisms and retry strategies to handle high-concurrency
     * scenarios on websites with heavy traffic.
     *
     * @param View $view The view instance containing the file path and other relevant data.
     * @param bool $recompile Whether to force re-compilation. If false, the method
     *                        returns the existing compiled file if it is already up-to-date.
     *
     * @return string The path to the compiled cache file.
     *
     * @throws TypeError If the Lexer, Parser, or Compiler factory does not return an expected instance.
     * @throws Exception If the cache lock file remains after the maximum retries or another critical error occurs.
     * @throws LexerException If an error occurs during lexical analysis.
     * @throws ParsingException If an error occurs during parsing.
     * @throws CompilerException|Throwable If an error occurs during compilation.
     */
    public static function Compile(View $view, bool $recompile = false): string
    {
        $filePath = Path::Normalize($view->filePath);
        if (!$recompile && self::IsCompiled($filePath, $cacheFile))
        {
            return $cacheFile;
        }

        // Ensure we have a cache file path
        if (empty($cacheFile))
            $cacheFile = self::GetCacheFileNameAndPath($filePath);

        // Need to compile
        try
        {
            /** @var LexerInterface $lexer */
            $lexer = self::$lexerFactory ? (self::$lexerFactory)() : new Lexer();
            if (!$lexer instanceof LexerInterface) {
                throw new TypeError('Lexer factory must return '. LexerInterface::class);
            }

            /** @var ParserInterface $parser */
            $parser = self::$parserFactory ? (self::$parserFactory)() : new Parser();
            if (!$parser instanceof ParserInterface) {
                throw new TypeError('Parser factory must return '. ParserInterface::class);
            }

            /** @var CompilerInterface $compiler */
            $compiler = self::$compilerFactory ? (self::$compilerFactory)(self::$compileDir) : new Compiler(self::$compileDir);
            if (!$compiler instanceof CompilerInterface) {
                throw new TypeError('Compiler factory must return '. CompilerInterface::class);
            }

            // Ensure the cache directory exists
            $dir = Path::GetDirectoryName($cacheFile);
            if (!Directory::Exists($dir)) {
                Directory::CreateDirectory($dir);
            }

            $lockFile = $cacheFile . '.lock';
            $stream = new FileStream($cacheFile, FileStream::WRITE);
            if ($stream->lock(true, true))
            {
                touch($lockFile);
                try
                {
                    // Tokenize, parse, and compile the template
                    $contents = File::ReadAllText($filePath);

                    $tokens = $lexer->tokenize($contents);
                    $nodeCollection = $parser->parse($tokens);
                    $contents = $compiler->compile($nodeCollection);

                    // Clear the stream only after we acquired the lock
                    $stream->truncate(0);

                    // Compile and cache the file while holding the lock
                    $stream->write($contents);

                    // Unlock and close the file stream
                    $stream->unlock();
                    $stream->close();

                    // If we are here, the compilation was successful, so cache the compiled file
                    self::$compiledFiles[$filePath] = $cacheFile;
                }
                catch (Throwable $e)
                {
                    $stream->unlock();
                    $stream->close();
                    File::Delete($cacheFile);
                    throw $e;
                }
                finally
                {
                    File::Delete($lockFile);
                }
            }
            else
            {
                // Close the stream
                $stream->close();

                // Retry/Backoff strategy for websites with high traffic
                // Retry loop with exponential backoff + jitter
                $maxRetries = 5; // Maximum number of retries
                $baseDelay = 100; // Base delay in milliseconds (100ms)
                $retries = 0;

                // If lock is held by another process, wait until the compiled cache becomes available
                while ((File::Exists($lockFile) || !File::Exists($cacheFile)) && $retries <= $maxRetries)
                {
                    $retries++;
                    $jitter = random_int(10, 150); // Add random jitter in milliseconds
                    $delay = ($baseDelay * $retries) + $jitter; // Exponential backoff + jitter
                    usleep($delay * 1000); // Convert milliseconds to microseconds for usleep
                }

                // If retries exhausted and lock still exists, throw an exception or log a warning
                if ($retries == 5 && File::Exists($lockFile)) {
                    throw new Exception("Cache lock file still exists after maximum retries. Aborting.");
                }
            }

            return $cacheFile;
        }
        catch (LexerException|ParsingException|CompilerException $e)
        {
            // Determine the source name for debugging
            $sourceName = str_replace(ROOT, '', $filePath);

            // Enrich the exception with the View name/path and re-throw
            $e->setViewFile($sourceName);
            throw $e;
        }
    }

    /**
     * Resolves the file path for a module's view based on the specified view type and file name.
     *
     * @param ModuleProvider $provider The module for which the view path needs to be resolved.
     * @param string $viewFileName The name of the view file (without extension).
     *  This is relative to the application's views folder or the root template folder if a theme is set.
     *
     * @return string The resolved file path to the view file.
     *
     * @throws ViewNotFoundException If the view file cannot be found.
     */
    public static function ResolveModuleViewPath(ModuleProvider $provider, string $viewFileName): string
    {
        $templatePath = Path::Combine(ROOT, 'application', 'templates', self::$themeName ?? 'default');
        if (str_ends_with(strtolower($viewFileName), '.tpl')) {
            $viewFileName = substr($viewFileName, 0, -4);
        }
        $viewFileName = Path::Normalize($viewFileName);

        // Check if the template overrides the module view
        $viewPath = Path::Combine($templatePath, 'modules', $provider->module->name, $viewFileName . '.tpl');
        if (!file_exists($viewPath))
        {
            // Fallback to the module's own view folder
            $viewPath2 = Path::Combine($provider->getRootPath(), 'views', $viewFileName . '.tpl');

            // Ensure we found something
            if (!file_exists($viewPath2))
            {
                // If we get here, the partial was not found in any include path
                $searchedPaths = implode(', ', array_map(
                    fn($path) => pathinfo($path, PATHINFO_DIRNAME),
                    [$viewPath, $viewPath2]
                ));
                throw new ViewNotFoundException("View file not found: $viewFileName. Searched: $searchedPaths");
            }
            $viewPath = $viewPath2;
        }

        return $viewPath;
    }

    /**
     * Resolves the full path to a view file based on its name and type.
     *
     * @param string $viewFileName The name of the view file (without extension).
     *  This is relative to the application's views folder or the root template folder if a theme is set.
     *
     * @return string The resolved path to the view file.
     *
     * @throws ViewNotFoundException If the view file cannot be found.
     */
    public static function ResolveViewPath(string $viewFileName): string
    {
        $templatePath = Path::Combine(ROOT, 'application', 'templates', self::$themeName ?? 'default');
        if (str_ends_with(strtolower($viewFileName), '.tpl')) {
            $viewFileName = substr($viewFileName, 0, -4);
        }
        $viewFileName = Path::Normalize($viewFileName);

        // Check if the template overrides the module view
        $viewPath = Path::Combine($templatePath, $viewFileName . '.tpl');
        if (!file_exists($viewPath))
        {
            // Fallback to the applications own view folder
            $viewPath2 = Path::Combine(APP_DIR, 'views', $viewFileName . '.tpl');

            // Ensure we found something
            if (!file_exists($viewPath2))
            {
                // If we get here, the partial was not found in any include path
                $searchedPaths = implode(', ', array_map(
                    fn($path) => pathinfo($path, PATHINFO_DIRNAME),
                    [$viewPath, $viewPath2]
                ));
                throw new ViewNotFoundException("View file not found: $viewFileName. Searched: $searchedPaths");
            }
            $viewPath = $viewPath2;
        }

        return $viewPath;
    }

    /**
     * Generates and returns the full file path and name for the cached compiled template.
     * If no custom cache key is set, a new cache key will be generated dynamically.
     *
     * @return string The full path to the cached compiled template file as a string.
     */
    public static function GetCacheFileNameAndPath(string $filePath): string
    {
        $cacheKey = self::GenerateCompiledTemplateCacheKey($filePath);
        return Path::Combine(self::$compileDir, 'views', $cacheKey . '.phtml');
    }

    /**
     * Generates the unique cache key for the compiled template based on the contents of the View
     *
     * @return string Cache key
     */
    protected static function GenerateCompiledTemplateCacheKey($filePath): string
    {
        return substr(md5($filePath), 0, 16);
    }

    /**
     * Sets the factory callable for creating Lexer instances.
     *
     * @param callable $factory The callable responsible for creating Lexer instances.
     *
     * @return void
     */
    public static function SetLexerFactory(callable $factory): void
    {
        self::$lexerFactory = $factory;
        self::$compiledFiles = [];
    }

    /**
     * Sets the factory responsible for creating parser instances.
     *
     * @param callable $factory A callable that returns a new parser instance.
     *
     * @return void
     */
    public static function SetParserFactory(callable $factory): void
    {
        self::$parserFactory = $factory;
        self::$compiledFiles = [];
    }

    /**
     * Sets the factory callable used to create compiler instances.
     *
     * @param callable $factory A factory callable that returns a new compiler instance. callable(string $compileDir)
     *
     * @return void
     */
    public static function SetCompilerFactory(callable $factory): void
    {
        self::$compilerFactory = $factory;
        self::$compiledFiles = [];
    }

    /**
     * Resets the engine factories and clears the compiled files cache.
     *
     * @return void
     */
    public static function ResetEngineFactories(): void
    {
        self::$lexerFactory = null;
        self::$parserFactory = null;
        self::$compilerFactory = null;
        self::$compiledFiles = [];
    }
}