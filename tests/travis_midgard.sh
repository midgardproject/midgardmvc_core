#!/bin/bash

# Install TAL
pyrus install http://phptal.org/latest.tar.gz 

# Install Pake
pyrus channel-discover pear.indeyets.ru
pyrus install -f http://pear.indeyets.ru/get/pake-1.6.3.tgz

# Install Midgard2 library from OBS
sudo apt-get install -y dbus libgda-4.0-4 php5-dev
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/libmidgard2-2010_10.05.5.1-1_i386.deb
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/midgard2-common_10.05.5.1-1_i386.deb 
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/libmidgard2-dev_10.05.5.1-1_i386.deb 
sudo dpkg -i --force-depends libmidgard2-2010_10.05.5.1-1_i386.deb
sudo dpkg -i midgard2-common_10.05.5.1-1_i386.deb
sudo dpkg -i libmidgard2-dev_10.05.5.1-1_i386.deb

# Build and install Midgard2 PHP extension
wget https://github.com/midgardproject/midgard-php5/tarball/ratatoskr
tar zxf ratatoskr
sh -c "cd midgardproject-midgard-php5-*&&`pyrus get php_dir|tail -1`/pake test"
