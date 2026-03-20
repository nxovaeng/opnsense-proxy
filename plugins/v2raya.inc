<?php

/*
 * v2rayA service registration for OPNsense
 */

function v2raya_services()
{
    $services = array();

    $services[] = array(
        'description' => gettext('v2rayA'),
        'configd' => array(
            'restart' => array('v2raya restart'),
            'start'   => array('v2raya start'),
            'stop'    => array('v2raya stop'),
        ),
        'name'    => 'v2raya',
        'pidfile' => '/var/run/v2raya.pid',
        'enabled' => true,
        'rcfile'  => '/usr/local/etc/rc.d/v2raya',
    );

    return $services;
}
