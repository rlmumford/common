<?php

/**
 * @file
 * Contains \RLMumford\composer\ScriptHandler.
 */

namespace RLMumford\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class ScriptHandler {

  /**
   * Script to checkout out the counselkit package.
   */
  public static function developRLMumford(Event $event) {
    $rootDir = getcwd();
    $composer = $event->getComposer();
    $rootRequires = $composer->getPackage()->getRequires();
    if (empty($rootRequires['counselkit/counselkit'])) {
      $event->getIO()->write('Cannot find RLMumford Package.');
      return;
    }
    $package = $composer->getRepositoryManager()->getLocalRepository()
      ->findPackage('counselkit/counselkit', $rootRequires['counselkit/counselkit']->getConstraint());
    $installationManager = $composer->getInstallationManager();
    $installPath = $installationManager->getInstallPath($package);
    $fullVersion = $package->getFullPrettyVersion(FALSE);
    $sourceUrl = $package->getSourceUrl();
    list($tagish, $sha1) = explode(' ', $fullVersion, 2);
    $command = "cd {$rootDir}/{$installPath}; ";
    $command .= "git clone {$sourceUrl} .; ";
    $command .= "git checkout {$sha1}; ";
    $command .= "cd $rootDir; ";
    exec($command, $output, $return);
    $event->getIO()->write("Checked out Git Repository for ".$package->getPrettyName()." in {$rootDir}/{$installPath}");
  }

  /**
   * Script to check out any hosted drupal module.
   */
  public static function developModule(Event $event) {
    $module_names = $event->getArguments();
    if (empty($module_names)) {
      $event->getIO()->write("You must specify which modules to develop.");
      return;
    }
    $rootDir = getcwd();
    $composer = $event->getComposer();
    $installationManager = $composer->getInstallationManager();
    foreach ($module_names as $module) {
      $packages = $composer->getRepositoryManager()->getLocalRepository()->findPackages("drupal/{$module}");
      if (empty($packages)) {
        $event->getIO()->write("Cannot find any packages matching {$module}. Skipping.");
        continue;
      }
      foreach ($packages as $package) {
        $installPath = $installationManager->getInstallPath($package);
        $fullVersion = $package->getFullPrettyVersion(FALSE);
        $sourceUrl = $package->getSourceUrl();
        $sourceType = $package->getSourceType();
        $event->getIO()->write("Install Path: {$installPath}\nFull Version: {$fullVersion}\nSource Url: {$sourceUrl}\n Source Type: {$sourceType}");
        $parts = explode(' ', $fullVersion, 2);
        if (isset($parts[1])) {
          $ref = $parts[1];
        }
        else {
          // Here we need to convert a composer version string into a drupal.org tag.
          $tagish = reset($parts);
          preg_match("/(?P<dev>(dev-)*)(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)(-(?P<stability>\w+)(?P<stabnum>\d+))*/", $tagish, $matches);
          $ref = "8.x-{$matches['major']}.".(!empty($matches['dev']) ? 'x-dev' : $matches['minor'].(!empty($matches['stability']) ? "-{$matches['stability']}{$matches['stabnum']}": ""));
        }
        $command = "cd {$rootDir}/{$installPath}; ";
        $command .= "if [ -d \".git\" ]; then echo true; fi";
        exec($command, $output, $return);
        if (in_array("true", $output)) {
          $event->getIO()->write("Git Repository for ".$package->getPrettyName()." is already checked out for development.");
        }
        else {
          $command = "cd {$rootDir}/{$installPath}; ";
          $command .= "git init; ";
          $command .= "git remote add origin {$sourceUrl}; ";
          $command .= "git fetch; ";
          $command .= "git reset {$ref}; ";
          $command .= "git checkout {$ref}; ";
          $command .= "cd $rootDir; ";
          exec($command, $output, $return);
          $event->getIO()->write("Checked out Git Repository for ".$package->getPrettyName()." in {$rootDir}/{$installPath} at {$ref}");
        }
      }
    }
  }
}