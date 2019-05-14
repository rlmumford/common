<?php

/**
 * Implements hook_views_data_alter().
 */
function user_role_labels_views_data_alter(&$data) {
  $data['users_field_data']['name']['filter']['id'] = 'user_name_role_labels_filter';
  $data['users_field_data']['role_label'] = [
    'title' => t('Label (Role Aware)'),
    'help' => t('Display the user label, with option to specify a role context.'),
    'field' => [
      'id' => 'user_role_label',
    ],
  ];
}
