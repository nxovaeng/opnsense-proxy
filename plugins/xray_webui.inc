<?php

/*
 * xray-webui service registration for OPNsense
 */

function xray_webui_services()
{
    $services = array();

    $services[] = array(
        'description' => gettext('xray-webui'),
        'configd' => array(
            'restart' => array('xray-webui restart'),
            'start'   => array('xray-webui start'),
            'stop'    => array('xray-webui stop'),
        ),
        'name'    => 'xray_webui',
        'pidfile' => '/var/run/xray-webui.pid',
        'enabled' => true,
        'rcfile'  => '/usr/local/etc/rc.d/xray-webui',
    );

    return $services;
}
