<?php

class PropertySetAdapter extends ElementAdapter { //This will never change.
    protected $dbClass = 'modPropertySet';
    protected $dbClassIDKey = 'id';
    protected $dbClassNameKey = 'name';
    protected $dbClassParentKey = 'category';
    protected $createProcessor = 'element/propertyset/create';
    protected $updateProcessor = 'element/propertyset/update';

// Database fields for the XPDO Object
    protected $myFields;


    final function __construct(&$modx, &$helpers, $fields, $mode = MODE_BOOTSTRAP) {
        /* @var $object modPropertySet */
        $this->name = $fields['name'];
        parent::__construct($modx, $helpers, $fields, $mode);
    }


    public static function createResolver($dir, $intersects, $helpers, $mode = MODE_BOOTSTRAP) {

        /* Create tv.resolver.php resolver */
        /* @var $helpers Helpers */
        if (!empty($dir) && !empty($intersects)) {
            $helpers->sendLog(MODX::LOG_LEVEL_INFO, "\n" . 'Creating elementPropertySet resolver');
            $tpl = $helpers->getTpl('propertysetresolver.php');
            $tpl = $helpers->replaceTags($tpl);
            if (empty($tpl)) {
                $helpers->sendLog(MODX::LOG_LEVEL_ERROR,
                    '[PropertySet Adapter] propertysetresolver tpl is empty');
                return false;
            }

            $fileName = 'propertyset.resolver.php';

            if (!file_exists($dir . '/' . $fileName) || $mode == MODE_EXPORT) {
                $intersectArray = $helpers->beautify($intersects);
                $tpl = str_replace("'[[+intersects]]'", $intersectArray, $tpl);

                $helpers->writeFile($dir, $fileName, $tpl);
            } else {
                $helpers->sendLog(MODX::LOG_LEVEL_INFO, '    ' . $fileName . ' already exists');
            }
        }
        return true;
    }
}