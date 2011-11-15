#!/bin/bash

# Install TAL
sudo apt-get install -y php-pear
sudo pear install http://phptal.org/latest.tar.gz 

# Install Midgard from PPA
sudo add-apt-repository "deb http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/ ./"
sudo apt-get update
sudo apt-get install -y php5-midgard2
