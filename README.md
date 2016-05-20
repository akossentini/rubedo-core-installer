# Composer installer plugin for Rubedo core

## About

This Composer installer plugin let you install Rubedo Core as a project dependency with Composer.

## How it works

The plugin will :

* clone Rubedo repository into a temporary dir
* retrieve the list of files removed from the previous release installed if a previous version of Rubedo Core has been installed
* remove files and directories, from Rubedo repository, that we don't want to install on our project
* copy the files / directories from the temporary dir into the project's Rubedo root dir
* delete, from the project's Rubedo root dir, the files removed since the previous release installed, if any

Beware that, for this to work, Rubedo Core package version should be typed as "rubedo-core"

## Installation

    COMPOSER=composer.project.json php composer.phar require novactive/rubedo-core-installer 


## Configuration

You can add the following [extra](https://getcomposer.org/doc/04-schema.md#extra) parameters into your project's composer file :

Configuration          | Type             | Description                                                                                       |
-----------------------|------------------|---------------------------------------------------------------------------------------------------|
rubedo-root-dir        | string           | Path to rubedo root directory                                                                     |
rubedo-files-to-ignore | array of strings | List of files from Rubedo Core repository that should not be installed (default: .gitignore)      |
rubedo-dirs-to-ignore  | array of strings | List of dirs from Rubedo Core repository that should not be installed (default: .git, extensions) |
