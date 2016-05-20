# Composer installer plugin for Rubedo core

## About

This Composer installer plugin lets
 syou install Rubedo Core as a project dependency with Composer.

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

## Contributing

In order to be accepted, your contribution needs to pass a few controls : 

* PHP files should be valid
* PHP files should follow the [PSR-2](http://www.php-fig.org/psr/psr-2/) standard
* PHP files should be [phpmd](https://phpmd.org) and [phpcpd](https://github.com/sebastianbergmann/phpcpd) warning/error free

To ease the validation process, install the [pre-commit framework](http://pre-commit.com) and install the repository pre-commit hook :

    pre-commit install

Finally, in order to homogenize commit messages across contributors (and to ease generation of the CHANGELOG), please apply this [git commit message hook](https://gist.github.com/GMaissa/f008b2ffca417c09c7b8) onto your local repository.  
