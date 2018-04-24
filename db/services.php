<?php
$functions = array(
    'enrol_bank_get_instance_info' => array(
        'classname'   => 'enrol_bank_external',
        'methodname'  => 'get_instance_info',
        'classpath'   => 'enrol/bank/externallib.php',
        'description' => 'bank enrolment instance information.',
        'type'        => 'read',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),

    'enrol_bank_enrol_user' => array(
        'classname'   => 'enrol_bank_external',
        'methodname'  => 'enrol_user',
        'classpath'   => 'enrol/bank/externallib.php',
        'description' => 'bank enrol the current user in the given course.',
        'type'        => 'write',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    )
);
