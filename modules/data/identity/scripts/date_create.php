<?php

use Drupal\identity\IdentityDataGroup;

$identity_data_storage = \Drupal::entityTypeManager()->getStorage('identity_data');

$phone_number = $identity_data_storage->create([
'type' => 'telephone_number',
'telephone_number' => '02089493303',
'can_sms' => TRUE,
]);
$name = $identity_data_storage->create([
'type' => 'personal_name',
'full_name' => 'James Everall',
'name' => [
'given' => 'James',
'family' => 'Everall',
],
'name_type' => 'full',
]);

$group = new IdentityDataGroup([$name, $phone_number]);

/** @var \Drupal\identity\IdentityDataIdentityAcquirer $identity_acquirer */
$identity_acquirer = \Drupal::service('identity.acquirer');
$result = $identity_acquirer->acquireIdentity($group);

$identity = $result->getIdentity();
foreach ($group->getDatas() as $data) {
  $data->setIdentity($identity)->skipIdentitySave()->save();
}
$identity->save();
dpm($group);

dpm($name);
dpm($phone_number);
