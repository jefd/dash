<?php

add_filter('wpmem_notify_addr', 'moderator_email');
function moderator_email( $email ) {
 
    // single email example
    $email = 'leah.dubots@noaa.gov';
     
    // multiple emails example
    // $email = 'notify1@mydomain.com, notify2@mydomain.com';
     
    // take the default and append a second address to it example:
    // $email = $email . ', notify2@mydomain.com';
     
    // return the result
    return $email;
}
