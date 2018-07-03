<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing HTML block instances.
 *
 * @package   block_html
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing HTML block instances.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_my_courses_edit_form extends block_edit_form
{
    protected function specific_definition($mform)
    {
        global $CFG;

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Visualização padrão;
        $mform->addElement('select', 'config_defaultView',
            get_string('configUserDefaulView', 'block_my_courses'),
            [
                0=>get_string('configOptionViewBlock', 'block_my_courses'),
                1=>get_string('configOptionViewList', 'block_my_courses')
            ]
        );
        $mform->setDefault('config_defaultView', 0);

        foreach ($this->enrol_get_my_courses("id,category,fullname", "category desc, fullname asc") as $t) {
            $registro[$this->root_category($t, 1)] = $this->root_category($t, 1);
        }
        asort($registro);
        $registro[''] =get_string('configOptionCategory', 'block_my_courses');
        $mform->addElement('select', 'config_defaultCategory', get_string('configUserDefaulCategory', 'block_my_courses'), $registro);
        $mform->setDefault('config_defaultCategory', [''=>get_string('configUserDefaulCategory', 'block_my_courses')]);

    }


    /**
     * @param $course
     * @param int $dep
     * @param int $r
     * @return mixed
     */
    protected function root_category($course, $dep = 1, $r = 0)
    {
        $arr = [':', ''];
        global $DB;
        $category = $DB->get_record('course_categories', array('id' => $course->category));
        $path = explode('/', $category->path);
        $root_category_id = (count($path) - $dep > 0) ? $path[count($path) - $dep] : end($path);
        $root_category = $DB->get_record('course_categories', array('id' => $root_category_id));
        $nome = str_replace($arr, "", $root_category->name);
        return !$r ? $nome : [$nome, $root_category_id];
    }

    /**
     * Overide Function
     * Returns list of courses current $USER is enrolled in and can access
     * - $fields is an array of field names to ADD
     *   so name the fields you really need, which will
     *   be added and uniq'd
     * @param string|array $fields
     * @param string $sort
     * @param int $limit max number of courses
     * @return array
     * @throws coding_exception
     */
    protected function enrol_get_my_courses($fields = NULL, $sort = 'visible DESC,sortorder ASC', $roleid = 0, $limit = 0)
    {
        global $DB, $USER;

        // Guest account does not have any courses
        if (isguestuser() or !isloggedin()) {
            return (array());
        }
        $basefields = array('id', 'category', 'sortorder',
            'shortname', 'fullname', 'idnumber',
            'startdate', 'visible',
            'groupmode', 'groupmodeforce', 'cacherev');
        if (empty($fields)) {
            $fields = $basefields;
        } else if (is_string($fields)) {
            // turn the fields from a string to an array
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
            $fields = array_unique(array_merge($basefields, $fields));
        } else if (is_array($fields)) {
            $fields = array_unique(array_merge($basefields, $fields));
        } else {
            throw new coding_exception('Invalid $fileds parameter in enrol_get_my_courses()');
        }
        if (in_array('*', $fields)) {
            $fields = array('*');
        }
        $orderby = "";
        $sort = trim($sort);
        if (!empty($sort)) {
            $rawsorts = explode(',', $sort);
            $sorts = array();
            foreach ($rawsorts as $rawsort) {
                $rawsort = trim($rawsort);
                if (strpos($rawsort, 'c.') === 0) {
                    $rawsort = substr($rawsort, 2);
                }
                $sorts[] = trim($rawsort);
            }
            $sort = 'c.' . implode(',c.', $sorts);
            $orderby = "ORDER BY $sort";
        }
        $wheres = array("c.id <> :siteid");
        $params = array('siteid' => SITEID);
        if (isset($USER->loginascontext) and $USER->loginascontext->contextlevel == CONTEXT_COURSE) {
            // list _only_ this course - anything else is asking for trouble...
            $wheres[] = "courseid = :loginas";
            $params['loginas'] = $USER->loginascontext->instanceid;
        }
        $coursefields = 'c.' . join(',c.', $fields);
        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel) ";
        if ($roleid) {
            $ccjoin .= "LEFT JOIN {role_assignments} ra on (ra.contextid =ctx.id  and ra.userid = :userid2 ) ";
            $params['userid2'] = $USER->id;
            $wheres[] = "ra.roleid = :roleid2";
            $params['roleid2'] = $roleid;
        }
        $params['contextlevel'] = CONTEXT_COURSE;
        $wheres = implode(" AND ", $wheres);
        $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                      
                      WHERE ue.status = :active AND e.status = :enabled AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
                   ) en ON (en.courseid = c.id)
           $ccjoin 
             WHERE $wheres
          $orderby";
        $params['userid'] = $USER->id;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = round(time(), -2); // improves db caching
        $params['now2'] = $params['now1'];


        $courses = $DB->get_records_sql($sql, $params, 0, $limit);

        // preload contexts and check visibility
        foreach ($courses as $id => $course) {
            context_helper::preload_from_record($course);
            if (!$course->visible) {
                if (!$context = context_course::instance($id, IGNORE_MISSING)) {
                    unset($courses[$id]);
                    continue;
                }
                if (!has_capability('moodle/course:viewhiddencourses', $context)) {
                    unset($courses[$id]);
                    continue;
                }
            }
            $courses[$id] = $course;
        }
        return $courses;
    }
}
