<?php
/**
 * lexiconhelper class file for MyComponent extra
 *
 * Copyright 2012 by Bob Ray <http://bobsguides.com>
 * Created on 08-11-2012
 *
 * MyComponent is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * MyComponent is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * MyComponent; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package mycomponent
 */

/**
 * Description
 * -----------
 * methods used by lexiconhelper.php
 *
 * Variables
 * ---------
 * @var $modx modX
 * @var $scriptProperties array
 *
 * @package mycomponent
 **/

class LexiconHelper {
    /* @var $modx modX - MODX object */
    public $modx;
    /* @var $props array  - $scriptProperties array */
    public $props;
    /* @var $helpers Helpers  - class of helper functions */
    public $helpers;
    public $packageName;
    public $packageNameLower;
    public $source;
    public $targetBase;
    public $targetCore;
    public $targetAssets;
    public $tplPath; /* path to element Tpl files */
    public $categoryId;
    public $dirPermission;
    public $filePermission;
    public $classFiles;
    public $included;
    public $output;
    public $codeMatches;
    public $primaryLanguage;
    public $element; // current element being processed
    public $loadedLexiconFiles = array();
    public $lexiconCodeStrings = array();
    public $usedSomewhere = array();
    public $definedSomeWhere = array();
    public $lexiconFileStrings = array();
    public $missing = array();

    function  __construct(&$modx, &$props = array()) {
        $this->modx =& $modx;
        $this->props =& $props;
    }

    public function init($configPath) {
        clearstatcache(); /*  make sure is_dir() is current */
        $config = $configPath;
        if (file_exists($config)) {
            $configProps = @include $config;
        }
        else {
            die('Could not find main config file at ' . $config);
        }

        if (empty($configProps)) {
            /* @var $configFile string - defined in included build.config.php */
            die('Could not find project config file at ' . $configFile);
        }
        $this->props = array_merge($configProps, $this->props);
        unset($config, $configFile, $configProps);
        $this->output =  "\nProject: " . $this->props['packageName'];
        $this->source = $this->props['source'];
        /* add trailing slash if missing */
        if (substr($this->source, -1) != "/") {
            $this->source .= "/";
        }

        require_once $this->source . 'core/components/mycomponent/model/mycomponent/helpers.class.php';
        $this->helpers = new Helpers($this->modx, $this->props);
        $this->helpers->init();

        $this->packageNameLower = $this->props['packageNameLower'];
        $this->targetBase = MODX_BASE_PATH . 'assets/mycomponents/' . $this->packageNameLower . '/';
        $this->targetCore = $this->targetBase . 'core/components/' . $this->packageNameLower . '/';
        $this->primaryLanguage = $this->props['primaryLanguage'];
        clearstatcache(); /*  make sure is_dir() is current */




    }

    public function run() {
        $snippets = $this->props['elements']['modSnippet'];
        $elements = array();
        /* get all plugins and snippets from config file */
        foreach (explode(',', $snippets) as $snippet) {
            $elements[strtolower(trim($snippet))] = 'modSnippet';
        }
        $plugins = $this->props['elements']['modPlugin'];
        foreach (explode(',', $plugins) as $plugin) {
            $elements[strtolower(trim($plugin))] = 'modPlugin';
        }
        $this->classFiles = array();
        $dir = $this->targetCore . 'model';
        $this->helpers->resetFiles();
        $this->helpers->dirWalk($dir, 'php', true);
        $this->classFiles = $this->helpers->getFiles();
        if (!empty($this->classFiles)) {
            $this->output .= "\nFound these class files: " . implode(', ', array_keys($this->classFiles));

        }

        foreach ($elements as $element => $type) {
            $this->element = $element;
            $this->included = array();
            $this->loadedLexiconFiles = array();
            $this->lexiconCodeStrings = array();
            $this->codeMatches = array();
            $this->missing = array();
            $this->output .= "\n\n*********************************************";
            $this->output .= "\nProcessing Element: " . $element . " -- Type: " . $type;
            $this->getCode($element, $type);
            if (!empty($this->included)) {
                $this->output .= "\nCode File(s) analyzed: " . implode(', ', $this->included);
            }
            if (!empty($this->loadedLexiconFiles)) {
                $this->output .= "\nLexicon File(s) analyzed: " . implode(', ', array_keys($this->loadedLexiconFiles));
            } else {
                $this->output .= "\nNo Lexicon File(s) loaded";
            }
            $this->usedSomewhere = array_merge($this->usedSomewhere, $this->lexiconCodeStrings);

            $this->getLexiconFileStrings();
            $this->definedSomeWhere = array_merge($this->definedSomeWhere, $this->lexiconFileStrings);
            // $this->output .= ! empty($this->loadedLexiconFiles)? "\nLexicon files: " . implode(', ', $this->loadedLexiconFiles) : "\nNo lexicon files used";
            //$this->output .= ! empty($this->lexiconCodeStrings)? "\nLexicon strings: " . print_r($this->lexiconCodeStrings, true) : "\nNo Lexicon strings found";

            $this->output .= "\n" . count($this->lexiconCodeStrings) . ' lexicon strings in code file(s)';
            $this->output .= "\n" . count($this->lexiconFileStrings) . ' lexicon strings in lexicon file(s)';

            $missing = $this->findMissing();
            $this->output .= $this->reportMissing($missing);
        }
        $lexPropStrings = $this->getLexiconPropertyStrings();
        $this->checkPropertyDescriptions($lexPropStrings);

        $this->report();
    }

