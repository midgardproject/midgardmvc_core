#!/bin/bash

# Install TAL
sudo apt-get install -y php-pear
sudo pear install http://phptal.org/latest.tar.gz 

# Install Midgard from OBS
sudo apt-get install -y dbus libgda-4.0-4 
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/libmidgard2-2010_10.05.5-1_i386.deb
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/midgard2-common_10.05.5-1_i386.deb 
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/php5-midgard2_10.05.5-1_i386.deb
sudo dpkg -i --force-depends libmidgard2-2010_10.05.5-1_i386.deb
sudo dpkg -i midgard2-common_10.05.5-1_i386.deb
sudo dpkg -i php5-midgard2_10.05.5-1_i386.deb

# We need to enable Midgard correctly
php --ini
