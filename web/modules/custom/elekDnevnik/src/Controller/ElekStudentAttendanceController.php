<?php

namespace Drupal\elekDnevnik\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElekStudentAttendanceController extends ControllerBase {

    protected $currentUser;

    public function __construct(AccountInterface $current_user) {
        $this->currentUser = $current_user;
    }

    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('current_user')
        );
    }

    public function viewAttendance() {
        $current_user = \Drupal::currentUser();
        $connection = \Drupal::database();

        $user_data = $connection->query("SELECT id FROM {user_registration} WHERE username = :username", [
            ':username' => $current_user->getAccountName()
        ])->fetchAssoc();

        $student_id = $user_data['id'];

        $query = Database::getConnection()->select('student_attendance', 'a');
        $query->fields('a', ['date']);
        $query->condition('student_id', $student_id);
        $query->condition('is_absent', 1);

        $results = $query->execute()->fetchAll();

        $attendance_by_date = [];
        foreach ($results as $result) {
            $date = $result->date;
            if (!isset($attendance_by_date[$date])) {
                $attendance_by_date[$date] = 0;
            }
            $attendance_by_date[$date]++;
        }

        $rows = [];
        foreach ($attendance_by_date as $date => $count) {
            $rows[] = [
                'date' => $date,
                'count' => $count,
            ];
        }

        $header = [
            'date' => $this->t('Datum'),
            'count' => $this->t('Izostanci'),
        ];

        $build = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('Nemate zabeležene izostanke.'),
        ];

        return $build;
    }
}
