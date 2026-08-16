<?php
/**
 * Create/Update All System Stored Procedures
 */
require_once __DIR__ . '/db.php';

$sps = [];

function dropAndCreate($conn, $name, $body) {
    global $sps;
    $conn->query("DROP PROCEDURE IF EXISTS `$name`");
    if ($conn->query($body)) {
        $sps[] = "[OK] Stored Procedure `$name` created/updated successfully";
    } else {
        $sps[] = "[FAIL] Failed to create `$name`: " . $conn->error;
    }
}

// 1. sp_AdminLogin
dropAndCreate($conn, 'sp_AdminLogin', "
CREATE PROCEDURE sp_AdminLogin(IN p_Email VARCHAR(255))
BEGIN
    SELECT AdminId, Name, Email, PasswordHash, Role, Status
    FROM `admin`
    WHERE LOWER(Email) = LOWER(p_Email)
    LIMIT 1;
END
");

// 2. sp_GetStudentInfo
dropAndCreate($conn, 'sp_GetStudentInfo', "
CREATE PROCEDURE sp_GetStudentInfo(IN p_StudentId VARCHAR(100), IN p_EventId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id, 
           u.course, u.year_level, u.section, u.profile_photo, u.phone,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = p_EventId AND er.UserId = u.UserId), 0) AS is_registered,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId AND a.LogType = 'Log In'), 0) AS has_logged_in,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId AND a.LogType = 'Log Out'), 0) AS has_logged_out,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId), 0) AS has_attended
    FROM `user` u 
    WHERE u.student_id = p_StudentId 
       OR u.UserId = CAST(p_StudentId AS UNSIGNED)
       OR u.Email = p_StudentId
    LIMIT 1;
END
");

