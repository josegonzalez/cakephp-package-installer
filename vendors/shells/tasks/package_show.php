<?php
/**
 * Package Show Shell
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

class PackageShowTask extends Shell {

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
        $this->out('Package Show Task');
        $this->hr();
        $this->out('Shows the contents of a given control file', 2);
        $this->out('Usage: cake package show');
        $this->out('       cake package show app');
        $this->out('       cake package show PACKAGE_NAME', 2);
        $this->out('You can substitute PACKAGE_NAME for the name of an installed package.');
        $this->out('For example:', 2);
        $this->out('       cake package show package_installer');
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
        $path = empty($this->args[0]) ? (empty($this->args[1]) ? 'app' : $this->args[1]): $this->args[0];
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

}