    public  function getLexiconFileStrings() {
        $files = $this->loadedLexiconFiles;
        $included = array();
        foreach ($files as $fqn => $path) {
            if (!is_string($fqn) || $fqn == '') continue;
            $fileName = $path;
            if (!in_array($fqn, $included)) {
                $included[] = $fqn;
                if  (file_exists($fileName)) {
                    $_lang = null;
                    include $fileName;
                    if (is_array($_lang)) {
                        $this->lexiconFileStrings = array_merge($this->lexiconFileStrings, $_lang);
                    } else {
                        $this->output .= "\nNo language strings in file: " . $fileName;
                    }
                } else {
                    $this->output .= "\nCan't find lexicon file: " . $fileName;
                }
            }
        }
    }

    /**
     * Returns a fully qualified lexicon spec (e.g. 'example:en:default.inc.php')
     * @param $lexFileSpec (partial or full lexicon spec (e.g., default, en:default)
     * @return string
     */
    public function getLexFqn ($lexFileSpec) {
        $nspos = strpos($lexFileSpec, ':');
        $languages = array_keys($this->props['languages']);
        $language = $languages[0];
        $namespace = $this->props['category'];
        if ($nspos === false) {
            $topic_parsed = $lexFileSpec;

        } else { /* if namespace, search specified lexicon */
            $params = explode(':', $lexFileSpec);
            if (count($params) <= 2) {
                $namespace = $params[0];
                $topic_parsed = $params[1];
            }
            else {
                $language = $params[0];
                $namespace = $params[1];
                $topic_parsed = $params[2];
            }
        }
        return $language . ':' . $namespace . ':'  . $topic_parsed;
    }
    public function getLexiconFilePath($fqn) {
        $val = explode(':', $fqn);
        return $this->targetCore . 'lexicon' . '/' . $val[0] . '/' . $val[2] . '.inc.php';
    }

    public function findMissing() {
        $missing = array();
        $inCode = $this->lexiconCodeStrings;
        $inLexicon = $this->lexiconFileStrings;

        foreach($inCode as $key => $value) {

            if (! array_key_exists($key, $inLexicon)) {
                if (!array_key_exists($key, $missing)) {
                    $missing[$key] = $value;
                }
            }
        }
        if (is_array($inCode) && !empty($inCode) && empty($missing)) {
            $this->output .= "\nNo missing strings in lexicon file!";
        }
        return $missing;

    }

    /**
     * Get appropriate quote character for a lexicon string
     * @param $value
     * @return string
     */
    public function getQc($value) {
        /* use double quote for strings containing a single quote */
        $qc = strchr($value, "'")
            ? '"'
            : "'";
        /* switch back if the single quote is escaped */
        $qc = strstr($value, "\'")
            ? "'"
            : $qc;
        return $qc;
    }

