<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\FileUploader;
use Gibbon\Services\Format;
use Gibbon\Module\MasteryTranscript\Domain\TranscriptGateway;
use Gibbon\Domain\System\DiscussionGateway;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\NotificationGateway;

require_once '../../gibbon.php';

$masteryTranscriptTranscriptID = $_GET['masteryTranscriptTranscriptID'] ?? '';

$URL = $gibbon->session->get('absoluteURL')."/index.php?q=/modules/Mastery Transcript/journey_record_edit.php&masteryTranscriptJourneyID=$masteryTranscriptTranscriptID";

if (isActionAccessible($guid, $connection2, '/modules/Mastery Transcript/journey_record_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($masteryTranscriptTranscriptID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $transcriptGateway = $container->get(TranscriptGateway::class);
    $result = $container->get(TranscriptGateway::class)->selectJourneyByID($masteryTranscriptTranscriptID);

    if ($result->rowCount() != 1) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $values = $result->fetch();

    if ($values['gibbonPersonIDStudent'] != $gibbon->session->get('gibbonPersonID')) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $discussionGateway = $container->get(DiscussionGateway::class);

    $data = [
        'foreignTable'       => 'masteryTranscriptTranscript',
        'foreignTableID'     => $masteryTranscriptTranscriptID,
        'gibbonModuleID'     => getModuleIDFromName($connection2, 'Mastery Transcript'),
        'gibbonPersonID'     => $gibbon->session->get('gibbonPersonID'),
        'comment'            => $_POST['comment'] ?? '',
        'type'               => $_POST['type'] ?? '',
        'comment'            => $_POST['comment'] ?? '',
        'attachmentType'     => $_POST['evidenceType'] ?? null,
        'attachmentLocation' => $_POST['evidenceLink'] ?? null,
    ];

    //Deal with file upload
    if ($data['attachmentType'] == 'File' && !empty($_FILES['evidenceFile']['tmp_name'])) {
        $fileUploader = new FileUploader($pdo, $gibbon->session);
        $logo = $fileUploader->uploadFromPost($_FILES['evidenceFile'], 'masteryTranscript_evidence_'.$gibbon->session->get('gibbonPersonID'));

        if (!empty($logo)) {
            $data['attachmentLocation'] = $logo;
        }
    }

    // Validate the required values are present
    if (empty($data['type']) || empty($data['comment']) || (!is_null($data['attachmentType']) && empty($data['attachmentLocation']))) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Insert the record
    $inserted = $discussionGateway->insert($data);

    //Update the journey
    $data = [
        'status'                        => ($data['type'] == 'Comment') ? $values['status'] : 'Complete - Pending',
        'timestampCompletePending'      => ($data['type'] == 'Comment') ? null : date('Y-m-d H:i:s')
    ];
    $updated = $transcriptGateway->update($masteryTranscriptTranscriptID, $data);

    //Notify mentor
    $notificationGateway = new NotificationGateway($pdo);
    $notificationSender = new NotificationSender($notificationGateway, $gibbon->session);
    if ($data['status'] == 'Complete - Pending') {
        $notificationString = __m('{student} has requested approval for the {type} {name}.', ['student' => Format::name('', $gibbon->session->get('preferredName'), $gibbon->session->get('surname'), 'Student', false, true), 'type' => strtolower($values['type']), 'name' => $values['name']]);
    }
    else {
        $notificationString = __m('{student} has added to the journey log for the {type} {name}.', ['student' => Format::name('', $gibbon->session->get('preferredName'), $gibbon->session->get('surname'), 'Student', false, true), 'type' => strtolower($values['type']), 'name' => $values['name']]);
    }
    $notificationSender->addNotification($values['gibbonPersonIDSchoolMentor'], $notificationString, "Mastery Transcript", "/index.php?q=/modules/Mastery Transcript/journey_manage_edit.php&masteryTranscriptTranscriptID=$masteryTranscriptTranscriptID");
    $notificationSender->sendNotifications();


    $URL .= !$inserted && !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}
