<?php
// Include the Base Class (only once)
require_once('objectadapter.class.php');

class ResourceAdapter extends ObjectAdapter
{//These will never change.

    protected $dbClass = 'modResource';
    protected $dbClassIDKey = 'id';
    protected $dbClassNameKey = 'pagetitle';
    protected $dbClassParentKey = 'parent';
    protected $createProcessor = 'resource/create';
    protected $updateProcessor = 'resource/update';
    protected $defaults = array();
    protected $name;
    /* @var $helpers Helpers */
    public $helpers;
    /* @var $modx modX */
    public $modx;
// Database Columns for the XPDO Object
    protected $myFields;


    function __construct(&$modx, &$helpers, $fields, $mode = MODE_BOOTSTRAP) {
        /* @var $modx modX */
        /* @var $object modResource */
        parent::__construct($modx, $helpers);
        $this->name = $fields['pagetitle'];
        if (! isset($fields['id'])) {
            $fields['id'] = '';
        }
        if ($mode == MODE_BOOTSTRAP) {
    // Set defaults if they are not already set
            $this->defaults['published'] = $modx->getOption('publish_default', null);
            $this->defaults['richtext'] = $modx->getOption('richtext_default',null);
            $this->defaults['hidemenu'] = $modx->getOption('hidemenu_default', null);
            $this->defaults['cacheable'] = $modx->getOption('cache_default', null);
            $this->defaults['searchable'] = $modx->getOption('search_default', null);
            $this->defaults['context_key'] = $modx->getOption('default_context', null);

            if (!isset($fields['class_key'])) {
                $fields['class_key'] = 'modDocument';
            }
            foreach ($this->defaults as $field => $value) {
                $fields[$field] = isset($fields[$field])
                    ? $fields[$field]
                    : $value;
            }

        } elseif ($mode == MODE_EXPORT) {
                $this->fieldsToNames($fields);
                $this->myFields = $fields;
        }
        $this->setResourceResolver($fields, $mode);
        $this->myFields = $fields;
        ObjectAdapter::$myObjects['resources'][] = $fields;
    }

    public function setResourceResolver($fields, $mode) {
        $resolverFields = array();
        $resolverFields['pagetitle'] = $fields['pagetitle'];
        $resolverFields['parent'] = isset($fields['parent'])
            ? $fields['parent']
            : 'default';
        $resolverFields['template'] = isset($fields['template'])
            ? $fields['template']
            : 'default';
        if ($mode == MODE_BOOTSTRAP && isset($fields['tvValues'])) {
            $resolverFields['tvValues'] = $fields['tvValues'];
        } elseif ($mode == MODE_EXPORT) {
            $me = $this->modx->getObject('modResource', array('pagetitle' => $fields['pagetitle']));
            if (!$me) {
                $this->helpers->sendLog(MODX::LOG_LEVEL_ERROR, '[ResourceAdapter] ' .
                    $this->modx->lexicon('mc_self_nf'));
            } else {
                /* Check for TVs (this is ugly, but we only want OUR TVs) */
                $myId = $me->get('id');
                /* get our categories */
                $categories = $this->modx->getOption('categories',
                    ObjectAdapter::$myObjects, array(0));
                /* get Tvs in all our categories */
                $tvObjects = array();
                foreach($categories as $categoryName => $fields ) {
                    $categoryObj = $this->modx->getObject('modCategory',
                        array('category' => $categoryName));
                    if ($categoryObj) {
                        $categoryId = $categoryObj->get('id');
                        $tvObjects = array_merge($tvObjects, $this->modx->getCollection('modTemplateVar', array('category' => $categoryId)));
                    }
                }
                /* get the TvValues */
                /* @var $tvObj modTemplateVar */
                if (!empty($tvObjects)) {
                    foreach ($tvObjects as $tvObj) {
                        $val = $tvObj->getValue($myId);
                        if (!empty($val) && $val != $tvObj->get('default_text')) {
                            $resolverFields['tvValues'][$tvObj->get('name')] = $val;
                        }
                    }
                }
            }
        }
        ObjectAdapter::$myObjects['resourceResolver'][] = $resolverFields;

    }
    /**
     * Converts object fields containing IDs to the names of the objects
     * represented by the IDs -- only executes on export.
     * @param $fields array
     */
    public function fieldsToNames(&$fields, $mode = MODE_BOOTSTRAP) {
        if (!empty($fields['parent'])) {
            $parentObj = $this->modx->getObject('modResource', $fields['parent']);
            if ($parentObj) {
                $fields['parent'] =  $parentObj->get('pagetitle');
            } else {
                if ($mode != MODE_REMOVE) {
                    $this->helpers->sendLog(MODX::LOG_LEVEL_ERROR,
                        '[ResourceAdapter] ' .
                            $this->modx->lexicon('mc_parent_nf')
                                . ': ' .  $fields['parent']);
                }
            }
        }
        if (!empty($fields['template'])) {
            if ($fields['template'] == $this->modx->getOption('default_template')) {
                $fields['template'] = 'default';
            } else {
                $templateObj = $this->modx->getObject('modTemplate', $fields['template']);
                if ($templateObj) {
                    $fields['template'] = $templateObj->get('templatename');
                }
            }
        }
    }

