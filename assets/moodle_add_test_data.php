<?php
/** This Moodle command-line script adds a course, imports the course with
 * the Astra plugin from the MOOC grader and creates test user accounts.
 */

define('CLI_SCRIPT', true);
$moodleroot = '/var/www/html';
require_once($moodleroot . '/config.php');
require_once($moodleroot . '/course/lib.php');
require_once($moodleroot . '/lib/clilib.php');
require_once($moodleroot . '/mod/astra/classes/autosetup/auto_setup.php');
require_once($moodleroot . '/user/lib.php');

// Log in as the admin user since some functions check user capabilities.
\core\session\manager::set_user(get_admin());

cli_heading('Adding test data to Moodle');

// Add course unless it already exists.
$course = $DB->get_record('course', array('shortname' => 'defcourse'));
if (!$course) {
    $data = (object) array(
        'category' => 1, // default course category
        'fullname' => 'Default course',
        'shortname' => 'defcourse',
        'idnumber' => 'defcourse',
        'visible' => 1,
        'format' => 'topics',
        'numsections' => 1,
        'lang' => '', // do not force any language
        'showgrades' => 1, // show gradebook to students
        'showreports' => 1, // show activity reports to students
        'enablecompletion' => 1, // allow completion progress tracking
        // the following settings are specific to the topics format
        'hiddensections' => 1, // hidden sections are invisible
        'coursedisplay' => 1, // show one section per page
    );
    cli_writeln('Creating a default Moodle course space...');
    $course = create_course($data);
    // default blocks for new courses are defined in config.php
}

// Astra: import the course from the MOOC grader
cli_writeln('Importing the course in Astra...');
$autosetuperrors = \mod_astra\autosetup\auto_setup::configure_content_from_url(
    $course->id, 1, 'http://grader:8080/default/aplus-json');
foreach ($autosetuperrors as $err) {
    cli_problem($err); // print error
}

// Add teacher, student and assistant user accounts.
function setup_add_user($username, $roleshortname, $course, $firstname, $lastname, $password = null) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('username' => $username));
    if (!$user) {
        if (!$password) {
            $password = $username;
        }
        $userid = user_create_user((object) array(
            'username' => $username,
            'password' => $password,
            'email' => $username . '@domain.local',
            'firstname' => $firstname,
            'lastname' => $lastname,
            'auth' => 'manual',
            'confirmed' => 1,
            'deleted' => 0,
            'mnethostid' => $CFG->mnet_localhost_id, // Always local user.
        ), true, false);
        $user = $DB->get_record('user', array('id' => $userid));
        // Enrol to the course.
        $enrolerrors = array();
        $roleid = $DB->get_field('role', 'id', array('shortname' => $roleshortname));
        \mod_astra\autosetup\auto_setup::enrolUsersToCourse(array($user),
            $course->id, $roleid, $enrolerrors);
        if (!empty($enrolerrors)) {
            foreach ($enrolerrors as $err) {
                cli_problem($err); // print error
            }
        }
    }
}

cli_writeln('Creating user accounts...');
setup_add_user('teacher', 'editingteacher', $course, 'Tom', 'Teacher');
setup_add_user('student', 'student', $course, 'Steve', 'Student');
setup_add_user('assistant', 'teacher', $course, 'Ann', 'Assistant');

cli_writeln('Test data setup finished.');

