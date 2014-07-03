<?php

//  Load DOM Reader
require_once PDS_SS_LIB_PATH . 'class/simple_html_dom.php';
require_once PDS_SS_LIB_PATH . 'class/PDS_SS_Parse_INI.php';

class PDS_SS_Suggestion_Finder {

    //  Default Provider to Use
    private $def_provider;

    //  Suggestion Provider Hosts to Mappings
    private $providers = array();

    
    //  Constructor
    public function __construct() {
        //  Do Nothing
    }

    //  Get Database Reader
    protected function getWPDB() {
        global $wpdb;
        return $wpdb;
    }

    //  Generate Random Default Provider
    public function randomizeProvider() {

        //  Provide Keys
        $providers = array_keys($this->providers);

        //  Set a Random One
        $this->def_provider = $providers[mt_rand(0, sizeof($providers) - 1)];
    }

    //  Set Default Provider
    public function setDefaultProvider($provider) {

        //  Check Provider Exists
        if($this->hasProvider($provider)) {

            //  Set Default
            $this->def_provider = $provider;
        }
    }

    //  Get Default Provider
    public function getDefaultProvider() {
        return $this->def_provider;
    }

    //  Providers Lists
    public function providersLists() {

        //  Providers
        $providers = array();

        //  Loop Each
        foreach($this->providers as $provider_name => $provider) {

            //  Store
            $providers[$provider_name] = $provider['label'];
        }

        //  Return
        return $providers;
    }

    //  Add New Provider
    public function addProvider($provider, $label) {

        //  Check Already Exists
        if(!$this->hasProvider($provider)) {

            //  Create
            $this->providers[$provider] = array(
                'label' => $label,
                'mappers' => array(),
                'max_map_priority' => 0
            );

            //  Randomize Provider
            $this->randomizeProvider();
        }
    }

    //  Check Provider Already Exists
    public function hasProvider($provider) {
        return (isset($this->providers[$provider]));
    }

    //  Remove Existsing Provider
    public function removeProvider($provider) {

        //  Check
        if($this->hasProvider($provider)) {

            //  Unset
            unset($this->providers[$provider]);
        }
    }

    //  Get Provider
    public function getProvider($provider, $def = null) {

        //  Check Exists
        if($this->hasProvider($provider)) {

            //  Return
            return $this->providers[$provider];
        }

        //  Return Default
        return $def;
    }

    //  Add New Provider Mapper
    public function addProviderMapper($provider, $name, $url, $preg, $priority = null) {

        //  Check Provider Exists
        if($this->hasProvider($provider) && !$this->providerHasMapper($name)) {

            //  Get New Priority
            $priority || $priority = $this->providers[$provider]['max_priority'] + 1;

            //  Add
            $this->providers[$provider]['mappers'][$name] = array(
                'url' => $url,
                'match' => (is_array($preg) ? $preg : array($preg)),
                'priority' => $priority,
            );

            //  Set Max Priority
            if($priority > $this->providers[$provider]['max_priority'])
                $this->providers[$provider]['max_priority'] = $priority;
        }
    }

    //  Check Provider Has Mapper
    public function providerHasMapper($provider, $mapper) {
        return ($this->hasProvider($provider) && isset($this->providers[$provider]['mappers'][$mapper]));
    }

    //  Remove Provider Mapper
    public function removeProviderMapper($provider, $mapper) {

        //  Check the Provider Mapper Exists
        if($this->providerHasMapper($provider, $mapper)) {

            //  Unset
            unset($this->providers[$provider]['mappers'][$mapper]);
        }
    }

    //  Reset Provider Mappers
    public function resetProviderMappers($provider, $new = array()) {

        //  Check Provider Exists
        if($this->hasProvider($provider)) {

            //  Clear Mappers
            $this->providers[$provider]['mappers'] = $new;
        }
    }

    //  Add New Match Term for Provider Mapper
    public function addMatchForProviderMapper($provider, $mapper, $match) {

        //  Check Provider Exists
        if($this->providerHasMapper($provider, $mapper)) {

            //  Add Match
            $this->providers[$provider]['mappers'][$mapper]['match'][] = $match;
        }
    }

    //  Reset Match Terms for Provider Mapper
    public function resetMatchesForProviderMapper($provider, $mapper, $new = array()) {

        //  Check Provider Exists
        if($this->providerHasMapper($provider, $mapper)) {

            //  Add Match
            $this->providers[$provider]['mappers'][$mapper]['match'] = $new;
        }
    }

    //  Add New Suggestion to Dictionary
    public function addSuggestion($query, $suggestion, $provider = null) {

        //  Check
        if(!$this->suggestionExists($query, $suggestion)) {

            //  Get WPDB
            $wpdb = $this->getWPDB();

            //  Provider
            $provider || $provider = $this->def_provider;

            //  Prepare the Insert Query
            $sql = "INSERT INTO {$wpdb->table_spell_dict} SET " . 
                    "`query` = '{$query}', `suggestion` = '{$suggestion}', `provider` = '{$provider}'";

            //  Perform Query
            $wpdb->query($sql);
        }

        //  Return the Suggestion
        return $suggestion;
    }

