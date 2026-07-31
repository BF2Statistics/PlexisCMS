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
namespace System\Security;
use System\Configuration\ConfigBase;
use System\Configuration\ConfigManager;

/**
 * Class responsible for filtering XSS (Cross-Site Scripting) vulnerabilities from input.
 * Provides mechanisms for filtering HTML tags and attributes using whitelist or blacklist methods.
 */
class XssFilter
{
    /**
     * Constant containing the cleaning method of whitelist
     * @var int
     */
    const int WHITELIST = 0;

    /**
     * Constant containing the cleaning method of blacklist
     * @var int
     */
    const int BLACKLIST = 1;

    /**
     * Xss Filter Config
     * @var ConfigBase
     */
    protected static $Config;

    /**
     * Array of tags to be filtered
     * @var string[]
     */
    protected array $tagsArray = array();

    /**
     * Array of attributes to be filtered
     * @var string[]
     */
    protected array $attrArray = array();

    /**
     * Tags list cleaning method (whitelist or blacklist)
     * @var int
     */
    protected int $tagsMethod = self::WHITELIST;

    /**
     * Attributes list cleaning method (whitelist or blacklist)
     * @var int
     */
    protected int $attrMethod = self::WHITELIST;

    /**
     * Automatically remove blacklisted tags and attributes
     * @var bool
     */
    protected bool $useBlacklist = true;

    /**
     * Constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        // Load the config file for this
        if(!(self::$Config instanceof ConfigBase))
        {
            $path = SYSTEM_DIR . DS . 'config' . DS .  'xssfilter.class.php';
            try {
                self::$Config = ConfigManager::Load($path);
            }
            catch( \System\IO\FileNotFoundException $e ) {
                throw new \Exception('Missing XssFilter class configuration file: '. $path);
            }
        }
    }

    /**
     * Adds a tag to the array of tags to be filtered. Adding tags does not clear
     * the current array of tags
     *
     * @param string $tag The html tag name to be filtered
     *
     * @return void
     */
    public function addTag(string $tag): void
    {
        $tag = strtolower($tag);
        if (!in_array($tag, $this->tagsArray))
            $this->tagsArray[] = $tag;
    }

    /**
     * Adds an array of tags to be filtered. Adding tags does not clear
     * the current array of tags
     *
     * @param string[] $tags An array of html tag names to be filtered
     *
     * @return void
     */
    public function addTagsArray(array $tags): void
    {
        foreach ($tags as $tag)
        {
            $this->addTag($tag);
        }
    }

    /**
     * Adds an attribute to the array of attributes to be filtered.
     * Adding attributes does not clear the current array of attributes
     *
     * @param string $attr The html attribute name to be filtered
     *
     * @return void
     */
    public function addAttr($attr): void
    {
        $attr = strtolower($attr);
        if (!in_array($attr, $this->attrArray))
            $this->attrArray[] = $attr;
    }

    /**
     * Adds an array of attributes to be filtered. Adding attributes does not clear
     * the current array of attributes
     *
     * @param string[] $attrs An array of html attribute names to be filtered
     *
     * @return void
     */
    public function addAttrArray(array $attrs): void
    {
        foreach ($attrs as $attr)
        {
            $this->addAttr($attr);
        }
    }

    /**
     * Sets the tag method for filtering.
     *
     * @param int $mode The filter method. This value will either be
     *   the constant value of XssFilter::WHITELIST or XssFilter::BLACKLIST.
     *   If set to whitelist, all tags that are NOT defined will be removed.
     *   If set to blacklist, all tags defined <b>will</b> be removed from the source,
     *   and all non-defined tags will <b>not</b> be filtered out
     *
     * @return void
     *@throws \Exception
     */
    public function setTagsMethod(int $mode): void
    {
        if ($mode != 0 && $mode != 1)
            throw new \Exception('Invalid argument type for $mode');

        $this->tagsMethod = $mode;
    }

    /**
     * Sets the attribute method for filtering.
     *
     * @param int $mode The filter method. This value will either be
     *   the constant value of XssFilter::WHITELIST or XssFilter::BLACKLIST.
     *   If set to whitelist, all attributes that are NOT defined will be removed.
     *   If set to blacklist, all attributes defined <b>will</b> be removed from the source,
     *   and all non-defined attributes will <b>not</b> be filtered out
     *
     * @return void
     *@throws \Exception
     */
    public function setAttrMethod(int $mode): void
    {
        if ($mode != 0 && $mode != 1)
            throw new \Exception('Invalid argument type for $mode');

        $this->attrMethod = $mode;
    }

    /**
     * Defines whether the filter should use the blacklist of tags and attributes,
     * and automatically remove them. Blacklist is enabled by default.
     *
     * @param bool $bool Auto remove blacklisted tags and attributes? Blacklisted tags and
     *   attributes are defined in the "system/config/xssfilter.class.php" config file.
     *
     * @return void
     */
    public function useBlacklist(bool $bool = true): void
    {
        $this->useBlacklist = $bool;
    }

