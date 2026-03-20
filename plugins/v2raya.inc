<?php

/*
 * v2rayA + hev-socks5-tunnel service registration for OPNsense
 */

function v2raya_services()
{
    global $config;

    $services = array();

    $services[] = array(
        'description' => gettext('v2rayA'),
        'configd' => array(
            'restart' => array('v2raya restart'),
            'start' => array('v2raya start'),
            'stop' => array('v2raya stop'),
        ),
        'name' => 'v2raya',
        'pidfile' => '/var/run/v2raya.pid',
        'enabled' => true,
        'rcfile' => '/usr/local/etc/rc.d/v2raya',
    );

    $services[] = array(
        'description' => gettext('hev-socks5-tunnel'),
        'configd' => array(
            'restart' => array('hevsocks5tunnel restart'),
            'start' => array('hevsocks5tunnel start'),
            'stop' => array('hevsocks5tunnel stop'),
        ),
        'name' => 'hevsocks5tunnel',
        'pidfile' => '/var/run/hevsocks5tunnel.pid',
        'enabled' => true,
        'rcfile' => '/usr/local/etc/rc.d/hevsocks5tunnel',
    );

    return $services;
}
