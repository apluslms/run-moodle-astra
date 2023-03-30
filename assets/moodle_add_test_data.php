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
require_once($moodleroot . '/mod/lti/locallib.php');

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
// Note: this does not work in newer MOOC-Grader versions (v1.12 and later)
// because Astra does not support the JWT authentication.
cli_writeln('Importing the course in Astra...');
$autosetuperrors = \mod_astra\autosetup\auto_setup::configure_content_from_url(
    $course->id, 1, 'http://grader:8080/default/aplus-json');
foreach ($autosetuperrors as $err) {
    cli_problem($err); // print error
}

// Add teacher, student and assistant user accounts.
function setup_add_user($username, $roleshortname, $course, $firstname, $lastname, $studentid, $password = null) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('username' => $username));
    if (!$user) {
        if (!$password) {
            $password = $username;
        }
        $userid = user_create_user((object) array(
            'username' => $username,
            'password' => $password,
            'email' => $username . '@localhost.invalid',
            'firstname' => $firstname,
            'lastname' => $lastname,
            'idnumber' => $studentid,
            'auth' => 'manual',
            'confirmed' => 1,
            'deleted' => 0,
            'mnethostid' => $CFG->mnet_localhost_id, // Always local user.
        ), true, false);
        $user = $DB->get_record('user', array('id' => $userid));
        if ($roleshortname && $course) {
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
    return $user->id;
}

cli_writeln('Creating user accounts...');
setup_add_user('teacher', 'editingteacher', $course, 'Terry', 'Teacher', '<teacher>');
setup_add_user('student', 'student', $course, 'Stacy', 'Student', '123456');
setup_add_user('assistant', 'teacher', $course, 'Andy', 'Assistant', '133701');
setup_add_user('newstudent', 'student', $course, 'Niles', 'New-Student', '666777');
$rootuserid = setup_add_user('root', null, null, 'Ruth', 'Robinson', '<admin>');

// Add the "root" user to the admin users.
$admins = array();
foreach (explode(',', $CFG->siteadmins) as $admin) {
    $admin = (int)$admin;
    if ($admin) {
        $admins[$admin] = $admin;
    }
}
$admins[$rootuserid] = $rootuserid;
set_config('siteadmins', implode(',', $admins));

// Create LTI preconfigured tool settings for using A+ LTI Tool v1.3.
$ltitype = new stdClass();
$ltitype->state = LTI_TOOL_STATE_CONFIGURED;
$lticonfig = new stdClass();
$lticonfig->lti_toolurl = 'http://plus:8000';
$lticonfig->lti_description = 'A+ LTI Tool v1.3';
$lticonfig->lti_typename = 'A+ LTI Tool v1.3';
$lticonfig->lti_ltiversion = LTI_VERSION_1P3;
$lticonfig->lti_clientid = 'abcdefghijklmn';
$lticonfig->lti_coursevisible = LTI_COURSEVISIBLE_PRECONFIGURED;
$lticonfig->lti_contentitem = 1; // true
$lticonfig->lti_toolurl_ContentItemSelectionRequest = 'http://plus:8000/lti/launch/';
$lticonfig->lti_keytype = LTI_RSA_KEY;
$lticonfig->lti_publickey = "-----BEGIN PUBLIC KEY-----\r
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1IHkKROjJmnxYYc3Nd5a\r
HaMgVPxkdnaBq2Wll90XxTazisbePCU0r7IrDea0t2F7KiZZClAHJrkRAvBuiZDl\r
UHILzTK4MEJ1PlgRpZIsu6ijhU4P5FEl46r7ZuIm85Wjs7LABwduAK9vWddxPUkr\r
ljx8y6kxmG0VGLW1RFgUOtTaJhv/tQHxrjV95pIXckzFRaqIU/brwNNVrTWgaM6P\r
UcP0wGFEwZUlgI+tSTlWkecVtaQauIUpIHGjHK1ryDly8QLuR0ipcypKZkKSeOrN\r
vVpKGeLRPgtlV1qhw8hYk28E9mc/tkVQq44d439OM1qniUw1WGwg4uI+J56lCami\r
3wIDAQAB\r
-----END PUBLIC KEY-----";
$lticonfig->lti_initiatelogin = 'http://plus:8000/lti/login/';
$lticonfig->lti_redirectionuris = "http://plus:8000/lti/login/\r
http://plus:8000/lti/launch/";
$lticonfig->lti_launchcontainer = LTI_LAUNCH_CONTAINER_WINDOW;
$lticonfig->lti_sendname = 1; // always
$lticonfig->lti_sendemailaddr = 1;
$lticonfig->lti_acceptgrades = 2; // Delegate to teacher
$lticonfig->lti_organizationid_default = LTI_DEFAULT_ORGID_SITEHOST;
//$lticonfig->lti_organizationid = '';
$lticonfig->lti_organizationurl = 'http://moodle:8050/';
// IMS LTI Assignment and Grades Services: Use this service for grade sync and column management
$lticonfig->ltiservice_gradesynchronization = 2;
$lticonfig->ltiservice_memberships = 0; // Do not use.
$lticonfig->ltiservice_toolsettings = 0;

lti_add_type($ltitype, $lticonfig);


cli_writeln('Test data setup finished.');

