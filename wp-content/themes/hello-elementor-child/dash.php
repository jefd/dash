<?php

require 'token.php';

$VERSION = 'v1.0.6';


$DB_PATH = dirname(__FILE__) . '/metrics.db';


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
    global $DB_PATH;

    try {
        $db = new PDO("sqlite:$DB_PATH");

        $res = $db -> query('select * from repos order by owner, name;');

        $lst = [];
        foreach ($res as $row) {

            $o = Array();

            $o['owner'] = $row['owner'];
            $o['name'] = $row['name'];
            $o['metric'] = $row['metric'];
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
            "forks" => "/forks?per_page=100",
            "releases" => "/releases",
            "contributors" => "/contributors",
];

$NUMBER_TOP_CONTRIBUTORS = 3;
/*******************************************************************************************/



function mk_dataset($label, $color, $data, $order=0){
    $lst = [
        'type' => 'line',
        'label' => $label,
        'borderColor' => $color, 
        'backgroundColor' => $color,
        'fill' => false,
        'order' => $order,
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
        $unique_views = Array();

        foreach($body->views as $view) {
            $dates[] = substr($view->timestamp, 0, 10);
            $views[] = $view->count;
            $unique_views[] = $view->uniques;
        }
        return Array('dates' => $dates, 'views' => $views, 'unique_views' => $unique_views, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($data){

        $m = Array();
        $m['labels'] = $data['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Views', '#01a64a', $data['views'], 1);
        $m['datasets'][] = mk_dataset('Unique Views', '#d87203', $data['unique_views']);
        $m['count'] = $data['count'];
        $m['uniques'] = $data['uniques'];

        return $m;
    }

    $response = wp_remote_get($url, $args);
    
    if($response['response']['code'] == 200) {
        $body = json_decode(wp_remote_retrieve_body( $response ));
        $data = get_data($body);
        $chart_data = format_data($data);
    }
    else{
        $chart_data = ["message" => "Error loading Github metrics data " . $response['response']['code']];
    }
    
    return $chart_data;
}

function get_view_chart_data_db($table_name, $start, $end) {
    global $DB_PATH;

    function get_data($body){
        $dates = Array();
        $views = Array();
        $unique_views = Array();

        foreach($body->views as $view) {
            $dates[] = substr($view->timestamp, 0, 10);
            $views[] = $view->count;
            $unique_views[] = $view->uniques;
        }
        return Array('dates' => $dates, 'views' => $views, 'unique_views' => $unique_views, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($data){

        $m = Array();
        $m['labels'] = $data['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Views', '#01a64a', $data['views'], 1);
        $m['datasets'][] = mk_dataset('Unique Views', '#d87203', $data['unique_views']);
        $m['count'] = $data['count'];
        $m['uniques'] = $data['uniques'];

        return $m;
    }

    //$response = wp_remote_get($url, $args);

    /*************************************************/

    try {

        $db = new PDO("sqlite:$DB_PATH");
        //$res = $db -> query("select * from \"$table_name\";");
        //$res = $db -> query("select * from \"$table_name\" where timestamp>=\"2022-08-23\" and timestamp<=\"2022-08-27\";");
        $start .= 'T00:00:00Z'; $end .= 'T00:00:00Z';
        $res = $db -> query("select * from \"$table_name\" where timestamp>=\"$start\" and timestamp<=\"$end\" order by timestamp;");

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
        $unique_clones = Array();

        foreach($body->clones as $clone) {
            $dates[] = substr($clone->timestamp, 0, 10);
            $clones[] = $clone->count;
            $unique_clones[] = $clone->uniques;
        }
        return Array('dates' => $dates, 'clones' => $clones, 'unique_clones' => $unique_clones, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($data){

        $m = Array();
        $m['labels'] = $data['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Clones', '#01a64a', $data['clones'], 1);
        $m['datasets'][] = mk_dataset('Unique Clones', '#d87203', $data['unique_clones']);
        $m['count'] = $data['count'];
        $m['uniques'] = $data['uniques'];

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

function get_clone_chart_data_db($table_name, $start, $end) {
    global $DB_PATH;

    function get_data($body){
        $dates = Array();
        $clones = Array();
        $unique_clones = Array();

        foreach($body->clones as $clone) {
            $dates[] = substr($clone->timestamp, 0, 10);
            $clones[] = $clone->count;
            $unique_clones[] = $clone->uniques;
        }
        return Array('dates' => $dates, 'clones' => $clones, 'unique_clones' => $unique_clones, 'count' => $body->count, 'uniques' => $body->uniques);
    }

    function format_data($data){

        $m = Array();
        $m['labels'] = $data['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Clones', '#01a64a', $data['clones'], 1);
        $m['datasets'][] = mk_dataset('Unique Clones', '#d87203', $data['unique_clones']);
        $m['count'] = $data['count'];
        $m['uniques'] = $data['uniques'];

        return $m;
         
    }

    //$response = wp_remote_get($url, $args);

    /*************************************************/

    try {

        $db = new PDO("sqlite:$DB_PATH");
        //$res = $db -> query("select * from \"$table_name\";");
        //$res = $db -> query("select * from \"$table_name\" where timestamp>=\"2022-08-23\" and timestamp<=\"2022-08-27\";");
        $start .= 'T00:00:00Z'; $end .= 'T00:00:00Z';
        $res = $db -> query("select * from \"$table_name\" where timestamp>=\"$start\" and timestamp<=\"$end\" order by timestamp;");

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
        $body = json_decode(json_encode(["count" => $count, "uniques" => $uniques, "clones" => $lst]));
        $data = get_data($body);
        $chart_data = format_data($data);
    
    }
    catch(PDOException $e) {
        $chart_data = ["message" => $e->getMessage()];
    }

    
    return $chart_data;
}

function get_freq_chart_data($url, $args, $start, $end) {

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

    function get_indices($dates, $start, $end) {
        $start_index = -1;
        $end_index = -1;
        foreach ($dates as $ind => $date) {
            if ($date >= $start && $start_index == -1)
                $start_index = $ind;
            
            if ($date >= $end && $end_index == -1)
                $end_index = $ind;
            
        }
		if ($start_index == -1)
        	$start_index = 0;

    	if ($end_index == -1)
        	$end_index = count($dates) - 1;

        return [$start_index, $end_index];
    }

    function filter_data($data, $start, $end) {
        $dates = $data['dates'];
        $additions = $data['additions'];
        $deletions = $data['deletions'];

        $ind = get_indices($dates, $start, $end);
        $start_index = $ind[0];
        $end_index = $ind[1];
        
        if ($start_index != -1 && $end_index != -1) {
            $len = $end_index - $start_index + 1;
            $dates = array_slice($dates, $start_index, $len);
            $additions = array_slice($additions, $start_index,  $len);
            $deletions = array_slice($deletions, $start_index, $len);

        }
        return Array('dates' => $dates, 'additions' => $additions, 'deletions' => $deletions);
    }


    function format_data($data){

        $m = Array();
        $m['labels'] = $data['dates'];

        $m['datasets'] = Array();
        $m['datasets'][] = mk_dataset('Additions', '#d87203', $data['additions']);
        $m['datasets'][] = mk_dataset('Deletions', '#01a64a', $data['deletions']);

        return $m;
    }


    $tries = 0;

    while ($tries < 10){
        $tries += 1;
        $response = wp_remote_get($url, $args);
        $status_code = $response['response']['code'];
        if($status_code == 200) {
            $body = json_decode(wp_remote_retrieve_body( $response ));
            $data = get_data($body);
            $data = filter_data($data, $start, $end);
            $chart_data = format_data($data);
            return $chart_data;
        }
        sleep(1);
    }

    $chart_data = ["message" => "Error loading Github metrics data"];
    
    
    return $chart_data;
       
}

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

function get_fork_count($url, $args) {
    $total = 0;
    while (isset($url) && $url !== '') {
        $response = wp_remote_get($url, $args);
        if($response['response']['code'] == 200) {
            $body = json_decode(wp_remote_retrieve_body($response));

            foreach($body as $l) {
                $date = substr($l->commit->author->date, 0, 10);
                $count = $l->forks_count;
                if ($count == 0)
                    $total += 1;
                else
                    $total += ($count + 1);

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
    return $total;

}

function get_fork_count_db($table_name) {
    global $DB_PATH;
    $db = new PDO("sqlite:$DB_PATH");
    $res = $db -> query("select fork_count from \"$table_name\" limit 1;");
    foreach ($res as $row) {
        return $row['fork_count'];
    }
     
}

function get_commit_chart_data($url, $args) {
    
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

    $owner = $request['owner'];
    $repo = $request['repo'];
    $metric = $request['metric'];

    $start = $request->get_param('start');
    $end = $request->get_param('end');
    
    $dl = $request->get_param('dl');

    $args = get_args($owner, $repo);

    $url = get_url($owner, $repo, $metric);
    $table_name = get_table_name($owner, $repo, $metric);

    if ($metric == "views") {
        //$data = get_view_chart_data($url, $args);
        $data = get_view_chart_data_db($table_name, $start, $end); 
    }
    else if ($metric == "clones") {
        //$data = get_clone_chart_data($url, $args);
        $data = get_clone_chart_data_db($table_name, $start, $end); 

        // Special case: we need to add the number of forks 
        // here so we can include it below the clones graph.
        
        // Get data from api
        //$fork_url = get_url($owner, $repo, "forks");
        //$fork_count = get_fork_count($fork_url, $args);
        
        // Get data from local database
        $fork_table_name = get_table_name($owner, $repo, "forks");
        $fork_count = get_fork_count_db($fork_table_name);
        $data['fork_count'] = $fork_count;
    }
    else if ($metric == "frequency") {
        $data = get_freq_chart_data($url, $args, $start, $end);
    }
    else if ($metric == "commits") {
        //$url .= "&since=2022-08-01T00:00:00Z&until=2022-09-06T00:00:00Z";
        //$url .= "&since=2021-08-01T00:00:00Z&until=2022-08-01T00:00:00Z";
        $url .= "&since={$start}T00:00:00Z&until={$end}T00:00:00Z";
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

    if($dl == '1')
        return serve_csv($data, $request);

    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
}


function mk_csv($data, $metric) {
    $header_map = [
        'views' => ['timestamp', 'count', 'uniques'],
        'clones' => ['timestamp', 'count', 'uniques'],
        'frequency' => ['timestamp', 'additions', 'deletions'],
        'commits' => ['timestamp', 'commits'],
    ];

    try {
        $headers = $header_map[$metric];
        $timestamps = $data['labels'];
        
        $col1 = $data['datasets'][0]['data'];
        $col2 = null;
        if (count($headers) == 3)
            $col2 = $data['datasets'][1]['data'];

        $f = fopen('php://memory', 'r+');
        fputcsv($f, $headers);
        foreach($timestamps as $idx => $val) {
            if (! $col2)
                fputcsv($f, [$timestamps[$idx], $col1[$idx]]);
            else
                fputcsv($f, [$timestamps[$idx], $col1[$idx], $col2[$idx]]);
        }
        rewind($f);
        $csv_string = stream_get_contents($f);
        return $csv_string;
    }
    catch(Exception $e) {
        return false;
    }

}


function serve_csv($data, $request) {
    $response = new WP_REST_Response;

    $owner = $request['owner'];
    $repo = $request['repo'];
    $metric = $request['metric'];

    $filename = "{$owner}_{$repo}_{$metric}.csv";

    $csv_string = mk_csv($data, $metric);

    if ($csv_string) {
        // csv data exists, prepare response.
        //$csv_string = stream_get_contents($f);

        $response->set_data($csv_string);
        $response->set_headers([
            'Content-Type'   => "application/csv",
            'Content-Length' => strlen($csv_string),
            'Content-disposition' => "test.csv",
            'Content-disposition' => "filename={$filename}",
        ]);

        // Add filter here. This filter will return our csv file!
        add_filter('rest_pre_serve_request', 'csv_callback', 0, 2);
    } else {
        // Return a simple "not-found" JSON response.
        $response->set_data('not-found');
        $response->set_status(404);
    }

    return $response;
}

function csv_callback($served, $result) {
    $is_csv   = false;
    $csv_data = null;

    // Check the "Content-Type" header to confirm that we really want to return
    // a csv file.
    foreach ($result->get_headers() as $header => $value) {
        if ('content-type' === strtolower($header)) {
            $is_csv   = 0 === strpos( $value, 'application/csv' );
            $csv_data = $result->get_data();
            break;
        }
    }

    // Output the csv file and tell the REST server to not send any other
    // details (via "return true").
    if ($is_csv && is_string($csv_data)) {
        echo $csv_data;
        return true;
    }
    return $served;
}

