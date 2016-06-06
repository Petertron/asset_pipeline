<?php

namespace asset_pipeline\ap;

use Symphony;
use General;

function getPreproIfExists($name)
{
    //$prepro = '\\'.ID . "\\prepro\\$name";
    $prepro = ID . "\\prepro\\$name";
    return class_exists($prepro) ? $prepro : null;
}

function replaceFileExtension($file, $new_ext)
{
    return substr($file, 0, strrpos($file, '.')) . '.' . $new_ext;
}

class AP
{
    static $compilation_list; // Value is set to false if there is no compilation list
    static $files_compiled;

    public static function init()
    {
        self::$compilation_list = getCompilationList();
    }

    public static function prepareAsset($file)
    {
        /*if (self::$files_compiled[$file]) {
            return OUTPUT_URL . '/' . self::$files_compiled[$file];
        }*/

        // No compiled file.
        /*if (self::$compilation_list === false) return null;
        if (!isset(self::$compilation_list)) {
            self::$compilation_list = getCompilationList();
        }*/
        //if (!isset($compilation_list[$file])) return null;

        //$source_path = self::$compilation_list[$file] . '/' . $file;
        $source_path = 'stylesheets/' . $file;
        //$source_path_abs = SOURCE_DIR . '/' . self::$compilation_list[$file] . '/' . $file;
        $ext = General::getExtension($source_path);
        $prepro = getPreproIfExists($ext);
        $output_type = $prepro ? $prepro::getOutputType() : $ext;
        $output_path = $prepro ? replaceFileExtension($file, $output_type) : $file;

        if (in_array($output_type, array('css', 'js'))) {
            ob_start();
            if ($output_type == 'css') {
                self::processCSS($source_path, $prepro);
            } elseif ($output_type == 'js') {
                self::processJS($source_path, $prepro);
            }
            file_put_contents(CACHE . '/' . $output_path, ob_get_contents());
            ob_end_clean();
        }
        return OUTPUT_URL . '/' . $output_path;
    }

    public static function processCSS($source_path, $prepro = null)
    {
        $source_path_abs = SOURCE_DIR . '/' . $source_path;
        $content = file_get_contents($source_path_abs);
        if ($prepro) {
            error_log("Boxant: $prepro");
            $result = $prepro::compile($content, dirname($source_path_abs));
            $content = $result['content'];
        }
        if (substr($content, 0, 2) == '/*') {
            $header_end = strpos($content, '*/');
            if ($header_end !== -1) {
                $header = substr($content, 0, $header_end + 2);
                $content = trim(trim(substr($content, $header_end + 2)), "\n");

                $requires = array();
                $line = strtok($header, "\r\n");
                while ($line) {
                    $line = trim(ltrim(trim($line), '*'));
                    if (substr($line, 0, 1) == '=') {
                        $line = preg_split('/\s+/', ltrim(substr($line, 2)));
                        if ($line[0] == 'require') {
                            $requires[] = $line[1];
                        }
                    }
                    $line = strtok("\r\n");
                }
                if(!empty($requires)) {
                    foreach ($requires as $file) {
                        $ext = General::getExtension($file);
                        if ($ext == 'css') {
                            self::processCSS($file);
                        } else {
                            $prepro = getPreproIfExists($ext);
                            if ($prepro && $prepro::getOutputType() == 'css') {
                                self::processCSS($file, $prepro);
                            }
                        }
                    }
                }
            }
        }
        echo $content;
    }

    static function processJS($source_path, $prepro = null)
    {
        return "// breep";
    }

}