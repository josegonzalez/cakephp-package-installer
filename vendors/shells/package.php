<?php
/**
 *
 * No package can be installed from subversion or mercurial.
 *
 * Mandatory control fields:
 *
 * - Source: source to package installation (git://github.com/username/package.git)
 * - Maintainer: username at a given service (username)
 * - Package: name of package at source
 * - Description: description of the package
 * - Type: plugin|application|util
 * - Homepage: Homepage of package. Need not be a standalone page
 * - Issues: Issue-tracker
 *
 * Recommended
 * - Alias: folder_alias to install to. can be a path, in which case it is relative to APP
 * - Section: a given over-arching category for a package (authentication, tagging)
 * - Suggests: suggested package to also install
 * - Pre-depends: Packages that must be installed before usage
 * - Dependencies: Packages that should also be installed
 *
 */

/**
 * Package Shell
 *
 * PHP version 5
 *
 * @category Package
 * @package  cakepackages
 * @version  0.0.1
 * @author   Jose Diaz-Gonzalez <support@savant.be>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.josediazgonzalez.com
 */

class PackageShell extends Shell {

    var $Folder = null;

    var $Socket = null;

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
 * Initializes the Shell
 * acts as constructor for subclasses
 * allows configuration of tasks prior to shell execution
 *
 * @access public
 */
    function initialize() {
        parent::initialize();
        $this->api = 'http://packages.dev/1';
    }

/**
 * Help
 *
 * @return void
 * @access public
 */
    function help() {
        $this->out('Package Installer Shell');
        $this->hr();
        $this->out("Usage: cake package");
        $this->out("       cake package app");
        $this->out("       cake package installed");
        $this->out("       cake package verify");
        $this->out("       cake package rebuild");
        $this->out("       cake package search");
        $this->out("       cake package show package_name");
        $this->out("       cake package install package_name");
        $this->out("       cake package install maintainer_name/package_name");
        $this->out("       cake package install maintainer_name/package_name -version 1.0");
        $this->out("       cake package install maintainer_name/package_name -alias package_alias");
        $this->out("       cake package install maintainer_name/package_name -dir global");
        $this->out("       cake package install maintainer_name/package_name -source github");
        $this->out("       cake package remove package_name");
        $this->out("       cake package remove package_name -alias true");
        $this->out("       cake package remove maintainer_name/package_name");
        $this->out('');
    }

/**
 * Describes the current application in terms of it's control file
 *
 * @return void
 */
    function app() {
        $this->args[0] = 'app';
        $this->show();
    }

/**
 * Outputs the contents of the control file of either the application (app)
 * or a given plugin
 *
 * @return void
 */
    function show() {
        $path = empty($this->args[0]) ? 'app' : $this->args[0];
        if ($path == 'app') {
            $path = APP_PATH . 'config' . DS . 'control';
        }

        if (isset($this->params['path'])) {
            $path = $this->params['path'];
        }
        if (strpos($path, '/') !== 0) {
            $path =  'plugins' . DS . $path . DS . 'config' . DS . 'control';
            if (isset($this->params['global'])) {
                $path = ROOT . DS . $path;
            } else {
                $path = APP_PATH . $path;
            }
        }

        $control = $this->__loadControl($path);
        if (!$control) {
            $this->error(sprintf(__('Missing config at path %s', true), $path));
        }

        foreach ($control as $key => $value) {
            $this->out(sprintf('%s: %s', Inflector::humanize($key), $value));
        }
        $this->out();
    }

    function installed() {

    }

    function verify() {

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
 * Searches a cakepackages 1.0-compatible api
 *
 * @return void
 */
    function search() {
        if (!isset($this->args[0])) {
            $query = $this->in(__("Enter a search term or 'q' or nothing to exit", true), null, 'q');
        } else {
            $query = $this->args[0];
        }

        if ($query === 'q') {
            $this->_stop(1);
        }

        $this->out(__('Searching all plugins for query...', true));
        $this->_socket();
        $plugins = $this->_search($query);

        if (empty($plugins)) {
            $this->out(__('No results found. Sorry.'));
        } else {
            $this->out(sprintf('%d results found.', count($plugins)), 2);

            foreach ($plugins as $key => $result) {
                $name = str_replace(array('-plugin', '_plugin'), '', $result->name);
                $this->out(sprintf(__('%d. %s', true), $key + 1, $name));
                $this->out(sprintf(__('    %s', true), $result->summary), 2);
            }
        }
    }

/**
 * Fires a request to the server for a given query
 *
 * @param string $query 
 * @return mixed array of response objects if successful, false otherwise
 */
    function _search($query) {
        $response = false;

        Cache::set(array('duration' => '+7 days'));
        if (($response = Cache::read('Plugins.server.search.' . $query)) === false) {
            $response = json_decode($this->Socket->get(sprintf("%s/search/%s", $this->api, $query)));
            Cache::set(array('duration' => '+7 days'));
            Cache::write('Plugins.server.search.' . $query, $response);
        }

        if ($response->status == '200') {
            return $response->results;
        }
        return false;
    }

/**
 * cake package install package_name
 * cake package install maintainer_name/package_name
 * cake package install maintainer_name/package_name -version 1.0
 * cake package install maintainer_name/package_name -alias package_alias
 * cake package install maintainer_name/package_name -dir app
 * cake package install maintainer_name/package_name -source github
 * cake package install maintainer_name/package_name -type git
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
    function install() {
        $options    = array(
            'package'   => false,
            'maintainer'=> false,
            'version'   => false,
            'alias'     => false,
            'dir'       => 'app',
            'source'    => 'github',
            'type'      => 'git',
        );

        if (isset($this->args[0])) {
            if (strstr($this->args[0], '/') === false) {
                $options['package']     = $this->args[0];
            } else {
                list($maintainer, $package) = explode('/', $this->args[0]);
                $options['package']     = $package;
                $options['maintainer']  = $maintainer;
            }
        } elseif (isset($this->params['package'])) {
            $options['package'] = $this->params['package'];
        }

        if (!$options['package']) $this->error(__('Invalid package name', true));

        foreach ($options as $key => $value) {
            if (isset($this->params[$key])) {
                $options[$key] = $this->params[$key];
            }
        }

        $this->_socket();
        $result = $this->_installQuery($options);
        if (count($result) > 1) {
            $result = $this->_installChoose($result);
            if (!$result) $this->error(__('Unable to choose correct plugin', true));
        }

        if ($this->_install($result, $options)) {
            $this->out(sprintf(__('Successfully installed %s', true), $result['name']));
        }
    }

    function _installQuery($options) {
        $response = false;

        $request = array();
        foreach ($options as $option => $value) {
            if ($value !== false) {
                $request[$option] = $value;
            }
        }

        Cache::set(array('duration' => '+7 days'));
        if (($response = Cache::read('Plugins.server.install.' . md5(serialize($request)))) === false) {
            $response = json_decode($this->Socket->get(sprintf("%s/package", $this->api), $request));
            Cache::set(array('duration' => '+7 days'));
            Cache::write('Plugins.server.install.' . md5(serialize($request)), $response);
        }

        if ($response->status == '200') {
            return $response->results;
        }
        return false;
    }

    function _installChoose($result) {
        
    }

    function _install($result, $options) {
        
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
 * Setup the shell's HttpSocket object
 *
 * @return HttpSocket
 */
    function _socket() {
        if (!$this->Socket) {
            App::import('Core', 'HttpSocket');
            $this->Socket = new HttpSocket();
        }
        return $this->Socket;
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