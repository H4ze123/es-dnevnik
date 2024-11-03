<?php

namespace Drupal\elekDnevnik\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElekStudentActivityController extends ControllerBase {

    protected $currentUser;

    public function __construct(AccountInterface $current_user) {
        $this->currentUser = $current_user;
    }

    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('current_user')
        );
    }

    public function viewActivity() {
        $current_user = \Drupal::currentUser();
        $connection = \Drupal::database();

        $user_data = $connection->query("SELECT id FROM {user_registration} WHERE username = :username", [
            ':username' => $current_user->getAccountName()
        ])->fetchAssoc();

        $student_id = $user_data['id'];

        $user_query = Database::getConnection()->select('user_registration', 'u')
            ->fields('u', ['role'])
            ->condition('id', $student_id, '=');
        $student_role = $user_query->execute()->fetchfield();

        $odeljenje = strtoupper(str_replace('odeljenje_', '', explode(',', $student_role)[0]));

        $activity_query = Database::getConnection()->select('student_activity', 'g')
            ->fields('g', ['naziv_predmeta', 'vrsta_aktivnosti', 'datum_aktivnosti'])
            ->condition('odeljenje', $odeljenje, '=');
        $activities = $activity_query->execute()->fetchAll();

        if (empty($activities)) {
            \Drupal::logger('elekDnevnik')->info('Nema zakazanih aktivnosti za odeljenje: @odeljenje', ['@odeljenje' => $odeljenje]);
        }

        $rows = [];
        foreach ($activities as $activity) {
            $rows[] = [
                'datum_aktivnosti' => $activity->datum_aktivnosti,
                'naziv_predmeta' => ucfirst(str_replace('_', ' ', $activity->naziv_predmeta)),
                'vrsta_aktivnosti' => ucfirst(str_replace('_', ' ', $activity->vrsta_aktivnosti)),
            ];
        }

        $header = [
            'datum_aktivnosti' => $this->t('Datum aktivnosti'),
            'naziv_predmeta' => $this->t('Predmet'),
            'vrsta_aktivnosti' => $this->t('Vrsta aktivnosti'),
        ];

        $build = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('Nemate zabeležene aktivnosti.'),
        ];

        return $build;
    }
}