    /**
     * Cleans a source string, using the Xss Filter tags and methods defined.
     *
     * @param string|string[] $source The source to be cleaned. May also be an array
     *   of strings to be cleaned
     *
     * @return string|string[] The cleaned source
     */
    public function clean(array|string $source): array|string
    {
        // If in array, clean each value
        if (is_array($source))
        {
            foreach ($source as $key => $value)
            {
                if (is_string($value))
                {
                    // filter element for XSS and other 'bad' code etc.
                    $source[$key] = $this->remove($this->decode($value));
                }
            }
            return $source;
        }
        elseif (is_string($source))
        {
            // filter element for XSS and other 'bad' code etc.
            return $this->remove($this->decode($source));
        }

        return $source;
    }

    /**
     * Removes all unwanted tags and attributes
     *
     * @param string $source The source to be cleaned.
     *
     * @return string Returns the cleaned source
     */
    protected function remove(string $source): string
    {
        $loopCounter = 0;
        while($source != $this->filterTags($source))
        {
            $source = $this->filterTags($source);
            $loopCounter++;
        }
        return $source;
    }

    /**
     * Internal method for removing all unwanted tags
     *
     * @param string $source The source to be cleaned.
     *
     * @return string Returns the cleaned source
     */
    protected function filterTags(string $source): string
    {
        $preTag = null;
        $postTag = $source;

        // find initial tag's position
        $tagOpen_start = strpos($source, '<');

        // iterate through string until no tags left
        while ($tagOpen_start !== false)
        {
            // process tag interactively
            $preTag .= substr($postTag, 0, $tagOpen_start);
            $postTag = substr($postTag, $tagOpen_start);
            $fromTagOpen = substr($postTag, 1);
            $tagOpen_end = strpos($fromTagOpen, '>');
            if($tagOpen_end === false)
            {
                break;
            }

            // next start of tag (for nested tag assessment)
            $tagOpen_nested = strpos($fromTagOpen, '<');
            if (($tagOpen_nested !== false) && ($tagOpen_nested < $tagOpen_end))
            {
                $preTag .= substr($postTag, 0, ($tagOpen_nested + 1));
                $postTag = substr($postTag, ($tagOpen_nested + 1));
                $tagOpen_start = strpos($postTag, '<');
                continue;
            }

            $currentTag = substr($fromTagOpen, 0, $tagOpen_end);
            $tagLength = strlen($currentTag);
            if (!$tagOpen_end)
            {
                $preTag .= $postTag;
            }

            // iterate through tag finding attribute pairs - setup
            $tagLeft = $currentTag;
            $attrSet = array();
            $currentSpace = strpos($tagLeft, ' ');

            // is end tag
            if (str_starts_with($currentTag, "/"))
            {
                $isCloseTag = true;
                list($tagName) = explode(' ', $currentTag);
                $tagName = substr($tagName, 1);
            }

            // is start tag
            else
            {
                $isCloseTag = false;
                list($tagName) = explode(' ', $currentTag);
            }

            // excludes all "non-regular" tag names OR no tag name OR remove if xssauto is on and tag is blacklisted
            if (!preg_match("/^[a-z][a-z0-9]*$/i", $tagName) || !$tagName || ((in_array(strtolower($tagName), self::$Config["tagBlacklist"])) && $this->useBlacklist))
            {
                $postTag = substr($postTag, ($tagLength + 2));
                $tagOpen_start = strpos($postTag, '<');
                continue;
            }

            // this while is needed to support attribute values with spaces in!
            while ($currentSpace !== false)
            {
                $fromSpace = substr($tagLeft, ($currentSpace+1));
                $nextSpace = strpos($fromSpace, ' ');
                $openQuotes = strpos($fromSpace, '"');
                $closeQuotes = strpos(substr($fromSpace, ($openQuotes+1)), '"') + $openQuotes + 1;

                // another equals exists
                if (str_contains($fromSpace, '='))
                {
                    // opening and closing quotes exists
                    if (($openQuotes !== false) && (str_contains(substr($fromSpace, ($openQuotes + 1)), '"')))
                    {
                        $attr = substr($fromSpace, 0, ($closeQuotes+1));
                    }

                    // one or neither exist
                    else
                    {
                        $attr = substr($fromSpace, 0, $nextSpace);
                    }
                }

                // no more equals exist
                else
                {
                    $attr = substr($fromSpace, 0, $nextSpace);
                }

                // last attr pair
                if (!$attr)
                {
                    $attr = $fromSpace;
                }

                // add to attribute pairs array
                $attrSet[] = $attr;

                // next inc
                $tagLeft = substr($fromSpace, strlen($attr));
                $currentSpace = strpos($tagLeft, ' ');
            }

            // appears in array specified by user
            $tagFound = in_array(strtolower($tagName), $this->tagsArray);

            // remove this tag on condition
            if ((!$tagFound && $this->tagsMethod || ($tagFound && !$this->tagsMethod)))
            {
                // reconstruct tag with allowed attributes
                if(!$isCloseTag)
                {
                    $attrSet = $this->filterAttr($attrSet);
                    $preTag .= '<' . $tagName;
                    for($i = 0; $i < count($attrSet); $i++)
                    {
                        $preTag .= ' ' . $attrSet[$i];
                    }

                    // reformat single tags to XHTML
                    if(strpos($fromTagOpen, "</" . $tagName))
                    {
                        $preTag .= '>';
                    }
                    else
                    {
                        $preTag .= ' />';
                    }
                }

                // just the tagname
                else
                {
                    $preTag .= '</' . $tagName . '>';
                }
            }

            // find next tag's start
            $postTag = substr($postTag, ($tagLength + 2));
            $tagOpen_start = strpos($postTag, '<');
        }

        // append any code after end of tags
        $preTag .= $postTag;
        return $preTag;
    }