// 3. sp_GetOrgMembers
dropAndCreate($conn, 'sp_GetOrgMembers', "
CREATE PROCEDURE sp_GetOrgMembers(IN p_OrgId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.student_id, u.Email, 
           u.course, u.year_level, u.section, u.phone, u.profile_photo, 
           u.cor_document, u.cor_document AS CorDocumentUrl,
           u.Position, u.officer_role, u.is_officer, u.status,
           u.verification_status, u.created_at
    FROM `user` u 
    WHERE u.OrgId = p_OrgId 
    ORDER BY u.first_name ASC;
END
");

// 4. sp_GetOrgOfficers
dropAndCreate($conn, 'sp_GetOrgOfficers', "
CREATE PROCEDURE sp_GetOrgOfficers(IN p_OrgId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id,
           u.course, u.year_level, u.section, u.phone,
           COALESCE(u.officer_role, u.Position, 'Officer') AS officer_role,
           u.profile_photo, u.is_officer
    FROM `user` u 
    WHERE u.OrgId = p_OrgId AND (u.is_officer = 1 OR u.officer_role IS NOT NULL OR u.Position IS NOT NULL)
    ORDER BY u.first_name ASC;
END
");

// 5. sp_GetOrgAnnouncements
dropAndCreate($conn, 'sp_GetOrgAnnouncements', "
CREATE PROCEDURE sp_GetOrgAnnouncements(IN p_OrgId INT)
BEGIN
    SELECT a.*, o.OrgName
    FROM announcement a
    LEFT JOIN organization o ON o.OrgId = a.OrgId
    WHERE a.OrgId = p_OrgId
    ORDER BY a.CreatedAt DESC;
END
");

// 6. sp_GetOrgEvents
dropAndCreate($conn, 'sp_GetOrgEvents', "
CREATE PROCEDURE sp_GetOrgEvents(IN p_OrgId INT)
BEGIN
    SELECT e.*, o.OrgName,
           (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count
    FROM event e
    LEFT JOIN organization o ON e.OrgId = o.OrgId
    WHERE e.OrgId = p_OrgId
    ORDER BY e.EventDateTime DESC;
END
");

// 7. sp_GetOrgAssessments
dropAndCreate($conn, 'sp_GetOrgAssessments', "
CREATE PROCEDURE sp_GetOrgAssessments(IN p_OrgId INT)
BEGIN
    SELECT a.*, e.EventName, e.EventDateTime
    FROM assessments a
    LEFT JOIN event e ON e.EventId = a.event_id
    WHERE a.created_by = p_OrgId OR e.OrgId = p_OrgId
    ORDER BY a.assessment_id DESC;
END
");

// 8. sp_GetOrgDocuments
dropAndCreate($conn, 'sp_GetOrgDocuments', "
CREATE PROCEDURE sp_GetOrgDocuments(IN p_OrgId INT)
BEGIN
    SELECT d.DocId, d.OrgId, d.EventId, d.Title, d.DocType, d.Description, 
           d.FilePath, d.FileSize, d.UploadedAt,
           e.EventName
    FROM org_documents d
    LEFT JOIN event e ON e.EventId = d.EventId
    WHERE d.OrgId = p_OrgId
    ORDER BY d.UploadedAt DESC;
END
");

// 9. sp_GetOrgMessages
dropAndCreate($conn, 'sp_GetOrgMessages', "
CREATE PROCEDURE sp_GetOrgMessages(IN p_OrgId INT)
BEGIN
    SELECT MessageId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt
    FROM org_messages
    WHERE OrgId = p_OrgId
    ORDER BY SentAt ASC;
END
");

// 10. sp_GetOSAStudents
dropAndCreate($conn, 'sp_GetOSAStudents', "
CREATE PROCEDURE sp_GetOSAStudents()
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id,
           u.course, u.year_level, u.section, u.status, u.created_at,
           o.OrgName
    FROM `user` u
    LEFT JOIN organization o ON o.OrgId = u.OrgId
    ORDER BY u.UserId DESC;
END
");

// 11. sp_GetOSADashboard
dropAndCreate($conn, 'sp_GetOSADashboard', "
CREATE PROCEDURE sp_GetOSADashboard()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM `user`) AS total_students,
        (SELECT COUNT(*) FROM organization WHERE LOWER(COALESCE(Status, 'active')) = 'active') AS active_orgs,
        (SELECT COUNT(*) FROM organization) AS total_orgs,
        (SELECT COUNT(*) FROM event WHERE LOWER(COALESCE(EventStatus, 'scheduled')) IN ('scheduled','ongoing')) AS upcoming_events,
        (SELECT COUNT(*) FROM certificates) AS total_certs,
        (SELECT COUNT(*) FROM org_messages WHERE SenderType = 'org' AND IsRead = 0) AS unread_count;
END
");

// 11b. sp_GetOSAMessages
dropAndCreate($conn, 'sp_GetOSAMessages', "
CREATE PROCEDURE sp_GetOSAMessages(IN p_OrgId INT)
BEGIN
    IF p_OrgId > 0 THEN
        SELECT MessageId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt
        FROM org_messages
        WHERE OrgId = p_OrgId
        ORDER BY SentAt ASC;
    ELSE
        SELECT MessageId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt
        FROM org_messages
        ORDER BY SentAt DESC;
    END IF;
END
");

// 12. sp_RecordAttendance
dropAndCreate($conn, 'sp_RecordAttendance', "
CREATE PROCEDURE sp_RecordAttendance(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Method VARCHAR(50),
    IN p_Status VARCHAR(50),
    IN p_LogType VARCHAR(20)
)
BEGIN
    INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType)
    VALUES (p_EventId, p_UserId, p_Method, p_Status, NOW(), p_LogType);
END
");

// 13. sp_UpdateEvent
dropAndCreate($conn, 'sp_UpdateEvent', "
CREATE PROCEDURE sp_UpdateEvent(
    IN p_EventId INT,
    IN p_OrgId INT,
    IN p_EventName VARCHAR(255),
    IN p_EventDescription TEXT,
    IN p_EventDateTime DATETIME,
    IN p_EventLocation VARCHAR(255),
    IN p_EventMode VARCHAR(50),
    IN p_EventCapacity INT,
    IN p_EventSpeaker VARCHAR(255)
)
BEGIN
    UPDATE event 
    SET EventName = p_EventName,
        EventDescription = p_EventDescription,
        EventDateTime = p_EventDateTime,
        EventLocation = p_EventLocation,
        EventMode = p_EventMode,
        EventCapacity = p_EventCapacity,
        EventSpeaker = p_EventSpeaker
    WHERE EventId = p_EventId AND OrgId = p_OrgId;
END
");

// 14. sp_UpdateOfficerRole
dropAndCreate($conn, 'sp_UpdateOfficerRole', "
CREATE PROCEDURE sp_UpdateOfficerRole(
    IN p_UserId INT,
    IN p_OrgId INT,
    IN p_OfficerRole VARCHAR(100)
)
BEGIN
    IF p_OfficerRole IS NULL OR p_OfficerRole = '' THEN
        UPDATE `user` SET officer_role = NULL, Position = NULL, is_officer = 0 WHERE UserId = p_UserId AND OrgId = p_OrgId;
    ELSE
        UPDATE `user` SET officer_role = p_OfficerRole, Position = p_OfficerRole, is_officer = 1, OrgId = p_OrgId WHERE UserId = p_UserId;
    END IF;
END
");

// 15. sp_AddOfficer
dropAndCreate($conn, 'sp_AddOfficer', "
CREATE PROCEDURE sp_AddOfficer(
    IN p_OrgId INT,
    IN p_FirstName VARCHAR(100),
    IN p_LastName VARCHAR(100),
    IN p_StudentId VARCHAR(100),
    IN p_Email VARCHAR(255),
    IN p_YearLevel VARCHAR(50),
    IN p_Role VARCHAR(100),
    IN p_DefaultPass VARCHAR(255)
)
BEGIN
    DECLARE v_ExistingId INT DEFAULT 0;
    SELECT UserId INTO v_ExistingId FROM `user` WHERE LOWER(Email) = LOWER(p_Email) LIMIT 1;
    
    IF v_ExistingId > 0 THEN
        UPDATE `user` SET OrgId = p_OrgId, officer_role = p_Role, Position = p_Role, is_officer = 1, year_level = p_YearLevel WHERE UserId = v_ExistingId;
    ELSE
        INSERT INTO `user` (first_name, last_name, student_id, Email, PasswordHash, OrgId, officer_role, Position, is_officer, year_level, Status, created_at)
        VALUES (p_FirstName, p_LastName, p_StudentId, p_Email, p_DefaultPass, p_OrgId, p_Role, p_Role, 1, p_YearLevel, 'active', NOW());
    END IF;
END
");

// 16. sp_GetStudentCertificates
dropAndCreate($conn, 'sp_GetStudentCertificates', "
CREATE PROCEDURE sp_GetStudentCertificates(IN p_UserId INT)
BEGIN
    SELECT c.*, e.EventName, e.EventDateTime, o.OrgName
    FROM certificates c
    LEFT JOIN event e ON e.EventId = c.EventId
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE c.UserId = p_UserId
    ORDER BY c.IssuedAt DESC;
END
");

// 17. sp_GetTestResponses
dropAndCreate($conn, 'sp_GetTestResponses', "
CREATE PROCEDURE sp_GetTestResponses(IN p_EventId INT, IN p_TestType VARCHAR(50))
BEGIN
    IF LOWER(p_TestType) LIKE '%pre%' THEN
        SELECT r.Score, r.SubmittedAt, u.first_name, u.last_name, u.Email,
               r.tab_switches, r.engagement_score, r.monitoring_flagged
        FROM event_pretest r 
        JOIN user u ON r.UserId = u.UserId 
        WHERE r.EventId = p_EventId 
        ORDER BY r.Score DESC, r.SubmittedAt ASC;
    ELSE
        SELECT r.Score, r.SubmittedAt, u.first_name, u.last_name, u.Email,
               r.tab_switches, r.engagement_score, r.monitoring_flagged
        FROM event_posttest r 
        JOIN user u ON r.UserId = u.UserId 
        WHERE r.EventId = p_EventId 
        ORDER BY r.Score DESC, r.SubmittedAt ASC;
    END IF;
END
");

// 18. sp_GetAssessmentQuestions
dropAndCreate($conn, 'sp_GetAssessmentQuestions', "
CREATE PROCEDURE sp_GetAssessmentQuestions(IN p_AssessmentId INT)
BEGIN
    SELECT 
        q.question_id, 
        q.question_text, 
        q.correct_answer,
        COUNT(sqr.id) AS total_answered,
        SUM(sqr.is_correct) AS total_correct
    FROM assessment_questions q
    LEFT JOIN student_question_responses sqr ON q.question_id = sqr.question_id
    WHERE q.assessment_id = p_AssessmentId
    GROUP BY q.question_id
    ORDER BY q.question_id ASC;
END
");

// 19. sp_GetOSAOrganizations
dropAndCreate($conn, 'sp_GetOSAOrganizations', "
CREATE PROCEDURE sp_GetOSAOrganizations()
BEGIN
    SELECT o.*,
           (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId) AS members_count,
           (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId AND u.officer_role IS NOT NULL AND u.officer_role != '') AS officers_count,
           (SELECT CONCAT(u.first_name,' ',u.last_name) FROM user u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%president%' AND LOWER(u.officer_role) NOT LIKE '%vice%' LIMIT 1) AS president_name,
           (SELECT CONCAT(u.first_name,' ',u.last_name) FROM user u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%vice%president%' LIMIT 1) AS vp_name,
           (SELECT GROUP_CONCAT(t.Type SEPARATOR ', ') FROM orgtype t WHERE t.OrgId = o.OrgId) AS org_type
    FROM organization o
    ORDER BY o.OrgName ASC;
END
");

// 20. sp_GetStudentAnnouncements
dropAndCreate($conn, 'sp_GetStudentAnnouncements', "
CREATE PROCEDURE sp_GetStudentAnnouncements()
BEGIN
    SELECT a.*, COALESCE(o.OrgName, 'NAAP OSA') AS OrgName
    FROM announcement a
    LEFT JOIN organization o ON a.OrgId = o.OrgId
    WHERE LOWER(TRIM(COALESCE(a.Status, 'approved'))) = 'approved'
    ORDER BY COALESCE(a.DatePosted, a.CreatedAt) DESC;
END
");

// 21. sp_GetStudentProfile
dropAndCreate($conn, 'sp_GetStudentProfile', "
CREATE PROCEDURE sp_GetStudentProfile(IN p_UserId INT)
BEGIN
    SELECT u.*, o.OrgName
    FROM `user` u
    LEFT JOIN organization o ON o.OrgId = u.OrgId
    WHERE u.UserId = p_UserId
    LIMIT 1;
END
");

// 22. sp_UpdateStudentProfile
dropAndCreate($conn, 'sp_UpdateStudentProfile', "
CREATE PROCEDURE sp_UpdateStudentProfile(
    IN p_UserId INT,
    IN p_FirstName VARCHAR(100),
    IN p_LastName VARCHAR(100),
    IN p_MiddleName VARCHAR(100),
    IN p_Phone VARCHAR(50),
    IN p_Address TEXT,
    IN p_ProfilePhoto VARCHAR(255)
)
BEGIN
    UPDATE `user`
    SET first_name = p_FirstName,
        last_name = p_LastName,
        middle_name = p_MiddleName,
        phone = p_Phone,
        Address = p_Address,
        profile_photo = COALESCE(NULLIF(p_ProfilePhoto, ''), profile_photo)
    WHERE UserId = p_UserId;
END
");

// 23. sp_GetAdminDashboard
dropAndCreate($conn, 'sp_GetAdminDashboard', "
CREATE PROCEDURE sp_GetAdminDashboard()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM `user`) AS total_students,
        (SELECT COUNT(*) FROM `osa`) AS total_osa,
        (SELECT COUNT(*) FROM `organization`) AS total_orgs,
        (SELECT COUNT(*) FROM `admin`) AS total_admins,
        (SELECT COUNT(*) FROM `auditlog` WHERE DATE(`Date`) = CURDATE()) AS today_logs,
        (SELECT COUNT(*) FROM `event`) AS total_events;
END
");

// 24. sp_GetOrgDashboard
dropAndCreate($conn, 'sp_GetOrgDashboard', "
CREATE PROCEDURE sp_GetOrgDashboard(IN p_OrgId INT)
BEGIN
    SELECT
        (SELECT COUNT(*) FROM `user` WHERE OrgId = p_OrgId) AS total_members,
        (SELECT COUNT(*) FROM event WHERE OrgId = p_OrgId AND EventDateTime >= NOW()) AS upcoming_events,
        (SELECT COUNT(*) FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = p_OrgId) AS total_attendance,
        (SELECT COUNT(*) FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = p_OrgId) AS pending_approvals;
END
");

// 25. sp_GetOrgSettings
dropAndCreate($conn, 'sp_GetOrgSettings', "
CREATE PROCEDURE sp_GetOrgSettings(IN p_OrgId INT)
BEGIN
    SELECT OrgId, OrgName, Description, OrgPicture, OrgBanner, username AS Username, email AS Email, Status, Adviser, DateRegistered
    FROM organization
    WHERE OrgId = p_OrgId
    LIMIT 1;
END
");

// 26. sp_GetOrgCertificates
dropAndCreate($conn, 'sp_GetOrgCertificates', "
CREATE PROCEDURE sp_GetOrgCertificates(IN p_OrgId INT)
BEGIN
    SELECT c.CertId, c.CertCode, c.IssuedAt, c.GeneratedImage,
           e.EventName, e.EventDateTime,
           u.first_name, u.last_name, u.student_id, u.profile_photo,
           t.TemplateName, t.TemplateImage
    FROM certificates c
    JOIN event e ON e.EventId = c.EventId
    JOIN user u ON u.UserId = c.UserId
    JOIN certificate_templates t ON t.TemplateId = c.TemplateId
    WHERE c.OrgId = p_OrgId
    ORDER BY c.IssuedAt DESC;
END
");

// 27. sp_GetOrgAuditTrail
dropAndCreate($conn, 'sp_GetOrgAuditTrail', "
CREATE PROCEDURE sp_GetOrgAuditTrail(IN p_OrgId INT)
BEGIN
    SELECT *
    FROM auditlog
    WHERE (ActorType='org' OR ActorType='organization') AND (ActorId = p_OrgId OR UserId = p_OrgId)
    ORDER BY Date DESC
    LIMIT 500;
END
");

// 28. sp_GetStudentEvents
dropAndCreate($conn, 'sp_GetStudentEvents', "
CREATE PROCEDURE sp_GetStudentEvents()
BEGIN
    SELECT e.*, o.OrgName,
           (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count,
           (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS reg_count
    FROM event e
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    ORDER BY e.EventDateTime DESC;
END
");

// 29. sp_GetEventDetail
dropAndCreate($conn, 'sp_GetEventDetail', "
CREATE PROCEDURE sp_GetEventDetail(IN p_EventId INT)
BEGIN
    SELECT e.*, o.OrgName, o.OrgLogo
    FROM event e
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE e.EventId = p_EventId
    LIMIT 1;
END
");

// 30. sp_GetTestResults
dropAndCreate($conn, 'sp_GetTestResults', "
CREATE PROCEDURE sp_GetTestResults(IN p_EventId INT, IN p_UserId INT)
BEGIN
    SELECT 
        (SELECT Score FROM event_pretest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS pretest_score,
        (SELECT SubmittedAt FROM event_pretest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS pretest_time,
        (SELECT Score FROM event_posttest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS posttest_score,
        (SELECT SubmittedAt FROM event_posttest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS posttest_time;
END
");

// 31. sp_StudentRegister
dropAndCreate($conn, 'sp_StudentRegister', "
CREATE PROCEDURE sp_StudentRegister(
    IN p_FirstName VARCHAR(100),
    IN p_MiddleName VARCHAR(100),
    IN p_LastName VARCHAR(100),
    IN p_StudentId VARCHAR(100),
    IN p_Address TEXT,
    IN p_Email VARCHAR(255),
    IN p_Course VARCHAR(100),
    IN p_YearLevel VARCHAR(50),
    IN p_Section VARCHAR(50),
    IN p_Username VARCHAR(100),
    IN p_PassHash VARCHAR(255),
    IN p_Phone VARCHAR(50),
    IN p_ProfilePath VARCHAR(255),
    IN p_CorPath VARCHAR(255)
)
BEGIN
    INSERT INTO `user` (first_name, middle_name, last_name, student_id, Address, Email, course, year_level, section, username, PasswordHash, phone, profile_photo, cor_document, Status, verification_status, Role, created_at)
    VALUES (p_FirstName, p_MiddleName, p_LastName, p_StudentId, p_Address, p_Email, p_Course, p_YearLevel, p_Section, p_Username, p_PassHash, p_Phone, p_ProfilePath, p_CorPath, 'active', 'ai_verified', 'student', NOW());
    SELECT LAST_INSERT_ID() AS new_user_id;
END
");

// 32. sp_SaveStudentQuestionResponse
dropAndCreate($conn, 'sp_SaveStudentQuestionResponse', "
CREATE PROCEDURE sp_SaveStudentQuestionResponse(
    IN p_AssessmentId INT,
    IN p_StudentId INT,
    IN p_QuestionId INT,
    IN p_IsCorrect INT,
    IN p_GivenAnswer VARCHAR(255)
)
BEGIN
    DELETE FROM student_question_responses WHERE assessment_id = p_AssessmentId AND student_id = p_StudentId AND question_id = p_QuestionId;
    INSERT INTO student_question_responses (assessment_id, student_id, question_id, is_correct, given_answer)
    VALUES (p_AssessmentId, p_StudentId, p_QuestionId, p_IsCorrect, p_GivenAnswer);
END
");

// 33. sp_UpdateStudentPassword
dropAndCreate($conn, 'sp_UpdateStudentPassword', "
CREATE PROCEDURE sp_UpdateStudentPassword(
    IN p_UserId INT,
    IN p_Email VARCHAR(255),
    IN p_PassHash VARCHAR(255)
)
BEGIN
    IF p_UserId > 0 THEN
        UPDATE `user` SET PasswordHash = p_PassHash WHERE UserId = p_UserId;
    ELSE
        UPDATE `user` SET PasswordHash = p_PassHash WHERE LOWER(Email) = LOWER(p_Email);
    END IF;
END
");

// 34. sp_UpdateUserStatus
dropAndCreate($conn, 'sp_UpdateUserStatus', "
CREATE PROCEDURE sp_UpdateUserStatus(
    IN p_UserId INT,
    IN p_Status VARCHAR(50)
)
BEGIN
    UPDATE `user` SET status = p_Status WHERE UserId = p_UserId;
END
");

// 35. sp_UpdateOrgStudentStatus
dropAndCreate($conn, 'sp_UpdateOrgStudentStatus', "
CREATE PROCEDURE sp_UpdateOrgStudentStatus(
    IN p_UserId INT,
    IN p_OrgId INT,
    IN p_Status VARCHAR(50),
    IN p_VerificationStatus VARCHAR(50)
)
BEGIN
    UPDATE `user` SET status = p_Status, verification_status = p_VerificationStatus WHERE UserId = p_UserId AND OrgId = p_OrgId;
END
");

// 36. sp_UpdateAnnouncement
dropAndCreate($conn, 'sp_UpdateAnnouncement', "
CREATE PROCEDURE sp_UpdateAnnouncement(
    IN p_AnnouncementId INT,
    IN p_Title VARCHAR(255),
    IN p_Body TEXT,
    IN p_Status VARCHAR(50)
)
BEGIN
    UPDATE announcement SET Title = p_Title, Body = p_Body, Status = p_Status WHERE AnnouncementId = p_AnnouncementId;
END
");

// 37. sp_UpdateAnnouncementStatus
dropAndCreate($conn, 'sp_UpdateAnnouncementStatus', "
CREATE PROCEDURE sp_UpdateAnnouncementStatus(
    IN p_AnnouncementId INT,
    IN p_Status VARCHAR(50)
)
BEGIN
    UPDATE announcement SET Status = p_Status WHERE AnnouncementId = p_AnnouncementId;
END
");

// 38. sp_UpdateOSASettings
dropAndCreate($conn, 'sp_UpdateOSASettings', "
CREATE PROCEDURE sp_UpdateOSASettings(
    IN p_OsaId INT,
    IN p_Name VARCHAR(255),
    IN p_Email VARCHAR(255),
    IN p_PassHash VARCHAR(255)
)
BEGIN
    IF p_PassHash IS NOT NULL AND p_PassHash != '' THEN
        UPDATE `osa` SET Name = p_Name, Email = p_Email, PasswordHash = p_PassHash WHERE OsaId = p_OsaId;
    ELSE
        UPDATE `osa` SET Name = p_Name, Email = p_Email WHERE OsaId = p_OsaId;
    END IF;
END
");

// 39. sp_DeleteAttendance
dropAndCreate($conn, 'sp_DeleteAttendance', "
CREATE PROCEDURE sp_DeleteAttendance(
    IN p_EventId INT,
    IN p_UserId INT
)
BEGIN
    DELETE FROM attendance WHERE EventId = p_EventId AND UserId = p_UserId;
END
");

// 40. sp_GetAssessmentQuestionsDetail
dropAndCreate($conn, 'sp_GetAssessmentQuestionsDetail', "
CREATE PROCEDURE sp_GetAssessmentQuestionsDetail(IN p_AssessmentId INT)
BEGIN
    SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points
    FROM assessment_questions
    WHERE assessment_id = p_AssessmentId
    ORDER BY question_id ASC;
END
");

// 41. sp_OrgLogin
dropAndCreate($conn, 'sp_OrgLogin', "
CREATE PROCEDURE sp_OrgLogin(IN p_Username VARCHAR(255))
BEGIN
    SELECT OrgId, OrgName, username, PasswordHash
    FROM organization
    WHERE LOWER(username) = LOWER(p_Username) OR LOWER(email) = LOWER(p_Username)
    LIMIT 1;
END
");

// 42. sp_GetActiveOrganizations
dropAndCreate($conn, 'sp_GetActiveOrganizations', "
CREATE PROCEDURE sp_GetActiveOrganizations()
BEGIN
    SELECT o.*,
           (SELECT COUNT(*) FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = o.OrgId) AS member_count,
           (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId AND LOWER(e.EventStatus) = 'scheduled') AS event_count
    FROM organization o
    WHERE LOWER(o.Status) = 'active' OR o.Status IS NULL
    ORDER BY o.OrgName ASC;
END
");

// 43. sp_GetStudentProfileDashboard
dropAndCreate($conn, 'sp_GetStudentProfileDashboard', "
CREATE PROCEDURE sp_GetStudentProfileDashboard(IN p_UserId INT)
BEGIN
    SELECT
        (SELECT COUNT(DISTINCT EventId) FROM eventregistration WHERE UserId = p_UserId) AS reg_events_count,
        (SELECT COUNT(DISTINCT EventId) FROM attendance WHERE UserId = p_UserId) AS attended_events_count,
        (SELECT COUNT(*) FROM certificates WHERE UserId = p_UserId) AS certs_count;
END
");

// 44. sp_GetOSAEvents
dropAndCreate($conn, 'sp_GetOSAEvents', "
CREATE PROCEDURE sp_GetOSAEvents()
BEGIN
    SELECT e.*, o.OrgName
    FROM event e
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    ORDER BY e.EventDateTime DESC;
END
");

// 45. sp_GetOSAReports
dropAndCreate($conn, 'sp_GetOSAReports', "
CREATE PROCEDURE sp_GetOSAReports()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM event WHERE EventStatus = 'Scheduled') AS scheduled_events,
        (SELECT COUNT(*) FROM event WHERE EventStatus = 'Ongoing') AS ongoing_events,
        (SELECT COUNT(*) FROM event WHERE EventStatus = 'Completed') AS completed_events,
        (SELECT COUNT(*) FROM event WHERE EventStatus IN ('Cancelled','Delayed')) AS cancelled_events;
END
");

// 46. sp_GetOSAAuditTrail
dropAndCreate($conn, 'sp_GetOSAAuditTrail', "
CREATE PROCEDURE sp_GetOSAAuditTrail()
BEGIN
    SELECT *
    FROM auditlog
    WHERE ActorType = 'osa' OR ActorType = 'admin'
    ORDER BY Date DESC
    LIMIT 500;
END
");

// 47. sp_GetOSAMessages
dropAndCreate($conn, 'sp_GetOSAMessages', "
CREATE PROCEDURE sp_GetOSAMessages(IN p_OrgId INT)
BEGIN
    IF p_OrgId > 0 THEN
        SELECT * FROM org_messages WHERE OrgId = p_OrgId ORDER BY SentAt ASC;
    ELSE
        SELECT * FROM org_messages ORDER BY SentAt DESC LIMIT 200;
    END IF;
END
");

// 48. sp_GetOrgReports
dropAndCreate($conn, 'sp_GetOrgReports', "
CREATE PROCEDURE sp_GetOrgReports(IN p_OrgId INT)
BEGIN
    SELECT
        (SELECT COUNT(*) FROM event WHERE OrgId = p_OrgId) AS total_events,
        (SELECT COUNT(*) FROM `user` WHERE OrgId = p_OrgId) AS total_members,
        (SELECT COUNT(*) FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = p_OrgId) AS total_attendance;
END
");

// 49. sp_DeleteOrgEvent
dropAndCreate($conn, 'sp_DeleteOrgEvent', "
CREATE PROCEDURE sp_DeleteOrgEvent(IN p_EventId INT, IN p_OrgId INT)
BEGIN
    DELETE FROM attendance WHERE EventId = p_EventId;
    DELETE FROM eventregistration WHERE EventId = p_EventId;
    DELETE FROM certificates WHERE EventId = p_EventId;
    DELETE FROM event WHERE EventId = p_EventId AND OrgId = p_OrgId;
END
");

// 50. sp_OSALogin
dropAndCreate($conn, 'sp_OSALogin', "
CREATE PROCEDURE sp_OSALogin(IN p_Email VARCHAR(255))
BEGIN
    SELECT OsaId, Name, Email, PasswordHash
    FROM `osa`
    WHERE LOWER(Email) = LOWER(p_Email) OR LOWER(Name) = LOWER(p_Email)
    LIMIT 1;
END
");

// 51. sp_StudentLogin
dropAndCreate($conn, 'sp_StudentLogin', "
CREATE PROCEDURE sp_StudentLogin(IN p_Email VARCHAR(255))
BEGIN
    SELECT UserId, first_name, last_name, Email, student_id, username, PasswordHash, Status
    FROM `user`
    WHERE LOWER(Email) = LOWER(p_Email) OR LOWER(student_id) = LOWER(p_Email) OR LOWER(username) = LOWER(p_Email)
    LIMIT 1;
END
");

// 52. sp_RegisterStudentEvent
dropAndCreate($conn, 'sp_RegisterStudentEvent', "
CREATE PROCEDURE sp_RegisterStudentEvent(IN p_EventId INT, IN p_StudentId INT)
BEGIN
    DECLARE v_OrgId INT DEFAULT 0;
    SELECT OrgId INTO v_OrgId FROM `event` WHERE EventId = p_EventId LIMIT 1;
    
    IF NOT EXISTS (SELECT 1 FROM `eventregistration` WHERE EventId = p_EventId AND UserId = p_StudentId) THEN
        INSERT INTO `eventregistration` (UserId, EventId, OrgId, DateIssued)
        VALUES (p_StudentId, p_EventId, v_OrgId, CURDATE());
    END IF;
    SELECT 1 AS success, 'Event registration successful' AS message;
END
");

// 53. sp_RecordAttendance
dropAndCreate($conn, 'sp_RecordAttendance', "
CREATE PROCEDURE sp_RecordAttendance(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Method VARCHAR(50),
    IN p_Status VARCHAR(50),
    IN p_LogType VARCHAR(50)
)
BEGIN
    IF p_LogType IS NULL OR p_LogType = '' THEN
        SET p_LogType = 'Log In';
    END IF;
    IF p_Method IS NULL OR p_Method = '' THEN
        SET p_Method = 'qr';
    END IF;
    IF p_Status IS NULL OR p_Status = '' THEN
        SET p_Status = 'present';
    END IF;

    INSERT INTO `attendance` (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType)
    VALUES (p_EventId, p_UserId, p_Method, p_Status, NOW(), p_LogType);

    SELECT 1 AS success, CONCAT(p_LogType, ' recorded successfully') AS message;
END
");

// 54. sp_SubmitPretest
dropAndCreate($conn, 'sp_SubmitPretest', "
CREATE PROCEDURE sp_SubmitPretest(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Score INT,
    IN p_TabSwitches INT,
    IN p_EngagementScore INT,
    IN p_MonitoringFlagged INT
)
BEGIN
    INSERT INTO event_pretest (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt)
    VALUES (p_EventId, p_UserId, p_Score, p_TabSwitches, p_EngagementScore, p_MonitoringFlagged, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        tab_switches = p_TabSwitches,
        engagement_score = p_EngagementScore,
        monitoring_flagged = p_MonitoringFlagged,
        SubmittedAt = NOW();

    INSERT INTO preposttest (EventId, StudentId, TestType, Score, CompletedAt)
    VALUES (p_EventId, p_UserId, 'pre', p_Score, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        CompletedAt = NOW();
END
");

// 55. sp_SubmitPosttest
dropAndCreate($conn, 'sp_SubmitPosttest', "
CREATE PROCEDURE sp_SubmitPosttest(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Score INT,
    IN p_TabSwitches INT,
    IN p_EngagementScore INT,
    IN p_MonitoringFlagged INT
)
BEGIN
    INSERT INTO event_posttest (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt)
    VALUES (p_EventId, p_UserId, p_Score, p_TabSwitches, p_EngagementScore, p_MonitoringFlagged, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        tab_switches = p_TabSwitches,
        engagement_score = p_EngagementScore,
        monitoring_flagged = p_MonitoringFlagged,
        SubmittedAt = NOW();

    INSERT INTO preposttest (EventId, StudentId, TestType, Score, CompletedAt)
    VALUES (p_EventId, p_UserId, 'post', p_Score, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        CompletedAt = NOW();
END
");

echo "=== System Stored Procedures Initialization ===\n";
foreach ($sps as $msg) echo "$msg\n";
echo "\nInitialization Complete.\n";
?>
