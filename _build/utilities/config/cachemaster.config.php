<?php


$components = array(
    /* These are used to define the package and set values for placeholders */
    'packageName' => 'CacheMaster',
    'packageNameLower' => 'cachemaster',
    'author' => 'Bob Ray',
    'email' => '<http://bobsguides.com>',
    'copyright' => '2012',
    'createdon' => strftime('%m-%d-%Y'),
    'offerAbort' => false, /* Show package name and ask user to confirm before running */
    /* Change this if you need to alter any tpl files. User new dir. under _build/utilities */
    'tplDir' => 'buildtpls',

    /* Set directory permissions for project directories */
    'dirPermission' => 0755, /* No quotes!! */
    'filePermission' => 0644, /* No quotes!! */

    /* Define source directories (mycomponent root and core directories) */
    'source' => MODX_ASSETS_PATH . 'mycomponents/mycomponent/', /* path to MyComponent source files */
    'sourceCore' => MODX_ASSETS_PATH . 'mycomponents/mycomponent/',

    /* Define default directories and files to be created in project*/
    'initialize' => true,
    'defaultStuff' => array(
        '_build' => true, /* build directory for transport package */
        'utilities' => false, /* copy utilities to target dir -- Usually unnecessary */
        'lexicon' => true, /* create lexicon directory */
        'docs' => true, /* readme.txt, license, changelog, and/or tutorial(s) */
        'readme.md' => true, /* Description file for GitHub project home page */
        'languages' => 'en,fr', /* only list languages for which you have language files */
    ),
    /* Define optional directories to create under assets
     * add your own as needed
     * set to true to create directory
     * set to false to skip
     * Empty js and css files will be created
     */
    'assetsDirs' => array(
        'css' => true,
        'js' => true,
        'images' => true,
        'audio' => true,
        'video' => true,
        'themes' => true,
    ),
    /* extra script resolver(s) to be run during install
     *  Creates a default resolver named after the package
     *  (other resolvers may be created above for TVs and plugins)
     */
    'resolvers' => 'true',

    /* validators can abort the install after checking conditions
     * comma-separated list of validator names or '' (no prefix of suffix) */
    'validators' => '',

    /* install.options is needed if you will interact with user during the install */
    /* set to 'install.options' or '' */
    'install.options' => 'install.options',

    /* suffixes for files */
    'suffixes' => array(
        'plugin' => '.php',
        'snippet' => '.php',
        'chunk' => '.html',
        'template' => '.html',
        'tv' => '.tv',
        'resource' => '.html',
        'default' => '.php',
    ),

    /* These control the creation of elements */
    'createElementFiles' => true, /* create element files */
    'createElementObjects' => true, /* also create objects in MODX */
    'makeStatic' => 'CacheMaster', /* Comma-separated list of elements to set as static */
    'createResourceObjects' => false,
    'createResourceFiles' => false,
    'allStatic' => true, /* will make all element objects static - makeStatic will be ignored */


    /* array containing the actual Element Names */
    'elements' => array(
        'snippets' => '',
        'plugins' => 'CacheMaster',
        'tvs' => '',
        'templates' => '',
        'chunks' => '',
    ),
    /* comma-separated lists of the actual Resource Names */
    'resources' => array(

    ),
    /* array of plugin names and comma-separated list of their events.
     * automatically generates resolver
     */
    'pluginEvents' => array(
        'CacheMaster' => 'OnDocFormSave',
    ),

    /* Array of Templates and comma-separated list of TVs to attach to them.
     * Automatically generates resolver (use 'default' for default template).
     * TV names and Template names are both case-sensitive
     */
    'templateVarTemplates' => array(
        'default' => 'MyTvOne,MyTvTwo',
        'Collapsible' => 'MyTvOne,MyTvTwo,MyTvThree',
    ),

    /* (NOT IMPLEMENTED) Array of new events to create, plugins to attach, and fields */
    'newEvents' => array(
        'OnEvent1' => array(
            'plugins' => 'CacheMaster',
            'fields' => array(
                'event' => 'OnEvent1',
                'priority' => 0,
                'propertyset' => 0,
            ),
        ),
        'OnEvent2' => array(
            'plugins' => 'CacheMaster',
            'fields' => array(
                'event' => 'OnEvent2',
                'priority' => 0,
                'propertyset' => 0,
            ),
        )
    ),
    /* (NOT IMPLEMENTED) comma-separated lists naming other new objects
     * to create */
    'otherObjects' => array(
        'menus' => '',
        'systemEvents' => '', /* only *new* System Events to be created */
        'propertySets' => '',
        'systemSettings' => '',

    )
);

return $components;