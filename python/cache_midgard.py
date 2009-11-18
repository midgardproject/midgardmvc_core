# -*- coding: utf-8 -*-
import _midgard as midgard
import dbus.mainloop.glib
import gobject
import configuration
# import midcom_cache
import sys

class cache_midgard:
    cnc = ''
    host = ''
    cache_domain = 'midcom_core_services_cache_midgard'
    
    def __init__(self, cnc, host):
        self.cnc = cnc
        self.host = host
    
    def test(self):
        print self.cnc
        
    def delete(self, module, identifier):
        args = [["domain", self.cache_domain + ':' + module], ["name", identifier]] 
        self.host.delete_parameters(args)

    def delete_all(self, module):
        args = [["domain", self.cache_domain + ':' + module]] 
        self.host.delete_parameters(args)
