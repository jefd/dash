<?php

require 'token.php';

$VERSION = 'v1.0.6';


add_shortcode( 'dashboard', 'metrics_dash_board');
function metrics_dash_board($atts) {
    global $VERSION;
    
    return <<<EOT
    <div id="dashboard-app"></div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/mithril/mithril.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/jefd/dash@{$VERSION}/wp-content/themes/hello-elementor-child/js/dash.js"></script>
    EOT;
}

add_action('rest_api_init', function () {
    register_rest_route( 'dash/v1', '/(?P<owner>[a-z-]+)/(?P<repo>[a-z-]+)/(?P<metric>[a-z-]+)',array(
        'methods'  => 'GET',
        'callback' => 'get_metric_data'
    ));
});

add_action('rest_api_init', function () {
    register_rest_route( 'dash/v1', '/repos',array(
        'methods'  => 'GET',
        'callback' => 'get_repo_list'
    ));
});


/*
function get_repo_list($response) {

    $data = [
        ['owner' => 'ufs-community', 'name' => 'ufs-weather-model', 'title' => 'Weather Model', 'minDate' => '2022-05-21'],
        ['owner' => 'ufs-community', 'name' => 'ufs-srweather-app', 'title' => 'Short Range Weather App', 'minDate' => '2022-06-22'],
    
    ];

    $response = new WP_REST_Response($data);
    $response->set_status(200);

    return $response;
    
}
*/

function get_repo_list($response) {
    $DB_PATH = dirname(__FILE__) . '/metrics.db';

    try {
        $db = new PDO("sqlite:$DB_PATH");

        $res = $db -> query('select * from repos;');

        $lst = [];
        foreach ($res as $row) {

            $o = Array();

            $o['owner'] = $row['owner'];
            $o['name'] = $row['name'];
            $o['title'] = $row['title'];
            $o['minDate'] = substr($row['minDate'], 0, 10);

            $lst[] = $o;

        }

        $response = new WP_REST_Response($lst);
        $response->set_status(200);

        return $response;

    }
    catch(PDOException $e) {
        return new WP_Error( 'error', $e->getMessage(), array('status' => 404) );
    }
    
}



/************************************* Constants *******************************************/
// map of repos to tokens
$REPOS = [
    ["owner" => "ufs-community", "name" => "ufs-weather-model", "token" => $TOKEN],
    ["owner" => "ufs-community", "name" => "ufs-srweather-app", "token" => $TOKEN],
];

// map of metric name to GitHub API path
$METRICS = ["views" => "/traffic/views",
            "clones" => "/traffic/clones",
            "frequency" => "/stats/code_frequency",
            "commits" => "/commits?per_page=100",
            "releases" => "/releases",
            "contributors" => "/contributors",
];

$NUMBER_TOP_CONTRIBUTORS = 3;
/*******************************************************************************************/



function mk_dataset($label, $color, $data){
    $lst = [
        'type' => 'line',
        'label' => $label,
        'borderColor' => $color, 
        'backgroundColor' => $color,
        'fill' => true,
        'tension' => 0.4,
        'borderWidth' => 3,
        'data' => $data
    ];
    return $lst;
}

