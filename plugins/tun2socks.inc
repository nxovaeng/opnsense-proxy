<?php

/*
 * tun2socks (hev-socks5-tunnel) service registration for OPNsense
 */

function tun2socks_services()
{
    $services = array();

    $services[] = array(
        'description' => gettext('tun2socks (hev-socks5-tunnel)'),
        'configd' => array(
            'restart' => array('tun2socks restart'),
            'start'   => array('tun2socks start'),
            'stop'    => array('tun2socks stop'),
        ),
        'name'    => 'hevsocks5tunnel',
        'pidfile' => '/var/run/hevsocks5tunnel.pid',
        'enabled' => true,
        'rcfile'  => '/usr/local/etc/rc.d/hevsocks5tunnel',
    );

    return $services;
}