    /**
     * Converts object fields containing names to the IDs of the objects
     * represented by the names.
     * @param $fields array
     */

    public function fieldsToIds(&$fields) {
        if (!isset($fields['parent']) || $fields['parent'] == 'default') {
            $fields['parent'] = '0';
        } else {
            $parentObj = $this->modx->getObject('modResource', array('pagetitle' => $fields['parent']));
            if ($parentObj) {
                $fields['parent'] = $parentObj->get('id');
            } else {
                $this->helpers->sendLog(MODX::LOG_LEVEL_ERROR, '[ResourceAdapter] ' .
                    $this->modx->lexicon('mc_parent_nf')
                        . ': ' . $fields['pagetitle']);
            }
        }
        if (!isset($fields['template']) || empty($fields['template']) || $fields['template'] == 'default') {
            $fields['template'] = $this->modx->getOption('default_template');
        } else {
            $templateObj = $this->modx->getObject('modTemplate', array('templatename' => $fields['template']));
            if ($templateObj) {
                $fields['template'] = $templateObj->get('id');
            }
        }
    }


/* *****************************************************************************
   Import Objects and Support Functions
***************************************************************************** */

    final public function addToMODx($overwrite = false)
    {//Perform default export implementation
        /* @var $modx modX */
        $fields =& $this->myFields;

        $this->fieldsToIds($fields);

        /*if (isset($fields['tvValues'])) {
            $tvValues = $fields['tvValues'];
            unset($fields['tvValues']);
        }*/
        if (!isset($fields['alias']) || empty($fields['alias'])) {
            $fields['alias'] = str_replace(' ', '-', strtolower($fields['pagetitle']));
        }
        $this->myFields = &$fields;
        parent::addToMODx($overwrite);



    }

    public static function createResolver($dir, $intersects, $helpers, $mode = MODE_BOOTSTRAP) {

        /* Create resource.resolver.php resolver */
        /* @var $helpers Helpers */
        if (!empty($dir) && !empty($intersects)) {
            $helpers->sendLog(MODX::LOG_LEVEL_INFO, "\n" .
                $helpers->modx->lexicon('mc_creating_resource_resolver'));
            $tpl = $helpers->getTpl('resourceresolver.php');
            $tpl = $helpers->replaceTags($tpl);
            if (empty($tpl)) {
                $helpers->sendLog(MODX::LOG_LEVEL_ERROR, '[Resource Adapter] ' .
                    $helpers->modx->lexicon('mc_resourceresolvertpl_empty'));
                return false;
            }

            $fileName = 'resource.resolver.php';

            if (!file_exists($dir . '/' . $fileName) || $mode == MODE_EXPORT) {
                $intersectArray = $helpers->beautify($intersects);
                $tpl = str_replace("'[[+intersects]]'", $intersectArray, $tpl);

                $helpers->writeFile($dir, $fileName, $tpl);
            } else {
                $helpers->sendLog(MODX::LOG_LEVEL_INFO, '    ' . $fileName . ' ' .
                    $helpers->modx->lexicon('mc_already_exists'));
            }
        }
        return true;
    }

    public static function createTransportFiles(&$helpers, $mode = MODE_BOOTSTRAP) {
        /* @var $helpers Helpers */
        $helpers->sendLog(MODX::LOG_LEVEL_INFO, "\n" . '    ' .
            $helpers->modx->lexicon('mc_processing_resources'));
        $resources = $helpers->modx->getOption('resources', ObjectAdapter::$myObjects, array());
        parent::createTransportFile($helpers, $resources, '', 'modResource', $mode);
    }