    public function reportMissing($missing) {
        $output = "";
        if (!empty($missing)) {
            //$this->output .= "\nStrings missing from Language file(s):";
            $code = '';
            foreach ($missing as $key => $value) {
                $qc = $this->getQc($value);
                $code .= "\n\$_lang['" . $key . "'] = {$qc}" . $value . "{$qc};";
            }
            $count = count($this->loadedLexiconFiles);
            if ($count == 1 && $this->props['rewriteLexiconFiles']) {
                /* append $output to lexicon file  */
                $comment = '/* used in ' . $this->element . ' or its included classes */';
                $fileName = reset($this->loadedLexiconFiles);


                if (file_exists($fileName)) {
                    $content = file_get_contents($fileName);
                    $success = false;
                    if (strstr($content, $comment)) {
                        $content = str_replace($comment, $comment , $code, $content);
                        $fp = fopen($fileName , 'w');
                        if ($fp) {
                            fwrite($fp, $content);
                            fclose($fp);
                            $success = true;
                        }
                    } else {
                        $fp = fopen($fileName, 'a');
                        if ($fp) {
                            fwrite($fp, "\n\n" . $comment . $code);
                            fclose($fp);
                            $success = true;
                        } else {
                            $output .= "\nCould not open lexicon file: " . $fileName;
                        }
                    }
                    if ($success) {
                    $output .= "\nUpdated Lexicon file -- " . key($this->loadedLexiconFiles) . " with these strings:\n" . $code;
                    }
                }


            } elseif ($this->props['rewriteLexiconFiles']) {
                $output .= "\n\nCan't update multiple Lexicon files;\npaste these strings in the appropriate file:\n" . $code;
            } else {
                $this->output .= "\nMissing Lexicon strings:" . $code;

            }

            // $this->output .= "\nloaded\n" .print_r($this->loadedLexiconFiles, true);
            // $this->output .= "\nincluded\n" . print_r($this->included, true);

            // $this->output .= "\nCount: " . count($this->loadedLexiconFiles);
        }

        return $output;

    }

    public function findUnused() {
        $unused = array();
        if (!empty($this->usedSomewhere) && !empty($this->definedSomeWhere)) {
            foreach ($this->definedSomeWhere as $key => $value) {
                if( !array_key_exists($key, $this->usedSomewhere))
                    $unused[$key] = $value;
            }

        }
        return $unused;
    }
    public function reportUnused($unused) {
        $output = '';
        if (!empty($unused)) {
            $code = '';
            foreach($unused as $key => $value) {
                /* skip System Setting strings */
                if (! strstr($key, 'setting_')) {
                    $code .= "\n    \$_lang['" . $key . "'] = '" . $value . "';";
                }
            }
            if (!empty($code)) {
                $output .= "\nThe following lexicon strings never used in code:\n" . $code;
            } else {
                $output .= "\nNo unused strings in lexicon files!";
            }
        } else {
            $output .= "\nNo unused strings in lexicon files!";
        }
        return $output;

    }
    public function findUndefined() {
        $undefined = array();
        foreach ($this->usedSomewhere as $key => $value) {
            if (!array_key_exists($key, $this->definedSomeWhere)) {
                $undefined[$key] = $value;
            }
        }
        return $undefined;

    }

    public function reportUndefined($undefined) {
        if (!empty($undefined)) {

            $output = "\n" . count($undefined) . ' lexicon strings in code are not defined in a language file (see above)';
        } else {
            $output = "\nAll lexicon strings are defined in lexicon files!";
        }
        return $output;
    }

    public function findEmpty() {
        $empty = array();
        foreach ($this->definedSomeWhere as $key => $value) {
            if (empty($value)) {
                $empty[] = $key;
            }
        }
        return $empty;
    }

    public function reportEmpty($empty) {
        if (empty($empty)) {
            $output = "\nNo Empty Lexicon strings in lexicon files!";
        }
        else {
            $output = "\nThe following lexicon strings are in a lexicon file, but have no value:";
            foreach ($empty as $string) {
                $output .= "\n    \$_lang['" . $string . "'] = '';";
            }
        }
        return $output;
    }

    public function report() {
        $this->output .= $this->checkSystemSettingDescriptions();
        $this->output .= "\n\n********  Final Audit  ********";
        $undefined = $this->findUndefined();
        $this->output .= $this->reportUndefined($undefined);

        $unused = $this->findUnused();
        $this->output .= $this->reportUnused($unused);
        $empty = $this->findEmpty();
        $this->output .= $this->reportEmpty($empty);


        echo $this->output;
        // echo "\n\nUsed Somewhere: " . print_r($this->usedSomewhere, true);
        // echo "\n\nDefined Somewhere: " . print_r($this->definedSomeWhere, true);
    }

    public function getLexiconPropertyStrings() {
        $_lang = array();
        $lexiconFilePath = $this->targetCore . 'lexicon/' . $this->primaryLanguage . '/' . 'properties.inc.php';
        if (file_exists($lexiconFilePath)) {
            require $lexiconFilePath;
        } else {
           // $this->output .= "\nNo properties.inc.php lexicon file";
        }
        return $_lang;
    }

