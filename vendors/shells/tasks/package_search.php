<?php
/**
 * Package Search Task
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

class PackageSearchTask extends Shell {

    var $Socket = null;


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
        $this->out('Queries a given package index for a term', 2);
        $this->out('Usage: cake package search QUERY', 2);
    }

/**
 * Searches a cakepackages 1.0-compatible api
 *
 * @return void
 */
    function search() {
        if (!isset($this->args[1])) {
            $query = $this->in(__("Enter a search term or 'q' or nothing to exit", true), null, 'q');
        } else {
            $query = $this->args[1];
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