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

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\MasteryTranscript\Domain\TranscriptGateway;

if (isActionAccessible($guid, $connection2, '/modules/Mastery Transcript/transcripts_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Manage Transcripts'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $page->addMessage(sprintf(__m('Published Mastery Transcripts can be viewed using %1$sthis link%2$s and the student-specific codes shown in the table below.'),"<a href='https://transcript.mastery.org' target='_blank'>", "</a>"));

    // Query categories
    $transcriptGateway = $container->get(TranscriptGateway::class);

    $criteria = $transcriptGateway->newQueryCriteria()
        ->sortBy(['sequenceNumber', 'name'])
        ->fromPOST();

    $transcripts = $transcriptGateway->selectAllTranscripts($criteria);

    // Render table
    $table = DataTable::createPaginated('transcripts', $criteria);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Mastery Transcript/transcripts_manage_add.php')
        ->displayLabel();

    $table->addColumn('schoolYear', __('School Year'));

    $table->addColumn('student', __('Student'))
        ->notSortable()
        ->format(function($values) use ($guid) {
            return Format::name('', $values['preferredName'], $values['surname'], 'Student', false, true);
        });

    $table->addColumn('status', __m('Status'));

    $table->addColumn('code', __m('Code'));

    // ACTIONS
    $table->addActionColumn()
        ->addParam('masteryTranscriptTranscriptID')
        ->format(function ($category, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/Mastery Transcript/transcripts_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/Mastery Transcript/transcripts_manage_delete.php');
        });

    echo $table->render($transcripts);
}