    /**
     * Check  lexicon properties.inc.php for property descriptions,
     * output strings.
     */
    public function checkPropertyDescriptions($lexStrings) {

        $this->output .= "\n\n********  Checking for property description lexicon strings ********";
        foreach($this->props['elements'] as $type => $elementList) {

            $elements = empty($elementList)? array() : explode(',', $elementList);
            foreach ($elements as $element ) {
                $propsFileName = $this->helpers->getFileName($element, $type, 'properties');
                $propsFilePath = $this->targetBase . '_build/data/properties/' . $propsFileName;
                /* process one properties file */
                $missing = array();
                $empty = array();
                if (file_exists($propsFilePath)) {
                    $props = include $propsFilePath;
                    $this->output .= "\n\n********\nChecking Properties for " . $element . ' -- Type: ' . $type;
                    if (!is_array($props)) {
                        $this->output .= "\nNo properties in " . $propsFileName;
                    } else {
                        foreach($props as $prop) {
                            $description = $prop['desc'];

                            if (strstr($description, '~~')) {
                                $s = explode('~~', $description);
                                $lexKey = $s[0];
                            } else {
                                $lexKey = $description;
                            }
                            if ( ! array_key_exists($lexKey, $lexStrings)) {
                                $missing[] = $description;
                            } else {
                                if (isset($s[1])) {
                                    if ($lexStrings[$lexKey] != $s[1] ) {
                                        $empty[$lexKey] = $s[1];
                                    }
                                }

                            }
                        }
                        $comment = "/* Used in " . $propsFileName . " */";
                        $this->updateLexiconPropertiesFile($missing, $empty, $comment);
                    }

                } else {
                    // $this->output .= "\n\nNo Properties file for " . $element . '  -- at ' . $propsFilePath;
                }
            }
        }
    }

    public function updateLexiconPropertiesFile($missing, $empty, $comment) {
        $emptyFixed = 0;
        $code = '';
        if (empty($missing) && empty($empty) ) {
            $this->output .= "\nNo missing property descriptions in lexicon file!";
            $this->output .= "\nNo empty property descriptions in lexicon file!";
            return;
        } else {
            $lexFile = $this->targetCore . '/lexicon/' . $this->primaryLanguage . '/properties.inc.php';
            $lexFileContent = file_get_contents($lexFile);
            $original = $lexFileContent;
        }

        if (empty($missing)) {
            $this->output .= "\nNo missing property description lexicon strings!";
        } else {
            foreach ($missing as $string) {
                $val = strstr($string, '~~') ? explode('~~', $string) : array($string,'');
                $qc = $this->getQc($val[1]);
                $code  .= "\n\$_lang['" . $val[0] . "'] = {$qc}" . $val[1] . "{$qc};";
            }
            if (strstr($lexFileContent, $comment)) {
                $lexFileContent = str_replace($comment, $comment . $code,$lexFileContent);
            } else {
                $lexFileContent .= "\n\n" . $comment . $code . "\n";
            }
        }
        if (!empty ($empty)) {
            foreach ($empty as $key => $value) {
                $pattern = "/(_lang\[')" . $key . "(']\s*=\s* )'.*'/";
                $qc = $this->getQc($value);
                $replace = "$1$key$2{$qc}" . $value . "{$qc}";
                preg_match($pattern, $lexFileContent, $matches);
                $count = 0;
                $lexFileContent = preg_replace($pattern, $replace, $lexFileContent,  1, $count);
                $emptyFixed += $count;
            }
            $this->output .= "\nUpdated lexicon string(s) with these key(s)";
                foreach($empty as $key => $value) {
                    $this->output .= "\n    " . $key;
                }
        } else {
            $this->output .= "\nNo empty property descriptions in lexicon file!";
        }
        if ($this->props['rewriteLexiconFiles'] && (!empty($missing) || $emptyFixed)) {
            $fp = fopen($lexFile, 'w');
            if ($fp) {
                fwrite($fp, $lexFileContent);
                fclose($fp);
                if (!empty($missing)) {
                    $this->output .= "\nUpdated properties.inc.php entries with these keys:";
                    foreach($missing as $key => $value) {
                       $this->output .= "\n    " . $value;
                    }
                    if ($emptyFixed) {
                    $this->output .= "\nFixed " . $emptyFixed . ' empty lexicon string(s)';
                    }
                }
            } else {
                $this->output .= "\nCould not open lexicon properties file for writing: " . $lexFile;
            }
        } else {
            $this->output .= "\nCode to add to lexicon properties file:";
            $this->output .= "\n" . $comment . "\n" . $code . "\n\n";
        }


        echo print_r($empty, true);
    }