    //  Check the Query -> Suggestion Exists
    public function suggestionExists($query, $suggestion) {

        //  Get WPDB
        $wpdb = $this->getWPDB();

        //  Get Row
        $row = $wpdb->get_row("SELECT id FROM {$wpdb->table_spell_dict} WHERE `query` = '{$query}' AND `suggestion` = '{$suggestion}' LIMIT 1");

        //  Return
        return (!is_null($row));
    }

    //  Get Local Suggestions for the Query
    public function getLocalSuggestions($query) {

        //  Get WPDB
        $wpdb = $this->getWPDB();

        //  Suggestions
        $suggestions = array();

        //  Get Results
        $results = $wpdb->get_results("SELECT suggestion FROM {$wpdb->table_spell_dict} WHERE `query` = '{$query}'");

        //  Loop Each Suggestions
        foreach($results as $row) {

            //  Store Suggestion
            $suggestions[] = $row->suggestion;
        }

        //  Return Suggestions
        return $suggestions;
    }

    //  Get Suggestions Auto
    public function getSuggestions($query, $force = false) {

        //  Get Local Suggestions
        $suggestions = $this->getLocalSuggestions($query);

        //  Get Last Suggestion Loaded Time
        $last_loaded = get_option('__spell_suggestions_loaded__' . $this->def_provider);

        //  Check Suggesstions Found
        if((!$last_loaded || ($last_loaded + 120) < time())
                && (sizeof($suggestions) == 0 || $force)) {

            //  Read from Providers
            $new_suggestions = $this->_read_from_providers($query);

            //  Save All Suggestions to Database
            foreach($new_suggestions as $new_suggestion) {

                //  Save Suggestion to Database
                $this->addSuggestion($query, $new_suggestion);
            }

            //  Set
            $suggestions = $new_suggestions;

            //  Store Last Suggestion Loaded Time
            update_option('__spell_suggestions_loaded__' . $this->def_provider, time());
        }

        //  Return Suggestions
        return $suggestions;
    }

    //  Read Suggestions from Provider
    protected function _read_from_providers($query, $provider = null) {

        //  Provider
        $provider || $provider = $this->def_provider;

        //  Get Provider Info
        $providerInfo = $this->getProvider($provider);

        //  Mappers
        $mappers = $providerInfo['mappers'];

        //  Sort Mappers
        $this->_items_sort($mappers, 'priority');

        //  Suggestions
        $suggestions = array();

        //  Loop Each Mappers
        foreach($mappers as $mapper) {

            //  Match Pregs
            $match_pregs = $mapper['match'];

            //  Check
            if(sizeof($match_pregs) > 0) {

                //  URL to Map
                $urlToMap = str_ireplace('{query}', $query, $mapper['url']);

                //  Read cURL Contents
                $curl_contents = $this->_curl_read_url($urlToMap);
                //$curl_contents = file_get_contents(PDS_SS_PATH . 'test_cache/google_response.html');

                //  Loop Each
                foreach($match_pregs as $match_preg) {

                    //  Check for Closure
                    if($match_preg instanceof Closure) {

                        //  Get Match
                        $match = call_user_func_array($match_preg, array($curl_contents));

                        //  Check
                        if($match) {

                            //  Store Suggestion
                            $suggestions[] = $match;

                            //  Break the Loop
                            break;
                        }
                    } else {

                        //  Perform Match Query
                        $match = null;
                        preg_match($match_preg, $curl_contents, $match);

                        //  Check Found
                        if($match) {

                            //  Get Suggestion
                            $suggestions[] = trim(strip_tags($match['suggestion']));

                            //  Break the Loop
                            break;
                        }
                    }
                }
            }
        }

        //  Return Suggestions
        return $suggestions;
    }

    //  Read Config File
    public function readConfigFile($file_path, $clear = false) {

        //  Read Configurations
        $configs = PDS_SS_Parse_INI::parse($file_path, true);

        //  Check Configs Loaded
        if($configs) {

            //  Store
            $this->providers = ($clear ? $configs : array_merge($this->providers, $configs));

            //  Set Random Provider
            $this->randomizeProvider();
        }
    }

    //  Read cURL Data
    private function _curl_read_url($url) {

        //  Explode URL
        $explodes = explode('?', $url);

        //  URL Page Only
        $safeURLPage = $explodes[0];
        unset($explodes[0]);

        //  Query Vars
        $query_vars = array();

        //  Loop Each
        foreach($explodes as $explode) {

            //  Explode Again
            $exp1 = explode('=', $explode);

            //  Store
            $query_vars[$exp1[0]] = $exp1[1];
        }

        //  Safe URL
        $safeURL = $safeURLPage . '?' . http_build_query($query_vars);

        //  User Agent
        $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';

        //  Create cUrl Instance
        $ch = curl_init();

        //  Set cUrl Properties
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_URL, $safeURL);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        //curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

        //  Get Response
        $response = curl_exec($ch);

        //  Read Status Code
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //  Check for 200
        if($statusCode != 200) {

            //  Clear Response
            $response = null;
        }

        //  Close cUrl Connection
        curl_close($ch);

        //  Return Response
        return $response;
    }

    //  Sort Array Items
    protected function _items_sort(&$array, $key) {
        $sorter = array();
        $ret = array();
        reset($array);
        foreach ($array as $ii => $va) {
            $sorter[$ii] = $va[$key];
        }
        asort($sorter);
        foreach ($sorter as $ii => $va) {
            $ret[$ii] = $array[$ii];
        }
        $array = $ret;
    }
}