function get_view_chart_data($url, $args) {
    function get_data($body){
        $dates = Array();
        $views = Array();

        foreach($body->views as $view) {
            $dates[] = substr($view->timestamp, 0, 10);
            $views[] = $view->count;
        }
        return Array('dates' => $dates, 'views' => $views, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($views){

        $m = Array();
        $m['labels'] = $views['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Views', '#01a64a', $views['views']);
        $m['count'] = $views['count'];
        $m['uniques'] = $views['uniques'];

        return $m;
    }

    $response = wp_remote_get($url, $args);
    
    if($response['response']['code'] == 200) {
        $body = json_decode(wp_remote_retrieve_body( $response ));
        $data = get_data($body);
        $chart_data = format_data($data);
    }
    else{
        $chart_data = ["message" => "Error loading Github metrics data"];
    }
    
    return $chart_data;
}

function get_view_chart_data_db($table_name) {
    function get_data($body){
        $dates = Array();
        $views = Array();

        foreach($body->views as $view) {
            $dates[] = substr($view->timestamp, 0, 10);
            $views[] = $view->count;
        }
        return Array('dates' => $dates, 'views' => $views, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($views){

        $m = Array();
        $m['labels'] = $views['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Views', '#01a64a', $views['views']);
        $m['count'] = $views['count'];
        $m['uniques'] = $views['uniques'];

        return $m;
    }

    //$response = wp_remote_get($url, $args);

    /*************************************************/
    $DB_PATH = dirname(__FILE__) . '/metrics.db';

    try {
        $db = new PDO("sqlite:$DB_PATH");
        $res = $db -> query("select * from \"$table_name\";");


        $lst = [];
        $count = 0;
        $uniques = 0;
        foreach ($res as $row) {

            $o = Array();

            $o['timestamp'] = $row['timestamp'];
            $o['count'] = $row['count'];
            $o['uniques'] = $row['uniques'];

            $count += $o['count'];
            $uniques += $o['uniques'];

            $lst[] = $o;

        }
        $body = json_decode(json_encode(["count" => $count, "uniques" => $uniques, "views" => $lst]));
        $data = get_data($body);
        $chart_data = format_data($data);
    
    }
    catch(PDOException $e) {
        $chart_data = ["message" => $e->getMessage()];
    }

    
    return $chart_data;
}
    /*************************************************/

function get_clone_chart_data($url, $args) {
    function get_data($body){
        $dates = Array();
        $clones = Array();

        foreach($body->clones as $clone) {
            $dates[] = substr($clone->timestamp, 0, 10);
            $clones[] = $clone->count;
        }
        return Array('dates' => $dates, 'clones' => $clones, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($clones){

        $m = Array();
        $m['labels'] = $clones['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Clones', '#01a64a', $clones['clones']);
        $m['count'] = $clones['count'];
        $m['uniques'] = $clones['uniques'];

        return $m;
         
    }

    $response = wp_remote_get($url, $args);

    if($response['response']['code'] == 200) {
        $body = json_decode(wp_remote_retrieve_body( $response ));
        $data = get_data($body);
        $chart_data = format_data($data);
    }
    else{
        $chart_data = ["message" => "Error loading Github metrics data"];
    }
    
    return $chart_data;
       
}

function get_freq_chart_data($url, $args) {
    function get_data($body){
        $dates = Array();
        $additions = Array();
        $deletions = Array();
        foreach($body as $lst){
            $timestamp = $lst[0];
            $dates[] = date("Y-m-d", $timestamp);
            $additions[] = $lst[1];
            $deletions[] = $lst[2];
        }
        return Array('dates' => $dates, 'additions' => $additions, 'deletions' => $deletions);
    }

    function format_data($freq){

        $m = Array();
        $m['labels'] = $freq['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Additions', '#d87203', $freq['additions']);
        $m['datasets'][] = mk_dataset('Deletions', '#01a64a', $freq['deletions']);

        return $m;
    }

    $response = wp_remote_get($url, $args);

    if($response['response']['code'] == 200) {
        $body = json_decode(wp_remote_retrieve_body( $response ));
        $data = get_data($body);
        $chart_data = format_data($data);
    }
    else{
        $chart_data = ["message" => "Error loading Github metrics data"];
    }
    
    return $chart_data;
       
}

function get_commit_chart_data($url, $args) {
    
	function split_strip($s, $ch) {
        $lst = explode($ch, $s);
        return array_map("trim", $lst);
    }
    
    
    function fetch_links($headers) {
        $rel_lst = ['first', 'last', 'next', 'prev'];
        $m = Array();

        try {
            $link = $headers['Link'];
        } 
        catch (Exception $e) {
            return $m;
        }
        
        if (!isset($link) || $link == '') {
            return $m;
        }

        $f = function($item) {
            return split_strip($item, ';');
        };

        foreach($rel_lst as $rel) {
            $l = split_strip($link, ',');
            $l2 = array_map($f, $l);

            foreach($l2 as $a) {
                if(strpos($a[1], $rel)) {
                    $m[$rel] = substr($a[0], 1, strlen($a[0]) - 2);
                }
            }

        }

        return $m;
    }

    function get_commit_map($url, $args) {
        $commit_map = Array();

        while (isset($url) && $url !== '') {
            $response = wp_remote_get($url, $args);
            if($response['response']['code'] == 200) {
                $body = json_decode(wp_remote_retrieve_body($response));

                foreach($body as $l) {
                    $date = substr($l->commit->author->date, 0, 10);
                    if (array_key_exists($date, $commit_map)) {
                        $commit_map[$date] += 1;
                    }
                    else {
                        $commit_map[$date] = 1;
                    }

                }

                $headers = wp_remote_retrieve_headers($response);
                $links = fetch_links($headers);

                if (array_key_exists('next', $links)) {
                    $url = $links['next'];
                }
                else {
                    $url = '';
                }

            }

        }
        ksort($commit_map);
        return $commit_map;
    }

    function get_data($commit_map){
        $dates = Array();
        $commits = Array();

        foreach($commit_map as $k=>$v) {
            $dates[] = $k;
            $commits[] = $v;
        }
        return Array('dates' => $dates, 'commits' => $commits);
    }

    function format_data($data){

        $m = Array();
        $m['labels'] = $data['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Commits', '#01a64a', $data['commits']);

        return $m;
    }


     
    $commit_map = get_commit_map($url, $args);
    
    if (empty($commit_map)) {
        return $commit_map;
    }

    $chart_data = format_data(get_data($commit_map));
    return $chart_data;
}


function get_release_data($url, $args) {

    $response = wp_remote_get($url, $args);

    if($response['response']['code'] == 200) {
        $body = json_decode(wp_remote_retrieve_body( $response ));

        $release_data = [];
        foreach($body as $release) {
            $name = $release->name;
            $d = $release->created_at;
            $release_data[] = ["name" => $name, "date" => $d];
        }
        $rel_data = ["releases" => $release_data];
    }
    else{
        $rel_data = ["message" => "Error loading Github metrics data"];
    }
    
    return $rel_data;
}

function get_contributor_data($url, $args) {
    global $NUMBER_TOP_CONTRIBUTORS;

    $response = wp_remote_get($url, $args);
    
    if($response['response']['code'] == 200) {
        $body = json_decode(wp_remote_retrieve_body( $response ));

        $total = count($body);
        $top_list = array_splice($body, 0, $NUMBER_TOP_CONTRIBUTORS);

        $top = [];
        foreach($top_list as $t) {
            $top[] = ["login" => $t->login, "contributions" => $t->contributions];
        } 

        $tc = ["count" => $total, "top" => $top];

    }
    else{
        $tc = ["message" => "Error loading Github metrics data"];
    }
    
    return $tc;
}


function get_url($owner, $repo, $metric) {
    global $METRICS;

    $path = $METRICS[$metric];

    $url = "https://api.github.com/repos/{$owner}/{$repo}{$path}";
    return $url;

}

function get_table_name($owner, $repo, $metric) {
    return "{$owner}/{$repo}/{$metric}";
}

function get_args($owner, $repo) {
    function get_token($owner, $repo) {
        global $REPOS;
        foreach ($REPOS as $item) {
            if ($item["owner"] == $owner && $item["name"] == $repo) {
                return $item["token"];
            }
        }
        return null;
    }

    $token = get_token($owner, $repo);

    $args = array(
        'headers' => array(
        'Authorization' => "Bearer {$token}"
        ),
    );
    return $args;
}



function get_metric_data($request) {

    $start = $request->get_param('start');
    $end = $request->get_param('end');

    // testing querystring parameters
    /*
    if (! is_null($start) ) {
        $response = new WP_REST_Response(['start' => $start]);
        $response->set_status(200);
        return $response;
    }
     */

    $owner = $request['owner'];
    $repo = $request['repo'];
    $metric = $request['metric'];

    $args = get_args($owner, $repo);

    $url = get_url($owner, $repo, $metric);
    $table_name = get_table_name($owner, $repo, $metric);

    if ($metric == "views") {
        //$data = get_view_chart_data($url, $args);
        $data = get_view_chart_data_db($table_name); 
    }
    else if ($metric == "clones") {
        $data = get_clone_chart_data($url, $args);
    }
    else if ($metric == "frequency") {
        $data = get_freq_chart_data($url, $args);
    }
    else if ($metric == "commits") {
        $data = get_commit_chart_data($url, $args);
    }
    else if ($metric == "releases") {
        $data = get_release_data($url, $args);
    }
    else if ($metric == "contributors") {
        $data = get_contributor_data($url, $args);
    }

    if (empty($data)) {
        return new WP_Error( 'No Data', 'No Data', array('status' => 404) );
    }

    $response = new WP_REST_Response($data);
    $response->set_status(200);

    return $response;
}

