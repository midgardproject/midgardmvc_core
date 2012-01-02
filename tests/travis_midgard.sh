#!/bin/bash

# Install Midgard2
./tests/travis_midgard2.sh

# Install dependencies with Composer
wget http://getcomposer.org/composer.phar 
php composer.phar install
