<?php
/**
 * Package Rebuild Task
 *
 * PHP version 5
 *
 * @category Package
 * @package  package_installer
 * @version  0.0.1
 * @author   Jose Diaz-Gonzalez <support@savant.be>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.josediazgonzalez.com
 * @todo     implement execute() method to redirect ridiculous task calls
 */

class PackageRebuildTask extends Shell {

    var $Folder = null;

/**
 * Main shell logic.
 *
 * @return void
 */
    function main() {
        if (!empty($this->params[0])) {
            $this->command = $this->params[0];
        }

        if (empty($this->command)) {
            $this->command = 'help';
        }

        $this->{$this->command}();
    }

/**
 * Help
 *
 * @return void
 * @access public
 */
    function help() {
        $this->out('Package Rebuild Task');
        $this->hr();
        $this->out('Rebuilds the installed package index', 2);
        $this->out('Usage: cake package rebuild', 2);
    }

/**
 * Rebuilds a packages.php config file based upon existing plugins
 *
 * @return void
 */
    function rebuild() {
        $plugins = array();
        $this->_folder(APP);

        foreach (App::path('plugins') as $path) {
            $this->Folder->cd($path);
            $contents = $this->Folder->read();
            $folders = $contents[0];

            foreach ($folders as $plugin) {
                $plugins[$plugin] =  $path . $plugin;
            }
        }

        $configs = array();
        $incompatible = array();
        foreach ($plugins as $plugin => $path) {
            $config = $this->__loadControl($path . DS . 'config' . DS . 'control', true);
            if (!$config) {
                $incompatible[] = $plugin;
                continue;
            }

            $config['install-path'] = $path;
            $configs[$plugin] = $config;
        }

        if (!$this->_writeInstalled($configs)) {
            $this->error('Unable to write packages file');
        }

        if (!empty($incompatible)) {
            $this->out('Incompatible packages');
            $this->hr();
            foreach ($incompatible as $plugin) {
                $this->out($plugin);
            }
            $this->hr();
        }

        $this->out('Package file created');
    }

/**
 * Loads a control file into an array from a given path
 *
 * @param string $path full path to control file, including "control"
 * @return mixed False if control file does not exist or array of field names mapping to values
 */
    function __loadControl($path, $normalize = false) {
        if (!file_exists($path)) {
            return false;
        }

        $last = null;
        $contents = array();
        $file = file($path);
        foreach ($file as $line) {
            if (strpos($line, '  ') === 0) {
                if (!$last) continue;
                $contents[$last] .= ' ' . trim($line);
            } else {
                if (strstr($line, ':') === false) {
                    continue;
                }
                list($last, $line) = explode(':', $line, 2);
                $last = strtolower($last);
                $contents[$last] = trim($line);
            }
        }

        $results = $contents;
        if ($normalize) {
            $results = array();
            foreach ($contents as $field => $value) {
                $results[Inflector::slug(strtolower($field), '-')] = $value;
            }
        }

        return $contents;
    }

/**
 * Writes a packages.php file
 *
 * @param array $configs array of config control files
 * @return void
 */
    function _writeInstalled($configs) {
        $keys = array(
            'source', 'maintainer', 'package', 'description', 'type', 'homepage', 'issues',
            'alias', 'section', 'suggests', 'pre-depends', 'dependencies', 'install-path',
        );
        $content = "<?php\n";
        $content .= "\$installed = array(\n";
        foreach ($configs as $pluginName => $config) {
            $content .= "\t'" . $pluginName . "' => array(\n";
            foreach ($keys as $key) {
                if (!isset($config[$key])) continue;
                $content .= "\t\t'" . $key . "' => '" . $config[$key] . "',\n";
            }
            $content .= "\t),\n";
        }
        $content .= ");\n";
        $content .= "?>";

        $File = new File(CONFIGS . 'packages.php', true);
        return $File->write($content);
    }

/**
 * Setup the shell's Folder object
 *
 * @return Folder
 */
    function _folder($path) {
        if (!$this->Folder) {
            App::import('Core', 'Folder');
            $this->Folder = new Folder($path);
        }

        $this->Folder->cd($path);
        return $this->Folder;
    }

}