    /**
     * Internal method for removing all unwanted attributes
     *
     * @param string[] $attrSet An array of attribute sets in a tag
     *
     * @return string[] Returns an array of filtered attribute sets
     */
    protected function filterAttr(array $attrSet): array
    {
        $newSet = array();

        // process attributes
        for ($i = 0; $i <count($attrSet); $i++)
        {
            // skip blank spaces in tag
            if (!$attrSet[$i])
            {
                continue;
            }

            // split into attr name and value
            $attrSubSet = explode('=', trim($attrSet[$i]));
            list($attrSubSet[0]) = explode(' ', $attrSubSet[0]);

            // removes all "non-regular" attr names AND also attr blacklisted
            if ((!preg_match("/^[a-z]*$/i", $attrSubSet[0])) || ($this->useBlacklist && ((in_array(strtolower($attrSubSet[0]), self::$Config["attrBlacklist"])) || (str_starts_with($attrSubSet[0], 'on')))))
            {
                continue;
            }

            // xss attr value filtering
            if ($attrSubSet[1])
            {
                // strips unicode, hex, etc
                $attrSubSet[1] = str_replace('&#', '', $attrSubSet[1]);

                // strip normal newline within attr value
                $attrSubSet[1] = preg_replace('/\s+/', '', $attrSubSet[1]);

                // strip double quotes
                $attrSubSet[1] = str_replace('"', '', $attrSubSet[1]);

                // [requested feature] convert single quotes from either side to doubles (Single quotes shouldn't be used to pad attr value)
                if ((str_starts_with($attrSubSet[1], "'")) && (substr($attrSubSet[1], (strlen($attrSubSet[1]) - 1), 1) == "'"))
                {
                    $attrSubSet[1] = substr($attrSubSet[1], 1, (strlen($attrSubSet[1]) - 2));
                }

                // strip slashes
                $attrSubSet[1] = stripslashes($attrSubSet[1]);
            }

            // auto strip attr's with javascript:
            if (
                ((str_contains(strtolower($attrSubSet[1]), 'expression')) && (strtolower($attrSubSet[0]) == 'style'))
                || (str_contains(strtolower($attrSubSet[1]), 'javascript:'))
                || (str_contains(strtolower($attrSubSet[1]), 'behaviour:'))
                || (str_contains(strtolower($attrSubSet[1]), 'vbscript:'))
                || (str_contains(strtolower($attrSubSet[1]), 'mocha:'))
                || (str_contains(strtolower($attrSubSet[1]), 'livescript:'))
            ) continue;

            // if matches user defined array
            $attrFound = in_array(strtolower($attrSubSet[0]), $this->attrArray);

            // keep this attr on condition
            if ((!$attrFound && $this->attrMethod) || ($attrFound && !$this->attrMethod))
            {
                // attr has value
                if($attrSubSet[1])
                {
                    $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[1] . '"';
                }

                // attr has decimal zero as value
                elseif($attrSubSet[1] == "0")
                {
                    $newSet[] = $attrSubSet[0] . '="0"';
                }

                // reformat single attributes to XHTML
                else
                {
                    $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[0] . '"';
                }
            }
        }
        return $newSet;
    }

    /**
     * Decodes all html entities from the source
     *
     * @param string $source The source to be converted.
     *
     * @return string Returns the converted source
     */
    protected function decode(string $source): string
    {
        $source = html_entity_decode($source, ENT_QUOTES, "ISO-8859-1");
        $source = preg_replace_callback('/&#(\d+);/',
            function ($matches) {
                return chr($matches[1]);
            },
            $source
        );

        return preg_replace_callback('/&#x([a-f0-9]+);/i',
            function ($matches) {
                return chr(hexdec($matches[1]));
            },
            $source
        );
    }
}