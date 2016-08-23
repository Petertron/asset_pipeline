<?php

require_once EXTENSIONS . '/asset_pipeline/lib/defines.php';
require_once EXTENSIONS . '/asset_pipeline/lib/pipeline.php';

use asset_pipeline\AP;
use asset_pipeline\Pipeline;

class contentExtensionAsset_pipelinePrecompile_assets_ajax
{
    public function __construct()
    {
        $this->_output = array();
        $this->error_occurred = false;
    }

    public function build(array $context = array())
    {
        Pipeline::initialise();

        if (is_array($_POST['items'])) {
            $items = $_POST['items'];

            $source_directories = include AP\SOURCE_DIRECTORIES;

            $this->_output['html'] = "Files compiled:<br><br>";

            // Compile non-code files

            foreach ($items as $dir_path) {
                $directory = $source_directories[$dir_path];
                if (in_array($directory['type'], array('css', 'js'))) continue;
                $source_dir_abs = WORKSPACE . '/' . $dir_path;// . '/';
                $listing = Pipeline::getRecursiveFileList($source_dir_abs);
                foreach ($listing as $file) {
                    $source_file_abs = $source_dir_abs . '/' . $file;
                    if (!file_exists($source_file_abs)) continue;
                    $md5 = md5_file($source_file_abs);
                    $output_file = Pipeline::filenameInsertMD5($file, $md5);
                    $output_file_abs = AP\OUTPUT_DIR . '/' . $output_file;
                    General::realiseDirectory(basename($output_file_abs));
                    copy ($source_file_abs, $output_file_abs);
                    Pipeline::registerCompiledFile($file, $output_file);
                    $this->_output['html'] .= "$output_file<br>";
                }
            }

            // Compile code files

            $processors = array('css' => 'processCSS', 'js' => 'processJS');
            foreach ($items as $dir_path) {
                $directory = $source_directories[$dir_path];
                $type = $directory['type'];
                if (!Pipeline::isCodeType($type)) continue;
                //if (!array_key_exists($type, $processors)) continue;
                $source_dir_abs = WORKSPACE . '/' . $dir_path;
                $to_compile = $directory['precompile_files'];
                $processCode = $processors[$type];
                if (is_array($to_compile) && !empty($to_compile)) {
                    foreach ($to_compile as $file) {
                        $input_type = General::getExtension($file);
                        if (Pipeline::getOutputType($input_type) != $type) continue;
                        $source_file_abs = $source_dir_abs . '/' . $file;
                        if (!file_exists($source_file_abs)) continue;
                        $output = Pipeline::$processCode($source_file_abs);
                        Pipeline::deleteCompiledFile($file); // Delete previous compilation, if any
                        $md5 = md5($output);
                        $output_file = Pipeline::filenameInsertMD5(
                            Pipeline::replaceExtension($file, $type), $md5
                        );
                        $output_file_abs = AP\OUTPUT_DIR . '/' . $output_file;
                        $output = self::minify($output, $type);
                        General::realiseDirectory(dirname($output_file_abs));
                        General::writeFile($output_file_abs, $output);
                        //file_put_contents($output_file_abs, $output);
                        Pipeline::registerCompiledFile($file, $output_file);
                        $this->_output['html'] .= "$output_file<br>";
                    }
                }
            }

            Pipeline::saveCompilationInfo();
        }
    }

    static function minify($buffer, $type)
    {
        switch ($type) {
            case 'css':
                $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
                // Remove space after colons
                $buffer = str_replace(': ', ':', $buffer);
                // Remove line breaks & tabs
                $buffer = str_replace(array("\r\n", "\r", "\n", "\t"), '', $buffer);
                // Collapse adjacent spaces into a single space
                $buffer = preg_replace('/\s{2,}/', ' ', $buffer);
                // Remove spaces that might still be left where we know they aren't needed
                $buffer = str_replace(array('} '), '}', $buffer);
                $buffer = str_replace(array('{ '), '{', $buffer);
                $buffer = str_replace(array('; '), ';', $buffer);
                $buffer = str_replace(array(', '), ',', $buffer);
                $buffer = str_replace(array(' }'), '}', $buffer);
                $buffer = str_replace(array(' {'), '{', $buffer);
                $buffer = str_replace(array(' ;'), ';', $buffer);
                $buffer = str_replace(array(' ,'), ',', $buffer);
                break;
            case 'js':
                $buffer = preg_replace("/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/", "", $buffer);
                $buffer = str_replace(["\r\n","\r","\t","\n",'  ','    ','     '], '', $buffer);
                $buffer = preg_replace(['(( )+\))','(\)( )+)'], ')', $buffer);
                break;
        }

        return $buffer;
    }

    public function generate($page = NULL)
    {
        header('Content-Type: text/javascript');
        echo json_encode($this->_output);
        exit();
    }

}
