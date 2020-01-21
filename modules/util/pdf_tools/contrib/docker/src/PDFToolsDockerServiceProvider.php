<?php

namespace Drupal\pdf_tools_docker;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class PDFToolsDockerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('pdf_tools.generator');
    $definition->setClass(DockerWKHTMLtoPDFGenerator::class);
    $definition->addArgument(new Reference('config.factory'));
    $definition->addArgument(new Reference('http_client'));
  }

}