    static function exportResources(&$modx, &$helpers, $props, $mode = MODE_EXPORT) {
        /* @var $modx modX */
        /* @var $helpers Helpers */
        $objects = array();

        /* Add resources from exportResources array in the project config file
          to $this->myObjects array */
        $helpers->sendLog(MODX::LOG_LEVEL_INFO, "\n" .
            $helpers->modx->lexicon('mc_processing_resources'));
        $byId = $modx->getOption('getResourcesById', $props, false);
        $method = $byId? 'ID' : 'pagetitle';
        $resources = $modx->getOption('exportResources', $props, array());
        if (!empty($resources)) {
            foreach ($resources as $resource) {
                if ($byId) {
                    $resObject = $modx->getObject('modResource', $resource);
                } else {
                    $resObject = $modx->getObject('modResource', array('pagetitle' => trim($resource)));
                }
                if ($resObject) {
                    $objects[] = $resObject;
                } else {
                    $helpers->sendLog(modX::LOG_LEVEL_ERROR,
                        '[Resource Adapter] ' .
                        $helpers->modx->lexicon('mc_could_not_get_resource_with_method')
                        . ' ' . $method . ': ' . $resource);
                }
            }
        }
        /* if $parents is set in project config, add children (and optionally parent
           to  $resources array */
        $parents = $modx->getOption('parents', $props, array() );
        $includeParents = $modx->getOption('includeParents', $props, false);
        if (!empty($parents)) {
            foreach ($parents as $parentResource) {
                if ($byId) {
                    $parentObj = $modx->getObject('modResource', $parentResource);
                } else {
                    $parentObj = $modx->getObject('modResource', array('pagetitle' => $parentResource));
                }
                if ($parentObj) {
                    if ($includeParents) {
                        $objects[] = $parentObj;
                    }
                    $children = $parentObj->getMany('Children');
                    if (!empty ($children)) {
                        $objects = array_merge($objects, $children);
                    }
                }
            }

        }
        if (!empty($objects)) {
            /* @var $object modResource */
            $dryRun = $props['dryRun'];
            foreach($objects as $object) {
                $fields = $object->toArray();
                $a = new ResourceAdapter($modx, $helpers, $fields, $mode);
                if ($mode == MODE_REMOVE) {
                    $a->remove();
                } elseif ($mode == MODE_EXPORT) {
                    $content = $object->getContent();
                    $a->createCodeFile(true, $content,  $mode, $dryRun );
                }
            }
        } else {
            $helpers->sendLog(MODX::LOG_LEVEL_ERROR, '[ResourceAdapter] ' .
                $helpers->modx->lexicon('mc_no_resources_found'));
        }
    }

    /**
     * Writes the properties file for objects with properties
     *
     * @param $properties array - object properties as PHP array
     * @param $fileName - Name of properties file
     * @param $objectName - Name of MODX object
     */
    /*public function exportProperties($overwrite = false)
    {//For Quick Access
        $mc = $this->myComponent;
        $dir = $mc->getPath('properties');
        $name = $this->getName();
        $class = $this->getSafeClass();
        $properties = $this->myFields['properties'];
        $fileName = $this->getFileName('properties');
        
        $tpl = $this->getTpl('propertiesfile.tpl');
        $tpl = str_replace('[[+element]]',$name,$tpl);
        $tpl = str_replace('[[+elementType]]', $class, $tpl);
        $tpl = $mc->replaceTags($tpl);

        $hastags = strpos($tpl, '<'.'?'.'php');
        if ($hastags === false)
            $tpl = '<'.'?'.'php'.$tpl;
        $tpl .=  "\n\n" . $this->render_properties($properties) . "\n\n";

        $mc->writeFile($dir, $fileName, $tpl, $this->dryRun);
        
        unset($tpl);
    }*/

    /**
     * Recursive function to write the code for the build properties file. This
     * function has changed from its original, as it checks the passed value. If
     * it is a string, there is no recursion. If not, there is.
     *
     * @param $arr - array of properties
     * @param $depth int - controls recursion
     *
     * @return string - code for the elements properties
     */
    /*public function render_properties($arr, $depth = -1)
    {
    // For Indents
        $tabWidth = 4;
    
        if ($depth == -1) {
            // this will only happen once
            $output = "\$properties = array( \n";
            $depth++;
        } else {
            $output = "array( \n";
        }
        $indent = str_repeat(" ", $depth + $tabWidth );

        foreach( $arr as $key => $val ) 
        {//Ignore List...
            if ($key == 'desc_trans' 
            ||  $key == 'area_trans') 
                continue;
            
            // No key for each property array
            $output .= $depth == 0
                ? $indent 
                : $indent . "'$key' => ";

            if( is_array( $val ) && !empty($val) ) {
                $output .= $this->render_properties( $val, $depth + $tabWidth );
            } else {
                $val = empty($val)? '': $val;
                // see if there are any single quotes
                $qc = "'";
                if (strpos($val,$qc) !== false) {
                    // yes - change outer quote char to "
                    //   and escape all " chars in string
                    $qc = '"';
                    $val = str_replace($qc,'\"',$val);
                }

                $output .= $qc . $val . $qc . ",\n";
            }
        }
        $output .= $depth
            ? $indent . "),\n"
            : "\n);\n\nreturn \$properties;";
        return $output;
    }*/

}