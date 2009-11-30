# -*- coding: utf-8 -*-
import os
import yaml
import sys
class configuration:
    components = []
    component = ''
    path = ''
    merged = []
    
    def __init__(self):
        # self.component = component
        # self.components.append(component)
        
        # A bit of kludge solution but it works
        # This way we can determine the path where the /midgardmvc_core/python
        # is and reverse to the root of the installation
        os.chdir(sys.path[0])
        os.chdir('..')
        os.chdir('..')
        self.path = os.getcwd()        
   
    def load_component_configuration(self, component_name):
        path = self.path + '/' + component_name + '/configuration/defaults.yml'
        self.load_file(path)
    
    def load_file(self, file_path):
        
        if os.path.exists(file_path):
            f = open(file_path)
            data = f.read()
        else:
            return False
        
        self.merged = yaml.load(data)        
        return self.merged
        
    def get(self, key, subkey = False):
        
        if (subkey != False):
            return self.merged[key][subkey]
        
        return self.merged[key]
        