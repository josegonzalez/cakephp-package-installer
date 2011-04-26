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
 * @package  package_installer
 * @version  0.0.1
 * @author   Jose Diaz-Gonzalez <support@savant.be>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.josediazgonzalez.com
 */

class PackageShell extends Shell {

    var $Socket = null;

    var $tasks = array(
        'PackageRebuild',
        'PackageSearch',
        'PackageShow',
    );

    var $map = array(
        'app' => 'PackageShow::app',
        'show' => 'PackageShow::show',
        'rebuild' => 'PackageRebuild::rebuild',
        'search' => 'PackageSearch::search',
    );

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

        if (isset($this->map[$this->command])) {
            $cmd = explode('::', $this->map[$this->command]);
            unset($this->args[0], $this->{$cmd[0]}->args[0]);
            return $this->{$cmd[0]}->{$cmd[1]}();
        }

        return $this->{$this->command}();
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
        $this->api = 'http://cakepackages.com/1';
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

    function installed() {

    }

    function verify() {

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

}