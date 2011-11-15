#!/bin/bash

# Install TAL
sudo apt-get install -y php-pear
sudo pear install http://phptal.org/latest.tar.gz 

# Install Midgard from OBS
sudo apt-get install -y dbus
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/all/midgard-libgda-4.0-common_4.0.12-1_all.deb 
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/midgard-libgda-4.0-4_4.0.12-1_i386.deb
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/libmidgard2-2010_10.05.5-1_i386.deb
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/midgard2-common_10.05.5-1_i386.deb 
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/php5-midgard2_10.05.5-1_i386.deb
sudo dpkg -i midgard-libgda-4.0-common_4.0.12-1_all.deb  
sudo dpkg -i midgard-libgda-4.0-4_4.0.12-1_i386.deb
sudo dpkg -i libmidgard2-2010_10.05.5-1_i386.deb
sudo dpkg -i midgard2-common_10.05.5-1_i386.deb
sudo dpkg -i php5-midgard2_10.05.5-1_i386.deb
