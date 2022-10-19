<?php

require 'token.php';

$VERSION = 'v2.0.4';

add_shortcode( 'jira', 'jira_service_desk');
function jira_service_desk($atts) {
    global $VERSION;

    return <<<EOT
    <div id="jira-app"></div>
    <script src="https://unpkg.com/mithril/mithril.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/jefd/dash@{$VERSION}/wp-content/themes/hello-elementor-child/js/jira.js"></script>
    EOT;
}

add_action('rest_api_init', function () {
    register_rest_route( 'jira/v1', '/(?P<servicedesk>[\d]+)/(?P<requesttype>[\d]+)',array(
        'methods'  => 'POST',
        'callback' => 'service_desk'
    ));
});


function service_desk($request) {

    $servicedesk = $request['servicedesk'];
    $requesttype = $request['requesttype'];

    $body_params = $request->get_body_params();
    
    $file_params = $request->get_file_params();

    $data = ['body' => $body_params, 'file' => $file_params];

    $response = new WP_REST_Response($data);
    $response->set_status(200);

    return $response;
}
