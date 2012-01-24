<?php
/**
 * SassPlugin.class.php
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @version 1.0
 */

require 'phamlp/sass/SassParser.php';
// require 'coffeescript-php/coffeescript/coffeescript.php';

class SassPlugin extends StudIPPlugin implements SystemPlugin
{
    public function __construct()
    {
        parent::__construct();

        $this->compile('link', '.sass', function ($source) {
            $sassParser = new SassParser(array(
                'cache' => false,
                'style' => Studip\ENV === 'development' ? 'expanded' : 'compact',
            ));
            return $sassParser->toCss($source);
        });

    // unfortunately, coffeescript-php is not final yet
    /*
        $this->compile('script', '.coffee', function ($source) {
            return CoffeeScript\compile(file_get_contents($source));
        });
    */
    }

    /**
     * Extracts source files from Stud.IPs page headers, compiles them if
     * neccessary and replaces the urls with compiled ones.
     *
     * @param String   $tag       HTML tag to investigate
     * @param String   $extension File extension to investigate
     * @param Function $compiler  Compiler function, receives absolute source
     *                            file as only parameter, returns compiled
     *                            source
     */
    private function compile($tag, $extension, $compiler)
    {
        $tag_types = array(
            'link'   => array('attribute' => 'href', 'extension' => '.css', 'parameter' => null),
            'script' => array('attribute' => 'src', 'extension' => '.js', 'parameter' => ''),
        );
        $type = $tag_types[$tag];
        if (!isset($type)) {
            throw new Exception('Unknown tag type "' . $tag . '" provided');
        }
        
        $regexp = sprintf('/<%s([^>]*)%s="(%s([^"]*?)%s)"([^>]*)>/',
                          $tag, $type['attribute'],
                          preg_quote($GLOBALS['ABSOLUTE_URI_STUDIP'], '/'),
                          preg_quote($extension));

        preg_match_all($regexp, PageLayout::getHeadElements(), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $source = $GLOBALS['ABSOLUTE_PATH_STUDIP'] . $match[3] . $extension;

            $target = sprintf('/cache/%s-%s%s', md5($match[0]), basename($match[3]), $type['extension']);
            $file   = dirname(__FILE__) . $target;
            $url    = $this->getPluginURL() . $target;

        // These settings would compile into the source directory
        /*
            $target = $match[3] . $type['extension'];
            $file   = $GLOBALS['ABSOLUTE_PATH_STUDIP'] . $target;
            $url    = $GLOBALS['ABSOLUTE_URI_STUDIP'] . $target;
        */

            if (!file_exists($file) or Studip\ENV === 'development') {
                file_put_contents($file, $compiler($source));
            }

            $attributes = self::parseAttributes($match[1] . $match[4]);
            PageLayout::removeHeadElement($tag, $attributes + array($type['attribute'] => $match[2]));
            PageLayout::addHeadElement($tag, $attributes + array($type['attribute'] => $url), $type['parameter']);
        }
    }

    /**
     * Parses attributes from an html tag (for example: name="foo").
     *
     * @param  String $string Text to parse
     * @return Array Associative list of attributes, name as key, content as value
     */
    private static function parseAttributes($string)
    {
        $attributes = array();
        preg_replace_callback('/\b(\w+)="(.*?)"/', function ($match) use (&$attributes) {
            $attributes[$match[1]] = $match[2];
        }, $string);
        return $attributes;
    }
}
