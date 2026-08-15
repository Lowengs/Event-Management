<?php
/**
 * Central API Endpoint Router
 * All API calls across the project route through this single file.
 * Usage: /config/API/endpoints/index.php?action=<action_name>
 */

$routerScript = realpath(__FILE__) ?: __FILE__;
$currentScript = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$isDirectRouterRequest = ($currentScript !== '' && $currentScript === $routerScript);
if (!defined('IS_API_ENDPOINT')) {
    define('IS_API_ENDPOINT', $isDirectRouterRequest);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!$isDirectRouterRequest) {
    ob_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? '';

if (empty($action) && isset($_SERVER['PATH_INFO'])) {
    $action = trim($_SERVER['PATH_INFO'], '/');
}

// Whitelist Route Mapping to physical handlers in /config/API/
$routes = [
    // Admin
    'admin_login'                     => '../admin/POST/POSTlogin.php',
    'admin_logout'                    => '../admin/POST/POSTlogout.php',
    'get_admin_dashboard'             => '../admin/GET/GETdashboard.php',
    'get_admin_audit_trail'           => '../admin/GET/GETaudit_trail.php',
    'create_org'                      => '../osa/POST/POSTorganization.php',
    'get_admin_users'                 => '../admin/GET/GETusers.php',
    'update_user_status'              => '../admin/PUT/PUTuser_status.php',
    'delete_user'                     => '../admin/DELETE/DELETEuser.php',
    'reset_user_password'             => '../admin/POST/POSTreset_password.php',
    'admin_reset_password'            => '../admin/POST/POSTreset_password.php',
    'change_admin_password'           => '../admin/POST/POSTpassword.php',
    'update_admin_password'           => '../admin/POST/POSTpassword.php',

    // Common
    'gemini_ask'                      => '../common/POST/POSTgemini_ask.php',
    'gemini_chat'                     => '../common/POST/POSTgemini_ask.php',
    'get_face_descriptors'            => '../common/GET/GETface_descriptors.php',
    'face_recognition'                => '../common/POST/POSTface_recognition.php',

    // Organization
    'org_login'                       => '../organization/POST/POSTlogin.php',
    'org_logout'                      => '../organization/POST/POSTlogout.php',
    'get_org_dashboard'               => '../organization/GET/GETdashboard.php',
    'get_org_detail'                  => '../student/GET/GETorganization_detail.php',
    'get_org_events'                  => '../organization/GET/GETevents.php',
    'get_org_members'                 => '../organization/GET/GETmembers.php',
    'export_org_members'              => '../organization/GET/GETexport_members.php',
    'get_org_officers'                => '../organization/GET/GETofficers.php',
    'get_org_announcements'           => '../organization/GET/GETannouncements.php',
    'get_org_messages'                => '../organization/GET/GETmessages.php',
    'get_org_documents'               => '../organization/GET/GETdocuments.php',
    'get_org_reports'                 => '../organization/GET/GETreports.php',
    'get_attendance_log'              => '../organization/GET/GETattendance_log.php',
    'get_certificate_templates'       => '../organization/GET/GETcertificate_templates.php',
    'get_event_participants'          => '../organization/GET/GETevent_participants.php',
    'get_audit_trail'                 => '../organization/GET/GETaudit_trail.php',
    'get_org_settings'                => '../organization/GET/GETsettings.php',
    'update_org_settings'             => '../organization/POST/POSTsettings.php',
    'update_org_password'             => '../organization/POST/POSTpassword.php',
    'get_assessments'                 => '../organization/GET/GETassessments.php',
    'get_org_test_responses'          => '../organization/GET/GETtest_responses.php',
    'get_certificates'                => '../organization/GET/GETcertificates.php',
    'create_org_event'                => '../organization/POST/POSTevent.php',
    'update_org_event'                => '../organization/PUT/PUTevent.php',
    'delete_org_event'                => '../organization/DELETE/DELETEevent.php',
    'create_org_announcement'         => '../organization/POST/POSTannouncement.php',
    'update_org_announcement'         => '../organization/PUT/PUTannouncement.php',
    'delete_org_announcement'         => '../organization/DELETE/DELETEannouncement.php',
    'send_org_message'                => '../organization/POST/POSTmessage.php',
      'upload_org_document'             => '../organization/POST/POSTdocument.php',
      'upload_org_event_reports'         => '../organization/POST/POSTevent_reports.php',
      'set_org_event_no_finance'         => '../organization/POST/POSTevent_no_finance.php',
    'add_officer'                     => '../organization/POST/POSTofficer.php',
    'update_officer_role'             => '../organization/PUT/PUTofficer_role.php',
    'delete_officer'                  => '../organization/PUT/PUTofficer_role.php',
    'update_member_status'            => '../organization/PUT/PUTstudent_status.php',
    'delete_org_member'               => '../organization/DELETE/DELETEmember.php',
    'delete_attendance'               => '../organization/DELETE/DELETEattendance.php',
    'delete_certificate_template'     => '../organization/DELETE/DELETEcertificate_template.php',
    'save_certificate_template'       => '../organization/POST/POSTcertificate_template.php',
    'issue_certificates'               => '../organization/POST/POSTissue_certificates.php',
    'record_attendance'               => '../organization/POST/POSTattendance_record.php',
    'trigger_antispoofing'            => '../organization/POST/POSTtrigger_antispoofing.php',
    'trigger_presence_check'          => '../organization/POST/POSTtrigger_presence_check.php',
    'update_org_event_status'         => '../organization/PUT/PUTevent.php',

    // OSA
    'osa_login'                       => '../osa/POST/POSTlogin.php',
    'osa_logout'                      => '../osa/POST/POSTlogout.php',
    'get_osa_dashboard'               => '../osa/GET/GETdashboard.php',
    'get_osa_organizations'           => '../osa/GET/GETorganizations.php',
    'get_osa_students'                => '../osa/GET/GETstudents.php',
    'get_osa_events'                  => '../osa/GET/GETevents.php',
    'get_osa_reports'                 => '../osa/GET/GETreports.php',
    'get_osa_audit_trail'             => '../osa/GET/GETaudit_trail.php',
    'get_osa_messages'                => '../osa/GET/GETmessages.php',
    'get_osa_announcements'           => '../osa/GET/GETannouncements.php',
    'update_osa_settings'             => '../osa/POST/POSTsettings.php',
    'create_osa_announcement'         => '../osa/POST/POSTannouncement.php',
    'send_osa_message'                => '../osa/POST/POSTmessage.php',
    'update_osa_announcement_status'  => '../osa/PUT/PUTannouncement_status.php',
    'osa_update_announcement_status'  => '../osa/PUT/PUTannouncement_status.php',
    'update_osa_organization_status'  => '../osa/PUT/PUTorganization_status.php',
    'PUTorganization_status'         => '../osa/PUT/PUTorganization_status.php',
    'update_organization_status'      => '../osa/PUT/PUTorganization_status.php',
    'delete_osa_organization'         => '../osa/DELETE/DELETEorganization.php',
    'delete_osa_announcement'         => '../osa/DELETE/DELETEannouncement.php',
    'create_osa_organization'         => '../osa/POST/POSTorganization.php',
    'osa_forgot_password'             => '../osa/POST/POSTforgot_password.php',

    // Student
    'student_login'                   => '../student/POST/POSTlogin.php',
    'student_logout'                  => '../student/POST/POSTlogout.php',
    'student_register'                => '../student/POST/POSTregister.php',
    'student_forgot_password'         => '../student/POST/POSTforgot_password.php',
    'student_reset_password'          => '../student/POST/POSTreset_password.php',
    'student_verify_otp'              => '../student/POST/POSTverify_otp.php',
    'get_student_profile'             => '../student/GET/GETprofile.php',
    'get_student_events'              => '../student/GET/GETevents.php',
    'get_student_info'                => '../student/GET/GETinfo.php',
    'get_student_certificates'        => '../student/GET/GETcertificates.php',
    'get_student_announcements'       => '../student/GET/GETannouncements.php',
    'get_student_qr'                  => '../student/GET/GETstudent_qr.php',
    'get_student_test_results'        => '../student/GET/GETtest_results.php',
    'get_organization_detail'         => '../student/GET/GETorganization_detail.php',
    'get_student_organizations'       => '../student/GET/GETorganizations.php',
    'get_event_detail'                => '../student/GET/GETevent_detail.php',
    'get_attendance_status'           => '../student/GET/GETattendance_status.php',
    'get_verification_notice'         => '../student/GET/GETverification_notice.php',
    'complete_verification'           => '../student/POST/POSTcomplete_verification.php',
    'get_assessment_questions'        => '../student/GET/GETassessment_questions.php',
    'student_record_attendance'       => '../student/POST/POSTattendance.php',
    'event_register'                  => '../student/POST/POSTevent_register.php',
    'register_event'                  => '../student/POST/POSTevent_register.php',
    'cancel_registration'             => '../student/DELETE/DELETEregistration.php',
    'delete_registration'             => '../student/DELETE/DELETEregistration.php',
    'submit_pretest'                  => '../student/POST/POSTpretest.php',
    'submit_posttest'                 => '../student/POST/POSTposttest.php',
    'submit_test'                     => '../student/POST/POSTsubmit_test.php',
    'get_index'                       => '../common/GET/GETindex.php',
    'get_student_profile_dashboard' => '../student/GET/GETprofile_dashboard.php',
    'get_student_registrations'      => '../student/GET/GETregistrations.php',
    'update_student_profile'          => '../student/POST/POSTupdate_profile.php',
    'update_student_password'         => '../student/PUT/PUTpassword.php',
    'delete_student_attendance'       => '../student/DELETE/DELETEattendance.php',
    'validate_cor'                    => '../student/POST/POSTvalidate_cor.php',
    'ai_analyze_cor'                  => '../student/POST/POSTai_analyze_cor.php'
];

if (empty($action) || !isset($routes[$action])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint action not found',
        'requested_action' => $action
    ]);
    exit;
}

$target_file = __DIR__ . '/' . $routes[$action];

if (!file_exists($target_file)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Target handler file missing',
        'target' => $routes[$action]
    ]);
    exit;
}

require_once $target_file;

if (!$isDirectRouterRequest) {
    $captured = ob_get_clean();
    echo $captured;
}