    /* ToDo: checkSystemEventDescriptions() ?? */

    public function checkSystemEventDescriptions(){
        /* don't know where the hell these are (if anywhere)
           There's no hover help for them in the Manager */
    }


    public function checkSystemSettingDescriptions() {
        /*
        * These should be in the default topic  (checked).
        * Check for both name and description  lex strings (not key):
        * setting_mySetting   Name of mySetting
        * setting_mySetting_desc   Description of mySetting
         *
        * Note: In the Manager, just update the system setting
        * and add the name and description (don't use keys or underscores)
        */
        /* ToDo: Update lexicon file */

        $comment = "/* System Setting Names and Descriptions */";
        $settings = $this->props['newSystemSettings'];
        $output = '';
        if (!empty($settings)) {
            $_lang = array();
            $missing = array();
            $output .= "\n\n********  Checking for System Setting lexicon strings ********";
            $output .= "\nChecking System Setting names and descriptions";
            $fqn = $this->getLexFqn('default');
            $fileName = $this->getLexiconFilePath($fqn);
            include $fileName;

            if (empty($_lang)) {
                $output .= "\nNo lexicon strings in default.inc.php";
                return $output;
            }
            foreach($settings as $key => $value ) {
                $key = strtoLower($key);
                $lexNameKey = 'setting_' . $key;
                $lexDescKey = 'setting_' . $key . '_desc';
                if ( !in_array($lexNameKey, array_keys($_lang))) {
                 $missing[$lexNameKey] = '';
                }
                if (!in_array($lexDescKey, array_keys($_lang))) {
                 $missing[$lexDescKey] = '';
                }
            }
        if (!empty($missing)) {

            $output .= "\nMissing from default.inc.php file (Setting Name/Setting Description):\n";
            $this->modx->lexicon->load($this->primaryLanguage . ':' . $this->packageNameLower . ':default');
            $code = '';
            foreach ($missing as $key => $value) {
             /* use values from MODX Lexicon Management, if set */
                $dbValue = $this->modx->lexicon($key);
                $value = $dbValue != $key? $dbValue : $value;
                $qc = strstr($value, "'") ? '"' : "'";
                $code .= "\n\$_lang['" . $key . "'] = {$qc}" . $value . "{$qc};";
            }
            if ($this->props['rewriteLexiconFiles']) {
                $content = file_get_contents($fileName);
                $success = false;
                if (! strstr($content, $comment)) {
                    $fp = fopen($fileName, 'a');
                    if ($fp) {
                        fwrite ($fp, "\n\n" . $comment . $code);
                        fclose($fp);
                        $success = true;
                    } else {
                        $output .= "\nCould not open default.inc.php for appending";
                    }
                } else {
                    $content = str_replace($comment, $comment . $code, $content);
                    $fp = fopen($fileName, 'w');
                    if ($fp) {
                        fwrite($fp, $content);
                        fclose($fp);
                        $success = true;
                    }
                    else {
                        $output .= "\nCould not open default.inc.php for writing";
                    }
                }
                if ($success) {
                    $output .= "\nUpdated these strings in default.inc.php:\n" . $code;
                }

            } else {
                $output .= "\nThese strings are missing from default.inc.php:" . $code;
            }
        }
        } else {
            $output = "\nNo System Setting names to check";
        }
    return $output;
    }


    public function addClassFiles($dir, $file) {
        //$this->output .= "\nIn addClassFiles";
        $this->classFiles[$file] = $dir;
    }

    /**
     * returns raw code from an element file and all
     * the class files it includes
     *
     * @param $element array member
     * @param $type string - 'modSnippet or modChunk
     */
    public function getCode($element, $type) {
        if (empty($element)) {
            $this->output .= 'Error: Element is empty';
            return;
        }
        $typeName = strtolower(substr($type, 3));
        $file = $this->targetCore . 'elements/' . $typeName . 's/' . $element . '.' . $typeName . '.php';


        $this->included[] = $element;
        $this->getIncludes($file);

    }

