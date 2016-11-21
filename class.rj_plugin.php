<?php

abstract class RJ_Plugin extends Gdn_Plugin {
    /** @var array Plugin Info needed in some helper functions. */
    public $pluginInfo = [];

    /**
     * Must be called from new plugin in its __construct method!
     *
     * @param Gdn_Plugin $sender Instance of the new plugin.
     *
     * @return void.
     */
    public function __construct($pluginName) {
        parent::__construct();

        // Save plugin info for later retrieval.
        $this->pluginInfo = Gdn::pluginManager()->getPluginInfo(
            $pluginName,
            Gdn_PluginManager::ACCESS_PLUGINNAME
        );

        // Register autoloader for this plugins classes.
        Gdn_Autoloader::registerMap(
            Gdn_Autoloader::MAP_PLUGIN,
            Gdn_Autoloader::CONTEXT_PLUGIN,
            __DIR__,
            [
                'SearchSubfolders' => false,
                'Extension' => $plugin->PluginInfo['Index'],
                'ClassFilter' => '*',
                'SaveToDisk' => true
            ]
        );
    }

    public function setup() {
        $this->structure();
    }

    public function structure() {
        return true;
    }

    public function __call($name, $arguments = '') {
        if (!class_exists($name)) {
            trigger_error(errorMessage('The "'.$this->pluginInfo['ClassName'].'" object does not have a "'.$name.'" method.', $this->pluginInfo['ClassName'], $name), E_USER_ERROR);
        }
        $class = new $name($this->pluginInfo['Index']);
        return $class;
    }

    /**
     * Override getView() to make plugin themable.
     *
     * Theme view must be in theme/views/plugin/name.php.
     *
     * @param  [type] $view [description]
     * @return [type]       [description]
     */
    public function getView($view) {
        $themeView = PATH_THEMES.DS.Gdn::controller()->Theme.DS.'views'.DS.$this->pluginInfo['Folder'].DS.$view;

        // Try to find a themed view first.
        if (is_readable($themeView)) {
            return $themeView;
        }

        // Return plugins view.
        return PATH_PLUGINS.DS.$this->pluginInfo['Folder'].DS.'views'.DS.$view;
    }
}
