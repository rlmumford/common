<?php

namespace Drupal\flexilayout_builder\Plugin\Relationship;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\ctools\Plugin\RelationshipInterface;

interface ConfigurableRelationshipInterface extends ConfigurablePluginInterface, RelationshipInterface, PluginWithFormsInterface, PluginFormInterface {

}
