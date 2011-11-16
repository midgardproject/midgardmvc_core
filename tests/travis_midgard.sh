#!/bin/bash

# Install TAL
pyrus install http://phptal.org/latest.tar.gz 

# Install Midgard from OBS
sudo apt-get install -y dbus libgda-4.0-4 php5-dev
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/libmidgard2-2010_10.05.5.1-1_i386.deb
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/midgard2-common_10.05.5.1-1_i386.deb 
wget http://download.opensuse.org/repositories/home:/midgardproject:/ratatoskr/xUbuntu_10.04/i386/php5-midgard2_10.05.5.1-1_i386.deb
sudo dpkg -i --force-depends libmidgard2-2010_10.05.5.1-1_i386.deb
sudo dpkg -i midgard2-common_10.05.5.1-1_i386.deb
sudo dpkg -i php5-midgard2_10.05.5.1-1_i386.deb

# We need to enable Midgard correctly
mkdir -p ~/.phpfarm/inst/php-5.3.8/lib/php/extensions/debug-non-zts-20090626
EXT_DIR=`php-config5 --extension-dir`
cp $EXT_DIR/midgard2.so ~/.phpfarm/inst/php-5.3.8/lib/php/extensions/debug-non-zts-20090626/midgard2.so
echo "extension=midgard2.so" >> ~/.phpfarm/inst/php-5.3.8/lib/php.ini
