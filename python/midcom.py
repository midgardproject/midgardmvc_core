# -*- coding: utf-8 -*-
import _midgard as midgard
import dbus.mainloop.glib
import gobject
import configuration
import cache_midgard
import sys

if len(sys.argv) != 4:
    print "Usage: python midcom.py configuration sitegroup host"
    sys.exit()

cnc = midgard.connection()
cnc.open(sys.argv[1])

if cnc.set_sitegroup(sys.argv[2]) == False:
    print ("Sitegroup %s not found" % (sys.argv[2]))
    sys.exit()

qb = midgard.query_builder('midgard_host')
qb.add_constraint('name', '=', sys.argv[3])
res = qb.execute()
if len(res) == 0:
    print ("Host %s not found" % (sys.argv[3]))
    sys.exit()
host = res[0]

# Testing cache
cache = cache_midgard.cache_midgard(cnc, host)
cache.delete_all('test')
cache.delete('test_domain', 'test')

# Testing configuration
conf = configuration.configuration()
conf.load_component_configuration("midcom_core")
print conf.get('authentication_configuration', 'fallback_translation')