    /**
     * Searches for included .php files in code
     * and appends their content to $code reference var
     *
     * Also populates $this->lexiconCodeStrings and $this->loadedLexiconFiles
     *
     * @param $file - path to code file(s)
     */
    public function getIncludes($file) {
        $matches = array();
        $lines = array();
        // $fp = fopen($file, "r");
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (!empty($content)) {
                $content = $this->helpers->strip_comments($content);
                $lines = explode ("\n", $content);
                unset($content);
            } else {
                $this->output .= "\nFile is empty:  " . $file;
            }
        }
        /*if ($fp) {
            while (!feof($fp)) {
                $lines[] = fgets($fp, 4096);
            }
            fclose($fp);
        }*/
        else {
            $this->output .= "\nCould not find file: " . $file;
            return;
        }

        $fileName = 'x';

        foreach ($lines as $line) {
            /* process lexicon->load() lines */
            if (strstr($line,'lexicon->load')) {
                preg_match('#lexicon->load\s*\s*\(\s*\'(.*)\'#', $line, $matches);
                if (isset($matches[1]) && !empty($matches[1])) {
                    $fqn = $this->getLexFqn($matches[1]);
                    if (! in_array($fqn, array_keys($this->loadedLexiconFiles ))) {
                        $this->loadedLexiconFiles[$fqn] = $this->getLexiconFilePath($fqn);
                    }
                }

            /* process lexicon entries */
            } elseif (strstr($line, 'modx->lexicon')) {
                preg_match('#modx->lexicon\s*\s*\(\s*[\'\"](.*)[\'\"]#', $line, $matches);
                if (isset($matches[1]) && !empty($matches[1])) {
                    if (strstr($matches[1], '~~' )) {
                        $s = explode('~~', $matches[1]);
                        $lexString = $s[0];
                        $value = $s[1];
                    } else {
                        $lexString = $matches[1];
                        $value = '';
                    }
                    if (!in_array($lexString, array_keys($this->lexiconCodeStrings))) {
                        $this->lexiconCodeStrings[$lexString] = $value;
                        // $this->lexiconCodeStrings[] = $matches[1];
                    } elseif (empty($this->lexiconCodeStrings[$lexString]) && !empty($value)) {
                        $this->lexiconCodeStrings[$lexString] = $value;
                    }
                }
            }
            /* recursively process includes files */
            if (strstr($line, 'include') || strstr($line, 'include_once') || strstr($line, 'require') || strstr($line, 'require_once')) {

                preg_match('#[0-9a-zA-Z_\-\s]*\.class\.php#', $line, $matches);
                $fileName = isset($matches[0]) && !empty($matches[0]) ? $matches[0] : 'x';

            }

            /* check files included with getService() and loadClass() */
            if (strstr($line, 'modx->getService')) {
                $matches = array();
                $pattern = "/modx\s*->\s*getService\s*\(\s*\'[^,]*,\s*'([^']*)/";
                preg_match($pattern, $line, $matches);
                if (!isset($matches[1])) continue;
                $s = strtoLower($matches[1]);
                if (strstr($s, '.')) {
                    $r = strrev($s);
                    $fileName = strrev(substr($r, 0, strpos($r, '.')));
                }
                else {
                    $fileName = $s;
                }
            }
            if (strstr($line, 'modx->loadClass')) {
                $pattern = "/modx\s*->\s*loadClass\s*\(\s*\'([^']*)/";
                preg_match($pattern, $line, $matches);
                if (!isset($matches[1])) continue;

                $s = strtoLower($matches[1]);
                if (strstr($s, '.')) {
                    $r = strrev($s);
                    $fileName = strrev(substr($r, 0, strpos($r, '.')));
                }
                else {
                    $fileName = $s;
                }
            }


            $fileName = strstr($fileName, 'class.php')
                ? $fileName
                : $fileName . '.class.php';
            if (isset($this->classFiles[$fileName])) {

                // skip files we've already included
                if (!in_array($fileName, $this->included)) {
                    //$this->output .= "\n\nRecursing";
                    $this->included[] = $fileName;
                    $this->getIncludes($this->classFiles[$fileName] . '/' . $fileName);
                }
            }
        }
    }


/*    public function dir_walk($callback, $dir, $types = null, $recursive = false, $baseDir = '') {

        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (is_file($dir . '/' . $file)) {
                    if (is_array($types)) {
                        if (!in_array(strtolower(pathinfo($dir . $file, PATHINFO_EXTENSION)), $types, true)) {
                            continue;
                        }
                    }
                    $this->{$callback}($dir, $file);
                }
                elseif ($recursive && is_dir($dir . '/' . $file)) {
                    $this->dir_walk($callback, $dir . '/' . $file, $types, $recursive, $baseDir . '/' . $file);
                }
            }
            closedir($dh);
        }
    }*/


}
