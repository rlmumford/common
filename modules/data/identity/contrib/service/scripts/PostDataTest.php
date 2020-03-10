<?php

use Symfony\Component\HttpFoundation\Request;
use Drupal\identity_service\Controller\ServiceController;

$json = '{"data":[{"role":"individual","class":"role","type":"universal","reference":"caseinfo_party:party-375a420e:role"},{"class":"personal_name","type":"full","reference":"caseinfo_party:party-375a420e:name","full_name":"E. L. Clark","is_formal":false},{"class":"address","type":"mailing","reference":"caseinfo_party:party-375a420e:address","address":{"langcode":null,"country_code":"US","administrative_area":"GA","locality":"Atlanta","dependent_locality":null,"postal_code":"30341","sorting_code":null,"address_line1":"Bldg. 3","address_line2":"3300 Northeast Expwy.","organization":"Clark & Washington, LLC","given_name":"E.","additional_name":"L.","family_name":"Clark"}},{"other_identity":"669bafaf-8d58-4410-a948-bcc2531bed09","class":"works_at","type":"unknown","reference":"caseinfo_party:party-375a420e:firm"}],"source":{"app":"intel","reference":"caseinfo_party:party-375a420e","label":"E. L. Clark (Case Info Party)","notification_url":"\/api\/notify\/identity"}}';

$request = Request::create(
  'https://id.counselkit.com/api/identity/data',
  'POST',
  ['_format' => 'json'],
  [],
  [],
  ['CONTENT_TYPE' => 'application/json'],
  $json
);

$controller = ServiceController::create(\Drupal::getContainer());
$start = microtime(TRUE);
dpm($controller->postData($request));
dpm(microtime(TRUE) - $start, 'Total time');
