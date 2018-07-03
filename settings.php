<?php
defined('MOODLE_INTERNAL') || die;


if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_my_courses_default_category',
        get_string('configtmycourses1', 'block_my_courses'),
        get_string('configtmycourses0', 'block_my_courses'), null));
}
